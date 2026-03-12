import { setCssVar } from 'quasar'
import type { TenantSettings } from '@/types'

const DEFAULTS = {
  primary: '#1976D2',
  secondary: '#26A69A',
  accent: '#9C27B0',
}

export function applyTenantTheme(settings: TenantSettings | null | undefined) {
  setCssVar('primary', settings?.primary_color || DEFAULTS.primary)
  setCssVar('secondary', settings?.secondary_color || DEFAULTS.secondary)
  setCssVar('accent', settings?.accent_color || DEFAULTS.accent)
}
