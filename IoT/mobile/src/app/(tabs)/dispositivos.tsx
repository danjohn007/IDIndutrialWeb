import Ionicons from '@expo/vector-icons/Ionicons';
import { useRouter, type Href } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { StatusBadge } from '@/components/status-badge';
import { useAuth } from '@/context/auth-context';
import { useForegroundRefresh } from '@/hooks/use-foreground-refresh';
import { ApiError, controlMobileShelly, getMobileDevices } from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { GeneralState, MobileDevice, MobileShellyActuator } from '@/types/api';

function numeric(value: number | string | null | undefined): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function reading(value: number | string | null | undefined, suffix: string): string {
  if (value === null || value === undefined || value === '') return '--';
  return `${Number(value).toFixed(1)}${suffix}`;
}

function dateLabel(value: string | null | undefined): string {
  if (!value) return 'Sin registro';
  const date = new Date(`${value.replace(' ', 'T')}Z`);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    month: 'short',
  }).format(date);
}

function durationLabel(value: number | string | null | undefined): string {
  const seconds = Math.max(0, numeric(value));
  if (!seconds) return 'Completo';
  const minutes = Math.ceil(seconds / 60);
  return `${minutes} min restantes`;
}

function healthColor(value: string | null | undefined): string {
  const normalized = String(value ?? '').toUpperCase();
  if (normalized === 'OK') return colors.success;
  if (normalized === 'OFFLINE') return colors.muted;
  if (normalized === 'CALENTANDO' || normalized === 'INICIANDO') return colors.warning;
  if (normalized === 'FALLO') return colors.critical;
  return colors.muted;
}

function healthLabel(value: string | null | undefined): string {
  const normalized = String(value ?? '').toUpperCase();
  if (normalized === 'OK') return 'Operativo';
  if (normalized === 'OFFLINE') return 'Offline';
  if (normalized === 'CALENTANDO') return 'Calentando';
  if (normalized === 'INICIANDO') return 'Iniciando';
  if (normalized === 'FALLO') return 'Fallo';
  if (normalized === 'REVISAR') return 'Revisar';
  return 'Sin datos';
}

function SensorHealth({ label, health }: { label: string; health: string | null | undefined }) {
  const color = healthColor(health);
  return (
    <View style={styles.healthItem}>
      <Text style={styles.healthName}>{label}</Text>
      <Text style={[styles.healthValue, { color }]}>{healthLabel(health)}</Text>
    </View>
  );
}

function DeviceCard({ device }: { device: MobileDevice }) {
  const offline = device.conexion === 'OFFLINE';
  const state: GeneralState = offline ? 'OFFLINE' : (device.estado_general ?? 'NORMAL');
  const gas = offline ? null : device.gas_raw;
  const threshold = Math.max(1, numeric(device.mq2_umbral_adc));
  const gasPercent = gas === null || gas === undefined
    ? 0
    : Math.min(100, (numeric(gas) / threshold) * 100);
  const gasColor = numeric(gas) >= threshold ? colors.critical : colors.warning;
  const alarmLatched = !offline && numeric(device.alarma_enclavada) === 1;
  const alarmSilenced = alarmLatched && numeric(device.alarma_silenciada) === 1;
  const dangerActive = !offline && numeric(device.peligro_activo) === 1;

  return (
    <View style={[styles.deviceCard, offline && styles.deviceOffline]}>
      <View style={styles.deviceHeader}>
        <View style={styles.deviceIdentity}>
          <Text style={styles.deviceId}>{device.id}</Text>
          <Text style={styles.location}>{device.ubicacion}</Text>
        </View>
        <StatusBadge state={state} />
      </View>

      {alarmLatched ? (
        <View style={[
          styles.alarmState,
          alarmSilenced ? styles.alarmStateSilenced : styles.alarmStateSounding,
        ]}>
          <Ionicons
            color={alarmSilenced ? colors.warning : colors.critical}
            name={alarmSilenced ? 'volume-mute-outline' : 'notifications-outline'}
            size={21}
          />
          <View style={styles.alarmStateCopy}>
            <Text style={[
              styles.alarmStateTitle,
              { color: alarmSilenced ? colors.warning : colors.critical },
            ]}>
              {alarmSilenced ? 'Buzzer silenciado' : 'Buzzer intermitente activo'}
            </Text>
            <Text style={styles.alarmStateDescription}>
              {alarmSilenced
                ? dangerActive
                  ? 'El peligro continúa. Restablecimiento físico bloqueado.'
                  : 'Lecturas seguras. Falta completar la revisión con el botón físico.'
                : 'Abre la alerta correspondiente para silenciar desde la app.'}
            </Text>
          </View>
        </View>
      ) : null}

      <View style={styles.readings}>
        <View style={styles.reading}>
          <Ionicons color={colors.normal} name="thermometer-outline" size={18} />
          <Text style={styles.readingLabel}>Temperatura</Text>
          <Text style={styles.readingValue}>{offline ? '--' : reading(device.temperatura, ' °C')}</Text>
        </View>
        <View style={styles.reading}>
          <Ionicons color={colors.success} name="water-outline" size={18} />
          <Text style={styles.readingLabel}>Humedad</Text>
          <Text style={styles.readingValue}>{offline ? '--' : reading(device.humedad, '%')}</Text>
        </View>
      </View>

      <View style={styles.mq2Panel}>
        <View style={styles.mq2Header}>
          <View>
            <Text style={styles.sensorModel}>MQ-2</Text>
            <Text style={styles.sensorTitle}>Humo y gas</Text>
          </View>
          <Text style={[styles.sensorStatus, { color: offline ? colors.muted : healthColor(device.salud_mq2) }]}>
            {offline ? 'OFFLINE' : healthLabel(device.salud_mq2).toUpperCase()}
          </Text>
        </View>
        <View style={styles.gaugeReadout}>
          <Text style={styles.gaugeValue}>{offline ? '--' : `${numeric(gas)} ADC`}</Text>
          <Text style={styles.gaugeMeta}>Umbral {threshold} ADC</Text>
        </View>
        <View style={styles.gaugeTrack}>
          <View style={[styles.gaugeFill, { backgroundColor: gasColor, width: `${gasPercent}%` }]} />
        </View>
        <View style={styles.gaugeScale}>
          <Text style={styles.gaugeScaleText}>0</Text>
          <Text style={styles.gaugeScaleText}>{offline ? 'Sin lectura actual' : `${Math.round(gasPercent)}% del umbral`}</Text>
          <Text style={styles.gaugeScaleText}>{threshold}</Text>
        </View>
        <View style={styles.mq2Details}>
          <Text style={styles.detailText}>Calentamiento: {offline ? '--' : durationLabel(device.mq2_calentamiento_restante_s)}</Text>
          <Text style={styles.detailText}>Calibración: {dateLabel(device.mq2_ultima_calibracion)}</Text>
        </View>
      </View>

      <View style={styles.flameRow}>
        <View style={styles.flameCopy}>
          <Ionicons color={offline ? colors.muted : colors.critical} name="flame-outline" size={20} />
          <View>
            <Text style={styles.sensorModel}>KY-026</Text>
            <Text style={styles.sensorTitle}>Detección de flama</Text>
          </View>
        </View>
        <Text style={[styles.flameValue, { color: offline ? colors.muted : numeric(device.flama_detectada) === 1 ? colors.critical : colors.success }]}>
          {offline ? 'Sin lectura' : numeric(device.flama_detectada) === 1 ? 'Detectada' : 'Sin detección'}
        </Text>
      </View>

      <View style={styles.healthGrid}>
        <SensorHealth health={offline ? 'OFFLINE' : device.salud_dht} label="DHT11" />
        <SensorHealth health={offline ? 'OFFLINE' : device.salud_mq2} label="MQ-2" />
        <SensorHealth health={offline ? 'OFFLINE' : device.salud_flama} label="KY-026" />
      </View>
      <View style={styles.footer}>
        <Text style={styles.footerText}>Última lectura: {dateLabel(device.ultima_lectura)}</Text>
        {device.ultima_alerta ? <Text style={styles.footerText}>Última alerta: {dateLabel(device.ultima_alerta)}</Text> : null}
      </View>
    </View>
  );
}

function ShellyCard({
  actuator,
  canControl,
  busy,
  onControl,
  onOpen,
}: {
  actuator: MobileShellyActuator;
  canControl: boolean;
  busy: boolean;
  onControl: (actuator: MobileShellyActuator, action: 'ENCENDER' | 'APAGAR') => void;
  onOpen: (actuator: MobileShellyActuator) => void;
}) {
  const online = actuator.conexion === 'ONLINE';
  const outputOn = numeric(actuator.salida_encendida) === 1;
  const connectionColor = online ? colors.success : actuator.conexion === 'DESACTUALIZADO' ? colors.warning : colors.muted;
  return (
    <View style={[styles.shellyCard, !online && styles.deviceOffline, outputOn && styles.shellyCardOn]}>
      <View style={styles.deviceHeader}>
        <View style={styles.deviceIdentity}>
          <Text style={styles.sensorModel}>SHELLY · CANAL {actuator.canal}</Text>
          <Text style={styles.deviceId}>{actuator.id}</Text>
          <Text style={styles.location}>{actuator.ubicacion}</Text>
        </View>
        <Text style={[styles.shellyConnection, { color: connectionColor }]}>
          {actuator.conexion.replace('_', ' ')}
        </Text>
      </View>
      <View style={styles.shellyOutput}>
        <Ionicons color={outputOn ? colors.warning : colors.muted} name="radio-outline" size={25} />
        <View style={styles.shellyOutputCopy}>
          <Text style={styles.readingLabel}>{actuator.funcion}</Text>
          <Text style={[styles.shellyOutputValue, { color: outputOn ? colors.warning : colors.textStrong }]}>
            {outputOn ? 'ENCENDIDA' : 'APAGADA'}
          </Text>
        </View>
      </View>
      <View style={styles.shellyMetrics}>
        <View style={styles.shellyMetric}><Text style={styles.readingLabel}>Potencia</Text><Text style={styles.shellyMetricValue}>{actuator.potencia_w == null ? '--' : `${numeric(actuator.potencia_w).toFixed(1)} W`}</Text></View>
        <View style={styles.shellyMetric}><Text style={styles.readingLabel}>Voltaje</Text><Text style={styles.shellyMetricValue}>{actuator.voltaje_v == null ? '--' : `${numeric(actuator.voltaje_v).toFixed(1)} V`}</Text></View>
      </View>
      <Text style={styles.detailText}>ESP32 asociado: {actuator.dispositivo_vinculado_id ?? 'Sin asociar'}</Text>
      <Text style={styles.detailText}>Sincronizado: {dateLabel(actuator.sincronizado_en)}</Text>
      {actuator.ultimo_error ? <Text style={styles.shellyError}>{actuator.ultimo_error}</Text> : null}
      <Pressable onPress={() => onOpen(actuator)} style={styles.detailButton}>
        <Text style={styles.detailButtonText}>Ver configuracion y actividad</Text>
        <Ionicons color={colors.normal} name="chevron-forward" size={18} />
      </Pressable>
      {canControl ? (
        <Pressable
          disabled={busy}
          onPress={() => onControl(actuator, outputOn ? 'APAGAR' : 'ENCENDER')}
          style={[styles.shellyButton, outputOn && styles.shellyButtonOff, busy && styles.buttonDisabled]}
        >
          {busy ? <ActivityIndicator color={outputOn ? colors.critical : colors.black} size="small" /> : null}
          <Ionicons color={outputOn ? colors.critical : colors.black} name={outputOn ? 'stop-circle-outline' : 'power-outline'} size={19} />
          <Text style={[styles.shellyButtonText, outputOn && styles.shellyButtonTextOff]}>{outputOn ? 'Apagar' : 'Encender'}</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

export default function DevicesScreen() {
  const router = useRouter();
  const { token, user } = useAuth();
  const [devices, setDevices] = useState<MobileDevice[]>([]);
  const [actuators, setActuators] = useState<MobileShellyActuator[]>([]);
  const [busyActuator, setBusyActuator] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [section, setSection] = useState<'ESP32' | 'SHELLY'>('ESP32');

  const load = useCallback(async (refresh = false, quiet = false) => {
    if (!token) return;
    if (refresh) setRefreshing(true);
    if (!refresh && !quiet) setLoading(true);
    if (!quiet) setError('');
    try {
      const response = await getMobileDevices(token, section === 'SHELLY');
      setDevices(response.dispositivos);
      setActuators(response.actuadores_shelly ?? []);
    } catch (caught) {
      if (!quiet) {
        setError(caught instanceof ApiError ? caught.message : 'No fue posible cargar los dispositivos.');
      }
    } finally {
      if (!quiet) setLoading(false);
      if (refresh) setRefreshing(false);
    }
  }, [section, token]);

  useEffect(() => {
    void load();
  }, [load]);

  useForegroundRefresh(
    () => void load(false, true),
    10000,
    section === 'SHELLY',
  );

  const online = devices.filter((device) => device.conexion === 'ONLINE').length;
  const shellyOnline = actuators.filter((actuator) => actuator.conexion === 'ONLINE').length;
  const canControl = user?.rol === 'ADMIN' || user?.rol === 'OPERADOR';

  const runShellyCommand = useCallback((actuator: MobileShellyActuator, action: 'ENCENDER' | 'APAGAR') => {
    if (!token) return;
    const execute = async () => {
      setBusyActuator(actuator.id);
      try {
        const result = await controlMobileShelly(token, actuator.id, action);
        if (!result.aplicado) Alert.alert('Orden en proceso', result.error ?? 'El servidor volvera a intentarlo.');
        await load(true);
      } catch (caught) {
        Alert.alert('No fue posible controlar el Shelly', caught instanceof ApiError ? caught.message : 'Intenta nuevamente.');
      } finally {
        setBusyActuator('');
      }
    };
    void execute();
  }, [load, token]);

  return (
    <AppScreen
      eyebrow="EQUIPOS"
      title="Dispositivos"
      action={
        <View style={styles.headerActions}>
          {section === 'SHELLY' && user?.rol === 'ADMIN' ? (
            <Pressable accessibilityLabel="Agregar Shelly" onPress={() => router.push('/shelly/formulario' as Href)} style={styles.addButton}>
              <Ionicons color={colors.black} name="add" size={22} />
            </Pressable>
          ) : null}
          <Pressable accessibilityLabel="Actualizar dispositivos" onPress={() => void load(true)} style={styles.refreshButton}>
            <Ionicons color={colors.text} name="refresh" size={21} />
          </Pressable>
        </View>
      }
      scrollProps={{
        refreshControl: (
          <RefreshControl
            colors={[colors.warning]}
            onRefresh={() => void load(true)}
            refreshing={refreshing}
            tintColor={colors.warning}
          />
        ),
      }}
    >
      <View style={styles.summary}>
        <View>
          <Text style={styles.summaryLabel}>SALUD DEL SISTEMA</Text>
          <Text style={styles.summaryTitle}>
            {section === 'ESP32'
              ? `${online}/${devices.length} ESP32 en linea`
              : `${shellyOnline}/${actuators.length} Shelly en linea`}
          </Text>
        </View>
        <Ionicons
          color={(section === 'ESP32'
            ? online === devices.length && devices.length
            : shellyOnline === actuators.length && actuators.length) ? colors.success : colors.warning}
          name={section === 'ESP32' ? 'hardware-chip-outline' : 'flash-outline'}
          size={29}
        />
      </View>

      <View style={styles.segmented}>
        <Pressable onPress={() => setSection('ESP32')} style={[styles.segment, section === 'ESP32' && styles.segmentSelected]}>
          <Ionicons color={section === 'ESP32' ? colors.black : colors.muted} name="hardware-chip-outline" size={18} />
          <Text style={[styles.segmentText, section === 'ESP32' && styles.segmentTextSelected]}>Sensores ESP32</Text>
        </Pressable>
        <Pressable onPress={() => setSection('SHELLY')} style={[styles.segment, section === 'SHELLY' && styles.segmentSelected]}>
          <Ionicons color={section === 'SHELLY' ? colors.black : colors.muted} name="flash-outline" size={18} />
          <Text style={[styles.segmentText, section === 'SHELLY' && styles.segmentTextSelected]}>Equipos Shelly</Text>
        </Pressable>
      </View>

      {loading ? <ActivityIndicator color={colors.warning} size="large" /> : null}
      {error ? (
        <View style={styles.errorPanel}>
          <Ionicons color={colors.critical} name="cloud-offline-outline" size={25} />
          <Text style={styles.errorText}>{error}</Text>
          <Pressable onPress={() => void load()} style={styles.retryButton}>
            <Text style={styles.retryText}>Reintentar</Text>
          </Pressable>
        </View>
      ) : null}
      {!loading && !error && section === 'ESP32' && !devices.length ? (
        <View style={styles.emptyPanel}>
          <Ionicons color={colors.muted} name="hardware-chip-outline" size={28} />
          <Text style={styles.emptyText}>No hay dispositivos disponibles.</Text>
        </View>
      ) : null}
      {section === 'ESP32' ? devices.map((device) => <DeviceCard device={device} key={device.id} />) : null}
      {section === 'SHELLY' ? (
        <View style={styles.sectionHeading}>
          <Text style={styles.summaryLabel}>ACTUADORES Y CARGAS</Text>
          <Text style={styles.sectionTitle}>Equipos Shelly</Text>
          <Text style={styles.sectionDescription}>Control, configuracion electrica y actividad por canal.</Text>
        </View>
      ) : null}
      {!loading && !error && section === 'SHELLY' && !actuators.length ? (
        <View style={styles.emptyPanel}>
          <Ionicons color={colors.muted} name="flash-outline" size={28} />
          <Text style={styles.emptyText}>No hay equipos Shelly registrados.</Text>
          {user?.rol === 'ADMIN' ? (
            <Pressable onPress={() => router.push('/shelly/formulario' as Href)} style={styles.emptyAddButton}>
              <Ionicons color={colors.black} name="add" size={18} />
              <Text style={styles.emptyAddText}>Agregar Shelly</Text>
            </Pressable>
          ) : null}
        </View>
      ) : null}
      {section === 'SHELLY' ? actuators.map((actuator) => (
        <ShellyCard
          actuator={actuator}
          busy={busyActuator === actuator.id}
          canControl={canControl}
          key={actuator.id}
          onControl={runShellyCommand}
          onOpen={(item) => router.push(`/shelly/${encodeURIComponent(item.id)}` as Href)}
        />
      )) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  headerActions: { flexDirection: 'row', gap: spacing.sm },
  refreshButton: { alignItems: 'center', borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, height: 44, justifyContent: 'center', width: 44 },
  addButton: { alignItems: 'center', backgroundColor: colors.warning, borderRadius: radius.md, height: 44, justifyContent: 'center', width: 44 },
  summary: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, flexDirection: 'row', justifyContent: 'space-between', padding: spacing.lg },
  summaryLabel: { color: colors.normal, fontSize: 11, fontWeight: '900' },
  summaryTitle: { color: colors.textStrong, fontSize: 20, fontWeight: '900', marginTop: spacing.xs },
  segmented: { backgroundColor: colors.surfaceStrong, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, flexDirection: 'row', gap: spacing.xs, padding: spacing.xs },
  segment: { alignItems: 'center', borderRadius: radius.sm, flex: 1, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 44, paddingHorizontal: spacing.sm },
  segmentSelected: { backgroundColor: colors.warning },
  segmentText: { color: colors.muted, fontSize: 12, fontWeight: '800' },
  segmentTextSelected: { color: colors.black },
  deviceCard: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  deviceOffline: { borderColor: colors.borderSoft, opacity: 0.78 },
  deviceHeader: { alignItems: 'flex-start', flexDirection: 'row', justifyContent: 'space-between' },
  deviceIdentity: { flex: 1, gap: spacing.xs, paddingRight: spacing.sm },
  deviceId: { color: colors.textStrong, fontSize: 19, fontWeight: '900' },
  location: { color: colors.muted, fontSize: 14 },
  alarmState: { alignItems: 'flex-start', borderRadius: radius.sm, borderWidth: 1, flexDirection: 'row', gap: spacing.sm, padding: spacing.md },
  alarmStateSounding: { backgroundColor: colors.surfaceStrong, borderColor: colors.critical },
  alarmStateSilenced: { backgroundColor: colors.surfaceStrong, borderColor: colors.warning },
  alarmStateCopy: { flex: 1, gap: spacing.xs },
  alarmStateTitle: { fontSize: 13, fontWeight: '900' },
  alarmStateDescription: { color: colors.text, fontSize: 12, lineHeight: 18 },
  readings: { flexDirection: 'row', gap: spacing.md },
  reading: { backgroundColor: colors.surfaceStrong, borderRadius: radius.sm, flex: 1, gap: spacing.xs, padding: spacing.md },
  readingLabel: { color: colors.muted, fontSize: 12 },
  readingValue: { color: colors.textStrong, fontSize: 19, fontWeight: '900' },
  mq2Panel: { backgroundColor: colors.surfaceStrong, borderColor: colors.borderSoft, borderRadius: radius.sm, borderWidth: 1, gap: spacing.sm, padding: spacing.md },
  mq2Header: { alignItems: 'flex-start', flexDirection: 'row', justifyContent: 'space-between' },
  sensorModel: { color: colors.normal, fontSize: 11, fontWeight: '900' },
  sensorTitle: { color: colors.textStrong, fontSize: 15, fontWeight: '800', marginTop: spacing.xs },
  sensorStatus: { fontSize: 11, fontWeight: '900' },
  gaugeReadout: { alignItems: 'baseline', flexDirection: 'row', gap: spacing.sm, marginTop: spacing.xs },
  gaugeValue: { color: colors.textStrong, fontSize: 22, fontWeight: '900' },
  gaugeMeta: { color: colors.muted, fontSize: 12 },
  gaugeTrack: { backgroundColor: colors.border, borderRadius: radius.sm, height: 10, overflow: 'hidden' },
  gaugeFill: { borderRadius: radius.sm, height: '100%', minWidth: 0 },
  gaugeScale: { flexDirection: 'row', justifyContent: 'space-between' },
  gaugeScaleText: { color: colors.muted, fontSize: 11 },
  mq2Details: { borderTopColor: colors.borderSoft, borderTopWidth: 1, gap: spacing.xs, marginTop: spacing.xs, paddingTop: spacing.sm },
  detailText: { color: colors.muted, fontSize: 12 },
  flameRow: { alignItems: 'center', borderColor: colors.borderSoft, borderRadius: radius.sm, borderWidth: 1, flexDirection: 'row', justifyContent: 'space-between', padding: spacing.md },
  flameCopy: { alignItems: 'center', flexDirection: 'row', gap: spacing.sm },
  flameValue: { fontSize: 13, fontWeight: '900', textAlign: 'right' },
  healthGrid: { borderTopColor: colors.borderSoft, borderTopWidth: 1, flexDirection: 'row', gap: spacing.sm, paddingTop: spacing.md },
  healthItem: { flex: 1, gap: spacing.xs },
  healthName: { color: colors.muted, fontSize: 11, fontWeight: '700' },
  healthValue: { fontSize: 12, fontWeight: '900' },
  footer: { borderTopColor: colors.borderSoft, borderTopWidth: 1, gap: spacing.xs, paddingTop: spacing.md },
  footerText: { color: colors.muted, fontSize: 12 },
  sectionHeading: { gap: spacing.xs, marginTop: spacing.sm },
  sectionTitle: { color: colors.textStrong, fontSize: 20, fontWeight: '900' },
  sectionDescription: { color: colors.muted, fontSize: 12, lineHeight: 18 },
  shellyCard: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  shellyCardOn: { borderColor: colors.warning },
  shellyConnection: { fontSize: 11, fontWeight: '900' },
  shellyOutput: { alignItems: 'center', backgroundColor: colors.surfaceStrong, borderRadius: radius.sm, flexDirection: 'row', gap: spacing.md, padding: spacing.md },
  shellyOutputCopy: { flex: 1, gap: spacing.xs },
  shellyOutputValue: { fontSize: 18, fontWeight: '900' },
  shellyMetrics: { flexDirection: 'row', gap: spacing.md },
  shellyMetric: { backgroundColor: colors.surfaceStrong, borderRadius: radius.sm, flex: 1, gap: spacing.xs, padding: spacing.md },
  shellyMetricValue: { color: colors.textStrong, fontSize: 16, fontWeight: '900' },
  shellyError: { color: colors.critical, fontSize: 12, lineHeight: 18 },
  detailButton: { alignItems: 'center', borderBottomColor: colors.borderSoft, borderBottomWidth: 1, borderTopColor: colors.borderSoft, borderTopWidth: 1, flexDirection: 'row', justifyContent: 'space-between', minHeight: 44 },
  detailButtonText: { color: colors.normal, fontSize: 12, fontWeight: '800' },
  shellyButton: { alignItems: 'center', backgroundColor: colors.warning, borderColor: colors.warning, borderRadius: radius.sm, borderWidth: 1, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 46, paddingHorizontal: spacing.lg },
  shellyButtonOff: { backgroundColor: 'transparent', borderColor: colors.critical },
  shellyButtonText: { color: colors.black, fontSize: 14, fontWeight: '900' },
  shellyButtonTextOff: { color: colors.critical },
  buttonDisabled: { opacity: 0.55 },
  errorPanel: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.critical, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  errorText: { color: colors.text, fontSize: 14, textAlign: 'center' },
  retryButton: { borderColor: colors.normal, borderRadius: radius.sm, borderWidth: 1, paddingHorizontal: spacing.lg, paddingVertical: spacing.sm },
  retryText: { color: colors.normal, fontSize: 13, fontWeight: '800' },
  emptyPanel: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.sm, padding: spacing.xl },
  emptyText: { color: colors.muted, fontSize: 14 },
  emptyAddButton: { alignItems: 'center', backgroundColor: colors.warning, borderRadius: radius.sm, flexDirection: 'row', gap: spacing.sm, minHeight: 44, paddingHorizontal: spacing.lg },
  emptyAddText: { color: colors.black, fontSize: 13, fontWeight: '900' },
});
