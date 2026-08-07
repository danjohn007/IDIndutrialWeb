import Ionicons from '@expo/vector-icons/Ionicons';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { useAuth } from '@/context/auth-context';
import * as api from '@/services/api';
import {
  getPushCapability,
  requestExpoPushToken,
} from '@/services/push-notifications';
import {
  readPushToken,
  removePushToken,
  savePushToken,
} from '@/services/push-token-storage';
import { colors, radius, spacing } from '@/theme/colors';
import type { MobilePushStatus } from '@/types/api';

const roleNames = {
  ADMIN: 'Administrador',
  OPERADOR: 'Operador',
  LECTURA: 'Consulta',
} as const;

export default function AccountScreen() {
  const { signOut, token, user } = useAuth();
  const [pushStatus, setPushStatus] = useState<MobilePushStatus | null>(null);
  const [localPushToken, setLocalPushToken] = useState<string | null>(null);
  const [pushLoading, setPushLoading] = useState(true);
  const [pushSaving, setPushSaving] = useState(false);
  const [pushMessage, setPushMessage] = useState('');
  const capability = useMemo(() => getPushCapability(), []);

  const loadPushStatus = useCallback(async () => {
    if (!token) return;
    setPushLoading(true);
    const storedToken = await readPushToken();
    setLocalPushToken(storedToken);
    try {
      const status = await api.getMobilePushStatus(token);
      setPushStatus(status);
    } catch (error) {
      setPushMessage(
        error instanceof Error
          ? error.message
          : 'No fue posible consultar las notificaciones.',
      );
    } finally {
      setPushLoading(false);
    }
  }, [token]);

  useEffect(() => {
    void loadPushStatus();
  }, [loadPushStatus]);

  const togglePush = useCallback(async () => {
    if (!token || !capability.available || pushSaving) return;
    setPushSaving(true);
    setPushMessage('');
    try {
      if (localPushToken) {
        await api.disableMobilePush(token, localPushToken);
        await removePushToken();
        setLocalPushToken(null);
        setPushMessage('Notificaciones desactivadas en este telefono.');
      } else {
        const registration = await requestExpoPushToken();
        await api.registerMobilePush(
          token,
          registration.token,
          registration.platform,
          registration.deviceName,
        );
        await savePushToken(registration.token);
        setLocalPushToken(registration.token);
        setPushMessage('Alertas criticas activadas en este telefono.');
      }
      setPushStatus(await api.getMobilePushStatus(token));
    } catch (error) {
      setPushMessage(
        error instanceof Error
          ? error.message
          : 'No fue posible actualizar las notificaciones.',
      );
    } finally {
      setPushSaving(false);
    }
  }, [capability, localPushToken, pushSaving, token]);

  return (
    <AppScreen eyebrow="SESION" title="Cuenta">
      <View style={styles.profile}>
        <View style={styles.avatar}>
          <Ionicons color={colors.warning} name="person-outline" size={28} />
        </View>
        <View style={styles.profileCopy}>
          <Text style={styles.name}>{user?.nombre ?? 'Usuario'}</Text>
          <Text style={styles.email}>{user?.email ?? '--'}</Text>
          <Text style={styles.role}>
            {user ? roleNames[user.rol] : 'Sin rol'}
          </Text>
        </View>
      </View>

      <View style={styles.security}>
        <Ionicons color={colors.normal} name="shield-checkmark-outline" size={24} />
        <View style={styles.securityCopy}>
          <Text style={styles.securityTitle}>Sesion protegida</Text>
          <Text style={styles.securityText}>
            El token se almacena cifrado por el sistema del telefono y puede
            revocarse al cerrar sesion.
          </Text>
        </View>
      </View>

      <View style={styles.notifications}>
        <View style={styles.notificationHeader}>
          <View style={styles.notificationIcon}>
            <Ionicons
              color={localPushToken ? colors.critical : colors.warning}
              name={localPushToken ? 'notifications' : 'notifications-outline'}
              size={23}
            />
          </View>
          <View style={styles.notificationCopy}>
            <Text style={styles.securityTitle}>Alertas criticas</Text>
            <Text style={styles.securityText}>
              Recibe avisos por flama, humo/gas o temperatura peligrosa aunque
              la app no este abierta.
            </Text>
          </View>
        </View>

        <View style={styles.pushSummary}>
          <Text style={styles.pushSummaryLabel}>Este telefono</Text>
          <Text
            style={[
              styles.pushSummaryValue,
              localPushToken ? styles.pushEnabled : styles.pushDisabled,
            ]}
          >
            {localPushToken ? 'ACTIVADO' : 'DESACTIVADO'}
          </Text>
        </View>

        <Text style={styles.deviceCount}>
          {pushLoading
            ? 'Consultando dispositivos...'
            : `${pushStatus?.habilitadas ?? 0} telefono(s) activo(s) en tu cuenta`}
        </Text>

        {!capability.available ? (
          <Text style={styles.capabilityMessage}>{capability.message}</Text>
        ) : null}
        {pushMessage ? <Text style={styles.pushMessage}>{pushMessage}</Text> : null}

        <Pressable
          disabled={!capability.available || pushLoading || pushSaving}
          onPress={() => void togglePush()}
          style={({ pressed }) => [
            styles.pushButton,
            localPushToken && styles.pushButtonDisable,
            (!capability.available || pushLoading || pushSaving) && styles.disabled,
            pressed && styles.pressed,
          ]}
        >
          {pushSaving ? (
            <ActivityIndicator color={colors.black} size="small" />
          ) : (
            <Ionicons
              color={localPushToken ? colors.text : colors.black}
              name={localPushToken ? 'notifications-off-outline' : 'notifications-outline'}
              size={20}
            />
          )}
          <Text
            style={[
              styles.pushButtonText,
              localPushToken && styles.pushButtonDisableText,
            ]}
          >
            {localPushToken ? 'Desactivar en este telefono' : 'Activar notificaciones'}
          </Text>
        </Pressable>
      </View>

      <Pressable
        onPress={() => void signOut()}
        style={({ pressed }) => [styles.logout, pressed && styles.pressed]}
      >
        <Ionicons color={colors.critical} name="log-out-outline" size={22} />
        <Text style={styles.logoutText}>Cerrar sesion</Text>
      </Pressable>
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  profile: {
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    flexDirection: 'row',
    gap: spacing.lg,
    padding: spacing.lg,
  },
  avatar: {
    alignItems: 'center',
    backgroundColor: colors.surfaceStrong,
    borderColor: colors.warning,
    borderRadius: radius.md,
    borderWidth: 1,
    height: 58,
    justifyContent: 'center',
    width: 58,
  },
  profileCopy: {
    flex: 1,
    gap: spacing.xs,
  },
  name: {
    color: colors.textStrong,
    fontSize: 19,
    fontWeight: '900',
  },
  email: {
    color: colors.muted,
    fontSize: 13,
  },
  role: {
    color: colors.warning,
    fontSize: 11,
    fontWeight: '900',
    marginTop: spacing.xs,
    textTransform: 'uppercase',
  },
  security: {
    alignItems: 'flex-start',
    backgroundColor: colors.surfaceStrong,
    borderRadius: radius.md,
    flexDirection: 'row',
    gap: spacing.md,
    padding: spacing.lg,
  },
  securityCopy: {
    flex: 1,
    gap: spacing.xs,
  },
  securityTitle: {
    color: colors.textStrong,
    fontSize: 15,
    fontWeight: '800',
  },
  securityText: {
    color: colors.muted,
    fontSize: 13,
    lineHeight: 19,
  },
  notifications: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    gap: spacing.md,
    padding: spacing.lg,
  },
  notificationHeader: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: spacing.md,
  },
  notificationIcon: {
    alignItems: 'center',
    backgroundColor: colors.surfaceStrong,
    borderRadius: radius.md,
    height: 44,
    justifyContent: 'center',
    width: 44,
  },
  notificationCopy: {
    flex: 1,
    gap: spacing.xs,
  },
  pushSummary: {
    alignItems: 'center',
    backgroundColor: colors.surfaceStrong,
    borderRadius: radius.sm,
    flexDirection: 'row',
    justifyContent: 'space-between',
    minHeight: 42,
    paddingHorizontal: spacing.md,
  },
  pushSummaryLabel: {
    color: colors.muted,
    fontSize: 13,
  },
  pushSummaryValue: {
    fontSize: 12,
    fontWeight: '900',
  },
  pushEnabled: {
    color: colors.success,
  },
  pushDisabled: {
    color: colors.warning,
  },
  deviceCount: {
    color: colors.muted,
    fontSize: 12,
  },
  capabilityMessage: {
    color: colors.warning,
    fontSize: 13,
    lineHeight: 19,
  },
  pushMessage: {
    color: colors.normal,
    fontSize: 13,
    lineHeight: 19,
  },
  pushButton: {
    alignItems: 'center',
    backgroundColor: colors.warning,
    borderRadius: radius.md,
    flexDirection: 'row',
    gap: spacing.sm,
    justifyContent: 'center',
    minHeight: 48,
    paddingHorizontal: spacing.md,
  },
  pushButtonDisable: {
    backgroundColor: colors.surfaceStrong,
    borderColor: colors.border,
    borderWidth: 1,
  },
  pushButtonText: {
    color: colors.black,
    flexShrink: 1,
    fontSize: 14,
    fontWeight: '900',
    textAlign: 'center',
  },
  pushButtonDisableText: {
    color: colors.text,
  },
  disabled: {
    opacity: 0.5,
  },
  logout: {
    alignItems: 'center',
    borderColor: colors.critical,
    borderRadius: radius.md,
    borderWidth: 1,
    flexDirection: 'row',
    gap: spacing.sm,
    justifyContent: 'center',
    minHeight: 52,
  },
  pressed: {
    opacity: 0.75,
  },
  logoutText: {
    color: colors.critical,
    fontSize: 15,
    fontWeight: '900',
  },
});
