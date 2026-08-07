import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

const PUSH_TOKEN_KEY = 'idindustrial.mobile.expoPushToken';

export async function readPushToken(): Promise<string | null> {
  if (Platform.OS === 'web') {
    return globalThis.localStorage?.getItem(PUSH_TOKEN_KEY) ?? null;
  }
  return SecureStore.getItemAsync(PUSH_TOKEN_KEY);
}

export async function savePushToken(token: string): Promise<void> {
  if (Platform.OS === 'web') {
    globalThis.localStorage?.setItem(PUSH_TOKEN_KEY, token);
    return;
  }
  await SecureStore.setItemAsync(PUSH_TOKEN_KEY, token);
}

export async function removePushToken(): Promise<void> {
  if (Platform.OS === 'web') {
    globalThis.localStorage?.removeItem(PUSH_TOKEN_KEY);
    return;
  }
  await SecureStore.deleteItemAsync(PUSH_TOKEN_KEY);
}
