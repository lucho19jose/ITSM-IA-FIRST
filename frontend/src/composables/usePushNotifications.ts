import { ref } from 'vue';
import { Capacitor } from '@capacitor/core';
import { PushNotifications, type Token, type PushNotificationSchema } from '@capacitor/push-notifications';

/**
 * Composable para manejar Push Notifications en la app nativa
 */
export function usePushNotifications() {
  const isNative = Capacitor.isNativePlatform();
  const fcmToken = ref<string | null>(null);
  const notifications = ref<PushNotificationSchema[]>([]);
  const permissionGranted = ref(false);

  // Solicitar permisos y obtener token
  const requestPermissions = async (): Promise<boolean> => {
    if (!isNative) {
      console.log('Push notifications solo disponible en dispositivos nativos');
      return false;
    }

    try {
      const permission = await PushNotifications.requestPermissions();
      permissionGranted.value = permission.receive === 'granted';

      if (permissionGranted.value) {
        await PushNotifications.register();
      }

      return permissionGranted.value;
    } catch (error) {
      // Firebase no está configurado o hubo un error de inicialización
      console.warn('Push notifications no disponibles:', error);
      permissionGranted.value = false;
      return false;
    }
  };

  // Configurar listeners
  const initPushNotifications = () => {
    if (!isNative) return;

    // Token recibido
    PushNotifications.addListener('registration', (token: Token) => {
      fcmToken.value = token.value;
      console.log('FCM Token:', token.value);
      // Enviar token al backend
      sendTokenToBackend(token.value);
    });

    // Error de registro
    PushNotifications.addListener('registrationError', (error) => {
      console.error('Error al registrar push notifications:', error);
    });

    // Notificación recibida mientras la app está abierta
    PushNotifications.addListener('pushNotificationReceived', (notification) => {
      console.log('Notificación recibida:', notification);
      notifications.value.push(notification);
      // Aquí puedes mostrar un toast o actualizar el estado de la app
    });

    // Usuario tocó la notificación
    PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
      console.log('Acción de notificación:', action);
      // Navegar a la pantalla correspondiente según action.notification.data
      handleNotificationAction(action.notification.data);
    });
  };

  // Enviar token FCM al backend Laravel
  const sendTokenToBackend = async (token: string) => {
    try {
      const apiUrl = import.meta.env.VITE_API_URL || '/api';
      const authToken = localStorage.getItem('auth_token');
      
      if (!authToken) {
        console.log('Usuario no autenticado, guardando token para después');
        localStorage.setItem('pending_fcm_token', token);
        return;
      }

      await fetch(`${apiUrl}/v1/devices/register`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${authToken}`,
        },
        body: JSON.stringify({
          token,
          platform: Capacitor.getPlatform(),
          device_name: navigator.userAgent,
        }),
      });
    } catch (error) {
      console.error('Error enviando token al backend:', error);
    }
  };

  // Manejar acción de notificación
  const handleNotificationAction = (data: Record<string, unknown>) => {
    if (!data) return;

    // Ejemplo: navegar según el tipo de notificación
    if (data.ticket_id) {
      // router.push(`/tickets/${data.ticket_id}`)
      window.location.href = `/tickets/${data.ticket_id}`;
    }
  };

  // Limpiar listeners al desmontar
  const cleanup = () => {
    if (isNative) {
      PushNotifications.removeAllListeners();
    }
  };

  return {
    fcmToken,
    notifications,
    permissionGranted,
    requestPermissions,
    initPushNotifications,
    cleanup,
  };
}
