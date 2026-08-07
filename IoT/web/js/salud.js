const HEALTH_URL = './api/salud_sistema.php';
const HEALTH_SESSION_URL = './api/auth/me.php';
const HEALTH_LOGOUT_URL = './api/auth/logout.php';

let healthCsrfToken = '';

const healthElements = {
  userName: document.querySelector('#healthUserName'),
  userRole: document.querySelector('#healthUserRole'),
  refresh: document.querySelector('#healthRefresh'),
  logout: document.querySelector('#healthLogout'),
  summary: document.querySelector('.health-summary'),
  state: document.querySelector('#healthState'),
  description: document.querySelector('#healthDescription'),
  updated: document.querySelector('#healthUpdated'),
  online: document.querySelector('#onlineDevices'),
  onlineDetail: document.querySelector('#onlineDetail'),
  offline: document.querySelector('#offlineDevices'),
  review: document.querySelector('#sensorsReview'),
  calibration: document.querySelector('#calibrationDue'),
  stuck: document.querySelector('#stuckReadings'),
  status: document.querySelector('#healthStatus'),
  devices: document.querySelector('#healthDeviceList'),
  emptyTemplate: document.querySelector('#healthEmptyTemplate')
};

function healthText(value) {
  return String(value ?? '')
    .trim()
    .toUpperCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function healthEscape(value) {
  return String(value ?? '--')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function healthDate(value) {
  if (!value) return '--';
  let normalized = String(value).replace(' ', 'T');
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/.test(normalized)) normalized += 'Z';
  const date = new Date(normalized);
  if (Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
  }).format(date);
}

function statusClass(value) {
  const status = healthText(value);
  if (['OFFLINE', 'FALLO', 'CRITICO'].includes(status)) return 'is-critical';
  if (['REVISAR', 'CALENTANDO', 'PENDIENTE', 'ALERTA'].includes(status)) return 'is-warning';
  return '';
}

function devicePriority(device) {
  const values = [device.conexion, device.salud_dht, device.salud_mq2, device.salud_flama];
  if (values.some((value) => ['OFFLINE', 'FALLO'].includes(healthText(value)))) return 'CRITICO';
  if (
    values.some((value) => ['REVISAR', 'CALENTANDO'].includes(healthText(value)))
    || Number(device.mq2_calibracion_requerida) === 1
    || Number(device.mq2_lectura_atascada) === 1
  ) return 'REVISAR';
  return 'OPERATIVO';
}

function checkMarkup(label, value) {
  const className = statusClass(value);
  return `<div class="health-check ${className}"><span>${healthEscape(label)}</span><strong>${healthEscape(value || 'Sin datos')}</strong></div>`;
}

function renderDevices(devices) {
  healthElements.devices.innerHTML = '';
  if (!devices.length) {
    healthElements.devices.appendChild(healthElements.emptyTemplate.content.firstElementChild.cloneNode(true));
    return;
  }

  devices.forEach((device) => {
    const priority = devicePriority(device);
    const card = document.createElement('article');
    card.className = `health-device ${statusClass(priority)}`;
    const calibration = device.ultima_calibracion ? healthDate(device.ultima_calibracion) : 'Pendiente';
    const mq2Notes = [];
    if (Number(device.mq2_lectura_atascada) === 1) mq2Notes.push('Lectura MQ-2 sin variacion util');
    if (Number(device.mq2_calibracion_requerida) === 1) mq2Notes.push('Calibracion MQ-2 requerida');
    if (!mq2Notes.length) mq2Notes.push(`Calibracion: ${calibration}`);
    card.innerHTML = `
      <div class="health-device-header">
        <div><strong>${healthEscape(device.id)}</strong><small>${healthEscape(device.ubicacion)}</small></div>
        <span class="health-state-badge ${statusClass(priority)}">${healthEscape(priority)}</span>
      </div>
      <div class="health-checks">
        ${checkMarkup('Conexion', device.conexion)}
        ${checkMarkup('DHT11', device.salud_dht)}
        ${checkMarkup('MQ-2', Number(device.mq2_lectura_atascada) === 1 ? 'REVISAR' : device.salud_mq2)}
        ${checkMarkup('KY-026', device.salud_flama)}
        ${checkMarkup('Ultima lectura', healthDate(device.ultima_lectura))}
        ${checkMarkup('Calibracion MQ-2', calibration)}
      </div>
      <p class="health-mq2-note ${mq2Notes.length > 1 || Number(device.mq2_calibracion_requerida) === 1 || Number(device.mq2_lectura_atascada) === 1 ? 'is-warning' : ''}">
        <strong>MQ-2:</strong> ${healthEscape(mq2Notes.join(' · '))} · Umbral ${healthEscape(device.mq2_umbral_adc)} ADC
      </p>
    `;
    healthElements.devices.appendChild(card);
  });
}

function renderHealth(payload) {
  const summary = payload.resumen ?? {};
  const online = Number(summary.dispositivos_online) || 0;
  const offline = Number(summary.dispositivos_offline) || 0;
  const review = Number(summary.sensores_revisar) || 0;
  const calibration = Number(summary.mq2_calibracion_requerida) || 0;
  const stuck = Number(summary.mq2_lectura_atascada) || 0;
  const total = Number(summary.dispositivos_total) || 0;
  const critical = offline > 0;
  const warning = !critical && (review > 0 || calibration > 0 || stuck > 0);

  healthElements.summary.classList.toggle('is-critical', critical);
  healthElements.summary.classList.toggle('is-warning', warning);
  healthElements.state.textContent = critical ? 'ATENCION REQUERIDA' : (warning ? 'REVISION PROGRAMADA' : 'SISTEMA OPERATIVO');
  healthElements.description.textContent = critical
    ? 'Hay dispositivos sin comunicacion. Verifica energia, red y conexion del ESP32.'
    : (warning ? 'Existen tareas de mantenimiento o sensores que requieren revision.' : 'Todos los dispositivos y sensores reportan condiciones operativas.');
  healthElements.updated.textContent = healthDate(payload.generado_en);
  healthElements.online.textContent = online;
  healthElements.onlineDetail.textContent = `${total} registrados`;
  healthElements.offline.textContent = offline;
  healthElements.review.textContent = review;
  healthElements.calibration.textContent = calibration;
  healthElements.stuck.textContent = stuck;

  document.querySelector('#offlineDevices').closest('.health-metric').classList.toggle('is-critical', offline > 0);
  document.querySelector('#sensorsReview').closest('.health-metric').classList.toggle('is-warning', review > 0);
  document.querySelector('#calibrationDue').closest('.health-metric').classList.toggle('is-warning', calibration > 0);
  document.querySelector('#stuckReadings').closest('.health-metric').classList.toggle('is-warning', stuck > 0);
  document.querySelector('#onlineDevices').closest('.health-metric').classList.toggle('is-ok', online > 0 && offline === 0);
  renderDevices(Array.isArray(payload.dispositivos) ? payload.dispositivos : []);
}

async function loadHealth() {
  healthElements.refresh.disabled = true;
  healthElements.refresh.classList.add('is-loading');
  healthElements.status.textContent = 'Actualizando diagnostico...';
  try {
    const response = await fetch(HEALTH_URL, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
    if (response.status === 401) return window.location.replace('../crm/');
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result?.ok === false) throw new Error(result?.error || `HTTP ${response.status}`);
    renderHealth(result.data ?? {});
    healthElements.status.textContent = 'Diagnostico actualizado automaticamente cada 10 segundos.';
  } catch (error) {
    healthElements.status.textContent = error.message || 'No fue posible consultar la salud del sistema.';
  } finally {
    healthElements.refresh.disabled = false;
    healthElements.refresh.classList.remove('is-loading');
  }
}

async function loadHealthSession() {
  try {
    const response = await fetch(HEALTH_SESSION_URL, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
    if (!response.ok) return false;
    const result = await response.json();
    healthElements.userName.textContent = result.data.usuario.nombre;
    healthElements.userRole.textContent = result.data.usuario.rol;
    healthCsrfToken = result.data.csrf_token;
    return true;
  } catch (error) {
    return false;
  }
}

healthElements.refresh.addEventListener('click', loadHealth);
healthElements.logout.addEventListener('click', async () => {
  try {
    await fetch(HEALTH_LOGOUT_URL, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': healthCsrfToken }, credentials: 'same-origin' });
  } finally {
    window.location.replace('../crm/');
  }
});
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') loadHealth(); });

(async () => {
  if (!await loadHealthSession()) return window.location.replace('../crm/');
  loadHealth();
  setInterval(loadHealth, 10000);
})();
