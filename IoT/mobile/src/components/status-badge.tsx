import { useEffect, useRef } from 'react';
import { AccessibilityInfo, Animated, Easing, StyleSheet, Text, View } from 'react-native';

import { colors, radius, spacing } from '@/theme/colors';
import type { GeneralState } from '@/types/api';

const stateColors: Record<GeneralState, string> = {
  NORMAL: colors.normal,
  ALERTA: colors.warning,
  ALARMA: colors.critical,
  OFFLINE: colors.muted,
};

export function StatusBadge({ state }: { state: GeneralState }) {
  const color = stateColors[state];
  const pulse = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    let mounted = true;
    let animation: Animated.CompositeAnimation | null = null;
    pulse.stopAnimation();
    pulse.setValue(0);
    if (!['ALERTA', 'ALARMA'].includes(state)) return undefined;

    void AccessibilityInfo.isReduceMotionEnabled().then((reduceMotion) => {
      if (!mounted || reduceMotion) return;
      animation = Animated.loop(
        Animated.sequence([
          Animated.timing(pulse, {
            duration: 900,
            easing: Easing.out(Easing.cubic),
            toValue: 1,
            useNativeDriver: true,
          }),
          Animated.delay(700),
          Animated.timing(pulse, {
            duration: 0,
            toValue: 0,
            useNativeDriver: true,
          }),
        ]),
      );
      animation.start();
    });

    return () => {
      mounted = false;
      animation?.stop();
    };
  }, [pulse, state]);

  return (
    <View style={[styles.badge, { borderColor: color }]}>
      <View style={styles.dotWrap}>
        <Animated.View
          style={[
            styles.ring,
            {
              borderColor: color,
              opacity: pulse.interpolate({ inputRange: [0, 1], outputRange: [0.5, 0] }),
              transform: [{
                scale: pulse.interpolate({ inputRange: [0, 1], outputRange: [1, 2.2] }),
              }],
            },
          ]}
        />
        <View style={[styles.dot, { backgroundColor: color }]} />
      </View>
      <Text style={[styles.label, { color }]}>{state}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignItems: 'center',
    alignSelf: 'flex-start',
    borderRadius: radius.md,
    borderWidth: 1,
    flexDirection: 'row',
    gap: spacing.sm,
    minHeight: 34,
    paddingHorizontal: spacing.md,
  },
  dot: {
    borderRadius: 4,
    height: 8,
    width: 8,
  },
  dotWrap: {
    alignItems: 'center',
    height: 12,
    justifyContent: 'center',
    width: 12,
  },
  ring: {
    borderRadius: 8,
    borderWidth: 1,
    height: 12,
    position: 'absolute',
    width: 12,
  },
  label: {
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
  },
});
