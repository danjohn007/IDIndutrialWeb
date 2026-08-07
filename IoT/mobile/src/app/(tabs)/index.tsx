import Ionicons from '@expo/vector-icons/Ionicons';
import { useRouter } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Image,
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
import { ApiError, getMobileSummary } from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileDevice, MobileSummary } from '@/types/api';

function numeric(value: number | string | null | undefined): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function reading(value: number | string | null, suffix: string): string {
  if (value === null || value === '') return '--';
  const parsed = Number(value);
  return Number.isFinite(parsed) ? `${parsed.toFixed(1)}${suffix}` : '--';
}

function DeviceCard({ device }: { device: MobileDevice }) {
  const isOffline = device.conexion === 'OFFLINE';
  const gas = isOffline ? 0 : numeric(device.gas_raw);
  const threshold = Math.max(1, numeric(device.mq2_umbral_adc));
  const gasPercent = Math.min(100, (gas / threshold) * 100);

  return (
    <View style={styles.deviceCard}>
      <View
        style={[
          styles.deviceRail,
          { backgroundColor: isOffline ? colors.muted : colors.normal },
        ]}
      />
      <View style={styles.deviceHeader}>
        <View style={styles.deviceIdentity}>
          <Text style={styles.deviceId}>{device.id}</Text>
          <Text style={styles.deviceLocation}>{device.ubicacion}</Text>
        </View>
        <Text
          style={[
            styles.connection,
            { color: device.conexion === 'ONLINE' ? colors.normal : colors.muted },
          ]}
        >
          {device.conexion}
        </Text>
      </View>
      <View style={styles.readings}>
        <View style={styles.reading}>
          <Text style={styles.readingLabel}>Temperatura</Text>
          <Text style={styles.readingValue}>{reading(isOffline ? null : device.temperatura, ' C')}</Text>
        </View>
        <View style={styles.reading}>
          <Text style={styles.readingLabel}>Humedad</Text>
          <Text style={styles.readingValue}>{reading(isOffline ? null : device.humedad, '%')}</Text>
        </View>
      </View>
      <View style={styles.sensorRow}>
        <View style={styles.sensorCopy}>
          <Text style={styles.sensorName}>MQ-2 Humo/Gas</Text>
          <Text style={styles.sensorValue}>{isOffline ? '--' : `${gas} ADC`}</Text>
        </View>
        <Text style={styles.sensorHealth}>{isOffline ? 'OFFLINE' : (device.salud_mq2 ?? 'SIN DATOS')}</Text>
      </View>
      <View style={styles.progressTrack}>
        <View
          style={[
            styles.progressValue,
            {
              backgroundColor: gas >= threshold ? colors.critical : colors.warning,
              width: `${gasPercent}%`,
            },
          ]}
        />
      </View>
      <View style={styles.sensorRow}>
        <View style={styles.sensorCopy}>
          <Text style={styles.sensorName}>KY-026 Flama</Text>
          <Text style={styles.sensorValue}>
            {isOffline
              ? 'Sin lectura reciente'
              : numeric(device.flama_detectada) === 1 ? 'Detectada' : 'Sin deteccion'}
          </Text>
        </View>
        <Text
          style={[
            styles.sensorHealth,
            !isOffline && numeric(device.flama_detectada) === 1 && { color: colors.critical },
          ]}
        >
          {isOffline ? 'OFFLINE' : (device.salud_flama ?? 'SIN DATOS')}
        </Text>
      </View>
    </View>
  );
}

export default function MonitorScreen() {
  const { token, user } = useAuth();
  const router = useRouter();
  const [data, setData] = useState<MobileSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const requestInFlight = useRef(false);

  const load = useCallback(async (
    mode: 'initial' | 'refresh' | 'silent' = 'initial',
  ) => {
    if (!token || requestInFlight.current) return;
    requestInFlight.current = true;
    if (mode === 'refresh') setRefreshing(true);
    if (mode === 'initial') setLoading(true);
    if (mode !== 'silent') setError('');
    try {
      setData(await getMobileSummary(token));
    } catch (caught) {
      if (mode !== 'silent') {
        setError(
          caught instanceof ApiError
            ? caught.message
            : 'No fue posible cargar el monitoreo.',
        );
      }
    } finally {
      requestInFlight.current = false;
      setLoading(false);
      setRefreshing(false);
    }
  }, [token]);

  useEffect(() => {
    void load();
  }, [load]);

  useForegroundRefresh(
    useCallback(() => {
      void load('silent');
    }, [load]),
    5000,
    Boolean(token),
  );

  const state = data?.resumen.estado_general ?? 'NORMAL';

  return (
    <AppScreen
      eyebrow="ID INDUSTRIAL"
      title="Monitoreo"
      action={
        <Image
          source={require('../../../assets/logo-id-industrial.png')}
          resizeMode="contain"
          style={styles.headerLogo}
        />
      }
      scrollProps={{
        refreshControl: (
          <RefreshControl
            colors={[colors.warning]}
            onRefresh={() => void load('refresh')}
            refreshing={refreshing}
            tintColor={colors.warning}
          />
        ),
      }}
    >
      <View style={[styles.statePanel, { borderColor: state === 'ALARMA' ? colors.critical : state === 'ALERTA' ? colors.warning : state === 'OFFLINE' ? colors.muted : colors.border }]}>
        <View style={[styles.stateRail, { backgroundColor: state === 'ALARMA' ? colors.critical : state === 'ALERTA' ? colors.warning : state === 'OFFLINE' ? colors.muted : colors.normal }]} />
        <View style={styles.stateCopy}>
          <Text style={styles.sectionLabel}>ESTADO GENERAL</Text>
          <Text style={styles.stateTitle}>{state}</Text>
          <Text style={styles.stateDescription}>
            {state === 'ALARMA'
              ? 'Evento critico detectado. Revisa el origen de inmediato.'
              : state === 'ALERTA'
                ? 'Existe una condicion que requiere seguimiento.'
                : state === 'OFFLINE'
                  ? 'No hay lectura reciente del ESP32. Revisa su conexion o alimentacion.'
                  : 'Los dispositivos reportan condiciones normales.'}
          </Text>
        </View>
        <StatusBadge state={state} />
      </View>

      {user?.rol === 'ADMIN' ? (
        <Pressable
          accessibilityHint="Abre las solicitudes recibidas desde el sitio web"
          accessibilityLabel="Ver cotizaciones"
          accessibilityRole="button"
          onPress={() => router.push('/cotizaciones')}
          style={({ pressed }) => [styles.quotesEntry, pressed && styles.quotesEntryPressed]}
        >
          <View style={styles.quotesIcon}>
            <Ionicons color={colors.warning} name="document-text-outline" size={24} />
          </View>
          <View style={styles.quotesCopy}>
            <Text style={styles.quotesEyebrow}>CRM MOVIL</Text>
            <Text style={styles.quotesTitle}>Cotizaciones</Text>
            <Text style={styles.quotesDescription}>Consulta solicitudes y abre su detalle sin salir de la app.</Text>
          </View>
          <Ionicons color={colors.muted} name="chevron-forward" size={21} />
        </Pressable>
      ) : null}

      {loading ? (
        <ActivityIndicator color={colors.warning} size="large" />
      ) : error ? (
        <View style={styles.errorPanel}>
          <Ionicons color={colors.critical} name="cloud-offline-outline" size={26} />
          <Text style={styles.errorText}>{error}</Text>
          <Pressable onPress={() => void load('initial')} style={styles.retryButton}>
            <Text style={styles.retryText}>Reintentar</Text>
          </Pressable>
        </View>
      ) : data ? (
        <>
          <View style={styles.kpiGrid}>
            <View style={styles.kpi}>
              <View style={styles.kpiHeader}><Ionicons color={colors.normal} name="radio-outline" size={17} /><Text style={styles.kpiLabel}>En linea</Text></View>
              <Text style={styles.kpiValue}>
                {numeric(data.resumen.dispositivos_online)}/
                {numeric(data.resumen.dispositivos_total)}
              </Text>
            </View>
            <View style={styles.kpi}>
              <View style={styles.kpiHeader}><Ionicons color={colors.critical} name="warning-outline" size={17} /><Text style={styles.kpiLabel}>Criticas abiertas</Text></View>
              <Text
                style={[
                  styles.kpiValue,
                  numeric(data.resumen.criticas_abiertas) > 0 && {
                    color: colors.critical,
                  },
                ]}
              >
                {numeric(data.resumen.criticas_abiertas)}
              </Text>
            </View>
            <View style={styles.kpi}>
              <View style={styles.kpiHeader}><Ionicons color={colors.warning} name="notifications-outline" size={17} /><Text style={styles.kpiLabel}>Alertas del mes</Text></View>
              <Text style={styles.kpiValue}>{numeric(data.resumen.alertas_mes)}</Text>
            </View>
            <View style={styles.kpi}>
              <View style={styles.kpiHeader}><Ionicons color={colors.muted} name="cloud-offline-outline" size={17} /><Text style={styles.kpiLabel}>Fuera de linea</Text></View>
              <Text style={styles.kpiValue}>
                {numeric(data.resumen.dispositivos_offline)}
              </Text>
            </View>
          </View>

          <View style={styles.sectionHeading}>
            <Text style={styles.sectionTitle}>Dispositivos</Text>
            <Text style={styles.sectionMeta}>En vivo · cada 5 s</Text>
          </View>
          {data.dispositivos.length ? (
            data.dispositivos.slice(0, 3).map((device) => (
              <DeviceCard device={device} key={device.id} />
            ))
          ) : (
            <Text style={styles.empty}>No hay dispositivos disponibles.</Text>
          )}

          <View style={styles.sectionHeading}>
            <Text style={styles.sectionTitle}>Alertas recientes</Text>
            <Text style={styles.sectionMeta}>Ultimas 5</Text>
          </View>
          <View style={styles.alertList}>
            {data.alertas.length ? (
              data.alertas.map((alert) => (
                <View style={styles.alertRow} key={String(alert.id)}>
                  <View
                    style={[
                      styles.alertMarker,
                      {
                        backgroundColor:
                          alert.severidad === 'CRITICO'
                            ? colors.critical
                            : colors.warning,
                      },
                    ]}
                  />
                  <View style={styles.alertCopy}>
                    <Text style={styles.alertTitle}>{alert.tipo_alerta}</Text>
                    <Text style={styles.alertMeta}>
                      {alert.dispositivo_id} · {alert.ubicacion}
                    </Text>
                  </View>
                  <Text style={styles.alertState}>{alert.estado_atencion}</Text>
                </View>
              ))
            ) : (
              <Text style={styles.empty}>No hay alertas recientes.</Text>
            )}
          </View>
        </>
      ) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  quotesEntry: {
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    flexDirection: 'row',
    gap: spacing.md,
    minHeight: 88,
    padding: spacing.md,
  },
  quotesEntryPressed: {
    backgroundColor: colors.surfaceRaised,
    opacity: 0.86,
  },
  quotesIcon: {
    alignItems: 'center',
    backgroundColor: colors.surfaceRaised,
    borderRadius: radius.md,
    height: 48,
    justifyContent: 'center',
    width: 48,
  },
  quotesCopy: {
    flex: 1,
  },
  quotesEyebrow: {
    color: colors.warning,
    fontSize: 10,
    fontWeight: '900',
  },
  quotesTitle: {
    color: colors.textStrong,
    fontSize: 16,
    fontWeight: '900',
    marginTop: spacing.xs,
  },
  quotesDescription: {
    color: colors.muted,
    fontSize: 12,
    lineHeight: 17,
    marginTop: spacing.xs,
  },
  headerLogo: {
    height: 34,
    width: 112,
  },
  statePanel: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: 1,
    gap: spacing.lg,
    overflow: 'hidden',
    padding: spacing.lg,
    shadowColor: colors.black,
    shadowOffset: { height: 7, width: 0 },
    shadowOpacity: 0.24,
    shadowRadius: 12,
    elevation: 3,
  },
  stateRail: {
    height: 3,
    left: 0,
    position: 'absolute',
    right: 0,
    top: 0,
  },
  stateCopy: {
    gap: spacing.sm,
  },
  sectionLabel: {
    color: colors.normal,
    fontSize: 11,
    fontWeight: '900',
    letterSpacing: 0,
  },
  stateTitle: {
    color: colors.textStrong,
    fontSize: 36,
    fontWeight: '900',
    letterSpacing: 0,
  },
  stateDescription: {
    color: colors.muted,
    fontSize: 14,
    lineHeight: 20,
  },
  kpiGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.md,
  },
  kpi: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    flexBasis: '47%',
    flexGrow: 1,
    minHeight: 92,
    overflow: 'hidden',
    padding: spacing.md,
  },
  kpiHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: spacing.sm,
  },
  kpiLabel: {
    color: colors.muted,
    fontSize: 12,
  },
  kpiValue: {
    color: colors.textStrong,
    fontSize: 26,
    fontWeight: '900',
    marginTop: spacing.sm,
  },
  sectionHeading: {
    alignItems: 'baseline',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: spacing.sm,
  },
  sectionTitle: {
    color: colors.textStrong,
    fontSize: 20,
    fontWeight: '800',
  },
  sectionMeta: {
    color: colors.muted,
    fontSize: 12,
  },
  deviceCard: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    overflow: 'hidden',
    gap: spacing.md,
    padding: spacing.lg,
  },
  deviceRail: {
    bottom: 0,
    left: 0,
    position: 'absolute',
    top: 0,
    width: 3,
  },
  deviceHeader: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  deviceIdentity: {
    flex: 1,
    gap: spacing.xs,
  },
  deviceId: {
    color: colors.textStrong,
    fontSize: 17,
    fontWeight: '900',
  },
  deviceLocation: {
    color: colors.muted,
    fontSize: 13,
  },
  connection: {
    fontSize: 11,
    fontWeight: '900',
  },
  readings: {
    flexDirection: 'row',
    gap: spacing.md,
  },
  reading: {
    backgroundColor: colors.surfaceStrong,
    borderRadius: radius.md,
    flex: 1,
    minHeight: 72,
    padding: spacing.md,
  },
  readingLabel: {
    color: colors.muted,
    fontSize: 12,
  },
  readingValue: {
    color: colors.normal,
    fontSize: 21,
    fontWeight: '900',
    marginTop: spacing.sm,
  },
  sensorRow: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  sensorCopy: {
    gap: spacing.xs,
  },
  sensorName: {
    color: colors.text,
    fontSize: 13,
    fontWeight: '700',
  },
  sensorValue: {
    color: colors.textStrong,
    fontSize: 16,
    fontWeight: '800',
  },
  sensorHealth: {
    color: colors.warning,
    fontSize: 11,
    fontWeight: '900',
  },
  progressTrack: {
    backgroundColor: colors.border,
    borderRadius: 3,
    height: 6,
    overflow: 'hidden',
  },
  progressValue: {
    borderRadius: 3,
    height: 6,
  },
  alertList: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    overflow: 'hidden',
  },
  alertRow: {
    alignItems: 'center',
    borderBottomColor: colors.borderSoft,
    borderBottomWidth: StyleSheet.hairlineWidth,
    flexDirection: 'row',
    gap: spacing.md,
    minHeight: 68,
    paddingHorizontal: spacing.md,
  },
  alertMarker: {
    borderRadius: 2,
    height: 32,
    width: 4,
  },
  alertCopy: {
    flex: 1,
    gap: spacing.xs,
  },
  alertTitle: {
    color: colors.textStrong,
    fontSize: 14,
    fontWeight: '800',
  },
  alertMeta: {
    color: colors.muted,
    fontSize: 12,
  },
  alertState: {
    color: colors.warning,
    fontSize: 10,
    fontWeight: '900',
  },
  errorPanel: {
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderColor: colors.critical,
    borderRadius: radius.md,
    borderWidth: 1,
    gap: spacing.md,
    padding: spacing.xl,
  },
  errorText: {
    color: colors.text,
    fontSize: 14,
    lineHeight: 20,
    textAlign: 'center',
  },
  retryButton: {
    borderColor: colors.warning,
    borderRadius: radius.md,
    borderWidth: 1,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
  },
  retryText: {
    color: colors.warning,
    fontWeight: '800',
  },
  empty: {
    color: colors.muted,
    padding: spacing.lg,
    textAlign: 'center',
  },
});
