const app = document.querySelector('#app');
const sessionKey = 'medvida-session';
const specialties = ['Cardiologia', 'Ortopedia', 'Neurologia', 'Dermatologia', 'Oftalmologia', 'Clínico Geral', 'Gastroenterologia', 'Endocrinologia'];

let session = JSON.parse(localStorage.getItem(sessionKey) || 'null');
let accessType = 'paciente';
let clinicDraft = null;
let selectedSlot = null;
let clinicDoctors = [];

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
    app.innerHTML = shell(`${logo()}<h1 class="screen-title center">Como deseja entrar?</h1><div class="tabs"><button class="${accessType === 'paciente' ? 'active' : ''}" data-type="paciente">Sou paciente</button><button class="${accessType === 'clinica' ? 'active' : ''}" data-type="clinica">Sou clínica</button></div><form id="login-form" class="card"><h2>Entrar</h2><label>E-mail<input name="email" type="email" required></label><label>Senha<span class="password-field"><input name="senha" type="password" required><button type="button" class="eye" data-eye>👁️</button></span></label><button class="primary">Entrar</button>${formError()}<p class="center"><button class="link-btn" type="button" data-go="register">Ainda não tenho conta</button></p></form>`);
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
    app.innerHTML = shell(`${logo()}<h1 class="screen-title center">Cadastro de paciente</h1><form id="patient-form" class="card"><label>Nome completo<input name="nome" required></label><label>CPF<input name="cpf" required></label><label>Telefone<input name="telefone" required></label><label>Data de nascimento<input name="data_nascimento" type="date"></label><label>Plano de saúde<input name="plano"></label><label>E-mail<input name="email" type="email" required></label><label>Senha<span class="password-field"><input name="senha" type="password" minlength="8" required><button type="button" class="eye" data-eye>👁️</button></span></label><button class="primary">Criar conta</button>${formError()}</form>`);
    document.querySelector('#patient-form').onsubmit = event => createAccount(event, 'pacientes');
}

function clinicRegister() {
    app.innerHTML = shell(`${logo()}<h1 class="screen-title center">Cadastro da clínica</h1><form id="clinic-form" class="card"><label>Nome da clínica<input name="nome" required></label><label>Telefone<input name="telefone" required></label><label>Endereço<input name="endereco" required></label><label>Planos que atende<input name="plano_atendido" placeholder="Unimed, Amil, Particular"></label><label>E-mail<input name="email" type="email" required></label><label>Senha<span class="password-field"><input name="senha" type="password" minlength="8" required><button type="button" class="eye" data-eye>👁️</button></span></label><button class="primary">Criar conta</button>${formError()}</form>`);
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
}

function book() {
    app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Marcar Consulta</h1><p class="subheading">Qual especialidade você precisa?</p><div class="grid2 specialty-grid">${specialties.map((name, index) => `<button class="choice specialty-choice" data-specialty="${name}"><span>${['❤️','🦴','🧠','🌿','👁️','🩺','🫀','🛸'][index]}</span>${name}</button>`).join('')}</div>${bottomNav('book')}`);
}

async function doctors(specialty) {
    app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Médicos disponíveis</h1><p class="subheading">${specialty}</p><div id="doctors">${message('Buscando médicos disponíveis...')}</div>${bottomNav('book')}`);
    try {
        const result = await api('medicos');
        const doctors = (result.data || result).filter(doctor => doctor.especialidade === specialty);
        document.querySelector('#doctors').innerHTML = doctors.length ? doctors.map(doctor => `<section class="appointment"><div class="doctor-head"><span class="avatar">🧑‍⚕️</span><div><h3>${doctor.nome}</h3><p class="specialty">${doctor.especialidade} · ${doctor.crm}</p><p>📍 ${doctor.clinica?.endereco || ''}</p></div></div><div id="slots-${doctor.id}">Carregando horários...</div></section>`).join('') : message('Ainda não há médicos cadastrados nesta especialidade.');
        doctors.forEach(loadSlots);
    } catch (error) { document.querySelector('#doctors').innerHTML = message(error.message); }
}

async function loadSlots(doctor) {
    try {
        const slots = await api(`disponibilidades?medico_id=${doctor.id}&disponivel=true`);
        document.querySelector(`#slots-${doctor.id}`).innerHTML = slots.length ? `<p class="subheading">Horários disponíveis:</p><div class="timegrid">${slots.map(slot => `<button data-slot="${slot.id}">${String(slot.horario).slice(0, 5)}</button>`).join('')}</div>` : '<p class="subheading">Sem horários disponíveis.</p>';
        slots.forEach(slot => { document.querySelector(`[data-slot="${slot.id}"]`).onclick = () => { selectedSlot = slot; confirmBooking(); }; });
    } catch (error) { document.querySelector(`#slots-${doctor.id}`).textContent = error.message; }
}

function confirmBooking() {
    const doctor = selectedSlot.medico;
    app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Confirmar consulta</h1><section class="card"><h3>🧑‍⚕️ ${doctor.nome}</h3><p class="specialty">${doctor.especialidade}</p><p>🏥 ${doctor.clinica?.nome || ''}</p><p>📍 ${doctor.clinica?.endereco || ''}</p><p>🗓️ ${selectedSlot.data} · ${String(selectedSlot.horario).slice(0,5)}</p></section><section class="notice">ℹ️ Após confirmar, aguarde a aprovação da clínica. Você receberá uma notificação.</section><button class="primary" data-action="book">Solicitar Agendamento</button>${formError()}`);
}

async function createBooking() {
    try { await api('consultas', { method: 'POST', body: JSON.stringify({ medico_id: selectedSlot.medico_id, paciente_id: session.usuario.id, lugar: selectedSlot.medico.clinica?.endereco || 'Clínica MedVida', data: selectedSlot.data, horario: String(selectedSlot.horario).slice(0, 5) }) }); consultas(); } catch (error) { showError(error.message); }
}

function consultas() { app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Minhas Consultas</h1><div id="consultas">${message('Carregando suas consultas...')}</div>${bottomNav('consultas')}`); renderConsultations('consultas'); }

async function renderConsultations(targetId, onlyFirst = false) {
    try { const result = await api('consultas'); const items = (result.data || result).slice(0, onlyFirst ? 1 : undefined); document.querySelector(`#${targetId}`).innerHTML = items.length ? items.map(consultationCard).join('') : message('Você ainda não possui consultas marcadas.'); } catch (error) { document.querySelector(`#${targetId}`).innerHTML = message(error.message); }
}

function consultationCard(item) { return `<section class="appointment"><span class="pill ${item.status === 'pendente' ? 'pending' : ''}">${item.status}</span><h3>${item.medico?.nome || ''}</h3><p class="specialty">${item.medico?.especialidade || ''}</p><p>🗓️ ${item.data} &nbsp; ⏱️ ${String(item.horario).slice(0, 5)}</p></section>`; }

function clinicDashboard() { app.innerHTML = shell(`${logoutLink()}<header class="clinic-header">${clinicName(session.usuario.nome)}<img class="mini-logo" src="/logo-medvida.png" alt="MedVida"></header><div class="clinic-tabs"><button class="active">Agenda</button><button data-go="clinic-medics">Médicos</button></div><div id="clinic-consultas">${message('Ainda não há consultas marcadas nesta clínica.')}</div>`); }

async function clinicMedics() {
    app.innerHTML = shell(`${logoutLink()}<header class="clinic-header">${clinicName(session.usuario.nome)}<img class="mini-logo" src="/logo-medvida.png" alt="MedVida"></header><div class="clinic-tabs"><button data-go="clinic">Agenda</button><button class="active">Médicos</button></div><div id="medics">${message('Carregando médicos...')}</div><button class="choice add-doctor" data-go="doctor-register">+ Cadastrar novo médico</button><button class="link-btn" data-go="availability-register">Cadastrar horários</button>`);
    try { const result = await api('medicos'); clinicDoctors = (result.data || result).filter(doctor => doctor.clinica_id === session.usuario.id); document.querySelector('#medics').innerHTML = clinicDoctors.length ? clinicDoctors.map(doctor => `<section class="appointment"><h3>🧑‍⚕️ ${doctor.nome}</h3><p class="specialty">${doctor.especialidade}</p></section>`).join('') : message('Ainda não há médicos cadastrados nesta clínica.'); } catch (error) { document.querySelector('#medics').innerHTML = message(error.message); }
}

function doctorRegister() {
    app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Cadastrar médico</h1><form id="doctor-form" class="card"><label>Nome completo<input name="nome" required></label><label>CRM<input name="crm" required></label><label>Especialidade<select name="especialidade" required><option value="">Selecione</option>${optionList()}</select></label><label>Telefone<input name="telefone" required></label><button class="primary">Cadastrar médico</button>${formError()}</form>`);
    document.querySelector('#doctor-form').onsubmit = async event => { event.preventDefault(); try { await api('medicos', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(event.target))) }); clinicMedics(); } catch (error) { showError(error.message); } };
}

async function availabilityRegister() {
    if (!clinicDoctors.length) {
        try { const result = await api('medicos'); clinicDoctors = (result.data || result).filter(doctor => doctor.clinica_id === session.usuario.id); } catch (error) { return showError(error.message); }
    }
    app.innerHTML = shell(`${logoutLink()}<h1 class="screen-title">Cadastrar horários</h1><form id="availability-form" class="card"><label>Médico<select name="medico_id" required><option value="">Selecione</option>${clinicDoctors.map(doctor => `<option value="${doctor.id}">${doctor.nome}</option>`).join('')}</select></label><label>Data<input name="data" type="date" required></label><label>Horário<input name="horario" type="time" required></label><button class="primary">Salvar horário</button>${formError()}</form>`);
    document.querySelector('#availability-form').onsubmit = async event => { event.preventDefault(); try { await api('disponibilidades', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(event.target))) }); clinicMedics(); } catch (error) { showError(error.message); } };
}

function showError(text) { const error = document.querySelector('#form-error'); if (error) { error.hidden = false; error.textContent = text; } }

document.addEventListener('click', event => {
    const type = event.target.closest('[data-type]')?.dataset.type;
    if (type) { accessType = type; access(); return; }
    if (event.target.closest('[data-action="logout"]')) { logout(); return; }
    if (event.target.closest('[data-eye]')) { const input = event.target.closest('.password-field').querySelector('input'); input.type = input.type === 'password' ? 'text' : 'password'; return; }
    if (event.target.closest('[data-action="pay"]')) { activateClinic(); return; }
    if (event.target.closest('[data-action="book"]')) { createBooking(); return; }
    const specialty = event.target.closest('[data-specialty]')?.dataset.specialty;
    if (specialty) { doctors(specialty); return; }
    const route = event.target.closest('[data-go]')?.dataset.go;
    ({ home, book, consultas, clinic: clinicDashboard, 'clinic-medics': clinicMedics, 'doctor-register': doctorRegister, 'availability-register': availabilityRegister, register }[route] || (() => {}))();
});

if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
session ? (session.tipo === 'clinica' ? clinicDashboard() : home()) : access();
