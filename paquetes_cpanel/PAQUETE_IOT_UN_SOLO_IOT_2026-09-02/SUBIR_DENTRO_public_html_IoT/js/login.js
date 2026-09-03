const LOGIN_URL = './api/auth/login.php';
const SESSION_URL = './api/auth/me.php';
const SETUP_URL = './api/auth/crear_admin_inicial.php';

const loginForm = document.querySelector('#loginForm');
const loginMessage = document.querySelector('#loginMessage');
const setupToggle = document.querySelector('#setupToggle');
const setupForm = document.querySelector('#setupForm');
const setupMessage = document.querySelector('#setupMessage');

async function postJson(url, payload) {
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    credentials: 'same-origin',
    cache: 'no-store',
    body: JSON.stringify(payload)
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || result?.ok === false) {
    let message = result?.error ?? `HTTP ${response.status}`;
    if (result?.codigo === 'SETUP_TOKEN_MISMATCH' && result?.detalle) {
      message += ` · origen: ${result.detalle.origen_configurado}`
        + ` · config.local: ${result.detalle.config_local_detectado ? 'si' : 'no'}`
        + ` · configurado: ${result.detalle.longitud_configurada}`
        + ` · recibido: ${result.detalle.longitud_recibida}`;
    }
    throw new Error(message);
  }
  return result;
}

function formPayload(form) {
  const payload = Object.fromEntries(new FormData(form).entries());
  if (typeof payload.setup_token === 'string') {
    payload.setup_token = payload.setup_token.trim();
  }
  return payload;
}

function setFormBusy(form, busy) {
  form.querySelectorAll('input, button').forEach((control) => {
    control.disabled = busy;
  });
}

async function checkExistingSession() {
  try {
    const response = await fetch(SESSION_URL, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    });
    if (response.ok) window.location.replace('./index.html');
  } catch (error) {
    // The login form remains available when the API cannot be reached.
  }
}

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!loginForm.reportValidity()) return;

  const payload = formPayload(loginForm);
  setFormBusy(loginForm, true);
  loginMessage.textContent = 'Validando acceso...';
  try {
    await postJson(LOGIN_URL, payload);
    window.location.replace('./index.html');
  } catch (error) {
    loginMessage.textContent = error.message || 'No fue posible iniciar sesion.';
  } finally {
    setFormBusy(loginForm, false);
  }
});

setupToggle.addEventListener('click', () => {
  const willOpen = setupForm.hidden;
  setupForm.hidden = !willOpen;
  setupToggle.setAttribute('aria-expanded', String(willOpen));
  setupToggle.textContent = willOpen
    ? 'Ocultar configuracion inicial'
    : 'Crear administrador inicial';
  if (willOpen) setupForm.querySelector('input')?.focus();
});

setupForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!setupForm.reportValidity()) return;

  const payload = formPayload(setupForm);
  setFormBusy(setupForm, true);
  setupMessage.textContent = 'Creando administrador...';
  try {
    await postJson(SETUP_URL, payload);
    window.location.replace('./index.html');
  } catch (error) {
    setupMessage.textContent = error.message || 'No fue posible crear el administrador.';
  } finally {
    setFormBusy(setupForm, false);
  }
});

checkExistingSession();
