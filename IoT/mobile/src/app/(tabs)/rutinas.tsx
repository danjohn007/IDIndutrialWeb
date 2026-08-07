import Ionicons from '@expo/vector-icons/Ionicons';
import { useRouter, type Href } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { useAuth } from '@/context/auth-context';
import {
  ApiError,
  disableAlexaIntegration,
  getMobileRoutines,
  prepareAlexaIntegration,
  runMobileRoutine,
  setMobileRoutineState,
} from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileRoutine, MobileRoutineExecution, MobileRoutinesResponse } from '@/types/api';

const dayNames: Record<number, string> = { 1: 'Lun', 2: 'Mar', 3: 'Mie', 4: 'Jue', 5: 'Vie', 6: 'Sab', 7: 'Dom' };

function numeric(value: number | string | null | undefined): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function dateLabel(value: string | null | undefined): string {
  if (!value) return 'Sin ejecuciones';
  const date = new Date(`${value.replace(' ', 'T')}Z`);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es-MX', { day: '2-digit', hour: '2-digit', minute: '2-digit', month: 'short' }).format(date);
}

function scheduleLabel(routine: MobileRoutine): string {
  if (routine.tipo_disparador === 'MANUAL') return 'Ejecucion manual';
  const days = routine.dias.map((day) => dayNames[day]).filter(Boolean).join(', ');
  return `${days} · ${(routine.hora_local ?? '').slice(0, 5)}`;
}

function executionColor(status: MobileRoutineExecution['estado'] | MobileRoutine['ultimo_estado']): string {
  if (status === 'COMPLETADA') return colors.success;
  if (status === 'PARCIAL' || status === 'OMITIDA') return colors.warning;
  if (status === 'FALLIDA') return colors.critical;
  return colors.muted;
}

function RoutineCard({
  routine,
  canManage,
  canRun,
  busy,
  onEdit,
  onRun,
  onToggle,
}: {
  routine: MobileRoutine;
  canManage: boolean;
  canRun: boolean;
  busy: boolean;
  onEdit: () => void;
  onRun: () => void;
  onToggle: (active: boolean) => void;
}) {
  const active = numeric(routine.activa) === 1;
  const unavailable = numeric(routine.acciones_no_disponibles) > 0;
  return (
    <View style={[styles.routineCard, !active && styles.inactiveCard]}>
      <View style={styles.cardHeader}>
        <View style={styles.cardCopy}>
          <Text style={styles.routineName}>{routine.nombre}</Text>
          <Text style={styles.routineSchedule}>{scheduleLabel(routine)}</Text>
        </View>
        {canManage ? (
          <Switch
            disabled={busy}
            onValueChange={onToggle}
            thumbColor={active ? colors.success : colors.muted}
            trackColor={{ false: colors.border, true: colors.surfaceRaised }}
            value={active}
          />
        ) : <Text style={[styles.stateText, { color: active ? colors.success : colors.muted }]}>{active ? 'ACTIVA' : 'PAUSADA'}</Text>}
      </View>
      {routine.descripcion ? <Text style={styles.description}>{routine.descripcion}</Text> : null}
      <View style={styles.routineMeta}>
        <View><Text style={styles.metaLabel}>ACCIONES</Text><Text style={styles.metaValue}>{numeric(routine.acciones_total)}</Text></View>
        <View><Text style={styles.metaLabel}>ULTIMA EJECUCION</Text><Text style={styles.metaValueSmall}>{dateLabel(routine.ultima_ejecucion)}</Text></View>
        <View><Text style={styles.metaLabel}>RESULTADO</Text><Text style={[styles.metaValueSmall, { color: executionColor(routine.ultimo_estado) }]}>{routine.ultimo_estado ?? 'PENDIENTE'}</Text></View>
      </View>
      {unavailable ? (
        <View style={styles.warningPanel}>
          <Ionicons color={colors.warning} name="warning-outline" size={18} />
          <Text style={styles.warningText}>Una accion ya no tiene un equipo disponible.</Text>
        </View>
      ) : null}
      <View style={styles.cardActions}>
        {canManage ? (
          <Pressable disabled={busy} onPress={onEdit} style={styles.outlineButton}>
            <Ionicons color={colors.normal} name="create-outline" size={18} />
            <Text style={styles.outlineText}>Editar</Text>
          </Pressable>
        ) : null}
        {canRun ? (
          <Pressable disabled={busy || !active || unavailable} onPress={onRun} style={[styles.runButton, (busy || !active || unavailable) && styles.disabled]}>
            {busy ? <ActivityIndicator color={colors.black} size="small" /> : <Ionicons color={colors.black} name="play" size={18} />}
            <Text style={styles.runText}>Ejecutar</Text>
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

export default function RoutinesScreen() {
  const router = useRouter();
  const { token } = useAuth();
  const [data, setData] = useState<MobileRoutinesResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busyId, setBusyId] = useState<number | string | null>(null);
  const [error, setError] = useState('');
  const [showIntegrations, setShowIntegrations] = useState(false);
  const [skillId, setSkillId] = useState('');
  const [savingAlexa, setSavingAlexa] = useState(false);

  const load = useCallback(async (refresh = false) => {
    if (!token) return;
    refresh ? setRefreshing(true) : setLoading(true);
    setError('');
    try {
      const response = await getMobileRoutines(token);
      setData(response);
      setSkillId(response.integraciones.alexa.identificador_externo ?? '');
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No fue posible cargar las rutinas.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [token]);

  useEffect(() => { void load(); }, [load]);

  const execute = useCallback((routine: MobileRoutine) => {
    if (!token) return;
    Alert.alert('Ejecutar rutina', `Se enviaran ${numeric(routine.acciones_total)} acciones de "${routine.nombre}".`, [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Ejecutar', onPress: async () => {
        setBusyId(routine.id);
        try {
          const result = await runMobileRoutine(token, routine.id);
          Alert.alert('Rutina finalizada', `${result.acciones_exitosas} de ${result.acciones_total} acciones aplicadas.`);
          await load(true);
        } catch (caught) {
          Alert.alert('No fue posible ejecutar', caught instanceof ApiError ? caught.message : 'Intenta nuevamente.');
        } finally { setBusyId(null); }
      } },
    ]);
  }, [load, token]);

  const toggle = useCallback(async (routine: MobileRoutine, active: boolean) => {
    if (!token) return;
    setBusyId(routine.id);
    try {
      await setMobileRoutineState(token, routine.id, active);
      await load(true);
    } catch (caught) {
      Alert.alert('No fue posible cambiar la rutina', caught instanceof ApiError ? caught.message : 'Intenta nuevamente.');
    } finally { setBusyId(null); }
  }, [load, token]);

  const prepareAlexa = useCallback(async () => {
    if (!token) return;
    setSavingAlexa(true);
    try {
      const result = await prepareAlexaIntegration(token, skillId.trim());
      Alert.alert(
        result.estado === 'CONFIGURADA' ? 'Alexa vinculada' : 'Configuracion guardada',
        result.estado === 'CONFIGURADA'
          ? 'La cuenta de Alexa ya puede descubrir los equipos compatibles.'
          : 'Ahora habilita la Skill ID Industrial desde la app Alexa y vincula tu cuenta.',
      );
      await load(true);
    } catch (caught) {
      Alert.alert('No fue posible guardar', caught instanceof ApiError ? caught.message : 'Intenta nuevamente.');
    } finally { setSavingAlexa(false); }
  }, [load, skillId, token]);

  const disconnectAlexa = useCallback(() => {
    if (!token) return;
    Alert.alert('Desvincular Alexa', 'Alexa dejara de controlar los equipos de ID Industrial.', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Desvincular', style: 'destructive', onPress: async () => {
        setSavingAlexa(true);
        try {
          await disableAlexaIntegration(token);
          Alert.alert('Alexa desvinculada', 'Los tokens de acceso fueron revocados. Tambien puedes deshabilitar la Skill desde Alexa.');
          await load(true);
        } catch (caught) {
          Alert.alert('No fue posible desvincular', caught instanceof ApiError ? caught.message : 'Intenta nuevamente.');
        } finally { setSavingAlexa(false); }
      } },
    ]);
  }, [load, token]);

  const canManage = data?.permisos.administrar ?? false;
  return (
    <AppScreen
      eyebrow="AUTOMATIZACION"
      title="Rutinas"
      action={(
        <View style={styles.headerActions}>
          {canManage ? (
            <Pressable accessibilityLabel="Agregar rutina" onPress={() => router.push('/rutina/formulario' as Href)} style={styles.addButton}><Ionicons color={colors.black} name="add" size={23} /></Pressable>
          ) : null}
          <Pressable accessibilityLabel="Actualizar rutinas" onPress={() => void load(true)} style={styles.refreshButton}><Ionicons color={colors.text} name="refresh" size={21} /></Pressable>
        </View>
      )}
      scrollProps={{ refreshControl: <RefreshControl colors={[colors.warning]} onRefresh={() => void load(true)} refreshing={refreshing} tintColor={colors.warning} /> }}
    >
      <View style={styles.hero}>
        <View><Text style={styles.eyebrow}>CONTROL PROGRAMADO</Text><Text style={styles.heroTitle}>{data?.rutinas.filter((item) => numeric(item.activa) === 1).length ?? 0} rutinas activas</Text></View>
        <Ionicons color={colors.warning} name="timer-outline" size={32} />
      </View>
      {loading ? <ActivityIndicator color={colors.warning} size="large" /> : null}
      {error ? <View style={styles.errorPanel}><Text style={styles.errorText}>{error}</Text><Pressable onPress={() => void load()} style={styles.outlineButton}><Text style={styles.outlineText}>Reintentar</Text></Pressable></View> : null}
      {!loading && !error && !data?.rutinas.length ? (
        <View style={styles.emptyPanel}>
          <Ionicons color={colors.muted} name="timer-outline" size={30} />
          <Text style={styles.emptyTitle}>Todavia no hay rutinas</Text>
          <Text style={styles.description}>Habilita un Shelly para automatizacion y crea una secuencia manual o por horario.</Text>
        </View>
      ) : null}
      {data?.rutinas.map((routine) => (
        <RoutineCard
          busy={String(busyId) === String(routine.id)}
          canManage={data.permisos.administrar}
          canRun={data.permisos.ejecutar}
          key={String(routine.id)}
          onEdit={() => router.push(`/rutina/formulario?id=${routine.id}` as Href)}
          onRun={() => execute(routine)}
          onToggle={(active) => void toggle(routine, active)}
          routine={routine}
        />
      ))}

      {data ? (
        <View style={styles.section}>
          <Pressable onPress={() => setShowIntegrations((value) => !value)} style={styles.sectionHeader}>
            <View><Text style={styles.eyebrow}>INTEGRACIONES</Text><Text style={styles.sectionTitle}>Servicios conectados</Text></View>
            <Ionicons color={colors.muted} name={showIntegrations ? 'chevron-up' : 'chevron-down'} size={22} />
          </Pressable>
          {showIntegrations ? (
            <View style={styles.integrationList}>
              <View style={styles.integrationRow}>
                <Ionicons color={colors.warning} name="flash-outline" size={24} />
                <View style={styles.integrationCopy}><Text style={styles.integrationTitle}>Shelly Cloud</Text><Text style={styles.description}>{data.integraciones.shelly.equipos_disponibles} equipos habilitados para rutinas</Text></View>
                <Text style={[styles.stateText, { color: data.integraciones.shelly.estado === 'CONFIGURADA' ? colors.success : colors.warning }]}>{data.integraciones.shelly.estado}</Text>
              </View>
              <View style={styles.integrationRow}>
                <Ionicons color={colors.normal} name="mic-outline" size={24} />
                <View style={styles.integrationCopy}><Text style={styles.integrationTitle}>Amazon Alexa</Text><Text style={styles.description}>{data.integraciones.alexa.vinculada ? `${data.integraciones.alexa.equipos_disponibles} equipos disponibles para voz` : 'Habilita la Skill y vincula tu cuenta de ID Industrial.'}</Text></View>
                <Text style={[styles.stateText, { color: data.integraciones.alexa.estado === 'CONFIGURADA' ? colors.success : colors.warning }]}>{data.integraciones.alexa.estado}</Text>
              </View>
              {canManage ? (
                <View style={styles.alexaForm}>
                  <View style={styles.alexaStatusRow}>
                    <Text style={styles.description}>OAuth cPanel</Text>
                    <Text style={[styles.stateText, { color: data.integraciones.alexa.oauth_listo ? colors.success : colors.critical }]}>{data.integraciones.alexa.oauth_listo ? 'LISTO' : 'PENDIENTE'}</Text>
                  </View>
                  <View style={styles.alexaStatusRow}>
                    <Text style={styles.description}>Lambda segura</Text>
                    <Text style={[styles.stateText, { color: data.integraciones.alexa.lambda_lista ? colors.success : colors.critical }]}>{data.integraciones.alexa.lambda_lista ? 'LISTA' : 'PENDIENTE'}</Text>
                  </View>
                  <View style={styles.alexaStatusRow}>
                    <Text style={styles.description}>Canales compatibles</Text>
                    <Text style={styles.integrationTitle}>{data.integraciones.alexa.equipos_disponibles}</Text>
                  </View>
                  <View style={styles.alexaStatusRow}>
                    <Text style={styles.description}>Rutinas visibles como escenas</Text>
                    <Text style={styles.integrationTitle}>{data.integraciones.alexa.rutinas_disponibles}</Text>
                  </View>
                  <Text style={styles.inputLabel}>Alexa Skill ID</Text>
                  <TextInput onChangeText={setSkillId} placeholder="amzn1.ask.skill..." placeholderTextColor={colors.muted} style={styles.input} value={skillId} />
                  <Pressable disabled={savingAlexa} onPress={() => void prepareAlexa()} style={[styles.outlineButton, savingAlexa && styles.disabled]}>
                    {savingAlexa ? <ActivityIndicator color={colors.normal} size="small" /> : <Ionicons color={colors.normal} name="save-outline" size={18} />}
                    <Text style={styles.outlineText}>Guardar configuracion</Text>
                  </Pressable>
                  {data.integraciones.alexa.vinculada ? (
                    <Pressable disabled={savingAlexa} onPress={disconnectAlexa} style={[styles.disconnectButton, savingAlexa && styles.disabled]}>
                      <Ionicons color={colors.critical} name="unlink-outline" size={18} />
                      <Text style={styles.disconnectText}>Desvincular Alexa</Text>
                    </Pressable>
                  ) : null}
                </View>
              ) : null}
            </View>
          ) : null}
        </View>
      ) : null}

      {data?.ejecuciones.length ? (
        <View style={styles.section}>
          <Text style={styles.eyebrow}>AUDITORIA</Text><Text style={styles.sectionTitle}>Ejecuciones recientes</Text>
          {data.ejecuciones.slice(0, 8).map((execution) => (
            <View key={String(execution.id)} style={styles.executionRow}>
              <View style={styles.executionCopy}><Text style={styles.integrationTitle}>{execution.rutina_nombre}</Text><Text style={styles.description}>{execution.origen} · {dateLabel(execution.iniciada_en)} · {numeric(execution.acciones_exitosas)}/{numeric(execution.acciones_total)}</Text></View>
              <Text style={[styles.stateText, { color: executionColor(execution.estado) }]}>{execution.estado}</Text>
            </View>
          ))}
        </View>
      ) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  headerActions: { flexDirection: 'row', gap: spacing.sm },
  refreshButton: { alignItems: 'center', borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, height: 44, justifyContent: 'center', width: 44 },
  addButton: { alignItems: 'center', backgroundColor: colors.warning, borderRadius: radius.md, height: 44, justifyContent: 'center', width: 44 },
  hero: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, flexDirection: 'row', justifyContent: 'space-between', padding: spacing.lg },
  eyebrow: { color: colors.normal, fontSize: 11, fontWeight: '900' },
  heroTitle: { color: colors.textStrong, fontSize: 20, fontWeight: '900', marginTop: spacing.xs },
  routineCard: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  inactiveCard: { opacity: 0.72 },
  cardHeader: { alignItems: 'flex-start', flexDirection: 'row', gap: spacing.sm, justifyContent: 'space-between' },
  cardCopy: { flex: 1, gap: spacing.xs },
  routineName: { color: colors.textStrong, fontSize: 18, fontWeight: '900' },
  routineSchedule: { color: colors.warning, fontSize: 12, fontWeight: '800' },
  description: { color: colors.muted, fontSize: 12, lineHeight: 18 },
  stateText: { fontSize: 10, fontWeight: '900' },
  routineMeta: { flexDirection: 'row', gap: spacing.sm },
  metaLabel: { color: colors.muted, fontSize: 9, fontWeight: '800' },
  metaValue: { color: colors.textStrong, fontSize: 18, fontWeight: '900', marginTop: spacing.xs },
  metaValueSmall: { color: colors.textStrong, fontSize: 11, fontWeight: '800', marginTop: spacing.xs },
  warningPanel: { alignItems: 'center', backgroundColor: colors.surfaceStrong, borderColor: colors.warning, borderRadius: radius.sm, borderWidth: 1, flexDirection: 'row', gap: spacing.sm, padding: spacing.sm },
  warningText: { color: colors.warning, flex: 1, fontSize: 11 },
  cardActions: { flexDirection: 'row', gap: spacing.sm },
  runButton: { alignItems: 'center', backgroundColor: colors.warning, borderRadius: radius.sm, flex: 1, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 44 },
  runText: { color: colors.black, fontSize: 13, fontWeight: '900' },
  outlineButton: { alignItems: 'center', borderColor: colors.normal, borderRadius: radius.sm, borderWidth: 1, flex: 1, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 44, paddingHorizontal: spacing.md },
  outlineText: { color: colors.normal, fontSize: 12, fontWeight: '900' },
  disabled: { opacity: 0.45 },
  section: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  sectionHeader: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between' },
  sectionTitle: { color: colors.textStrong, fontSize: 18, fontWeight: '900', marginTop: spacing.xs },
  integrationList: { gap: spacing.md },
  integrationRow: { alignItems: 'center', borderBottomColor: colors.borderSoft, borderBottomWidth: 1, flexDirection: 'row', gap: spacing.md, paddingVertical: spacing.sm },
  integrationCopy: { flex: 1, gap: spacing.xs },
  integrationTitle: { color: colors.textStrong, fontSize: 13, fontWeight: '800' },
  alexaForm: { backgroundColor: colors.surfaceStrong, borderRadius: radius.sm, gap: spacing.sm, padding: spacing.md },
  alexaStatusRow: { alignItems: 'center', borderBottomColor: colors.borderSoft, borderBottomWidth: 1, flexDirection: 'row', justifyContent: 'space-between', paddingBottom: spacing.sm },
  disconnectButton: { alignItems: 'center', borderColor: colors.critical, borderRadius: radius.sm, borderWidth: 1, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 44 },
  disconnectText: { color: colors.critical, fontSize: 12, fontWeight: '900' },
  inputLabel: { color: colors.text, fontSize: 11, fontWeight: '800' },
  input: { borderColor: colors.border, borderRadius: radius.sm, borderWidth: 1, color: colors.textStrong, minHeight: 46, paddingHorizontal: spacing.md },
  executionRow: { alignItems: 'center', borderBottomColor: colors.borderSoft, borderBottomWidth: 1, flexDirection: 'row', gap: spacing.sm, paddingVertical: spacing.sm },
  executionCopy: { flex: 1, gap: spacing.xs },
  emptyPanel: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.sm, padding: spacing.xl },
  emptyTitle: { color: colors.textStrong, fontSize: 17, fontWeight: '900' },
  errorPanel: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.critical, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  errorText: { color: colors.critical, textAlign: 'center' },
});
