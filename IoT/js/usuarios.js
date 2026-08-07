const SESSION_URL = './api/auth/me.php';
const USERS_URL = './api/usuarios.php';
const LOGOUT_URL = './api/auth/logout.php';

const state = { csrf: '', currentUser: null, users: [], editingId: null };
const elements = {
  name: document.querySelector('#sessionUserName'),
  role: document.querySelector('#sessionUserRole'),
  logout: document.querySelector('#logoutButton'),
  newButton: document.querySelector('#newUserButton'),
  table: document.querySelector('#usersTable'),
  summary: document.querySelector('#usersSummary'),
  pageMessage: document.querySelector('#pageMessage'),
  dialog: document.querySelector('#userDialog'),
  form: document.querySelector('#userForm'),
  title: document.querySelector('#userDialogTitle'),
  id: document.querySelector('#userId'),
  userName: document.querySelector('#userName'),
  email: document.querySelector('#userEmail'),
  userRole: document.querySelector('#userRole'),
  userStatus: document.querySelector('#userStatus'),
  password: document.querySelector('#userPassword'),
  passwordHelp: document.querySelector('#passwordHelp'),
  dialogMessage: document.querySelector('#dialogMessage'),
  submit: document.querySelector('#dialogSubmit'),
  cancel: document.querySelector('#dialogCancel'),
  close: document.querySelector('#dialogClose')
};

function escapeHtml(value) {
  return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

function statusLabel(value) { return { ACTIVO: 'Activo', BLOQUEADO: 'Bloqueado', INACTIVO: 'Inactivo' }[value] ?? value; }
function roleLabel(value) { return { ADMIN: 'Administrador', OPERADOR: 'Operador', LECTURA: 'Solo lectura' }[value] ?? value; }
function dateLabel(value) {
  if (!value) return 'Nunca';
  const date = new Date(String(value).replace(' ', 'T') + 'Z');
  return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(date);
}

async function requestJson(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(options.csrf ? { 'X-CSRF-TOKEN': state.csrf } : {}) },
    ...options
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || result?.ok === false) throw new Error(result?.error ?? ('HTTP ' + response.status));
  return result;
}

function showMessage(element, message, isError = false) {
  element.textContent = message;
  element.classList.toggle('is-error', isError);
}

function renderUsers() {
  const active = state.users.filter((user) => user.estado === 'ACTIVO').length;
  elements.summary.textContent = state.users.length + ' usuarios registrados · ' + active + ' activos';
  elements.table.innerHTML = state.users.map((user) => {
    const roleClass = user.rol === 'ADMIN' ? 'admin' : user.rol === 'OPERADOR' ? 'operator' : '';
    const statusClass = user.estado === 'ACTIVO' ? 'active' : user.estado === 'BLOQUEADO' ? 'blocked' : 'inactive';
    const isCurrent = Number(user.id) === Number(state.currentUser?.id);
    const nextStatus = user.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
    const toggle = isCurrent ? '' : '<button class="button ' + (user.estado === 'ACTIVO' ? 'button-danger' : 'button-secondary') + ' button-small" type="button" data-action="status" data-status="' + nextStatus + '" data-id="' + user.id + '">' + (user.estado === 'ACTIVO' ? 'Desactivar' : 'Activar') + '</button>';
    return '<tr><td><div class="user-cell"><strong>' + escapeHtml(user.nombre) + (isCurrent ? ' (tu cuenta)' : '') + '</strong><small>' + escapeHtml(user.email) + '</small></div></td><td><span class="role-badge ' + roleClass + '">' + escapeHtml(roleLabel(user.rol)) + '</span></td><td><span class="status-badge ' + statusClass + '">' + escapeHtml(statusLabel(user.estado)) + '</span></td><td>' + escapeHtml(dateLabel(user.ultimo_acceso)) + '</td><td><div class="action-row"><button class="button button-secondary button-small" type="button" data-action="edit" data-id="' + user.id + '">Editar</button>' + toggle + '</div></td></tr>';
  }).join('');
}

async function loadUsers() {
  const result = await requestJson(USERS_URL);
  state.users = result?.data?.usuarios ?? [];
  renderUsers();
}

function resetForm(user = null) {
  state.editingId = user ? Number(user.id) : null;
  elements.title.textContent = user ? 'Editar usuario' : 'Nuevo usuario';
  elements.submit.textContent = user ? 'Guardar cambios' : 'Guardar usuario';
  elements.id.value = user?.id ?? '';
  elements.userName.value = user?.nombre ?? '';
  elements.email.value = user?.email ?? '';
  elements.userRole.value = user?.rol ?? 'LECTURA';
  elements.userStatus.value = user?.estado ?? 'ACTIVO';
  elements.password.value = '';
  elements.password.required = !user;
  elements.passwordHelp.textContent = user ? 'Dejala vacia para conservar la contraseña actual.' : 'Minimo 8 caracteres, con mayuscula, minuscula y numero.';
  showMessage(elements.dialogMessage, '');
}

function openCreate() { resetForm(); elements.dialog.showModal(); elements.userName.focus(); }
function openEdit(id) {
  const user = state.users.find((item) => Number(item.id) === Number(id));
  if (!user) return;
  resetForm(user);
  elements.dialog.showModal();
  elements.userName.focus();
}

async function changeStatus(id, status) {
  const user = state.users.find((item) => Number(item.id) === Number(id));
  if (!user || !window.confirm('¿Deseas ' + (status === 'ACTIVO' ? 'activar' : 'desactivar') + ' a ' + user.nombre + '?')) return;
  try {
    await requestJson(USERS_URL, { method: 'POST', csrf: true, body: JSON.stringify({ accion: 'cambiar_estado', id: Number(id), estado: status }) });
    showMessage(elements.pageMessage, 'Usuario ' + (status === 'ACTIVO' ? 'activado' : 'desactivado') + ' correctamente.');
    await loadUsers();
  } catch (error) { showMessage(elements.pageMessage, error.message, true); }
}

async function submitUser(event) {
  event.preventDefault();
  if (!elements.form.reportValidity()) return;
  const editing = Boolean(state.editingId);
  const payload = { accion: editing ? 'actualizar' : 'crear', ...(editing ? { id: state.editingId } : {}), nombre: elements.userName.value.trim(), email: elements.email.value.trim(), rol: elements.userRole.value, estado: elements.userStatus.value, password: elements.password.value };
  elements.submit.disabled = true;
  showMessage(elements.dialogMessage, 'Guardando...');
  try {
    await requestJson(USERS_URL, { method: 'POST', csrf: true, body: JSON.stringify(payload) });
    elements.dialog.close();
    showMessage(elements.pageMessage, editing ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.');
    await loadUsers();
  } catch (error) { showMessage(elements.dialogMessage, error.message, true); }
  finally { elements.submit.disabled = false; }
}

async function loadSession() {
  try {
    const result = await requestJson(SESSION_URL);
    state.currentUser = result?.data?.usuario ?? null;
    state.csrf = String(result?.data?.csrf_token ?? '');
    if (state.currentUser?.rol !== 'ADMIN' || !state.csrf) { window.location.replace('./index.html'); return false; }
    elements.name.textContent = state.currentUser.nombre;
    elements.role.textContent = state.currentUser.rol;
    return true;
  } catch { window.location.replace('../crm/'); return false; }
}

async function logout() {
  elements.logout.disabled = true;
  try { await requestJson(LOGOUT_URL, { method: 'POST', csrf: true, body: '{}' }); }
  finally { window.location.replace('../crm/'); }
}

elements.newButton.addEventListener('click', openCreate);
elements.cancel.addEventListener('click', () => elements.dialog.close());
elements.close.addEventListener('click', () => elements.dialog.close());
elements.form.addEventListener('submit', submitUser);
elements.logout.addEventListener('click', logout);
elements.table.addEventListener('click', (event) => {
  const button = event.target.closest('button[data-action]');
  if (!button) return;
  if (button.dataset.action === 'edit') openEdit(button.dataset.id);
  if (button.dataset.action === 'status') void changeStatus(button.dataset.id, button.dataset.status);
});

(async function init() {
  if (await loadSession()) {
    try { await loadUsers(); }
    catch (error) { showMessage(elements.pageMessage, error.message, true); }
  }
})();
