import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import DigestFetch from 'digest-fetch';
import { XMLParser } from 'fast-xml-parser';

const configPath = path.resolve(process.argv[2] || 'config.json');
const config = JSON.parse(await fs.readFile(configPath, 'utf8'));
const parser = new XMLParser({ ignoreAttributes: false, removeNSPrefix: true, trimValues: true });

if (!/^https:\/\//i.test(config.apiBaseUrl || '')) throw new Error('apiBaseUrl debe usar HTTPS');
if (String(config.bridgeToken || '').length < 32) throw new Error('bridgeToken debe tener al menos 32 caracteres');
if (!Array.isArray(config.devices) || !config.devices.length) throw new Error('Agrega por lo menos un equipo en devices');

const intervalMs = Math.max(15, Number(config.pollSeconds) || 30) * 1000;
const cachedInfo = new Map();

function firstValue(object, names) {
  if (!object || typeof object !== 'object') return null;
  for (const name of names) {
    if (object[name] !== undefined && object[name] !== null && object[name] !== '') return object[name];
  }
  for (const value of Object.values(object)) {
    if (value && typeof value === 'object') {
      const found = firstValue(value, names);
      if (found !== null) return found;
    }
  }
  return null;
}

async function isapiXml(device, endpoint) {
  const client = new DigestFetch(device.username, device.password);
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);
  try {
    const response = await client.fetch(`${String(device.baseUrl).replace(/\/$/, '')}${endpoint}`, {
      headers: { Accept: 'application/xml' }, signal: controller.signal,
    });
    if (!response.ok) throw new Error(`ISAPI ${endpoint}: HTTP ${response.status}`);
    return parser.parse(await response.text());
  } finally {
    clearTimeout(timeout);
  }
}

async function sendHeartbeat(device, payload) {
  const response = await fetch(`${String(config.apiBaseUrl).replace(/\/$/, '')}/hikvision_ingest.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json', 'Content-Type': 'application/json',
      'X-HIKVISION-BRIDGE-TOKEN': config.bridgeToken,
    },
    body: JSON.stringify({ equipo_id: device.id, ...payload }),
    signal: AbortSignal.timeout(12000),
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || result.ok === false) throw new Error(result.error || `cPanel respondio HTTP ${response.status}`);
}

async function pollDevice(device) {
  try {
    let info = cachedInfo.get(device.id);
    if (!info || Date.now() - info.loadedAt > 3600000) {
      info = { raw: await isapiXml(device, '/ISAPI/System/deviceInfo'), loadedAt: Date.now() };
      cachedInfo.set(device.id, info);
    }
    let rawStatus = {};
    let statusWarning = null;
    try {
      rawStatus = await isapiXml(device, '/ISAPI/System/status');
    } catch (error) {
      statusWarning = error instanceof Error ? error.message : String(error);
    }
    await sendHeartbeat(device, {
      online: true,
      estado: {
        nombre: firstValue(info.raw, ['deviceName', 'name']),
        modelo: firstValue(info.raw, ['model']),
        serial: firstValue(info.raw, ['serialNumber', 'serialNo']),
        firmware: firstValue(info.raw, ['firmwareVersion']),
        mac: firstValue(info.raw, ['macAddress', 'mac']),
        uptime_s: Number(firstValue(rawStatus, ['deviceUpTime', 'upTime', 'uptime'])) || null,
      },
      error: statusWarning,
    });
    console.log(`[${new Date().toISOString()}] ${device.id}: ONLINE`);
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    try {
      await sendHeartbeat(device, { online: false, estado: {}, error: message });
    } catch (reportError) {
      console.error(`[${new Date().toISOString()}] ${device.id}: no se pudo reportar a cPanel`, reportError);
      return;
    }
    console.warn(`[${new Date().toISOString()}] ${device.id}: OFFLINE - ${message}`);
  }
}

async function pollAll() { await Promise.allSettled(config.devices.map(pollDevice)); }

function eventSeverity(type) {
  const normalized = String(type || '').toLowerCase();
  if (/(fire|smoke|panic|tamper|forced|denied|alarm)/.test(normalized)) return 'CRITICO';
  if (/(motion|linecross|region|videoloss|door)/.test(normalized)) return 'PRECAUCION';
  return 'INFO';
}

async function listenDeviceEvents(device) {
  while (device.listenEvents !== false) {
    try {
      const client = new DigestFetch(device.username, device.password);
      const response = await client.fetch(`${String(device.baseUrl).replace(/\/$/, '')}/ISAPI/Event/notification/alertStream`, {
        headers: { Accept: 'multipart/mixed, application/xml' },
      });
      if (!response.ok || !response.body) throw new Error(`alertStream: HTTP ${response.status}`);
      console.log(`[${new Date().toISOString()}] ${device.id}: escuchando eventos ISAPI`);
      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      while (true) {
        const { done, value } = await reader.read();
        if (done) throw new Error('alertStream finalizo');
        buffer += decoder.decode(value, { stream: true });
        let start = buffer.indexOf('<?xml');
        let end = buffer.indexOf('</EventNotificationAlert>');
        while (start >= 0 && end > start) {
          const xml = buffer.slice(start, end + '</EventNotificationAlert>'.length);
          buffer = buffer.slice(end + '</EventNotificationAlert>'.length);
          const parsed = parser.parse(xml);
          const type = String(firstValue(parsed, ['eventType']) || 'EVENTO_ISAPI');
          const state = String(firstValue(parsed, ['eventState']) || 'active');
          if (!(type.toLowerCase() === 'videoloss' && state.toLowerCase() === 'inactive')) {
            const occurred = firstValue(parsed, ['dateTime', 'time']);
            await sendHeartbeat(device, {
              online: true,
              eventos: [{
                tipo: type.toUpperCase(), severidad: eventSeverity(type), codigo: state,
                descripcion: `Evento Hikvision ${type} (${state})`, ocurrido_en: occurred,
                detalle: {
                  eventType: type,
                  eventState: state,
                  channelId: firstValue(parsed, ['channelID', 'dynChannelID']),
                  deviceId: firstValue(parsed, ['deviceID', 'serialNumber']),
                  ipAddress: firstValue(parsed, ['ipAddress']),
                },
              }],
            });
          }
          start = buffer.indexOf('<?xml');
          end = buffer.indexOf('</EventNotificationAlert>');
        }
        if (buffer.length > 1048576) buffer = buffer.slice(-262144);
      }
    } catch (error) {
      console.warn(`[${new Date().toISOString()}] ${device.id}: eventos ISAPI en espera`, error instanceof Error ? error.message : error);
      await new Promise((resolve) => setTimeout(resolve, 5000));
    }
  }
}

await pollAll();
for (const device of config.devices) void listenDeviceEvents(device);
setInterval(() => void pollAll(), intervalMs);
