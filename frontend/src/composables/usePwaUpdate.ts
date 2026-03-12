import { ref } from 'vue'
import { useRegisterSW } from 'virtual:pwa-register/vue'

const needRefresh = ref(false)
let updateSW: ((reloadPage?: boolean) => Promise<void>) | undefined

export function usePwaUpdate() {
  if (!updateSW) {
    const reg = useRegisterSW({
      onNeedRefresh() {
        needRefresh.value = true
      },
      onOfflineReady() {
        // App cached and ready for offline use — no action needed
      },
    })
    updateSW = reg.updateServiceWorker
  }

  function applyUpdate() {
    updateSW?.(true)
    needRefresh.value = false
  }

  function dismissUpdate() {
    needRefresh.value = false
  }

  return { needRefresh, applyUpdate, dismissUpdate }
}
