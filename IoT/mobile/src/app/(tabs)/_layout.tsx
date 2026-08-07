import Ionicons from '@expo/vector-icons/Ionicons';
import { Redirect, Tabs } from 'expo-router';
import { useEffect, useRef } from 'react';
import { ActivityIndicator, Animated, StyleSheet, useWindowDimensions, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useAuth } from '@/context/auth-context';
import { colors } from '@/theme/colors';

function TabIcon({ color, focused, name, size }: {
  color: string;
  focused: boolean;
  name: React.ComponentProps<typeof Ionicons>['name'];
  size: number;
}) {
  const progress = useRef(new Animated.Value(focused ? 1 : 0)).current;
  useEffect(() => {
    Animated.spring(progress, {
      damping: 15,
      mass: 0.7,
      stiffness: 220,
      toValue: focused ? 1 : 0,
      useNativeDriver: true,
    }).start();
  }, [focused, progress]);

  return (
    <Animated.View style={{
      transform: [
        { scale: progress.interpolate({ inputRange: [0, 1], outputRange: [1, 1.14] }) },
        { translateY: progress.interpolate({ inputRange: [0, 1], outputRange: [0, -2] }) },
      ],
    }}>
      <Ionicons color={color} name={name} size={size} />
    </Animated.View>
  );
}

export default function TabLayout() {
  const { loading, user } = useAuth();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const compactTabs = width < 400;

  if (loading) {
    return (
      <View style={styles.loading}>
        <ActivityIndicator color={colors.warning} />
      </View>
    );
  }
  if (!user) {
    return <Redirect href="/login" />;
  }

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        sceneStyle: { backgroundColor: colors.background },
        tabBarActiveTintColor: colors.warning,
        tabBarInactiveTintColor: colors.muted,
        tabBarLabelStyle: {
          fontSize: compactTabs ? 8 : 9,
          fontWeight: '700',
          letterSpacing: 0,
        },
        tabBarItemStyle: {
          minWidth: 0,
        },
        tabBarStyle: {
          backgroundColor: colors.surfaceStrong,
          borderTopColor: colors.border,
          height: 58 + Math.max(insets.bottom, 8),
          paddingBottom: Math.max(insets.bottom, 8),
          paddingTop: 7,
        },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Monitoreo',
          tabBarIcon: ({ color, focused, size }) => (
            <TabIcon color={color} focused={focused} name="pulse-outline" size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="alertas"
        options={{
          title: 'Alertas',
          tabBarIcon: ({ color, focused, size }) => (
            <TabIcon color={color} focused={focused} name="warning-outline" size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="graficas"
        options={{
          title: 'En vivo',
          tabBarIcon: ({ color, focused, size }) => (
            <TabIcon color={color} focused={focused} name="analytics-outline" size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="dispositivos"
        options={{
          title: 'Equipos',
          tabBarIcon: ({ color, focused, size }) => (
            <TabIcon color={color} focused={focused} name="hardware-chip-outline" size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="rutinas"
        options={{
          title: 'Rutinas',
          tabBarIcon: ({ color, focused, size }) => (
            <TabIcon color={color} focused={focused} name="timer-outline" size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="cuenta"
        options={{
          title: 'Cuenta',
          tabBarIcon: ({ color, focused, size }) => (
            <TabIcon color={color} focused={focused} name="person-outline" size={size} />
          ),
        }}
      />
    </Tabs>
  );
}

const styles = StyleSheet.create({
  loading: {
    alignItems: 'center',
    backgroundColor: colors.background,
    flex: 1,
    justifyContent: 'center',
  },
});
