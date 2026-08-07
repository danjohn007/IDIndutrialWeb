import Constants, { ExecutionEnvironment } from 'expo-constants';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

export type PushCapability =
  | { available: true; projectId: string }
  | {
      available: false;
      reason: 'WEB' | 'EXPO_GO' | 'MISSING_PROJECT_ID';
      message: string;
    };

if (Platform.OS !== 'web') {
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldPlaySound: true,
      shouldSetBadge: false,
      shouldShowBanner: true,
      shouldShowList: true,
    }),
  });
}

export async function configureNotificationChannels(): Promise<void> {
  if (Platform.OS !== 'android') return;

  await Promise.all([
    Notifications.setNotificationChannelAsync('critical-alerts', {
      name: 'Alertas críticas',
      description: 'Incendio, flama, gas o temperatura peligrosa.',
      importance: Notifications.AndroidImportance.MAX,
      lightColor: '#FF453A',
      sound: 'default',
      vibrationPattern: [0, 250, 180, 250],
    }),
    Notifications.setNotificationChannelAsync('crm-updates', {
      name: 'Solicitudes comerciales',
      description: 'Nuevas solicitudes de cotización recibidas en el CRM.',
      importance: Notifications.AndroidImportance.DEFAULT,
      lightColor: '#2563EB',
      sound: 'default',
    }),
  ]);
}
export function getPushCapability(): PushCapability {
  if (Platform.OS === 'web') {
    return {
      available: false,
      reason: 'WEB',
      message: 'Las notificaciones críticas se activan desde Android o iOS.',
    };
  }
  if (Constants.executionEnvironment === ExecutionEnvironment.StoreClient) {
    return {
      available: false,
      reason: 'EXPO_GO',
      message:
        'Expo Go muestra la app, pero las notificaciones remotas requieren un build de desarrollo.',
    };
  }

  const projectId =
    process.env.EXPO_PUBLIC_EAS_PROJECT_ID?.trim()
    || Constants.expoConfig?.extra?.eas?.projectId
    || Constants.easConfig?.projectId;
  if (!projectId) {
    return {
      available: false,
      reason: 'MISSING_PROJECT_ID',
      message: 'Falta configurar el Project ID de EAS para este build.',
    };
  }

  return { available: true, projectId };
}

export async function requestExpoPushToken(): Promise<{
  token: string;
  platform: 'ANDROID' | 'IOS';
  deviceName: string;
}> {
  const capability = getPushCapability();
  if (!capability.available) {
    throw new Error(capability.message);
  }

  await configureNotificationChannels();

  const current = await Notifications.getPermissionsAsync();
  const permission =
    current.status === 'granted'
      ? current
      : await Notifications.requestPermissionsAsync();
  if (permission.status !== 'granted') {
    throw new Error('El telefono no concedio permiso para notificaciones.');
  }

  const response = await Notifications.getExpoPushTokenAsync({
    projectId: capability.projectId,
  });

  return {
    token: response.data,
    platform: Platform.OS === 'ios' ? 'IOS' : 'ANDROID',
    deviceName: `ID Industrial ${Platform.OS === 'ios' ? 'iPhone' : 'Android'}`,
  };
}
