import axios, { type AxiosRequestConfig } from 'axios'
import NProgress from 'nprogress'
import { pinia } from '@/stores'
import { useAuthStore } from '@/stores/auth'
import { Notify } from 'quasar'
import { Capacitor } from '@capacitor/core'

let activeRequests = 0

// En móvil, usar la URL del servidor configurada; en web, usar ruta relativa
const getApiBaseUrl = (): string => {
  if (Capacitor.isNativePlatform()) {
    // URL del backend para la app móvil - configurable via .env
    // IMPORTANTE: Para dispositivos físicos, usar la IP de tu red local
    // Ejemplo: VITE_API_URL=http://192.168.1.100:8000/api/v1
    const apiUrl = import.meta.env.VITE_API_URL
    if (apiUrl) {
      console.log('[API] Using configured URL:', apiUrl)
      return apiUrl
    }
    // Fallback para emulador de Android
    console.warn('[API] No VITE_API_URL configured, using emulator fallback 10.0.2.2')
    return 'http://10.0.2.2:8000/api/v1'
  }
  return '/api/v1'
}

const instance = axios.create({
  baseURL: getApiBaseUrl(),
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

instance.interceptors.request.use((config) => {
  if (activeRequests++ === 0) NProgress.start()
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

instance.interceptors.response.use(
  (response) => {
    if (--activeRequests === 0) NProgress.done()
    return response
  },
  (error) => {
    if (--activeRequests === 0) NProgress.done()
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      const auth = useAuthStore(pinia)
      auth.user = null
      // Redirect to portal login if in portal context
      const portalSlug = localStorage.getItem('portal_tenant_slug')
      if (portalSlug) {
        localStorage.removeItem('portal_tenant_slug')
        window.location.href = `/portal/${portalSlug}/login`
      } else {
        window.location.href = '/login'
      }
    } else if (error.response?.status === 422) {
      const errors = error.response.data.errors
      if (errors) {
        const firstError = Object.values(errors)[0] as string[]
        Notify.create({ type: 'negative', message: firstError[0] })
      }
    } else if (error.response?.status >= 500) {
      Notify.create({ type: 'negative', message: 'Error del servidor. Intente nuevamente.' })
    }
    return Promise.reject(error)
  }
)

export async function get<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
  const { data } = await instance.get<T>(url, config)
  return data
}

export async function post<T = any>(url: string, body?: any, config?: AxiosRequestConfig): Promise<T> {
  const { data } = await instance.post<T>(url, body, config)
  return data
}

export async function put<T = any>(url: string, body?: any, config?: AxiosRequestConfig): Promise<T> {
  const { data } = await instance.put<T>(url, body, config)
  return data
}

export async function patch<T = any>(url: string, body?: any, config?: AxiosRequestConfig): Promise<T> {
  const { data } = await instance.patch<T>(url, body, config)
  return data
}

export async function del<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
  const { data } = await instance.delete<T>(url, config)
  return data
}

export default instance
