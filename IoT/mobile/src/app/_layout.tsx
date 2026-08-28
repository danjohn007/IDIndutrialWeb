import * as Notifications from 'expo-notifications';
import { Stack, useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Platform } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { AuthProvider } from '@/context/auth-context';
import { useAuth } from '@/context/auth-context';
import { colors } from '@/theme/colors';

type NotificationTarget =
  | { kind: 'alert'; id: string }
  | { kind: 'shelly'; id: string };

function notificationTarget(
  response: Notifications.NotificationResponse | null,
): NotificationTarget | null {
  const data = response?.notification.request.content.data;
  if (!data) return null;

  const candidates = [data.alertaId, data.alerta_id, data.alertId];
  for (const candidate of candidates) {
    if (
      (typeof candidate === 'number' || typeof candidate === 'string')
      && /^\d+$/.test(String(candidate))
      && Number(candidate) > 0
    ) {
      return { kind: 'alert', id: String(candidate) };
    }
  }

  const route = [data.url, data.route, data.ruta].find(
    (value): value is string => typeof value === 'string',
  );
  const routeMatch = route?.match(/\/alerta\/(\d+)(?:[/?#]|$)/i);
  if (routeMatch?.[1]) return { kind: 'alert', id: routeMatch[1] };

  const shellyCandidates = [data.actuadorId, data.actuador_id, data.shellyId];
  for (const candidate of shellyCandidates) {
    if (typeof candidate === 'string' && /^[A-Za-z0-9_-]{1,64}$/.test(candidate)) {
      return { kind: 'shelly', id: candidate };
    }
  }
  const shellyMatch = route?.match(/\/shelly\/([^/?#]+)(?:[/?#]|$)/i);
  return shellyMatch?.[1]
    ? { kind: 'shelly', id: decodeURIComponent(shellyMatch[1]) }
    : null;
}

function NotificationNavigationObserver() {
  const router = useRouter();
  const { loading, user } = useAuth();
  const [pendingTarget, setPendingTarget] = useState<NotificationTarget | null>(null);
  const processedResponses = useRef(new Set<string>());

  const captureAlert = useCallback(
    (response: Notifications.NotificationResponse | null) => {
      if (!response) return;

      const responseKey = [
        response.notification.request.identifier,
        response.actionIdentifier,
      ].join(':');
      if (processedResponses.current.has(responseKey)) return;

      const target = notificationTarget(response);
      if (!target) return;

      processedResponses.current.add(responseKey);
      setPendingTarget(target);
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
      || pendingTarget === null
    ) {
      return;
    }

    const target = pendingTarget;
    const frame = requestAnimationFrame(() => {
      if (target.kind === 'alert') {
        router.push({ pathname: '/alerta/[id]', params: { id: target.id } });
      } else {
        router.push({ pathname: '/shelly/[id]', params: { id: target.id } });
      }
      setPendingTarget(null);
      Notifications.clearLastNotificationResponse();
    });

    return () => cancelAnimationFrame(frame);
  }, [loading, pendingTarget, router, user]);

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
          <Stack.Screen name="hikvision/[id]" />
          <Stack.Screen name="hikvision/formulario" />
          <Stack.Screen name="zkteco/[id]" />
          <Stack.Screen name="zkteco/formulario" />
          <Stack.Screen name="rutina/formulario" />
        </Stack>
      </AuthProvider>
    </SafeAreaProvider>
  );
}
