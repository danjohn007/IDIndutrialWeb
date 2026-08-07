import Ionicons from '@expo/vector-icons/Ionicons';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { useAuth } from '@/context/auth-context';
import { ApiError, getMobileRoutineDetail, getMobileRoutines, saveMobileRoutine } from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileRoutineAction, MobileRoutineActuator, MobileRoutineSaveInput } from '@/types/api';

const days = [
  { value: 1, label: 'L' }, { value: 2, label: 'M' }, { value: 3, label: 'M' },
  { value: 4, label: 'J' }, { value: 5, label: 'V' }, { value: 6, label: 'S' },
  { value: 7, label: 'D' },
];

type FormAction = { actuador_id: string; accion: 'ENCENDER' | 'APAGAR' };

function Field({ label, value, onChangeText, placeholder, multiline = false }: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder?: string;
  multiline?: boolean;
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        multiline={multiline}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={colors.muted}
        style={[styles.input, multiline && styles.textarea]}
        value={value}
      />
    </View>
  );
}

function Choice<T extends string>({ label, value, options, onChange }: {
  label: string;
  value: T;
  options: Array<{ value: T; label: string }>;
  onChange: (value: T) => void;
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <View style={styles.choices}>
        {options.map((option) => {
          const selected = option.value === value;
          return (
            <Pressable key={option.value} onPress={() => onChange(option.value)} style={[styles.choice, selected && styles.choiceSelected]}>
              <Text style={[styles.choiceText, selected && styles.choiceTextSelected]}>{option.label}</Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

function actuatorName(actuator: MobileRoutineActuator | undefined): string {
  return actuator?.nombre || actuator?.id || 'Equipo no disponible';
}

export default function RoutineFormScreen() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const editing = Boolean(id);
  const router = useRouter();
  const { token, user } = useAuth();
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [trigger, setTrigger] = useState<'MANUAL' | 'HORARIO'>('MANUAL');
  const [time, setTime] = useState('08:00');
  const [selectedDays, setSelectedDays] = useState<number[]>([1, 2, 3, 4, 5]);
  const [timezone, setTimezone] = useState('America/Mexico_City');
  const [active, setActive] = useState(true);
  const [actuators, setActuators] = useState<MobileRoutineActuator[]>([]);
  const [actions, setActions] = useState<FormAction[]>([]);
  const [pendingActuator, setPendingActuator] = useState('');
  const [pendingAction, setPendingAction] = useState<'ENCENDER' | 'APAGAR'>('ENCENDER');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (user?.rol !== 'ADMIN') router.replace('/(tabs)/rutinas' as Href);
  }, [router, user?.rol]);

  useEffect(() => {
    try {
      const localZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (localZone) setTimezone(localZone);
    } catch {
      // Conserva la zona predeterminada cuando el dispositivo no la reporta.
    }
  }, []);

  useEffect(() => {
    if (!token) return;
    const load = async () => {
      setError('');
      try {
        if (editing && id) {
          const detail = await getMobileRoutineDetail(token, id);
          setActuators(detail.actuadores);
          setPendingActuator(detail.actuadores[0]?.id ?? '');
          setName(detail.rutina.nombre);
          setDescription(detail.rutina.descripcion ?? '');
          setTrigger(detail.rutina.tipo_disparador);
          setTime((detail.rutina.hora_local ?? '08:00').slice(0, 5));
          setSelectedDays(detail.rutina.dias);
          setTimezone(detail.rutina.zona_horaria);
          setActive(Number(detail.rutina.activa) === 1);
          setActions(detail.rutina.acciones.map((action: MobileRoutineAction) => ({ actuador_id: action.actuador_id, accion: action.accion })));
        } else {
          const response = await getMobileRoutines(token);
          setActuators(response.actuadores);
          setPendingActuator(response.actuadores[0]?.id ?? '');
        }
      } catch (caught) {
        setError(caught instanceof ApiError ? caught.message : 'No fue posible preparar el formulario.');
      } finally { setLoading(false); }
    };
    void load();
  }, [editing, id, token]);

  const availableToAdd = useMemo(
    () => actuators.filter((actuator) => !actions.some((action) => action.actuador_id === actuator.id)),
    [actions, actuators],
  );

  useEffect(() => {
    if (!availableToAdd.some((actuator) => actuator.id === pendingActuator)) {
      setPendingActuator(availableToAdd[0]?.id ?? '');
    }
  }, [availableToAdd, pendingActuator]);

  const toggleDay = useCallback((day: number) => {
    setSelectedDays((current) => current.includes(day)
      ? current.filter((item) => item !== day)
      : [...current, day].sort());
  }, []);

  const addAction = useCallback(() => {
    if (!pendingActuator) return;
    if (actions.length >= 5) {
      Alert.alert('Limite alcanzado', 'Cada rutina admite hasta cinco acciones.');
      return;
    }
    setActions((current) => [...current, { actuador_id: pendingActuator, accion: pendingAction }]);
  }, [actions.length, pendingAction, pendingActuator]);

  const updateAction = useCallback((index: number, action: 'ENCENDER' | 'APAGAR') => {
    setActions((current) => current.map((item, itemIndex) => itemIndex === index ? { ...item, accion: action } : item));
  }, []);

  const payload = useMemo<MobileRoutineSaveInput>(() => ({
    accion: editing ? 'ACTUALIZAR' : 'CREAR',
    ...(editing ? { id: Number(id) } : {}),
    nombre: name.trim(),
    descripcion: description.trim(),
    tipo_disparador: trigger,
    hora_local: trigger === 'HORARIO' ? time.trim() : null,
    dias: trigger === 'HORARIO' ? selectedDays : [],
    zona_horaria: timezone,
    activa: active,
    acciones: actions,
  }), [actions, active, description, editing, id, name, selectedDays, time, timezone, trigger]);

  const save = useCallback(async () => {
    if (!token) return;
    if (payload.nombre.length < 3 || !payload.acciones.length) {
      Alert.alert('Rutina incompleta', 'Escribe un nombre y agrega al menos una accion.');
      return;
    }
    if (payload.tipo_disparador === 'HORARIO' && (!/^\d{2}:\d{2}$/.test(payload.hora_local ?? '') || !payload.dias.length)) {
      Alert.alert('Horario incompleto', 'Usa una hora HH:MM y selecciona al menos un dia.');
      return;
    }
    setSaving(true);
    try {
      await saveMobileRoutine(token, payload);
      Alert.alert('Rutina guardada', 'La automatizacion quedo disponible.', [
        { text: 'Continuar', onPress: () => router.replace('/(tabs)/rutinas' as Href) },
      ]);
    } catch (caught) {
      Alert.alert('No fue posible guardar', caught instanceof ApiError ? caught.message : 'Revisa los datos e intenta nuevamente.');
    } finally { setSaving(false); }
  }, [payload, router, token]);

  return (
    <AppScreen
      eyebrow="AUTOMATIZACION"
      title={editing ? 'Editar rutina' : 'Nueva rutina'}
      leading={<Pressable accessibilityLabel="Volver" onPress={() => router.back()} style={styles.iconButton}><Ionicons color={colors.text} name="arrow-back" size={22} /></Pressable>}
      scrollProps={{ keyboardShouldPersistTaps: 'handled' }}
    >
      {loading ? <ActivityIndicator color={colors.warning} size="large" /> : null}
      {error ? <View style={styles.errorPanel}><Text style={styles.errorText}>{error}</Text></View> : null}
      {!loading && !error ? (
        <>
          <View style={styles.section}>
            <Text style={styles.eyebrow}>1 · IDENTIDAD</Text><Text style={styles.sectionTitle}>Nombre y objetivo</Text>
            <Field label="Nombre" onChangeText={setName} placeholder="Inicio de jornada" value={name} />
            <Field label="Descripcion opcional" multiline onChangeText={setDescription} placeholder="Enciende los equipos necesarios al comenzar el turno" value={description} />
            <View style={styles.toggleRow}><View style={styles.toggleCopy}><Text style={styles.toggleTitle}>Rutina activa</Text><Text style={styles.helper}>Las rutinas pausadas no se ejecutan manualmente ni por cron.</Text></View><Switch onValueChange={setActive} thumbColor={active ? colors.success : colors.muted} trackColor={{ false: colors.border, true: colors.surfaceRaised }} value={active} /></View>
          </View>

          <View style={styles.section}>
            <Text style={styles.eyebrow}>2 · DISPARADOR</Text><Text style={styles.sectionTitle}>Cuando debe ejecutarse</Text>
            <Choice label="Tipo" onChange={setTrigger} options={[{ label: 'Manual', value: 'MANUAL' }, { label: 'Por horario', value: 'HORARIO' }]} value={trigger} />
            {trigger === 'HORARIO' ? (
              <>
                <Field label="Hora local (HH:MM)" onChangeText={setTime} placeholder="08:00" value={time} />
                <View style={styles.field}><Text style={styles.label}>Dias</Text><View style={styles.dayRow}>{days.map((day) => { const selected = selectedDays.includes(day.value); return <Pressable key={day.value} onPress={() => toggleDay(day.value)} style={[styles.day, selected && styles.daySelected]}><Text style={[styles.dayText, selected && styles.dayTextSelected]}>{day.label}</Text></Pressable>; })}</View></View>
                <View style={styles.zonePanel}><Ionicons color={colors.normal} name="globe-outline" size={18} /><View style={styles.toggleCopy}><Text style={styles.toggleTitle}>Zona horaria</Text><Text style={styles.helper}>{timezone}</Text></View></View>
              </>
            ) : <Text style={styles.helper}>El usuario debera confirmar cada ejecucion desde la app.</Text>}
          </View>

          <View style={styles.section}>
            <Text style={styles.eyebrow}>3 · ACCIONES</Text><Text style={styles.sectionTitle}>Equipos que responderan</Text>
            {!actuators.length ? (
              <View style={styles.emptyPanel}><Ionicons color={colors.warning} name="warning-outline" size={24} /><Text style={styles.emptyTitle}>No hay canales habilitados</Text><Text style={styles.helper}>Las sirenas y salidas de seguridad no participan. Usa un canal Shelly destinado a una carga no critica, clasificalo como Automatizacion y activa Permitir rutinas.</Text></View>
            ) : null}
            {actions.map((action, index) => {
              const actuator = actuators.find((item) => item.id === action.actuador_id);
              return (
                <View key={action.actuador_id} style={styles.actionCard}>
                  <View style={styles.actionHeader}><View style={styles.toggleCopy}><Text style={styles.actionName}>{index + 1}. {actuatorName(actuator)}</Text><Text style={styles.helper}>{actuator?.ubicacion} · Canal {actuator?.canal}</Text></View><Pressable accessibilityLabel="Quitar accion" onPress={() => setActions((current) => current.filter((_, itemIndex) => itemIndex !== index))} style={styles.removeButton}><Ionicons color={colors.critical} name="trash-outline" size={19} /></Pressable></View>
                  <Choice label="Orden" onChange={(value) => updateAction(index, value)} options={[{ label: 'Encender', value: 'ENCENDER' }, { label: 'Apagar', value: 'APAGAR' }]} value={action.accion} />
                </View>
              );
            })}
            {availableToAdd.length && actions.length < 5 ? (
              <View style={styles.addPanel}>
                <Text style={styles.label}>Agregar equipo</Text>
                <View style={styles.choices}>{availableToAdd.map((actuator) => { const selected = actuator.id === pendingActuator; return <Pressable key={actuator.id} onPress={() => setPendingActuator(actuator.id)} style={[styles.deviceChoice, selected && styles.deviceChoiceSelected]}><Text style={[styles.choiceText, selected && styles.choiceTextSelected]}>{actuatorName(actuator)}</Text><Text style={[styles.deviceMeta, selected && styles.deviceMetaSelected]}>{actuator.ubicacion}</Text></Pressable>; })}</View>
                <Choice label="Accion" onChange={setPendingAction} options={[{ label: 'Encender', value: 'ENCENDER' }, { label: 'Apagar', value: 'APAGAR' }]} value={pendingAction} />
                <Pressable onPress={addAction} style={styles.addActionButton}><Ionicons color={colors.normal} name="add" size={20} /><Text style={styles.addActionText}>Agregar accion</Text></Pressable>
              </View>
            ) : null}
          </View>

          <Pressable disabled={saving || !actuators.length} onPress={() => void save()} style={[styles.saveButton, (saving || !actuators.length) && styles.disabled]}>{saving ? <ActivityIndicator color={colors.black} size="small" /> : <Ionicons color={colors.black} name="save-outline" size={20} />}<Text style={styles.saveText}>{editing ? 'Guardar cambios' : 'Crear rutina'}</Text></Pressable>
        </>
      ) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  iconButton: { alignItems: 'center', borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, height: 44, justifyContent: 'center', width: 44 },
  section: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  eyebrow: { color: colors.normal, fontSize: 11, fontWeight: '900' },
  sectionTitle: { color: colors.textStrong, fontSize: 19, fontWeight: '900' },
  field: { gap: spacing.sm },
  label: { color: colors.text, fontSize: 12, fontWeight: '800' },
  input: { backgroundColor: colors.surfaceStrong, borderColor: colors.border, borderRadius: radius.sm, borderWidth: 1, color: colors.textStrong, fontSize: 15, minHeight: 48, paddingHorizontal: spacing.md, paddingVertical: spacing.sm },
  textarea: { minHeight: 84, textAlignVertical: 'top' },
  choices: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  choice: { borderColor: colors.border, borderRadius: radius.sm, borderWidth: 1, justifyContent: 'center', minHeight: 42, paddingHorizontal: spacing.md },
  choiceSelected: { backgroundColor: colors.normal, borderColor: colors.normal },
  choiceText: { color: colors.text, fontSize: 12, fontWeight: '800' },
  choiceTextSelected: { color: colors.black },
  helper: { color: colors.muted, fontSize: 11, lineHeight: 17 },
  toggleRow: { alignItems: 'center', borderTopColor: colors.borderSoft, borderTopWidth: 1, flexDirection: 'row', gap: spacing.md, justifyContent: 'space-between', paddingTop: spacing.md },
  toggleCopy: { flex: 1, gap: spacing.xs },
  toggleTitle: { color: colors.textStrong, fontSize: 13, fontWeight: '800' },
  dayRow: { flexDirection: 'row', gap: spacing.xs, justifyContent: 'space-between' },
  day: { alignItems: 'center', borderColor: colors.border, borderRadius: radius.sm, borderWidth: 1, flex: 1, height: 42, justifyContent: 'center' },
  daySelected: { backgroundColor: colors.warning, borderColor: colors.warning },
  dayText: { color: colors.muted, fontSize: 12, fontWeight: '900' },
  dayTextSelected: { color: colors.black },
  zonePanel: { alignItems: 'center', backgroundColor: colors.surfaceStrong, borderRadius: radius.sm, flexDirection: 'row', gap: spacing.md, padding: spacing.md },
  actionCard: { backgroundColor: colors.surfaceStrong, borderColor: colors.borderSoft, borderRadius: radius.sm, borderWidth: 1, gap: spacing.md, padding: spacing.md },
  actionHeader: { alignItems: 'flex-start', flexDirection: 'row', gap: spacing.sm },
  actionName: { color: colors.textStrong, fontSize: 14, fontWeight: '900' },
  removeButton: { alignItems: 'center', borderColor: colors.critical, borderRadius: radius.sm, borderWidth: 1, height: 38, justifyContent: 'center', width: 38 },
  addPanel: { borderColor: colors.border, borderRadius: radius.sm, borderStyle: 'dashed', borderWidth: 1, gap: spacing.md, padding: spacing.md },
  deviceChoice: { borderColor: colors.border, borderRadius: radius.sm, borderWidth: 1, gap: spacing.xs, minHeight: 52, padding: spacing.sm },
  deviceChoiceSelected: { backgroundColor: colors.normal, borderColor: colors.normal },
  deviceMeta: { color: colors.muted, fontSize: 10 },
  deviceMetaSelected: { color: colors.black },
  addActionButton: { alignItems: 'center', borderColor: colors.normal, borderRadius: radius.sm, borderWidth: 1, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 44 },
  addActionText: { color: colors.normal, fontSize: 12, fontWeight: '900' },
  saveButton: { alignItems: 'center', backgroundColor: colors.warning, borderRadius: radius.sm, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 52 },
  saveText: { color: colors.black, fontSize: 15, fontWeight: '900' },
  disabled: { opacity: 0.45 },
  emptyPanel: { alignItems: 'center', backgroundColor: colors.surfaceStrong, borderRadius: radius.sm, gap: spacing.sm, padding: spacing.lg },
  emptyTitle: { color: colors.textStrong, fontSize: 15, fontWeight: '900' },
  errorPanel: { backgroundColor: colors.surface, borderColor: colors.critical, borderRadius: radius.md, borderWidth: 1, padding: spacing.lg },
  errorText: { color: colors.critical, textAlign: 'center' },
});
