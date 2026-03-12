import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Required by Laravel Echo
;(window as any).Pusher = Pusher

let echoInstance: Echo<'reverb'> | null = null

export function getEcho(): Echo<'reverb'> {
  if (echoInstance) return echoInstance

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/api/v1/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('token') || ''}`,
      },
    },
  })

  return echoInstance
}

export function disconnectEcho() {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
  }
}

export function updateEchoToken() {
  if (!echoInstance) return
  const connector = echoInstance.connector as any
  if (connector?.pusher?.config?.auth?.headers) {
    connector.pusher.config.auth.headers.Authorization = `Bearer ${localStorage.getItem('token') || ''}`
  }
}
