const SESSION_URL = './api/auth/me.php';
const DEVICES_URL = './api/dispositivos_admin.php';
const SHELLY_TEST_URL = './api/shelly_probar_conexion.php';
const SHELLY_COMMAND_URL = './api/shelly_comando.php';
const SHELLY_SYNC_LIVE_URL = './api/shelly_sync_live.php';
const LOGOUT_URL = './api/auth/logout.php';
const DEVICES_REFRESH_MS = 5000;

const state = { csrf: '', currentUser: null, devices: [], editingId: null };
let devicesRefreshInFlight = false;
const elements = {
  name: document.querySelector('#sessionUserName'),
  role: document.querySelector('#sessionUserRole'),
  logout: document.querySelector('#logoutButton'),
  newButton: document.querySelector('#newDeviceButton'),
  table: document.querySelector('#devicesTable'),
  summary: document.querySelector('#devicesSummary'),
  pageMessage: document.querySelector('#pageMessage'),
  dialog: document.querySelector('#deviceDialog'),
  form: document.querySelector('#deviceForm'),
  title: document.querySelector('#deviceDialogTitle'),
  type: document.querySelector('#deviceType'),
  deviceId: document.querySelector('#deviceId'),
  deviceIdHelp: document.querySelector('#deviceIdHelp'),
  location: document.querySelector('#deviceLocation'),
  status: document.querySelector('#deviceStatus'),
  shellyFields: document.querySelector('#shellyFields'),
  shellyDeviceId: document.querySelector('#shellyDeviceId'),
  shellyModel: document.querySelector('#shellyModel'),
  shellyGeneration: document.querySelector('#shellyGeneration'),
  shellyLocalIp: document.querySelector('#shellyLocalIp'),
  shellyChannel: document.querySelector('#shellyChannel'),
  shellyFunction: document.querySelector('#shellyFunction'),
  shellyControlMode: document.querySelector('#shellyControlMode'),
  shellyLinkedDevice: document.querySelector('#shellyLinkedDevice'),
  dialogMessage: document.querySelector('#dialogMessage'),
  submit: document.querySelector('#dialogSubmit'),
  cancel: document.querySelector('#dialogCancel'),
  close: document.querySelector('#dialogClose')
};

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function dateLabel(value) {
  if (!value) return 'Sin conexion';
  const date = new Date(String(value).replace(' ', 'T') + 'Z');
  return Number.isNaN(date.getTime())
    ? String(value)
    : new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      }).format(date);
}

async function requestJson(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(options.csrf ? { 'X-CSRF-TOKEN': state.csrf } : {})
    },
    ...options
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || result?.ok === false) {
    throw new Error(result?.error ?? ('HTTP ' + response.status));
  }
  return result;
}

function showMessage(element, message, isError = false) {
  element.textContent = message;
  element.classList.toggle('is-error', isError);
}

function renderDevices() {
  const active = state.devices.filter((device) => device.estado === 'Activo').length;
  const esp32Count = state.devices.filter((device) => device.tipo === 'ESP32').length;
  const shellyCount = state.devices.filter((device) => device.tipo === 'SHELLY').length;
  elements.summary.textContent =
    state.devices.length + ' registrados · ' + esp32Count + ' ESP32 · ' + shellyCount +
    ' Shelly · ' + active + ' activos';

  if (!state.devices.length) {
    elements.table.innerHTML =
      '<tr><td colspan="6"><div class="user-cell"><strong>No hay dispositivos registrados</strong><small>Usa “Nuevo dispositivo” para agregar el primero.</small></div></td></tr>';
    return;
  }

  elements.table.innerHTML = state.devices.map((device) => {
    const statusClass =
      device.estado === 'Activo'
        ? 'active'
        : device.estado === 'Mantenimiento'
          ? 'blocked'
          : 'inactive';
    const nextStatus = device.estado === 'Activo' ? 'Inactivo' : 'Activo';
    const toggleLabel = device.estado === 'Activo' ? 'Desactivar' : 'Activar';
    const toggleClass = device.estado === 'Activo' ? 'button-danger' : 'button-secondary';
    const isShelly = device.tipo === 'SHELLY';
    const detail = isShelly
      ? [device.modelo, device.funcion, 'Canal ' + device.canal].filter(Boolean).join(' · ')
      : 'DISPOSITIVO_ID';
    const shellyOnline = isShelly && device.conexion === 'ONLINE';
    const shellyOutput = Number(device.salida_encendida) === 1;
    const connection = isShelly
      ? '<div class="user-cell"><strong class="shelly-connection ' + (shellyOnline ? 'online' : 'offline') + '">' +
          escapeHtml((device.conexion || 'SIN_DATOS').replace('_', ' ')) +
        '</strong><small>' + escapeHtml(dateLabel(device.sincronizado_en)) + '</small>' +
        (device.ultimo_error ? '<small class="shelly-row-error">' + escapeHtml(device.ultimo_error) + '</small>' : '') +
        '</div>'
      : escapeHtml(dateLabel(device.ultima_conexion));
    const shellyActions = isShelly
      ? '<button class="button button-secondary button-small" type="button" data-action="test" data-id="' + escapeHtml(device.id) + '">Probar</button>' +
        '<button class="button button-small ' + (shellyOutput ? 'button-danger' : 'button-primary') + '" type="button" data-action="shelly-output" data-output="' + (shellyOutput ? 'APAGAR' : 'ENCENDER') + '" data-id="' + escapeHtml(device.id) + '">' + (shellyOutput ? 'Apagar' : 'Encender') + '</button>'
      : '';

    return (
      '<tr>' +
        '<td><div class="user-cell"><strong>' + escapeHtml(device.id) + '</strong><small>' + escapeHtml(detail) + '</small></div></td>' +
        '<td><span class="device-type-badge ' + (isShelly ? 'shelly' : 'esp32') + '">' + escapeHtml(device.tipo) + '</span></td>' +
        '<td>' + escapeHtml(device.ubicacion) + '</td>' +
        '<td><span class="status-badge ' + statusClass + '">' + escapeHtml(device.estado) + '</span></td>' +
        '<td>' + connection + '</td>' +
        '<td><div class="action-row">' +
          shellyActions +
          '<button class="button button-secondary button-small" type="button" data-action="edit" data-id="' + escapeHtml(device.id) + '">Editar</button>' +
          '<button class="button ' + toggleClass + ' button-small" type="button" data-action="status" data-status="' + nextStatus + '" data-id="' + escapeHtml(device.id) + '">' + toggleLabel + '</button>' +
        '</div></td>' +
      '</tr>'
    );
  }).join('');
}

async function testShelly(id) {
  showMessage(elements.pageMessage, 'Consultando Shelly Cloud...');
  try {
    await requestJson(SHELLY_TEST_URL, {
      method: 'POST', csrf: true, body: JSON.stringify({ actuador_id: id })
    });
    showMessage(elements.pageMessage, 'Conexion y estado de ' + id + ' actualizados.');
    await loadDevices();
  } catch (error) {
    showMessage(elements.pageMessage, error.message, true);
  }
}

async function controlShelly(id, action) {
  if (action === 'ENCENDER' && !window.confirm('Encender ' + id + '? Verifica antes que el canal controle la sirena correcta.')) return;
  showMessage(elements.pageMessage, 'Enviando orden a Shelly...');
  try {
    const result = await requestJson(SHELLY_COMMAND_URL, {
      method: 'POST', csrf: true,
      body: JSON.stringify({ actuador_id: id, accion: action })
    });
    if (!result?.data?.aplicado) {
      throw new Error(result?.data?.error || 'Shelly no confirmo el cambio del canal');
    }
    showMessage(elements.pageMessage, 'Orden ' + action.toLowerCase() + ' aplicada y verificada.');
    await loadDevices();
  } catch (error) {
    showMessage(elements.pageMessage, error.message, true);
  }
}

function populateLinkedDevices(selectedId = '') {
  const esp32Devices = state.devices.filter((device) => device.tipo === 'ESP32');
  elements.shellyLinkedDevice.innerHTML =
    '<option value="">Sin asociar</option>' +
    esp32Devices.map((device) => (
      '<option value="' + escapeHtml(device.id) + '">' +
      escapeHtml(device.id + ' · ' + device.ubicacion) +
      '</option>'
    )).join('');
  elements.shellyLinkedDevice.value = selectedId || '';
}

function updateDeviceType(type) {
  const isShelly = type === 'SHELLY';
  elements.shellyFields.hidden = !isShelly;
  elements.shellyDeviceId.required = isShelly;
  elements.shellyModel.required = isShelly;
  elements.deviceId.placeholder = isShelly ? 'SHELLY_001' : 'ESP32_002';
  elements.deviceIdHelp.textContent = isShelly
    ? 'Identificador interno para mostrar este actuador en ID Industrial.'
    : 'Debe coincidir exactamente con DISPOSITIVO_ID en el firmware del ESP32.';
}

async function loadDevices() {
  const result = await requestJson(DEVICES_URL);
  state.devices = result?.data?.dispositivos ?? [];
  renderDevices();
}

async function refreshDevicesQuietly() {
  if (document.hidden || elements.dialog.open || devicesRefreshInFlight) return;
  devicesRefreshInFlight = true;
  try {
    await requestJson(SHELLY_SYNC_LIVE_URL, {
      method: 'POST', csrf: true, body: '{}'
    });
    await loadDevices();
  } catch {
    // El siguiente ciclo reintentara sin interrumpir la administracion.
  } finally {
    devicesRefreshInFlight = false;
  }
}

function resetForm(device = null) {
  state.editingId = device?.id ?? null;
  elements.title.textContent = device ? 'Editar dispositivo' : 'Nuevo dispositivo';
  elements.submit.textContent = device ? 'Guardar cambios' : 'Guardar dispositivo';
  elements.deviceId.value = device?.id ?? '';
  elements.deviceId.readOnly = Boolean(device);
  elements.type.value = device?.tipo ?? 'ESP32';
  elements.type.disabled = Boolean(device);
  elements.location.value = device?.ubicacion ?? '';
  elements.status.value = device?.estado ?? 'Activo';
  elements.shellyDeviceId.value = device?.shelly_device_id ?? '';
  elements.shellyModel.value = device?.modelo ?? '';
  elements.shellyGeneration.value = device?.generacion ?? 'GEN2_PLUS';
  elements.shellyLocalIp.value = device?.ip_local ?? '';
  elements.shellyChannel.value = device?.canal ?? 0;
  elements.shellyFunction.value = device?.funcion ?? 'SIRENA';
  elements.shellyControlMode.value = device?.modo_control ?? 'HIBRIDO';
  populateLinkedDevices(device?.dispositivo_vinculado_id ?? '');
  updateDeviceType(elements.type.value);
  showMessage(elements.dialogMessage, '');
}

function openCreate() {
  resetForm();
  elements.dialog.showModal();
  elements.deviceId.focus();
}

function openEdit(id) {
  const device = state.devices.find((item) => item.id === id);
  if (!device) return;
  resetForm(device);
  elements.dialog.showModal();
  elements.location.focus();
}

async function changeStatus(id, status) {
  const device = state.devices.find((item) => item.id === id);
  if (
    !device
    || !window.confirm(
      '¿Deseas ' + (status === 'Activo' ? 'activar' : 'desactivar') + ' ' + device.id + '?'
    )
  ) {
    return;
  }

  try {
    await requestJson(DEVICES_URL, {
      method: 'POST',
      csrf: true,
      body: JSON.stringify({
        accion: 'actualizar',
        tipo: device.tipo,
        id: device.id,
        ubicacion: device.ubicacion,
        estado: status
      })
    });
    showMessage(
      elements.pageMessage,
      'Dispositivo ' + (status === 'Activo' ? 'activado' : 'desactivado') + ' correctamente.'
    );
    await loadDevices();
  } catch (error) {
    showMessage(elements.pageMessage, error.message, true);
  }
}

async function submitDevice(event) {
  event.preventDefault();
  if (!elements.form.reportValidity()) return;

  const editing = Boolean(state.editingId);
  const payload = {
    accion: editing ? 'actualizar' : 'crear',
    tipo: elements.type.value,
    id: elements.deviceId.value.trim(),
    ubicacion: elements.location.value.trim(),
    estado: elements.status.value
  };

  if (elements.type.value === 'SHELLY') {
    Object.assign(payload, {
      shelly_device_id: elements.shellyDeviceId.value.trim(),
      modelo: elements.shellyModel.value.trim(),
      generacion: elements.shellyGeneration.value,
      ip_local: elements.shellyLocalIp.value.trim(),
      canal: Number(elements.shellyChannel.value),
      funcion: elements.shellyFunction.value,
      modo_control: elements.shellyControlMode.value,
      dispositivo_vinculado_id: elements.shellyLinkedDevice.value
    });
  }

  elements.submit.disabled = true;
  showMessage(elements.dialogMessage, 'Guardando...');
  try {
    await requestJson(DEVICES_URL, {
      method: 'POST',
      csrf: true,
      body: JSON.stringify(payload)
    });
    elements.dialog.close();
    showMessage(
      elements.pageMessage,
      editing ? 'Dispositivo actualizado correctamente.' : 'Dispositivo registrado correctamente.'
    );
    await loadDevices();
  } catch (error) {
    showMessage(elements.dialogMessage, error.message, true);
  } finally {
    elements.submit.disabled = false;
  }
}

async function loadSession() {
  try {
    const result = await requestJson(SESSION_URL);
    state.currentUser = result?.data?.usuario ?? null;
    state.csrf = String(result?.data?.csrf_token ?? '');
    if (state.currentUser?.rol !== 'ADMIN' || !state.csrf) {
      window.location.replace('./index.html');
      return false;
    }
    elements.name.textContent = state.currentUser.nombre;
    elements.role.textContent = state.currentUser.rol;
    return true;
  } catch {
    window.location.replace('../crm/');
    return false;
  }
}

async function logout() {
  elements.logout.disabled = true;
  try {
    await requestJson(LOGOUT_URL, { method: 'POST', csrf: true, body: '{}' });
  } finally {
    window.location.replace('../crm/');
  }
}

elements.newButton.addEventListener('click', openCreate);
elements.cancel.addEventListener('click', () => elements.dialog.close());
elements.close.addEventListener('click', () => elements.dialog.close());
elements.form.addEventListener('submit', submitDevice);
elements.type.addEventListener('change', () => updateDeviceType(elements.type.value));
elements.logout.addEventListener('click', logout);
elements.table.addEventListener('click', (event) => {
  const button = event.target.closest('button[data-action]');
  if (!button) return;
  if (button.dataset.action === 'edit') openEdit(button.dataset.id);
  if (button.dataset.action === 'status') {
    void changeStatus(button.dataset.id, button.dataset.status);
  }
  if (button.dataset.action === 'test') void testShelly(button.dataset.id);
  if (button.dataset.action === 'shelly-output') {
    void controlShelly(button.dataset.id, button.dataset.output);
  }
});

(async function init() {
  if (await loadSession()) {
    try {
      await loadDevices();
      void refreshDevicesQuietly();
      window.setInterval(() => void refreshDevicesQuietly(), DEVICES_REFRESH_MS);
    } catch (error) {
      showMessage(elements.pageMessage, error.message, true);
    }
  }
})();
