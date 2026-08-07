import * as Notifications from 'expo-notifications';
import { Stack, useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Linking, Platform } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { AuthProvider } from '@/context/auth-context';
import { useAuth } from '@/context/auth-context';
import { configureNotificationChannels } from '@/services/push-notifications';
import { colors } from '@/theme/colors';

type NotificationDestination =
  | { type: 'ALERT'; id: string }
  | { type: 'QUOTE'; url: string };

function notificationDestination(
  response: Notifications.NotificationResponse | null,
): NotificationDestination | null {
  const data = response?.notification.request.content.data;
  if (!data) return null;

  if (String(data.tipo ?? '').toUpperCase() === 'COTIZACION') {
    const quoteUrl = [data.url, data.crmUrl, data.targetUrl].find(
      (value): value is string => typeof value === 'string',
    );
    if (
      quoteUrl
      && /^https:\/\/(?:www\.)?idindustrial\.com\.mx\/(?:[^?#]+\/)?crm\/oportunidades\/\d+(?:[/?#]|$)/i.test(quoteUrl)
    ) {
      return { type: 'QUOTE', url: quoteUrl };
    }
  }

  const candidates = [data.alertaId, data.alerta_id, data.alertId];
  for (const candidate of candidates) {
    if (
      (typeof candidate === 'number' || typeof candidate === 'string')
      && /^\d+$/.test(String(candidate))
      && Number(candidate) > 0
    ) {
      return { type: 'ALERT', id: String(candidate) };
    }
  }

  const route = [data.url, data.route, data.ruta].find(
    (value): value is string => typeof value === 'string',
  );
  const routeMatch = route?.match(/\/alerta\/(\d+)(?:[/?#]|$)/i);
  return routeMatch ? { type: 'ALERT', id: routeMatch[1] } : null;
}

function NotificationNavigationObserver() {
  const router = useRouter();
  const { loading, user } = useAuth();
  const [pending, setPending] = useState<NotificationDestination | null>(null);
  const processedResponses = useRef(new Set<string>());

  const captureNotification = useCallback(
    (response: Notifications.NotificationResponse | null) => {
      if (!response) return;

      const responseKey = [
        response.notification.request.identifier,
        response.actionIdentifier,
      ].join(':');
      if (processedResponses.current.has(responseKey)) return;

      const destination = notificationDestination(response);
      if (!destination) return;

      processedResponses.current.add(responseKey);
      setPending(destination);
    },
    [],
  );

  useEffect(() => {
    if (Platform.OS === 'web') return;

    captureNotification(Notifications.getLastNotificationResponse());
    const subscription =
      Notifications.addNotificationResponseReceivedListener(captureNotification);

    return () => subscription.remove();
  }, [captureNotification]);

  useEffect(() => {
    if (Platform.OS === 'web' || loading || !user || pending === null) {
      return;
    }

    const destination = pending;
    const frame = requestAnimationFrame(() => {
      if (destination.type === 'ALERT') {
        router.push({
          pathname: '/alerta/[id]',
          params: { id: destination.id },
        });
      } else {
        void Linking.openURL(destination.url).catch((error: unknown) => {
          console.warn('No fue posible abrir la oportunidad del CRM', error);
        });
      }
      setPending(null);
      Notifications.clearLastNotificationResponse();
    });

    return () => cancelAnimationFrame(frame);
  }, [loading, pending, router, user]);

  return null;
}

export default function RootLayout() {
  useEffect(() => {
    void configureNotificationChannels();
  }, []);

  return (
    <SafeAreaProvider>
      <AuthProvider>
        <NotificationNavigationObserver />
        <StatusBar backgroundColor={colors.background} style="light" />
        <Stack
          screenOptions={{
            animation: 'fade',
            contentStyle: { backgroundColor: colors.background },
            headerShown: false,
          }}
        >
          <Stack.Screen name="index" />
          <Stack.Screen name="login" />
          <Stack.Screen name="(tabs)" />
          <Stack.Screen name="alerta/[id]" />
          <Stack.Screen name="shelly/[id]" />
          <Stack.Screen name="shelly/formulario" />
          <Stack.Screen name="rutina/formulario" />
        </Stack>
      </AuthProvider>
    </SafeAreaProvider>
  );
}
