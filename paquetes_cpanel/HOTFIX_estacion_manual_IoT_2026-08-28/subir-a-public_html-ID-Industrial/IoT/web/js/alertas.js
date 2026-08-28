const ALERTS_URL = './api/alertas.php';
const SESSION_URL = './api/auth/me.php';
const LOGOUT_URL = './api/auth/logout.php';

let currentPage = 1;
let totalPages = 1;
let csrfToken = '';
let devicesLoaded = false;
let pendingDeviceId = new URLSearchParams(location.search).get('dispositivo_id') ?? '';

const elements = {
  form: document.querySelector('#historyFilters'),
  device: document.querySelector('#filterDevice'),
  clear: document.querySelector('#clearFilters'),
  export: document.querySelector('#exportAlerts'),
  table: document.querySelector('#historyTable'),
  count: document.querySelector('#historyCount'),
  status: document.querySelector('#historyStatus'),
  previous: document.querySelector('#previousPage'),
  next: document.querySelector('#nextPage'),
  pageStatus: document.querySelector('#pageStatus'),
  userName: document.querySelector('#historyUserName'),
  userRole: document.querySelector('#historyUserRole'),
  logout: document.querySelector('#historyLogout')
};

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function normalizeText(value) {
  return String(value ?? '')
    .trim()
    .toUpperCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function parseApiDate(value) {
  if (!value) return null;
  let normalized = String(value).replace(' ', 'T');
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/.test(normalized)) normalized += 'Z';
  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? null : date;
}

function formatDate(value) {
  const date = parseApiDate(value);
  if (!date) return String(value ?? '--');
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
}

function originClass(value) {
  const type = normalizeText(value);
  if (type.includes('ESTACION MANUAL') || type.includes('PULSADOR')) return 'manual';
  if ((type.includes('FLAMA') || type.includes('FUEGO')) && (type.includes('GAS') || type.includes('HUMO'))) return 'combined';
  if (type.includes('FLAMA') || type.includes('FUEGO')) return 'flame';
  if (type.includes('GAS') || type.includes('HUMO') || type.includes('MQ-2')) return 'smoke';
  if (type.includes('TEMPERATURA') || type.includes('DHT')) return 'temperature';
  if (type.includes('SIN CONEXION') || type.includes('DESCONECT')) return 'connectivity';
  return '';
}

function severityClass(value) {
  const severity = normalizeText(value);
  if (severity === 'CRITICO') return 'critical';
  if (severity === 'PRECAUCION') return 'warning';
  return '';
}

function formatAlertValue(alert) {
  const type = normalizeText(alert.tipo_alerta);
  const value = alert.valor_sensor;
  if (type.includes('SIN CONEXION') || type.includes('DESCONECT')) return 'Sin comunicacion';
  if (type.includes('ESTACION MANUAL') || type.includes('PULSADOR')) return 'Activada';
  if (type.includes('FLAMA') && !type.includes('GAS') && !type.includes('HUMO')) return 'Detectada';
  if (type.includes('GAS') || type.includes('HUMO') || type.includes('MQ-2')) {
    return value === null || value === undefined ? '--' : `${value} ADC`;
  }
  if (type.includes('TEMPERATURA')) {
    return value === null || value === undefined ? '--' : `${value} °C`;
  }
  return value ?? '--';
}

function formQuery(includePage = true) {
  const params = new URLSearchParams();
  const data = new FormData(elements.form);
  for (const [name, rawValue] of data.entries()) {
    const value = String(rawValue).trim();
    if (!value) continue;
    if (['desde', 'hasta'].includes(name)) {
      const date = new Date(value);
      if (!Number.isNaN(date.getTime())) params.set(name, date.toISOString());
    } else {
      params.set(name, value);
    }
  }
  if (!params.has('dispositivo_id') && pendingDeviceId) {
    params.set('dispositivo_id', pendingDeviceId);
  }
  if (includePage) params.set('pagina', String(currentPage));
  return params;
}

function syncUrl() {
  const params = formQuery();
  history.replaceState(null, '', `${location.pathname}?${params.toString()}`);
  const exportParams = formQuery(false);
  elements.export.href = `${ALERTS_URL.replace('alertas.php', 'exportar_alertas_csv.php')}?${exportParams.toString()}`;
}

function populateDevices(devices) {
  if (devicesLoaded) return;
  const selected = pendingDeviceId || elements.device.value;
  devices.forEach((device) => {
    const option = document.createElement('option');
    option.value = device.id;
    option.textContent = `${device.id} · ${device.ubicacion}`;
    elements.device.appendChild(option);
  });
  elements.device.value = selected;
  pendingDeviceId = '';
  devicesLoaded = true;
}

function renderAlerts(alerts) {
  elements.table.innerHTML = '';
  if (!alerts.length) {
    const row = document.createElement('tr');
    row.innerHTML = '<td colspan="8">No hay alertas que coincidan con los filtros.</td>';
    elements.table.appendChild(row);
    return;
  }

  alerts.forEach((alert) => {
    const row = document.createElement('tr');
    const care = normalizeText(alert.estado_atencion || 'NUEVA');
    row.innerHTML = `
      <td data-label="Fecha">${escapeHtml(formatDate(alert.fecha_hora))}</td>
      <td data-label="Dispositivo"><strong>${escapeHtml(alert.dispositivo_id)}</strong></td>
      <td data-label="Ubicacion">${escapeHtml(alert.ubicacion)}</td>
      <td data-label="Origen"><span class="alert-origin ${originClass(alert.tipo_alerta)}">${escapeHtml(alert.tipo_alerta)}</span></td>
      <td data-label="Valor">${escapeHtml(formatAlertValue(alert))}</td>
      <td data-label="Severidad"><span class="severity ${severityClass(alert.severidad)}">${escapeHtml(alert.severidad)}</span></td>
      <td data-label="Atencion">
        <span class="history-care">
          <strong>${escapeHtml(care)}</strong>
          <small>${escapeHtml(alert.responsable ?? 'Sin responsable')}</small>
        </span>
      </td>
      <td data-label="Detalle"><a class="detail-link" href="index.html?alerta_id=${Number(alert.id)}">Ver detalle</a></td>
    `;
    elements.table.appendChild(row);
  });
}

async function loadAlerts() {
  elements.status.textContent = 'Consultando historial...';
  elements.previous.disabled = true;
  elements.next.disabled = true;
  syncUrl();

  try {
    const response = await fetch(`${ALERTS_URL}?${formQuery().toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    });
    if (response.status === 401) {
      window.location.replace('../crm/');
      return;
    }
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result?.ok === false) {
      throw new Error(result?.error ?? `HTTP ${response.status}`);
    }

    const alerts = Array.isArray(result?.data?.alertas) ? result.data.alertas : [];
    populateDevices(result?.data?.dispositivos ?? []);
    renderAlerts(alerts);

    const meta = result?.meta ?? {};
    currentPage = Number(meta.pagina) || 1;
    totalPages = Number(meta.paginas) || 1;
    elements.count.textContent = `${Number(meta.total) || 0} alertas encontradas`;
    elements.pageStatus.textContent = `Pagina ${currentPage} de ${totalPages}`;
    elements.previous.disabled = currentPage <= 1;
    elements.next.disabled = currentPage >= totalPages;
    elements.status.textContent = alerts.length
      ? `${alerts.length} registros en esta pagina`
      : 'Sin coincidencias';
  } catch (error) {
    elements.status.textContent = error.message || 'No fue posible consultar el historial.';
  }
}

async function loadSession() {
  try {
    const response = await fetch(SESSION_URL, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    });
    if (!response.ok) {
      window.location.replace('../crm/');
      return false;
    }
    const result = await response.json();
    elements.userName.textContent = result.data.usuario.nombre;
    elements.userRole.textContent = result.data.usuario.rol;
    csrfToken = result.data.csrf_token;
    return true;
  } catch (error) {
    window.location.replace('../crm/');
    return false;
  }
}

function applyUrlFilters() {
  const params = new URLSearchParams(location.search);
  currentPage = Math.max(1, Number(params.get('pagina')) || 1);
  for (const control of elements.form.elements) {
    if (!control.name || !params.has(control.name)) continue;
    const value = params.get(control.name);
    if (['desde', 'hasta'].includes(control.name)) {
      const date = parseApiDate(value);
      if (date) {
        const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
        control.value = local.toISOString().slice(0, 16);
      }
    } else {
      control.value = value;
    }
  }
}

elements.form.addEventListener('submit', (event) => {
  event.preventDefault();
  currentPage = 1;
  loadAlerts();
});
elements.clear.addEventListener('click', () => {
  elements.form.reset();
  currentPage = 1;
  loadAlerts();
});
elements.previous.addEventListener('click', () => {
  if (currentPage <= 1) return;
  currentPage--;
  loadAlerts();
});
elements.next.addEventListener('click', () => {
  if (currentPage >= totalPages) return;
  currentPage++;
  loadAlerts();
});
elements.logout.addEventListener('click', async () => {
  try {
    await fetch(LOGOUT_URL, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      cache: 'no-store'
    });
  } finally {
    window.location.replace('../crm/');
  }
});

async function initialize() {
  if (!await loadSession()) return;
  applyUrlFilters();
  loadAlerts();
}

initialize();
