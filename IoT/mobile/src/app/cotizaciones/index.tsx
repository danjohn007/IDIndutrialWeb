import Ionicons from '@expo/vector-icons/Ionicons';
import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { useAuth } from '@/context/auth-context';
import { ApiError, getMobileQuotes } from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobileQuoteRequest, MobileQuotesPage } from '@/types/api';

function dateTimeLabel(value: string): string {
  const date = new Date(`${value.replace(' ', 'T')}Z`);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    month: 'short',
  }).format(date);
}

function desiredDateLabel(value: string | null): string {
  if (!value) return 'Sin fecha definida';
  const [year, month, day] = value.split('-');
  return year && month && day ? `${day}/${month}/${year}` : value;
}

function statusColor(status: string): string {
  const normalized = status.toLowerCase();
  if (normalized.includes('nueva')) return colors.warning;
  if (normalized.includes('ganad') || normalized.includes('aprobad')) return colors.success;
  if (normalized.includes('perdid') || normalized.includes('cancel')) return colors.critical;
  return colors.normal;
}

function QuoteCard({ quote, onPress }: { quote: MobileQuoteRequest; onPress: () => void }) {
  const accent = statusColor(quote.status);
  return (
    <Pressable
      accessibilityHint="Abre el detalle de la solicitud"
      accessibilityLabel={`Cotizacion de ${quote.company_name}`}
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [styles.card, pressed && styles.cardPressed]}
    >
      <View style={[styles.cardRail, { backgroundColor: accent }]} />
      <View style={styles.cardBody}>
        <View style={styles.cardTopline}>
          <View style={styles.cardIdentity}>
            <Text numberOfLines={1} style={styles.company}>{quote.company_name}</Text>
            <Text numberOfLines={1} style={styles.contact}>{quote.contact_name}</Text>
          </View>
          <Text style={[styles.status, { color: accent }]}>{quote.status}</Text>
        </View>
        <Text numberOfLines={1} style={styles.service}>
          {quote.request_type || 'Solicitud'} · {quote.service}
        </Text>
        <View style={styles.metaRow}>
          <View style={styles.metaItem}>
            <Ionicons color={colors.muted} name="location-outline" size={16} />
            <Text numberOfLines={1} style={styles.metaText}>
              {quote.project_location || 'Sin locacion'}
            </Text>
          </View>
          <View style={styles.metaItem}>
            <Ionicons color={colors.muted} name="calendar-outline" size={16} />
            <Text style={styles.metaText}>{desiredDateLabel(quote.desired_execution_date)}</Text>
          </View>
        </View>
        <View style={styles.cardFooter}>
          <Text style={styles.received}>Recibida {dateTimeLabel(quote.created_at)}</Text>
          <View style={styles.footerAction}>
            {Number(quote.attachments_count) > 0 ? (
              <View style={styles.attachmentCount}>
                <Ionicons color={colors.text} name="attach-outline" size={15} />
                <Text style={styles.attachmentText}>{quote.attachments_count}</Text>
              </View>
            ) : null}
            <Ionicons color={colors.muted} name="chevron-forward" size={18} />
          </View>
        </View>
      </View>
    </Pressable>
  );
}

export default function QuotesScreen() {
  const { token } = useAuth();
  const router = useRouter();
  const [data, setData] = useState<MobileQuotesPage | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState('');
  const loadingMoreRef = useRef(false);

  const load = useCallback(async (page = 1, append = false) => {
    if (!token || (append && loadingMoreRef.current)) return;
    if (append) {
      loadingMoreRef.current = true;
      setLoadingMore(true);
    } else {
      setLoading(true);
    }
    setError('');
    try {
      const response = await getMobileQuotes(token, page);
      setData((current) => {
        if (!append || !current) return response;
        const byId = new Map(
          [...current.solicitudes, ...response.solicitudes].map((quote) => [String(quote.id), quote]),
        );
        return { ...response, solicitudes: Array.from(byId.values()) };
      });
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No fue posible cargar las cotizaciones.');
    } finally {
      loadingMoreRef.current = false;
      setLoading(false);
      setLoadingMore(false);
      setRefreshing(false);
    }
  }, [token]);

  useFocusEffect(useCallback(() => {
    void load();
  }, [load]));

  const refresh = () => {
    setRefreshing(true);
    void load();
  };
  const canLoadMore = Boolean(data && data.paginacion.pagina < data.paginacion.paginas);

  return (
    <AppScreen
      eyebrow="CRM MOVIL"
      title="Cotizaciones"
      leading={
        <Pressable
          accessibilityLabel="Volver"
          accessibilityRole="button"
          hitSlop={8}
          onPress={() => router.back()}
          style={({ pressed }) => [styles.iconButton, pressed && styles.pressed]}
        >
          <Ionicons color={colors.textStrong} name="arrow-back" size={22} />
        </Pressable>
      }
      action={
        <Pressable
          accessibilityLabel="Actualizar cotizaciones"
          accessibilityRole="button"
          onPress={refresh}
          style={({ pressed }) => [styles.iconButton, pressed && styles.pressed]}
        >
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
      <View style={styles.summary}>
        <View style={styles.summaryIcon}>
          <Ionicons color={colors.warning} name="document-text-outline" size={24} />
        </View>
        <View style={styles.summaryCopy}>
          <Text style={styles.summaryValue}>{data?.paginacion.total ?? '--'}</Text>
          <Text style={styles.summaryLabel}>Solicitudes web registradas</Text>
        </View>
      </View>

      {loading && !data ? <ActivityIndicator color={colors.warning} size="large" /> : null}
      {error ? (
        <View style={styles.feedback}>
          <Ionicons color={colors.critical} name="cloud-offline-outline" size={26} />
          <Text style={styles.feedbackText}>{error}</Text>
          <Pressable onPress={() => void load()} style={({ pressed }) => [styles.retry, pressed && styles.pressed]}>
            <Text style={styles.retryText}>Reintentar</Text>
          </Pressable>
        </View>
      ) : null}

      {data?.solicitudes.map((quote) => (
        <QuoteCard
          key={String(quote.id)}
          onPress={() => router.push({ pathname: '/cotizaciones/[id]', params: { id: String(quote.id) } })}
          quote={quote}
        />
      ))}

      {data && !data.solicitudes.length && !loading ? (
        <View style={styles.feedback}>
          <Ionicons color={colors.muted} name="file-tray-outline" size={28} />
          <Text style={styles.feedbackText}>Todavia no hay solicitudes de cotizacion.</Text>
        </View>
      ) : null}

      {canLoadMore ? (
        <Pressable
          disabled={loadingMore}
          onPress={() => void load((data?.paginacion.pagina ?? 0) + 1, true)}
          style={({ pressed }) => [styles.moreButton, (pressed || loadingMore) && styles.pressed]}
        >
          {loadingMore ? <ActivityIndicator color={colors.normal} /> : <Text style={styles.moreText}>Cargar mas</Text>}
        </Pressable>
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
  summary: {
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    flexDirection: 'row',
    gap: spacing.md,
    padding: spacing.lg,
  },
  summaryIcon: {
    alignItems: 'center',
    backgroundColor: colors.surfaceRaised,
    borderRadius: radius.md,
    height: 48,
    justifyContent: 'center',
    width: 48,
  },
  summaryCopy: { flex: 1 },
  summaryValue: { color: colors.textStrong, fontSize: 24, fontWeight: '900' },
  summaryLabel: { color: colors.muted, fontSize: 13, marginTop: spacing.xs },
  card: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    flexDirection: 'row',
    minHeight: 148,
    overflow: 'hidden',
  },
  cardPressed: { backgroundColor: colors.surfaceRaised, opacity: 0.88 },
  cardRail: { width: 4 },
  cardBody: { flex: 1, gap: spacing.sm, padding: spacing.md },
  cardTopline: { alignItems: 'flex-start', flexDirection: 'row', gap: spacing.sm },
  cardIdentity: { flex: 1 },
  company: { color: colors.textStrong, fontSize: 17, fontWeight: '900' },
  contact: { color: colors.muted, fontSize: 12, marginTop: spacing.xs },
  status: { fontSize: 10, fontWeight: '900', maxWidth: 108, textAlign: 'right', textTransform: 'uppercase' },
  service: { color: colors.text, fontSize: 14, fontWeight: '700' },
  metaRow: { gap: spacing.sm },
  metaItem: { alignItems: 'center', flexDirection: 'row', gap: spacing.sm },
  metaText: { color: colors.muted, flexShrink: 1, fontSize: 12 },
  cardFooter: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between', marginTop: spacing.xs },
  received: { color: colors.muted, fontSize: 11 },
  footerAction: { alignItems: 'center', flexDirection: 'row', gap: spacing.sm },
  attachmentCount: { alignItems: 'center', flexDirection: 'row', gap: spacing.xs },
  attachmentText: { color: colors.text, fontSize: 11, fontWeight: '800' },
  feedback: {
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    gap: spacing.md,
    padding: spacing.xl,
  },
  feedbackText: { color: colors.text, fontSize: 14, textAlign: 'center' },
  retry: { borderColor: colors.normal, borderRadius: radius.sm, borderWidth: 1, minHeight: 44, paddingHorizontal: spacing.lg, justifyContent: 'center' },
  retryText: { color: colors.normal, fontSize: 13, fontWeight: '800' },
  moreButton: { alignItems: 'center', borderColor: colors.normal, borderRadius: radius.md, borderWidth: 1, justifyContent: 'center', minHeight: 48 },
  moreText: { color: colors.normal, fontSize: 14, fontWeight: '800' },
  pressed: { opacity: 0.7 },
});
