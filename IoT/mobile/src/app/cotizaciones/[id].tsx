import Ionicons from '@expo/vector-icons/Ionicons';
import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Linking,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { useAuth } from '@/context/auth-context';
import { ApiError, getMobileQuoteDetail } from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileQuoteDetail } from '@/types/api';

function dateTimeLabel(value: string): string {
  const date = new Date(`${value.replace(' ', 'T')}Z`);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

function dateLabel(value: string | null): string {
  if (!value) return 'Sin fecha definida';
  const [year, month, day] = value.split('-');
  return year && month && day ? `${day}/${month}/${year}` : value;
}

function fileSizeLabel(value: number | string): string {
  const bytes = Number(value);
  if (!Number.isFinite(bytes) || bytes < 1) return '0 KB';
  if (bytes < 1024 * 1024) return `${Math.ceil(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function DetailRow({ icon, label, value }: { icon: React.ComponentProps<typeof Ionicons>['name']; label: string; value: string }) {
  return (
    <View style={styles.detailRow}>
      <View style={styles.detailIcon}>
        <Ionicons color={colors.normal} name={icon} size={18} />
      </View>
      <View style={styles.detailCopy}>
        <Text style={styles.detailLabel}>{label}</Text>
        <Text selectable style={styles.detailValue}>{value || 'Sin especificar'}</Text>
      </View>
    </View>
  );
}

export default function QuoteDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { token } = useAuth();
  const router = useRouter();
  const [data, setData] = useState<MobileQuoteDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [openingCrm, setOpeningCrm] = useState(false);

  const load = useCallback(async () => {
    if (!token || !id) return;
    setLoading(true);
    setError('');
    try {
      setData(await getMobileQuoteDetail(token, id));
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No fue posible cargar la cotizacion.');
    } finally {
      setLoading(false);
    }
  }, [id, token]);

  useFocusEffect(useCallback(() => {
    void load();
  }, [load]));

  const openCrm = async () => {
    if (!data?.crm_url || openingCrm) return;
    setOpeningCrm(true);
    try {
      await Linking.openURL(data.crm_url);
    } catch {
      setError('No fue posible abrir el CRM en el navegador.');
    } finally {
      setOpeningCrm(false);
    }
  };

  const quote = data?.solicitud;

  return (
    <AppScreen
      eyebrow={quote ? `SOLICITUD #${quote.id}` : 'CRM MOVIL'}
      includeBottomInset
      leading={
        <Pressable
          accessibilityLabel="Volver a cotizaciones"
          accessibilityRole="button"
          hitSlop={8}
          onPress={() => router.back()}
          style={({ pressed }) => [styles.iconButton, pressed && styles.pressed]}
        >
          <Ionicons color={colors.textStrong} name="arrow-back" size={22} />
        </Pressable>
      }
      title={quote?.company_name || 'Detalle de cotizacion'}
    >
      {loading && !data ? <ActivityIndicator color={colors.warning} size="large" /> : null}
      {error ? (
        <View style={styles.feedback}>
          <Ionicons color={colors.critical} name="alert-circle-outline" size={27} />
          <Text style={styles.feedbackText}>{error}</Text>
          {!data ? (
            <Pressable onPress={() => void load()} style={({ pressed }) => [styles.retry, pressed && styles.pressed]}>
              <Text style={styles.retryText}>Reintentar</Text>
            </Pressable>
          ) : null}
        </View>
      ) : null}

      {quote ? (
        <>
          <View style={styles.hero}>
            <View style={styles.heroTopline}>
              <View style={styles.heroIcon}>
                <Ionicons color={colors.warning} name="document-text-outline" size={27} />
              </View>
              <View style={styles.heroCopy}>
                <Text style={styles.heroEyebrow}>{quote.request_type || 'Solicitud'}</Text>
                <Text style={styles.heroTitle}>{quote.service}</Text>
              </View>
            </View>
            <View style={styles.statusRow}>
              <View style={styles.statusChip}><Text style={styles.statusText}>{quote.status}</Text></View>
              <Text style={styles.received}>Recibida {dateTimeLabel(quote.created_at)}</Text>
            </View>
          </View>

          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Proyecto</Text>
            <DetailRow icon="location-outline" label="Locacion" value={quote.project_location || ''} />
            <DetailRow icon="calendar-outline" label="Fecha deseada" value={dateLabel(quote.desired_execution_date)} />
            <DetailRow icon="flag-outline" label="Prioridad" value={quote.priority} />
          </View>

          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Contacto</Text>
            <DetailRow icon="person-outline" label="Nombre" value={quote.contact_name} />
            <DetailRow icon="mail-outline" label="Correo" value={quote.contact_email || ''} />
            <DetailRow icon="logo-whatsapp" label="Telefono WhatsApp" value={quote.contact_phone || ''} />
          </View>

          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Requerimientos</Text>
            <Text selectable style={styles.notes}>{quote.notes || 'Sin requerimientos adicionales.'}</Text>
          </View>

          <View style={styles.section}>
            <View style={styles.sectionHeading}>
              <Text style={styles.sectionTitle}>Archivos adjuntos</Text>
              <Text style={styles.sectionCount}>{data.adjuntos.length}</Text>
            </View>
            {data.adjuntos.length ? data.adjuntos.map((attachment) => (
              <View key={String(attachment.id)} style={styles.fileRow}>
                <Ionicons color={colors.warning} name="document-attach-outline" size={22} />
                <View style={styles.fileCopy}>
                  <Text numberOfLines={2} style={styles.fileName}>{attachment.original_name}</Text>
                  <Text style={styles.fileMeta}>{attachment.mime} · {fileSizeLabel(attachment.size)}</Text>
                </View>
              </View>
            )) : <Text style={styles.emptyText}>Esta solicitud no incluye archivos.</Text>}
          </View>

          <Pressable
            accessibilityHint="Abre la oportunidad en el CRM web"
            accessibilityRole="link"
            disabled={openingCrm}
            onPress={() => void openCrm()}
            style={({ pressed }) => [styles.crmButton, (pressed || openingCrm) && styles.crmButtonPressed]}
          >
            {openingCrm ? (
              <ActivityIndicator color={colors.black} />
            ) : (
              <Ionicons color={colors.black} name="open-outline" size={21} />
            )}
            <Text style={styles.crmButtonText}>Ver en CRM</Text>
          </Pressable>
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
  hero: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    gap: spacing.lg,
    padding: spacing.lg,
  },
  heroTopline: { alignItems: 'center', flexDirection: 'row', gap: spacing.md },
  heroIcon: { alignItems: 'center', backgroundColor: colors.surfaceRaised, borderRadius: radius.md, height: 52, justifyContent: 'center', width: 52 },
  heroCopy: { flex: 1 },
  heroEyebrow: { color: colors.warning, fontSize: 11, fontWeight: '900', textTransform: 'uppercase' },
  heroTitle: { color: colors.textStrong, fontSize: 18, fontWeight: '900', marginTop: spacing.xs },
  statusRow: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between' },
  statusChip: { backgroundColor: colors.surfaceRaised, borderColor: colors.border, borderRadius: radius.sm, borderWidth: 1, paddingHorizontal: spacing.sm, paddingVertical: spacing.xs },
  statusText: { color: colors.normal, fontSize: 11, fontWeight: '900', textTransform: 'uppercase' },
  received: { color: colors.muted, fontSize: 11 },
  section: { backgroundColor: colors.surface, borderColor: colors.border, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.lg },
  sectionHeading: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between' },
  sectionTitle: { color: colors.textStrong, fontSize: 15, fontWeight: '900' },
  sectionCount: { color: colors.warning, fontSize: 13, fontWeight: '900' },
  detailRow: { alignItems: 'flex-start', flexDirection: 'row', gap: spacing.md },
  detailIcon: { alignItems: 'center', height: 32, justifyContent: 'center', width: 32 },
  detailCopy: { flex: 1 },
  detailLabel: { color: colors.muted, fontSize: 11, fontWeight: '800', textTransform: 'uppercase' },
  detailValue: { color: colors.text, fontSize: 14, lineHeight: 21, marginTop: spacing.xs },
  notes: { color: colors.text, fontSize: 14, lineHeight: 22 },
  fileRow: { alignItems: 'center', borderTopColor: colors.borderSoft, borderTopWidth: StyleSheet.hairlineWidth, flexDirection: 'row', gap: spacing.md, minHeight: 56, paddingTop: spacing.md },
  fileCopy: { flex: 1 },
  fileName: { color: colors.text, fontSize: 13, fontWeight: '700' },
  fileMeta: { color: colors.muted, fontSize: 11, marginTop: spacing.xs },
  emptyText: { color: colors.muted, fontSize: 13 },
  crmButton: { alignItems: 'center', backgroundColor: colors.warning, borderRadius: radius.md, flexDirection: 'row', gap: spacing.sm, justifyContent: 'center', minHeight: 52, paddingHorizontal: spacing.lg },
  crmButtonPressed: { opacity: 0.72 },
  crmButtonText: { color: colors.black, fontSize: 15, fontWeight: '900' },
  feedback: { alignItems: 'center', backgroundColor: colors.surface, borderColor: colors.critical, borderRadius: radius.md, borderWidth: 1, gap: spacing.md, padding: spacing.xl },
  feedbackText: { color: colors.text, fontSize: 14, textAlign: 'center' },
  retry: { borderColor: colors.normal, borderRadius: radius.sm, borderWidth: 1, justifyContent: 'center', minHeight: 44, paddingHorizontal: spacing.lg },
  retryText: { color: colors.normal, fontSize: 13, fontWeight: '800' },
  pressed: { opacity: 0.7 },
});
