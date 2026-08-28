import Ionicons from '@expo/vector-icons/Ionicons';
import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { LiveLineChart } from '@/components/live-line-chart';
import { useAuth } from '@/context/auth-context';
import { useForegroundRefresh } from '@/hooks/use-foreground-refresh';
import {
  ApiError,
  getMobileDevices,
  getMobileLiveHistory,
  getMobileSummary,
} from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileDevice, MobileLiveSample } from '@/types/api';

function numeric(value: number | string | null | undefined): number | null {
  if (value === null || value === undefined || value === '') return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

function dateFromUtc(value: string): Date {
  return new Date(`${value.replace(' ', 'T')}Z`);
}

function timeLabel(value: string | null | undefined): string {
  if (!value) return '--';
  const date = dateFromUtc(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es-MX', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(date);
}

function toLiveSample(device: MobileDevice): MobileLiveSample | null {
  if (!device.ultima_lectura) return null;
  return {
    periodo: device.ultima_lectura,
    temperatura: device.temperatura,
    humedad: device.humedad,
    gas_raw: device.gas_raw,
    gas_porcentaje: device.gas_porcentaje,
    gas_detectado: device.gas_detectado,
    flama_detectada: device.flama_detectada,
    estado_general: device.estado_general,
  };
}

function mergeSample(samples: MobileLiveSample[], incoming: MobileLiveSample): MobileLiveSample[] {
  const merged = new Map(samples.map((sample) => [sample.periodo, sample]));
  merged.set(incoming.periodo, incoming);
  return [...merged.values()]
    .sort((left, right) => dateFromUtc(left.periodo).getTime() - dateFromUtc(right.periodo).getTime())
    .slice(-120);
}

function downsample(samples: MobileLiveSample[], maximumPoints = 60): MobileLiveSample[] {
  if (samples.length <= maximumPoints) return samples;
  const step = (samples.length - 1) / (maximumPoints - 1);
  return Array.from(
    { length: maximumPoints },
    (_, index) => samples[Math.round(index * step)],
  );
}

function ChartCard({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle: string;
  children: React.ReactNode;
}) {
  return (
    <View style={styles.chartCard}>
      <View style={styles.chartHeading}>
        <Text style={styles.chartTitle}>{title}</Text>
        <Text style={styles.chartSubtitle}>{subtitle}</Text>
      </View>
      {children}
    </View>
  );
}

export default function LiveChartsScreen() {
  const { token } = useAuth();
  const [devices, setDevices] = useState<MobileDevice[]>([]);
  const [selectedId, setSelectedId] = useState('');
  const [currentDevice, setCurrentDevice] = useState<MobileDevice | null>(null);
  const [samples, setSamples] = useState<MobileLiveSample[]>([]);
  const [threshold, setThreshold] = useState(1600);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [screenFocused, setScreenFocused] = useState(false);
  const pollingRef = useRef(false);

  const loadDevices = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    setError('');
    try {
      const response = await getMobileDevices(token);
      setDevices(response.dispositivos);
      setSelectedId((current) => (
        response.dispositivos.some((device) => device.id === current)
          ? current
          : response.dispositivos[0]?.id ?? ''
      ));
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No fue posible cargar los dispositivos.');
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    void loadDevices();
  }, [loadDevices]);

  const loadHistory = useCallback(async () => {
    if (!token || !selectedId) return;
    setRefreshing(true);
    setError('');
    try {
      const response = await getMobileLiveHistory(token, selectedId, 30);
      setSamples(response.muestras);
      setThreshold(Number(response.dispositivo.mq2_umbral_adc) || 1600);
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No fue posible cargar las gráficas.');
    } finally {
      setRefreshing(false);
    }
  }, [selectedId, token]);

  useEffect(() => {
    void loadHistory();
  }, [loadHistory]);

  useEffect(() => {
    setCurrentDevice(devices.find((device) => device.id === selectedId) ?? null);
  }, [devices, selectedId]);

  const refreshLive = useCallback(async () => {
    if (!token || !selectedId || pollingRef.current) return;
    pollingRef.current = true;
    try {
      const response = await getMobileSummary(token);
      const device = response.dispositivos.find((item) => item.id === selectedId);
      if (!device) return;
      setCurrentDevice(device);
      setDevices((current) => current.map((item) => (
        item.id === device.id ? { ...item, ...device } : item
      )));
      const sample = toLiveSample(device);
      if (device.conexion === 'ONLINE' && sample) {
        setSamples((current) => mergeSample(current, sample));
      }
      setError('');
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Se perdió la actualización en vivo.');
    } finally {
      pollingRef.current = false;
    }
  }, [selectedId, token]);

  useFocusEffect(useCallback(() => {
    setScreenFocused(true);
    if (selectedId) void refreshLive();
    return () => setScreenFocused(false);
  }, [refreshLive, selectedId]));

  useForegroundRefresh(
    refreshLive,
    5000,
    Boolean(token && selectedId && screenFocused),
  );

  const chartSamples = useMemo(() => downsample(samples), [samples]);
  const temperaturePoints = useMemo(() => chartSamples.map((sample) => ({
    label: timeLabel(sample.periodo),
    value: numeric(sample.temperatura),
  })), [chartSamples]);
  const humidityPoints = useMemo(() => chartSamples.map((sample) => ({
    label: timeLabel(sample.periodo),
    value: numeric(sample.humedad),
  })), [chartSamples]);
  const gasPoints = useMemo(() => chartSamples.map((sample) => ({
    label: timeLabel(sample.periodo),
    value: numeric(sample.gas_raw),
  })), [chartSamples]);
  const gasMaximum = Math.min(
    4095,
    Math.max(threshold * 1.2, ...gasPoints.map((point) => point.value ?? 0), 100),
  );
  const latest = samples.at(-1) ?? null;
  const offline = currentDevice?.conexion === 'OFFLINE';

  return (
    <AppScreen
      action={
        <Pressable
          accessibilityLabel="Recargar historial"
          disabled={refreshing}
          onPress={() => void loadHistory()}
          style={styles.iconButton}
        >
          {refreshing
            ? <ActivityIndicator color={colors.warning} />
            : <Ionicons color={colors.text} name="refresh" size={21} />}
        </Pressable>
      }
      eyebrow="MONITOREO"
      title="Gráficas en vivo"
    >
      <View style={styles.livePanel}>
        <View style={styles.liveHeading}>
          <View style={[styles.liveDot, offline && styles.offlineDot]} />
          <Text style={[styles.liveLabel, offline && styles.offlineText]}>
            {offline ? 'SIN CONEXIÓN' : 'EN VIVO · CADA 5 S'}
          </Text>
        </View>
        <Text style={styles.liveDescription}>
          {offline
            ? 'El dispositivo no envía datos recientes. Se conserva la última lectura disponible.'
            : 'Los datos nuevos se agregan mientras esta pantalla permanece abierta.'}
        </Text>
      </View>

      {devices.length > 1 ? (
        <View style={styles.selector}>
          <Text style={styles.selectorLabel}>Dispositivo</Text>
          <View style={styles.deviceChips}>
            {devices.map((device) => (
              <Pressable
                key={device.id}
                onPress={() => {
                  setSamples([]);
                  setSelectedId(device.id);
                }}
                style={[
                  styles.deviceChip,
                  selectedId === device.id && styles.deviceChipActive,
                ]}
              >
                <Text
                  style={[
                    styles.deviceChipText,
                    selectedId === device.id && styles.deviceChipTextActive,
                  ]}
                >
                  {device.id}
                </Text>
              </Pressable>
            ))}
          </View>
        </View>
      ) : null}

      {loading ? <ActivityIndicator color={colors.warning} size="large" /> : null}
      {error ? (
        <View style={styles.errorPanel}>
          <Ionicons color={colors.critical} name="alert-circle-outline" size={21} />
          <Text style={styles.errorText}>{error}</Text>
        </View>
      ) : null}

      {!loading && !devices.length ? (
        <View style={styles.emptyPanel}>
          <Ionicons color={colors.muted} name="analytics-outline" size={30} />
          <Text style={styles.emptyTitle}>No hay dispositivos disponibles</Text>
          <Text style={styles.emptyText}>Registra un ESP32 para comenzar a visualizar lecturas.</Text>
        </View>
      ) : null}

      {selectedId && samples.length ? (
        <>
          <View style={styles.deviceSummary}>
            <View>
              <Text style={styles.deviceId}>{selectedId}</Text>
              <Text style={styles.deviceLocation}>{currentDevice?.ubicacion ?? 'Ubicación sin registrar'}</Text>
            </View>
            <View style={styles.lastReading}>
              <Text style={styles.lastReadingLabel}>Último dato</Text>
              <Text style={styles.lastReadingValue}>{timeLabel(latest?.periodo)}</Text>
            </View>
          </View>

          <ChartCard title="Temperatura" subtitle="Escala en grados Celsius">
            <LiveLineChart
              color={colors.normal}
              points={temperaturePoints}
              valueFormatter={(value) => `${value.toFixed(1)} °C`}
            />
          </ChartCard>

          <ChartCard title="Humedad" subtitle="Escala independiente de 0 a 100%">
            <LiveLineChart
              color={colors.success}
              maximum={100}
              minimum={0}
              points={humidityPoints}
              valueFormatter={(value) => `${Math.round(value)}%`}
            />
          </ChartCard>

          <ChartCard title="Humo y gas · MQ-2" subtitle={`Línea roja: umbral de alarma (${threshold} ADC)`}>
            <LiveLineChart
              color={colors.warning}
              maximum={gasMaximum}
              minimum={0}
              points={gasPoints}
              threshold={threshold}
              thresholdLabel="UMBRAL"
              valueFormatter={(value) => `${Math.round(value)} ADC`}
            />
          </ChartCard>

          <View style={styles.flameCard}>
            <View style={styles.chartHeading}>
              <Text style={styles.chartTitle}>Detección de flama · KY-026</Text>
              <Text style={styles.chartSubtitle}>Rojo significa que el sensor detectó flama en esa lectura.</Text>
            </View>
            <View style={styles.flameTimeline}>
              {samples.slice(-60).map((sample, index) => {
                const detected = numeric(sample.flama_detectada) === 1;
                return (
                  <View
                    accessibilityLabel={`${detected ? 'Flama detectada' : 'Sin flama'} a las ${timeLabel(sample.periodo)}`}
                    key={`${sample.periodo}-${index}-flame`}
                    style={[
                      styles.flameSample,
                      detected && styles.flameDetected,
                    ]}
                  />
                );
              })}
            </View>
            <View style={styles.flameState}>
              <Ionicons
                color={numeric(latest?.flama_detectada) === 1 ? colors.critical : colors.success}
                name={numeric(latest?.flama_detectada) === 1 ? 'flame' : 'checkmark-circle-outline'}
                size={21}
              />
              <Text
                style={[
                  styles.flameStateText,
                  numeric(latest?.flama_detectada) === 1 && styles.flameStateCritical,
                ]}
              >
                {numeric(latest?.flama_detectada) === 1 ? 'Flama detectada ahora' : 'Sin flama en la última lectura'}
              </Text>
            </View>
          </View>

          <Text style={styles.chartHelp}>
            Toca cualquier punto de una gráfica para consultar su valor y hora exactos.
          </Text>
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
  livePanel: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    gap: spacing.sm,
    padding: spacing.lg,
  },
  liveHeading: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: spacing.sm,
  },
  liveDot: {
    backgroundColor: colors.success,
    borderRadius: 5,
    height: 9,
    width: 9,
  },
  offlineDot: {
    backgroundColor: colors.muted,
  },
  liveLabel: {
    color: colors.success,
    fontSize: 11,
    fontWeight: '900',
  },
  offlineText: {
    color: colors.muted,
  },
  liveDescription: {
    color: colors.muted,
    fontSize: 13,
    lineHeight: 19,
  },
  selector: {
    gap: spacing.sm,
  },
  selectorLabel: {
    color: colors.text,
    fontSize: 13,
    fontWeight: '800',
  },
  deviceChips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  deviceChip: {
    borderColor: colors.border,
    borderRadius: radius.sm,
    borderWidth: 1,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  deviceChipActive: {
    backgroundColor: colors.warning,
    borderColor: colors.warning,
  },
  deviceChipText: {
    color: colors.text,
    fontSize: 12,
    fontWeight: '800',
  },
  deviceChipTextActive: {
    color: colors.black,
  },
  errorPanel: {
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderColor: colors.critical,
    borderRadius: radius.md,
    borderWidth: 1,
    flexDirection: 'row',
    gap: spacing.sm,
    padding: spacing.md,
  },
  errorText: {
    color: colors.text,
    flex: 1,
    fontSize: 13,
    lineHeight: 19,
  },
  emptyPanel: {
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    gap: spacing.sm,
    padding: spacing.xl,
  },
  emptyTitle: {
    color: colors.textStrong,
    fontSize: 17,
    fontWeight: '900',
  },
  emptyText: {
    color: colors.muted,
    fontSize: 13,
    textAlign: 'center',
  },
  deviceSummary: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  deviceId: {
    color: colors.textStrong,
    fontSize: 17,
    fontWeight: '900',
  },
  deviceLocation: {
    color: colors.muted,
    fontSize: 12,
    marginTop: spacing.xs,
  },
  lastReading: {
    alignItems: 'flex-end',
  },
  lastReadingLabel: {
    color: colors.muted,
    fontSize: 10,
  },
  lastReadingValue: {
    color: colors.text,
    fontSize: 13,
    fontWeight: '800',
    marginTop: spacing.xs,
  },
  chartCard: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    padding: spacing.lg,
  },
  chartHeading: {
    gap: spacing.xs,
    marginBottom: spacing.md,
  },
  chartTitle: {
    color: colors.textStrong,
    fontSize: 16,
    fontWeight: '900',
  },
  chartSubtitle: {
    color: colors.muted,
    fontSize: 11,
    lineHeight: 16,
  },
  flameCard: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    padding: spacing.lg,
  },
  flameTimeline: {
    flexDirection: 'row',
    gap: 2,
    height: 18,
  },
  flameSample: {
    backgroundColor: colors.border,
    borderRadius: 2,
    flex: 1,
  },
  flameDetected: {
    backgroundColor: colors.critical,
  },
  flameState: {
    alignItems: 'center',
    borderTopColor: colors.borderSoft,
    borderTopWidth: 1,
    flexDirection: 'row',
    gap: spacing.sm,
    marginTop: spacing.md,
    paddingTop: spacing.md,
  },
  flameStateText: {
    color: colors.success,
    fontSize: 13,
    fontWeight: '800',
  },
  flameStateCritical: {
    color: colors.critical,
  },
  chartHelp: {
    color: colors.muted,
    fontSize: 12,
    lineHeight: 18,
    paddingBottom: spacing.md,
    textAlign: 'center',
  },
});
