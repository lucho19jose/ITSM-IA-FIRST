import { ref, onMounted, onUnmounted } from 'vue';
import { Capacitor } from '@capacitor/core';
import { App, type URLOpenListenerEvent } from '@capacitor/app';
import { StatusBar, Style } from '@capacitor/status-bar';
import { SplashScreen } from '@capacitor/splash-screen';
import { Haptics, ImpactStyle, NotificationType } from '@capacitor/haptics';
import { Network, type ConnectionStatus } from '@capacitor/network';
import { Keyboard } from '@capacitor/keyboard';

/**
 * Composable para funciones nativas de Capacitor
 * Detecta automáticamente si estamos en plataforma nativa o web
 */
export function useNative() {
  const isNative = ref(Capacitor.isNativePlatform());
  const platform = ref(Capacitor.getPlatform());
  const isOnline = ref(true);
  const keyboardVisible = ref(false);

  // Inicializar listeners nativos
  const initNativeListeners = async () => {
    if (!isNative.value) return;

    // Network listener
    const networkStatus = await Network.getStatus();
    isOnline.value = networkStatus.connected;

    Network.addListener('networkStatusChange', (status: ConnectionStatus) => {
      isOnline.value = status.connected;
    });

    // Deep link listener
    App.addListener('appUrlOpen', (event: URLOpenListenerEvent) => {
      // Manejar deep links aquí
      console.log('Deep link:', event.url);
    });

    // Keyboard listeners (Android/iOS)
    Keyboard.addListener('keyboardWillShow', () => {
      keyboardVisible.value = true;
    });

    Keyboard.addListener('keyboardWillHide', () => {
      keyboardVisible.value = false;
    });

    // Ocultar splash screen después de inicialización
    await SplashScreen.hide();
  };

  // Configurar StatusBar
  const setStatusBarStyle = async (style: 'light' | 'dark') => {
    if (!isNative.value) return;
    await StatusBar.setStyle({ 
      style: style === 'light' ? Style.Light : Style.Dark 
    });
  };

  const setStatusBarColor = async (color: string) => {
    if (!isNative.value || platform.value !== 'android') return;
    await StatusBar.setBackgroundColor({ color });
  };

  // Haptics
  const vibrate = async (style: 'light' | 'medium' | 'heavy' = 'medium') => {
    if (!isNative.value) return;
    const impactStyle = {
      light: ImpactStyle.Light,
      medium: ImpactStyle.Medium,
      heavy: ImpactStyle.Heavy,
    };
    await Haptics.impact({ style: impactStyle[style] });
  };

  const vibrateNotification = async (type: 'success' | 'warning' | 'error' = 'success') => {
    if (!isNative.value) return;
    const notificationType = {
      success: NotificationType.Success,
      warning: NotificationType.Warning,
      error: NotificationType.Error,
    };
    await Haptics.notification({ type: notificationType[type] });
  };

  // App state
  const exitApp = () => {
    if (isNative.value) {
      App.exitApp();
    }
  };

  // Manejo del botón atrás en Android
  const handleBackButton = (callback: () => void) => {
    if (!isNative.value || platform.value !== 'android') return;
    
    App.addListener('backButton', ({ canGoBack }) => {
      if (canGoBack) {
        window.history.back();
      } else {
        callback();
      }
    });
  };

  onMounted(() => {
    initNativeListeners();
  });

  onUnmounted(() => {
    if (isNative.value) {
      Network.removeAllListeners();
      App.removeAllListeners();
      Keyboard.removeAllListeners();
    }
  });

  return {
    isNative,
    platform,
    isOnline,
    keyboardVisible,
    setStatusBarStyle,
    setStatusBarColor,
    vibrate,
    vibrateNotification,
    exitApp,
    handleBackButton,
  };
}
