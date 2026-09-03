const SESSION_URL = './api/auth/me.php';
const DEVICES_URL = './api/dispositivos_admin.php';
const SHELLY_TEST_URL = './api/shelly_probar_conexion.php';
const SHELLY_COMMAND_URL = './api/shelly_comando.php';
const SHELLY_SYNC_LIVE_URL = './api/shelly_sync_live.php';
const SHELLY_WEBHOOKS_URL = './api/shelly_webhooks_admin.php';
const LOGOUT_URL = './api/auth/logout.php';
const DEVICES_REFRESH_MS = 5000;

const state = {
  csrf: '',
  currentUser: null,
  devices: [],
  editingId: null,
  webhookActuatorId: null,
  webhookProbeUrl: null
};
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
  shellyCategory: document.querySelector('#shellyCategory'),
  shellyAllowRoutines: document.querySelector('#shellyAllowRoutines'),
  shellyRequireConfirmation: document.querySelector('#shellyRequireConfirmation'),
  shellyControlMode: document.querySelector('#shellyControlMode'),
  shellyLinkedDevice: document.querySelector('#shellyLinkedDevice'),
  hikvisionFields: document.querySelector('#hikvisionFields'),
  hikvisionName: document.querySelector('#hikvisionName'),
  hikvisionCategory: document.querySelector('#hikvisionCategory'),
  hikvisionModel: document.querySelector('#hikvisionModel'),
  hikvisionSerial: document.querySelector('#hikvisionSerial'),
  hikvisionLocalIp: document.querySelector('#hikvisionLocalIp'),
  hikvisionPort: document.querySelector('#hikvisionPort'),
  hikvisionProtocol: document.querySelector('#hikvisionProtocol'),
  zktecoFields: document.querySelector('#zktecoFields'),
  zktecoName: document.querySelector('#zktecoName'),
  zktecoCategory: document.querySelector('#zktecoCategory'),
  zktecoModel: document.querySelector('#zktecoModel'),
  zktecoSerial: document.querySelector('#zktecoSerial'),
  zktecoLocalIp: document.querySelector('#zktecoLocalIp'),
  zktecoPort: document.querySelector('#zktecoPort'),
  zktecoProtocol: document.querySelector('#zktecoProtocol'),
  zktecoMachineNumber: document.querySelector('#zktecoMachineNumber'),
  dialogMessage: document.querySelector('#dialogMessage'),
  submit: document.querySelector('#dialogSubmit'),
  cancel: document.querySelector('#dialogCancel'),
  close: document.querySelector('#dialogClose'),
  webhookDialog: document.querySelector('#webhookDialog'),
  webhookClose: document.querySelector('#webhookDialogClose'),
  webhookReload: document.querySelector('#webhookReload'),
  webhookProbe: document.querySelector('#webhookProbe'),
  webhookDevice: document.querySelector('#webhookDevice'),
  webhookMessage: document.querySelector('#webhookMessage'),
  webhookStatusGrid: document.querySelector('#webhookStatusGrid'),
  webhookUrls: document.querySelector('#webhookUrls'),
  webhookRpcEndpoint: document.querySelector('#webhookRpcEndpoint'),
  webhookRpcPayloads: document.querySelector('#webhookRpcPayloads'),
  webhookDeliveries: document.querySelector('#webhookDeliveries')
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
  const hikvisionCount = state.devices.filter((device) => device.tipo === 'HIKVISION').length;
  const zktecoCount = state.devices.filter((device) => device.tipo === 'ZKTECO').length;
  elements.summary.textContent =
    state.devices.length + ' registrados · ' + esp32Count + ' ESP32 · ' + shellyCount +
    ' Shelly · ' + hikvisionCount + ' Hikvision · ' + active + ' activos';

  elements.summary.textContent = [
    state.devices.length + ' registrados',
    esp32Count + ' ESP32',
    shellyCount + ' Shelly',
    hikvisionCount + ' Hikvision',
    zktecoCount + ' ZKTeco',
    active + ' activos'
  ].join(' / ');

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
    const isHikvision = device.tipo === 'HIKVISION';
    const isZkteco = device.tipo === 'ZKTECO';
  const detail = isShelly
      ? [device.modelo, device.funcion, device.categoria, 'Canal ' + device.canal].filter(Boolean).join(' · ')
      : isHikvision
        ? [device.nombre, device.categoria, device.modelo].filter(Boolean).join(' · ')
        : 'DISPOSITIVO_ID';
    const deviceDetail = isZkteco
      ? [device.nombre, device.categoria, device.modelo, device.protocolo].filter(Boolean).join(' / ')
      : detail;
    const shellyOnline = isShelly && device.conexion === 'ONLINE';
    const shellyOutput = Number(device.salida_encendida) === 1;
    const connection = isShelly
      ? '<div class="user-cell"><strong class="shelly-connection ' + (shellyOnline ? 'online' : 'offline') + '">' +
          escapeHtml((device.conexion || 'SIN_DATOS').replace('_', ' ')) +
        '</strong><small>' + escapeHtml(dateLabel(device.sincronizado_en)) + '</small>' +
        (device.ultimo_error ? '<small class="shelly-row-error">' + escapeHtml(device.ultimo_error) + '</small>' : '') +
        '</div>'
      : isHikvision
        ? '<div class="user-cell"><strong class="shelly-connection ' + (device.conexion === 'ONLINE' ? 'online' : 'offline') + '">' +
            escapeHtml((device.conexion || 'SIN_DATOS').replace('_', ' ')) +
          '</strong><small>' + escapeHtml(dateLabel(device.sincronizado_en)) + '</small>' +
          (device.ultimo_error ? '<small class="shelly-row-error">' + escapeHtml(device.ultimo_error) + '</small>' : '') +
          '</div>'
        : escapeHtml(dateLabel(device.ultima_conexion));
    const deviceConnection = isZkteco
      ? '<div class="user-cell"><strong class="shelly-connection ' + (device.conexion === 'ONLINE' ? 'online' : 'offline') + '">' +
          escapeHtml((device.conexion || 'SIN_DATOS').replace('_', ' ')) +
        '</strong><small>' + escapeHtml(dateLabel(device.sincronizado_en)) + '</small>' +
        (device.ultimo_error ? '<small class="shelly-row-error">' + escapeHtml(device.ultimo_error) + '</small>' : '') +
        '</div>'
      : connection;
    const typeClass = isShelly ? 'shelly' : isHikvision ? 'hikvision' : isZkteco ? 'zkteco' : 'esp32';
    const shellyActions = isShelly
      ? '<button class="button button-secondary button-small" type="button" data-action="test" data-id="' + escapeHtml(device.id) + '">Probar</button>' +
        '<button class="button button-secondary button-small" type="button" data-action="webhooks" data-id="' + escapeHtml(device.id) + '">Webhooks</button>' +
        '<button class="button button-small ' + (shellyOutput ? 'button-danger' : 'button-primary') + '" type="button" data-action="shelly-output" data-output="' + (shellyOutput ? 'APAGAR' : 'ENCENDER') + '" data-id="' + escapeHtml(device.id) + '">' + (shellyOutput ? 'Apagar' : 'Encender') + '</button>'
      : '';

    return (
      '<tr>' +
        '<td><div class="user-cell"><strong>' + escapeHtml(device.id) + '</strong><small>' + escapeHtml(deviceDetail) + '</small></div></td>' +
        '<td><span class="device-type-badge ' + typeClass + '">' + escapeHtml(device.tipo) + '</span></td>' +
        '<td>' + escapeHtml(device.ubicacion) + '</td>' +
        '<td><span class="status-badge ' + statusClass + '">' + escapeHtml(device.estado) + '</span></td>' +
        '<td>' + deviceConnection + '</td>' +
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

function webhookStatusCard(label, delivery) {
  const received = delivery?.recibido_en ? dateLabel(delivery.recibido_en) : 'Sin entrega';
  const stateLabel = delivery?.estado || 'PENDIENTE';
  const statusClass = stateLabel === 'PROCESADA' ? 'ok' : stateLabel === 'ERROR' ? 'error' : 'pending';
  const detail = delivery?.ultimo_error
    ? delivery.ultimo_error
    : delivery
      ? (delivery.cambio_estado ? 'Estado actualizado' : 'Entrega recibida sin cambio de estado')
      : 'Activa el canal para comprobar este evento.';
  return (
    '<article class="webhook-status ' + statusClass + '">' +
      '<span>' + escapeHtml(label) + '</span>' +
      '<strong>' + escapeHtml(stateLabel) + '</strong>' +
      '<small>' + escapeHtml(received) + '</small>' +
      '<small>' + escapeHtml(detail) + '</small>' +
    '</article>'
  );
}

function webhookCopyBlock(label, value, code = false) {
  const content = escapeHtml(value || '');
  return (
    '<div class="webhook-copy-block">' +
      '<div class="webhook-copy-heading"><strong>' + escapeHtml(label) + '</strong>' +
        '<button class="button button-secondary button-small" type="button" data-copy-webhook>Copiar</button>' +
      '</div>' +
      (code
        ? '<pre tabindex="0">' + content + '</pre>'
        : '<textarea rows="3" readonly>' + content + '</textarea>') +
    '</div>'
  );
}

function renderWebhookDeliveries(deliveries) {
  if (!deliveries.length) {
    elements.webhookDeliveries.innerHTML =
      '<p class="webhook-empty">Todavia no se ha recibido ningun evento de este canal.</p>';
    return;
  }
  elements.webhookDeliveries.innerHTML = deliveries.map((delivery) => (
    '<div class="webhook-delivery">' +
      '<div><strong>' + escapeHtml(delivery.evento) + '</strong>' +
        '<small>' + escapeHtml(dateLabel(delivery.recibido_en)) + '</small></div>' +
      '<span class="webhook-delivery-source">' + escapeHtml(delivery.metodo) + '</span>' +
      '<span class="webhook-delivery-state ' + (delivery.estado === 'ERROR' ? 'error' : '') + '">' +
        escapeHtml(delivery.estado) + '</span>' +
      '<small>' + escapeHtml(delivery.ultimo_error || (delivery.cambio_externo ? 'Cambio externo' : 'Confirmacion o sin cambio')) + '</small>' +
    '</div>'
  )).join('');
}

function renderWebhookConfiguration(data) {
  const actuator = data.actuador;
  elements.webhookDevice.textContent =
    actuator.id + ' · ' + actuator.modelo + ' · canal ' + actuator.canal;
  elements.webhookStatusGrid.innerHTML =
    webhookStatusCard('Encendido', data.ultimas_entregas?.encendido) +
    webhookStatusCard('Apagado', data.ultimas_entregas?.apagado);
  elements.webhookUrls.innerHTML =
    webhookCopyBlock('URL de encendido', data.urls.encendido) +
    webhookCopyBlock('URL de apagado', data.urls.apagado);
  state.webhookProbeUrl = data.urls.prueba || null;
  elements.webhookProbe.disabled = !state.webhookProbeUrl;
  elements.webhookRpcEndpoint.textContent = data.rpc_endpoint_local
    ? 'Endpoint local: ' + data.rpc_endpoint_local
    : 'Configura una IP local en el dispositivo para usar RPC.';
  elements.webhookRpcPayloads.innerHTML =
    webhookCopyBlock('Crear webhook de encendido', JSON.stringify(data.rpc.encendido, null, 2), true) +
    webhookCopyBlock('Crear webhook de apagado', JSON.stringify(data.rpc.apagado, null, 2), true);
  renderWebhookDeliveries(data.entregas || []);
  showMessage(
    elements.webhookMessage,
    data.auditoria_disponible
      ? 'Receptor listo. Configura las dos acciones y verifica una entrega de cada tipo.'
      : 'Ejecuta migracion_shelly_webhooks_mysql57.sql para activar el diagnostico de entregas.',
    !data.auditoria_disponible
  );
}

async function loadWebhookConfiguration(id) {
  elements.webhookReload.disabled = true;
  showMessage(elements.webhookMessage, 'Consultando configuracion y entregas...');
  try {
    const result = await requestJson(
      SHELLY_WEBHOOKS_URL + '?actuador_id=' + encodeURIComponent(id)
    );
    renderWebhookConfiguration(result.data);
  } catch (error) {
    showMessage(elements.webhookMessage, error.message, true);
    elements.webhookStatusGrid.innerHTML = '';
    elements.webhookUrls.innerHTML = '';
    elements.webhookRpcPayloads.innerHTML = '';
    elements.webhookDeliveries.innerHTML = '';
  } finally {
    elements.webhookReload.disabled = false;
  }
}

function openWebhooks(id) {
  state.webhookActuatorId = id;
  state.webhookProbeUrl = null;
  elements.webhookProbe.disabled = true;
  elements.webhookDevice.textContent = id;
  elements.webhookStatusGrid.innerHTML = '';
  elements.webhookUrls.innerHTML = '';
  elements.webhookRpcPayloads.innerHTML = '';
  elements.webhookDeliveries.innerHTML = '';
  elements.webhookDialog.showModal();
  void loadWebhookConfiguration(id);
}

async function probeWebhookReceiver() {
  if (!state.webhookProbeUrl) return;
  elements.webhookProbe.disabled = true;
  showMessage(elements.webhookMessage, 'Validando receptor, token, dispositivo y canal...');
  try {
    const result = await requestJson(state.webhookProbeUrl);
    const actuator = result?.data?.actuadores?.join(', ') || state.webhookActuatorId;
    showMessage(
      elements.webhookMessage,
      'Receptor listo para ' + actuator + '. Esta prueba no cambio la salida fisica.'
    );
  } catch (error) {
    showMessage(elements.webhookMessage, error.message, true);
  } finally {
    elements.webhookProbe.disabled = false;
  }
}

async function copyWebhookValue(button) {
  const block = button.closest('.webhook-copy-block');
  const source = block?.querySelector('textarea, pre');
  const value = source?.value ?? source?.textContent ?? '';
  if (!value) return;
  try {
    await navigator.clipboard.writeText(value);
  } catch {
    const fallback = document.createElement('textarea');
    fallback.value = value;
    fallback.style.position = 'fixed';
    fallback.style.opacity = '0';
    document.body.appendChild(fallback);
    fallback.select();
    document.execCommand('copy');
    fallback.remove();
  }
  const original = button.textContent;
  button.textContent = 'Copiado';
  window.setTimeout(() => { button.textContent = original; }, 1200);
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
  const isHikvision = type === 'HIKVISION';
  const isZkteco = type === 'ZKTECO';
  elements.shellyFields.hidden = !isShelly;
  elements.hikvisionFields.hidden = !isHikvision;
  elements.zktecoFields.hidden = !isZkteco;
  elements.shellyDeviceId.required = isShelly;
  elements.shellyModel.required = isShelly;
  elements.shellyAllowRoutines.disabled = elements.shellyCategory.value === 'SEGURIDAD';
  elements.hikvisionName.required = isHikvision;
  elements.hikvisionLocalIp.required = isHikvision;
  elements.zktecoName.required = isZkteco;
  elements.zktecoLocalIp.required = isZkteco && elements.zktecoProtocol.value === 'PULL_4370';
  elements.deviceId.placeholder = isShelly ? 'SHELLY_001' : isHikvision ? 'HIK_001' : isZkteco ? 'ZK_001' : 'ESP32_002';
  elements.deviceIdHelp.textContent = isShelly
    ? 'Identificador interno para mostrar este actuador en ID Industrial.'
    : isHikvision || isZkteco
      ? 'Debe coincidir con devices[].id en el archivo config.json del conector local.'
      : 'Debe coincidir exactamente con DISPOSITIVO_ID en el firmware del ESP32.';
}

async function loadDevices() {
  const result = await requestJson(DEVICES_URL);
  state.devices = result?.data?.dispositivos ?? [];
  renderDevices();
}

async function refreshDevicesQuietly() {
  if (
    document.hidden ||
    elements.dialog.open ||
    elements.webhookDialog.open ||
    devicesRefreshInFlight
  ) return;
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
  elements.shellyCategory.value = device?.categoria ?? 'SEGURIDAD';
  elements.shellyAllowRoutines.value = Number(device?.permite_rutinas ?? 0) === 1 ? '1' : '0';
  elements.shellyRequireConfirmation.value = Number(device?.requiere_confirmacion ?? 1) === 1 ? '1' : '0';
  elements.shellyControlMode.value = device?.modo_control ?? 'HIBRIDO';
  elements.hikvisionName.value = device?.nombre ?? '';
  elements.hikvisionCategory.value = device?.categoria ?? 'CAMARA';
  elements.hikvisionModel.value = device?.modelo ?? '';
  elements.hikvisionSerial.value = device?.numero_serie ?? '';
  elements.hikvisionLocalIp.value = device?.ip_local ?? '';
  elements.hikvisionPort.value = device?.puerto ?? 80;
  elements.hikvisionProtocol.value = device?.protocolo ?? 'HTTP';
  elements.zktecoName.value = device?.nombre ?? '';
  elements.zktecoCategory.value = device?.categoria ?? 'ASISTENCIA';
  elements.zktecoModel.value = device?.modelo ?? '';
  elements.zktecoSerial.value = device?.numero_serie ?? '';
  elements.zktecoLocalIp.value = device?.ip_local ?? '';
  elements.zktecoPort.value = device?.puerto ?? 4370;
  elements.zktecoProtocol.value = device?.protocolo ?? 'PULL_4370';
  elements.zktecoMachineNumber.value = device?.numero_maquina ?? 1;
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
      categoria: elements.shellyCategory.value,
      permite_rutinas: elements.shellyCategory.value === 'SEGURIDAD'
        ? 0
        : Number(elements.shellyAllowRoutines.value),
      requiere_confirmacion: Number(elements.shellyRequireConfirmation.value),
      modo_control: elements.shellyControlMode.value,
      dispositivo_vinculado_id: elements.shellyLinkedDevice.value
    });
  }
  if (elements.type.value === 'HIKVISION') {
    Object.assign(payload, {
      nombre: elements.hikvisionName.value.trim(),
      categoria: elements.hikvisionCategory.value,
      modelo: elements.hikvisionModel.value.trim(),
      numero_serie: elements.hikvisionSerial.value.trim(),
      ip_local: elements.hikvisionLocalIp.value.trim(),
      puerto: Number(elements.hikvisionPort.value),
      protocolo: elements.hikvisionProtocol.value
    });
  }
  if (elements.type.value === 'ZKTECO') {
    Object.assign(payload, {
      nombre: elements.zktecoName.value.trim(),
      categoria: elements.zktecoCategory.value,
      modelo: elements.zktecoModel.value.trim(),
      numero_serie: elements.zktecoSerial.value.trim(),
      ip_local: elements.zktecoLocalIp.value.trim(),
      puerto: Number(elements.zktecoPort.value),
      protocolo: elements.zktecoProtocol.value,
      numero_maquina: Number(elements.zktecoMachineNumber.value)
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
    window.location.replace('./login.html');
    return false;
  }
}

async function logout() {
  elements.logout.disabled = true;
  try {
    await requestJson(LOGOUT_URL, { method: 'POST', csrf: true, body: '{}' });
  } finally {
    window.location.replace('./login.html');
  }
}

elements.newButton.addEventListener('click', openCreate);
elements.cancel.addEventListener('click', () => elements.dialog.close());
elements.close.addEventListener('click', () => elements.dialog.close());
elements.webhookClose.addEventListener('click', () => elements.webhookDialog.close());
elements.webhookReload.addEventListener('click', () => {
  if (state.webhookActuatorId) void loadWebhookConfiguration(state.webhookActuatorId);
});
elements.webhookProbe.addEventListener('click', () => void probeWebhookReceiver());
elements.webhookDialog.addEventListener('click', (event) => {
  const copyButton = event.target.closest('[data-copy-webhook]');
  if (copyButton) void copyWebhookValue(copyButton);
});
elements.form.addEventListener('submit', submitDevice);
elements.type.addEventListener('change', () => updateDeviceType(elements.type.value));
elements.shellyCategory.addEventListener('change', () => {
  if (elements.shellyCategory.value === 'SEGURIDAD') {
    elements.shellyAllowRoutines.value = '0';
  }
  if (elements.shellyCategory.value === 'MONITOREO') {
    elements.shellyFunction.value = 'OTRO';
  }
  updateDeviceType(elements.type.value);
});
elements.zktecoProtocol.addEventListener('change', () => updateDeviceType(elements.type.value));
elements.logout.addEventListener('click', logout);
elements.table.addEventListener('click', (event) => {
  const button = event.target.closest('button[data-action]');
  if (!button) return;
  if (button.dataset.action === 'edit') openEdit(button.dataset.id);
  if (button.dataset.action === 'status') {
    void changeStatus(button.dataset.id, button.dataset.status);
  }
  if (button.dataset.action === 'test') void testShelly(button.dataset.id);
  if (button.dataset.action === 'webhooks') openWebhooks(button.dataset.id);
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
