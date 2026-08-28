import Ionicons from '@expo/vector-icons/Ionicons';
import { useFocusEffect, useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { useAuth } from '@/context/auth-context';
import { ApiError, getMobileHikvisionDetail } from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileHikvisionDetail } from '@/types/api';

function dateLabel(value: string | null | undefined) {
  if (!value) return 'Sin registro';
  const date = new Date(`${value.replace(' ', 'T')}Z`);
  return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

export default function HikvisionDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const { token } = useAuth();
  const [detail, setDetail] = useState<MobileHikvisionDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const load = useCallback(async () => {
    if (!token || !id) return;
    try { setError(''); setDetail(await getMobileHikvisionDetail(token, id)); }
    catch (caught) { setError(caught instanceof ApiError ? caught.message : 'No fue posible cargar el equipo.'); }
    finally { setLoading(false); }
  }, [id, token]);
  useFocusEffect(useCallback(() => { void load(); }, [load]));
  const device = detail?.equipo;
  const online = device?.conexion === 'ONLINE';
  return <AppScreen eyebrow="HIKVISION · ISAPI" title={device?.nombre || id || 'Equipo'} leading={(
    <Pressable accessibilityLabel="Volver" onPress={() => router.back()} style={styles.iconButton}><Ionicons color={colors.text} name="arrow-back" size={22} /></Pressable>
  )} action={detail?.permisos.administrar ? <Pressable accessibilityLabel="Editar" onPress={() => router.push(`/hikvision/formulario?id=${encodeURIComponent(id)}` as Href)} style={styles.iconButton}><Ionicons color={colors.warning} name="create-outline" size={22} /></Pressable> : undefined}>
    {loading ? <ActivityIndicator color={colors.warning} size="large" /> : null}
    {error ? <View style={styles.error}><Text style={styles.errorText}>{error}</Text><Pressable onPress={() => void load()}><Text style={styles.link}>Reintentar</Text></Pressable></View> : null}
    {device ? <>
      <View style={[styles.hero, online && styles.heroOnline]}><View style={styles.heroTop}><Ionicons color={online ? colors.success : colors.muted} name={device.categoria === 'CONTROL_ACCESO' ? 'keypad-outline' : 'videocam-outline'} size={34} /><Text style={[styles.connection, { color: online ? colors.success : colors.muted }]}>{device.conexion.replace('_', ' ')}</Text></View><Text style={styles.model}>{device.modelo_detectado || device.modelo || 'Modelo pendiente de detectar'}</Text><Text style={styles.location}>{device.ubicacion}</Text></View>
      <View style={styles.card}><Text style={styles.sectionLabel}>IDENTIDAD Y SALUD</Text>
        {[['ID', device.id], ['Categoria', device.categoria.replace('_', ' ')], ['Serie', device.serial_detectado || device.numero_serie || '--'], ['Firmware', device.firmware || '--'], ['MAC', device.mac || '--'], ['Sincronizado', dateLabel(device.sincronizado_en)]].map(([label, value]) => <View key={label} style={styles.row}><Text style={styles.rowLabel}>{label}</Text><Text style={styles.rowValue}>{value}</Text></View>)}
        {device.ultimo_error ? <Text style={styles.errorText}>{device.ultimo_error}</Text> : null}
      </View>
      <View style={styles.card}><Text style={styles.sectionLabel}>EVENTOS RECIENTES</Text>{detail?.eventos.length ? detail.eventos.map((event) => <View key={String(event.id)} style={styles.event}><View style={styles.eventTop}><Text style={styles.eventTitle}>{event.tipo_evento.replace(/_/g, ' ')}</Text><Text style={[styles.severity, { color: event.severidad === 'CRITICO' ? colors.critical : event.severidad === 'PRECAUCION' ? colors.warning : colors.normal }]}>{event.severidad}</Text></View>{event.descripcion ? <Text style={styles.eventDescription}>{event.descripcion}</Text> : null}<Text style={styles.eventDate}>{dateLabel(event.ocurrido_en || event.recibido_en)}</Text></View>) : <Text style={styles.empty}>Aun no hay eventos reportados.</Text>}</View>
    </> : null}
  </AppScreen>;
}

const styles = StyleSheet.create({
  iconButton: { alignItems: 'center', borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, height: 44, justifyContent: 'center', width: 44 },
  hero: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.sm, padding: spacing.xl }, heroOnline: { borderColor: colors.success }, heroTop: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between' }, connection: { fontSize: 12, fontWeight: '900' }, model: { color: colors.textStrong, fontSize: 20, fontWeight: '900' }, location: { color: colors.muted, fontSize: 14 },
  card: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.sm, padding: spacing.lg }, sectionLabel: { color: colors.normal, fontSize: 11, fontWeight: '900' }, row: { borderBottomColor: colors.borderSoft, borderBottomWidth: 1, flexDirection: 'row', gap: spacing.md, justifyContent: 'space-between', paddingVertical: spacing.sm }, rowLabel: { color: colors.muted, fontSize: 12 }, rowValue: { color: colors.textStrong, flex: 1, fontSize: 12, fontWeight: '800', textAlign: 'right' },
  event: { borderTopColor: colors.borderSoft, borderTopWidth: 1, gap: spacing.xs, paddingTop: spacing.md }, eventTop: { flexDirection: 'row', justifyContent: 'space-between' }, eventTitle: { color: colors.textStrong, flex: 1, fontSize: 14, fontWeight: '900' }, severity: { fontSize: 10, fontWeight: '900' }, eventDescription: { color: colors.text, fontSize: 12, lineHeight: 18 }, eventDate: { color: colors.muted, fontSize: 11 }, empty: { color: colors.muted, fontSize: 13 },
  error: { alignItems: 'center', borderColor: colors.critical, borderRadius: radius.md, borderWidth: 1, gap: spacing.sm, padding: spacing.lg }, errorText: { color: colors.critical, fontSize: 12, lineHeight: 18 }, link: { color: colors.normal, fontWeight: '800' },
});
