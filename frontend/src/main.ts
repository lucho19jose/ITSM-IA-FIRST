import { createApp } from 'vue'
import { Quasar, Notify, Dialog, Loading } from 'quasar'
import { pinia } from './stores'
import router from './router'
import i18n from './i18n'
import App from './App.vue'

import '@quasar/extras/material-icons/material-icons.css'
import 'quasar/src/css/index.sass'
import 'nprogress/nprogress.css'

// Unregister stale service workers in development
if (import.meta.env.DEV && 'serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then((registrations) => {
    registrations.forEach((r) => r.unregister())
  })
}

const app = createApp(App)

app.use(pinia)
app.use(router)
app.use(i18n)
app.use(Quasar, {
  plugins: { Notify, Dialog, Loading },
  config: {
    notify: { position: 'top-right', timeout: 3000 },
  },
})

// Wait for initial navigation to resolve before mounting
// This prevents components from rendering before all plugins are ready
router.isReady().then(() => {
  app.mount('#app')
})
