import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type PropsWithChildren,
} from 'react';

import * as api from '@/services/api';
import { removePushToken } from '@/services/push-token-storage';
import { readToken, removeToken, saveToken } from '@/services/token-storage';
import type { MobileUser } from '@/types/api';

type AuthContextValue = {
  loading: boolean;
  token: string | null;
  user: MobileUser | null;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: PropsWithChildren) {
  const [loading, setLoading] = useState(true);
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<MobileUser | null>(null);

  useEffect(() => {
    let active = true;

    async function restoreSession() {
      const storedToken = await readToken();
      if (!storedToken) {
        if (active) setLoading(false);
        return;
      }

      try {
        const currentUser = await api.getCurrentUser(storedToken);
        if (active) {
          setToken(storedToken);
          setUser(currentUser);
        }
      } catch {
        await removeToken();
      } finally {
        if (active) setLoading(false);
      }
    }

    void restoreSession();
    return () => {
      active = false;
    };
  }, []);

  const signIn = useCallback(async (email: string, password: string) => {
    const data = await api.login(email, password);
    await saveToken(data.sesion.token);
    setToken(data.sesion.token);
    setUser(data.usuario);
  }, []);

  const signOut = useCallback(async () => {
    const activeToken = token;
    setToken(null);
    setUser(null);
    await removeToken();
    await removePushToken();
    if (activeToken) {
      try {
        await api.logout(activeToken);
      } catch {
        // La sesion local debe cerrarse aunque el servidor no responda.
      }
    }
  }, [token]);

  const value = useMemo(
    () => ({ loading, token, user, signIn, signOut }),
    [loading, signIn, signOut, token, user],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth debe usarse dentro de AuthProvider');
  }
  return context;
}
