const app = document.querySelector('#app');
const sessionKey = 'medvida-session';
let session = JSON.parse(localStorage.getItem(sessionKey) || 'null');
let selectedSlot = null;

const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]);
const api = async (path, options = {}) => {
    const headers = { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...options.headers };
    if (session?.token) headers.Authorization = `Bearer ${session.token}`;
    const response = await fetch(`/api/${path}`, { ...options, headers });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || 'Não foi possível concluir esta ação.');
    return data;
};
const saveSession = data => { session = data; localStorage.setItem(sessionKey, JSON.stringify(data)); };
const clearSession = () => { session = null; localStorage.removeItem(sessionKey); };
const header = () => `<header class="topbar"><a class="brand" href="#inicio"><span class="brand-mark">+</span>MedVida</a><nav class="nav" aria-label="Navegação principal"><button data-route="buscar">Encontrar médico</button><button data-route="cadastro-paciente">Sou paciente</button><button data-route="cadastro-clinica">Sou clínica</button><button class="always" data-route="login">Entrar</button></nav></header>`;
const layout = content => `<div class="shell">${header()}<main id="conteudo">${content}</main><footer class="footer">MedVida · Sua saúde, conectada</footer></div>`;
const notice = (message, error = false) => `<p class="notice${error ? ' error' : ''}" role="alert">${escapeHtml(message)}</p>`;

function home() {
    app.innerHTML = layout(`<section class="hero"><div><p class="eyebrow">Agendamento médico acessível</p><h1>Sua saúde mais perto de você.</h1><p class="hero-text">Encontre profissionais, escolha um horário e acompanhe sua consulta em poucos passos.</p><div class="actions"><button class="button" data-route="buscar">Agendar consulta</button><button class="button secondary" data-route="cadastro-clinica">Cadastrar clínica</button></div></div><div class="hero-art" aria-hidden="true"><div class="appointment-preview"><span class="status"><span class="dot"></span>Consulta confirmada</span><h3 style="margin-top:1rem">Dra. Ana Martins</h3><p class="meta">Cardiologia · Terça-feira, 10:30</p></div></div></section>`);
}

function login() {
    app.innerHTML = layout(`<section class="main"><div class="page-heading"><div><p class="eyebrow">Acesso seguro</p><h1>Como deseja entrar?</h1><p>Use o e-mail e a senha cadastrados.</p></div></div><form id="login-form" class="panel" style="max-width:620px"><div class="form-grid"><label>Você é<select name="tipo"><option value="paciente">Paciente</option><option value="clinica">Clínica</option></select></label><label>E-mail<input required type="email" name="email" autocomplete="email"></label><label class="full">Senha<input required type="password" name="senha" autocomplete="current-password"></label></div><div class="actions"><button class="button" type="submit">Entrar</button><button class="button secondary" type="button" data-route="inicio">Voltar</button></div><div id="form-message"></div></form></section>`);
    document.querySelector('#login-form').addEventListener('submit', async event => {
        event.preventDefault(); const form = new FormData(event.currentTarget); const area = document.querySelector('#form-message');
        try { const data = await api('auth/login', { method: 'POST', body: JSON.stringify(Object.fromEntries(form)) }); saveSession(data); dashboard(); } catch (error) { area.innerHTML = notice(error.message, true); }
    });
}

function patientRegister() {
    app.innerHTML = layout(`<section class="main"><div class="page-heading"><div><p class="eyebrow">Cadastro de paciente</p><h1>Cuide da sua saúde.</h1><p>Preencha seus dados para começar a agendar.</p></div></div><form id="patient-form" class="panel"><div class="form-grid"><label>Nome completo<input required name="nome" autocomplete="name"></label><label>CPF<input required name="cpf" inputmode="numeric" placeholder="000.000.000-00"></label><label>Telefone<input required name="telefone" inputmode="tel" autocomplete="tel"></label><label>Data de nascimento<input required type="date" name="data_nascimento"></label><label>Sexo<select name="sexo"><option value="">Prefiro não informar</option><option>Feminino</option><option>Masculino</option><option>Outro</option></select></label><label>Plano de saúde<input name="plano" placeholder="Ex.: Particular ou Unimed"></label><label class="full">Características de saúde <span class="meta">(opcional)</span><textarea name="caracteristicas_saude" placeholder="Informe apenas o que for importante para seu atendimento."></textarea></label><label>E-mail<input required type="email" name="email" autocomplete="email"></label><label>Senha<input required type="password" minlength="8" name="senha" autocomplete="new-password"></label></div><div class="actions"><button class="button" type="submit">Criar cadastro</button><button class="button secondary" type="button" data-route="inicio">Voltar</button></div><div id="form-message"></div></form></section>`);
    bindRegistration('patient-form', 'pacientes', 'Cadastro criado. Agora você pode entrar.', false);
}

function clinicRegister() {
    app.innerHTML = layout(`<section class="main"><div class="page-heading"><div><p class="eyebrow">Cadastro de clínica</p><h1>Leve sua clínica para o MedVida.</h1><p>A taxa única de adesão está em modo demonstrativo: nenhuma cobrança será feita.</p></div></div><form id="clinic-form" class="panel"><div class="form-grid"><label>Nome da clínica<input required name="nome"></label><label>Telefone<input required name="telefone" inputmode="tel"></label><label class="full">Endereço<input required name="endereco" autocomplete="street-address"></label><label class="full">Planos atendidos<input name="plano_atendido" placeholder="Ex.: Particular, Unimed, Amil"></label><label>E-mail<input required type="email" name="email" autocomplete="email"></label><label>Senha<input required type="password" minlength="8" name="senha" autocomplete="new-password"></label><fieldset class="full"><legend>Forma de pagamento da taxa única</legend><div class="choice-list"><label class="choice"><input required type="radio" name="forma_pagamento" value="pix"> PIX</label><label class="choice"><input type="radio" name="forma_pagamento" value="cartao_credito"> Cartão de crédito</label><label class="choice"><input type="radio" name="forma_pagamento" value="boleto"> Boleto</label></div></fieldset></div><div class="actions"><button class="button" type="submit">Concluir cadastro</button><button class="button secondary" type="button" data-route="inicio">Voltar</button></div><div id="form-message"></div></form></section>`);
    bindRegistration('clinic-form', 'clinicas', 'Cadastro ativado em modo demonstrativo. Você já pode entrar.', true);
}

function bindRegistration(formId, endpoint, success, simulated) {
    document.querySelector(`#${formId}`).addEventListener('submit', async event => {
        event.preventDefault(); const area = document.querySelector('#form-message');
        try { await api(endpoint, { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))) }); area.innerHTML = notice(success); event.currentTarget.reset(); if (simulated) setTimeout(login, 850); } catch (error) { area.innerHTML = notice(error.message, true); }
    });
}

async function search() {
    app.innerHTML = layout(`<section class="main"><div class="page-heading"><div><p class="eyebrow">Escolha seu atendimento</p><h1>Encontre um médico.</h1><p>Veja profissionais cadastrados e escolha um horário disponível.</p></div></div><div id="doctor-list" class="grid three"><p class="empty">Carregando médicos...</p></div></section>`);
    try {
        const result = await api('medicos'); const doctors = result.data || result;
        document.querySelector('#doctor-list').innerHTML = doctors.length ? doctors.map(doctor => `<article class="card"><span class="pill">${escapeHtml(doctor.especialidade)}</span><h3>${escapeHtml(doctor.nome)}</h3><p>${escapeHtml(doctor.clinica?.nome || 'Clínica MedVida')}</p><p class="meta">CRM ${escapeHtml(doctor.crm)}</p><div class="card-actions"><button class="button" data-doctor="${doctor.id}">Ver horários</button></div></article>`).join('') : `<p class="empty">Ainda não há médicos disponíveis.</p>`;
        document.querySelectorAll('[data-doctor]').forEach(button => button.addEventListener('click', () => availability(button.dataset.doctor)));
    } catch (error) { document.querySelector('#doctor-list').innerHTML = notice(error.message, true); }
}

async function availability(id) {
    app.innerHTML = layout(`<section class="main"><div class="page-heading"><div><p class="eyebrow">Horários disponíveis</p><h1>Escolha o melhor momento.</h1></div><button class="button secondary" data-route="buscar">Voltar aos médicos</button></div><div id="availability-list" class="grid three"><p class="empty">Buscando horários...</p></div></section>`);
    try { const slots = await api(`disponibilidades?medico_id=${id}&disponivel=true`); document.querySelector('#availability-list').innerHTML = slots.length ? slots.map(slot => `<article class="card"><span class="pill">${escapeHtml(slot.medico?.especialidade || 'Consulta')}</span><h3>${escapeHtml(slot.data)} · ${escapeHtml(String(slot.horario).slice(0, 5))}</h3><p>${escapeHtml(slot.medico?.nome || '')}</p><button class="button" data-slot="${slot.id}">Agendar</button></article>`).join('') : `<p class="empty">Não há horários disponíveis para este médico.</p>`; document.querySelectorAll('[data-slot]').forEach(button => button.addEventListener('click', () => { selectedSlot = slots.find(slot => String(slot.id) === button.dataset.slot); book(); })); } catch (error) { document.querySelector('#availability-list').innerHTML = notice(error.message, true); }
}

function book() {
    if (!session || session.tipo !== 'paciente') { login(); return; }
    app.innerHTML = layout(`<section class="main"><div class="page-heading"><div><p class="eyebrow">Confirmar agendamento</p><h1>Conte o motivo da consulta.</h1><p>Essas informações serão tratadas como dados sensíveis.</p></div></div><form id="booking-form" class="panel" style="max-width:720px"><label>Mensagem para a clínica <span class="meta">(opcional)</span><textarea name="mensagem_paciente" placeholder="Ex.: preciso de uma avaliação de rotina."></textarea></label><div class="actions"><button class="button" type="submit">Solicitar consulta</button><button class="button secondary" type="button" data-route="buscar">Cancelar</button></div><div id="form-message"></div></form></section>`);
    document.querySelector('#booking-form').addEventListener('submit', async event => { event.preventDefault(); try { await api('consultas', { method: 'POST', body: JSON.stringify({ medico_id: selectedSlot.medico_id, paciente_id: session.usuario.id, lugar: selectedSlot.medico?.clinica?.endereco || 'A confirmar com a clínica', data: selectedSlot.data, horario: String(selectedSlot.horario).slice(0, 5), ...Object.fromEntries(new FormData(event.currentTarget)) }) }); dashboard(); } catch (error) { document.querySelector('#form-message').innerHTML = notice(error.message, true); } });
}

async function dashboard() {
    if (!session) return login();
    const isClinic = session.tipo === 'clinica';
    app.innerHTML = `<div class="shell"><div class="dashboard-header"><div class="brand" style="color:#fff"><span class="brand-mark" style="background:#fff;color:#087e8b">+</span>MedVida</div><p>Olá, ${escapeHtml(session.usuario?.nome || 'usuário')}.</p></div><main id="conteudo" class="main"><div class="page-heading"><div><p class="eyebrow">${isClinic ? 'Painel da clínica' : 'Minhas consultas'}</p><h1>${isClinic ? 'Agenda e solicitações' : 'Acompanhe seu atendimento'}</h1></div><button class="button secondary" id="logout">Sair</button></div><div class="tabs"><button class="tab active">${isClinic ? 'Agenda' : 'Consultas'}</button>${isClinic ? '<button class="tab" data-route="buscar">Ver médicos</button>' : '<button class="tab" data-route="buscar">Nova consulta</button>'}</div><div id="consultation-list" class="grid two"><p class="empty">Carregando...</p></div></main><footer class="footer">MedVida · Sua saúde, conectada</footer></div>`;
    document.querySelector('#logout').addEventListener('click', async () => { try { await api('logout', { method: 'POST' }); } finally { clearSession(); home(); } });
    try { const consultations = await api('consultas'); const items = consultations.data || consultations; document.querySelector('#consultation-list').innerHTML = items.length ? items.map(item => `<article class="card"><span class="pill">${escapeHtml(item.status)}</span><h3>${escapeHtml(item.medico?.nome || 'Consulta médica')}</h3><p>${escapeHtml(item.data)} · ${escapeHtml(String(item.horario).slice(0, 5))}</p><p class="meta">${escapeHtml(item.medico?.especialidade || '')}</p></article>`).join('') : `<p class="empty">${isClinic ? 'Ainda não há consultas nesta clínica.' : 'Você ainda não possui consultas.'}</p>`; } catch (error) { document.querySelector('#consultation-list').innerHTML = notice(error.message, true); }
}

const routes = { inicio: home, login, 'cadastro-paciente': patientRegister, 'cadastro-clinica': clinicRegister, buscar: search };
document.addEventListener('click', event => { const route = event.target.closest('[data-route]')?.dataset.route; if (route) { event.preventDefault(); routes[route]?.(); window.scrollTo({ top: 0, behavior: 'smooth' }); } });
window.addEventListener('hashchange', () => routes[location.hash.slice(1)]?.());
if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
routes[location.hash.slice(1)]?.() || home();
