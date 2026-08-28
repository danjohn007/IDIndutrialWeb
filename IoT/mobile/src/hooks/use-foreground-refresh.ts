import { useEffect, useRef } from 'react';
import { AppState, type AppStateStatus } from 'react-native';

export function useForegroundRefresh(
  refresh: () => void | Promise<void>,
  intervalMs: number,
  enabled = true,
) {
  const refreshRef = useRef(refresh);
  const runningRef = useRef(false);

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

    const run = () => {
      if (runningRef.current) return;
      runningRef.current = true;
      Promise.resolve(refreshRef.current())
        .catch(() => undefined)
        .finally(() => {
          runningRef.current = false;
        });
    };

    const start = () => {
      stop();
      if (appState !== 'active') return;
      timer = setInterval(run, intervalMs);
    };

    const subscription = AppState.addEventListener('change', (nextState) => {
      const returningToForeground = appState !== 'active' && nextState === 'active';
      appState = nextState;
      if (returningToForeground) run();
      start();
    });

    start();
    return () => {
      stop();
      subscription.remove();
    };
  }, [enabled, intervalMs]);
}
