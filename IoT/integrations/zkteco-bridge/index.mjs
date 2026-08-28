import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const ZKLib = require('node-zklib');
const configPath = path.resolve(process.argv[2] || 'config.json');
const config = JSON.parse(await fs.readFile(configPath, 'utf8'));

if (!/^https:\/\//i.test(config.apiBaseUrl || '')) throw new Error('apiBaseUrl debe usar HTTPS');
if (String(config.bridgeToken || '').length < 32) throw new Error('bridgeToken debe tener al menos 32 caracteres');
if (!Array.isArray(config.devices) || !config.devices.length) throw new Error('Agrega por lo menos un equipo en devices');

const pollMs = Math.max(15, Number(config.pollSeconds) || 30) * 1000;
const attendanceMs = Math.max(30, Number(config.attendancePollSeconds) || 60) * 1000;
const initialLookbackMs = Math.max(0, Number(config.initialLookbackMinutes) || 10) * 60000;
const maxEvents = Math.min(25, Math.max(1, Number(config.maxEventsPerPoll) || 25));
const deviceState = new Map();

function textValue(value, fallback = null) {
  if (value === undefined || value === null || value === '') return fallback;
  return String(value);
}

function numberValue(value) {
  const number = Number(value);
  return Number.isFinite(number) && number >= 0 ? Math.trunc(number) : null;
}

function pick(object, names) {
  for (const name of names) {
    if (object?.[name] !== undefined && object[name] !== null && object[name] !== '') return object[name];
  }
  return null;
}

function attendanceRows(result) {
  if (Array.isArray(result)) return result;
  if (Array.isArray(result?.data)) return result.data;
  return [];
}

function eventDate(row) {
  const raw = pick(row, ['timestamp', 'recordTime', 'time', 'date']);
  if (raw instanceof Date && !Number.isNaN(raw.getTime())) return raw;
  const parsed = raw ? new Date(raw) : null;
  return parsed && !Number.isNaN(parsed.getTime()) ? parsed : null;
}

function eventKey(deviceId, row) {
  const identity = [
    deviceId,
    pick(row, ['record_id', 'recordId', 'uid', 'id']),
    pick(row, ['user_id', 'userId', 'deviceUserId', 'pin']),
    eventDate(row)?.toISOString(),
    pick(row, ['type', 'verifyMode', 'status']),
  ].join('|');
  return crypto.createHash('sha256').update(identity).digest('hex');
}

function mapAttendance(deviceId, row) {
  const occurred = eventDate(row);
  return {
    pin_usuario: textValue(pick(row, ['user_id', 'userId', 'deviceUserId', 'pin'])),
    tipo_evento: 'MARCAJE',
    modo_verificacion: textValue(pick(row, ['type', 'verifyMode', 'verificationMode'])),
    estado_entrada: textValue(pick(row, ['status', 'inOutMode', 'state'])),
    ocurrido_en: occurred?.toISOString() || null,
    dedupe_key: eventKey(deviceId, row),
    detalle: {
      record_id: textValue(pick(row, ['record_id', 'recordId', 'uid', 'id'])),
      work_code: textValue(pick(row, ['workCode', 'work_code'])),
    },
  };
}

async function sendToApi(device, payload) {
  const response = await fetch(`${String(config.apiBaseUrl).replace(/\/$/, '')}/zkteco_ingest.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-ZKTECO-BRIDGE-TOKEN': config.bridgeToken,
    },
    body: JSON.stringify({ equipo_id: device.id, fuente: 'CONECTOR_LOCAL', ...payload }),
    signal: AbortSignal.timeout(15000),
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || result.ok === false) throw new Error(result.error || `cPanel respondio HTTP ${response.status}`);
  return result;
}

async function optionalCall(instance, method) {
  if (typeof instance[method] !== 'function') return null;
  try {
    return await instance[method]();
  } catch {
    return null;
  }
}

async function pollDevice(device, index) {
  if (String(device.protocol || 'PULL_4370').toUpperCase() !== 'PULL_4370') return;
  const state = deviceState.get(device.id) || { initialized: false, seen: new Set(), lastAttendanceAt: 0 };
  deviceState.set(device.id, state);
  const instance = new ZKLib(
    device.ip,
    Number(device.port) || 4370,
    Number(device.timeoutMs) || 10000,
    Number(device.inport) || (5200 + index)
  );

  try {
    await instance.createSocket();
    const info = await instance.getInfo();
    const now = Date.now();
    let events = [];
    if (now - state.lastAttendanceAt >= attendanceMs) {
      const rows = attendanceRows(await instance.getAttendances());
      const cutoff = now - initialLookbackMs;
      const mapped = rows.map((row) => ({ row, key: eventKey(device.id, row), date: eventDate(row) }));
      if (!state.initialized) {
        for (const item of mapped) state.seen.add(item.key);
        events = mapped
          .filter((item) => item.date && item.date.getTime() >= cutoff)
          .slice(-maxEvents)
          .map((item) => mapAttendance(device.id, item.row));
        state.initialized = true;
      } else {
        events = mapped
          .filter((item) => !state.seen.has(item.key))
          .slice(-maxEvents)
          .map((item) => mapAttendance(device.id, item.row));
        for (const item of mapped) state.seen.add(item.key);
      }
      if (state.seen.size > 10000) state.seen = new Set(mapped.slice(-2000).map((item) => item.key));
      state.lastAttendanceAt = now;
    }

    const [deviceName, firmware, platform] = await Promise.all([
      optionalCall(instance, 'getDeviceName'),
      optionalCall(instance, 'getDeviceVersion'),
      optionalCall(instance, 'getPlatform'),
    ]);
    await sendToApi(device, {
      online: true,
      estado: {
        nombre: textValue(deviceName),
        modelo: textValue(device.model),
        serial: textValue(device.serial),
        firmware: textValue(firmware),
        plataforma: textValue(platform),
        usuarios_total: numberValue(pick(info, ['userCounts', 'users', 'userCount'])),
        registros_total: numberValue(pick(info, ['logCounts', 'logs', 'logCount'])),
        capacidad_usuarios: numberValue(pick(info, ['userCapacity', 'usersCap'])),
        capacidad_registros: numberValue(pick(info, ['logCapacity', 'logsCap'])),
      },
      eventos: events,
      error: null,
    });
    console.log(`[${new Date().toISOString()}] ${device.id}: ONLINE, ${events.length} eventos nuevos`);
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    try {
      await sendToApi(device, { online: false, estado: {}, eventos: [], error: message });
    } catch (reportError) {
      console.error(`[${new Date().toISOString()}] ${device.id}: no se pudo reportar a cPanel`, reportError);
      return;
    }
    console.warn(`[${new Date().toISOString()}] ${device.id}: OFFLINE - ${message}`);
  } finally {
    try {
      await instance.disconnect();
    } catch {
      // El socket puede no haberse abierto.
    }
  }
}

async function pollAll() {
  await Promise.allSettled(config.devices.map((device, index) => pollDevice(device, index)));
}

await pollAll();
setInterval(() => void pollAll(), pollMs);
