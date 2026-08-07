import Ionicons from '@expo/vector-icons/Ionicons';
import { useRef, useState } from 'react';
import { Redirect } from 'expo-router';
import {
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  useWindowDimensions,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useAuth } from '@/context/auth-context';
import { ApiError, apiBaseUrl } from '@/services/api';
import { colors, radius, spacing } from '@/theme/colors';

export default function LoginScreen() {
  const { loading, signIn, user } = useAuth();
  const { width: viewportWidth } = useWindowDimensions();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordVisible, setPasswordVisible] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const passwordInput = useRef<TextInput>(null);

  if (!loading && user) {
    return <Redirect href="/(tabs)" />;
  }

  async function submit() {
    if (!email.trim() || !password) {
      setError('Ingresa tu correo y contraseña.');
      return;
    }
    setSubmitting(true);
    setError('');
    try {
      await signIn(email.trim(), password);
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? caught.message
          : 'No fue posible iniciar sesión.',
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={Platform.OS === 'android' ? 12 : 0}
        style={styles.keyboard}
      >
        <ScrollView
          bounces={false}
          contentContainerStyle={styles.scrollContent}
          keyboardDismissMode={Platform.OS === 'ios' ? 'interactive' : 'on-drag'}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <View
            style={[
              styles.container,
              { width: Math.max(280, Math.min(viewportWidth - 48, 440)) },
            ]}
          >
            <Image
              source={require('../../assets/logo-id-industrial.png')}
              resizeMode="contain"
              style={styles.logo}
            />
            <View style={styles.copy}>
              <Text style={styles.eyebrow}>MONITOREO INDUSTRIAL</Text>
              <Text style={styles.title}>Acceso seguro</Text>
              <Text style={styles.subtitle}>
                Consulta dispositivos, sensores y alertas desde cualquier lugar.
              </Text>
            </View>

            <View style={styles.form}>
              <View style={styles.field}>
                <Text style={styles.label}>Correo</Text>
                <TextInput
                  autoCapitalize="none"
                  autoComplete="email"
                  blurOnSubmit={false}
                  keyboardType="email-address"
                  onChangeText={setEmail}
                  onSubmitEditing={() => passwordInput.current?.focus()}
                  placeholder="usuario@empresa.com"
                  placeholderTextColor={colors.muted}
                  returnKeyType="next"
                  style={styles.input}
                  value={email}
                />
              </View>
              <View style={styles.field}>
                <Text style={styles.label}>Contraseña</Text>
                <View style={styles.passwordField}>
                  <TextInput
                    autoCapitalize="none"
                    autoComplete="current-password"
                    onChangeText={setPassword}
                    onSubmitEditing={() => void submit()}
                    placeholder="Tu contraseña"
                    placeholderTextColor={colors.muted}
                    ref={passwordInput}
                    returnKeyType="done"
                    secureTextEntry={!passwordVisible}
                    style={[styles.input, styles.passwordInput]}
                    value={password}
                  />
                  <Pressable
                    accessibilityLabel={passwordVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                    hitSlop={8}
                    onPress={() => setPasswordVisible((visible) => !visible)}
                    style={styles.passwordToggle}
                  >
                    <Ionicons
                      color={colors.muted}
                      name={passwordVisible ? 'eye-off-outline' : 'eye-outline'}
                      size={21}
                    />
                  </Pressable>
                </View>
              </View>
              {error ? <Text style={styles.error}>{error}</Text> : null}
              <Pressable
                disabled={submitting}
                onPress={() => void submit()}
                style={({ pressed }) => [
                  styles.button,
                  pressed && styles.buttonPressed,
                  submitting && styles.buttonDisabled,
                ]}
              >
                {submitting ? (
                  <ActivityIndicator color={colors.black} />
                ) : (
                  <Text style={styles.buttonText}>Iniciar sesión</Text>
                )}
              </Pressable>
            </View>

            <Text style={styles.server}>
              {apiBaseUrl ? 'API configurada' : 'API pendiente de configurar'}
            </Text>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    backgroundColor: colors.background,
    flex: 1,
    maxWidth: '100%',
    width: '100%',
  },
  keyboard: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingBottom: spacing.xl,
    paddingTop: spacing.xl,
  },
  container: {
    alignSelf: 'center',
    justifyContent: 'center',
  },
  logo: {
    alignSelf: 'flex-start',
    height: 58,
    marginBottom: spacing.xxl,
    width: 220,
  },
  copy: {
    gap: spacing.sm,
    marginBottom: spacing.xl,
    minWidth: 0,
  },
  eyebrow: {
    color: colors.warning,
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
  },
  title: {
    color: colors.textStrong,
    fontSize: 34,
    fontWeight: '900',
    letterSpacing: 0,
  },
  subtitle: {
    color: colors.muted,
    flexShrink: 1,
    fontSize: 16,
    lineHeight: 23,
  },
  form: {
    alignSelf: 'stretch',
    gap: spacing.lg,
    minWidth: 0,
  },
  field: {
    gap: spacing.sm,
  },
  label: {
    color: colors.text,
    fontSize: 14,
    fontWeight: '700',
  },
  input: {
    backgroundColor: colors.surfaceStrong,
    borderColor: colors.border,
    borderRadius: radius.md,
    borderWidth: 1,
    color: colors.textStrong,
    fontSize: 16,
    minWidth: 0,
    minHeight: 54,
    paddingHorizontal: spacing.lg,
  },
  passwordField: {
    position: 'relative',
  },
  passwordInput: {
    paddingRight: 52,
  },
  passwordToggle: {
    alignItems: 'center',
    height: 54,
    justifyContent: 'center',
    position: 'absolute',
    right: 0,
    top: 0,
    width: 52,
  },
  error: {
    color: colors.critical,
    fontSize: 14,
    lineHeight: 20,
  },
  button: {
    alignItems: 'center',
    backgroundColor: colors.warning,
    borderRadius: radius.md,
    justifyContent: 'center',
    minHeight: 54,
  },
  buttonPressed: {
    opacity: 0.82,
  },
  buttonDisabled: {
    opacity: 0.65,
  },
  buttonText: {
    color: colors.black,
    fontSize: 16,
    fontWeight: '900',
  },
  server: {
    color: colors.muted,
    fontSize: 12,
    marginTop: spacing.xl,
    textAlign: 'center',
  },
});
