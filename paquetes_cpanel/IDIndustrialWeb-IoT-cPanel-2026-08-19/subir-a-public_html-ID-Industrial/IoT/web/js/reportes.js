const REPORT_HEALTH_URL = './api/salud_sistema.php';
const REPORT_SESSION_URL = './api/auth/me.php';
const REPORT_LOGOUT_URL = './api/auth/logout.php';

let reportCsrfToken = '';
const reportElements = {
  form: document.querySelector('#reportFilters'),
  device: document.querySelector('#reportDevice'),
  from: document.querySelector('#reportFrom'),
  to: document.querySelector('#reportTo'),
  pdf: document.querySelector('#downloadPdf'),
  csv: document.querySelector('#downloadCsv'),
  message: document.querySelector('#reportMessage'),
  userName: document.querySelector('#reportsUserName'),
  userRole: document.querySelector('#reportsUserRole'),
  logout: document.querySelector('#reportsLogout')
};

function reportLocalInput(date) {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 16);
}

function reportQuery() {
  const params = new URLSearchParams();
  const device = reportElements.device.value.trim();
  if (device) params.set('dispositivo_id', device);
  for (const [name, control] of [['desde', reportElements.from], ['hasta', reportElements.to]]) {
    if (!control.value) continue;
    const date = new Date(control.value);
    if (!Number.isNaN(date.getTime())) params.set(name, date.toISOString());
  }
  return params;
}

function updateLinks() {
  const params = reportQuery();
  reportElements.pdf.href = `api/reporte_pdf.php?${params.toString()}`;
  reportElements.csv.href = `api/exportar_alertas_csv.php?${params.toString()}`;
  reportElements.message.textContent = 'El PDF usa el periodo elegido e incluye las 30 alertas mas recientes que coincidan.';
}

async function loadReportSession() {
  try {
    const response = await fetch(REPORT_SESSION_URL, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
    if (!response.ok) return false;
    const result = await response.json();
    reportElements.userName.textContent = result.data.usuario.nombre;
    reportElements.userRole.textContent = result.data.usuario.rol;
    reportCsrfToken = result.data.csrf_token;
    return true;
  } catch (error) {
    return false;
  }
}

async function loadReportDevices() {
  try {
    const response = await fetch(REPORT_HEALTH_URL, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
    if (!response.ok) throw new Error('No fue posible cargar dispositivos');
    const result = await response.json();
    const devices = Array.isArray(result?.data?.dispositivos) ? result.data.dispositivos : [];
    devices.forEach((device) => {
      const option = document.createElement('option');
      option.value = device.id;
      option.textContent = `${device.id} · ${device.ubicacion}`;
      reportElements.device.appendChild(option);
    });
  } catch (error) {
    reportElements.message.textContent = error.message || 'No fue posible cargar los dispositivos.';
  }
}

reportElements.form.addEventListener('change', updateLinks);
reportElements.form.addEventListener('submit', (event) => { event.preventDefault(); updateLinks(); reportElements.pdf.click(); });
reportElements.logout.addEventListener('click', async () => {
  try {
    await fetch(REPORT_LOGOUT_URL, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': reportCsrfToken }, credentials: 'same-origin' });
  } finally {
    window.location.replace('../crm/');
  }
});

(async () => {
  if (!await loadReportSession()) return window.location.replace('../crm/');
  reportElements.to.value = reportLocalInput(new Date());
  reportElements.from.value = reportLocalInput(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000));
  await loadReportDevices();
  updateLinks();
})();
