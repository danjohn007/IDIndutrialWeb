import Ionicons from '@expo/vector-icons/Ionicons';
import { useFocusEffect, useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { useAuth } from '@/context/auth-context';
import { ApiError, getMobileZktecoDetail } from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileZktecoDetail } from '@/types/api';

function dateLabel(value: string | null | undefined) {
  if (!value) return 'Sin registro';
  const date = new Date(`${value.replace(' ', 'T')}Z`);
  return Number.isNaN(date.getTime())
    ? value
    : new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

function metric(value: number | string | null | undefined) {
  return value === null || value === undefined ? '--' : String(value);
}

export default function ZktecoDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const { token } = useAuth();
  const [detail, setDetail] = useState<MobileZktecoDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    if (!token || !id) return;
    try {
      setError('');
      setDetail(await getMobileZktecoDetail(token, id));
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No fue posible cargar el equipo.');
    } finally {
      setLoading(false);
    }
  }, [id, token]);

  useFocusEffect(useCallback(() => { void load(); }, [load]));

  const device = detail?.equipo;
  const online = device?.conexion === 'ONLINE';
  const statusColor = online
    ? colors.success
    : device?.conexion === 'DESACTUALIZADO' ? colors.warning : colors.muted;

  return (
    <AppScreen
      eyebrow="ZKTECO · ACCESO"
      title={device?.nombre || id || 'Equipo'}
      leading={(
        <Pressable accessibilityLabel="Volver" onPress={() => router.back()} style={styles.iconButton}>
          <Ionicons color={colors.text} name="arrow-back" size={22} />
        </Pressable>
      )}
      action={detail?.permisos.administrar ? (
        <Pressable
          accessibilityLabel="Editar"
          onPress={() => router.push(`/zkteco/formulario?id=${encodeURIComponent(id)}` as Href)}
          style={styles.iconButton}
        >
          <Ionicons color={colors.warning} name="create-outline" size={22} />
        </Pressable>
      ) : undefined}
    >
      {loading ? <ActivityIndicator color={colors.warning} size="large" /> : null}
      {error ? (
        <View style={styles.error}>
          <Text style={styles.errorText}>{error}</Text>
          <Pressable onPress={() => void load()}><Text style={styles.link}>Reintentar</Text></Pressable>
        </View>
      ) : null}
      {device ? (
        <>
          <View style={[styles.hero, online && styles.heroOnline]}>
            <View style={styles.heroTop}>
              <Ionicons color={statusColor} name="finger-print-outline" size={35} />
              <Text style={[styles.connection, { color: statusColor }]}>{device.conexion.replace('_', ' ')}</Text>
            </View>
            <Text style={styles.model}>{device.modelo_detectado || device.modelo || 'Modelo pendiente de detectar'}</Text>
            <Text style={styles.location}>{device.ubicacion}</Text>
          </View>

          <View style={styles.metrics}>
            <View style={styles.metricCard}>
              <Text style={styles.metricLabel}>USUARIOS</Text>
              <Text style={styles.metricValue}>{metric(device.usuarios_total)}</Text>
              <Text style={styles.metricMeta}>Capacidad {metric(device.capacidad_usuarios)}</Text>
            </View>
            <View style={styles.metricCard}>
              <Text style={styles.metricLabel}>REGISTROS</Text>
              <Text style={styles.metricValue}>{metric(device.registros_total)}</Text>
              <Text style={styles.metricMeta}>Capacidad {metric(device.capacidad_registros)}</Text>
            </View>
          </View>

          <View style={styles.card}>
            <Text style={styles.sectionLabel}>IDENTIDAD Y SALUD</Text>
            {[
              ['ID', device.id],
              ['Categoria', device.categoria.replace('_', ' ')],
              ['Serie', device.serial_detectado || device.numero_serie || '--'],
              ['Firmware', device.firmware || '--'],
              ['Plataforma', device.plataforma || '--'],
              ['Protocolo', device.protocolo.replace('_', ' ')],
              ['Maquina', String(device.numero_maquina)],
              ['Sincronizado', dateLabel(device.sincronizado_en)],
            ].map(([label, value]) => (
              <View key={label} style={styles.row}>
                <Text style={styles.rowLabel}>{label}</Text>
                <Text style={styles.rowValue}>{value}</Text>
              </View>
            ))}
            {device.ultimo_error ? <Text style={styles.errorText}>{device.ultimo_error}</Text> : null}
          </View>

          <View style={styles.card}>
            <Text style={styles.sectionLabel}>MARCACIONES RECIENTES</Text>
            {detail.eventos.length ? detail.eventos.map((event) => (
              <View key={String(event.id)} style={styles.event}>
                <View style={styles.eventTop}>
                  <View style={styles.eventIcon}>
                    <Ionicons color={colors.normal} name="enter-outline" size={18} />
                  </View>
                  <View style={styles.eventCopy}>
                    <Text style={styles.eventTitle}>{event.tipo_evento.replace(/_/g, ' ')}</Text>
                    <Text style={styles.eventDescription}>
                      Usuario {event.pin_usuario || 'sin identificar'}
                      {event.modo_verificacion ? ` · ${event.modo_verificacion.replace(/_/g, ' ')}` : ''}
                    </Text>
                    <Text style={styles.eventDate}>{dateLabel(event.ocurrido_en || event.recibido_en)}</Text>
                  </View>
                </View>
              </View>
            )) : <Text style={styles.empty}>Aun no hay marcaciones reportadas.</Text>}
          </View>
        </>
      ) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  iconButton: { alignItems: 'center', borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, height: 44, justifyContent: 'center', width: 44 },
  hero: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.sm, padding: spacing.xl },
  heroOnline: { borderColor: colors.success },
  heroTop: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between' },
  connection: { fontSize: 12, fontWeight: '900' },
  model: { color: colors.textStrong, fontSize: 20, fontWeight: '900' },
  location: { color: colors.muted, fontSize: 14 },
  metrics: { flexDirection: 'row', gap: spacing.md },
  metricCard: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, flex: 1, gap: spacing.xs, padding: spacing.lg },
  metricLabel: { color: colors.normal, fontSize: 10, fontWeight: '900' },
  metricValue: { color: colors.textStrong, fontSize: 24, fontWeight: '900' },
  metricMeta: { color: colors.muted, fontSize: 11 },
  card: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.sm, padding: spacing.lg },
  sectionLabel: { color: colors.normal, fontSize: 11, fontWeight: '900' },
  row: { borderBottomColor: colors.borderSoft, borderBottomWidth: 1, flexDirection: 'row', gap: spacing.md, justifyContent: 'space-between', paddingVertical: spacing.sm },
  rowLabel: { color: colors.muted, fontSize: 12 },
  rowValue: { color: colors.textStrong, flex: 1, fontSize: 12, fontWeight: '800', textAlign: 'right' },
  event: { borderTopColor: colors.borderSoft, borderTopWidth: 1, paddingTop: spacing.md },
  eventTop: { alignItems: 'flex-start', flexDirection: 'row', gap: spacing.md },
  eventIcon: { alignItems: 'center', backgroundColor: colors.surfaceStrong, borderRadius: radius.sm, height: 36, justifyContent: 'center', width: 36 },
  eventCopy: { flex: 1, gap: spacing.xs },
  eventTitle: { color: colors.textStrong, fontSize: 14, fontWeight: '900' },
  eventDescription: { color: colors.text, fontSize: 12, lineHeight: 18 },
  eventDate: { color: colors.muted, fontSize: 11 },
  empty: { color: colors.muted, fontSize: 13 },
  error: { alignItems: 'center', borderColor: colors.critical, borderRadius: radius.md, borderWidth: 1, gap: spacing.sm, padding: spacing.lg },
  errorText: { color: colors.critical, fontSize: 12, lineHeight: 18 },
  link: { color: colors.normal, fontWeight: '800' },
});
