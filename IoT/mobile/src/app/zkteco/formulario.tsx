import Ionicons from '@expo/vector-icons/Ionicons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { useAuth } from '@/context/auth-context';
import { ApiError, getMobileZktecoDetail, saveMobileZkteco } from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileZktecoSaveInput } from '@/types/api';

const categories: Array<{ value: MobileZktecoSaveInput['categoria']; label: string }> = [
  { value: 'ASISTENCIA', label: 'Asistencia' },
  { value: 'CONTROL_ACCESO', label: 'Control de acceso' },
  { value: 'HIBRIDO', label: 'Hibrido' },
  { value: 'OTRO', label: 'Otro' },
];

const protocols: Array<{ value: MobileZktecoSaveInput['protocolo']; label: string }> = [
  { value: 'PULL_4370', label: 'Pull local' },
  { value: 'PUSH_ADMS', label: 'Push ADMS' },
  { value: 'WDMS_API', label: 'ZKBio WDMS' },
];

const equipmentStatuses: Array<{ value: MobileZktecoSaveInput['estado']; label: string }> = [
  { value: 'Activo', label: 'Activo' },
  { value: 'Mantenimiento', label: 'Mantenimiento' },
  { value: 'Inactivo', label: 'Baja' },
];

function Field({ label, value, onChangeText, placeholder, editable = true, keyboardType = 'default' }: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder?: string;
  editable?: boolean;
  keyboardType?: 'default' | 'number-pad';
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        editable={editable}
        keyboardType={keyboardType}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={colors.muted}
        style={[styles.input, !editable && styles.inputDisabled]}
        value={value}
      />
    </View>
  );
}

export default function ZktecoFormScreen() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const editing = Boolean(id);
  const router = useRouter();
  const { token, user } = useAuth();
  const [loading, setLoading] = useState(editing);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    id: '',
    nombre: '',
    ubicacion: '',
    categoria: 'ASISTENCIA' as MobileZktecoSaveInput['categoria'],
    modelo: '',
    numeroSerie: '',
    ipLocal: '',
    puerto: '4370',
    protocolo: 'PULL_4370' as MobileZktecoSaveInput['protocolo'],
    numeroMaquina: '1',
    estado: 'Activo' as MobileZktecoSaveInput['estado'],
  });
  const update = <K extends keyof typeof form>(key: K, value: (typeof form)[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
  };

  useEffect(() => {
    if (user?.rol !== 'ADMIN') router.replace('/(tabs)/dispositivos');
  }, [router, user?.rol]);

  useEffect(() => {
    if (!editing || !id || !token) return;
    getMobileZktecoDetail(token, id).then(({ equipo }) => setForm({
      id: equipo.id,
      nombre: equipo.nombre,
      ubicacion: equipo.ubicacion,
      categoria: equipo.categoria,
      modelo: equipo.modelo ?? '',
      numeroSerie: equipo.numero_serie ?? '',
      ipLocal: equipo.ip_local ?? '',
      puerto: String(equipo.puerto),
      protocolo: equipo.protocolo,
      numeroMaquina: String(equipo.numero_maquina),
      estado: equipo.estado,
    })).catch((error) => {
      Alert.alert('No fue posible cargar el equipo', error instanceof ApiError ? error.message : 'Intenta nuevamente.');
    }).finally(() => setLoading(false));
  }, [editing, id, token]);

  const save = async () => {
    if (!token) return;
    const ipRequired = form.protocolo === 'PULL_4370';
    if (
      !/^[A-Za-z0-9][A-Za-z0-9._-]{2,63}$/.test(form.id)
      || form.nombre.trim().length < 2
      || form.ubicacion.trim().length < 2
      || (ipRequired && !form.ipLocal.trim())
    ) {
      Alert.alert('Revisa los datos', 'Completa ID, nombre, ubicacion y la IP cuando uses Pull local.');
      return;
    }
    const port = Number(form.puerto);
    const machine = Number(form.numeroMaquina);
    if (!Number.isInteger(port) || port < 1 || port > 65535) {
      Alert.alert('Puerto invalido', 'Usa un valor entre 1 y 65535.');
      return;
    }
    if (!Number.isInteger(machine) || machine < 1 || machine > 65535) {
      Alert.alert('Numero de maquina invalido', 'Usa un valor entre 1 y 65535.');
      return;
    }
    const saveNow = async () => {
      setSaving(true);
      try {
        await saveMobileZkteco(token, {
          accion: editing ? 'ACTUALIZAR' : 'CREAR',
          id: form.id.trim(),
          nombre: form.nombre.trim(),
          ubicacion: form.ubicacion.trim(),
          categoria: form.categoria,
          modelo: form.modelo.trim(),
          numero_serie: form.numeroSerie.trim(),
          ip_local: form.ipLocal.trim(),
          puerto: port,
          protocolo: form.protocolo,
          numero_maquina: machine,
          estado: form.estado,
        });
        router.back();
      } catch (error) {
        Alert.alert('No se pudo guardar', error instanceof ApiError ? error.message : 'Intenta nuevamente.');
      } finally {
        setSaving(false);
      }
    };
    if (editing && form.estado === 'Inactivo') {
      Alert.alert('Dar de baja equipo', 'El equipo deja de usarse, pero su historial se conserva.', [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Dar de baja', style: 'destructive', onPress: () => void saveNow() },
      ]);
      return;
    }
    await saveNow();
  };

  return (
    <AppScreen
      eyebrow="ZKTECO · CONECTOR LOCAL"
      title={editing ? 'Editar equipo' : 'Nuevo equipo'}
      leading={(
        <Pressable accessibilityLabel="Volver" onPress={() => router.back()} style={styles.iconButton}>
          <Ionicons color={colors.text} name="arrow-back" size={22} />
        </Pressable>
      )}
    >
      {loading ? <ActivityIndicator color={colors.warning} size="large" /> : (
        <>
          <View style={styles.notice}>
            <Ionicons color={colors.normal} name="shield-checkmark-outline" size={22} />
            <Text style={styles.noticeText}>
              La app no guarda huellas, rostros ni passwords del terminal. Las credenciales y el SDK se configuran solo en el conector local.
            </Text>
          </View>
          <View style={styles.card}>
            <Text style={styles.sectionLabel}>IDENTIDAD</Text>
            <Field editable={!editing} label="ID interno" onChangeText={(value) => update('id', value)} placeholder="ZK_001" value={form.id} />
            <Field label="Nombre visible" onChangeText={(value) => update('nombre', value)} placeholder="Checador principal" value={form.nombre} />
            <Field label="Ubicacion" onChangeText={(value) => update('ubicacion', value)} placeholder="Recepcion" value={form.ubicacion} />
            <Text style={styles.label}>Categoria</Text>
            <View style={styles.chips}>{categories.map((item) => (
              <Pressable key={item.value} onPress={() => update('categoria', item.value)} style={[styles.chip, form.categoria === item.value && styles.chipSelected]}>
                <Text style={[styles.chipText, form.categoria === item.value && styles.chipTextSelected]}>{item.label}</Text>
              </Pressable>
            ))}</View>
            <Field label="Modelo (opcional)" onChangeText={(value) => update('modelo', value)} placeholder="F18" value={form.modelo} />
            <Field label="Numero de serie (opcional)" onChangeText={(value) => update('numeroSerie', value)} value={form.numeroSerie} />
          </View>

          <View style={styles.card}>
            <Text style={styles.sectionLabel}>COMUNICACION</Text>
            <Text style={styles.label}>Protocolo</Text>
            <View style={styles.chips}>{protocols.map((item) => (
              <Pressable key={item.value} onPress={() => update('protocolo', item.value)} style={[styles.chip, form.protocolo === item.value && styles.chipSelected]}>
                <Text style={[styles.chipText, form.protocolo === item.value && styles.chipTextSelected]}>{item.label}</Text>
              </Pressable>
            ))}</View>
            <Field label="IP o host local" onChangeText={(value) => update('ipLocal', value)} placeholder="192.168.1.201" value={form.ipLocal} />
            <Field keyboardType="number-pad" label="Puerto" onChangeText={(value) => update('puerto', value)} value={form.puerto} />
            <Field keyboardType="number-pad" label="Numero de maquina" onChangeText={(value) => update('numeroMaquina', value)} value={form.numeroMaquina} />
          </View>

          <View style={styles.card}>
            <Text style={styles.sectionLabel}>OPERACION</Text>
            <Text style={styles.label}>Estado del equipo</Text>
            <View style={styles.chips}>{equipmentStatuses.map((item) => (
              <Pressable key={item.value} onPress={() => update('estado', item.value)} style={[styles.chip, form.estado === item.value && styles.chipSelected]}>
                <Text style={[styles.chipText, form.estado === item.value && styles.chipTextSelected]}>{item.label}</Text>
              </Pressable>
            ))}</View>
            <Text style={styles.helper}>Baja no borra el equipo: solo lo deja fuera de operacion y conserva su historial.</Text>
          </View>

          <Pressable disabled={saving} onPress={() => void save()} style={[styles.saveButton, saving && styles.disabled]}>
            {saving ? <ActivityIndicator color={colors.black} /> : (
              <><Ionicons color={colors.black} name="save-outline" size={20} /><Text style={styles.saveText}>Guardar equipo</Text></>
            )}
          </Pressable>
        </>
      )}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  iconButton: { alignItems: 'center', borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, height: 44, justifyContent: 'center', width: 44 },
  notice: { alignItems: 'flex-start', backgroundColor: colors.surface, borderColor: colors.normal, borderRadius: radius.md, borderWidth: 1, flexDirection: 'row', gap: spacing.md, padding: spacing.lg },
  noticeText: { color: colors.text, flex: 1, fontSize: 13, lineHeight: 19 },
  card: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  sectionLabel: { color: colors.normal, fontSize: 11, fontWeight: '900' },
  helper: { color: colors.muted, fontSize: 12, lineHeight: 18 },
  field: { gap: spacing.xs },
  label: { color: colors.text, fontSize: 13, fontWeight: '800' },
  input: { backgroundColor: colors.surfaceStrong, borderColor: colors.border, borderRadius: radius.sm, borderWidth: 1, color: colors.textStrong, minHeight: 48, paddingHorizontal: spacing.md },
  inputDisabled: { opacity: 0.6 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  chip: { borderColor: colors.border, borderRadius: radius.sm, borderWidth: 1, minHeight: 40, paddingHorizontal: spacing.md, paddingVertical: spacing.sm },
  chipSelected: { backgroundColor: colors.warning, borderColor: colors.warning },
  chipText: { color: colors.muted, fontSize: 12, fontWeight: '800' },
  chipTextSelected: { color: colors.black },
  saveButton: { alignItems: 'center', backgroundColor: colors.warning, borderRadius: radius.sm, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 50 },
  saveText: { color: colors.black, fontSize: 15, fontWeight: '900' },
  disabled: { opacity: 0.55 },
});
