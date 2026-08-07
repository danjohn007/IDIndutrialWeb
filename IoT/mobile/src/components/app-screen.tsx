import type { PropsWithChildren, ReactNode } from 'react';
import {
  ScrollView,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
  type ScrollViewProps,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Entrance } from '@/components/motion';
import { colors, spacing } from '@/theme/colors';

type AppScreenProps = PropsWithChildren<{
  title: string;
  eyebrow?: string;
  leading?: ReactNode;
  action?: ReactNode;
  includeBottomInset?: boolean;
  scrollProps?: ScrollViewProps;
}>;

export function AppScreen({
  title,
  eyebrow,
  leading,
  action,
  includeBottomInset = false,
  children,
  scrollProps,
}: AppScreenProps) {
  const { width } = useWindowDimensions();
  const compact = width < 380;

  return (
    <SafeAreaView
      edges={includeBottomInset ? ['top', 'bottom'] : ['top']}
      style={styles.safeArea}
    >
      <ScrollView
        contentContainerStyle={[
          styles.content,
          compact && styles.contentCompact,
        ]}
        showsVerticalScrollIndicator={false}
        {...scrollProps}
      >
        <Entrance style={styles.header}>
          {leading}
          <View style={styles.headerCopy}>
            {eyebrow ? <Text style={styles.eyebrow}>{eyebrow}</Text> : null}
            <Text style={[styles.title, compact && styles.titleCompact]}>{title}</Text>
          </View>
          {action}
        </Entrance>
        <Entrance delay={70} style={styles.body}>
          {children}
        </Entrance>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    alignSelf: 'center',
    flexGrow: 1,
    gap: spacing.lg,
    maxWidth: 760,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.xxl,
    paddingTop: spacing.sm,
    width: '100%',
  },
  contentCompact: {
    gap: spacing.md,
    paddingHorizontal: spacing.md,
  },
  header: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: spacing.md,
    justifyContent: 'space-between',
    minHeight: 58,
    paddingBottom: spacing.sm,
    borderBottomColor: colors.borderSoft,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  body: {
    gap: spacing.lg,
  },
  headerCopy: {
    flex: 1,
  },
  eyebrow: {
    color: colors.normal,
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  title: {
    color: colors.textStrong,
    fontSize: 28,
    fontWeight: '800',
    letterSpacing: 0,
    marginTop: spacing.xs,
  },
  titleCompact: {
    fontSize: 25,
  },
});
