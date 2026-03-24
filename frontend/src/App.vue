<script setup lang="ts">
import { onMounted } from 'vue'
import { usePwaUpdate } from '@/composables/usePwaUpdate'
import { useNative } from '@/composables/useNative'
import { usePushNotifications } from '@/composables/usePushNotifications'
import { useQuasar } from 'quasar'

const $q = useQuasar()
const { needRefresh, applyUpdate, dismissUpdate } = usePwaUpdate()
const { isNative, handleBackButton, setStatusBarColor } = useNative()
const { requestPermissions, initPushNotifications } = usePushNotifications()

onMounted(async () => {
  if (isNative.value) {
    // Configurar StatusBar con color de la marca
    await setStatusBarColor('#1976D2')

    // Manejar botón atrás en Android
    handleBackButton(() => {
      $q.dialog({
        title: 'Salir',
        message: '¿Deseas salir de la aplicación?',
        cancel: true,
        persistent: true,
      }).onOk(() => {
        // El composable useNative maneja el exit
      })
    })

    // Push notifications desactivadas temporalmente
    // Para habilitarlas, configura Firebase y descomenta:
    // const granted = await requestPermissions()
    // if (granted) {
    //   initPushNotifications()
    // }
  }
})
</script>

<template>
  <router-view />

  <!-- PWA update prompt -->
  <div v-if="needRefresh" class="pwa-update-banner">
    <span>Nueva version disponible</span>
    <button class="pwa-update-btn" @click="applyUpdate">Actualizar</button>
    <button class="pwa-dismiss-btn" @click="dismissUpdate">Cerrar</button>
  </div>
</template>

<style>
.pwa-update-banner {
  position: fixed;
  bottom: 16px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 9999;
  display: flex;
  align-items: center;
  gap: 12px;
  background: #1976d2;
  color: white;
  padding: 12px 20px;
  border-radius: 8px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
  font-size: 14px;
}
.pwa-update-btn {
  background: white;
  color: #1976d2;
  border: none;
  border-radius: 4px;
  padding: 6px 16px;
  font-weight: 600;
  cursor: pointer;
}
.pwa-dismiss-btn {
  background: transparent;
  color: rgba(255, 255, 255, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.4);
  border-radius: 4px;
  padding: 6px 12px;
  cursor: pointer;
}

/* NProgress — brand-colored bar */
#nprogress .bar {
  background: #1976d2 !important;
  height: 3px !important;
}
#nprogress .peg {
  box-shadow: 0 0 10px #1976d2, 0 0 5px #1976d2 !important;
}
</style>
