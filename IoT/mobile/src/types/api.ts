export type UserRole = 'ADMIN' | 'OPERADOR' | 'LECTURA';

export type MobileUser = {
  id: number;
  nombre: string;
  email: string;
  rol: UserRole;
};

export type SessionResponse = {
  usuario: MobileUser;
  sesion: {
    token: string;
    tipo: 'Bearer';
    expira_en: string;
  };
};

export type GeneralState = 'NORMAL' | 'ALERTA' | 'ALARMA' | 'OFFLINE';
export type AlarmOperationMode =
  | 'NORMAL'
  | 'ALERTA'
  | 'ALARMA_SONORA'
  | 'ALARMA_SILENCIADA'
  | 'REVISION_PENDIENTE';
export type AlarmSilencedBy = 'NINGUNO' | 'APP_MOVIL' | 'BOTON_FISICO';

export type MobileDevice = {
  id: string;
  ubicacion: string;
  conexion: 'ONLINE' | 'OFFLINE';
  estado_general: GeneralState | null;
  temperatura: number | string | null;
  humedad: number | string | null;
  indice_calor: number | string | null;
  gas_raw: number | string | null;
  gas_porcentaje: number | string | null;
  gas_detectado: number | string | null;
  flama_detectada: number | string | null;
  peligro_activo?: number | string | null;
  alarma_enclavada?: number | string | null;
  alarma_silenciada?: number | string | null;
  revision_fisica_pendiente?: number | string | null;
  buzzer_encendido?: number | string | null;
  modo_operacion?: AlarmOperationMode | null;
  silenciada_por?: AlarmSilencedBy | null;
  salud_dht: string | null;
  salud_mq2: string | null;
  salud_flama: string | null;
  ultima_lectura: string | null;
  mq2_umbral_adc: number | string;
  mq2_calentamiento_restante_s: number | string;
  estado_registro?: 'Activo' | 'Mantenimiento' | 'Inactivo';
  wifi_rssi?: number | string | null;
  tiempo_encendido?: number | string | null;
  contador_alarmas?: number | string | null;
  contador_silencios_en_linea?: number | string | null;
  contador_silencios_fisicos?: number | string | null;
  contador_resets_fisicos?: number | string | null;
  mq2_calentamiento_total_s?: number | string;
  mq2_ultima_calibracion?: string | null;
  mq2_adc_aire_limpio?: number | string | null;
  ultima_alerta?: string | null;
};

export type MobileShellyActuator = {
  id: string;
  nombre: string | null;
  ubicacion: string;
  dispositivo_vinculado_id: string | null;
  shelly_device_id: string;
  modelo: string;
  generacion: 'GEN1' | 'GEN2_PLUS';
  canal: number | string;
  funcion: string;
  categoria: 'SEGURIDAD' | 'AUTOMATIZACION' | 'MONITOREO';
  tipo_carga: 'RESISTIVA' | 'INDUCTIVA' | 'ELECTRONICA' | 'DESCONOCIDA';
  corriente_max_a: number | string | null;
  potencia_max_w: number | string | null;
  tiempo_max_encendido_s: number | string | null;
  apagado_automatico: number | string;
  permite_rutinas: number | string;
  requiere_confirmacion: number | string;
  descripcion: string | null;
  ip_local: string | null;
  modo_control: 'LOCAL' | 'CLOUD' | 'HIBRIDO';
  estado: 'Activo' | 'Mantenimiento' | 'Inactivo';
  conexion: 'ONLINE' | 'OFFLINE' | 'SIN_DATOS' | 'DESACTUALIZADO';
  online: number | string;
  salida_encendida: number | string | null;
  potencia_w: number | string | null;
  voltaje_v: number | string | null;
  corriente_a: number | string | null;
  temperatura_c: number | string | null;
  sincronizado_en: string | null;
  ultimo_error: string | null;
};

export type MobileShellyEvent = {
  id: number | string;
  evento: string;
  origen: 'CLOUD' | 'WEBHOOK' | 'SISTEMA' | 'USUARIO';
  salida_encendida: number | string | null;
  detalle: Record<string, unknown>;
  fecha_hora: string;
};

export type MobileShellyDetail = {
  actuador: MobileShellyActuator;
  eventos: MobileShellyEvent[];
  dispositivos_esp32: Array<{
    id: string;
    ubicacion: string;
    estado: 'Activo' | 'Mantenimiento';
  }>;
  permisos: {
    administrar: boolean;
    controlar: boolean;
  };
};

export type MobileShellySaveInput = {
  accion: 'CREAR' | 'ACTUALIZAR';
  id: string;
  nombre: string;
  ubicacion: string;
  shelly_device_id: string;
  modelo: string;
  generacion: 'GEN1' | 'GEN2_PLUS';
  ip_local: string;
  canal: number;
  funcion: 'SIRENA' | 'BALIZA' | 'VENTILACION' | 'CONTACTOR' | 'OTRO';
  categoria: 'SEGURIDAD' | 'AUTOMATIZACION' | 'MONITOREO';
  tipo_carga: 'RESISTIVA' | 'INDUCTIVA' | 'ELECTRONICA' | 'DESCONOCIDA';
  corriente_max_a: number | null;
  potencia_max_w: number | null;
  tiempo_max_encendido_s: number | null;
  apagado_automatico: boolean;
  permite_rutinas: boolean;
  requiere_confirmacion: boolean;
  descripcion: string;
  modo_control: 'LOCAL' | 'CLOUD' | 'HIBRIDO';
  dispositivo_vinculado_id: string;
  estado: 'Activo' | 'Mantenimiento' | 'Inactivo';
};

export type MobileShellyDetection = {
  shelly_device_id: string;
  modelo: string;
  generacion: 'GEN1' | 'GEN2_PLUS';
  online: boolean;
  canales: number[];
};

export type MobileRoutineActuator = {
  id: string;
  nombre: string | null;
  ubicacion: string;
  modelo: string;
  canal: number | string;
  funcion: string;
  tipo_carga: string;
  apagado_automatico: number | string;
  tiempo_max_encendido_s: number | string | null;
  conexion: 'ONLINE' | 'OFFLINE' | 'SIN_DATOS' | 'DESACTUALIZADO';
  online: number | string;
  salida_encendida: number | string | null;
};

export type MobileRoutineAction = {
  id?: number | string;
  orden?: number | string;
  actuador_id: string;
  accion: 'ENCENDER' | 'APAGAR';
  actuador_nombre?: string | null;
  ubicacion?: string | null;
  funcion?: string | null;
};

export type MobileRoutine = {
  id: number | string;
  nombre: string;
  descripcion: string | null;
  tipo_disparador: 'MANUAL' | 'HORARIO';
  hora_local: string | null;
  dias: number[];
  zona_horaria: string;
  activa: number | string;
  ultima_ejecucion: string | null;
  acciones_total?: number | string;
  acciones_no_disponibles?: number | string;
  ultimo_estado?: 'COMPLETADA' | 'PARCIAL' | 'FALLIDA' | 'OMITIDA' | null;
  acciones?: MobileRoutineAction[];
};

export type MobileRoutineExecution = {
  id: number | string;
  rutina_id: number | string;
  rutina_nombre: string;
  origen: 'MANUAL' | 'CRON';
  estado: 'EJECUTANDO' | 'COMPLETADA' | 'PARCIAL' | 'FALLIDA' | 'OMITIDA';
  acciones_total: number | string;
  acciones_exitosas: number | string;
  detalle: Record<string, unknown>;
  iniciada_en: string;
  finalizada_en: string | null;
  solicitado_por_nombre: string | null;
};

export type MobileRoutinesResponse = {
  rutinas: MobileRoutine[];
  actuadores: MobileRoutineActuator[];
  ejecuciones: MobileRoutineExecution[];
  integraciones: {
    shelly: { estado: 'CONFIGURADA' | 'PENDIENTE'; equipos_disponibles: number };
    alexa: {
      proveedor: 'ALEXA';
      nombre: string;
      estado: 'PENDIENTE' | 'CONFIGURADA' | 'ERROR' | 'INACTIVA';
      identificador_externo: string | null;
      vinculada: boolean;
      oauth_listo: boolean;
      lambda_lista: boolean;
      equipos_disponibles: number;
      rutinas_disponibles: number;
    };
  };
  permisos: { administrar: boolean; ejecutar: boolean };
};

export type MobileRoutineDetail = {
  rutina: MobileRoutine & { acciones: MobileRoutineAction[] };
  actuadores: MobileRoutineActuator[];
  ejecuciones: MobileRoutineExecution[];
  permisos: { administrar: boolean; ejecutar: boolean };
};

export type MobileRoutineSaveInput = {
  accion: 'CREAR' | 'ACTUALIZAR';
  id?: number;
  nombre: string;
  descripcion: string;
  tipo_disparador: 'MANUAL' | 'HORARIO';
  hora_local: string | null;
  dias: number[];
  zona_horaria: string;
  activa: boolean;
  acciones: Array<{ actuador_id: string; accion: 'ENCENDER' | 'APAGAR' }>;
};

export type MobileRoutineRunResult = {
  ejecutada: boolean;
  ejecucion_id: number;
  estado: 'COMPLETADA' | 'PARCIAL' | 'FALLIDA' | 'OMITIDA';
  acciones_total: number;
  acciones_exitosas: number;
};

export type MobileAlert = {
  id: number | string;
  dispositivo_id: string;
  ubicacion: string;
  tipo_alerta: string;
  valor_sensor: number | string | null;
  severidad: 'NORMAL' | 'PRECAUCION' | 'CRITICO';
  atendida: number | string;
  fecha_hora: string;
  estado_atencion: 'NUEVA' | 'RECONOCIDA' | 'RESUELTA';
};

export type MobileSummary = {
  generado_en: string;
  revision: string;
  resumen: {
    estado_general: GeneralState;
    dispositivos_total: number | string;
    dispositivos_online: number | string;
    dispositivos_offline: number | string;
    alertas_mes: number | string;
    criticas_abiertas: number | string;
  };
  dispositivos: MobileDevice[];
  alertas: MobileAlert[];
};

export type MobileAlertFilters = {
  dispositivo_id?: string;
  sensor?: 'GAS' | 'FLAMA' | 'TEMPERATURA' | 'DHT' | 'CONECTIVIDAD';
  severidad?: 'NORMAL' | 'PRECAUCION' | 'CRITICO';
  estado?: 'NUEVA' | 'RECONOCIDA' | 'RESUELTA';
  pagina?: number;
  por_pagina?: number;
};

export type MobileAlertsPage = {
  alertas: MobileAlert[];
  dispositivos: Array<{ id: string; ubicacion: string }>;
  paginacion: {
    pagina: number;
    por_pagina: number;
    total: number;
    paginas: number;
  };
  filtros: Omit<MobileAlertFilters, 'pagina' | 'por_pagina'>;
};

export type MobileDevicesResponse = {
  generado_en: string;
  dispositivos: MobileDevice[];
  actuadores_shelly: MobileShellyActuator[];
};

export type MobileShellyCommand = {
  comando_id: number;
  estado: 'APLICADO' | 'PROCESANDO' | 'REINTENTAR' | 'FALLIDO';
  aplicado: boolean;
  salida_encendida?: boolean;
  error?: string;
};

export type MobileLiveSample = {
  periodo: string;
  temperatura: number | string | null;
  humedad: number | string | null;
  gas_raw: number | string | null;
  gas_porcentaje: number | string | null;
  gas_detectado: number | string | null;
  flama_detectada: number | string | null;
  estado_general: GeneralState | null;
};

export type MobileLiveHistory = {
  generado_en: string;
  intervalo_actualizacion_s: number;
  ventana_minutos: number;
  dispositivo: {
    id: string;
    ubicacion: string;
    conexion: 'ONLINE' | 'OFFLINE';
    mq2_umbral_adc: number | string;
  };
  muestras: MobileLiveSample[];
};

export type MobileIncidentSample = {
  periodo: string;
  temperatura: number | string | null;
  humedad: number | string | null;
  indice_calor: number | string | null;
  gas_raw: number | string | null;
  gas_porcentaje: number | string | null;
  gas_detectado: number | string | null;
  flama_detectada: number | string | null;
  estado_general: 'NORMAL' | 'ALERTA' | 'ALARMA';
  salud_dht: string | null;
  salud_mq2: string | null;
  salud_flama: string | null;
};

export type MobileIncident = {
  alerta: MobileAlert & {
    responsable?: string | null;
    comentario?: string | null;
    gestion_fecha?: string | null;
  };
  ventana: {
    minutos_antes: number;
    minutos_despues: number;
    desde: string;
    hasta: string;
  };
  gas_umbral: number | string;
  lectura_evento: MobileIncidentSample | null;
  estado_actual: (MobileIncidentSample & {
    conexion: 'ONLINE' | 'OFFLINE';
    peligro_activo: number | string | null;
    alarma_enclavada: number | string | null;
    alarma_silenciada: number | string | null;
    revision_fisica_pendiente: number | string | null;
    buzzer_encendido: number | string | null;
    modo_operacion: AlarmOperationMode | null;
    silenciada_por: AlarmSilencedBy | null;
  }) | null;
  serie: MobileIncidentSample[];
};

export type MobileAlertAction = {
  alerta_id: number | string;
  estado_atencion: 'RECONOCIDA' | 'RESUELTA';
  responsable: string;
};

export type MobileAlarmCommand = {
  comando_id: number;
  dispositivo_id: string;
  estado: 'PENDIENTE' | 'ENTREGADO';
  expira_en: string;
};

export type MobilePushRegistration = {
  activa: boolean;
  plataforma: 'ANDROID' | 'IOS';
  nombre_dispositivo: string;
};

export type MobilePushStatus = {
  habilitadas: number;
  registros: Array<{
    id: number;
    plataforma: 'ANDROID' | 'IOS';
    nombre_dispositivo: string;
    ultimo_registro: string;
  }>;
};

export type ApiEnvelope<T> = {
  ok: boolean;
  data: T;
  error?: string;
};
