#!/usr/bin/env node
'use strict';

/**
 * Simulador de carga para ID Industrial.
 *
 * Seguridad:
 * - No envia solicitudes salvo que se indique --execute.
 * - Lee el token desde IDIND_API_TOKEN; nunca lo guarda ni lo imprime.
 * - Solo genera lecturas normales, sin alarmas, flama ni gas peligroso.
 *
 * Ejemplo PowerShell:
 *   $env:IDIND_API_TOKEN = 'TOKEN_DE_32_CARACTERES_O_MAS'
 *   node tools\simular-dispositivos.js --execute --devices=50 --duration-minutes=10
 */

const DEFAULTS = Object.freeze({
  baseUrl: 'https://idactivos.digital/ID-Industrial/api',
  devices: 50,
  prefix: 'SIM_',
  durationMinutes: 10,
  telemetryMs: 10000,
  commandsMs: 2000,
  rampSeconds: 60,
  timeoutMs: 8000,
  reportSeconds: 10
});

function printHelp() {
  process.stdout.write(`
Simulador de dispositivos ID Industrial

Uso:
  node tools\\simular-dispositivos.js [opciones]

Opciones:
  --execute                  Envia trafico real. Sin esta opcion solo calcula.
  --base-url=URL             API base (default: ${DEFAULTS.baseUrl})
  --devices=N                Cantidad de dispositivos, 1-500 (default: 50)
  --prefix=TEXTO             Prefijo de IDs (default: SIM_)
  --duration-minutes=N       Duracion total (default: 10)
  --telemetry-ms=N           Intervalo de lecturas (default: 10000)
  --commands-ms=N            Intervalo de comandos (default: 2000)
  --ramp-seconds=N           Tiempo para incorporar todos los equipos (default: 60)
  --timeout-ms=N             Timeout por solicitud (default: 8000)
  --report-seconds=N         Intervalo del resumen en consola (default: 10)
  --help                     Muestra esta ayuda

El token se obtiene exclusivamente de la variable IDIND_API_TOKEN.
Antes de ejecutar, importa database/simulacion_50_dispositivos_offline.sql.
`);
}

function parseNumber(name, value, minimum, maximum) {
  const parsed = Number(value);
  if (!Number.isFinite(parsed) || parsed < minimum || parsed > maximum) {
    throw new Error(`${name} debe estar entre ${minimum} y ${maximum}`);
  }
  return parsed;
}

function parseArgs(argv) {
  const config = { ...DEFAULTS, execute: false, help: false };
  const mapping = {
    'base-url': 'baseUrl',
    devices: 'devices',
    prefix: 'prefix',
    'duration-minutes': 'durationMinutes',
    'telemetry-ms': 'telemetryMs',
    'commands-ms': 'commandsMs',
    'ramp-seconds': 'rampSeconds',
    'timeout-ms': 'timeoutMs',
    'report-seconds': 'reportSeconds'
  };

  for (const argument of argv) {
    if (argument === '--execute') {
      config.execute = true;
      continue;
    }
    if (argument === '--help' || argument === '-h') {
      config.help = true;
      continue;
    }
    if (!argument.startsWith('--') || !argument.includes('=')) {
      throw new Error(`Opcion no reconocida: ${argument}`);
    }

    const separator = argument.indexOf('=');
    const key = argument.slice(2, separator);
    const value = argument.slice(separator + 1);
    const property = mapping[key];
    if (!property) {
      throw new Error(`Opcion no reconocida: --${key}`);
    }
    config[property] = value;
  }

  config.devices = Math.trunc(parseNumber('devices', config.devices, 1, 500));
  config.durationMinutes = parseNumber(
    'duration-minutes',
    config.durationMinutes,
    0.1,
    1440
  );
  config.telemetryMs = Math.trunc(
    parseNumber('telemetry-ms', config.telemetryMs, 1000, 3600000)
  );
  config.commandsMs = Math.trunc(
    parseNumber('commands-ms', config.commandsMs, 500, 3600000)
  );
  config.rampSeconds = parseNumber('ramp-seconds', config.rampSeconds, 0, 3600);
  config.timeoutMs = Math.trunc(
    parseNumber('timeout-ms', config.timeoutMs, 500, 60000)
  );
  config.reportSeconds = parseNumber(
    'report-seconds',
    config.reportSeconds,
    1,
    3600
  );
  config.baseUrl = String(config.baseUrl).trim().replace(/\/+$/, '');
  config.prefix = String(config.prefix).trim();

  if (!/^[A-Za-z0-9_-]+$/.test(config.prefix)) {
    throw new Error('prefix solo puede contener letras, numeros, guion y guion bajo');
  }
  if (config.prefix.length + 3 > 64) {
    throw new Error('prefix es demasiado largo para dispositivo_id');
  }

  let parsedUrl;
  try {
    parsedUrl = new URL(config.baseUrl);
  } catch {
    throw new Error('base-url no es una URL valida');
  }
  const isLocal = ['localhost', '127.0.0.1', '::1'].includes(parsedUrl.hostname);
  if (parsedUrl.protocol !== 'https:' && !isLocal) {
    throw new Error('base-url debe usar HTTPS fuera de localhost');
  }

  return config;
}

function deviceId(prefix, index) {
  return `${prefix}${String(index).padStart(3, '0')}`;
}

function createMetric() {
  return {
    sent: 0,
    ok: 0,
    errors: 0,
    totalMs: 0,
    maxMs: 0,
    statusCodes: Object.create(null)
  };
}

const metrics = {
  telemetry: createMetric(),
  commands: createMetric()
};

function recordMetric(metric, result) {
  metric.sent++;
  metric.totalMs += result.elapsedMs;
  metric.maxMs = Math.max(metric.maxMs, result.elapsedMs);
  const statusKey = result.status > 0 ? String(result.status) : 'NETWORK';
  metric.statusCodes[statusKey] = (metric.statusCodes[statusKey] || 0) + 1;
  if (result.ok) {
    metric.ok++;
  } else {
    metric.errors++;
  }
}

function metricSummary(metric) {
  const average = metric.sent ? metric.totalMs / metric.sent : 0;
  return {
    enviadas: metric.sent,
    correctas: metric.ok,
    errores: metric.errors,
    promedio_ms: Number(average.toFixed(1)),
    maximo_ms: Number(metric.maxMs.toFixed(1)),
    codigos: metric.statusCodes
  };
}

function normalTelemetry(id, index, startedAt) {
  const elapsedSeconds = Math.max(0, (Date.now() - startedAt) / 1000);
  const phase = elapsedSeconds / 20 + index * 0.37;
  const temperature = 24.5 + Math.sin(phase) * 1.5;
  const humidity = 48 + Math.cos(phase * 0.7) * 4;
  const gasRaw = Math.round(120 + (Math.sin(phase * 1.3) + 1) * 45 + (index % 7));

  return {
    dispositivo_id: id,
    temperatura: Number(temperature.toFixed(1)),
    humedad: Number(humidity.toFixed(1)),
    indice_calor: Number((temperature + 0.2).toFixed(1)),
    gas_raw: gasRaw,
    gas_porcentaje: Number(((gasRaw / 4095) * 100).toFixed(2)),
    gas_umbral: 1600,
    gas_detectado: 0,
    flama_detectada: 0,
    temperatura_alerta: 30,
    temperatura_alarma: 35,
    estado_general: 'NORMAL',
    tipo_alerta: '',
    peligro_activo: 0,
    alarma_enclavada: 0,
    alarma_silenciada: 0,
    revision_fisica_pendiente: 0,
    buzzer_encendido: 0,
    modo_operacion: 'NORMAL',
    silenciada_por: 'NINGUNO',
    salud_dht: 'OK',
    salud_mq2: 'OK',
    salud_flama: 'OK',
    wifi_rssi: -45 - (index % 25),
    tiempo_encendido: Math.floor(elapsedSeconds + index * 60),
    mq2_calentamiento_total_s: 120,
    contador_alarmas: 0,
    contador_silencios_en_linea: 0,
    contador_silencios_fisicos: 0,
    contador_resets_fisicos: 0
  };
}

async function postJson(url, token, payload, timeoutMs) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
  const started = performance.now();

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-API-TOKEN': token
      },
      body: JSON.stringify(payload),
      signal: controller.signal
    });
    const body = await response.json().catch(() => null);
    return {
      ok: response.ok && body?.ok !== false,
      status: response.status,
      body,
      elapsedMs: performance.now() - started,
      error: response.ok ? null : body?.error || `HTTP ${response.status}`
    };
  } catch (error) {
    return {
      ok: false,
      status: 0,
      body: null,
      elapsedMs: performance.now() - started,
      error: error?.name === 'AbortError' ? 'Timeout' : String(error?.message || error)
    };
  } finally {
    clearTimeout(timeout);
  }
}

function sleep(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

function waitUntil(milliseconds, stopSignal) {
  return Promise.race([
    sleep(milliseconds),
    stopSignal.promise
  ]);
}

function createStopSignal(durationMs) {
  let resolveStop;
  const signal = {
    stopped: false,
    reason: '',
    promise: new Promise((resolve) => {
      resolveStop = resolve;
    }),
    stop(reason) {
      if (signal.stopped) return;
      signal.stopped = true;
      signal.reason = reason;
      resolveStop();
    }
  };

  const timer = setTimeout(() => signal.stop('duracion completada'), durationMs);
  signal.promise.finally(() => clearTimeout(timer));
  return signal;
}

async function telemetryLoop(device, context) {
  await waitUntil(device.startDelayMs, context.stopSignal);
  while (!context.stopSignal.stopped) {
    const cycleStarted = Date.now();
    const result = await postJson(
      `${context.config.baseUrl}/guardar_lectura.php`,
      context.token,
      normalTelemetry(device.id, device.index, context.startedAt),
      context.config.timeoutMs
    );
    recordMetric(metrics.telemetry, result);
    if (!result.ok && device.telemetryErrors++ < 2) {
      process.stderr.write(
        `[${device.id}] lectura rechazada: ${result.error || 'error desconocido'}\n`
      );
    }
    const remaining = Math.max(
      0,
      context.config.telemetryMs - (Date.now() - cycleStarted)
    );
    await waitUntil(remaining, context.stopSignal);
  }
}

async function commandLoop(device, context) {
  await waitUntil(device.startDelayMs, context.stopSignal);
  while (!context.stopSignal.stopped) {
    const cycleStarted = Date.now();
    const result = await postJson(
      `${context.config.baseUrl}/comando_dispositivo.php`,
      context.token,
      {
        dispositivo_id: device.id,
        comando_aplicado_id: device.pendingCommandId
      },
      context.config.timeoutMs
    );
    recordMetric(metrics.commands, result);

    if (result.ok) {
      const command = result.body?.data?.comando;
      if (command?.id) {
        device.pendingCommandId = Number(command.id);
        process.stdout.write(
          `[${device.id}] comando simulado recibido: ${command.accion} #${command.id}\n`
        );
      } else if (device.pendingCommandId > 0) {
        device.pendingCommandId = 0;
      }
    } else if (device.commandErrors++ < 2) {
      process.stderr.write(
        `[${device.id}] consulta rechazada: ${result.error || 'error desconocido'}\n`
      );
    }

    const remaining = Math.max(
      0,
      context.config.commandsMs - (Date.now() - cycleStarted)
    );
    await waitUntil(remaining, context.stopSignal);
  }
}

function printReport(startedAt, devices) {
  const elapsedSeconds = (Date.now() - startedAt) / 1000;
  const activeDevices = devices.filter((device) => device.startDelayMs <= elapsedSeconds * 1000)
    .length;
  process.stdout.write(
    JSON.stringify({
      transcurrido_s: Number(elapsedSeconds.toFixed(1)),
      dispositivos_iniciados: activeDevices,
      telemetria: metricSummary(metrics.telemetry),
      comandos: metricSummary(metrics.commands)
    }) + '\n'
  );
}

async function main() {
  let config;
  try {
    config = parseArgs(process.argv.slice(2));
  } catch (error) {
    process.stderr.write(`Error: ${error.message}\n`);
    process.stderr.write('Usa --help para ver las opciones.\n');
    process.exitCode = 1;
    return;
  }

  if (config.help) {
    printHelp();
    return;
  }

  const durationSeconds = config.durationMinutes * 60;
  const estimatedTelemetry = Math.ceil(
    config.devices * durationSeconds * (1000 / config.telemetryMs)
  );
  const estimatedCommands = Math.ceil(
    config.devices * durationSeconds * (1000 / config.commandsMs)
  );

  process.stdout.write(
    JSON.stringify({
      modo: config.execute ? 'EJECUCION' : 'CALCULO_SEGURO',
      api: config.baseUrl,
      dispositivos: config.devices,
      ids: `${deviceId(config.prefix, 1)} a ${deviceId(config.prefix, config.devices)}`,
      duracion_minutos: config.durationMinutes,
      incorporacion_gradual_segundos: config.rampSeconds,
      solicitudes_estimadas: {
        telemetria: estimatedTelemetry,
        comandos: estimatedCommands,
        total: estimatedTelemetry + estimatedCommands
      }
    }, null, 2) + '\n'
  );

  if (!config.execute) {
    process.stdout.write(
      '\nModo seguro: no se envio ninguna solicitud. Agrega --execute para iniciar la prueba.\n'
    );
    return;
  }

  const token = String(process.env.IDIND_API_TOKEN || '');
  if (token.length < 32) {
    process.stderr.write(
      'Error: define IDIND_API_TOKEN con el token real de al menos 32 caracteres.\n'
    );
    process.exitCode = 1;
    return;
  }

  const startedAt = Date.now();
  const stopSignal = createStopSignal(config.durationMinutes * 60 * 1000);
  const devices = Array.from({ length: config.devices }, (_, offset) => {
    const index = offset + 1;
    const fraction = config.devices === 1 ? 0 : offset / (config.devices - 1);
    return {
      id: deviceId(config.prefix, index),
      index,
      startDelayMs: Math.round(fraction * config.rampSeconds * 1000),
      pendingCommandId: 0,
      telemetryErrors: 0,
      commandErrors: 0
    };
  });

  const stopFromSignal = (signalName) => {
    process.stdout.write(`\n${signalName} recibido; deteniendo la prueba...\n`);
    stopSignal.stop(signalName);
  };
  process.once('SIGINT', () => stopFromSignal('SIGINT'));
  process.once('SIGTERM', () => stopFromSignal('SIGTERM'));

  process.stdout.write(
    'Prueba iniciada. Presiona Ctrl+C para detenerla de forma ordenada.\n'
  );

  const context = { config, token, startedAt, stopSignal };
  const loops = devices.flatMap((device) => [
    telemetryLoop(device, context),
    commandLoop(device, context)
  ]);
  const reportTimer = setInterval(
    () => printReport(startedAt, devices),
    config.reportSeconds * 1000
  );

  await stopSignal.promise;
  clearInterval(reportTimer);
  await Promise.allSettled(loops);

  printReport(startedAt, devices);
  process.stdout.write(
    `Prueba terminada: ${stopSignal.reason}. Los dispositivos pasaran a OFFLINE aproximadamente dos minutos despues del ultimo envio.\n`
  );
}

main().catch((error) => {
  process.stderr.write(`Fallo no controlado: ${error?.stack || error}\n`);
  process.exitCode = 1;
});
