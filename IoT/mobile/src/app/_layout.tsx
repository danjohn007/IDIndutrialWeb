import * as Notifications from 'expo-notifications';
import { Stack, useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Platform } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { AuthProvider } from '@/context/auth-context';
import { useAuth } from '@/context/auth-context';
import { colors } from '@/theme/colors';

function notificationAlertId(
  response: Notifications.NotificationResponse | null,
): string | null {
  const data = response?.notification.request.content.data;
  if (!data) return null;

  const candidates = [data.alertaId, data.alerta_id, data.alertId];
  for (const candidate of candidates) {
    if (
      (typeof candidate === 'number' || typeof candidate === 'string')
      && /^\d+$/.test(String(candidate))
      && Number(candidate) > 0
    ) {
      return String(candidate);
    }
  }

  const route = [data.url, data.route, data.ruta].find(
    (value): value is string => typeof value === 'string',
  );
  const routeMatch = route?.match(/\/alerta\/(\d+)(?:[/?#]|$)/i);
  return routeMatch?.[1] ?? null;
}

function NotificationNavigationObserver() {
  const router = useRouter();
  const { loading, user } = useAuth();
  const [pendingAlertId, setPendingAlertId] = useState<string | null>(null);
  const processedResponses = useRef(new Set<string>());

  const captureAlert = useCallback(
    (response: Notifications.NotificationResponse | null) => {
      if (!response) return;

      const responseKey = [
        response.notification.request.identifier,
        response.actionIdentifier,
      ].join(':');
      if (processedResponses.current.has(responseKey)) return;

      const alertId = notificationAlertId(response);
      if (!alertId) return;

      processedResponses.current.add(responseKey);
      setPendingAlertId(alertId);
    },
    [],
  );

  useEffect(() => {
    if (Platform.OS === 'web') return;

    captureAlert(Notifications.getLastNotificationResponse());
    const subscription =
      Notifications.addNotificationResponseReceivedListener(captureAlert);

    return () => subscription.remove();
  }, [captureAlert]);

  useEffect(() => {
    if (
      Platform.OS === 'web'
      || loading
      || !user
      || pendingAlertId === null
    ) {
      return;
    }

    const alertId = pendingAlertId;
    const frame = requestAnimationFrame(() => {
      router.push({
        pathname: '/alerta/[id]',
        params: { id: alertId },
      });
      setPendingAlertId(null);
      Notifications.clearLastNotificationResponse();
    });

    return () => cancelAnimationFrame(frame);
  }, [loading, pendingAlertId, router, user]);

  return null;
}

export default function RootLayout() {
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
