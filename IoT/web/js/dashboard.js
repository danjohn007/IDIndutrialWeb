const API_URL = './api/web_get.php';
const CHART_API_URL = './api/resumen.php';
const ALERT_ACTION_URL = './api/atender_alerta.php';
const INCIDENT_URL = './api/incidente.php';
const SESSION_URL = './api/auth/me.php';
const LOGOUT_URL = './api/auth/logout.php';
const PASSWORD_URL = './api/auth/cambiar_password.php';
const MQ2_CALIBRATION_URL = './api/calibrar_mq2.php';
const ALARM_SILENCE_URL = './api/silenciar_alarma.php';
const SHELLY_COMMAND_URL = './api/shelly_comando.php';
const SHELLY_SYNC_LIVE_URL = './api/shelly_sync_live.php';
const REFRESH_MS = 2000;
const CHART_REFRESH_MS = 30000;
const SHELLY_SYNC_LIVE_MS = 5000;

const stateCopy = {
  DEVICE_OFFLINE: 'No hay lectura reciente del ESP32. Revisa su conexion o alimentacion.',
  NORMAL: 'Operación estable. Sensores dentro de los rangos esperados.',
  ALERTA: 'Precaución activa. Revisa humo, temperatura o salud del sensor.',
  ALARMA: 'Evento crítico detectado. Atiende el área señalada de inmediato.',
  SIN_DATOS: 'La API está disponible, pero todavía no hay dispositivos o alertas registrados.',
  OFFLINE: 'No se pudo contactar la API. Verifica conexión o sesión.'
};

let isLoading = false;
let hasRenderedData = false;
let isChartLoading = false;
let lastChartState = null;
let historicalChartSeries = [];
let liveChartSamples = [];
let currentGasThreshold = 1600;
let currentRangeHours = 24;
let currentUser = null;
let csrfToken = '';
let lastIncidentPayload = null;
let lastSeenAlertId = null;
const chartInteractionState = new WeakMap();
let alarmPulseTimer = null;
let isShellyLiveSyncing = false;
let lastRoutinesSignature = '';

const elements = {
  syncStatus: document.querySelector('#syncStatus'),
  refreshButton: document.querySelector('#refreshButton'),
  passwordButton: document.querySelector('#passwordButton'),
  logoutButton: document.querySelector('#logoutButton'),
  sessionUserName: document.querySelector('#sessionUserName'),
  sessionUserRole: document.querySelector('#sessionUserRole'),
  devicesAdminLink: document.querySelector('#devicesAdminLink'),
  usersLink: document.querySelector('#usersLink'),
  statusPanel: document.querySelector('#statusPanel'),
  generalState: document.querySelector('#generalState'),
  generalDescription: document.querySelector('#generalDescription'),
  activeDevices: document.querySelector('#activeDevices'),
  totalDevices: document.querySelector('#totalDevices'),
  activeFireSensors: document.querySelector('#activeFireSensors'),
  totalFireSensors: document.querySelector('#totalFireSensors'),
  monthAlerts: document.querySelector('#monthAlerts'),
  criticalOpen: document.querySelector('#criticalOpen'),
  lastRefresh: document.querySelector('#lastRefresh'),
  deviceGrid: document.querySelector('#deviceGrid'),
  shellyPanel: document.querySelector('#shellyPanel'),
  shellyGrid: document.querySelector('#shellyGrid'),
  routinesPanel: document.querySelector('#routinesPanel'),
  routineGrid: document.querySelector('#routineGrid'),
  routineSummary: document.querySelector('#routineSummary'),
  alertsTable: document.querySelector('#alertsTable'),
  alertDialog: document.querySelector('#alertActionDialog'),
  alertActionForm: document.querySelector('#alertActionForm'),
  alertDialogTitle: document.querySelector('#alertDialogTitle'),
  alertDialogSummary: document.querySelector('#alertDialogSummary'),
  alertDialogId: document.querySelector('#alertDialogId'),
  alertDialogAction: document.querySelector('#alertDialogAction'),
  alertResponsible: document.querySelector('#alertResponsible'),
  alertComment: document.querySelector('#alertComment'),
  alertFormMessage: document.querySelector('#alertFormMessage'),
  alertDialogClose: document.querySelector('#alertDialogClose'),
  alertDialogCancel: document.querySelector('#alertDialogCancel'),
  alertDialogSubmit: document.querySelector('#alertDialogSubmit'),
  passwordDialog: document.querySelector('#passwordDialog'),
  passwordForm: document.querySelector('#passwordForm'),
  passwordDialogClose: document.querySelector('#passwordDialogClose'),
  passwordDialogCancel: document.querySelector('#passwordDialogCancel'),
  currentPassword: document.querySelector('#currentPassword'),
  newPassword: document.querySelector('#newPassword'),
  confirmPassword: document.querySelector('#confirmPassword'),
  passwordMessage: document.querySelector('#passwordMessage'),
  passwordSubmit: document.querySelector('#passwordSubmit'),
  mq2CalibrationDialog: document.querySelector('#mq2CalibrationDialog'),
  mq2CalibrationForm: document.querySelector('#mq2CalibrationForm'),
  mq2CalibrationClose: document.querySelector('#mq2CalibrationClose'),
  mq2CalibrationCancel: document.querySelector('#mq2CalibrationCancel'),
  mq2CalibrationDevice: document.querySelector('#mq2CalibrationDevice'),
  mq2CalibrationSummary: document.querySelector('#mq2CalibrationSummary'),
  mq2CalibrationComment: document.querySelector('#mq2CalibrationComment'),
  mq2CalibrationMessage: document.querySelector('#mq2CalibrationMessage'),
  mq2CalibrationSubmit: document.querySelector('#mq2CalibrationSubmit'),
  incidentDialog: document.querySelector('#incidentDialog'),
  incidentDialogClose: document.querySelector('#incidentDialogClose'),
  incidentSummary: document.querySelector('#incidentSummary'),
  incidentStatus: document.querySelector('#incidentStatus'),
  incidentEnvironmentChart: document.querySelector('#incidentEnvironmentChart'),
  incidentGasChart: document.querySelector('#incidentGasChart'),
  incidentFlameChart: document.querySelector('#incidentFlameChart'),
  chartRange: document.querySelector('#chartRange'),
  chartStatus: document.querySelector('#chartStatus'),
  chartLiveIndicator: document.querySelector('#chartLiveIndicator'),
  environmentChart: document.querySelector('#environmentChart'),
  gasChart: document.querySelector('#gasChart'),
  flameChart: document.querySelector('#flameChart'),
  connectionChart: document.querySelector('#connectionChart'),
  emptyStateTemplate: document.querySelector('#emptyStateTemplate')
};

function normalizePayload(payload) {
  const root = payload?.data ?? payload?.resultado ?? payload ?? {};
  const isListResponse = Array.isArray(root);
  const dispositivos = isListResponse
    ? []
    : root.dispositivos ?? root.devices ?? root.sensores ?? root.lecturas ?? [];
  const alertas = isListResponse ? root : root.alertas ?? root.alerts ?? root.eventos ?? [];
  const resumen = root.resumen ?? root.kpis ?? root.metricas ?? {};
  const actuadoresShelly = root.actuadores_shelly ?? [];
  const rutinas = root.rutinas ?? [];

  return {
    resumen,
    dispositivos: Array.isArray(dispositivos) ? dispositivos : [],
    alertas: Array.isArray(alertas) ? alertas : [],
    actuadoresShelly: Array.isArray(actuadoresShelly) ? actuadoresShelly : [],
    rutinas: Array.isArray(rutinas) ? rutinas : [],
    rutinasDisponibles: root.rutinas_disponibles !== false
  };
}

function pickSystemState(dispositivos, alertas, resumen) {
  const deviceStates = dispositivos.map((item) => {
    if (normalizeText(item.estado_conexion) === 'OFFLINE') return 'OFFLINE';
    return normalizeState(item.estado_general ?? item.estado);
  });
  const onlineDevices = dispositivos.filter(
    (item) => normalizeText(item.estado_conexion) !== 'OFFLINE'
  );
  if (deviceStates.includes('ALARMA')) return 'ALARMA';
  if (deviceStates.includes('ALERTA')) return 'ALERTA';
  if (dispositivos.length > 0 && onlineDevices.length === 0) return 'DEVICE_OFFLINE';
  if (
    dispositivos.length > 0 ||
    alertas.length > 0 ||
    numberFrom(resumen.dispositivos_total ?? resumen.total_dispositivos, 0) > 0
  ) return 'NORMAL';
  return 'SIN_DATOS';
}

function normalizeState(value) {
  const state = normalizeText(value);
  if (['ALARMA', 'CRITICO', 'CRITICA', 'FUEGO', 'FLAMA'].includes(state)) return 'ALARMA';
  if (['ALERTA', 'PRECAUCION', 'WARNING'].includes(state)) return 'ALERTA';
  if (['OFFLINE', 'DESCONECTADO', 'SIN CONEXION'].includes(state)) return 'OFFLINE';
  if (['NORMAL', 'ACTIVO', 'OK'].includes(state)) return 'NORMAL';
  return state || 'NORMAL';
}

function normalizeSeverity(value) {
  const severity = normalizeText(value);
  if (['CRITICO', 'CRITICA', 'ALARMA', 'FUEGO', 'FLAMA'].includes(severity)) return 'CRITICO';
  if (['PRECAUCION', 'ALERTA', 'HUMO', 'TEMPERATURA ALTA'].includes(severity)) return 'PRECAUCION';
  return 'NORMAL';
}

function normalizeText(value) {
  return String(value ?? '')
    .trim()
    .toUpperCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function isResolved(value) {
  return value === true || ['1', 'SI', 'TRUE', 'ATENDIDA', 'RESUELTA'].includes(normalizeText(value));
}

function classForState(value) {
  const state = normalizeState(value);
  if (state === 'ALARMA') return 'critical';
  if (state === 'ALERTA') return 'warning';
  if (state === 'OFFLINE') return 'offline';
  return '';
}

function classForSeverity(value) {
  const severity = normalizeSeverity(value);
  if (severity === 'CRITICO') return 'critical';
  if (severity === 'PRECAUCION') return 'warning';
  return '';
}

function setSystemStatus(nextState) {
  elements.statusPanel.classList.remove('is-warning', 'is-critical', 'is-offline', 'is-empty');

  if (nextState === 'ALARMA') elements.statusPanel.classList.add('is-critical');
  if (nextState === 'ALERTA') elements.statusPanel.classList.add('is-warning');
  if (['OFFLINE', 'DEVICE_OFFLINE'].includes(nextState)) elements.statusPanel.classList.add('is-offline');
  if (nextState === 'SIN_DATOS') elements.statusPanel.classList.add('is-empty');

  const labels = {
    DEVICE_OFFLINE: 'Offline',
    OFFLINE: 'Sin conexión',
    SIN_DATOS: 'Sin datos'
  };
  const label = labels[nextState] ?? nextState;
  elements.generalState.textContent = label;
  elements.generalDescription.textContent = stateCopy[nextState] ?? stateCopy.NORMAL;
  document.title = nextState === 'ALARMA'
    ? 'ALARMA | ID Industrial'
    : 'ID Industrial | Panel de monitoreo';
}

function renderMetrics(resumen, dispositivos, alertas) {
  const total = numberFrom(resumen.dispositivos_total ?? resumen.total_dispositivos, dispositivos.length);
  const active = numberFrom(
    resumen.dispositivos_activos ?? resumen.sensores_activos,
    dispositivos.filter(isOperationalDevice).length
  );
  const totalFireSensors = numberFrom(
    resumen.sensores_incendio_total,
    dispositivos.length * 2
  );
  const activeFireSensors = numberFrom(
    resumen.sensores_incendio_activos,
    countOperationalFireSensors(dispositivos)
  );
  const monthAlerts = numberFrom(resumen.alertas_mes, alertas.length);
  const criticalOpen = numberFrom(
    resumen.criticas_abiertas ?? resumen.alertas_criticas,
    alertas.filter((item) => normalizeSeverity(item.severidad ?? item.tipo_alerta) === 'CRITICO' && !isResolved(item.atendida ?? item.resuelta)).length
  );

  elements.activeDevices.textContent = active;
  elements.totalDevices.textContent = `${total} registrados`;
  elements.activeFireSensors.textContent = activeFireSensors;
  elements.totalFireSensors.textContent = `${activeFireSensors} operativos de ${totalFireSensors}`;
  elements.monthAlerts.textContent = monthAlerts;
  elements.criticalOpen.textContent = criticalOpen;
  elements.lastRefresh.textContent = new Intl.DateTimeFormat('es-MX', {
    hour: '2-digit',
    minute: '2-digit'
  }).format(new Date());
}

function countOperationalFireSensors(dispositivos) {
  return dispositivos.reduce((total, device) => {
    if (normalizeText(device.estado_conexion) !== 'ONLINE') return total;

    const mq2 = normalizeText(device.salud_mq2 ?? device.saludMQ2);
    const flame = normalizeText(device.salud_flama ?? device.saludFlama);
    return total + (mq2 === 'OK' ? 1 : 0) + (flame === 'OK' ? 1 : 0);
  }, 0);
}

function isOperationalDevice(device) {
  if (device.activo !== undefined) {
    return device.activo === true || Number(device.activo) === 1;
  }

  const status = normalizeText(device.estado_dispositivo ?? device.estado);
  return !['INACTIVO', 'MANTENIMIENTO', 'OFFLINE', 'DESCONECTADO'].includes(status);
}

function numberFrom(value, fallback) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function gasMeterMarkup(value, threshold, detected) {
  const hasValue = value !== undefined && value !== null && value !== '';
  const rawValue = hasValue ? Number(value) : 0;
  const safeValue = Number.isFinite(rawValue) ? Math.max(0, Math.min(4095, rawValue)) : 0;
  const safeThreshold = Math.max(1, Math.min(4095, numberFrom(threshold, 1600)));
  const percentage = (safeValue / 4095) * 100;
  const thresholdPercentage = (safeThreshold / 4095) * 100;
  const levelClass = detected || safeValue >= safeThreshold
    ? 'is-critical'
    : (safeValue >= safeThreshold * 0.75 ? 'is-caution' : 'is-normal');
  const markerAngle = Math.PI - (thresholdPercentage / 100) * Math.PI;
  const markerInnerRadius = 61;
  const markerOuterRadius = 78;
  const markerX1 = 90 + Math.cos(markerAngle) * markerInnerRadius;
  const markerY1 = 88 - Math.sin(markerAngle) * markerInnerRadius;
  const markerX2 = 90 + Math.cos(markerAngle) * markerOuterRadius;
  const markerY2 = 88 - Math.sin(markerAngle) * markerOuterRadius;
  const displayValue = hasValue && Number.isFinite(rawValue) ? Math.round(safeValue) : '--';
  const displayPercentage = hasValue && Number.isFinite(rawValue)
    ? `${Number(percentage.toFixed(1))}% del rango`
    : 'Sin lectura actual';

  return `
    <div
      class="mq2-gauge ${levelClass}"
      role="meter"
      aria-label="Nivel de humo y gas"
      aria-valuemin="0"
      aria-valuemax="4095"
      aria-valuenow="${Math.round(safeValue)}"
      aria-valuetext="${displayValue} ADC; umbral ${Math.round(safeThreshold)} ADC"
    >
      <svg viewBox="0 0 180 104" aria-hidden="true">
        <path class="mq2-gauge-track" pathLength="100" d="M20 88 A70 70 0 0 1 160 88"/>
        <path
          class="mq2-gauge-level"
          pathLength="100"
          stroke-dasharray="${percentage.toFixed(2)} 100"
          d="M20 88 A70 70 0 0 1 160 88"
        />
        <line
          class="mq2-gauge-threshold"
          x1="${markerX1.toFixed(2)}"
          y1="${markerY1.toFixed(2)}"
          x2="${markerX2.toFixed(2)}"
          y2="${markerY2.toFixed(2)}"
        />
      </svg>
      <div class="mq2-gauge-readout">
        <strong>${displayValue}<span> ADC</span></strong>
        <small>${displayPercentage}</small>
      </div>
      <div class="mq2-gauge-scale" aria-hidden="true">
        <span>0</span>
        <span>Umbral ${Math.round(safeThreshold)}</span>
        <span>4095</span>
      </div>
    </div>
  `;
}

function renderDevices(dispositivos) {
  elements.deviceGrid.innerHTML = '';

  if (!dispositivos.length) {
    elements.deviceGrid.appendChild(emptyState());
    return;
  }

  dispositivos.forEach((device) => {
    const card = document.createElement('article');
    card.className = 'device-card';

    const isOffline = normalizeText(device.estado_conexion) === 'OFFLINE';
    const state = isOffline
      ? 'OFFLINE'
      : normalizeState(device.estado_general ?? device.estado);
    const badgeClass = classForState(state);
    const gasValue = isOffline ? null : (device.humo ?? device.gas ?? device.gas_raw);
    const gasDetected = !isOffline && isGasDetected(device);
    const flameDetected = !isOffline && isFlameDetected(device);
    const dhtHealth = isOffline ? 'OFFLINE' : normalizeText(device.salud_dht ?? device.saludDHT);
    const reportedGasHealth = isOffline
      ? 'OFFLINE'
      : normalizeText(device.salud_mq2 ?? device.saludMQ2);
    const flameHealth = isOffline
      ? 'OFFLINE'
      : normalizeText(device.salud_flama ?? device.saludFlama);
    const gasThreshold = numberFrom(device.gas_umbral, 1600);
    const warmupRemaining = Math.max(0, numberFrom(device.mq2_calentamiento_restante_s, 0));
    const stuckReading = Number(device.mq2_lectura_atascada) === 1;
    const gasHealth = isOffline ? 'OFFLINE' : (stuckReading ? 'REVISAR' : reportedGasHealth);
    const lastCalibration = device.mq2_ultima_calibracion;
    const cleanAirAdc = device.mq2_adc_aire_limpio;
    const canCalibrate = !isOffline && ['ADMIN', 'OPERADOR'].includes(normalizeText(currentUser?.rol));
    const gasStatus = detectorStatus(gasDetected, gasHealth, 'Detectado');
    const flameStatus = detectorStatus(flameDetected, flameHealth, 'Detectada');
    const alarmLatched = !isOffline && Number(device.alarma_enclavada) === 1;
    const alarmSilenced = alarmLatched && Number(device.alarma_silenciada) === 1;
    const dangerActive = !isOffline && Number(device.peligro_activo) === 1;
    const silencedBy = normalizeText(device.silenciada_por);
    const canSilenceAlarm = (
      alarmLatched
      && !alarmSilenced
      && ['ADMIN', 'OPERADOR'].includes(normalizeText(currentUser?.rol))
    );

    card.innerHTML = `
      <header>
        <div class="device-title">
          <strong>${escapeHtml(device.id ?? device.dispositivo_id ?? 'Sensor')}</strong>
          <span class="device-meta">${escapeHtml(device.ubicacion ?? 'Ubicación no asignada')}</span>
        </div>
        <span class="badge ${badgeClass}">${escapeHtml(state)}</span>
      </header>
      <div class="sensor-card-grid">
        <section class="sensor-card ${detectorClass(false, dhtHealth)}">
          <header>
            <div>
              <span class="sensor-model">DHT11</span>
              <strong class="sensor-name">Temperatura y humedad</strong>
            </div>
            <span class="detector-status">${escapeHtml(healthLabel(dhtHealth))}</span>
          </header>
          <div class="sensor-reading-grid">
            <div>
              <span>Temperatura</span>
              <strong>${escapeHtml(formatValue(device.temperatura, '°C'))}</strong>
            </div>
            <div>
              <span>Humedad</span>
              <strong>${escapeHtml(formatValue(device.humedad, '%'))}</strong>
            </div>
          </div>
          <small class="sensor-detail">Sensación térmica ${escapeHtml(formatValue(device.indice_calor, '°C'))}</small>
        </section>
        <section class="sensor-card mq2-card ${detectorClass(gasDetected, gasHealth)}">
          <header>
            <div>
              <span class="sensor-model">MQ-2</span>
              <strong class="sensor-name">Humo y gas</strong>
            </div>
            <span class="detector-status">${escapeHtml(gasStatus)}</span>
          </header>
          ${gasMeterMarkup(gasValue, gasThreshold, gasDetected)}
          <div class="mq2-diagnostics">
            <span>
              <small>Calentamiento</small>
              <strong>${warmupRemaining > 0 ? escapeHtml(formatDuration(warmupRemaining)) : 'Completo'}</strong>
            </span>
            <span>
              <small>Ultima calibracion</small>
              <strong>${lastCalibration ? escapeHtml(formatDate(lastCalibration)) : 'Pendiente'}</strong>
            </span>
            <span>
              <small>Base en aire limpio</small>
              <strong>${cleanAirAdc === null || cleanAirAdc === undefined ? '--' : `${escapeHtml(cleanAirAdc)} ADC`}</strong>
            </span>
          </div>
          ${stuckReading ? `
            <p class="mq2-diagnostic-warning" role="status">
              Lectura casi inmovil durante 10 minutos. Revisa alimentacion, divisor y cable AO.
            </p>
          ` : ''}
          ${canCalibrate ? `
            <button
              class="button mq2-calibration-button"
              type="button"
              data-mq2-calibrate="${escapeHtml(device.id ?? device.dispositivo_id)}"
              data-mq2-adc="${escapeHtml(gasValue ?? '')}"
              data-mq2-health="${escapeHtml(gasHealth)}"
            >Registrar calibracion</button>
          ` : ''}
        </section>
        <section class="sensor-card ${detectorClass(flameDetected, flameHealth)}">
          <header>
            <div>
              <span class="sensor-model">KY-026</span>
              <strong class="sensor-name">Detección de flama</strong>
            </div>
            <span class="detector-status">${escapeHtml(flameStatus)}</span>
          </header>
          <strong class="sensor-value">${isOffline ? 'Sin lectura' : (flameDetected ? 'Sí' : 'No')}</strong>
          <small class="sensor-detail">${isOffline ? 'Sin conexión reciente' : (flameDetected ? 'Flama presente' : 'Sin detección')}</small>
        </section>
      </div>
      ${alarmLatched ? `
        <section class="physical-alarm ${alarmSilenced ? 'is-silenced' : 'is-sounding'}" role="status">
          <div class="physical-alarm-indicator" aria-hidden="true"></div>
          <div>
            <strong>${alarmSilenced ? 'Buzzer silenciado' : 'Buzzer intermitente activo'}</strong>
            <p>
              ${alarmSilenced
                ? dangerActive
                  ? 'Los sensores todavia reportan peligro. El restablecimiento fisico esta bloqueado.'
                  : `Lecturas seguras. Falta completar la revision con el boton fisico${silencedBy === 'APP_MOVIL' ? '; silenciada desde la app' : silencedBy === 'BOTON_FISICO' ? '; silenciada desde GPIO25' : ''}.`
                : 'La alarma permanece enclavada hasta que se silencie y se complete la revision fisica.'}
            </p>
            ${canSilenceAlarm ? `
              <button
                class="button physical-alarm-silence"
                type="button"
                data-alarm-silence="${escapeHtml(device.id ?? device.dispositivo_id)}"
              >Silenciar buzzer</button>
            ` : ''}
          </div>
        </section>
      ` : ''}
      <div class="device-health">
        ${healthItem('DHT11', dhtHealth)}
        ${healthItem('MQ-2', gasHealth)}
        ${healthItem('KY-026', flameHealth)}
        ${healthItem('Conexión', device.estado_conexion ?? 'DESCONOCIDO')}
        <span>Última: <strong>${escapeHtml(formatDate(device.ultima_lectura ?? device.fecha_hora))}</strong></span>
      </div>
    `;

    elements.deviceGrid.appendChild(card);
  });
}

function renderShelly(actuadores) {
  if (!elements.shellyPanel || !elements.shellyGrid) return;
  elements.shellyPanel.hidden = actuadores.length === 0;
  elements.shellyGrid.innerHTML = '';
  if (!actuadores.length) return;

  const canControl = ['ADMIN', 'OPERADOR'].includes(normalizeText(currentUser?.rol));
  actuadores.forEach((actuador) => {
    const conexion = normalizeText(actuador.conexion || 'SIN_DATOS');
    const online = conexion === 'ONLINE';
    const outputOn = Number(actuador.salida_encendida) === 1;
    const card = document.createElement('article');
    card.className = `shelly-card ${online ? '' : 'is-offline'} ${outputOn ? 'is-on' : ''}`;
    card.innerHTML = `
      <header>
        <div class="device-title">
          <span class="sensor-model">SHELLY · CANAL ${escapeHtml(actuador.canal ?? 0)}</span>
          <strong>${escapeHtml(actuador.id)}</strong>
          <span class="device-meta">${escapeHtml(actuador.ubicacion || 'Ubicacion no asignada')}</span>
        </div>
        <span class="badge ${online ? '' : 'offline'}">${escapeHtml(conexion.replace('_', ' '))}</span>
      </header>
      <div class="shelly-output">
        <span class="shelly-output-indicator" aria-hidden="true"></span>
        <div>
          <small>${escapeHtml(actuador.funcion || 'ACTUADOR')}</small>
          <strong>${outputOn ? 'ENCENDIDA' : 'APAGADA'}</strong>
        </div>
      </div>
      <dl class="shelly-metrics">
        <div><dt>Potencia</dt><dd>${actuador.potencia_w == null ? '--' : `${Number(actuador.potencia_w).toFixed(1)} W`}</dd></div>
        <div><dt>Voltaje</dt><dd>${actuador.voltaje_v == null ? '--' : `${Number(actuador.voltaje_v).toFixed(1)} V`}</dd></div>
        <div><dt>ESP32</dt><dd>${escapeHtml(actuador.dispositivo_vinculado_id || 'Sin asociar')}</dd></div>
      </dl>
      ${actuador.ultimo_error ? `<p class="shelly-error">${escapeHtml(actuador.ultimo_error)}</p>` : ''}
      <footer>
        <small>Sincronizado: ${escapeHtml(formatDate(actuador.sincronizado_en))}</small>
        ${canControl ? `
          <button class="button shelly-control-button" type="button"
            data-shelly-id="${escapeHtml(actuador.id)}"
            data-shelly-action="${outputOn ? 'APAGAR' : 'ENCENDER'}">
            ${outputOn ? 'Apagar' : 'Encender'}
          </button>
        ` : ''}
      </footer>
    `;
    elements.shellyGrid.appendChild(card);
  });
}

function routineSchedule(routine) {
  if (normalizeText(routine.tipo_disparador) !== 'HORARIO') return 'Ejecucion manual';
  const dayNames = { 1: 'Lun', 2: 'Mar', 3: 'Mie', 4: 'Jue', 5: 'Vie', 6: 'Sab', 7: 'Dom' };
  const days = (Array.isArray(routine.dias) ? routine.dias : [])
    .map((day) => dayNames[Number(day)])
    .filter(Boolean)
    .join(', ');
  const time = String(routine.hora_local ?? '').slice(0, 5) || '--:--';
  return `${days || 'Sin dias'} · ${time}`;
}

function routineResult(value) {
  const state = normalizeText(value || 'PENDIENTE');
  if (state === 'COMPLETADA') return { label: 'Completada', className: 'is-success' };
  if (state === 'PARCIAL') return { label: 'Parcial', className: 'is-warning' };
  if (state === 'FALLIDA') return { label: 'Fallida', className: 'is-critical' };
  if (state === 'OMITIDA') return { label: 'Omitida', className: 'is-muted' };
  return { label: 'Sin ejecutar', className: 'is-muted' };
}

function renderRoutines(routines, available) {
  if (!elements.routinesPanel || !elements.routineGrid || !elements.routineSummary) return;
  const signature = JSON.stringify({ routines, available });
  if (signature === lastRoutinesSignature) return;
  lastRoutinesSignature = signature;
  elements.routineGrid.innerHTML = '';

  if (!available) {
    elements.routineSummary.textContent = 'Modulo no instalado';
    elements.routineGrid.innerHTML = `
      <div class="empty-state routine-empty">
        <strong>Rutinas no disponibles</strong>
        <span>La migracion de rutinas todavia no esta instalada.</span>
      </div>`;
    return;
  }

  const activeCount = routines.filter((routine) => Number(routine.activa) === 1).length;
  elements.routineSummary.textContent = `${activeCount} activas · ${routines.length} registradas`;
  if (!routines.length) {
    elements.routineGrid.innerHTML = `
      <div class="empty-state routine-empty">
        <strong>Sin rutinas registradas</strong>
        <span>Las rutinas configuradas desde la app apareceran aqui.</span>
      </div>`;
    return;
  }

  routines.forEach((routine, index) => {
    const active = Number(routine.activa) === 1;
    const unavailable = Number(routine.acciones_no_disponibles) > 0;
    const result = routineResult(routine.ultimo_estado);
    const actions = Array.isArray(routine.acciones_resumen) ? routine.acciones_resumen : [];
    const card = document.createElement('article');
    card.className = `routine-card ${active ? 'is-active' : 'is-inactive'} ${unavailable ? 'has-warning' : ''}`;
    card.style.setProperty('--routine-delay', `${Math.min(index * 55, 275)}ms`);
    card.innerHTML = `
      <header class="routine-card-header">
        <div class="routine-identity">
          <span class="routine-icon" aria-hidden="true"></span>
          <div>
            <strong>${escapeHtml(routine.nombre || 'Rutina')}</strong>
            <span>${escapeHtml(routineSchedule(routine))}</span>
          </div>
        </div>
        <span class="routine-state ${active ? 'is-active' : ''}">${active ? 'Activa' : 'Pausada'}</span>
      </header>
      ${routine.descripcion ? `<p class="routine-description">${escapeHtml(routine.descripcion)}</p>` : ''}
      <div class="routine-actions" aria-label="Acciones de la rutina">
        ${actions.length ? actions.map((action) => `
          <span class="routine-action">
            <b>${escapeHtml(action.accion === 'ENCENDER' ? 'Encender' : 'Apagar')}</b>
            ${escapeHtml(action.actuador_nombre || action.actuador_id || 'Shelly')}
          </span>`).join('') : '<span class="routine-action is-empty">Sin acciones disponibles</span>'}
      </div>
      <footer class="routine-footer">
        <span>Ultima: <strong>${escapeHtml(formatDate(routine.ultima_ejecucion))}</strong></span>
        <span class="routine-result ${result.className}">${escapeHtml(result.label)}</span>
      </footer>
      ${unavailable ? '<p class="routine-warning">Una o mas acciones requieren revisar su dispositivo.</p>' : ''}
    `;
    elements.routineGrid.appendChild(card);
  });
}

async function controlShelly(button) {
  const action = button.dataset.shellyAction;
  const actuatorId = button.dataset.shellyId;
  if (action === 'ENCENDER' && !window.confirm(`Encender ${actuatorId}? Verifica que la salida controle el equipo correcto.`)) return;
  button.disabled = true;
  try {
    const response = await fetch(SHELLY_COMMAND_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ actuador_id: actuatorId, accion: action })
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result?.ok === false) throw new Error(result?.error || 'No fue posible controlar el Shelly');
    if (!result?.data?.aplicado) {
      throw new Error(result?.data?.error || 'Shelly no confirmo el cambio del canal');
    }
    elements.syncStatus.textContent = `${actuatorId}: ${action === 'ENCENDER' ? 'encendido' : 'apagado'}`;
    await loadDashboard();
  } catch (error) {
    elements.syncStatus.textContent = error.message || 'No fue posible controlar el Shelly';
  } finally {
    button.disabled = false;
  }
}

function healthLabel(health) {
  if (health === 'OFFLINE') return 'Offline';
  if (health === 'OK') return 'Operativo';
  if (health === 'CALENTANDO') return 'Calentando';
  if (health === 'REVISAR') return 'Revisar';
  if (health === 'FALLO') return 'Fallo';
  return health || 'Sin datos';
}

function detectorStatus(detected, health, detectedLabel) {
  if (detected) return detectedLabel;
  if (health === 'OFFLINE') return 'Offline';
  if (health === 'CALENTANDO') return 'Calentando';
  if (health === 'REVISAR') return 'Revisar';
  if (health === 'FALLO') return 'Fallo';
  return 'Normal';
}

function detectorClass(detected, health) {
  if (detected) return 'is-detected';
  if (health === 'OFFLINE') return 'is-offline';
  if (health === 'FALLO') return 'is-failed';
  if (['CALENTANDO', 'REVISAR'].includes(health)) return 'is-warning';
  return '';
}

function isGasDetected(device) {
  if (device.gas_detectado !== undefined && device.gas_detectado !== null) {
    return Number(device.gas_detectado) === 1 || device.gas_detectado === true;
  }

  const value = Number(device.humo ?? device.gas ?? device.gas_raw);
  const threshold = numberFrom(device.gas_umbral, 1600);
  return Number.isFinite(value) && value >= threshold;
}

function isFlameDetected(device) {
  const value = device.flama ?? device.flama_detectada;
  return ['1', 'TRUE', 'DETECTADA', 'SI'].includes(normalizeText(value));
}

function healthItem(label, value) {
  const health = normalizeText(value || 'DESCONOCIDO');
  const healthClass = ['FALLO', 'OFFLINE'].includes(health)
    ? 'failed'
    : (['REVISAR', 'CALENTANDO'].includes(health) ? 'needs-review' : '');

  return `<span>${escapeHtml(label)}: <strong class="${healthClass}">${escapeHtml(health)}</strong></span>`;
}

function renderAlerts(alertas) {
  elements.alertsTable.innerHTML = '';

  if (!alertas.length) {
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = 7;
    cell.appendChild(emptyState());
    row.appendChild(cell);
    elements.alertsTable.appendChild(row);
    return;
  }

  alertas.slice(0, 5).forEach((alert) => {
    const row = document.createElement('tr');
    const severity = normalizeSeverity(alert.severidad ?? alert.tipo_alerta);
    const severityClass = classForSeverity(severity);
    const originClass = classForAlertOrigin(alert.tipo_alerta ?? alert.tipo);
    const careState = alertCareState(alert);
    const alertId = Number(alert.id);
    if (Number.isInteger(alertId) && alertId > 0) {
      row.dataset.alertId = String(alertId);
      row.tabIndex = 0;
      row.title = 'Abrir detalle del incidente';
    }
    const canManage = (
      Number.isInteger(alertId)
      && alertId > 0
      && ['ADMIN', 'OPERADOR'].includes(normalizeText(currentUser?.rol))
    );
    const managementDetail = alert.responsable
      ? `<small class="care-detail">${escapeHtml(alert.responsable)}${alert.gestion_fecha ? ` · ${escapeHtml(formatDate(alert.gestion_fecha))}` : ''}</small>`
      : '';
    let managementActions = '<span class="care-state care-resolved">Resuelta</span>';

    if (careState === 'NUEVA' && canManage) {
      managementActions = `
        <div class="alert-actions">
          <button class="alert-action-button" type="button" data-alert-action="RECONOCER" data-alert-id="${alertId}">Reconocer</button>
          <button class="alert-action-button resolve" type="button" data-alert-action="RESOLVER" data-alert-id="${alertId}">Resolver</button>
        </div>
      `;
    } else if (careState === 'RECONOCIDA' && canManage) {
      managementActions = `
        <span class="care-state care-recognized">Reconocida</span>
        ${managementDetail}
        <button class="alert-action-button resolve" type="button" data-alert-action="RESOLVER" data-alert-id="${alertId}">Resolver</button>
      `;
    } else if (careState === 'RESUELTA') {
      managementActions = `<span class="care-state care-resolved">Resuelta</span>${managementDetail}`;
    }

    row.innerHTML = `
      <td data-label="Fecha">${escapeHtml(formatDate(alert.fecha_hora ?? alert.fecha ?? alert.created_at))}</td>
      <td data-label="Dispositivo"><strong>${escapeHtml(alert.dispositivo_id ?? alert.sensor ?? '--')}</strong></td>
      <td data-label="Ubicacion">${escapeHtml(alert.ubicacion ?? '--')}</td>
      <td data-label="Origen"><span class="alert-origin ${originClass}">${escapeHtml(alert.tipo_alerta ?? alert.tipo ?? '--')}</span></td>
      <td data-label="Valor">${escapeHtml(formatAlertValue(alert))}</td>
      <td data-label="Estado"><span class="severity ${severityClass}">${escapeHtml(severity)}</span></td>
      <td class="care-cell" data-label="Atencion">${managementActions}</td>
    `;

    elements.alertsTable.appendChild(row);
  });
}

function alertCareState(alert) {
  if (isResolved(alert.atendida ?? alert.resuelta)) return 'RESUELTA';
  const state = normalizeText(alert.estado_atencion);
  return state === 'RECONOCIDA' ? 'RECONOCIDA' : 'NUEVA';
}

function findAlertButton(button) {
  const row = button.closest('tr');
  if (!row) return null;

  return {
    id: Number(button.dataset.alertId),
    action: normalizeText(button.dataset.alertAction),
    device: row.querySelector('[data-label="Dispositivo"]')?.textContent?.trim() || '--',
    origin: row.querySelector('[data-label="Origen"]')?.textContent?.trim() || '--'
  };
}

function openAlertDialog(button) {
  const alert = findAlertButton(button);
  if (!alert || !['RECONOCER', 'RESOLVER'].includes(alert.action)) return;

  const isResolve = alert.action === 'RESOLVER';
  elements.alertDialogId.value = String(alert.id);
  elements.alertDialogAction.value = alert.action;
  elements.alertDialogTitle.textContent = isResolve ? 'Resolver alerta' : 'Reconocer alerta';
  elements.alertDialogSummary.textContent = `${alert.device} · ${alert.origin}`;
  elements.alertDialogSubmit.textContent = isResolve ? 'Marcar resuelta' : 'Confirmar reconocimiento';
  elements.alertResponsible.value = currentUser?.nombre ?? '';
  elements.alertComment.value = '';
  elements.alertFormMessage.textContent = '';
  elements.alertDialog.showModal();
  elements.alertResponsible.focus();
}

function closeAlertDialog() {
  if (elements.alertDialog.open) elements.alertDialog.close();
}

async function submitAlertAction(event) {
  event.preventDefault();
  if (!elements.alertActionForm.reportValidity()) return;

  const responsable = elements.alertResponsible.value.trim();
  const payload = {
    alerta_id: Number(elements.alertDialogId.value),
    accion: elements.alertDialogAction.value,
    responsable,
    comentario: elements.alertComment.value.trim()
  };

  elements.alertDialogSubmit.disabled = true;
  elements.alertFormMessage.textContent = 'Guardando...';

  try {
    const response = await fetch(ALERT_ACTION_URL, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken
      },
      credentials: 'same-origin',
      cache: 'no-store',
      body: JSON.stringify(payload)
    });
    if (response.status === 401) {
      redirectToLogin();
      return;
    }
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result?.ok === false) {
      throw new Error(result?.error ?? `HTTP ${response.status}`);
    }

    closeAlertDialog();
    await loadDashboard();
  } catch (error) {
    elements.alertFormMessage.textContent = error.message || 'No fue posible actualizar la alerta.';
  } finally {
    elements.alertDialogSubmit.disabled = false;
  }
}
function announceNewAlarm(alertas) {
  const newest = alertas[0] ?? null;
  const newestId = newest ? String(newest.id ?? newest.fecha_hora ?? '') : '';

  if (lastSeenAlertId === null) {
    lastSeenAlertId = newestId;
    return;
  }

  if (
    newestId === '' ||
    newestId === lastSeenAlertId ||
    isResolved(newest?.atendida ?? newest?.resuelta)
  ) {
    return;
  }

  lastSeenAlertId = newestId;
  clearTimeout(alarmPulseTimer);
  elements.statusPanel.classList.add('is-new-alarm');
  alarmPulseTimer = setTimeout(() => {
    elements.statusPanel.classList.remove('is-new-alarm');
  }, 6000);

  if (typeof navigator.vibrate === 'function') {
    navigator.vibrate([250, 120, 250]);
  }
}

function classForAlertOrigin(value) {
  const type = normalizeText(value);
  const hasFlame = type.includes('FLAMA') || type.includes('FUEGO');
  const hasSmoke = type.includes('HUMO') || type.includes('GAS');

  if (hasFlame && hasSmoke) return 'combined';
  if (hasFlame) return 'flame';
  if (hasSmoke) return 'smoke';
  if (type.includes('TEMPERATURA') || type.includes('DHT')) return 'temperature';
  if (type.includes('SIN CONEXION') || type.includes('DESCONECT')) return 'connectivity';
  return '';
}

function formatAlertValue(alert) {
  const type = normalizeText(alert.tipo_alerta ?? alert.tipo);
  const value = alert.valor_sensor ?? alert.valor;

  if (type.includes('SIN CONEXION') || type.includes('DESCONECT')) {
    return 'Sin comunicacion';
  }
  if (type.includes('FLAMA') && !type.includes('HUMO') && !type.includes('GAS')) {
    return 'Detectada';
  }
  if (type.includes('HUMO') || type.includes('GAS')) {
    return value === undefined || value === null ? '--' : `${value} ADC`;
  }
  if (type.includes('TEMPERATURA')) {
    return value === undefined || value === null ? '--' : `${value} °C`;
  }
  return value ?? '--';
}

function emptyState() {
  return elements.emptyStateTemplate.content.firstElementChild.cloneNode(true);
}

function formatValue(value, unit) {
  if (value === undefined || value === null || value === '') return '--';
  const number = Number(value);
  const text = Number.isFinite(number) ? number.toFixed(unit === '' ? 0 : 1) : String(value);
  return `${text}${unit}`;
}

function formatFlame(value) {
  if (value === undefined || value === null || value === '') return '--';
  const normalized = String(value).toLowerCase();
  if (['1', 'true', 'detectada', 'si', 'sí'].includes(normalized)) return 'Detectada';
  if (['0', 'false', 'no'].includes(normalized)) return 'No';
  return String(value);
}

function formatDate(value) {
  if (!value) return '--';
  let normalizedDate = String(value).replace(' ', 'T');
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/.test(normalizedDate)) {
    normalizedDate += 'Z';
  }
  const date = new Date(normalizedDate);
  if (Number.isNaN(date.getTime())) return String(value);

  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
}

function formatDuration(seconds) {
  const safeSeconds = Math.max(0, Math.round(numberFrom(seconds, 0)));
  const minutes = Math.floor(safeSeconds / 60);
  const remainder = safeSeconds % 60;
  if (minutes === 0) return `${remainder} s`;
  return `${minutes} min ${String(remainder).padStart(2, '0')} s`;
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function parseApiDate(value) {
  if (!value) return null;
  let normalizedDate = String(value).replace(' ', 'T');
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/.test(normalizedDate)) {
    normalizedDate += 'Z';
  }
  const date = new Date(normalizedDate);
  return Number.isNaN(date.getTime()) ? null : date;
}

function chartNumber(value) {
  if (value === undefined || value === null || value === '') return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

function average(values) {
  const valid = values.filter((value) => value !== null);
  if (!valid.length) return null;
  return valid.reduce((total, value) => total + value, 0) / valid.length;
}

function maxValue(values) {
  const valid = values.filter((value) => value !== null);
  return valid.length ? Math.max(...valid) : null;
}

function buildLiveSample(dispositivos) {
  const readings = dispositivos.filter((device) => (
    device.ultima_lectura
    && [device.temperatura, device.humedad, device.humo, device.gas_raw, device.flama]
      .some((value) => chartNumber(value) !== null)
  ));
  if (!readings.length) return null;

  const timestamps = readings
    .map((device) => parseApiDate(device.ultima_lectura))
    .filter(Boolean);
  const newestTimestamp = timestamps.length
    ? new Date(Math.max(...timestamps.map((date) => date.getTime())))
    : new Date();

  return {
    periodo: newestTimestamp.toISOString().slice(0, 19).replace('T', ' '),
    temperatura: average(readings.map((device) => chartNumber(device.temperatura))),
    humedad: average(readings.map((device) => chartNumber(device.humedad))),
    gas_raw: maxValue(readings.map((device) => chartNumber(device.humo ?? device.gas_raw ?? device.gas))),
    flama_detectada: readings.some(isFlameDetected) ? 1 : 0,
    alarmas: readings.filter((device) => normalizeState(device.estado_general) === 'ALARMA').length,
    alertas: readings.filter((device) => normalizeState(device.estado_general) === 'ALERTA').length,
    revisiones_dht: readings.filter((device) => ['REVISAR', 'FALLO'].includes(normalizeText(device.salud_dht))).length,
    revisiones_mq2: readings.filter((device) => ['REVISAR', 'FALLO'].includes(normalizeText(device.salud_mq2))).length,
    revisiones_flama: readings.filter((device) => ['REVISAR', 'FALLO'].includes(normalizeText(device.salud_flama))).length
  };
}

function mergedChartSeries() {
  const cutoff = Date.now() - currentRangeHours * 60 * 60 * 1000;
  const samples = new Map();

  [...historicalChartSeries, ...liveChartSamples].forEach((item) => {
    const date = parseApiDate(item.periodo);
    if (date && date.getTime() >= cutoff) {
      samples.set(date.toISOString(), item);
    }
  });

  return [...samples.values()].sort((left, right) => (
    (parseApiDate(left.periodo)?.getTime() ?? 0)
      - (parseApiDate(right.periodo)?.getTime() ?? 0)
  ));
}

function appendLiveChartSample(dispositivos) {
  const sample = buildLiveSample(dispositivos);
  if (!sample) return;

  const sampleTime = parseApiDate(sample.periodo)?.getTime();
  const lastSampleTime = parseApiDate(liveChartSamples.at(-1)?.periodo)?.getTime();
  if (sampleTime === lastSampleTime && liveChartSamples.length) {
    liveChartSamples[liveChartSamples.length - 1] = sample;
  } else {
    liveChartSamples.push(sample);
  }
  liveChartSamples = liveChartSamples.slice(-150);

  const thresholds = dispositivos
    .map((device) => chartNumber(device.gas_umbral))
    .filter((value) => value !== null);
  if (thresholds.length) currentGasThreshold = Math.min(...thresholds);

  const merged = mergedChartSeries();
  if (merged.length) {
    renderCharts(merged, currentGasThreshold, currentRangeHours);
    elements.chartLiveIndicator.classList.add('is-active');
    elements.chartStatus.textContent = `${merged.length} puntos visibles · lectura en vivo cada 2 s · historial cada 30 s`;
  }
}

function chartLabel(value, rangeHours) {
  const date = parseApiDate(value);
  if (!date) return String(value ?? '--');

  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const hour = String(date.getHours()).padStart(2, '0');
  const minute = String(date.getMinutes()).padStart(2, '0');
  return rangeHours > 24
    ? `${day}/${month} ${hour}:${minute}`
    : `${hour}:${minute}`;
}

function exactChartLabel(value) {
  const date = parseApiDate(value);
  if (!date) return String(value ?? '--');

  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  }).format(date);
}

function prepareCanvas(canvas) {
  const container = canvas.parentElement;
  const width = Math.floor(container?.clientWidth ?? 0);
  const height = Math.floor(container?.clientHeight ?? 0);

  if (width < 100 || height < 100) {
    return null;
  }

  const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
  const renderWidth = Math.floor(width * pixelRatio);
  const renderHeight = Math.floor(height * pixelRatio);

  if (canvas.width !== renderWidth) canvas.width = renderWidth;
  if (canvas.height !== renderHeight) canvas.height = renderHeight;

  const context = canvas.getContext('2d');
  context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
  context.clearRect(0, 0, width, height);
  return { context, width, height };
}

function hideChartTooltips(except = null) {
  document.querySelectorAll('.chart-tooltip.is-visible').forEach((tooltip) => {
    if (tooltip !== except) tooltip.classList.remove('is-visible');
  });
}

function showChartTooltip(canvas, index, requestedTop = 34) {
  const state = chartInteractionState.get(canvas);
  if (!state) return;

  const container = canvas.parentElement;
  let tooltip = container.querySelector('.chart-tooltip');
  if (!tooltip) {
    tooltip = document.createElement('div');
    tooltip.className = 'chart-tooltip';
    tooltip.setAttribute('role', 'status');
    container.appendChild(tooltip);
  }

  tooltip.replaceChildren();
  const heading = document.createElement('strong');
  heading.textContent = state.config.tooltipLabels?.[index]
    ?? state.config.labels[index]
    ?? '--';
  tooltip.appendChild(heading);

  state.config.series.forEach((serie) => {
    const value = serie.values[index];
    if (value === null || value === undefined) return;

    const row = document.createElement('span');
    row.textContent = `${serie.label}: `;
    const exactValue = document.createElement('b');
    exactValue.textContent = serie.tooltipFormatter
      ? serie.tooltipFormatter(value)
      : formatAxisValue(value);
    row.appendChild(exactValue);
    tooltip.appendChild(row);
  });

  const horizontalMargin = Math.min(120, state.width / 2);
  const left = Math.max(
    horizontalMargin,
    Math.min(state.xFor(index), state.width - horizontalMargin)
  );
  const top = Math.max(10, Math.min(requestedTop, state.height - 105));
  tooltip.style.left = `${left}px`;
  tooltip.style.top = `${top}px`;
  tooltip.classList.add('is-visible');
  hideChartTooltips(tooltip);
  state.activeIndex = index;
}

function bindChartInteraction(canvas) {
  if (canvas.dataset.chartInteractive === 'true') return;
  canvas.dataset.chartInteractive = 'true';

  canvas.addEventListener('click', (event) => {
    const state = chartInteractionState.get(canvas);
    if (!state) return;

    const bounds = canvas.getBoundingClientRect();
    const relativeX = event.clientX - bounds.left;
    const plotRatio = Math.max(
      0,
      Math.min(1, (relativeX - state.padding.left) / Math.max(state.plotWidth, 1))
    );
    const index = state.pointCount <= 1
      ? 0
      : Math.round(plotRatio * (state.pointCount - 1));
    showChartTooltip(canvas, index, event.clientY - bounds.top + 10);
  });

  canvas.addEventListener('keydown', (event) => {
    const state = chartInteractionState.get(canvas);
    if (!state || !['ArrowLeft', 'ArrowRight', 'Enter', ' '].includes(event.key)) return;

    event.preventDefault();
    let index = state.activeIndex ?? state.pointCount - 1;
    if (event.key === 'ArrowLeft') index = Math.max(0, index - 1);
    if (event.key === 'ArrowRight') index = Math.min(state.pointCount - 1, index + 1);
    showChartTooltip(canvas, index);
  });
}

function drawChart(canvas, config) {
  const preparedCanvas = prepareCanvas(canvas);
  if (!preparedCanvas) return;

  const { context, width, height } = preparedCanvas;
  const needsWrappedLegend = config.series.length > 2 && width < 720;
  const padding = {
    top: needsWrappedLegend ? 54 : 34,
    right: 58,
    bottom: 38,
    left: config.leftPadding ?? 52
  };
  const plotWidth = width - padding.left - padding.right;
  const plotHeight = height - padding.top - padding.bottom;
  const pointCount = Math.max(config.labels.length, 1);

  const xFor = (index) =>
    padding.left + (pointCount <= 1 ? plotWidth / 2 : (index / (pointCount - 1)) * plotWidth);
  const yFor = (value, min, max) =>
    padding.top + plotHeight - ((value - min) / Math.max(max - min, 1)) * plotHeight;

  context.font = '11px system-ui, sans-serif';
  context.lineWidth = 1;

  for (let step = 0; step <= 4; step++) {
    const ratio = step / 4;
    const y = padding.top + plotHeight * ratio;
    const leftValue = config.leftMax - (config.leftMax - config.leftMin) * ratio;
    const rightValue = config.rightMax - (config.rightMax - config.rightMin) * ratio;

    context.strokeStyle = 'rgba(154, 168, 179, 0.12)';
    context.beginPath();
    context.moveTo(padding.left, y);
    context.lineTo(width - padding.right, y);
    context.stroke();

    context.fillStyle = '#9aa8b3';
    context.textAlign = 'right';
    context.fillText(
      config.leftFormatter ? config.leftFormatter(leftValue) : formatAxisValue(leftValue),
      padding.left - 8,
      y + 4
    );
    context.textAlign = 'left';
    context.fillText(
      config.rightFormatter ? config.rightFormatter(rightValue) : formatAxisValue(rightValue),
      width - padding.right + 8,
      y + 4
    );
  }

  const longestLabel = Math.max(...config.labels.map((label) => String(label).length), 5);
  const minimumTickSpacing = longestLabel > 5 ? 110 : 72;
  const availableTicks = Math.max(1, Math.floor(plotWidth / minimumTickSpacing) + 1);
  const xTickCount = Math.min(5, pointCount, availableTicks);
  for (let tick = 0; tick < xTickCount; tick++) {
    const index = xTickCount === 1
      ? 0
      : Math.round((tick / (xTickCount - 1)) * (pointCount - 1));
    context.fillStyle = '#9aa8b3';
    context.textAlign = tick === 0 ? 'left' : (tick === xTickCount - 1 ? 'right' : 'center');
    context.fillText(config.labels[index] ?? '', xFor(index), height - 12);
  }

  if (Number.isInteger(config.markerIndex) && config.markerIndex >= 0) {
    const markerX = xFor(Math.min(config.markerIndex, pointCount - 1));
    context.save();
    context.strokeStyle = '#ff453a';
    context.lineWidth = 1;
    context.setLineDash([4, 4]);
    context.beginPath();
    context.moveTo(markerX, padding.top);
    context.lineTo(markerX, padding.top + plotHeight);
    context.stroke();
    context.setLineDash([]);
    context.fillStyle = '#ff453a';
    context.textAlign = markerX > width - 120 ? 'right' : 'left';
    context.fillText('Alerta', markerX + (markerX > width - 120 ? -5 : 5), padding.top + 12);
    context.restore();
  }

  let legendX = padding.left;
  let legendY = 14;
  config.series.forEach((serie) => {
    const legendWidth = context.measureText(serie.label).width + 48;
    if (legendX + legendWidth > width - padding.right && legendX > padding.left) {
      legendX = padding.left;
      legendY += 20;
    }
    context.strokeStyle = serie.color;
    context.lineWidth = 3;
    context.beginPath();
    context.moveTo(legendX, legendY);
    context.lineTo(legendX + 16, legendY);
    context.stroke();
    context.fillStyle = '#e0e0e0';
    context.textAlign = 'left';
    context.fillText(serie.label, legendX + 22, legendY + 4);
    legendX += legendWidth;
  });

  config.series.forEach((serie) => {
    const min = serie.axis === 'right' ? config.rightMin : config.leftMin;
    const max = serie.axis === 'right' ? config.rightMax : config.leftMax;
    let previous = null;

    context.strokeStyle = serie.color;
    context.lineWidth = serie.width ?? 2;
    context.setLineDash(serie.dash ?? []);
    context.beginPath();

    serie.values.forEach((value, index) => {
      if (value === null) {
        previous = null;
        return;
      }

      const x = xFor(index);
      const y = yFor(value, min, max);
      if (!previous) {
        context.moveTo(x, y);
      } else if (serie.stepped) {
        context.lineTo(x, previous.y);
        context.lineTo(x, y);
      } else {
        context.lineTo(x, y);
      }
      previous = { x, y };
    });
    context.stroke();
    context.setLineDash([]);

    if (serie.points !== false) {
      serie.values.forEach((value, index) => {
        if (value === null) return;
        context.beginPath();
        context.fillStyle = serie.color;
        context.arc(xFor(index), yFor(value, min, max), 3, 0, Math.PI * 2);
        context.fill();
      });
    }
  });

  chartInteractionState.set(canvas, {
    config,
    width,
    height,
    padding,
    plotWidth,
    pointCount,
    xFor,
    activeIndex: chartInteractionState.get(canvas)?.activeIndex
  });
  bindChartInteraction(canvas);
}

function drawBinaryTimeline(canvas, config) {
  const preparedCanvas = prepareCanvas(canvas);
  if (!preparedCanvas) return;

  const { context, width, height } = preparedCanvas;
  const padding = { top: 36, right: 26, bottom: 38, left: 26 };
  const plotWidth = width - padding.left - padding.right;
  const pointCount = Math.max(config.labels.length, 1);
  const xFor = (index) =>
    padding.left + (pointCount <= 1 ? plotWidth / 2 : (index / (pointCount - 1)) * plotWidth);
  const bandTop = padding.top + 18;
  const bandHeight = Math.max(42, height - padding.top - padding.bottom - 30);

  context.font = '11px system-ui, sans-serif';
  context.fillStyle = '#2ecf7a';
  context.fillRect(padding.left, 12, 14, 6);
  context.fillStyle = '#e0e0e0';
  context.textAlign = 'left';
  context.fillText('Normal', padding.left + 20, 18);
  context.fillStyle = '#ff453a';
  context.fillRect(padding.left + 88, 12, 14, 6);
  context.fillStyle = '#e0e0e0';
  context.fillText('Detectada', padding.left + 108, 18);

  context.fillStyle = 'rgba(46, 207, 122, 0.14)';
  context.fillRect(padding.left, bandTop, plotWidth, bandHeight);

  config.values.forEach((value, index) => {
    const startX = index === 0
      ? padding.left
      : (xFor(index - 1) + xFor(index)) / 2;
    const endX = index === pointCount - 1
      ? width - padding.right
      : (xFor(index) + xFor(index + 1)) / 2;

    context.fillStyle = value >= 1
      ? 'rgba(255, 69, 58, 0.78)'
      : 'rgba(46, 207, 122, 0.24)';
    context.fillRect(startX, bandTop, Math.max(1, endX - startX), bandHeight);

    context.strokeStyle = value >= 1 ? '#ff453a' : '#2ecf7a';
    context.lineWidth = 2;
    context.beginPath();
    context.moveTo(xFor(index), bandTop);
    context.lineTo(xFor(index), bandTop + bandHeight);
    context.stroke();
  });

  context.strokeStyle = 'rgba(224, 224, 224, 0.16)';
  context.lineWidth = 1;
  context.strokeRect(padding.left, bandTop, plotWidth, bandHeight);

  if (Number.isInteger(config.markerIndex) && config.markerIndex >= 0) {
    const markerX = xFor(Math.min(config.markerIndex, pointCount - 1));
    context.save();
    context.strokeStyle = '#ff453a';
    context.lineWidth = 2;
    context.setLineDash([4, 4]);
    context.beginPath();
    context.moveTo(markerX, bandTop - 8);
    context.lineTo(markerX, bandTop + bandHeight + 8);
    context.stroke();
    context.restore();
  }

  const longestLabel = Math.max(...config.labels.map((label) => String(label).length), 5);
  const minimumTickSpacing = longestLabel > 5 ? 110 : 72;
  const availableTicks = Math.max(1, Math.floor(plotWidth / minimumTickSpacing) + 1);
  const xTickCount = Math.min(5, pointCount, availableTicks);
  for (let tick = 0; tick < xTickCount; tick++) {
    const index = xTickCount === 1
      ? 0
      : Math.round((tick / (xTickCount - 1)) * (pointCount - 1));
    context.fillStyle = '#9aa8b3';
    context.textAlign = tick === 0 ? 'left' : (tick === xTickCount - 1 ? 'right' : 'center');
    context.fillText(config.labels[index] ?? '', xFor(index), height - 12);
  }

  const interactionConfig = {
    labels: config.labels,
    tooltipLabels: config.tooltipLabels,
    series: [{
      label: 'Flama',
      values: config.values,
      tooltipFormatter: (value) => value >= 1 ? 'Detectada' : 'Normal'
    }]
  };
  chartInteractionState.set(canvas, {
    config: interactionConfig,
    width,
    height,
    padding,
    plotWidth,
    pointCount,
    xFor,
    activeIndex: chartInteractionState.get(canvas)?.activeIndex
  });
  bindChartInteraction(canvas);
}

function formatAxisValue(value) {
  if (Math.abs(value) >= 100) return Math.round(value).toString();
  return Number(value.toFixed(1)).toString();
}

function numericExtent(values, fallbackMin, fallbackMax, margin = 0) {
  const valid = values.filter((value) => value !== null);
  if (!valid.length) return [fallbackMin, fallbackMax];

  const minimum = Math.min(...valid);
  const maximum = Math.max(...valid);
  if (minimum === maximum) {
    return [minimum - Math.max(margin, 1), maximum + Math.max(margin, 1)];
  }
  return [minimum - margin, maximum + margin];
}

function renderCharts(series, gasThreshold, rangeHours) {
  const labels = series.map((item) => chartLabel(item.periodo, rangeHours));
  const tooltipLabels = series.map((item) => exactChartLabel(item.periodo));
  const temperature = series.map((item) => chartNumber(item.temperatura));
  const humidity = series.map((item) => chartNumber(item.humedad));
  const gas = series.map((item) => chartNumber(item.gas_raw));
  const flame = series.map((item) => Number(item.flama_detectada) === 1 ? 1 : 0);
  const alarmEvents = series.map((item) =>
    (chartNumber(item.alarmas) ?? 0) + (chartNumber(item.alertas) ?? 0)
  );
  const sensorReviews = series.map((item) =>
    (chartNumber(item.revisiones_dht) ?? 0)
      + (chartNumber(item.revisiones_mq2) ?? 0)
      + (chartNumber(item.revisiones_flama) ?? 0)
  );
  const [temperatureMin, temperatureMax] = numericExtent(temperature, 0, 50, 2);
  const validGasValues = gas.filter((value) => value !== null);
  const gasAxisMax = Math.min(
    4095,
    Math.max(gasThreshold * 1.25, ...validGasValues, 100)
  );
  const eventMax = Math.max(1, Math.ceil(Math.max(...alarmEvents, ...sensorReviews, 0)));

  lastChartState = { series, gasThreshold, rangeHours };

  drawChart(elements.environmentChart, {
    labels,
    tooltipLabels,
    leftMin: temperatureMin,
    leftMax: temperatureMax,
    rightMin: 0,
    rightMax: 100,
    leftPadding: 62,
    leftFormatter: (value) => `${Number(value.toFixed(1))} °C`,
    rightFormatter: (value) => `${Math.round(value)} %`,
    series: [
      {
        label: 'Temperatura',
        values: temperature,
        color: '#00a3ff',
        axis: 'left',
        tooltipFormatter: (value) => `${Number(value.toFixed(2))} °C`
      },
      {
        label: 'Humedad',
        values: humidity,
        color: '#2ecf7a',
        axis: 'right',
        tooltipFormatter: (value) => `${Number(value.toFixed(2))} %`
      }
    ]
  });

  drawChart(elements.gasChart, {
    labels,
    tooltipLabels,
    leftMin: 0,
    leftMax: gasAxisMax,
    rightMin: 0,
    rightMax: 1,
    leftPadding: 72,
    leftFormatter: (value) => `${Math.round(value)} ADC`,
    rightFormatter: () => '',
    series: [
      {
        label: 'Humo/Gas',
        values: gas,
        color: '#ffb000',
        axis: 'left',
        tooltipFormatter: (value) => `${Number(value.toFixed(2))} ADC`
      },
      {
        label: 'Umbral',
        values: series.map(() => gasThreshold),
        color: '#ff453a',
        axis: 'left',
        width: 1,
        dash: [6, 5],
        points: false,
        tooltipFormatter: (value) => `${Math.round(value)} ADC`
      }
    ]
  });

  drawBinaryTimeline(elements.flameChart, {
    labels,
    tooltipLabels,
    values: flame
  });

  drawChart(elements.connectionChart, {
    labels,
    tooltipLabels,
    leftMin: 0,
    leftMax: eventMax,
    rightMin: 0,
    rightMax: eventMax,
    leftFormatter: (value) => Math.round(value).toString(),
    rightFormatter: () => '',
    series: [
      {
        label: 'Alertas',
        values: alarmEvents,
        color: '#ff453a',
        axis: 'left',
        stepped: true,
        tooltipFormatter: (value) => Math.round(value).toString()
      },
      {
        label: 'Muestras con revisión',
        values: sensorReviews,
        color: '#ffb000',
        axis: 'left',
        stepped: true,
        tooltipFormatter: (value) => Math.round(value).toString()
      }
    ]
  });
}

function nearestSeriesIndex(series, targetValue) {
  const target = parseApiDate(targetValue)?.getTime();
  if (!Number.isFinite(target) || !series.length) return -1;

  let nearestIndex = 0;
  let nearestDistance = Number.POSITIVE_INFINITY;
  series.forEach((item, index) => {
    const timestamp = parseApiDate(item.periodo)?.getTime();
    if (!Number.isFinite(timestamp)) return;
    const distance = Math.abs(timestamp - target);
    if (distance < nearestDistance) {
      nearestDistance = distance;
      nearestIndex = index;
    }
  });
  return nearestIndex;
}

function clearIncidentCharts() {
  [
    elements.incidentEnvironmentChart,
    elements.incidentGasChart,
    elements.incidentFlameChart
  ].forEach((canvas) => {
    prepareCanvas(canvas);
    chartInteractionState.delete(canvas);
    canvas.parentElement.querySelector('.chart-tooltip')?.remove();
  });
}

function renderIncident(payload) {
  const alert = payload?.alerta ?? {};
  const series = Array.isArray(payload?.serie) ? payload.serie : [];
  const windowInfo = payload?.ventana ?? {};
  const careState = alertCareState(alert);
  const alertType = normalizeText(alert.tipo_alerta);
  const isConnectivityAlert = alertType.includes('SIN CONEXION')
    || alertType.includes('DESCONECT');

  elements.incidentSummary.innerHTML = `
    <div class="incident-fact">
      <span>Dispositivo</span>
      <strong>${escapeHtml(alert.dispositivo_id ?? '--')}</strong>
      <small>${escapeHtml(alert.ubicacion ?? '--')}</small>
    </div>
    <div class="incident-fact">
      <span>Origen</span>
      <strong>${escapeHtml(alert.tipo_alerta ?? '--')}</strong>
      <small>${escapeHtml(formatAlertValue(alert))}</small>
    </div>
    <div class="incident-fact">
      <span>Momento de alerta</span>
      <strong>${escapeHtml(formatDate(alert.fecha_hora))}</strong>
      <small>${escapeHtml(alert.severidad ?? '--')}</small>
    </div>
    <div class="incident-fact">
      <span>Atencion</span>
      <strong>${escapeHtml(careState)}</strong>
      <small>${escapeHtml(alert.responsable ?? 'Sin responsable')}</small>
    </div>
  `;

  document.querySelector('.incident-chart-grid')?.classList.toggle(
    'is-connectivity-event',
    isConnectivityAlert
  );
  if (isConnectivityAlert) {
    clearIncidentCharts();
    elements.incidentStatus.textContent = careState === 'RESUELTA'
      ? 'La comunicacion fue restablecida. El sistema cerro esta alerta automaticamente.'
      : 'El dispositivo dejo de enviar lecturas. Revisa alimentacion, Wi-Fi y acceso a internet.';
    return;
  }

  if (!series.length) {
    clearIncidentCharts();
    elements.incidentStatus.textContent = 'No hay muestras historicas alrededor de esta alerta.';
    return;
  }

  const labels = series.map((item) => chartLabel(item.periodo, 1));
  const tooltipLabels = series.map((item) => exactChartLabel(item.periodo));
  const temperature = series.map((item) => chartNumber(item.temperatura));
  const humidity = series.map((item) => chartNumber(item.humedad));
  const gas = series.map((item) => chartNumber(item.gas_raw));
  const flame = series.map((item) => Number(item.flama_detectada) === 1 ? 1 : 0);
  const markerIndex = nearestSeriesIndex(series, alert.fecha_hora);
  const [temperatureMin, temperatureMax] = numericExtent(temperature, 0, 50, 2);
  const gasThreshold = numberFrom(payload?.gas_umbral, 1600);
  const gasAxisMax = Math.min(
    4095,
    Math.max(gasThreshold * 1.25, ...gas.filter((value) => value !== null), 100)
  );

  drawChart(elements.incidentEnvironmentChart, {
    labels,
    tooltipLabels,
    markerIndex,
    leftMin: temperatureMin,
    leftMax: temperatureMax,
    rightMin: 0,
    rightMax: 100,
    leftPadding: 62,
    leftFormatter: (value) => `${Number(value.toFixed(1))} °C`,
    rightFormatter: (value) => `${Math.round(value)} %`,
    series: [
      {
        label: 'Temperatura',
        values: temperature,
        color: '#00a3ff',
        axis: 'left',
        tooltipFormatter: (value) => `${Number(value.toFixed(2))} °C`
      },
      {
        label: 'Humedad',
        values: humidity,
        color: '#2ecf7a',
        axis: 'right',
        tooltipFormatter: (value) => `${Number(value.toFixed(2))} %`
      }
    ]
  });

  drawChart(elements.incidentGasChart, {
    labels,
    tooltipLabels,
    markerIndex,
    leftMin: 0,
    leftMax: gasAxisMax,
    rightMin: 0,
    rightMax: 1,
    leftPadding: 72,
    leftFormatter: (value) => `${Math.round(value)} ADC`,
    rightFormatter: () => '',
    series: [
      {
        label: 'Humo/Gas',
        values: gas,
        color: '#ffb000',
        axis: 'left',
        tooltipFormatter: (value) => `${Number(value.toFixed(2))} ADC`
      },
      {
        label: 'Umbral',
        values: series.map(() => gasThreshold),
        color: '#ff453a',
        axis: 'left',
        width: 1,
        dash: [6, 5],
        points: false,
        tooltipFormatter: (value) => `${Math.round(value)} ADC`
      }
    ]
  });

  drawBinaryTimeline(elements.incidentFlameChart, {
    labels,
    tooltipLabels,
    markerIndex,
    values: flame
  });

  elements.incidentStatus.textContent = `${series.length} muestras · `
    + `${windowInfo.minutos_antes ?? 15} min antes y `
    + `${windowInfo.minutos_despues ?? 15} min despues · linea roja: alerta`;
}

async function openIncidentDetail(alertId) {
  if (!Number.isInteger(alertId) || alertId < 1) return;

  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('alerta_id', String(alertId));
  history.replaceState(null, '', currentUrl);
  elements.incidentSummary.innerHTML = '';
  elements.incidentStatus.textContent = 'Cargando incidente...';
  elements.incidentDialog.showModal();
  clearIncidentCharts();

  try {
    const response = await fetch(`${INCIDENT_URL}?alerta_id=${alertId}&minutos=15`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    });
    if (response.status === 401) {
      redirectToLogin();
      return;
    }
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result?.ok === false) {
      throw new Error(result?.error ?? `HTTP ${response.status}`);
    }

    lastIncidentPayload = result.data;
    renderIncident(result.data);
  } catch (error) {
    elements.incidentStatus.textContent = error.message || 'No fue posible cargar el incidente.';
  }
}

function closeIncidentDialog() {
  if (elements.incidentDialog.open) elements.incidentDialog.close();
  hideChartTooltips();
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.delete('alerta_id');
  history.replaceState(null, '', currentUrl);
}

async function loadCharts() {
  if (isChartLoading || document.visibilityState === 'hidden') return;

  isChartLoading = true;
  elements.chartStatus.textContent = 'Actualizando historial...';
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 8000);

  try {
    const rangeHours = numberFrom(elements.chartRange.value, 24);
    currentRangeHours = rangeHours;
    const response = await fetch(`${CHART_API_URL}?horas=${rangeHours}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
      signal: controller.signal
    });
    if (response.status === 401) {
      redirectToLogin();
      return;
    }
    if (!response.ok) throw new Error(`HTTP ${response.status}`);

    const payload = await response.json();
    if (payload?.ok === false) throw new Error(payload.error ?? 'Error de historial');

    const series = Array.isArray(payload?.data?.serie) ? payload.data.serie : [];
    historicalChartSeries = series;
    currentGasThreshold = numberFrom(payload?.data?.gas_umbral, currentGasThreshold);
    const merged = mergedChartSeries();
    if (!merged.length) {
      [
        elements.environmentChart,
        elements.gasChart,
        elements.flameChart,
        elements.connectionChart
      ].forEach((canvas) => {
        prepareCanvas(canvas);
        chartInteractionState.delete(canvas);
        canvas.parentElement.querySelector('.chart-tooltip')?.remove();
      });
      lastChartState = null;
      elements.chartStatus.textContent = 'Todavía no hay muestras registradas en este periodo.';
      return;
    }

    renderCharts(merged, currentGasThreshold, rangeHours);
    const historyMode = payload?.data?.modo_historial === 'solo_alarmas'
      ? 'eventos de alarma'
      : 'puntos históricos';
    elements.chartStatus.textContent = `${merged.length} puntos visibles · ${series.length} ${historyMode} · en vivo cada 2 s`;
    elements.chartLiveIndicator.classList.add('is-active');
  } catch (error) {
    elements.chartStatus.textContent = 'No fue posible consultar el historial.';
  } finally {
    clearTimeout(timeoutId);
    isChartLoading = false;
  }
}

async function loadDashboard() {
  if (isLoading || document.visibilityState === 'hidden') return;

  isLoading = true;
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 8000);
  elements.syncStatus.textContent = 'Actualizando...';
  elements.refreshButton.disabled = true;
  elements.refreshButton.classList.add('is-loading');

  try {
    const response = await fetch(API_URL, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
      signal: controller.signal
    });
    if (response.status === 401) {
      redirectToLogin();
      return;
    }

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const responseBody = await response.json();
    if (
      responseBody?.ok === false ||
      responseBody?.success === false ||
      normalizeText(responseBody?.status) === 'ERROR'
    ) {
      throw new Error(responseBody?.error ?? responseBody?.message ?? 'La API devolvió un error');
    }

    const payload = normalizePayload(responseBody);
    const systemState = pickSystemState(payload.dispositivos, payload.alertas, payload.resumen);

    setSystemStatus(systemState);
    renderMetrics(payload.resumen, payload.dispositivos, payload.alertas);
    renderDevices(payload.dispositivos);
    renderShelly(payload.actuadoresShelly);
    renderRoutines(payload.rutinas, payload.rutinasDisponibles);
    renderAlerts(payload.alertas);
    announceNewAlarm(payload.alertas);
    appendLiveChartSample(payload.dispositivos);

    hasRenderedData = true;
    elements.syncStatus.textContent = 'API en línea';
  } catch (error) {
    setSystemStatus('OFFLINE');
    if (!hasRenderedData) {
      renderDevices([]);
      renderShelly([]);
      renderRoutines([], false);
      renderAlerts([]);
    }
    elements.syncStatus.textContent = 'API no disponible';
  } finally {
    clearTimeout(timeoutId);
    isLoading = false;
    elements.refreshButton.disabled = false;
    elements.refreshButton.classList.remove('is-loading');
  }
}

async function syncShellyLive() {
  if (isShellyLiveSyncing || !csrfToken || document.visibilityState === 'hidden') return;

  isShellyLiveSyncing = true;
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 12000);
  try {
    const response = await fetch(SHELLY_SYNC_LIVE_URL, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: '{}',
      signal: controller.signal
    });
    if (response.status === 401) {
      redirectToLogin();
      return;
    }
    if (response.ok) void loadDashboard();
  } catch {
    // El panel conserva el ultimo estado y reintenta en el siguiente ciclo.
  } finally {
    clearTimeout(timeoutId);
    isShellyLiveSyncing = false;
  }
}

function refreshAll() {
  loadDashboard();
  loadCharts();
}

function redirectToLogin() {
  window.location.replace('../crm/');
}

async function loadSession() {
  try {
    const response = await fetch(SESSION_URL, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    });
    if (!response.ok) {
      redirectToLogin();
      return false;
    }

    const result = await response.json();
    currentUser = result?.data?.usuario ?? null;
    csrfToken = String(result?.data?.csrf_token ?? '');
    if (!currentUser || !csrfToken) {
      redirectToLogin();
      return false;
    }

    elements.sessionUserName.textContent = currentUser.nombre;
    elements.sessionUserRole.textContent = currentUser.rol;
    if (elements.devicesAdminLink) {
      elements.devicesAdminLink.hidden = normalizeText(currentUser.rol) !== 'ADMIN';
    }
    if (elements.usersLink) {
      elements.usersLink.hidden = normalizeText(currentUser.rol) !== 'ADMIN';
    }
    return true;
  } catch (error) {
    redirectToLogin();
    return false;
  }
}

async function logout() {
  elements.logoutButton.disabled = true;
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
    redirectToLogin();
  }
}

function openPasswordDialog() {
  elements.passwordForm.reset();
  elements.passwordMessage.textContent = '';
  elements.passwordDialog.showModal();
  elements.currentPassword.focus();
}

function closePasswordDialog() {
  if (elements.passwordDialog.open) elements.passwordDialog.close();
}

async function changePassword(event) {
  event.preventDefault();
  if (!elements.passwordForm.reportValidity()) return;
  if (elements.newPassword.value !== elements.confirmPassword.value) {
    elements.passwordMessage.textContent = 'La confirmacion no coincide.';
    elements.confirmPassword.focus();
    return;
  }

  elements.passwordSubmit.disabled = true;
  elements.passwordMessage.textContent = 'Actualizando password...';
  try {
    const response = await fetch(PASSWORD_URL, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      cache: 'no-store',
      body: JSON.stringify({
        password_actual: elements.currentPassword.value,
        password_nueva: elements.newPassword.value
      })
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result?.ok === false) {
      throw new Error(result?.error ?? `HTTP ${response.status}`);
    }
    closePasswordDialog();
    redirectToLogin();
  } catch (error) {
    elements.passwordMessage.textContent = error.message || 'No fue posible cambiar el password.';
  } finally {
    elements.passwordSubmit.disabled = false;
  }
}

function openMq2Calibration(button) {
  const health = normalizeText(button.dataset.mq2Health);
  elements.mq2CalibrationForm.reset();
  elements.mq2CalibrationDevice.value = button.dataset.mq2Calibrate;
  elements.mq2CalibrationSummary.textContent =
    `${button.dataset.mq2Calibrate} · lectura actual ${button.dataset.mq2Adc || '--'} ADC`;
  elements.mq2CalibrationMessage.textContent = health === 'CALENTANDO'
    ? 'Espera a que termine el calentamiento antes de registrar la referencia.'
    : '';
  elements.mq2CalibrationSubmit.disabled = health === 'CALENTANDO';
  elements.mq2CalibrationDialog.showModal();
  if (health !== 'CALENTANDO') elements.mq2CalibrationComment.focus();
}

function closeMq2Calibration() {
  if (elements.mq2CalibrationDialog.open) elements.mq2CalibrationDialog.close();
}

async function submitMq2Calibration(event) {
  event.preventDefault();
  if (!elements.mq2CalibrationForm.reportValidity()) return;

  elements.mq2CalibrationSubmit.disabled = true;
  elements.mq2CalibrationMessage.textContent = 'Registrando lectura base...';
  try {
    const response = await fetch(MQ2_CALIBRATION_URL, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      credentials: 'same-origin',
      cache: 'no-store',
      body: JSON.stringify({
        dispositivo_id: elements.mq2CalibrationDevice.value,
        comentario: elements.mq2CalibrationComment.value.trim()
      })
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result?.ok === false) {
      throw new Error(result?.error ?? `HTTP ${response.status}`);
    }
    closeMq2Calibration();
    await loadDashboard();
  } catch (error) {
    elements.mq2CalibrationMessage.textContent =
      error.message || 'No fue posible registrar la calibracion.';
  } finally {
    if (elements.mq2CalibrationDialog.open) {
      elements.mq2CalibrationSubmit.disabled = false;
    }
  }
}

async function silencePhysicalAlarm(button) {
  const dispositivoId = button.dataset.alarmSilence;
  if (!dispositivoId) return;

  const confirmado = window.confirm(
    `Silenciar el buzzer de ${dispositivoId}? La alarma seguira enclavada y requerira revision fisica.`
  );
  if (!confirmado) return;

  const textoAnterior = button.textContent;
  button.disabled = true;
  button.textContent = 'Enviando orden...';
  elements.syncStatus.textContent = 'Solicitando silencio...';

  try {
    const response = await fetch(ALARM_SILENCE_URL, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      cache: 'no-store',
      body: JSON.stringify({ dispositivo_id: dispositivoId })
    });
    if (response.status === 401) {
      redirectToLogin();
      return;
    }
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result?.ok === false) {
      throw new Error(result?.error ?? `HTTP ${response.status}`);
    }

    elements.syncStatus.textContent = 'Orden de silencio enviada';
    window.setTimeout(() => {
      void loadDashboard();
    }, 2500);
  } catch (error) {
    elements.syncStatus.textContent = error.message || 'No fue posible silenciar el buzzer';
    button.disabled = false;
    button.textContent = textoAnterior;
  }
}

let chartResizeTimer = null;
window.addEventListener('resize', () => {
  clearTimeout(chartResizeTimer);
  chartResizeTimer = setTimeout(() => {
    if (lastChartState) {
      renderCharts(
        lastChartState.series,
        lastChartState.gasThreshold,
        lastChartState.rangeHours
      );
    }
    if (elements.incidentDialog.open && lastIncidentPayload) {
      renderIncident(lastIncidentPayload);
    }
  }, 150);
});

elements.refreshButton.addEventListener('click', refreshAll);
elements.passwordButton.addEventListener('click', openPasswordDialog);
elements.logoutButton.addEventListener('click', logout);
elements.chartRange.addEventListener('change', loadCharts);
elements.alertsTable.addEventListener('click', (event) => {
  const button = event.target.closest('[data-alert-action]');
  if (button) {
    openAlertDialog(button);
    return;
  }
  const row = event.target.closest('[data-alert-id]');
  if (row) openIncidentDetail(Number(row.dataset.alertId));
});
elements.alertsTable.addEventListener('keydown', (event) => {
  if (!['Enter', ' '].includes(event.key) || event.target.closest('button')) return;
  const row = event.target.closest('[data-alert-id]');
  if (!row) return;
  event.preventDefault();
  openIncidentDetail(Number(row.dataset.alertId));
});
elements.alertActionForm.addEventListener('submit', submitAlertAction);
elements.alertDialogClose.addEventListener('click', closeAlertDialog);
elements.alertDialogCancel.addEventListener('click', closeAlertDialog);
elements.alertDialog.addEventListener('click', (event) => {
  if (event.target === elements.alertDialog) closeAlertDialog();
});
elements.passwordForm.addEventListener('submit', changePassword);
elements.passwordDialogClose.addEventListener('click', closePasswordDialog);
elements.passwordDialogCancel.addEventListener('click', closePasswordDialog);
elements.passwordDialog.addEventListener('click', (event) => {
  if (event.target === elements.passwordDialog) closePasswordDialog();
});
elements.deviceGrid.addEventListener('click', (event) => {
  const silenceButton = event.target.closest('[data-alarm-silence]');
  if (silenceButton) {
    void silencePhysicalAlarm(silenceButton);
    return;
  }
  const calibrationButton = event.target.closest('[data-mq2-calibrate]');
  if (calibrationButton) openMq2Calibration(calibrationButton);
});
elements.shellyGrid?.addEventListener('click', (event) => {
  const button = event.target.closest('[data-shelly-action]');
  if (button) void controlShelly(button);
});
elements.mq2CalibrationForm.addEventListener('submit', submitMq2Calibration);
elements.mq2CalibrationClose.addEventListener('click', closeMq2Calibration);
elements.mq2CalibrationCancel.addEventListener('click', closeMq2Calibration);
elements.mq2CalibrationDialog.addEventListener('click', (event) => {
  if (event.target === elements.mq2CalibrationDialog) closeMq2Calibration();
});
elements.incidentDialogClose.addEventListener('click', closeIncidentDialog);
elements.incidentDialog.addEventListener('click', (event) => {
  if (event.target === elements.incidentDialog) closeIncidentDialog();
});
document.addEventListener('click', (event) => {
  if (!event.target.closest('.chart-canvas')) hideChartTooltips();
});
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') refreshAll();
});

async function initializeDashboard() {
  if (!await loadSession()) return;
  refreshAll();
  void syncShellyLive();
  const requestedAlertId = Number(new URLSearchParams(location.search).get('alerta_id'));
  if (Number.isInteger(requestedAlertId) && requestedAlertId > 0) {
    openIncidentDetail(requestedAlertId);
  }
  setInterval(loadDashboard, REFRESH_MS);
  setInterval(loadCharts, CHART_REFRESH_MS);
  setInterval(() => void syncShellyLive(), SHELLY_SYNC_LIVE_MS);
}

initializeDashboard();
