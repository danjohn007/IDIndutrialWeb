import Ionicons from '@expo/vector-icons/Ionicons';
import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { useAuth } from '@/context/auth-context';
import { ApiError, getMobileAlerts } from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileAlert, MobileAlertFilters, MobileAlertsPage } from '@/types/api';

type FilterOption<T extends string> = { label: string; value: T | '' };
type SensorFilter = NonNullable<MobileAlertFilters['sensor']>;
type SeverityFilter = NonNullable<MobileAlertFilters['severidad']>;
type AttentionFilter = NonNullable<MobileAlertFilters['estado']>;

const sensorOptions: FilterOption<SensorFilter>[] = [
  { label: 'Todos', value: '' },
  { label: 'Humo/Gas', value: 'GAS' },
  { label: 'Flama', value: 'FLAMA' },
  { label: 'Estación manual', value: 'ESTACION_MANUAL' },
  { label: 'Temperatura', value: 'TEMPERATURA' },
  { label: 'Conectividad', value: 'CONECTIVIDAD' },
];

const severityOptions: FilterOption<SeverityFilter>[] = [
  { label: 'Todas', value: '' },
  { label: 'Críticas', value: 'CRITICO' },
  { label: 'Precaución', value: 'PRECAUCION' },
];

const statusOptions: FilterOption<AttentionFilter>[] = [
  { label: 'Todas', value: '' },
  { label: 'Nuevas', value: 'NUEVA' },
  { label: 'Reconocidas', value: 'RECONOCIDA' },
  { label: 'Resueltas', value: 'RESUELTA' },
];

function dateLabel(value: string): string {
  const date = new Date(`${value.replace(' ', 'T')}Z`);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    month: 'short',
  }).format(date);
}

function alertValue(alert: MobileAlert): string {
  const type = alert.tipo_alerta.toUpperCase();
  if (type.includes('SIN CONEXION') || type.includes('DESCONECT')) {
    return 'Sin comunicacion';
  }
  if (type.includes('ESTACION MANUAL') || type.includes('PULSADOR')) {
    return 'Activada';
  }
  if (alert.valor_sensor === null || alert.valor_sensor === undefined) {
    return 'Sin valor';
  }
  if (type.includes('FLAMA') || type.includes('FUEGO')) return 'Detectada';
  if (type.includes('TEMPERATURA') || type.includes('CALOR')) {
    return `${alert.valor_sensor} °C`;
  }
  if (type.includes('GAS') || type.includes('HUMO') || type.includes('MQ-2')) {
    return `${alert.valor_sensor} ADC`;
  }
  return String(alert.valor_sensor);
}

function severityColor(severity: MobileAlert['severidad']): string {
  if (severity === 'CRITICO') return colors.critical;
  if (severity === 'PRECAUCION') return colors.warning;
  return colors.normal;
}

function FilterGroup<T extends string>({
  options,
  selected,
  onChange,
}: {
  options: FilterOption<T>[];
  selected: T | '';
  onChange: (value: T | '') => void;
}) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
      {options.map((option) => {
        const active = selected === option.value;
        return (
          <Pressable
            key={option.label}
            onPress={() => onChange(option.value)}
            style={({ pressed }) => [styles.chip, active && styles.chipActive, pressed && styles.pressed]}
          >
            <Text style={[styles.chipText, active && styles.chipTextActive]}>{option.label}</Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

export default function AlertsScreen() {
  const { token } = useAuth();
  const router = useRouter();
  const [data, setData] = useState<MobileAlertsPage | null>(null);
  const [sensor, setSensor] = useState<SensorFilter | ''>('');
  const [severity, setSeverity] = useState<SeverityFilter | ''>('');
  const [status, setStatus] = useState<AttentionFilter | ''>('');
  const [deviceId, setDeviceId] = useState('');
  const [filtersOpen, setFiltersOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState('');
  const loadingMoreRef = useRef(false);

  const filters = useMemo<MobileAlertFilters>(() => ({
    ...(sensor ? { sensor } : {}),
    ...(severity ? { severidad: severity } : {}),
    ...(status ? { estado: status } : {}),
    ...(deviceId ? { dispositivo_id: deviceId } : {}),
    por_pagina: 20,
  }), [deviceId, sensor, severity, status]);

  const load = useCallback(async (page = 1, append = false) => {
    if (!token) return;
    if (append && loadingMoreRef.current) return;
    if (append) {
      loadingMoreRef.current = true;
      setLoadingMore(true);
    } else {
      setLoading(true);
    }
    setError('');
    try {
      const response = await getMobileAlerts(token, { ...filters, pagina: page });
      setData((current) => {
        if (!append || !current) return response;
        const alertsById = new Map(
          [...current.alertas, ...response.alertas].map((alert) => [
            String(alert.id),
            alert,
          ]),
        );
        return { ...response, alertas: Array.from(alertsById.values()) };
      });
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No fue posible cargar las alertas.');
    } finally {
      setLoading(false);
      setLoadingMore(false);
      loadingMoreRef.current = false;
      setRefreshing(false);
    }
  }, [filters, token]);

  useFocusEffect(useCallback(() => {
    void load();
  }, [load]));

  const refresh = () => {
    setRefreshing(true);
    void load();
  };
  const canLoadMore = Boolean(data && data.paginacion.pagina < data.paginacion.paginas);
  const activeFilterCount = [sensor, severity, status, deviceId].filter(Boolean).length;

  const clearFilters = () => {
    setSensor('');
    setSeverity('');
    setStatus('');
    setDeviceId('');
  };

  return (
    <AppScreen
      eyebrow="EVENTOS"
      title="Alertas"
      action={
        <Pressable accessibilityLabel="Actualizar alertas" onPress={refresh} style={styles.refreshButton}>
          <Ionicons color={colors.text} name="refresh" size={21} />
        </Pressable>
      }
      scrollProps={{
        refreshControl: (
          <RefreshControl
            colors={[colors.warning]}
            onRefresh={refresh}
            refreshing={refreshing}
            tintColor={colors.warning}
          />
        ),
      }}
    >
      <View style={styles.filterPanel}>
        <Pressable
          accessibilityRole="button"
          accessibilityState={{ expanded: filtersOpen }}
          onPress={() => setFiltersOpen((current) => !current)}
          style={({ pressed }) => [styles.filterHeading, pressed && styles.pressed]}
        >
          <View style={styles.filterHeadingCopy}>
            <Text style={styles.filterTitle}>Historial operativo</Text>
            <Text style={styles.filterSummary}>
              {activeFilterCount
                ? `${activeFilterCount} ${activeFilterCount === 1 ? 'filtro activo' : 'filtros activos'}`
                : 'Toca para filtrar'}
            </Text>
          </View>
          <View style={styles.filterHeadingRight}>
            <Text style={styles.total}>{data ? `${data.paginacion.total} eventos` : '--'}</Text>
            <Ionicons
              color={colors.muted}
              name={filtersOpen ? 'chevron-up' : 'chevron-down'}
              size={20}
            />
          </View>
        </Pressable>
        {filtersOpen ? (
          <>
            <View style={styles.filterDivider} />
            <Text style={styles.filterLabel}>Sensor</Text>
            <FilterGroup options={sensorOptions} selected={sensor} onChange={setSensor} />
            <Text style={styles.filterLabel}>Severidad</Text>
            <FilterGroup options={severityOptions} selected={severity} onChange={setSeverity} />
            <Text style={styles.filterLabel}>Estado</Text>
            <FilterGroup options={statusOptions} selected={status} onChange={setStatus} />
            {data?.dispositivos.length ? (
              <>
            <Text style={styles.filterLabel}>Dispositivo</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
              <Pressable
                onPress={() => setDeviceId('')}
                style={({ pressed }) => [styles.chip, !deviceId && styles.chipActive, pressed && styles.pressed]}
              >
                <Text style={[styles.chipText, !deviceId && styles.chipTextActive]}>Todos</Text>
              </Pressable>
              {data.dispositivos.map((device) => (
                <Pressable
                  key={device.id}
                  onPress={() => setDeviceId(device.id)}
                  style={({ pressed }) => [styles.chip, deviceId === device.id && styles.chipActive, pressed && styles.pressed]}
                >
                  <Text style={[styles.chipText, deviceId === device.id && styles.chipTextActive]}>{device.id}</Text>
                </Pressable>
              ))}
            </ScrollView>
              </>
            ) : null}
            {activeFilterCount ? (
              <Pressable onPress={clearFilters} style={({ pressed }) => [styles.clearButton, pressed && styles.pressed]}>
                <Ionicons color={colors.normal} name="close-circle-outline" size={18} />
                <Text style={styles.clearText}>Limpiar filtros</Text>
              </Pressable>
            ) : null}
          </>
        ) : null}
      </View>

      {loading && !data ? <ActivityIndicator color={colors.warning} size="large" /> : null}
      {error ? (
        <View style={styles.errorPanel}>
          <Ionicons color={colors.critical} name="cloud-offline-outline" size={25} />
          <Text style={styles.errorText}>{error}</Text>
          <Pressable onPress={() => void load()} style={styles.retryButton}>
            <Text style={styles.retryText}>Reintentar</Text>
          </Pressable>
        </View>
      ) : null}

      {data?.alertas.map((alert) => (
        <Pressable
          accessibilityHint="Abre las lecturas antes y después de la alerta"
          accessibilityRole="button"
          key={String(alert.id)}
          onPress={() => router.push({ pathname: '/alerta/[id]', params: { id: String(alert.id) } })}
          style={({ pressed }) => [styles.alertCard, pressed && styles.pressedCard]}
        >
          <View style={[styles.alertMarker, { backgroundColor: severityColor(alert.severidad) }]} />
          <View style={styles.alertCopy}>
            <View style={styles.alertTopline}>
              <Text numberOfLines={1} style={styles.alertTitle}>{alert.tipo_alerta}</Text>
              <Text style={[styles.state, { color: severityColor(alert.severidad) }]}>{alert.severidad}</Text>
            </View>
            <Text style={styles.alertMeta}>{alert.dispositivo_id} · {alert.ubicacion}</Text>
            <View style={styles.alertBottomline}>
              <Text style={styles.alertValue}>{alertValue(alert)}</Text>
              <Text style={styles.alertDate}>{dateLabel(alert.fecha_hora)}</Text>
            </View>
            <View style={styles.alertActionRow}>
              <Text style={styles.attention}>{alert.estado_atencion}</Text>
              <Ionicons color={colors.muted} name="chevron-forward" size={17} />
            </View>
          </View>
        </Pressable>
      ))}

      {data && !data.alertas.length && !loading ? (
        <View style={styles.emptyPanel}>
          <Ionicons color={colors.success} name="checkmark-circle-outline" size={28} />
          <Text style={styles.emptyText}>No hay alertas con estos filtros.</Text>
        </View>
      ) : null}

      {canLoadMore ? (
        <Pressable
          disabled={loadingMore}
          onPress={() => void load((data?.paginacion.pagina ?? 0) + 1, true)}
          style={({ pressed }) => [styles.moreButton, (pressed || loadingMore) && styles.pressed]}
        >
          {loadingMore ? <ActivityIndicator color={colors.normal} /> : <Text style={styles.moreText}>Cargar más</Text>}
        </Pressable>
      ) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  refreshButton: {
    alignItems: 'center',
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    height: 44,
    justifyContent: 'center',
    width: 44,
  },
  filterPanel: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    gap: spacing.sm,
    paddingVertical: spacing.md,
  },
  filterHeading: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    minHeight: 48,
    paddingHorizontal: spacing.md,
  },
  filterHeadingCopy: { flex: 1, gap: spacing.xs },
  filterHeadingRight: { alignItems: 'center', flexDirection: 'row', gap: spacing.sm },
  filterTitle: { color: colors.textStrong, fontSize: 17, fontWeight: '800' },
  filterSummary: { color: colors.muted, fontSize: 12 },
  total: { color: colors.muted, fontSize: 12, fontWeight: '700' },
  filterDivider: { backgroundColor: colors.borderSoft, height: 1, marginHorizontal: spacing.md },
  filterLabel: {
    color: colors.muted,
    fontSize: 11,
    fontWeight: '800',
    marginLeft: spacing.md,
    marginTop: spacing.xs,
    textTransform: 'uppercase',
  },
  chipRow: { gap: spacing.sm, paddingHorizontal: spacing.md },
  chip: {
    borderColor: colors.border,
    borderRadius: radius.sm,
    borderWidth: 1,
    minHeight: 34,
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
  },
  chipActive: { backgroundColor: colors.normal, borderColor: colors.normal },
  chipText: { color: colors.muted, fontSize: 12, fontWeight: '700' },
  chipTextActive: { color: colors.black },
  clearButton: {
    alignItems: 'center',
    alignSelf: 'flex-start',
    flexDirection: 'row',
    gap: spacing.sm,
    marginHorizontal: spacing.md,
    minHeight: 36,
  },
  clearText: { color: colors.normal, fontSize: 12, fontWeight: '800' },
  alertCard: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    flexDirection: 'row',
    overflow: 'hidden',
  },
  alertMarker: { width: 4 },
  alertCopy: { flex: 1, gap: spacing.xs, padding: spacing.md },
  alertTopline: { alignItems: 'center', flexDirection: 'row', gap: spacing.sm },
  alertTitle: { color: colors.textStrong, flex: 1, fontSize: 16, fontWeight: '800' },
  state: { fontSize: 11, fontWeight: '900' },
  alertMeta: { color: colors.muted, fontSize: 13 },
  alertBottomline: { alignItems: 'baseline', flexDirection: 'row', justifyContent: 'space-between', marginTop: spacing.xs },
  alertValue: { color: colors.text, fontSize: 14, fontWeight: '800' },
  alertDate: { color: colors.muted, fontSize: 12 },
  attention: { color: colors.normal, fontSize: 11, fontWeight: '800', marginTop: spacing.xs },
  alertActionRow: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between' },
  errorPanel: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.critical, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  errorText: { color: colors.text, fontSize: 14, textAlign: 'center' },
  retryButton: { borderColor: colors.normal, borderRadius: radius.sm, borderWidth: 1, paddingHorizontal: spacing.lg, paddingVertical: spacing.sm },
  retryText: { color: colors.normal, fontSize: 13, fontWeight: '800' },
  emptyPanel: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.sm, padding: spacing.xl },
  emptyText: { color: colors.muted, fontSize: 14 },
  moreButton: { alignItems: 'center', borderColor: colors.normal, borderRadius: radius.md, borderWidth: 1, justifyContent: 'center', minHeight: 48 },
  moreText: { color: colors.normal, fontSize: 14, fontWeight: '800' },
  pressed: { opacity: 0.7 },
  pressedCard: { backgroundColor: colors.surfaceRaised, opacity: 0.88 },
});
