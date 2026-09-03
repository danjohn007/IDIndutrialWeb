import Ionicons from '@expo/vector-icons/Ionicons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { useAuth } from '@/context/auth-context';
import {
  ApiError,
  getMobileIncident,
  manageMobileAlert,
  silenceMobileAlarm,
} from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type {
  MobileAlert,
  MobileIncident,
  MobileIncidentSample,
} from '@/types/api';

function optionalNumeric(value: number | string | null | undefined): number | null {
  if (value === null || value === undefined || value === '') return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

function numeric(value: number | string | null | undefined): number {
  return optionalNumeric(value) ?? 0;
}

function measurement(
  value: number | string | null | undefined,
  decimals: number,
  unit: string,
): string {
  const parsed = optionalNumeric(value);
  return parsed === null ? '--' : `${parsed.toFixed(decimals)}${unit}`;
}

function dateFromUtc(value: string): Date {
  return new Date(`${value.replace(' ', 'T')}Z`);
}

function dateLabel(value: string | null | undefined): string {
  if (!value) return 'Sin registro';
  const date = dateFromUtc(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date);
}

function timeLabel(value: string): string {
  const date = dateFromUtc(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es-MX', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}

function severityColor(severity: MobileAlert['severidad']): string {
  if (severity === 'CRITICO') return colors.critical;
  if (severity === 'PRECAUCION') return colors.warning;
  return colors.normal;
}

function alertCauseText(alert: MobileAlert): string {
  const type = alert.tipo_alerta.toLowerCase();
  if (type.includes('sin conexion') || type.includes('desconect')) {
    return 'El ESP32 dejo de reportar. Revisa corriente, Wi-Fi e Internet.';
  }
  if (type.includes('flama')) {
    return 'El sensor de flama detecto fuego.';
  }
  if (type.includes('estacion manual') || type.includes('pulsador')) {
    return 'Se bajo la palanca manual en sitio.';
  }
  if (type.includes('humo') || type.includes('gas')) {
    return 'El sensor MQ-2 detecto humo o gas.';
  }
  if (type.includes('temperatura')) {
    return 'La temperatura paso el limite configurado.';
  }
  if (type.includes('fallo') || type.includes('revisar')) {
    return 'Un sensor necesita revision.';
  }
  return 'El dispositivo reporto una condicion anormal.';
}

function eventValueText(incident: MobileIncident): string {
  const type = incident.alerta.tipo_alerta.toLowerCase();
  const sample = incident.lectura_evento;
  if (type.includes('sin conexion') || type.includes('desconect')) return 'Sin comunicacion';
  if (type.includes('flama')) return 'Flama detectada';
  if (type.includes('estacion manual') || type.includes('pulsador')) return 'Activada';
  if (type.includes('humo') || type.includes('gas')) {
    return measurement(sample?.gas_raw ?? incident.alerta.valor_sensor, 0, ' ADC');
  }
  if (type.includes('temperatura')) {
    return measurement(sample?.temperatura ?? incident.alerta.valor_sensor, 1, ' °C');
  }
  return incident.alerta.valor_sensor === null
    ? 'Condición registrada'
    : String(incident.alerta.valor_sensor);
}

function Metric({
  icon,
  label,
  value,
  color,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  value: string;
  color: string;
}) {
  return (
    <View style={styles.metric}>
      <Ionicons color={color} name={icon} size={19} />
      <Text style={styles.metricLabel}>{label}</Text>
      <Text style={[styles.metricValue, { color }]}>{value}</Text>
    </View>
  );
}

function SignalBars({
  color,
  samples,
  selectedIndex,
  valueFor,
  onSelect,
}: {
  color: string;
  samples: MobileIncidentSample[];
  selectedIndex: number;
  valueFor: (sample: MobileIncidentSample) => number;
  onSelect: (index: number) => void;
}) {
  const values = samples.map(valueFor);
  const minimum = values.length ? Math.min(...values) : 0;
  const maximum = values.length ? Math.max(...values) : 0;
  const range = Math.max(1, maximum - minimum);

  return (
    <View style={styles.signalBars}>
      {samples.map((sample, index) => {
        const height = 8 + ((valueFor(sample) - minimum) / range) * 48;
        const selected = index === selectedIndex;
        return (
          <Pressable
            accessibilityLabel={`Muestra de las ${timeLabel(sample.periodo)}`}
            key={`${sample.periodo}-${index}`}
            onPress={() => onSelect(index)}
            style={styles.signalSlot}
          >
            <View
              style={[
                styles.signalBar,
                {
                  backgroundColor: selected ? colors.textStrong : color,
                  height,
                  opacity: selected ? 1 : 0.78,
                },
              ]}
            />
          </Pressable>
        );
      })}
    </View>
  );
}

export default function IncidentScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const { token, user } = useAuth();
  const [incident, setIncident] = useState<MobileIncident | null>(null);
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [comment, setComment] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [silencing, setSilencing] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const load = useCallback(async () => {
    if (!token || !id) return;
    setLoading(true);
    setError('');
    try {
      const response = await getMobileIncident(token, id);
      setIncident(response);
      const eventTime = dateFromUtc(response.alerta.fecha_hora).getTime();
      const closest = response.serie.reduce((best, sample, index) => {
        const distance = Math.abs(dateFromUtc(sample.periodo).getTime() - eventTime);
        return distance < best.distance ? { distance, index } : best;
      }, { distance: Number.POSITIVE_INFINITY, index: 0 });
      setSelectedIndex(closest.index);
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No fue posible cargar el incidente.');
    } finally {
      setLoading(false);
    }
  }, [id, token]);

  useEffect(() => {
    void load();
  }, [load]);

  const samples = incident?.serie ?? [];
  const selectedSample = samples[selectedIndex] ?? null;
  const eventSample = incident?.lectura_evento ?? null;
  const currentState = incident?.estado_actual ?? null;
  const isConnectivityAlert = Boolean(
    incident
    && (
      incident.alerta.tipo_alerta.toLowerCase().includes('sin conexion')
      || incident.alerta.tipo_alerta.toLowerCase().includes('desconect')
    )
  );
  const selectedIsEvent = Boolean(
    incident
    && selectedSample
    && Math.abs(
      dateFromUtc(selectedSample.periodo).getTime()
      - dateFromUtc(incident.alerta.fecha_hora).getTime()
    ) < 1000,
  );
  const peaks = useMemo(() => {
    const temperatures = samples
      .map((sample) => optionalNumeric(sample.temperatura))
      .filter((value): value is number => value !== null);
    const gasValues = samples
      .map((sample) => optionalNumeric(sample.gas_raw))
      .filter((value): value is number => value !== null);
    return {
      temperature: temperatures.length ? Math.max(...temperatures) : null,
      gas: gasValues.length ? Math.max(...gasValues) : null,
      flame: samples.filter((sample) => numeric(sample.flama_detectada) === 1).length,
    };
  }, [samples]);
  const canManage = user?.rol === 'ADMIN' || user?.rol === 'OPERADOR';
  const alarmOnline = currentState?.conexion === 'ONLINE';
  const alarmLatched = numeric(currentState?.alarma_enclavada) === 1;
  const alarmSilenced = numeric(currentState?.alarma_silenciada) === 1;
  const dangerActive = numeric(currentState?.peligro_activo) === 1;

  const executeAction = async (action: 'RECONOCER' | 'RESOLVER') => {
    if (!token || !id) return;
    setSubmitting(true);
    setError('');
    setNotice('');
    try {
      const response = await manageMobileAlert(token, id, action, comment);
      setNotice(
        response.estado_atencion === 'RESUELTA'
          ? 'Alerta resuelta.'
          : 'Alerta reconocida.',
      );
      setComment('');
      await load();
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No fue posible actualizar la alerta.');
    } finally {
      setSubmitting(false);
    }
  };

  const requestResolve = () => {
    Alert.alert(
      'Resolver alerta',
      'Confirma que el sitio ya fue revisado.',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Resolver',
          style: 'destructive',
          onPress: () => void executeAction('RESOLVER'),
        },
      ],
    );
  };

  const executeSilence = async () => {
    if (!token || !id) return;
    setSilencing(true);
    setError('');
    setNotice('');
    try {
      await silenceMobileAlarm(token, id);
      setNotice('Orden enviada. El sonido se apagara en unos segundos.');
      await new Promise((resolve) => setTimeout(resolve, 2500));
      await load();
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No fue posible silenciar el buzzer.');
    } finally {
      setSilencing(false);
    }
  };

  const requestSilence = () => {
    Alert.alert(
      'Apagar sonido',
      'Se apaga el sonido. La alerta sigue activa hasta revisar el sitio.',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Apagar sonido',
          style: 'destructive',
          onPress: () => void executeSilence(),
        },
      ],
    );
  };

  return (
    <AppScreen
      eyebrow={id ? `ALERTA #${id}` : 'ALERTA'}
      includeBottomInset
      leading={
        <Pressable
          accessibilityLabel="Volver a alertas"
          onPress={() => router.back()}
          style={styles.iconButton}
        >
          <Ionicons color={colors.text} name="arrow-back" size={22} />
        </Pressable>
      }
      title="Detalle"
    >
      {loading && !incident ? <ActivityIndicator color={colors.warning} size="large" /> : null}
      {error ? (
        <View style={styles.errorPanel}>
          <Ionicons color={colors.critical} name="alert-circle-outline" size={24} />
          <Text style={styles.errorText}>{error}</Text>
          <Pressable onPress={() => void load()} style={styles.retryButton}>
            <Text style={styles.retryText}>Reintentar</Text>
          </Pressable>
        </View>
      ) : null}

      {incident ? (
        <>
          <View style={[styles.eventCard, { borderLeftColor: severityColor(incident.alerta.severidad) }]}>
            <View style={styles.eventHeader}>
              <View style={styles.eventCopy}>
                <Text style={styles.eventTitle}>{incident.alerta.tipo_alerta}</Text>
                <Text style={styles.eventMeta}>
                  {incident.alerta.dispositivo_id} · {incident.alerta.ubicacion}
                </Text>
              </View>
              <Text style={[styles.severity, { color: severityColor(incident.alerta.severidad) }]}>
                {incident.alerta.severidad}
              </Text>
            </View>
            <View style={styles.eventFooter}>
              <Text style={styles.eventDate}>{dateLabel(incident.alerta.fecha_hora)}</Text>
              <Text style={styles.attention}>{incident.alerta.estado_atencion}</Text>
            </View>
            {incident.alerta.responsable ? (
              <Text style={styles.managementMeta}>
                Última gestión: {incident.alerta.responsable}
                {incident.alerta.comentario ? ` · ${incident.alerta.comentario}` : ''}
              </Text>
            ) : null}
          </View>

          <View style={styles.causeCard}>
            <View style={styles.causeIcon}>
              <Ionicons
                color={severityColor(incident.alerta.severidad)}
                name={isConnectivityAlert
                  ? 'cloud-offline-outline'
                  : incident.alerta.tipo_alerta.toLowerCase().includes('flama')
                    ? 'flame'
                    : incident.alerta.tipo_alerta.toLowerCase().includes('estacion manual')
                      || incident.alerta.tipo_alerta.toLowerCase().includes('pulsador')
                      ? 'hand-left'
                      : 'warning'}
                size={23}
              />
            </View>
            <View style={styles.causeCopy}>
              <Text style={styles.causeLabel}>QUÉ ORIGINÓ LA ALERTA</Text>
              <Text style={styles.causeValue}>{eventValueText(incident)}</Text>
              <Text style={styles.causeDescription}>{alertCauseText(incident.alerta)}</Text>
              {eventSample ? (
                <Text style={styles.causeTime}>
                  Lectura del evento: {dateLabel(eventSample.periodo)}
                </Text>
              ) : null}
            </View>
          </View>

          {!isConnectivityAlert ? <>
            <Text style={styles.groupTitle}>Valores durante la ventana analizada</Text>
            <View style={styles.metrics}>
            <Metric
              color={colors.normal}
              icon="thermometer-outline"
              label="Máxima"
              value={peaks.temperature === null ? '--' : `${peaks.temperature.toFixed(1)} °C`}
            />
            <Metric
              color={colors.warning}
              icon="speedometer-outline"
              label="Gas máximo"
              value={peaks.gas === null ? '--' : `${Math.round(peaks.gas)} ADC`}
            />
            <Metric
              color={peaks.flame ? colors.critical : colors.success}
              icon="flame-outline"
              label="Flama"
              value={peaks.flame ? `${peaks.flame} detección${peaks.flame === 1 ? '' : 'es'}` : 'No detectada'}
            />
            </View>
          </> : null}

          {currentState ? (
            <View style={styles.currentCard}>
              <View style={styles.currentHeading}>
                <Text style={styles.sectionTitle}>Estado actual del dispositivo</Text>
                <Text
                  style={[
                    styles.currentConnection,
                    currentState.conexion === 'OFFLINE' && styles.currentOffline,
                  ]}
                >
                  {currentState.conexion}
                </Text>
              </View>
              <Text style={styles.currentDescription}>
                {isConnectivityAlert
                  ? currentState.conexion === 'ONLINE'
                    ? 'El ESP32 ya volvio a reportar.'
                    : 'El ESP32 sigue sin reportar.'
                  : incident.alerta.tipo_alerta.toLowerCase().includes('flama')
                  ? numeric(currentState.flama_detectada) === 1
                    ? 'Todavia detecta flama.'
                    : 'Ya no detecta flama.'
                  : incident.alerta.tipo_alerta.toLowerCase().includes('estacion manual')
                    || incident.alerta.tipo_alerta.toLowerCase().includes('pulsador')
                    ? numeric(currentState.estacion_manual_activada) === 1
                      ? 'La palanca sigue activada.'
                      : 'La palanca ya esta normal.'
                    : 'Estos son los datos mas recientes.'}
              </Text>
              {!isConnectivityAlert ? <View style={styles.sampleValues}>
                <Text style={styles.sampleValue}>Temp. {measurement(currentState.temperatura, 1, ' °C')}</Text>
                <Text style={styles.sampleValue}>Hum. {measurement(currentState.humedad, 1, '%')}</Text>
                <Text style={styles.sampleValue}>Gas {measurement(currentState.gas_raw, 0, ' ADC')}</Text>
                <Text style={[styles.sampleValue, numeric(currentState.flama_detectada) === 1 && styles.sampleCritical]}>
                  Flama {numeric(currentState.flama_detectada) === 1 ? 'detectada' : 'sin detección'}
                </Text>
                <Text style={[styles.sampleValue, numeric(currentState.estacion_manual_activada) === 1 && styles.sampleCritical]}>
                  Manual {numeric(currentState.estacion_manual_activada) === 1 ? 'activada' : 'normal'}
                </Text>
              </View> : null}
            </View>
          ) : null}

          {currentState && !isConnectivityAlert ? (
            <View style={[
              styles.alarmControlCard,
              alarmLatched && !alarmSilenced && styles.alarmControlCritical,
              alarmSilenced && styles.alarmControlSilenced,
            ]}>
              <View style={styles.alarmControlHeader}>
                <View style={styles.alarmControlTitle}>
                  <Ionicons
                    color={alarmLatched && !alarmSilenced ? colors.critical : colors.warning}
                    name={alarmSilenced ? 'volume-mute-outline' : 'notifications-outline'}
                    size={24}
                  />
                  <View style={styles.alarmControlCopy}>
                    <Text style={styles.sectionTitle}>Alarma física</Text>
                    <Text style={styles.alarmMode}>
                      {!alarmOnline
                        ? 'SIN CONEXION'
                        : !alarmLatched
                          ? 'SIN ALARMA'
                          : alarmSilenced
                            ? 'SONIDO APAGADO'
                            : 'SONANDO'}
                    </Text>
                  </View>
                </View>
                <Text style={[
                  styles.alarmConnection,
                  !alarmOnline && styles.currentOffline,
                ]}>
                  {alarmOnline ? 'ESP32 ONLINE' : 'ESP32 OFFLINE'}
                </Text>
              </View>

              <Text style={styles.alarmControlDescription}>
                {!alarmOnline
                  ? 'El ESP32 esta offline. Revisa el sitio.'
                  : !alarmLatched
                    ? 'No hay alarma pendiente.'
                    : alarmSilenced && dangerActive
                      ? 'El sonido esta apagado, pero el peligro sigue.'
                      : alarmSilenced
                        ? 'Ya no hay peligro. Falta revisar y resetear con el boton fisico.'
                        : 'Puedes apagar el sonido. La alerta seguira activa.'}
              </Text>

              {alarmSilenced ? (
                <View style={styles.alarmSilencedRow}>
                  <Ionicons color={colors.warning} name="warning-outline" size={19} />
                  <Text style={styles.alarmSilencedText}>
                    Revisión física pendiente
                    {currentState.silenciada_por && currentState.silenciada_por !== 'NINGUNO'
                      ? ` · ${currentState.silenciada_por === 'APP_MOVIL' ? 'apagada desde la app' : 'apagada con boton fisico'}`
                      : ''}
                  </Text>
                </View>
              ) : null}

              {canManage && alarmOnline && alarmLatched && !alarmSilenced ? (
                <Pressable
                  disabled={silencing}
                  onPress={requestSilence}
                  style={({ pressed }) => [
                    styles.silenceAction,
                    (pressed || silencing) && styles.pressed,
                  ]}
                >
                  {silencing ? (
                    <ActivityIndicator color={colors.textStrong} />
                  ) : (
                    <>
                      <Ionicons color={colors.textStrong} name="volume-mute-outline" size={20} />
                      <Text style={styles.silenceActionText}>Apagar sonido</Text>
                    </>
                  )}
                </Pressable>
              ) : null}

              {!canManage && alarmLatched ? (
                <Text style={styles.alarmReadOnly}>
                  Tu usuario solo puede ver el estado.
                </Text>
              ) : null}
            </View>
          ) : null}

          {!isConnectivityAlert ? <View style={styles.contextCard}>
            <View style={styles.contextHeader}>
              <View>
                <Text style={styles.sectionTitle}>Contexto del incidente</Text>
                <Text style={styles.sectionMeta}>
                  Evolución desde {incident.ventana.minutos_antes} min antes hasta {incident.ventana.minutos_despues} min después
                </Text>
              </View>
              <Text style={styles.sampleCount}>{samples.length} muestras</Text>
            </View>

            {samples.length ? (
              <>
                <View style={styles.signalGroup}>
                  <View style={styles.signalHeading}>
                    <Text style={styles.signalLabel}>Temperatura</Text>
                    <Text style={styles.signalUnit}>°C</Text>
                  </View>
                  <SignalBars
                    color={colors.normal}
                    onSelect={setSelectedIndex}
                    samples={samples}
                    selectedIndex={selectedIndex}
                    valueFor={(sample) => numeric(sample.temperatura)}
                  />
                </View>
                <View style={styles.signalGroup}>
                  <View style={styles.signalHeading}>
                    <Text style={styles.signalLabel}>Humo/Gas</Text>
                    <Text style={styles.signalUnit}>Umbral {incident.gas_umbral} ADC</Text>
                  </View>
                  <SignalBars
                    color={colors.warning}
                    onSelect={setSelectedIndex}
                    samples={samples}
                    selectedIndex={selectedIndex}
                    valueFor={(sample) => numeric(sample.gas_raw)}
                  />
                </View>
                <View style={styles.flameTimeline}>
                  <Text style={styles.signalLabel}>Flama</Text>
                  <View style={styles.flameDots}>
                    {samples.map((sample, index) => (
                      <Pressable
                        accessibilityLabel={`Flama ${numeric(sample.flama_detectada) === 1 ? 'detectada' : 'normal'} a las ${timeLabel(sample.periodo)}`}
                        key={`${sample.periodo}-flame`}
                        onPress={() => setSelectedIndex(index)}
                        style={[
                          styles.flameDot,
                          {
                            backgroundColor: numeric(sample.flama_detectada) === 1
                              ? colors.critical
                              : colors.border,
                            borderColor: index === selectedIndex ? colors.textStrong : 'transparent',
                          },
                        ]}
                      />
                    ))}
                  </View>
                </View>
                <View style={styles.flameTimeline}>
                  <Text style={styles.signalLabel}>Estacion manual</Text>
                  <View style={styles.flameDots}>
                    {samples.map((sample, index) => (
                      <Pressable
                        accessibilityLabel={`Estacion manual ${numeric(sample.estacion_manual_activada) === 1 ? 'activada' : 'normal'} a las ${timeLabel(sample.periodo)}`}
                        key={`${sample.periodo}-manual`}
                        onPress={() => setSelectedIndex(index)}
                        style={[
                          styles.flameDot,
                          {
                            backgroundColor: numeric(sample.estacion_manual_activada) === 1
                              ? colors.critical
                              : colors.border,
                            borderColor: index === selectedIndex ? colors.textStrong : 'transparent',
                          },
                        ]}
                      />
                    ))}
                  </View>
                </View>
              </>
            ) : (
              <Text style={styles.emptyText}>No hay muestras históricas para esta ventana.</Text>
            )}
          </View> : null}

          {selectedSample && !isConnectivityAlert ? (
            <View style={styles.sampleCard}>
              <View style={styles.sampleHeader}>
                <Text style={styles.sectionTitle}>
                  {selectedIsEvent ? 'Lectura que originó la alerta' : 'Lectura histórica seleccionada'}
                </Text>
                <Text style={styles.sampleTime}>{timeLabel(selectedSample.periodo)}</Text>
              </View>
              {!selectedIsEvent ? (
                <Text style={styles.sampleExplanation}>
                  Lectura de otro momento de la alerta.
                </Text>
              ) : null}
              <View style={styles.sampleValues}>
                <Text style={styles.sampleValue}>Temp. {measurement(selectedSample.temperatura, 1, ' °C')}</Text>
                <Text style={styles.sampleValue}>Hum. {measurement(selectedSample.humedad, 1, '%')}</Text>
                <Text style={styles.sampleValue}>Gas {measurement(selectedSample.gas_raw, 0, ' ADC')}</Text>
                <Text style={[styles.sampleValue, numeric(selectedSample.flama_detectada) === 1 && styles.sampleCritical]}>
                  Flama {numeric(selectedSample.flama_detectada) === 1 ? 'Sí' : 'No'}
                </Text>
                <Text style={[styles.sampleValue, numeric(selectedSample.estacion_manual_activada) === 1 && styles.sampleCritical]}>
                  Manual {numeric(selectedSample.estacion_manual_activada) === 1 ? 'activada' : 'normal'}
                </Text>
              </View>
            </View>
          ) : null}

          <View style={styles.actionCard}>
            <Text style={styles.sectionTitle}>Gestión de la alerta</Text>
            {!canManage ? (
              <View style={styles.readOnly}>
                <Ionicons color={colors.muted} name="eye-outline" size={20} />
                <Text style={styles.readOnlyText}>Tu perfil es de consulta. No puede modificar alertas.</Text>
              </View>
            ) : incident.alerta.estado_atencion === 'RESUELTA' ? (
              <View style={styles.resolved}>
                <Ionicons color={colors.success} name="checkmark-circle-outline" size={21} />
                <Text style={styles.resolvedText}>Esta alerta ya fue resuelta.</Text>
              </View>
            ) : (
              <>
                <TextInput
                  maxLength={500}
                  multiline
                  onChangeText={setComment}
                  placeholder="Comentario opcional sobre la revisión"
                  placeholderTextColor={colors.muted}
                  style={styles.comment}
                  textAlignVertical="top"
                  value={comment}
                />
                <View style={styles.actions}>
                  {incident.alerta.estado_atencion === 'NUEVA' ? (
                    <Pressable
                      disabled={submitting}
                      onPress={() => void executeAction('RECONOCER')}
                      style={({ pressed }) => [styles.secondaryAction, (pressed || submitting) && styles.pressed]}
                    >
                      <Ionicons color={colors.warning} name="eye-outline" size={19} />
                      <Text style={styles.secondaryActionText}>Reconocer</Text>
                    </Pressable>
                  ) : null}
                  <Pressable
                    disabled={submitting}
                    onPress={requestResolve}
                    style={({ pressed }) => [styles.primaryAction, (pressed || submitting) && styles.pressed]}
                  >
                    {submitting ? (
                      <ActivityIndicator color={colors.black} />
                    ) : (
                      <>
                        <Ionicons color={colors.black} name="checkmark-circle-outline" size={19} />
                        <Text style={styles.primaryActionText}>Resolver</Text>
                      </>
                    )}
                  </Pressable>
                </View>
              </>
            )}
            {notice ? <Text style={styles.notice}>{notice}</Text> : null}
          </View>
        </>
      ) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  iconButton: {
    alignItems: 'center',
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    height: 44,
    justifyContent: 'center',
    width: 44,
  },
  eventCard: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderLeftWidth: 4,
    borderRadius: radius.md,
    borderWidth: 1,
    gap: spacing.md,
    padding: spacing.lg,
  },
  eventHeader: { alignItems: 'flex-start', flexDirection: 'row', gap: spacing.md },
  eventCopy: { flex: 1, gap: spacing.xs },
  eventTitle: { color: colors.textStrong, fontSize: 21, fontWeight: '900' },
  eventMeta: { color: colors.muted, fontSize: 13 },
  severity: { fontSize: 11, fontWeight: '900' },
  eventFooter: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between' },
  eventDate: { color: colors.text, fontSize: 13 },
  attention: { color: colors.normal, fontSize: 11, fontWeight: '900' },
  managementMeta: { borderTopColor: colors.borderSoft, borderTopWidth: 1, color: colors.muted, fontSize: 12, lineHeight: 18, paddingTop: spacing.md },
  causeCard: { alignItems: 'flex-start', backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, flexDirection: 'row', gap: spacing.md, padding: spacing.lg },
  causeIcon: { alignItems: 'center', backgroundColor: colors.surfaceStrong, borderRadius: radius.md, height: 44, justifyContent: 'center', width: 44 },
  causeCopy: { flex: 1, gap: spacing.xs },
  causeLabel: { color: colors.muted, fontSize: 10, fontWeight: '900' },
  causeValue: { color: colors.textStrong, fontSize: 20, fontWeight: '900' },
  causeDescription: { color: colors.text, fontSize: 13, lineHeight: 19 },
  causeTime: { color: colors.muted, fontSize: 11, marginTop: spacing.xs },
  groupTitle: { color: colors.textStrong, fontSize: 15, fontWeight: '900', marginBottom: -spacing.sm },
  metrics: { flexDirection: 'row', gap: spacing.sm },
  metric: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, flex: 1, gap: spacing.xs, minWidth: 0, padding: spacing.md },
  metricLabel: { color: colors.muted, fontSize: 10, fontWeight: '700' },
  metricValue: { fontSize: 14, fontWeight: '900' },
  contextCard: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.lg, padding: spacing.lg },
  contextHeader: { alignItems: 'flex-start', flexDirection: 'row', justifyContent: 'space-between' },
  sectionTitle: { color: colors.textStrong, fontSize: 17, fontWeight: '900' },
  sectionMeta: { color: colors.muted, fontSize: 12, marginTop: spacing.xs },
  sampleCount: { color: colors.muted, fontSize: 11, fontWeight: '700' },
  signalGroup: { gap: spacing.sm },
  signalHeading: { flexDirection: 'row', justifyContent: 'space-between' },
  signalLabel: { color: colors.text, fontSize: 12, fontWeight: '800' },
  signalUnit: { color: colors.muted, fontSize: 11 },
  signalBars: { alignItems: 'flex-end', borderBottomColor: colors.border, borderBottomWidth: 1, flexDirection: 'row', height: 64 },
  signalSlot: { alignItems: 'center', flex: 1, height: 64, justifyContent: 'flex-end', minWidth: 3 },
  signalBar: { borderRadius: 2, maxWidth: 8, width: '68%' },
  flameTimeline: { gap: spacing.sm },
  flameDots: { flexDirection: 'row', gap: 2 },
  flameDot: { borderRadius: 3, borderWidth: 1, flex: 1, height: 7 },
  emptyText: { color: colors.muted, fontSize: 13, paddingVertical: spacing.lg, textAlign: 'center' },
  sampleCard: { backgroundColor: colors.surfaceStrong, borderColor: colors.borderSoft, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  sampleHeader: { alignItems: 'baseline', flexDirection: 'row', justifyContent: 'space-between' },
  sampleTime: { color: colors.normal, fontSize: 14, fontWeight: '900' },
  sampleExplanation: { color: colors.muted, fontSize: 12, lineHeight: 18 },
  sampleValues: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  sampleValue: { color: colors.text, flexBasis: '47%', fontSize: 13, fontWeight: '700' },
  sampleCritical: { color: colors.critical },
  currentCard: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  currentHeading: { alignItems: 'center', flexDirection: 'row', gap: spacing.md, justifyContent: 'space-between' },
  currentConnection: { color: colors.success, fontSize: 11, fontWeight: '900' },
  currentOffline: { color: colors.muted },
  currentDescription: { color: colors.muted, fontSize: 12, lineHeight: 18 },
  alarmControlCard: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    gap: spacing.md,
    padding: spacing.lg,
  },
  alarmControlCritical: { borderColor: colors.critical },
  alarmControlSilenced: { borderColor: colors.warning },
  alarmControlHeader: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: spacing.md,
    justifyContent: 'space-between',
  },
  alarmControlTitle: { alignItems: 'center', flex: 1, flexDirection: 'row', gap: spacing.md },
  alarmControlCopy: { flex: 1, gap: spacing.xs },
  alarmMode: { color: colors.warning, fontSize: 10, fontWeight: '900' },
  alarmConnection: { color: colors.success, fontSize: 10, fontWeight: '900' },
  alarmControlDescription: { color: colors.text, fontSize: 13, lineHeight: 19 },
  alarmSilencedRow: {
    alignItems: 'center',
    backgroundColor: colors.surfaceStrong,
    borderRadius: radius.sm,
    flexDirection: 'row',
    gap: spacing.sm,
    padding: spacing.md,
  },
  alarmSilencedText: { color: colors.warning, flex: 1, fontSize: 12, fontWeight: '800', lineHeight: 18 },
  silenceAction: {
    alignItems: 'center',
    backgroundColor: colors.critical,
    borderRadius: radius.md,
    flexDirection: 'row',
    gap: spacing.sm,
    justifyContent: 'center',
    minHeight: 50,
  },
  silenceActionText: { color: colors.textStrong, fontSize: 14, fontWeight: '900' },
  alarmReadOnly: { color: colors.muted, fontSize: 12, lineHeight: 18 },
  actionCard: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  comment: { backgroundColor: colors.surfaceStrong, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, color: colors.textStrong, fontSize: 14, minHeight: 92, padding: spacing.md },
  actions: { flexDirection: 'row', gap: spacing.md },
  secondaryAction: { alignItems: 'center', borderColor: colors.warning, borderRadius: radius.md, borderWidth: 1, flex: 1, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 48 },
  secondaryActionText: { color: colors.warning, fontSize: 13, fontWeight: '900' },
  primaryAction: { alignItems: 'center', backgroundColor: colors.warning, borderRadius: radius.md, flex: 1, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 48 },
  primaryActionText: { color: colors.black, fontSize: 13, fontWeight: '900' },
  readOnly: { alignItems: 'flex-start', flexDirection: 'row', gap: spacing.sm },
  readOnlyText: { color: colors.muted, flex: 1, fontSize: 13, lineHeight: 19 },
  resolved: { alignItems: 'center', flexDirection: 'row', gap: spacing.sm },
  resolvedText: { color: colors.success, fontSize: 13, fontWeight: '800' },
  notice: { color: colors.success, fontSize: 13, fontWeight: '800' },
  errorPanel: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.critical, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  errorText: { color: colors.text, fontSize: 14, textAlign: 'center' },
  retryButton: { borderColor: colors.normal, borderRadius: radius.sm, borderWidth: 1, paddingHorizontal: spacing.lg, paddingVertical: spacing.sm },
  retryText: { color: colors.normal, fontSize: 13, fontWeight: '800' },
  pressed: { opacity: 0.7 },
});
