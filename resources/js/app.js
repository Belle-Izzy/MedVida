const app = document.querySelector('#app');
const testMode = document.querySelector('meta[name="medvida-teste-local"]')?.content === '1';
const prototypeMode = document.querySelector('meta[name="medvida-prototipo"]')?.content === '1';
const sessionKey = 'medvida-session';
const resetVersion = 'medvida-reset-2026-10-08';
if (localStorage.getItem('medvida-data-version') !== resetVersion) {
    for (const key of Object.keys(localStorage)) {
        if (key.startsWith('medvida-')) localStorage.removeItem(key);
    }
    localStorage.setItem('medvida-data-version', resetVersion);
    if ('serviceWorker' in navigator) navigator.serviceWorker.getRegistration()
        .then(registration => registration?.pushManager?.getSubscription())
        .then(subscription => subscription?.unsubscribe()).catch(() => {});
}
const specialties = ['Cardiologia', 'Ortopedia', 'Neurologia', 'Dermatologia', 'Oftalmologia', 'Clínico Geral', 'Gastroenterologia', 'Endocrinologia'];

let session = JSON.parse(localStorage.getItem(sessionKey) || 'null');
let accessType = 'paciente';
let clinicDraft = null;
let selectedSlot = null;
let clinicDoctors = [];
let listedDoctors = [];
let detailReturn = 'consultas';
let activeConfirmation = null;
let pushPublicKey = null;
let launchDestination = new URLSearchParams(location.search).get('notificacao');
const pushOwnerKey = 'medvida-push-owner';

const firstName = name => (name || '').trim().split(/\s+/)[0] || '';
const clinicName = name => /^cl[ií]nica/i.test(name || '') ? name : `Clínica ${firstName(name)}`;
const shell = content => `<div class="phone">${content}</div>`;
const logo = () => `<img class="logo-image" src="/logo-medvida.png" alt="MedVida"><p class="tagline">Sua Saúde, Conectada.</p>`;
const logoutLink = () => `<p class="logout-row"><button class="link-btn" data-action="logout">Sair</button></p>`;
const notificationControl = () => `<section class="notification-control"><button type="button" class="link-btn" data-action="push-toggle" disabled>Carregando notificações...</button><p id="push-status" role="status"></p></section>`;
const message = text => `<section class="card"><p class="subheading center">${escapeHtml(text)}</p></section>`;
const formError = () => `<p class="error" id="form-error" hidden></p>`;
const optionList = () => specialties.map(item => `<option value="${item}">${item}</option>`).join('');
const backHeader = (title, target) => `<div class="topline"><button type="button" class="back-button" data-back="${target}" aria-label="Voltar">‹</button><span>${title}</span></div>`;
const pageHeading = (title, target) => `<div class="page-heading"><button type="button" class="back-button" data-back="${target}" aria-label="Voltar">‹</button><h1 class="screen-title">${title}</h1></div>`;
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
const statusText = status => ({ pendente: 'Aguardando confirmação', confirmada: 'Confirmada', recusada: 'Recusada', cancelada: 'Cancelada' })[status] || status;
const dateText = value => {
    const parts = String(value || '').slice(0, 10).split('-');
    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : String(value || '');
};
const canCancel = item => {
    if (!['pendente', 'confirmada'].includes(item.status)) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    today.setDate(today.getDate() + 2);
    return String(item.data || '').slice(0, 10) >= `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
};
const clinicTabs = (active, count = '0') => `<div class="clinic-tabs"><button class="${active === 'clinic' ? 'active' : ''}" data-go="clinic">Agenda</button><button class="${active === 'pending' ? 'active' : ''}" data-go="pending">Pendentes (<span id="pending-count">${count}</span>)</button><button class="${active === 'clinic-medics' ? 'active' : ''}" data-go="clinic-medics">Médicos</button></div>`;

async function api(path, options = {}) {
    const headers = { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...options.headers };
    if (session?.token) headers.Authorization = `Bearer ${session.token}`;
    const response = await fetch(`/api/${path}`, { ...options, headers });
    const data = await response.json().catch(() => ({}));
    if (response.status === 401 && session) {
        session = null;
        localStorage.removeItem(sessionKey);
        localStorage.removeItem(pushOwnerKey);
        if ('serviceWorker' in navigator) navigator.serviceWorker.getRegistration().then(registration => registration?.pushManager?.getSubscription()).then(subscription => subscription?.unsubscribe()).catch(() => {});
        access();
    }
    if (!response.ok) throw new Error(data.message || 'Não foi possível concluir esta ação.');
    return data;
}

function saveSession(data) {
    session = data;
    localStorage.setItem(sessionKey, JSON.stringify(data));
}

async function logout() {
    const token = session?.token;
    session = null;
    localStorage.removeItem(sessionKey);
    localStorage.removeItem(pushOwnerKey);
    access();
    try {
        const registration = await navigator.serviceWorker?.getRegistration();
        const subscription = await registration?.pushManager?.getSubscription();
        await subscription?.unsubscribe();
    } catch (_) {}
    if (token) fetch('/api/logout', { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }).catch(() => {});
}

const currentPushOwner = () => session ? `${session.tipo}:${session.usuario.id}` : '';
const decodePushKey = key => {
    const base64 = (key + '='.repeat((4 - key.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(base64), character => character.charCodeAt(0));
};

async function refreshNotificationControl() {
    const button = document.querySelector('[data-action="push-toggle"]');
    const status = document.querySelector('#push-status');
    if (!button || !status) return;
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        button.textContent = 'Notificações indisponíveis neste navegador';
        status.textContent = 'No iPhone, adicione o MedVida à Tela de Início.';
        return;
    }
    try {
        const config = await api('push/config');
        if (!config.public_key) throw new Error('As notificações ainda não foram configuradas neste servidor.');
        pushPublicKey = config.public_key;
        const registration = await navigator.serviceWorker.getRegistration();
        if (!registration?.active) throw new Error('Atualize a página para ativar as notificações.');
        const subscription = await registration.pushManager.getSubscription();
        if (!document.contains(button)) return;
        const active = subscription && localStorage.getItem(pushOwnerKey) === currentPushOwner();
        button.textContent = active ? 'Desativar notificações' : 'Ativar notificações';
        button.disabled = Notification.permission === 'denied' && !active;
        status.textContent = Notification.permission === 'denied'
            ? 'Permita notificações nas configurações do navegador para ativá-las.'
            : active ? 'Avisos deste dispositivo estão ativados.' : 'Receba avisos mesmo com o app fechado.';
    } catch (error) {
        if (!document.contains(button)) return;
        button.textContent = 'Notificações indisponíveis';
        status.textContent = error.message;
    }
}

async function toggleNotifications() {
    const button = document.querySelector('[data-action="push-toggle"]');
    const status = document.querySelector('#push-status');
    if (!button || !status || !pushPublicKey) return;
    button.disabled = true;
    try {
        const active = localStorage.getItem(pushOwnerKey) === currentPushOwner();
        if (!active && Notification.permission !== 'granted') {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') throw new Error('Você não autorizou as notificações neste dispositivo.');
        }
        const registration = await navigator.serviceWorker.ready;
        let subscription = await registration.pushManager.getSubscription();
        if (active) {
            if (subscription) {
                try { await api('push/subscriptions', { method: 'DELETE', body: JSON.stringify({ endpoint: subscription.endpoint }) }); }
                catch (_) { /* O endpoint será descartado pelo serviço de push após a inscrição local ser removida. */ }
                await subscription.unsubscribe();
            }
            localStorage.removeItem(pushOwnerKey);
        } else {
            subscription ||= await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: decodePushKey(pushPublicKey) });
            try {
                await api('push/subscriptions', { method: 'POST', body: JSON.stringify(subscription.toJSON()) });
                localStorage.setItem(pushOwnerKey, currentPushOwner());
            } catch (error) {
                await subscription.unsubscribe();
                throw error;
            }
        }
        refreshNotificationControl();
    } catch (error) {
        status.textContent = error.message;
        button.disabled = false;
    }
}

function bottomNav(active) {
    return `<nav class="navbottom"><button class="${active === 'home' ? 'active' : ''}" data-go="home"><span>🏠</span>Início</button><button class="${active === 'book' ? 'active' : ''}" data-go="book"><span>🗓️</span>Agendar</button><button class="${active === 'consultas' ? 'active' : ''}" data-go="consultas"><span>📋</span>Consultas</button></nav>`;
}

function access(notice = '') {
    const verified = new URLSearchParams(location.search).has('email_verificado');
    if (verified) history.replaceState(null, '', location.pathname + location.hash);
    app.innerHTML = shell(`${logo()}${verified ? message('E-mail confirmado! Agora você pode entrar.') : ''}${notice ? message(notice) : ''}<h1 class="screen-title center">Como deseja entrar?</h1><div class="tabs"><button class="${accessType === 'paciente' ? 'active' : ''}" data-type="paciente">Sou paciente</button><button class="${accessType === 'clinica' ? 'active' : ''}" data-type="clinica">Sou clínica</button></div><form id="login-form" class="card"><h2>Entrar</h2><label>E-mail<input name="email" type="email" required></label><label>Senha<span class="password-field"><input name="senha" type="password" required><button type="button" class="eye" data-eye><img src="/icon-eye-open.png" alt="Mostrar senha"></button></span></label><button class="primary">Entrar</button>${formError()}${accessType === 'paciente' || prototypeMode || !testMode ? '<p class="center"><button class="link-btn" type="button" data-go="register">Ainda não tenho conta</button></p>' : '<p class="center">Clínicas de teste são criadas pela administração.</p>'}${testMode ? '' : '<p class="center"><button class="link-btn" type="button" data-action="resend-email">Reenviar confirmação por e-mail</button></p>'}</form>`);
    document.querySelector('#login-form').onsubmit = login;
}

async function login(event) {
    event.preventDefault();
    try {
        const data = await api('auth/login', { method: 'POST', body: JSON.stringify({ ...Object.fromEntries(new FormData(event.target)), tipo: accessType }) });
        saveSession(data);
        if (data.tipo === 'clinica') clinicHasAccess(data.usuario) ? (launchDestination === 'pending' ? pending() : clinicDashboard()) : clinicPayment();
        else launchDestination === 'consultas' ? consultas() : home();
        launchDestination = null;
    } catch (error) { showError(error.message); }
}

function register() {
    accessType === 'clinica' ? clinicRegister() : patientRegister();
}

function patientRegister() {
    app.innerHTML = shell(`${backHeader('Cadastro de paciente', 'access')}${logo()}<form id="patient-form" class="card"><label>Nome completo<input name="nome" required></label><label>CPF<input name="cpf" required></label><label>Telefone<input name="telefone" required></label><label>Data de nascimento<input name="data_nascimento" type="date"></label><label>Plano de saúde<input name="plano"></label><label>E-mail<input name="email" type="email" required></label><label>Senha<span class="password-field"><input name="senha" type="password" minlength="8" required><button type="button" class="eye" data-eye><img src="/icon-eye-open.png" alt="Mostrar senha"></button></span></label><button class="primary">Criar conta</button>${formError()}</form>`);
    document.querySelector('#patient-form').onsubmit = event => createAccount(event, 'pacientes');
}

function clinicRegister() {
    app.innerHTML = shell(`${backHeader('Cadastro da clínica', 'access')}${logo()}<form id="clinic-form" class="card"><label>Nome da clínica<input name="nome" value="${escapeHtml(clinicDraft?.nome)}" required></label><label>Telefone<input name="telefone" value="${escapeHtml(clinicDraft?.telefone)}" required></label><label>Endereço<input name="endereco" value="${escapeHtml(clinicDraft?.endereco)}" required></label><label>Planos que atende<input name="plano_atendido" value="${escapeHtml(clinicDraft?.plano_atendido)}" placeholder="Unimed, Amil, Particular"></label><label>E-mail<input name="email" type="email" value="${escapeHtml(clinicDraft?.email)}" required></label><label>Senha<span class="password-field"><input name="senha" type="password" minlength="8" value="${escapeHtml(clinicDraft?.senha)}" required><button type="button" class="eye" data-eye><img src="/icon-eye-open.png" alt="Mostrar senha"></button></span></label><button class="primary">Criar conta</button>${formError()}</form>`);
    document.querySelector('#clinic-form').onsubmit = event => {
        event.preventDefault();
        clinicDraft = Object.fromEntries(new FormData(event.target));
        if (prototypeMode) registerPrototypeClinic();
        else clinicPlan();
    };
}

function clinicPlan() {
    if (prototypeMode) {
        app.innerHTML = shell(`${backHeader('Plano de Acesso', 'access')}<section class="card"><p class="subheading">Sua clínica foi cadastrada. Ative a conta para começar a atender pacientes pelo MedVida.</p></section><section class="plan"><div>Plano<br>Anual<br><small>Acesso completo por 12 meses</small></div><strong>R$<br>4.800,00</strong></section><section class="card"><p class="subheading">💳 Nesta versão de apresentação, a ativação é gratuita e nenhuma cobrança será feita.</p></section><button class="primary" data-action="pay">Ativar conta sem pagamento</button>${formError()}`);
        return;
    }
    app.innerHTML = shell(`${backHeader('Plano de Acesso', 'clinic-register')}<section class="card"><p class="subheading">Para ativar sua conta e começar a atender pacientes pelo MedVida, escolha o plano anual:</p></section><section class="plan"><div>Plano<br>Anual<br><small>Acesso completo por 12 meses</small></div><strong>R$<br>4.800,00</strong></section><section class="card"><p class="subheading">💳 Primeiro confirme seu e-mail. Depois você escolherá a forma de pagamento no ambiente seguro do Mercado Pago. A conta só será ativada após a confirmação do pagamento.</p></section><button class="primary" data-action="pay">Criar conta e confirmar e-mail</button>${formError()}`);
}

async function registerPrototypeClinic() {
    const button = document.querySelector('#clinic-form button.primary');
    button.disabled = true;
    try {
        const result = await api('clinicas', { method: 'POST', body: JSON.stringify(clinicDraft) });
        saveSession({ token: result.activation_token, tipo: 'clinica', usuario: result.clinica });
        clinicDraft = null;
        clinicPlan();
    } catch (error) {
        button.disabled = false;
        showError(error.message);
    }
}

async function createAccount(event, endpoint) {
    event.preventDefault();
    try { const result = await api(endpoint, { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(event.target))) }); if (testMode) { access(); } else verificationNotice(result.email_enviado); } catch (error) { showError(error.message); }
}

async function activateClinic() {
    if (prototypeMode) {
        const button = document.querySelector('[data-action="pay"]');
        button.disabled = true;
        try {
            await api('clinicas/prototipo/ativar', { method: 'POST' });
            session = null;
            localStorage.removeItem(sessionKey);
            clinicDraft = null;
            accessType = 'clinica';
            access('Clínica ativada! Entre com seu e-mail e senha.');
        } catch (error) {
            button.disabled = false;
            showError(error.message);
        }
        return;
    }
    try {
        const result = await api('clinicas', { method: 'POST', body: JSON.stringify(clinicDraft) });
        clinicDraft = null;
        verificationNotice(result.email_enviado);
    } catch (error) { showError(error.message); }
}

function verificationNotice(emailSent = true) {
    app.innerHTML = shell(`${logo()}<section class="card"><h2>Confirme seu e-mail</h2><p>${emailSent ? 'Enviamos um link de confirmação. Abra seu e-mail e toque no link' : 'A conta foi criada, mas o envio real de e-mails ainda não está configurado. Peça a configuração e depois solicite um novo link'} antes de entrar${accessType === 'clinica' ? ' e seguir para o pagamento' : ''}.</p><p>O link vence em 60 minutos. Confira também a pasta de spam.</p></section><button class="primary" data-go="access">Voltar ao login</button><p class="center"><button class="link-btn" data-action="resend-email">Reenviar link</button></p>`);
}

function resendEmailForm() {
    app.innerHTML = shell(`${backHeader('Reenviar confirmação', 'access')}${logo()}<form id="resend-email-form" class="card"><label>E-mail<input name="email" type="email" required></label><label>Senha<span class="password-field"><input name="senha" type="password" required><button type="button" class="eye" data-eye><img src="/icon-eye-open.png" alt="Mostrar senha"></button></span></label><button class="primary">Reenviar link</button>${formError()}</form>`);
    document.querySelector('#resend-email-form').onsubmit = async event => {
        event.preventDefault();
        try {
            const result = await api('auth/email/resend', { method: 'POST', body: JSON.stringify({ ...Object.fromEntries(new FormData(event.target)), tipo: accessType }) });
            app.innerHTML = shell(`${logo()}${message(result.message)}<button class="primary" data-go="access">Voltar ao login</button>`);
        } catch (error) { showError(error.message); }
    };
}

const clinicHasAccess = clinic => clinic?.status_cadastro === 'ativa' && clinic?.acesso_ate && new Date(clinic.acesso_ate).getTime() > Date.now();

function clinicPayment() {
    if (prototypeMode) {
        clinicPlan();
        return;
    }
    app.innerHTML = shell(`${logoutLink()}${logo()}<h1 class="screen-title center">Plano anual MedVida</h1><section class="card"><p class="subheading center">Sua clínica ainda não tem acesso ativo. O plano custa R$ 4.800,00 por 12 meses. A renovação não é automática.</p><p class="center">Se você já pagou, confira a confirmação abaixo. Pix e boleto podem levar algum tempo para ser aprovados.</p></section><button class="primary" data-action="clinic-check-status">Verificar pagamento</button><button class="primary" data-action="clinic-checkout">Abrir pagamento</button>${formError()}`);
    if (new URLSearchParams(location.search).has('pagamento')) {
        history.replaceState(null, '', location.pathname + location.hash);
        refreshClinicPayment();
    }
}

async function refreshClinicPayment() {
    try {
        const status = await api('clinicas/pagamento/status');
        const me = await api('me');
        session.usuario = me.usuario;
        localStorage.setItem(sessionKey, JSON.stringify(session));
        if (status.ativo) clinicDashboard();
        else showError('O pagamento ainda não foi confirmado. Você pode verificar novamente em instantes.');
    } catch (error) { showError(error.message); }
}

async function openClinicCheckout() {
    try {
        const payment = await api('clinicas/pagamento/checkout', { method: 'POST' });
        location.assign(payment.checkout_url);
    } catch (error) { showError(error.message); }
}

function home() {
    if (!session) return access();
    app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Olá, ${escapeHtml(firstName(session.usuario.nome))}!</h1><p class="subheading">O que você precisa?</p><div class="grid2"><button class="choice teal" data-go="book">🗓️<br>Marcar<br>Consulta</button><button class="choice sky" data-go="consultas">📋<br>Minhas<br>Consultas</button></div>${notificationControl()}<h2 class="subheading">Próxima consulta</h2><div id="next">${message('Carregando próxima consulta...')}</div>${bottomNav('home')}`);
    renderConsultations('next', true);
    checkPatientNotices();
    refreshNotificationControl();
}

async function checkPatientNotices() {
    try {
        const { data: cancelled } = await api('avisos/cancelamento');
        if (cancelled?.status === 'cancelada' && Number.isSafeInteger(Number(cancelled.id)) && Number(cancelled.id) > 0) {
            cancellationNotice(cancelled);
            return;
        }
        const result = await api('consultas?status=ativas');
        const items = result.data || result;
        const consultation = items.find(item => item.status === 'confirmada' && !localStorage.getItem(`medvida-confirmada-${item.id}`));
        if (consultation) confirmationScreen(consultation);
    } catch (_) {}
}

function cancellationNotice(item) {
    app.innerHTML = shell(`${logo()}<section class="card confirmation"><div class="confirmation-icon cancelled-icon">✕</div><h1 class="screen-title">Consulta cancelada</h1><p class="subheading">A clínica cancelou sua consulta com ${escapeHtml(item.medico?.nome)} em ${dateText(item.data)} às ${escapeHtml(String(item.horario).slice(0, 5))}.</p><button class="primary" data-ack-cancellation="${item.id}">Entendi</button>${formError()}</section>`);
}

async function acknowledgeCancellation(id) {
    try {
        await api(`consultas/${id}/cancelamento-lido`, { method: 'PATCH' });
        home();
    } catch (error) { showError(error.message); }
}

function confirmationScreen(consultation) {
    activeConfirmation = consultation;
    app.innerHTML = shell(`${logo()}<section class="card confirmation"><div class="confirmation-icon">✅</div><h1 class="screen-title">Consulta Confirmada!</h1><p class="subheading">A clínica aprovou seu agendamento. Você pode enviar uma mensagem para o médico:</p><button class="primary" data-message-consultation="${consultation.id}">Enviar Mensagem ao Médico</button><button class="choice" style="width:100%" data-skip-confirmation="${consultation.id}">Pular por Agora</button></section>`);
}

function messageDoctor(consultationId) {
    app.innerHTML = shell(`${logo()}${backHeader('Mensagem para o Médico', 'confirmation')}<section class="notice">💬 Envie um resumo do motivo da sua consulta. Isso ajuda o médico a se preparar para o seu atendimento.</section><label>Sua mensagem<textarea id="doctor-message" class="message-box" maxlength="300" placeholder="Ex: Estou sentindo dores no peito há alguns dias, acompanhadas de cansaço. Tenho histórico de hipertensão..."></textarea></label><p class="message-count"><span id="message-count">0</span>/300</p><button class="primary" data-send-message="${consultationId}">Enviar Mensagem</button><p class="center"><button class="link-btn" data-skip-confirmation="${consultationId}">Pular</button></p>${formError()}`);
    document.querySelector('#doctor-message').oninput = event => { document.querySelector('#message-count').textContent = event.target.value.length; };
}

async function sendMessage(consultationId) {
    try {
        const text = document.querySelector('#doctor-message').value;
        await api(`consultas/${consultationId}/mensagem`, { method: 'PATCH', body: JSON.stringify({ mensagem: text }) });
        localStorage.setItem(`medvida-confirmada-${consultationId}`, '1');
        consultas();
    } catch (error) { showError(error.message); }
}

function book() {
    app.innerHTML = shell(`${logoutLink()}${pageHeading('Marcar Consulta', 'home')}<p class="subheading">Qual especialidade você precisa?</p><div class="grid2 specialty-grid">${specialties.map((name, index) => `<button class="choice specialty-choice" data-specialty="${name}"><span>${['❤️','🦴','🧠','🌿','👁️','🩺','🫀','🛸'][index]}</span>${name}</button>`).join('')}</div>${bottomNav('book')}`);
}

async function doctors(specialty, restoreDate = null) {
    app.innerHTML = shell(`${logoutLink()}${pageHeading('Médicos disponíveis', 'book')}<p class="subheading">${escapeHtml(specialty)}</p><div id="doctors">${message('Buscando médicos disponíveis...')}</div>${bottomNav('book')}`);
    try {
        const result = await api('medicos');
        listedDoctors = (result.data || result).filter(doctor => doctor.especialidade === specialty);
        const target = document.querySelector('#doctors');
        if (!target) return;
        target.innerHTML = listedDoctors.length ? listedDoctors.map(doctor => `<section class="appointment"><div class="doctor-head"><span class="avatar">🧑‍⚕️</span><div><h3>${escapeHtml(doctor.nome)}</h3><p class="specialty">${escapeHtml(doctor.especialidade)} · ${escapeHtml(doctor.crm)}</p><p>📍 ${escapeHtml(doctor.clinica?.endereco || '')}</p></div></div>${calendar(doctor)}</section>`).join('') : message('Ainda não há médicos cadastrados nesta especialidade.');
        if (restoreDate) openDate(restoreDate.doctorId, restoreDate.date);
    } catch (error) { const target = document.querySelector('#doctors'); if (target) target.innerHTML = message(error.message); }
}

function calendar(doctor) {
    const availableWeekdays = new Set((doctor.horarios || []).map(item => Number(item.dia_semana)));
    const dates = Array.from({ length: 30 }, (_, index) => {
        const date = new Date();
        date.setHours(12, 0, 0, 0);
        date.setDate(date.getDate() + index);
        return date;
    }).filter(date => availableWeekdays.has(date.getDay() || 7));

    return dates.length ? `<p class="subheading">Escolha uma data:</p><div class="calendar">${dates.map(date => `<button data-calendar-date="${date.toISOString().slice(0, 10)}" data-doctor-id="${doctor.id}">${date.getDate()}/${String(date.getMonth() + 1).padStart(2, '0')}</button>`).join('')}</div>` : '<p class="subheading">Este médico ainda não informou dias de atendimento.</p>';
}

async function openDate(doctorId, date) {
    const doctor = listedDoctors.find(item => item.id === Number(doctorId));
    if (!doctor) return;
    const dateButton = document.querySelector(`[data-calendar-date="${date}"][data-doctor-id="${doctorId}"]`);
    if (!dateButton) return;
    const target = dateButton.closest('.appointment');
    target.querySelector('.slots-panel')?.remove();
    const panel = document.createElement('div');
    panel.className = 'slots-panel';
    panel.textContent = 'Buscando horários...';
    target.append(panel);

    try {
        const slots = await api(`medicos/${doctorId}/horarios?data=${date}`);
        panel.innerHTML = slots.length ? `<p class="subheading">Horários disponíveis:</p><div class="timegrid">${slots.map(time => `<button data-time="${escapeHtml(time)}" data-book-doctor="${escapeHtml(doctorId)}" data-book-date="${escapeHtml(date)}">${escapeHtml(time)}</button>`).join('')}</div>` : '<p class="subheading">Sem horários disponíveis nesta data.</p>';
    } catch (error) { panel.textContent = error.message; }
}

function confirmBooking() {
    const doctor = selectedSlot.doctor;
    app.innerHTML = shell(`${logoutLink()}${pageHeading('Confirmar consulta', 'doctors')}<section class="card"><h3>🧑‍⚕️ ${escapeHtml(doctor.nome)}</h3><p class="specialty">${escapeHtml(doctor.especialidade)}</p><p>🏥 ${escapeHtml(doctor.clinica?.nome || '')}</p><p>📍 ${escapeHtml(doctor.clinica?.endereco || '')}</p><p>🗓️ ${dateText(selectedSlot.date)} · ${escapeHtml(selectedSlot.time)}</p></section><section class="notice">ℹ️ Após confirmar, aguarde a aprovação da clínica. Você receberá uma notificação.</section><button class="primary" data-action="book">Solicitar Agendamento</button>${formError()}`);
}

async function createBooking() {
    try { await api('consultas', { method: 'POST', body: JSON.stringify({ medico_id: selectedSlot.doctor.id, paciente_id: session.usuario.id, lugar: selectedSlot.doctor.clinica?.endereco || 'Clínica MedVida', data: selectedSlot.date, horario: selectedSlot.time }) }); consultas(); } catch (error) { showError(error.message); }
}

function consultas() { app.innerHTML = shell(`${logoutLink()}${pageHeading('Minhas Consultas', 'home')}<div id="consultas">${message('Carregando suas consultas...')}</div>${bottomNav('consultas')}`); renderConsultations('consultas'); }

async function renderConsultations(targetId, onlyFirst = false) {
    try {
        const result = await api(onlyFirst ? 'consultas?status=proximas' : 'consultas?status=visiveis');
        const target = document.querySelector(`#${targetId}`);
        if (!target) return;
        const allItems = result.data || result;
        const items = onlyFirst ? allItems.slice(0, 1) : allItems;
        target.innerHTML = items.length ? items.map(item => consultationCard(item, onlyFirst ? 'home' : 'consultas')).join('') : message('Você ainda não possui consultas marcadas.');
    } catch (error) {
        const target = document.querySelector(`#${targetId}`);
        if (target) target.innerHTML = message(error.message);
    }
}

function consultationCard(item, returnTo) {
    return `<button class="appointment appointment-link" data-consultation="${item.id}" data-return="${returnTo}"><span class="pill ${item.status === 'pendente' ? 'pending' : ''}">${statusText(item.status)}</span><h3>${escapeHtml(item.medico?.nome)}</h3><p class="specialty">${escapeHtml(item.medico?.especialidade)}</p><p>🗓️ ${dateText(item.data)} &nbsp; ⏱️ ${escapeHtml(String(item.horario).slice(0, 5))}</p></button>`;
}

async function consultationDetails(id, returnTo = session.tipo === 'clinica' ? 'clinic' : 'consultas') {
    detailReturn = returnTo;
    app.innerHTML = shell(`${logoutLink()}${backHeader('Detalhes da Consulta', 'detail')}<div id="consultation-details">${message('Carregando consulta...')}</div>`);
    try {
        const item = await api(`consultas/${id}`);
        const target = document.querySelector('#consultation-details');
        if (!target) return;
        target.innerHTML = `<span class="pill detail-status ${item.status === 'pendente' ? 'pending' : ''}">${statusText(item.status)}</span>
            <section class="appointment details-card">
                <div class="detail-row"><span>🧑‍⚕️</span><div><small>Médico</small><strong>${escapeHtml(item.medico?.nome)}</strong></div></div>
                <div class="detail-row"><span>🩺</span><div><small>Especialidade</small><strong>${escapeHtml(item.medico?.especialidade)}</strong></div></div>
                <div class="detail-row"><span>🏥</span><div><small>Clínica</small><strong>${escapeHtml(item.medico?.clinica?.nome)}</strong></div></div>
                <div class="detail-row"><span>📍</span><div><small>Endereço</small><strong>${escapeHtml(item.lugar || item.medico?.clinica?.endereco)}</strong></div></div>
                <div class="detail-row"><span>🗓️</span><div><small>Data</small><strong>${dateText(item.data)}</strong></div></div>
                <div class="detail-row"><span>⏱️</span><div><small>Horário</small><strong>${escapeHtml(String(item.horario).slice(0, 5))}</strong></div></div>
                ${session.tipo === 'clinica' ? `<div class="detail-row"><span>👤</span><div><small>Paciente</small><strong>${escapeHtml(item.paciente?.nome)}</strong></div></div>` : ''}
            </section>
            ${canCancel(item) ? `<button class="cancel-button" data-ask-cancel="${item.id}">Cancelar Consulta</button><div id="cancel-confirmation"></div>` : ['pendente', 'confirmada'].includes(item.status) ? '<p class="deadline-note">O cancelamento exige pelo menos 2 dias de antecedência.</p>' : ''}
            <p class="error" id="detail-error" hidden></p>`;
    } catch (error) {
        const target = document.querySelector('#consultation-details');
        if (target) target.innerHTML = message(error.message);
    }
}

function askCancellation(id) {
    const target = document.querySelector('#cancel-confirmation');
    if (target) target.innerHTML = `<div class="cancel-confirmation"><p>Confirmar cancelamento?</p><div class="action-row"><button class="refuse" data-confirm-cancel="${id}">Sim</button><button class="confirm" data-dismiss-cancel>Não</button></div></div>`;
}

async function cancelConsultation(id) {
    const button = document.querySelector('[data-confirm-cancel]');
    if (button) button.disabled = true;
    try {
        await api(`consultas/${id}`, { method: 'DELETE' });
        session.tipo === 'clinica' ? clinicDashboard() : consultas();
    } catch (error) {
        if (button) button.disabled = false;
        const target = document.querySelector('#detail-error');
        if (target) { target.hidden = false; target.textContent = error.message; }
    }
}

async function clinicDashboard() {
    app.innerHTML = shell(`${logoutLink()}<header class="clinic-header">${escapeHtml(clinicName(session.usuario.nome))}<img class="mini-logo" src="/logo-medvida.png" alt="MedVida"></header>${clinicTabs('clinic')}${notificationControl()}<div id="clinic-consultas">${message('Carregando agenda...')}</div>`);
    refreshNotificationControl();
    try {
        const result = await api('consultas?status=ativas');
        const consultations = result.data || result;
        document.querySelector('#pending-count').textContent = result.pending_count ?? consultations.filter(item => item.status === 'pendente').length;
        const active = consultations.filter(item => ['pendente', 'confirmada'].includes(item.status));
        document.querySelector('#clinic-consultas').innerHTML = active.length ? active.map(item => `<button class="appointment appointment-link" data-consultation="${item.id}" data-return="clinic"><h3>🧑‍⚕️ ${escapeHtml(item.medico?.nome)}</h3><p class="specialty">${escapeHtml(item.medico?.especialidade)}</p><p>🗓️ ${dateText(item.data)} · ${escapeHtml(String(item.horario).slice(0, 5))}</p><p>${escapeHtml(item.paciente?.nome || 'Paciente')} <span class="pill ${item.status === 'pendente' ? 'pending' : ''}">${statusText(item.status)}</span></p></button>`).join('') : message('Ainda não há consultas marcadas nesta clínica.');
    } catch (error) { document.querySelector('#clinic-consultas').innerHTML = message(error.message); }
}

async function pending() {
    app.innerHTML = shell(`${logoutLink()}<header class="clinic-header">${escapeHtml(clinicName(session.usuario.nome))}<img class="mini-logo" src="/logo-medvida.png" alt="MedVida"></header>${clinicTabs('pending')}<div id="pending-list">${message('Carregando pendentes...')}</div>`);
    try {
        const result = await api('consultas?status=pendente');
        const consultations = (result.data || result).filter(item => item.status === 'pendente');
        document.querySelector('#pending-count').textContent = result.pending_count ?? consultations.length;
        document.querySelector('#pending-list').innerHTML = consultations.length ? consultations.map(pendingCard).join('') : message('Não há consultas pendentes.');
    } catch (error) { document.querySelector('#pending-list').innerHTML = message(error.message); }
}

function pendingCard(item) {
    return `<section class="appointment pending-card">
        <div class="pending-person"><span class="pending-avatar">👤</span><div><h3>${escapeHtml(item.paciente?.nome || 'Paciente')}</h3><p class="specialty">${escapeHtml(item.paciente?.plano || 'Particular')}</p></div></div>
        <div class="pending-info">
            <p>🩺 ${escapeHtml(item.medico?.nome)}</p>
            <p>🗓️ ${dateText(item.data)} às ${escapeHtml(String(item.horario).slice(0, 5))}</p>
            ${item.mensagem_paciente ? `<p>💬 “${escapeHtml(item.mensagem_paciente)}”</p>` : ''}
        </div>
        <div class="action-row pending-actions"><button class="confirm" data-confirm="${item.id}">✓ Confirmar</button><button class="refuse" data-refuse="${item.id}">✕ Recusar</button></div>
    </section>`;
}

async function updateConsultation(id, action) {
    try { await api(`consultas/${id}/${action}`, { method: 'PATCH' }); clinicDashboard(); } catch (error) { document.querySelector('#pending-list').insertAdjacentHTML('afterbegin', `<p class="error">${escapeHtml(error.message)}</p>`); }
}

async function clinicMedics() {
    app.innerHTML = shell(`${logoutLink()}<header class="clinic-header">${escapeHtml(clinicName(session.usuario.nome))}<img class="mini-logo" src="/logo-medvida.png" alt="MedVida"></header>${clinicTabs('clinic-medics')}<div id="medics">${message('Carregando médicos...')}</div><button class="add-doctor" data-go="doctor-register">+ Cadastrar novo médico</button>`);
    try {
        const [doctorResult, consultationResult] = await Promise.all([api('medicos'), api('consultas')]);
        if (!document.querySelector('#medics')) return;
        document.querySelector('#pending-count').textContent = consultationResult.pending_count ?? (consultationResult.data || consultationResult).filter(item => item.status === 'pendente').length;
        clinicDoctors = (doctorResult.data || doctorResult).filter(doctor => Number(doctor.clinica_id) === Number(session.usuario.id));
        document.querySelector('#medics').innerHTML = clinicDoctors.length ? clinicDoctors.map(doctor => `<section class="appointment doctor-list-card"><span class="avatar">🧑‍⚕️</span><div class="doctor-list-copy"><h3>${escapeHtml(doctor.nome)}</h3><p class="specialty">${escapeHtml(doctor.especialidade)}</p></div><button class="link-btn" data-edit-doctor="${doctor.id}">Editar</button></section>`).join('') : message('Ainda não há médicos cadastrados nesta clínica.');
    } catch (error) { const target = document.querySelector('#medics'); if (target) target.innerHTML = message(error.message); }
}

function scheduleRows(existing = []) {
    const days = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
    return `<fieldset class="schedule"><legend>Dias e horários de atendimento</legend>${days.map((day, index) => { const value = existing.find(item => Number(item.dia_semana) === index + 1); return `<div class="schedule-row" data-day="${index + 1}"><label><input type="checkbox" ${value ? 'checked' : ''}>${day}</label><input class="start" type="time" value="${value?.inicio?.slice(0, 5) || '07:00'}"><span>até</span><input class="end" type="time" value="${value?.fim?.slice(0, 5) || '09:00'}"></div>`; }).join('')}</fieldset>`;
}

function collectSchedule(form) {
    return [...form.querySelectorAll('.schedule-row')].filter(row => row.querySelector('input[type="checkbox"]').checked).map(row => ({ dia_semana: Number(row.dataset.day), inicio: row.querySelector('.start').value, fim: row.querySelector('.end').value }));
}

function doctorRegister(doctor = null) {
    const editing = Boolean(doctor);
    app.innerHTML = shell(`${logoutLink()}${pageHeading(editing ? 'Editar médico' : 'Cadastrar médico', 'clinic-medics')}<form id="doctor-form" class="card"><label>Nome completo<input name="nome" value="${escapeHtml(doctor?.nome)}" required></label><label>CRM<input name="crm" value="${escapeHtml(doctor?.crm)}" required></label><label>Especialidade<select name="especialidade" required><option value="">Selecione</option>${optionList()}</select></label><label>Telefone<input name="telefone" value="${escapeHtml(doctor?.telefone)}" required></label>${scheduleRows(doctor?.horarios)}<button class="primary">${editing ? 'Salvar alterações' : 'Cadastrar médico'}</button>${formError()}</form>`);
    const form = document.querySelector('#doctor-form');
    form.especialidade.value = doctor?.especialidade || '';
    form.onsubmit = async event => { event.preventDefault(); const data = { ...Object.fromEntries(new FormData(form)), horarios: collectSchedule(form) }; try { await api(editing ? `medicos/${doctor.id}` : 'medicos', { method: editing ? 'PATCH' : 'POST', body: JSON.stringify(data) }); clinicMedics(); } catch (error) { showError(error.message); } };
}

function editDoctor(id) {
    const doctor = clinicDoctors.find(item => item.id === Number(id));
    if (doctor) doctorRegister(doctor);
}

function showError(text) { const error = document.querySelector('#form-error'); if (error) { error.hidden = false; error.textContent = text; } }

function goBack(target) {
    switch (target) {
        case 'access': clinicDraft = null; access(); break;
        case 'clinic-register': clinicRegister(); break;
        case 'home': home(); break;
        case 'book': book(); break;
        case 'doctors': selectedSlot?.doctor ? doctors(selectedSlot.doctor.especialidade, { doctorId: selectedSlot.doctor.id, date: selectedSlot.date }) : book(); break;
        case 'consultas': consultas(); break;
        case 'clinic': clinicDashboard(); break;
        case 'clinic-medics': clinicMedics(); break;
        case 'confirmation': activeConfirmation ? confirmationScreen(activeConfirmation) : home(); break;
        case 'detail': goBack(detailReturn); break;
    }
}

document.addEventListener('click', event => {
    const type = event.target.closest('[data-type]')?.dataset.type;
    if (type) { accessType = type; access(); return; }
    if (event.target.closest('[data-action="logout"]')) { logout(); return; }
    if (event.target.closest('[data-action="push-toggle"]')) { toggleNotifications(); return; }
    if (event.target.closest('[data-eye]')) {
        const button = event.target.closest('[data-eye]');
        const input = button.closest('.password-field').querySelector('input');
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.querySelector('img').src = visible ? '/icon-eye-closed.png' : '/icon-eye-open.png';
        button.querySelector('img').alt = visible ? 'Ocultar senha' : 'Mostrar senha';
        return;
    }
    if (event.target.closest('[data-action="pay"]')) { activateClinic(); return; }
    if (event.target.closest('[data-action="resend-email"]')) { resendEmailForm(); return; }
    if (event.target.closest('[data-action="clinic-check-status"]')) { refreshClinicPayment(); return; }
    if (event.target.closest('[data-action="clinic-checkout"]')) { openClinicCheckout(); return; }
    if (event.target.closest('[data-action="book"]')) { createBooking(); return; }
    const back = event.target.closest('[data-back]')?.dataset.back;
    if (back) { goBack(back); return; }
    const consultationButton = event.target.closest('[data-consultation]');
    if (consultationButton) { consultationDetails(consultationButton.dataset.consultation, consultationButton.dataset.return); return; }
    const cancellationToAsk = event.target.closest('[data-ask-cancel]')?.dataset.askCancel;
    if (cancellationToAsk) { askCancellation(cancellationToAsk); return; }
    if (event.target.closest('[data-dismiss-cancel]')) { document.querySelector('#cancel-confirmation').innerHTML = ''; return; }
    const cancellationToConfirm = event.target.closest('[data-confirm-cancel]')?.dataset.confirmCancel;
    if (cancellationToConfirm) { cancelConsultation(cancellationToConfirm); return; }
    const cancellationToAcknowledge = event.target.closest('[data-ack-cancellation]')?.dataset.ackCancellation;
    if (cancellationToAcknowledge) { acknowledgeCancellation(cancellationToAcknowledge); return; }
    const confirm = event.target.closest('[data-confirm]')?.dataset.confirm;
    if (confirm) { updateConsultation(confirm, 'confirmar'); return; }
    const refuse = event.target.closest('[data-refuse]')?.dataset.refuse;
    if (refuse) { updateConsultation(refuse, 'recusar'); return; }
    const consultationToMessage = event.target.closest('[data-message-consultation]')?.dataset.messageConsultation;
    if (consultationToMessage) { messageDoctor(consultationToMessage); return; }
    const messageToSend = event.target.closest('[data-send-message]')?.dataset.sendMessage;
    if (messageToSend) { sendMessage(messageToSend); return; }
    const skippedConfirmation = event.target.closest('[data-skip-confirmation]')?.dataset.skipConfirmation;
    if (skippedConfirmation) { localStorage.setItem(`medvida-confirmada-${skippedConfirmation}`, '1'); home(); return; }
    const calendarDate = event.target.closest('[data-calendar-date]');
    if (calendarDate) { openDate(calendarDate.dataset.doctorId, calendarDate.dataset.calendarDate); return; }
    const bookingTime = event.target.closest('[data-book-doctor]');
    if (bookingTime) { selectedSlot = { doctor: listedDoctors.find(item => item.id === Number(bookingTime.dataset.bookDoctor)), date: bookingTime.dataset.bookDate, time: bookingTime.dataset.time }; confirmBooking(); return; }
    const doctorToEdit = event.target.closest('[data-edit-doctor]')?.dataset.editDoctor;
    if (doctorToEdit) { editDoctor(doctorToEdit); return; }
    const specialty = event.target.closest('[data-specialty]')?.dataset.specialty;
    if (specialty) { doctors(specialty); return; }
    const route = event.target.closest('[data-go]')?.dataset.go;
    ({ access, home, book, consultas, clinic: clinicDashboard, pending, 'clinic-medics': clinicMedics, 'doctor-register': doctorRegister, register }[route] || (() => {}))();
});

if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' }).then(registration => {
    registration.update();
    navigator.serviceWorker.ready.then(refreshNotificationControl).catch(() => {});
}).catch(() => {}));
if (launchDestination) history.replaceState(null, '', location.pathname + location.hash);
session ? (session.tipo === 'clinica'
    ? (clinicHasAccess(session.usuario) ? (launchDestination === 'pending' ? pending() : clinicDashboard()) : clinicPayment())
    : (launchDestination === 'consultas' ? consultas() : home())) : access();
