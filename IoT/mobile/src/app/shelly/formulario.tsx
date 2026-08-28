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
import {
  ApiError,
  detectMobileShelly,
  getMobileDevices,
  getMobileShellyDetail,
  saveMobileShelly,
} from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileShellySaveInput } from '@/types/api';

type FormState = {
  id: string;
  nombre: string;
  ubicacion: string;
  shellyDeviceId: string;
  modelo: string;
  generacion: MobileShellySaveInput['generacion'];
  ipLocal: string;
  canal: string;
  funcion: MobileShellySaveInput['funcion'];
  categoria: MobileShellySaveInput['categoria'];
  tipoCarga: MobileShellySaveInput['tipo_carga'];
  corrienteMax: string;
  potenciaMax: string;
  tiempoMaxMinutos: string;
  apagadoAutomatico: boolean;
  permiteRutinas: boolean;
  requiereConfirmacion: boolean;
  notificarCambiosExternos: boolean;
  descripcion: string;
  modoControl: MobileShellySaveInput['modo_control'];
  vinculadoId: string;
  estado: MobileShellySaveInput['estado'];
};

const initialForm: FormState = {
  id: '', nombre: '', ubicacion: '', shellyDeviceId: '', modelo: 'Shelly',
  generacion: 'GEN2_PLUS', ipLocal: '', canal: '0', funcion: 'SIRENA',
  categoria: 'SEGURIDAD', tipoCarga: 'DESCONOCIDA', corrienteMax: '',
  potenciaMax: '', tiempoMaxMinutos: '', apagadoAutomatico: false,
  permiteRutinas: false, requiereConfirmacion: true, notificarCambiosExternos: true,
  descripcion: '',
  modoControl: 'HIBRIDO', vinculadoId: '', estado: 'Activo',
};

function optionalNumber(value: string): number | null {
  if (!value.trim()) return null;
  const parsed = Number(value.replace(',', '.'));
  return Number.isFinite(parsed) ? parsed : null;
}

function Field({
  label,
  value,
  onChangeText,
  placeholder,
  editable = true,
  keyboardType = 'default',
  multiline = false,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder?: string;
  editable?: boolean;
  keyboardType?: 'default' | 'decimal-pad' | 'number-pad';
  multiline?: boolean;
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        editable={editable}
        keyboardType={keyboardType}
        multiline={multiline}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={colors.muted}
        style={[styles.input, !editable && styles.inputDisabled, multiline && styles.textarea]}
        value={value}
      />
    </View>
  );
}

function Chips<T extends string>({
  label,
  options,
  value,
  onChange,
}: {
  label: string;
  options: Array<{ label: string; value: T }>;
  value: T;
  onChange: (value: T) => void;
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <View style={styles.chips}>
        {options.map((option) => {
          const selected = option.value === value;
          return (
            <Pressable key={option.value} onPress={() => onChange(option.value)} style={[styles.chip, selected && styles.chipSelected]}>
              <Text style={[styles.chipText, selected && styles.chipTextSelected]}>{option.label}</Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

function ToggleRow({ label, description, value, onValueChange, disabled = false }: {
  label: string;
  description: string;
  value: boolean;
  onValueChange: (value: boolean) => void;
  disabled?: boolean;
}) {
  return (
    <View style={[styles.toggleRow, disabled && styles.disabled]}>
      <View style={styles.toggleCopy}>
        <Text style={styles.toggleTitle}>{label}</Text>
        <Text style={styles.toggleDescription}>{description}</Text>
      </View>
      <Switch
        disabled={disabled}
        onValueChange={onValueChange}
        thumbColor={value ? colors.warning : colors.muted}
        trackColor={{ false: colors.border, true: colors.surfaceRaised }}
        value={value}
      />
    </View>
  );
}

export default function ShellyFormScreen() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const editing = Boolean(id);
  const router = useRouter();
  const { token, user } = useAuth();
  const [form, setForm] = useState<FormState>(initialForm);
  const [esp32, setEsp32] = useState<Array<{ id: string; ubicacion: string }>>([]);
  const [detectedChannels, setDetectedChannels] = useState<number[]>([]);
  const [loading, setLoading] = useState(editing);
  const [detecting, setDetecting] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const update = useCallback(<K extends keyof FormState>(key: K, value: FormState[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
  }, []);

  useEffect(() => {
    if (user?.rol !== 'ADMIN') router.replace('/(tabs)/dispositivos');
  }, [router, user?.rol]);

  useEffect(() => {
    if (!token) return;
    const load = async () => {
      setError('');
      try {
        if (editing && id) {
          const detail = await getMobileShellyDetail(token, id);
          const item = detail.actuador;
          setEsp32(detail.dispositivos_esp32);
          setForm({
            id: item.id,
            nombre: item.nombre ?? item.id,
            ubicacion: item.ubicacion,
            shellyDeviceId: item.shelly_device_id,
            modelo: item.modelo,
            generacion: item.generacion,
            ipLocal: item.ip_local ?? '',
            canal: String(item.canal),
            funcion: item.funcion as FormState['funcion'],
            categoria: item.categoria,
            tipoCarga: item.tipo_carga,
            corrienteMax: item.corriente_max_a == null ? '' : String(item.corriente_max_a),
            potenciaMax: item.potencia_max_w == null ? '' : String(item.potencia_max_w),
            tiempoMaxMinutos: item.tiempo_max_encendido_s == null ? '' : String(Math.ceil(Number(item.tiempo_max_encendido_s) / 60)),
            apagadoAutomatico: Number(item.apagado_automatico) === 1,
            permiteRutinas: Number(item.permite_rutinas) === 1,
            requiereConfirmacion: Number(item.requiere_confirmacion) === 1,
            notificarCambiosExternos: Number(item.notificar_cambios_externos) === 1,
            descripcion: item.descripcion ?? '',
            modoControl: item.modo_control,
            vinculadoId: item.dispositivo_vinculado_id ?? '',
            estado: item.estado,
          });
        } else {
          const devices = await getMobileDevices(token);
          setEsp32(devices.dispositivos.map((device) => ({ id: device.id, ubicacion: device.ubicacion })));
        }
      } catch (caught) {
        setError(caught instanceof ApiError ? caught.message : 'No fue posible preparar el formulario.');
      } finally {
        setLoading(false);
      }
    };
    void load();
  }, [editing, id, token]);

  const runDetection = useCallback(async () => {
    if (!token || !form.shellyDeviceId.trim()) {
      Alert.alert('Device ID requerido', 'Escribe el identificador mostrado por Shelly Smart Control.');
      return;
    }
    setDetecting(true);
    try {
      const result = await detectMobileShelly(token, form.shellyDeviceId.trim());
      setDetectedChannels(result.canales);
      setForm((current) => ({
        ...current,
        modelo: result.modelo || current.modelo,
        generacion: result.generacion,
        canal: result.canales.includes(Number(current.canal)) ? current.canal : String(result.canales[0] ?? 0),
      }));
      Alert.alert(result.online ? 'Shelly detectado' : 'Shelly encontrado', result.online ? 'Cloud confirmo que el dispositivo esta en linea.' : 'El dispositivo existe, pero Cloud lo reporta offline.');
    } catch (caught) {
      Alert.alert('No fue posible detectar el Shelly', caught instanceof ApiError ? caught.message : 'Revisa el Device ID.');
    } finally {
      setDetecting(false);
    }
  }, [form.canal, form.shellyDeviceId, token]);

  const payload = useMemo<MobileShellySaveInput>(() => ({
    accion: editing ? 'ACTUALIZAR' : 'CREAR',
    id: form.id.trim(), nombre: form.nombre.trim(), ubicacion: form.ubicacion.trim(),
    shelly_device_id: form.shellyDeviceId.trim(), modelo: form.modelo.trim(),
    generacion: form.generacion, ip_local: form.ipLocal.trim(), canal: Number(form.canal),
    funcion: form.funcion, categoria: form.categoria, tipo_carga: form.tipoCarga,
    corriente_max_a: optionalNumber(form.corrienteMax),
    potencia_max_w: optionalNumber(form.potenciaMax),
    tiempo_max_encendido_s: form.tiempoMaxMinutos.trim() ? Math.round(Number(form.tiempoMaxMinutos) * 60) : null,
    apagado_automatico: form.apagadoAutomatico,
    permite_rutinas: form.categoria === 'SEGURIDAD' ? false : form.permiteRutinas,
    requiere_confirmacion: form.requiereConfirmacion,
    notificar_cambios_externos: form.notificarCambiosExternos,
    descripcion: form.descripcion.trim(), modo_control: form.modoControl,
    dispositivo_vinculado_id: form.vinculadoId, estado: form.estado,
  }), [editing, form]);

  const save = useCallback(async () => {
    if (!token) return;
    if (!payload.id || !payload.nombre || !payload.ubicacion || !payload.shelly_device_id || !payload.modelo) {
      Alert.alert('Datos incompletos', 'Completa ID, nombre, ubicacion, Device ID y modelo.');
      return;
    }
    if (payload.apagado_automatico && !payload.tiempo_max_encendido_s) {
      Alert.alert('Tiempo requerido', 'Define cuantos minutos puede permanecer encendido.');
      return;
    }
    setSaving(true);
    try {
      const result = await saveMobileShelly(token, payload);
      Alert.alert('Configuracion guardada', 'El dispositivo quedo disponible en ID Industrial.', [
        { text: 'Ver dispositivo', onPress: () => router.replace(`/shelly/${encodeURIComponent(result.actuador.id)}` as Href) },
      ]);
    } catch (caught) {
      Alert.alert('No fue posible guardar', caught instanceof ApiError ? caught.message : 'Revisa los datos e intenta nuevamente.');
    } finally {
      setSaving(false);
    }
  }, [payload, router, token]);

  return (
    <AppScreen
      eyebrow="ADMINISTRACION"
      title={editing ? 'Editar Shelly' : 'Agregar Shelly'}
      leading={<Pressable accessibilityLabel="Volver" onPress={() => router.back()} style={styles.iconButton}><Ionicons color={colors.text} name="arrow-back" size={22} /></Pressable>}
      scrollProps={{ keyboardShouldPersistTaps: 'handled' }}
    >
      {loading ? <ActivityIndicator color={colors.warning} size="large" /> : null}
      {error ? <View style={styles.errorPanel}><Text style={styles.errorText}>{error}</Text></View> : null}
      {!loading && !error ? (
        <>
          <View style={styles.section}>
            <Text style={styles.eyebrow}>1 · IDENTIDAD CLOUD</Text>
            <Text style={styles.sectionTitle}>Datos del dispositivo</Text>
            <Text style={styles.helper}>La Cloud Key permanece protegida en el servidor. La app solo envia el Device ID.</Text>
            <Field editable={!editing} label="ID interno" onChangeText={(value) => update('id', value)} placeholder="SHELLY_001" value={form.id} />
            <Field label="Nombre visible" onChangeText={(value) => update('nombre', value)} placeholder="Sirena de oficina" value={form.nombre} />
            <Field label="Ubicacion" onChangeText={(value) => update('ubicacion', value)} placeholder="Oficina principal" value={form.ubicacion} />
            <Field label="Device ID de Shelly" onChangeText={(value) => update('shellyDeviceId', value)} placeholder="34987a67da6c" value={form.shellyDeviceId} />
            <Pressable disabled={detecting} onPress={() => void runDetection()} style={[styles.detectButton, detecting && styles.disabled]}>
              {detecting ? <ActivityIndicator color={colors.black} size="small" /> : <Ionicons color={colors.black} name="cloud-done-outline" size={19} />}
              <Text style={styles.detectText}>Detectar con Shelly Cloud</Text>
            </Pressable>
            <Field label="Modelo" onChangeText={(value) => update('modelo', value)} placeholder="Shelly Pro 4PM" value={form.modelo} />
            <Chips label="Generacion" onChange={(value) => update('generacion', value)} options={[{ label: 'Gen 1', value: 'GEN1' }, { label: 'Gen 2 o posterior', value: 'GEN2_PLUS' }]} value={form.generacion} />
            {detectedChannels.length ? <Chips label="Canales detectados" onChange={(value) => update('canal', value)} options={detectedChannels.map((channel) => ({ label: `Canal ${channel}`, value: String(channel) }))} value={form.canal} /> : <Field keyboardType="number-pad" label="Canal de salida" onChangeText={(value) => update('canal', value)} value={form.canal} />}
            <Field label="IP local opcional" onChangeText={(value) => update('ipLocal', value)} placeholder="192.168.0.37" value={form.ipLocal} />
          </View>

          <View style={styles.section}>
            <Text style={styles.eyebrow}>2 · FUNCION</Text>
            <Text style={styles.sectionTitle}>Uso dentro del sistema</Text>
            <Text style={styles.helper}>Automatizacion es para cargas no criticas, como iluminacion o electrodomesticos. Seguridad protege sirenas, balizas y otros equipos de emergencia.</Text>
            <Chips label="Categoria" onChange={(value) => {
              update('categoria', value);
              if (value === 'SEGURIDAD') update('permiteRutinas', false);
              if (value === 'MONITOREO') update('funcion', 'OTRO');
            }} options={[{ label: 'Seguridad', value: 'SEGURIDAD' }, { label: 'Automatizacion', value: 'AUTOMATIZACION' }, { label: 'Monitoreo', value: 'MONITOREO' }]} value={form.categoria} />
            <Chips label="Funcion" onChange={(value) => update('funcion', value)} options={[{ label: 'Sirena', value: 'SIRENA' }, { label: 'Baliza', value: 'BALIZA' }, { label: 'Ventilacion', value: 'VENTILACION' }, { label: 'Contactor', value: 'CONTACTOR' }, { label: 'Otro', value: 'OTRO' }]} value={form.funcion} />
            <Chips label="Modo de control" onChange={(value) => update('modoControl', value)} options={[{ label: 'Cloud', value: 'CLOUD' }, { label: 'Hibrido', value: 'HIBRIDO' }, { label: 'Local', value: 'LOCAL' }]} value={form.modoControl} />
            <Chips label="ESP32 asociado" onChange={(value) => update('vinculadoId', value)} options={[{ label: 'Sin asociar', value: '' }, ...esp32.map((device) => ({ label: device.id, value: device.id }))]} value={form.vinculadoId} />
            <Field label="Descripcion opcional" multiline onChangeText={(value) => update('descripcion', value)} placeholder="Equipo o carga conectada" value={form.descripcion} />
          </View>

          <View style={styles.section}>
            <Text style={styles.eyebrow}>3 · PROTECCION</Text>
            <Text style={styles.sectionTitle}>Limites de la carga</Text>
            <Chips label="Tipo de carga" onChange={(value) => update('tipoCarga', value)} options={[{ label: 'Desconocida', value: 'DESCONOCIDA' }, { label: 'Resistiva', value: 'RESISTIVA' }, { label: 'Inductiva', value: 'INDUCTIVA' }, { label: 'Electronica', value: 'ELECTRONICA' }]} value={form.tipoCarga} />
            <View style={styles.twoColumns}>
              <Field keyboardType="decimal-pad" label="Corriente maxima (A)" onChangeText={(value) => update('corrienteMax', value)} value={form.corrienteMax} />
              <Field keyboardType="decimal-pad" label="Potencia maxima (W)" onChangeText={(value) => update('potenciaMax', value)} value={form.potenciaMax} />
            </View>
            <ToggleRow description="Shelly apagara la salida aunque la app este cerrada." label="Apagado automatico" onValueChange={(value) => update('apagadoAutomatico', value)} value={form.apagadoAutomatico} />
            {form.apagadoAutomatico ? <Field keyboardType="number-pad" label="Tiempo maximo encendido (minutos)" onChangeText={(value) => update('tiempoMaxMinutos', value)} value={form.tiempoMaxMinutos} /> : null}
             <ToggleRow description="Solicita confirmacion antes de energizar la salida." label="Confirmacion de encendido" onValueChange={(value) => update('requiereConfirmacion', value)} value={form.requiereConfirmacion} />
             <ToggleRow description="Avisa si Shelly Control o el boton fisico cambia esta salida." label="Avisar cambios externos" onValueChange={(value) => update('notificarCambiosExternos', value)} value={form.notificarCambiosExternos} />
            {form.categoria === 'SEGURIDAD' ? (
              <View style={styles.safetyNotice}>
                <Ionicons color={colors.warning} name="shield-checkmark-outline" size={21} />
                <View style={styles.safetyNoticeCopy}>
                  <Text style={styles.safetyNoticeTitle}>Canal protegido</Text>
                  <Text style={styles.helper}>Las rutinas no controlan sirenas ni salidas de emergencia. Para automatizar otra carga, registra su canal Shelly por separado y elige la categoria Automatizacion.</Text>
                </View>
              </View>
            ) : null}
            <ToggleRow disabled={form.categoria === 'SEGURIDAD'} description={form.categoria === 'SEGURIDAD' ? 'No disponible mientras la categoria sea Seguridad.' : 'Permite incluir este canal en automatizaciones manuales o programadas.'} label="Permitir rutinas" onValueChange={(value) => update('permiteRutinas', value)} value={form.permiteRutinas} />
            <Chips label="Estado administrativo" onChange={(value) => update('estado', value)} options={[{ label: 'Activo', value: 'Activo' }, { label: 'Mantenimiento', value: 'Mantenimiento' }, { label: 'Inactivo', value: 'Inactivo' }]} value={form.estado} />
          </View>

          <Pressable disabled={saving} onPress={() => void save()} style={[styles.saveButton, saving && styles.disabled]}>
            {saving ? <ActivityIndicator color={colors.black} size="small" /> : <Ionicons color={colors.black} name="save-outline" size={20} />}
            <Text style={styles.saveText}>{editing ? 'Guardar cambios' : 'Agregar dispositivo'}</Text>
          </Pressable>
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
  helper: { color: colors.muted, fontSize: 12, lineHeight: 18 },
  safetyNotice: { alignItems: 'flex-start', backgroundColor: colors.surfaceStrong, borderColor: colors.warning, borderRadius: radius.sm, borderWidth: 1, flexDirection: 'row', gap: spacing.sm, padding: spacing.md },
  safetyNoticeCopy: { flex: 1, gap: spacing.xs },
  safetyNoticeTitle: { color: colors.textStrong, fontSize: 13, fontWeight: '900' },
  field: { flex: 1, gap: spacing.sm, minWidth: 0 },
  label: { color: colors.text, fontSize: 12, fontWeight: '800' },
  input: { backgroundColor: colors.surfaceStrong, borderColor: colors.border, borderRadius: radius.sm, borderWidth: 1, color: colors.textStrong, fontSize: 15, minHeight: 48, paddingHorizontal: spacing.md, paddingVertical: spacing.sm },
  inputDisabled: { color: colors.muted, opacity: 0.7 },
  textarea: { minHeight: 88, textAlignVertical: 'top' },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  chip: { borderColor: colors.border, borderRadius: radius.sm, borderWidth: 1, minHeight: 40, justifyContent: 'center', paddingHorizontal: spacing.md },
  chipSelected: { backgroundColor: colors.normal, borderColor: colors.normal },
  chipText: { color: colors.text, fontSize: 12, fontWeight: '800' },
  chipTextSelected: { color: colors.black },
  detectButton: { alignItems: 'center', alignSelf: 'flex-start', backgroundColor: colors.warning, borderRadius: radius.sm, flexDirection: 'row', gap: spacing.sm, minHeight: 44, paddingHorizontal: spacing.lg },
  detectText: { color: colors.black, fontSize: 13, fontWeight: '900' },
  twoColumns: { flexDirection: 'row', gap: spacing.md },
  toggleRow: { alignItems: 'center', borderBottomColor: colors.borderSoft, borderBottomWidth: 1, flexDirection: 'row', gap: spacing.md, justifyContent: 'space-between', paddingVertical: spacing.sm },
  toggleCopy: { flex: 1, gap: spacing.xs },
  toggleTitle: { color: colors.textStrong, fontSize: 14, fontWeight: '800' },
  toggleDescription: { color: colors.muted, fontSize: 11, lineHeight: 16 },
  saveButton: { alignItems: 'center', backgroundColor: colors.warning, borderRadius: radius.sm, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 52 },
  saveText: { color: colors.black, fontSize: 15, fontWeight: '900' },
  disabled: { opacity: 0.5 },
  errorPanel: { backgroundColor: colors.surface, borderColor: colors.critical, borderRadius: radius.md, borderWidth: 1, padding: spacing.lg },
  errorText: { color: colors.critical, textAlign: 'center' },
});
