import { Platform } from 'react-native';

import type {
  ApiEnvelope,
  MobileAlertFilters,
  MobileAlertAction,
  MobileAlarmCommand,
  MobileAlertsPage,
  MobileDevicesResponse,
  MobileIncident,
  MobileLiveHistory,
  MobilePushRegistration,
  MobilePushStatus,
  MobileQuoteDetail,
  MobileQuotesPage,
  MobileSummary,
  MobileShellyCommand,
  MobileShellyActuator,
  MobileShellyDetail,
  MobileShellyDetection,
  MobileShellySaveInput,
  MobileRoutineDetail,
  MobileRoutineRunResult,
  MobileRoutineSaveInput,
  MobileRoutinesResponse,
  MobileUser,
  SessionResponse,
} from '@/types/api';

const configuredBaseUrl = process.env.EXPO_PUBLIC_API_BASE_URL?.trim() ?? '';
export const apiBaseUrl = configuredBaseUrl.replace(/\/+$/, '');

export class ApiError extends Error {
  status: number;

  constructor(message: string, status = 0) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

type RequestOptions = {
  method?: 'GET' | 'POST';
  token?: string | null;
  body?: Record<string, unknown>;
};

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  if (!apiBaseUrl) {
    throw new ApiError(
      'Configura EXPO_PUBLIC_API_BASE_URL antes de conectar la aplicacion.',
    );
  }

  const response = await fetch(`${apiBaseUrl}/${path.replace(/^\/+/, '')}`, {
    method: options.method ?? 'GET',
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  const payload = (await response.json().catch(() => null)) as ApiEnvelope<T> | null;
  if (!response.ok || !payload?.ok) {
    throw new ApiError(
      payload?.error ?? 'No fue posible comunicarse con el servidor.',
      response.status,
    );
  }
  return payload.data;
}

export function login(email: string, password: string): Promise<SessionResponse> {
  return request<SessionResponse>('mobile/auth/login.php', {
    method: 'POST',
    body: {
      email,
      password,
      dispositivo: `ID Industrial ${Platform.OS}`,
    },
  });
}

export async function getCurrentUser(token: string): Promise<MobileUser> {
  const data = await request<{
    usuario: MobileUser;
    sesion: { tipo: 'Bearer'; expira_en: string };
  }>('mobile/auth/me.php', { token });
  return data.usuario;
}

export function logout(token: string): Promise<{ sesion_cerrada: boolean }> {
  return request<{ sesion_cerrada: boolean }>('mobile/auth/logout.php', {
    method: 'POST',
    token,
  });
}

export function getMobileSummary(token: string): Promise<MobileSummary> {
  return request<MobileSummary>('mobile/resumen.php', { token });
}

export function getMobileAlerts(
  token: string,
  filters: MobileAlertFilters = {},
): Promise<MobileAlertsPage> {
  const query = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      query.set(key, String(value));
    }
  });
  const suffix = query.size ? `?${query.toString()}` : '';
  return request<MobileAlertsPage>(`mobile/alertas.php${suffix}`, { token });
}

export function getMobileDevices(
  token: string,
  syncShelly = false,
): Promise<MobileDevicesResponse> {
  const suffix = syncShelly ? '?sincronizar_shelly=1' : '';
  return request<MobileDevicesResponse>(`mobile/dispositivos.php${suffix}`, { token });
}

export function controlMobileShelly(
  token: string,
  actuatorId: string,
  action: 'ENCENDER' | 'APAGAR',
): Promise<MobileShellyCommand> {
  return request<MobileShellyCommand>('mobile/shelly_comando.php', {
    method: 'POST',
    token,
    body: { actuador_id: actuatorId, accion: action, confirmado: true },
  });
}

export function getMobileShellyDetail(
  token: string,
  actuatorId: string,
): Promise<MobileShellyDetail> {
  const query = new URLSearchParams({ actuador_id: actuatorId });
  return request<MobileShellyDetail>(`mobile/shelly_detalle.php?${query.toString()}`, { token });
}

export function saveMobileShelly(
  token: string,
  input: MobileShellySaveInput,
): Promise<{ actuador: MobileShellyActuator }> {
  return request<{ actuador: MobileShellyActuator }>('mobile/shelly_guardar.php', {
    method: 'POST',
    token,
    body: input,
  });
}

export function testMobileShelly(
  token: string,
  actuatorId: string,
): Promise<{ resultado: Record<string, unknown>; actuador: MobileShellyActuator }> {
  return request<{ resultado: Record<string, unknown>; actuador: MobileShellyActuator }>(
    'mobile/shelly_probar.php',
    { method: 'POST', token, body: { actuador_id: actuatorId } },
  );
}

export function detectMobileShelly(
  token: string,
  shellyDeviceId: string,
): Promise<MobileShellyDetection> {
  return request<MobileShellyDetection>('mobile/shelly_detectar.php', {
    method: 'POST',
    token,
    body: { shelly_device_id: shellyDeviceId },
  });
}

export function getMobileRoutines(token: string): Promise<MobileRoutinesResponse> {
  return request<MobileRoutinesResponse>('mobile/rutinas.php', { token });
}

export function getMobileRoutineDetail(
  token: string,
  routineId: string | number,
): Promise<MobileRoutineDetail> {
  const query = new URLSearchParams({ rutina_id: String(routineId) });
  return request<MobileRoutineDetail>(`mobile/rutina_detalle.php?${query.toString()}`, { token });
}

export function saveMobileRoutine(
  token: string,
  input: MobileRoutineSaveInput,
): Promise<{ rutina: MobileRoutineDetail['rutina'] }> {
  return request<{ rutina: MobileRoutineDetail['rutina'] }>('mobile/rutina_guardar.php', {
    method: 'POST', token, body: input,
  });
}

export function setMobileRoutineState(
  token: string,
  routineId: string | number,
  active: boolean,
): Promise<{ rutina_id: number; activa: boolean }> {
  return request<{ rutina_id: number; activa: boolean }>('mobile/rutina_estado.php', {
    method: 'POST', token, body: { rutina_id: Number(routineId), activa: active },
  });
}

export function runMobileRoutine(
  token: string,
  routineId: string | number,
): Promise<MobileRoutineRunResult> {
  return request<MobileRoutineRunResult>('mobile/rutina_ejecutar.php', {
    method: 'POST', token, body: { rutina_id: Number(routineId), confirmado: true },
  });
}

export function prepareAlexaIntegration(
  token: string,
  skillId: string,
): Promise<{ estado: 'PENDIENTE' | 'CONFIGURADA' }> {
  return request<{ estado: 'PENDIENTE' | 'CONFIGURADA' }>('mobile/integraciones.php', {
    method: 'POST', token, body: { accion: 'PREPARAR_ALEXA', nombre: 'Amazon Alexa', skill_id: skillId },
  });
}

export function disableAlexaIntegration(
  token: string,
): Promise<{ estado: 'INACTIVA' }> {
  return request<{ estado: 'INACTIVA' }>('mobile/integraciones.php', {
    method: 'POST', token, body: { accion: 'DESACTIVAR_ALEXA' },
  });
}

export function getMobileLiveHistory(
  token: string,
  deviceId: string,
  minutes = 30,
): Promise<MobileLiveHistory> {
  const query = new URLSearchParams({
    dispositivo_id: deviceId,
    minutos: String(minutes),
  });
  return request<MobileLiveHistory>(`mobile/graficas.php?${query.toString()}`, { token });
}

export function getMobileIncident(
  token: string,
  alertId: string | number,
  minutes = 15,
): Promise<MobileIncident> {
  const query = new URLSearchParams({
    alerta_id: String(alertId),
    minutos: String(minutes),
  });
  return request<MobileIncident>(`mobile/incidente.php?${query.toString()}`, { token });
}

export function manageMobileAlert(
  token: string,
  alertId: string | number,
  action: 'RECONOCER' | 'RESOLVER',
  comment: string,
): Promise<MobileAlertAction> {
  return request<MobileAlertAction>('mobile/atender_alerta.php', {
    method: 'POST',
    token,
    body: {
      alerta_id: Number(alertId),
      accion: action,
      comentario: comment.trim(),
    },
  });
}

export function silenceMobileAlarm(
  token: string,
  alertId: string | number,
): Promise<MobileAlarmCommand> {
  return request<MobileAlarmCommand>('mobile/silenciar_alarma.php', {
    method: 'POST',
    token,
    body: {
      alerta_id: Number(alertId),
    },
  });
}

export function getMobileQuotes(token: string, page = 1): Promise<MobileQuotesPage> {
  const query = new URLSearchParams({ pagina: String(page), por_pagina: '20' });
  return request<MobileQuotesPage>(`mobile/cotizaciones.php?${query.toString()}`, { token });
}

export function getMobileQuoteDetail(
  token: string,
  opportunityId: string | number,
): Promise<MobileQuoteDetail> {
  const query = new URLSearchParams({ id: String(opportunityId) });
  return request<MobileQuoteDetail>(`mobile/cotizacion.php?${query.toString()}`, { token });
}

export function registerMobilePush(
  token: string,
  expoPushToken: string,
  platform: 'ANDROID' | 'IOS',
  deviceName: string,
): Promise<MobilePushRegistration> {
  return request<MobilePushRegistration>('mobile/push/registrar.php', {
    method: 'POST',
    token,
    body: {
      expo_push_token: expoPushToken,
      plataforma: platform,
      nombre_dispositivo: deviceName,
    },
  });
}

export function getMobilePushStatus(token: string): Promise<MobilePushStatus> {
  return request<MobilePushStatus>('mobile/push/estado.php', { token });
}

export function disableMobilePush(
  token: string,
  expoPushToken: string,
): Promise<{ desactivada: boolean }> {
  return request<{ desactivada: boolean }>('mobile/push/desactivar.php', {
    method: 'POST',
    token,
    body: {
      expo_push_token: expoPushToken,
    },
  });
}
