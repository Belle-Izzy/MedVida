const app = document.querySelector('#app');
const sessionKey = 'medvida-session';
const specialties = ['Cardiologia', 'Ortopedia', 'Neurologia', 'Dermatologia', 'Oftalmologia', 'Clínico Geral', 'Gastroenterologia', 'Endocrinologia'];

let session = JSON.parse(localStorage.getItem(sessionKey) || 'null');
let accessType = 'paciente';
let clinicDraft = null;
let selectedSlot = null;
let clinicDoctors = [];
let listedDoctors = [];

const firstName = name => (name || '').trim().split(/\s+/)[0] || '';
const clinicName = name => /^cl[ií]nica/i.test(name || '') ? name : `Clínica ${firstName(name)}`;
const shell = content => `<div class="phone">${content}</div>`;
const logo = () => `<img class="logo-image" src="/logo-medvida.png" alt="MedVida"><p class="tagline">Sua Saúde, Conectada.</p>`;
const logoutLink = () => `<p class="logout-row"><button class="link-btn" data-action="logout">Sair</button></p>`;
const message = text => `<section class="card"><p class="subheading center">${text}</p></section>`;
const formError = () => `<p class="error" id="form-error" hidden></p>`;
const optionList = () => specialties.map(item => `<option value="${item}">${item}</option>`).join('');

async function api(path, options = {}) {
    const headers = { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...options.headers };
    if (session?.token) headers.Authorization = `Bearer ${session.token}`;
    const response = await fetch(`/api/${path}`, { ...options, headers });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || 'Não foi possível concluir esta ação.');
    return data;
}

function saveSession(data) {
    session = data;
    localStorage.setItem(sessionKey, JSON.stringify(data));
}

function logout() {
    const token = session?.token;
    session = null;
    localStorage.removeItem(sessionKey);
    access();
    if (token) fetch('/api/logout', { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }).catch(() => {});
}

function bottomNav(active) {
    return `<nav class="navbottom"><button class="${active === 'home' ? 'active' : ''}" data-go="home"><span>🏠</span>Início</button><button class="${active === 'book' ? 'active' : ''}" data-go="book"><span>🗓️</span>Agendar</button><button class="${active === 'consultas' ? 'active' : ''}" data-go="consultas"><span>📋</span>Consultas</button></nav>`;
}

function access() {
    app.innerHTML = shell(`${logo()}<h1 class="screen-title center">Como deseja entrar?</h1><div class="tabs"><button class="${accessType === 'paciente' ? 'active' : ''}" data-type="paciente">Sou paciente</button><button class="${accessType === 'clinica' ? 'active' : ''}" data-type="clinica">Sou clínica</button></div><form id="login-form" class="card"><h2>Entrar</h2><label>E-mail<input name="email" type="email" required></label><label>Senha<span class="password-field"><input name="senha" type="password" required><button type="button" class="eye" data-eye><img src="/icon-eye-open.png" alt="Mostrar senha"></button></span></label><button class="primary">Entrar</button>${formError()}<p class="center"><button class="link-btn" type="button" data-go="register">Ainda não tenho conta</button></p></form>`);
    document.querySelector('#login-form').onsubmit = login;
}

async function login(event) {
    event.preventDefault();
    try {
        const data = await api('auth/login', { method: 'POST', body: JSON.stringify({ ...Object.fromEntries(new FormData(event.target)), tipo: accessType }) });
        saveSession(data);
        data.tipo === 'clinica' ? clinicDashboard() : home();
    } catch (error) { showError(error.message); }
}

function register() {
    accessType === 'clinica' ? clinicRegister() : patientRegister();
}

function patientRegister() {
    app.innerHTML = shell(`${logo()}<h1 class="screen-title center">Cadastro de paciente</h1><form id="patient-form" class="card"><label>Nome completo<input name="nome" required></label><label>CPF<input name="cpf" required></label><label>Telefone<input name="telefone" required></label><label>Data de nascimento<input name="data_nascimento" type="date"></label><label>Plano de saúde<input name="plano"></label><label>E-mail<input name="email" type="email" required></label><label>Senha<span class="password-field"><input name="senha" type="password" minlength="8" required><button type="button" class="eye" data-eye><img src="/icon-eye-open.png" alt="Mostrar senha"></button></span></label><button class="primary">Criar conta</button>${formError()}</form>`);
    document.querySelector('#patient-form').onsubmit = event => createAccount(event, 'pacientes');
}

function clinicRegister() {
    app.innerHTML = shell(`${logo()}<h1 class="screen-title center">Cadastro da clínica</h1><form id="clinic-form" class="card"><label>Nome da clínica<input name="nome" required></label><label>Telefone<input name="telefone" required></label><label>Endereço<input name="endereco" required></label><label>Planos que atende<input name="plano_atendido" placeholder="Unimed, Amil, Particular"></label><label>E-mail<input name="email" type="email" required></label><label>Senha<span class="password-field"><input name="senha" type="password" minlength="8" required><button type="button" class="eye" data-eye><img src="/icon-eye-open.png" alt="Mostrar senha"></button></span></label><button class="primary">Criar conta</button>${formError()}</form>`);
    document.querySelector('#clinic-form').onsubmit = event => { event.preventDefault(); clinicDraft = Object.fromEntries(new FormData(event.target)); clinicPlan(); };
}

function clinicPlan() {
    app.innerHTML = shell(`<div class="topline">‹ <span>Plano de Acesso</span></div><section class="card"><p class="subheading">Para ativar sua conta e começar a atender pacientes pelo MedVida, escolha uma das opções abaixo:</p></section><section class="plan"><div>Plano<br>Anual<br><small>Acesso completo por 12 meses</small></div><strong>R$<br>4.800,00</strong></section><section class="card"><p class="subheading">💳 Formas de pagamento aceitas:</p><p>Cartão de crédito, débito, PIX ou boleto bancário</p></section><button class="primary" data-action="pay">Pagar e Ativar Conta</button>${formError()}`);
}

async function createAccount(event, endpoint) {
    event.preventDefault();
    try { await api(endpoint, { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(event.target))) }); access(); } catch (error) { showError(error.message); }
}

async function activateClinic() {
    try { await api('clinicas', { method: 'POST', body: JSON.stringify({ ...clinicDraft, forma_pagamento: 'pix' }) }); access(); } catch (error) { showError(error.message); }
}

function home() {
    if (!session) return access();
    app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Olá, ${firstName(session.usuario.nome)}!</h1><p class="subheading">O que você precisa?</p><div class="grid2"><button class="choice teal" data-go="book">🗓️<br>Marcar<br>Consulta</button><button class="choice sky" data-go="consultas">📋<br>Minhas<br>Consultas</button></div><h2 class="subheading">Próxima consulta</h2><div id="next">${message('Você ainda não possui consultas marcadas.')}</div>${bottomNav('home')}`);
    renderConsultations('next', true);
    checkConfirmed();
}

async function checkConfirmed() {
    try {
        const result = await api('consultas');
        const consultation = (result.data || result).find(item => item.status === 'confirmada' && !localStorage.getItem(`medvida-confirmada-${item.id}`));
        if (consultation) confirmationScreen(consultation);
    } catch (_) {}
}

function confirmationScreen(consultation) {
    app.innerHTML = shell(`${logo()}<section class="card confirmation"><div class="confirmation-icon">✅</div><h1 class="screen-title">Consulta Confirmada!</h1><p class="subheading">A clínica aprovou seu agendamento. Você pode enviar uma mensagem para o médico:</p><button class="primary" data-message-consultation="${consultation.id}">Enviar Mensagem ao Médico</button><button class="choice" style="width:100%" data-skip-confirmation="${consultation.id}">Pular por Agora</button></section>`);
}

function messageDoctor(consultationId) {
    app.innerHTML = shell(`${logo()}<div class="topline">‹ <span>Mensagem para o Médico</span></div><section class="notice">💬 Envie um resumo do motivo da sua consulta. Isso ajuda o médico a se preparar para o seu atendimento.</section><label>Sua mensagem<textarea id="doctor-message" class="message-box" maxlength="300" placeholder="Ex: Estou sentindo dores no peito há alguns dias, acompanhadas de cansaço. Tenho histórico de hipertensão..."></textarea></label><p class="message-count"><span id="message-count">0</span>/300</p><button class="primary" data-send-message="${consultationId}">Enviar Mensagem</button><p class="center"><button class="link-btn" data-skip-confirmation="${consultationId}">Pular</button></p>${formError()}`);
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
    app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Marcar Consulta</h1><p class="subheading">Qual especialidade você precisa?</p><div class="grid2 specialty-grid">${specialties.map((name, index) => `<button class="choice specialty-choice" data-specialty="${name}"><span>${['❤️','🦴','🧠','🌿','👁️','🩺','🫀','🛸'][index]}</span>${name}</button>`).join('')}</div>${bottomNav('book')}`);
}

async function doctors(specialty) {
    app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Médicos disponíveis</h1><p class="subheading">${specialty}</p><div id="doctors">${message('Buscando médicos disponíveis...')}</div>${bottomNav('book')}`);
    try {
        const result = await api('medicos');
        listedDoctors = (result.data || result).filter(doctor => doctor.especialidade === specialty);
        document.querySelector('#doctors').innerHTML = listedDoctors.length ? listedDoctors.map(doctor => `<section class="appointment"><div class="doctor-head"><span class="avatar">🧑‍⚕️</span><div><h3>${doctor.nome}</h3><p class="specialty">${doctor.especialidade} · ${doctor.crm}</p><p>📍 ${doctor.clinica?.endereco || ''}</p></div></div>${calendar(doctor)}</section>`).join('') : message('Ainda não há médicos cadastrados nesta especialidade.');
    } catch (error) { document.querySelector('#doctors').innerHTML = message(error.message); }
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
    const target = document.querySelector(`[data-calendar-date="${date}"][data-doctor-id="${doctorId}"]`).closest('.appointment');
    target.querySelector('.slots-panel')?.remove();
    const panel = document.createElement('div');
    panel.className = 'slots-panel';
    panel.textContent = 'Buscando horários...';
    target.append(panel);

    try {
        const slots = await api(`medicos/${doctorId}/horarios?data=${date}`);
        panel.innerHTML = slots.length ? `<p class="subheading">Horários disponíveis:</p><div class="timegrid">${slots.map(time => `<button data-time="${time}" data-book-doctor="${doctorId}" data-book-date="${date}">${time}</button>`).join('')}</div>` : '<p class="subheading">Sem horários disponíveis nesta data.</p>';
    } catch (error) { panel.textContent = error.message; }
}

function confirmBooking() {
    const doctor = selectedSlot.doctor;
    app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Confirmar consulta</h1><section class="card"><h3>🧑‍⚕️ ${doctor.nome}</h3><p class="specialty">${doctor.especialidade}</p><p>🏥 ${doctor.clinica?.nome || ''}</p><p>📍 ${doctor.clinica?.endereco || ''}</p><p>🗓️ ${selectedSlot.date} · ${selectedSlot.time}</p></section><section class="notice">ℹ️ Após confirmar, aguarde a aprovação da clínica. Você receberá uma notificação.</section><button class="primary" data-action="book">Solicitar Agendamento</button>${formError()}`);
}

async function createBooking() {
    try { await api('consultas', { method: 'POST', body: JSON.stringify({ medico_id: selectedSlot.doctor.id, paciente_id: session.usuario.id, lugar: selectedSlot.doctor.clinica?.endereco || 'Clínica MedVida', data: selectedSlot.date, horario: selectedSlot.time }) }); consultas(); } catch (error) { showError(error.message); }
}

function consultas() { app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Minhas Consultas</h1><div id="consultas">${message('Carregando suas consultas...')}</div>${bottomNav('consultas')}`); renderConsultations('consultas'); }

async function renderConsultations(targetId, onlyFirst = false) {
    try {
        const result = await api('consultas');
        const target = document.querySelector(`#${targetId}`);
        if (!target) return;
        const items = (result.data || result).slice(0, onlyFirst ? 1 : undefined);
        target.innerHTML = items.length ? items.map(consultationCard).join('') : message('Você ainda não possui consultas marcadas.');
    } catch (error) {
        const target = document.querySelector(`#${targetId}`);
        if (target) target.innerHTML = message(error.message);
    }
}

function consultationCard(item) { return `<section class="appointment"><span class="pill ${item.status === 'pendente' ? 'pending' : ''}">${item.status}</span><h3>${item.medico?.nome || ''}</h3><p class="specialty">${item.medico?.especialidade || ''}</p><p>🗓️ ${item.data} &nbsp; ⏱️ ${String(item.horario).slice(0, 5)}</p></section>`; }

async function clinicDashboard() {
    app.innerHTML = shell(`${logoutLink()}<header class="clinic-header">${clinicName(session.usuario.nome)}<img class="mini-logo" src="/logo-medvida.png" alt="MedVida"></header><div class="clinic-tabs"><button class="active">Agenda</button><button data-go="pending">Pendentes (<span id="pending-count">0</span>)</button><button data-go="clinic-medics">Médicos</button></div><div id="clinic-consultas">${message('Carregando agenda...')}</div>`);
    try {
        const result = await api('consultas');
        const consultations = result.data || result;
        document.querySelector('#pending-count').textContent = consultations.filter(item => item.status === 'pendente').length;
        document.querySelector('#clinic-consultas').innerHTML = consultations.length ? consultations.map(item => `<section class="appointment"><h3>🧑‍⚕️ ${item.medico?.nome || ''}</h3><p class="specialty">${item.medico?.especialidade || ''}</p><p>🗓️ ${item.data} · ${String(item.horario).slice(0,5)}</p><p>${item.paciente?.nome || 'Paciente'} <span class="pill ${item.status === 'pendente' ? 'pending' : ''}">${item.status}</span></p></section>`).join('') : message('Ainda não há consultas marcadas nesta clínica.');
    } catch (error) { document.querySelector('#clinic-consultas').innerHTML = message(error.message); }
}

async function pending() {
    app.innerHTML = shell(`${logoutLink()}<header class="clinic-header">${clinicName(session.usuario.nome)}<img class="mini-logo" src="/logo-medvida.png" alt="MedVida"></header><div class="clinic-tabs"><button data-go="clinic">Agenda</button><button class="active">Pendentes</button><button data-go="clinic-medics">Médicos</button></div><div id="pending-list">${message('Carregando pendentes...')}</div>`);
    try {
        const result = await api('consultas');
        const consultations = (result.data || result).filter(item => item.status === 'pendente');
        document.querySelector('#pending-list').innerHTML = consultations.length ? consultations.map(item => `<section class="appointment pending-card"><h3>👤 ${item.paciente?.nome || 'Paciente'}</h3><p class="specialty">${item.paciente?.plano || 'Particular'}</p><div class="pending-info">🩺 ${item.medico?.nome || ''}<br>🗓️ ${item.data} às ${String(item.horario).slice(0,5)}${item.mensagem_paciente ? `<br>💬 "${item.mensagem_paciente}"` : ''}</div><div class="action-row"><button class="confirm" data-confirm="${item.id}">✓ Confirmar</button><button class="refuse" data-refuse="${item.id}">✕ Recusar</button></div></section>`).join('') : message('Não há consultas pendentes.');
    } catch (error) { document.querySelector('#pending-list').innerHTML = message(error.message); }
}

async function updateConsultation(id, action) {
    try { await api(`consultas/${id}/${action}`, { method: 'PATCH' }); clinicDashboard(); } catch (error) { document.querySelector('#pending-list').insertAdjacentHTML('afterbegin', `<p class="error">${error.message}</p>`); }
}

async function clinicMedics() {
    app.innerHTML = shell(`${logoutLink()}<header class="clinic-header">${clinicName(session.usuario.nome)}<img class="mini-logo" src="/logo-medvida.png" alt="MedVida"></header><div class="clinic-tabs"><button data-go="clinic">Agenda</button><button class="active">Médicos</button></div><div id="medics">${message('Carregando médicos...')}</div><button class="choice add-doctor" data-go="doctor-register">+ Cadastrar novo médico</button>`);
    try { const result = await api('medicos'); clinicDoctors = (result.data || result).filter(doctor => doctor.clinica_id === session.usuario.id); document.querySelector('#medics').innerHTML = clinicDoctors.length ? clinicDoctors.map(doctor => `<section class="appointment"><h3>🧑‍⚕️ ${doctor.nome}</h3><p class="specialty">${doctor.especialidade}</p><button class="link-btn" data-edit-doctor="${doctor.id}">Editar</button></section>`).join('') : message('Ainda não há médicos cadastrados nesta clínica.'); } catch (error) { document.querySelector('#medics').innerHTML = message(error.message); }
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
    app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">${editing ? 'Editar médico' : 'Cadastrar médico'}</h1><form id="doctor-form" class="card"><label>Nome completo<input name="nome" value="${doctor?.nome || ''}" required></label><label>CRM<input name="crm" value="${doctor?.crm || ''}" required></label><label>Especialidade<select name="especialidade" required><option value="">Selecione</option>${optionList()}</select></label><label>Telefone<input name="telefone" value="${doctor?.telefone || ''}" required></label>${scheduleRows(doctor?.horarios)}<button class="primary">${editing ? 'Salvar alterações' : 'Cadastrar médico'}</button>${formError()}</form>`);
    const form = document.querySelector('#doctor-form');
    form.especialidade.value = doctor?.especialidade || '';
    form.onsubmit = async event => { event.preventDefault(); const data = { ...Object.fromEntries(new FormData(form)), horarios: collectSchedule(form) }; try { await api(editing ? `medicos/${doctor.id}` : 'medicos', { method: editing ? 'PATCH' : 'POST', body: JSON.stringify(data) }); clinicMedics(); } catch (error) { showError(error.message); } };
}

function editDoctor(id) {
    const doctor = clinicDoctors.find(item => item.id === Number(id));
    if (doctor) doctorRegister(doctor);
}

function showError(text) { const error = document.querySelector('#form-error'); if (error) { error.hidden = false; error.textContent = text; } }

document.addEventListener('click', event => {
    const type = event.target.closest('[data-type]')?.dataset.type;
    if (type) { accessType = type; access(); return; }
    if (event.target.closest('[data-action="logout"]')) { logout(); return; }
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
    if (event.target.closest('[data-action="book"]')) { createBooking(); return; }
    const calendarDate = event.target.closest('[data-calendar-date]');
    if (calendarDate) { openDate(calendarDate.dataset.doctorId, calendarDate.dataset.calendarDate); return; }
    const bookingTime = event.target.closest('[data-book-doctor]');
    if (bookingTime) { selectedSlot = { doctor: listedDoctors.find(item => item.id === Number(bookingTime.dataset.bookDoctor)), date: bookingTime.dataset.bookDate, time: bookingTime.dataset.time }; confirmBooking(); return; }
    const doctorToEdit = event.target.closest('[data-edit-doctor]')?.dataset.editDoctor;
    if (doctorToEdit) { editDoctor(doctorToEdit); return; }
    const specialty = event.target.closest('[data-specialty]')?.dataset.specialty;
    if (specialty) { doctors(specialty); return; }
    const route = event.target.closest('[data-go]')?.dataset.go;
    ({ home, book, consultas, clinic: clinicDashboard, 'clinic-medics': clinicMedics, 'doctor-register': doctorRegister, register }[route] || (() => {}))();
});

if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
session ? (session.tipo === 'clinica' ? clinicDashboard() : home()) : access();
