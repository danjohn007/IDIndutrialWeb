import { useEffect, useRef } from 'react';
import { AppState, type AppStateStatus } from 'react-native';

export function useForegroundRefresh(
  refresh: () => void,
  intervalMs: number,
  enabled = true,
) {
  const refreshRef = useRef(refresh);

  useEffect(() => {
    refreshRef.current = refresh;
  }, [refresh]);

  useEffect(() => {
    if (!enabled) return undefined;

    let appState: AppStateStatus = AppState.currentState;
    let timer: ReturnType<typeof setInterval> | null = null;

    const stop = () => {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    };

    const start = () => {
      stop();
      if (appState !== 'active') return;
      timer = setInterval(() => refreshRef.current(), intervalMs);
    };

    const subscription = AppState.addEventListener('change', (nextState) => {
      const returningToForeground = appState !== 'active' && nextState === 'active';
      appState = nextState;
      if (returningToForeground) refreshRef.current();
      start();
    });

    start();
    return () => {
      stop();
      subscription.remove();
    };
  }, [enabled, intervalMs]);
}
