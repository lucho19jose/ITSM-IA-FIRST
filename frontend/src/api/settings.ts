import { get, put, post, del } from '@/utils/api'
import type { Tenant } from '@/types'

export function getSettings() {
  return get<{ data: Tenant }>('settings')
}

export function updateSettings(data: Partial<Tenant>) {
  return put<{ data: Tenant }>('settings', data)
}

export function updateDomain(custom_domain: string | null) {
  return put<{ data: Tenant; message: string }>('settings/domain', { custom_domain })
}

export function verifyDomain() {
  return get<{ verified: boolean; domain: string; message: string }>('settings/verify-domain')
}

export function uploadLogo(file: File) {
  const form = new FormData()
  form.append('logo', file)
  return post<{ data: Tenant; message: string }>('settings/branding/logo', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function deleteLogo() {
  return del<{ data: Tenant; message: string }>('settings/branding/logo')
}

export function uploadFavicon(file: File) {
  const form = new FormData()
  form.append('favicon', file)
  return post<{ data: Tenant; message: string }>('settings/branding/favicon', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function deleteFavicon() {
  return del<{ data: Tenant; message: string }>('settings/branding/favicon')
}

export function updateBrandColors(colors: {
  primary_color: string | null
  secondary_color: string | null
  accent_color: string | null
}) {
  return put<{ data: Tenant; message: string }>('settings/branding/colors', colors)
}
