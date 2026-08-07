import Ionicons from '@expo/vector-icons/Ionicons';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, Text, View } from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { useAuth } from '@/context/auth-context';
import {
  ApiError,
  controlMobileShelly,
  getMobileShellyDetail,
  testMobileShelly,
} from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileShellyActuator, MobileShellyDetail } from '@/types/api';

function numberValue(value: number | string | null | undefined): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function dateLabel(value: string | null | undefined): string {
  if (!value) return 'Sin registro';
  const date = new Date(`${value.replace(' ', 'T')}Z`);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit', hour: '2-digit', minute: '2-digit', month: 'short', year: 'numeric',
  }).format(date);
}

function categoryLabel(value: MobileShellyActuator['categoria']): string {
  if (value === 'AUTOMATIZACION') return 'Automatizacion';
  if (value === 'MONITOREO') return 'Solo monitoreo';
  return 'Seguridad';
}

function eventLabel(value: string): string {
  return value.replace(/_/g, ' ').toLowerCase().replace(/^./, (letter) => letter.toUpperCase());
}

function DataRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.dataRow}>
      <Text style={styles.dataLabel}>{label}</Text>
      <Text style={styles.dataValue}>{value}</Text>
    </View>
  );
}

export default function ShellyDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const { token } = useAuth();
  const [detail, setDetail] = useState<MobileShellyDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    if (!token || !id) return;
    setError('');
    try {
      setDetail(await getMobileShellyDetail(token, id));
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No fue posible cargar el dispositivo.');
    } finally {
      setLoading(false);
    }
  }, [id, token]);

  useEffect(() => { void load(); }, [load]);

  const runTest = useCallback(async () => {
    if (!token || !id) return;
    setBusy(true);
    try {
      await testMobileShelly(token, id);
      await load();
      Alert.alert('Conexion verificada', 'El estado se sincronizo con Shelly Cloud.');
    } catch (caught) {
      Alert.alert('No fue posible sincronizar', caught instanceof ApiError ? caught.message : 'Intenta nuevamente.');
    } finally {
      setBusy(false);
    }
  }, [id, load, token]);

  const runControl = useCallback((action: 'ENCENDER' | 'APAGAR') => {
    if (!token || !id) return;
    const execute = async () => {
      setBusy(true);
      try {
        await controlMobileShelly(token, id, action);
        await load();
      } catch (caught) {
        Alert.alert('No fue posible enviar la orden', caught instanceof ApiError ? caught.message : 'Intenta nuevamente.');
      } finally {
        setBusy(false);
      }
    };
    void execute();
  }, [id, load, token]);

  const actuator = detail?.actuador;
  const outputOn = numberValue(actuator?.salida_encendida) === 1;
  const online = actuator?.conexion === 'ONLINE';

  return (
    <AppScreen
      eyebrow="SHELLY"
      title={actuator?.nombre || id || 'Detalle'}
      leading={(
        <Pressable accessibilityLabel="Volver" onPress={() => router.back()} style={styles.iconButton}>
          <Ionicons color={colors.text} name="arrow-back" size={22} />
        </Pressable>
      )}
      action={detail?.permisos.administrar ? (
        <Pressable
          accessibilityLabel="Editar Shelly"
          onPress={() => router.push(`/shelly/formulario?id=${encodeURIComponent(id)}` as Href)}
          style={styles.iconButton}
        >
          <Ionicons color={colors.warning} name="create-outline" size={22} />
        </Pressable>
      ) : undefined}
    >
      {loading ? <ActivityIndicator color={colors.warning} size="large" /> : null}
      {error ? (
        <View style={styles.errorPanel}>
          <Text style={styles.errorText}>{error}</Text>
          <Pressable onPress={() => void load()} style={styles.outlineButton}><Text style={styles.outlineText}>Reintentar</Text></Pressable>
        </View>
      ) : null}
      {actuator ? (
        <>
          <View style={[styles.hero, outputOn && styles.heroOn]}>
            <View style={styles.heroTop}>
              <View style={[styles.statusDot, { backgroundColor: online ? colors.success : colors.muted }]} />
              <Text style={[styles.connection, { color: online ? colors.success : colors.muted }]}>{actuator.conexion}</Text>
              <Text style={styles.channel}>CANAL {actuator.canal}</Text>
            </View>
            <Ionicons color={outputOn ? colors.warning : colors.muted} name="power" size={42} />
            <Text style={[styles.output, { color: outputOn ? colors.warning : colors.textStrong }]}>
              {outputOn ? 'SALIDA ENCENDIDA' : 'SALIDA APAGADA'}
            </Text>
            <Text style={styles.location}>{actuator.ubicacion}</Text>
            <View style={styles.metrics}>
              <DataRow label="Potencia" value={actuator.potencia_w == null ? '--' : `${numberValue(actuator.potencia_w).toFixed(1)} W`} />
              <DataRow label="Corriente" value={actuator.corriente_a == null ? '--' : `${numberValue(actuator.corriente_a).toFixed(2)} A`} />
              <DataRow label="Voltaje" value={actuator.voltaje_v == null ? '--' : `${numberValue(actuator.voltaje_v).toFixed(1)} V`} />
            </View>
          </View>

          {detail?.permisos.controlar && actuator.categoria !== 'MONITOREO' ? (
            <View style={styles.actions}>
              <Pressable disabled={busy} onPress={() => runControl(outputOn ? 'APAGAR' : 'ENCENDER')} style={[styles.primaryButton, outputOn && styles.stopButton, busy && styles.disabled]}>
                <Ionicons color={outputOn ? colors.critical : colors.black} name={outputOn ? 'stop-circle-outline' : 'power-outline'} size={20} />
                <Text style={[styles.primaryText, outputOn && styles.stopText]}>{outputOn ? 'Apagar' : 'Encender'}</Text>
              </Pressable>
              <Pressable disabled={busy} onPress={() => void runTest()} style={[styles.outlineButton, busy && styles.disabled]}>
                <Ionicons color={colors.normal} name="sync-outline" size={19} />
                <Text style={styles.outlineText}>Probar conexion</Text>
              </Pressable>
            </View>
          ) : null}

          <View style={styles.section}>
            <Text style={styles.sectionEyebrow}>CONFIGURACION</Text>
            <Text style={styles.sectionTitle}>Identidad y funcion</Text>
            <DataRow label="Device ID" value={actuator.shelly_device_id} />
            <DataRow label="Modelo" value={`${actuator.modelo} · ${actuator.generacion}`} />
            <DataRow label="Funcion" value={actuator.funcion} />
            <DataRow label="Categoria" value={categoryLabel(actuator.categoria)} />
            <DataRow label="ESP32 asociado" value={actuator.dispositivo_vinculado_id ?? 'Sin asociar'} />
            <DataRow label="Modo de control" value={actuator.modo_control} />
          </View>

          <View style={styles.section}>
            <Text style={styles.sectionEyebrow}>SEGURIDAD ELECTRICA</Text>
            <Text style={styles.sectionTitle}>Limites configurados</Text>
            <DataRow label="Tipo de carga" value={actuator.tipo_carga} />
            <DataRow label="Corriente maxima" value={actuator.corriente_max_a == null ? 'Sin definir' : `${actuator.corriente_max_a} A`} />
            <DataRow label="Potencia maxima" value={actuator.potencia_max_w == null ? 'Sin definir' : `${actuator.potencia_max_w} W`} />
            <DataRow label="Apagado automatico" value={numberValue(actuator.apagado_automatico) ? `${Math.ceil(numberValue(actuator.tiempo_max_encendido_s) / 60)} min` : 'Desactivado'} />
            <DataRow label="Confirmacion manual" value={numberValue(actuator.requiere_confirmacion) ? 'Requerida' : 'No requerida'} />
            {actuator.descripcion ? <Text style={styles.description}>{actuator.descripcion}</Text> : null}
          </View>

          <View style={styles.section}>
            <Text style={styles.sectionEyebrow}>AUDITORIA</Text>
            <Text style={styles.sectionTitle}>Ultimos 10 movimientos</Text>
            {detail?.eventos.length ? detail.eventos.slice(0, 10).map((event) => (
              <View key={String(event.id)} style={styles.event}>
                <View style={styles.eventCopy}>
                  <Text style={styles.eventTitle}>{eventLabel(event.evento)}</Text>
                  <Text style={styles.eventMeta}>{event.origen} · {dateLabel(event.fecha_hora)}</Text>
                </View>
                {event.salida_encendida != null ? (
                  <Ionicons color={numberValue(event.salida_encendida) ? colors.warning : colors.muted} name="power" size={18} />
                ) : null}
              </View>
            )) : <Text style={styles.description}>Todavia no hay eventos registrados.</Text>}
          </View>
        </>
      ) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  iconButton: { alignItems: 'center', borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, height: 44, justifyContent: 'center', width: 44 },
  errorPanel: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.critical, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  errorText: { color: colors.text, textAlign: 'center' },
  hero: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.sm, padding: spacing.xl },
  heroOn: { borderColor: colors.warning },
  heroTop: { alignItems: 'center', flexDirection: 'row', gap: spacing.sm, width: '100%' },
  statusDot: { borderRadius: 5, height: 10, width: 10 },
  connection: { fontSize: 12, fontWeight: '900' },
  channel: { color: colors.muted, fontSize: 11, fontWeight: '800', marginLeft: 'auto' },
  output: { fontSize: 22, fontWeight: '900' },
  location: { color: colors.muted, fontSize: 14 },
  metrics: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.sm, width: '100%' },
  dataRow: { borderBottomColor: colors.borderSoft, borderBottomWidth: 1, flex: 1, gap: spacing.xs, justifyContent: 'space-between', paddingVertical: spacing.sm },
  dataLabel: { color: colors.muted, fontSize: 11 },
  dataValue: { color: colors.textStrong, fontSize: 13, fontWeight: '800' },
  actions: { flexDirection: 'row', gap: spacing.sm },
  primaryButton: { alignItems: 'center', backgroundColor: colors.warning, borderColor: colors.warning, borderRadius: radius.sm, borderWidth: 1, flex: 1, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 48 },
  primaryText: { color: colors.black, fontSize: 14, fontWeight: '900' },
  stopButton: { backgroundColor: 'transparent', borderColor: colors.critical },
  stopText: { color: colors.critical },
  outlineButton: { alignItems: 'center', borderColor: colors.normal, borderRadius: radius.sm, borderWidth: 1, flex: 1, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 46, paddingHorizontal: spacing.md },
  outlineText: { color: colors.normal, fontSize: 13, fontWeight: '800' },
  disabled: { opacity: 0.5 },
  section: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.sm, padding: spacing.lg },
  sectionEyebrow: { color: colors.normal, fontSize: 11, fontWeight: '900' },
  sectionTitle: { color: colors.textStrong, fontSize: 18, fontWeight: '900', marginBottom: spacing.xs },
  description: { color: colors.muted, fontSize: 13, lineHeight: 19, paddingTop: spacing.sm },
  event: { alignItems: 'center', borderBottomColor: colors.borderSoft, borderBottomWidth: 1, flexDirection: 'row', gap: spacing.sm, paddingVertical: spacing.md },
  eventCopy: { flex: 1, gap: spacing.xs },
  eventTitle: { color: colors.textStrong, fontSize: 13, fontWeight: '800' },
  eventMeta: { color: colors.muted, fontSize: 11 },
});
