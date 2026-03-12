<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { Notify } from 'quasar'
import { setCssVar } from 'quasar'
import {
  getSettings, updateSettings, updateDomain, verifyDomain,
  uploadLogo, deleteLogo, uploadFavicon, deleteFavicon,
  updateBrandColors,
} from '@/api/settings'
import { applyTenantTheme } from '@/utils/theme'
import type { Tenant } from '@/types'

const { t } = useI18n()
const auth = useAuthStore()

const loading = ref(true)
const tenant = ref<Tenant | null>(null)

// Company form
const companyForm = ref({ name: '', ruc: '' })
const savingCompany = ref(false)

// Domain form
const domainForm = ref({ custom_domain: '' })
const savingDomain = ref(false)
const verifying = ref(false)
const verificationResult = ref<{ verified: boolean; message: string } | null>(null)

// Branding
const uploadingLogo = ref(false)
const uploadingFavicon = ref(false)
const logoInputRef = ref<HTMLInputElement | null>(null)
const faviconInputRef = ref<HTMLInputElement | null>(null)
const logoPreview = ref<string | null>(null)
const faviconPreview = ref<string | null>(null)

// Colors
const colorDefaults = { primary: '#1976D2', secondary: '#26A69A', accent: '#9C27B0' }
const colorForm = ref({
  primary_color: colorDefaults.primary,
  secondary_color: colorDefaults.secondary,
  accent_color: colorDefaults.accent,
})
const savingColors = ref(false)
const showPrimaryPicker = ref(false)
const showSecondaryPicker = ref(false)
const showAccentPicker = ref(false)

function syncTenant(data: Tenant) {
  tenant.value = data
  // Refresh the auth store so layout picks up the new logo/favicon
  if (auth.user?.tenant) {
    auth.user = { ...auth.user, tenant: data }
  }
}

onMounted(async () => {
  try {
    const res = await getSettings()
    tenant.value = res.data
    companyForm.value = {
      name: res.data.name,
      ruc: res.data.ruc || '',
    }
    domainForm.value = {
      custom_domain: res.data.custom_domain || '',
    }
    colorForm.value = {
      primary_color: res.data.settings?.primary_color || colorDefaults.primary,
      secondary_color: res.data.settings?.secondary_color || colorDefaults.secondary,
      accent_color: res.data.settings?.accent_color || colorDefaults.accent,
    }
  } finally {
    loading.value = false
  }
})

async function onSaveCompany() {
  savingCompany.value = true
  try {
    const res = await updateSettings({
      name: companyForm.value.name,
      ruc: companyForm.value.ruc || null,
    } as any)
    syncTenant(res.data)
    Notify.create({ type: 'positive', message: 'Configuración actualizada' })
  } finally {
    savingCompany.value = false
  }
}

async function onSaveDomain() {
  savingDomain.value = true
  try {
    const res = await updateDomain(domainForm.value.custom_domain || null)
    syncTenant(res.data)
    verificationResult.value = null
    Notify.create({ type: 'positive', message: res.message })
  } catch {
    // handled by interceptor
  } finally {
    savingDomain.value = false
  }
}

async function onVerifyDomain() {
  verifying.value = true
  try {
    const res = await verifyDomain()
    verificationResult.value = res
  } finally {
    verifying.value = false
  }
}

function onRemoveDomain() {
  domainForm.value.custom_domain = ''
  onSaveDomain()
}

function onPickLogo() {
  logoInputRef.value?.click()
}

async function onLogoSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploadingLogo.value = true
  try {
    const res = await uploadLogo(file)
    syncTenant(res.data)
    logoPreview.value = null
    Notify.create({ type: 'positive', message: res.message })
  } finally {
    uploadingLogo.value = false
    if (logoInputRef.value) logoInputRef.value.value = ''
  }
}

async function onDeleteLogo() {
  uploadingLogo.value = true
  try {
    const res = await deleteLogo()
    syncTenant(res.data)
    logoPreview.value = null
    Notify.create({ type: 'positive', message: res.message })
  } finally {
    uploadingLogo.value = false
  }
}

function onPickFavicon() {
  faviconInputRef.value?.click()
}

async function onFaviconSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploadingFavicon.value = true
  try {
    const res = await uploadFavicon(file)
    syncTenant(res.data)
    faviconPreview.value = null
    updateDocFavicon(res.data.favicon_url)
    Notify.create({ type: 'positive', message: res.message })
  } finally {
    uploadingFavicon.value = false
    if (faviconInputRef.value) faviconInputRef.value.value = ''
  }
}

async function onDeleteFavicon() {
  uploadingFavicon.value = true
  try {
    const res = await deleteFavicon()
    syncTenant(res.data)
    faviconPreview.value = null
    updateDocFavicon(null)
    Notify.create({ type: 'positive', message: res.message })
  } finally {
    uploadingFavicon.value = false
  }
}

function updateDocFavicon(url: string | null) {
  const link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
  if (link) {
    link.href = url || '/vite.svg'
  }
}

function onColorPreview(type: 'primary' | 'secondary' | 'accent', color: string | number | null) {
  if (typeof color === 'string' && /^#[0-9A-Fa-f]{6}$/.test(color)) {
    setCssVar(type, color)
  }
}

async function onSaveColors() {
  savingColors.value = true
  try {
    const res = await updateBrandColors({
      primary_color: colorForm.value.primary_color,
      secondary_color: colorForm.value.secondary_color,
      accent_color: colorForm.value.accent_color,
    })
    syncTenant(res.data)
    applyTenantTheme(res.data.settings)
    Notify.create({ type: 'positive', message: res.message })
  } finally {
    savingColors.value = false
  }
}

function onResetColors() {
  colorForm.value = {
    primary_color: colorDefaults.primary,
    secondary_color: colorDefaults.secondary,
    accent_color: colorDefaults.accent,
  }
  applyTenantTheme({
    primary_color: colorDefaults.primary,
    secondary_color: colorDefaults.secondary,
    accent_color: colorDefaults.accent,
  })
}
</script>

<template>
  <q-page padding>
    <div class="text-h5 q-mb-md">{{ t('nav.settings') }}</div>

    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <div v-else class="column q-gutter-md" style="max-width: 700px">
      <!-- Branding -->
      <q-card flat bordered>
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-md">Marca y Apariencia</div>
          <div class="text-body2 text-grey q-mb-lg">
            Personaliza el logo y favicon de tu portal de soporte.
          </div>

          <div class="row q-col-gutter-lg">
            <!-- Logo Upload -->
            <div class="col-12 col-sm-6">
              <div class="text-caption text-weight-medium q-mb-sm">Logo de la empresa</div>
              <div
                class="branding-upload-area cursor-pointer"
                @click="onPickLogo"
              >
                <q-spinner v-if="uploadingLogo" color="primary" size="32px" />
                <template v-else-if="tenant?.logo_url">
                  <img :src="tenant.logo_url" alt="Logo" class="branding-preview-img" />
                </template>
                <template v-else>
                  <q-icon name="add_photo_alternate" size="40px" color="grey-5" />
                  <div class="text-caption text-grey q-mt-sm">Haz clic para subir</div>
                </template>
              </div>
              <input
                ref="logoInputRef"
                type="file"
                accept="image/png,image/jpeg,image/svg+xml,image/webp"
                style="display: none"
                @change="onLogoSelected"
              />
              <div class="row items-center q-mt-sm q-gutter-xs">
                <div class="text-caption text-grey col">PNG, JPG, SVG o WebP. Max 2MB.</div>
                <q-btn
                  v-if="tenant?.logo_url"
                  flat
                  dense
                  size="sm"
                  color="negative"
                  icon="delete"
                  @click.stop="onDeleteLogo"
                  :loading="uploadingLogo"
                />
              </div>
            </div>

            <!-- Favicon Upload -->
            <div class="col-12 col-sm-6">
              <div class="text-caption text-weight-medium q-mb-sm">Favicon</div>
              <div
                class="branding-upload-area branding-upload-area--small cursor-pointer"
                @click="onPickFavicon"
              >
                <q-spinner v-if="uploadingFavicon" color="primary" size="24px" />
                <template v-else-if="tenant?.favicon_url">
                  <img :src="tenant.favicon_url" alt="Favicon" class="branding-preview-favicon" />
                </template>
                <template v-else>
                  <q-icon name="tab" size="32px" color="grey-5" />
                  <div class="text-caption text-grey q-mt-sm">Haz clic para subir</div>
                </template>
              </div>
              <input
                ref="faviconInputRef"
                type="file"
                accept="image/png,image/x-icon,image/svg+xml"
                style="display: none"
                @change="onFaviconSelected"
              />
              <div class="row items-center q-mt-sm q-gutter-xs">
                <div class="text-caption text-grey col">PNG, ICO o SVG. Max 512KB.</div>
                <q-btn
                  v-if="tenant?.favicon_url"
                  flat
                  dense
                  size="sm"
                  color="negative"
                  icon="delete"
                  @click.stop="onDeleteFavicon"
                  :loading="uploadingFavicon"
                />
              </div>
            </div>
          </div>

          <!-- Brand Colors -->
          <q-separator class="q-my-lg" />

          <div class="text-caption text-weight-medium q-mb-md">Colores de la aplicación</div>
          <div class="row q-col-gutter-md">
            <!-- Primary -->
            <div class="col-12 col-sm-4">
              <div class="text-caption text-grey q-mb-xs">Primario</div>
              <div class="row items-center q-gutter-sm no-wrap">
                <div
                  class="color-swatch cursor-pointer"
                  :style="{ background: colorForm.primary_color }"
                  @click="showPrimaryPicker = !showPrimaryPicker"
                />
                <q-input
                  v-model="colorForm.primary_color"
                  dense
                  outlined
                  class="col"
                  :rules="[v => /^#[0-9A-Fa-f]{6}$/.test(v) || 'Hex inválido']"
                  maxlength="7"
                  @update:model-value="v => onColorPreview('primary', v)"
                />
              </div>
              <q-menu v-model="showPrimaryPicker" anchor="bottom left" self="top left" no-focus>
                <q-color
                  v-model="colorForm.primary_color"
                  no-header
                  no-footer
                  default-view="palette"
                  @update:model-value="v => onColorPreview('primary', v)"
                />
              </q-menu>
            </div>

            <!-- Secondary -->
            <div class="col-12 col-sm-4">
              <div class="text-caption text-grey q-mb-xs">Secundario</div>
              <div class="row items-center q-gutter-sm no-wrap">
                <div
                  class="color-swatch cursor-pointer"
                  :style="{ background: colorForm.secondary_color }"
                  @click="showSecondaryPicker = !showSecondaryPicker"
                />
                <q-input
                  v-model="colorForm.secondary_color"
                  dense
                  outlined
                  class="col"
                  :rules="[v => /^#[0-9A-Fa-f]{6}$/.test(v) || 'Hex inválido']"
                  maxlength="7"
                  @update:model-value="v => onColorPreview('secondary', v)"
                />
              </div>
              <q-menu v-model="showSecondaryPicker" anchor="bottom left" self="top left" no-focus>
                <q-color
                  v-model="colorForm.secondary_color"
                  no-header
                  no-footer
                  default-view="palette"
                  @update:model-value="v => onColorPreview('secondary', v)"
                />
              </q-menu>
            </div>

            <!-- Accent -->
            <div class="col-12 col-sm-4">
              <div class="text-caption text-grey q-mb-xs">Acento</div>
              <div class="row items-center q-gutter-sm no-wrap">
                <div
                  class="color-swatch cursor-pointer"
                  :style="{ background: colorForm.accent_color }"
                  @click="showAccentPicker = !showAccentPicker"
                />
                <q-input
                  v-model="colorForm.accent_color"
                  dense
                  outlined
                  class="col"
                  :rules="[v => /^#[0-9A-Fa-f]{6}$/.test(v) || 'Hex inválido']"
                  maxlength="7"
                  @update:model-value="v => onColorPreview('accent', v)"
                />
              </div>
              <q-menu v-model="showAccentPicker" anchor="bottom left" self="top left" no-focus>
                <q-color
                  v-model="colorForm.accent_color"
                  no-header
                  no-footer
                  default-view="palette"
                  @update:model-value="v => onColorPreview('accent', v)"
                />
              </q-menu>
            </div>
          </div>

          <!-- Preview stripe -->
          <div class="row q-mt-md" style="height: 8px; border-radius: 4px; overflow: hidden">
            <div class="col" :style="{ background: colorForm.primary_color }" />
            <div class="col" :style="{ background: colorForm.secondary_color }" />
            <div class="col" :style="{ background: colorForm.accent_color }" />
          </div>

          <div class="row q-mt-md q-gutter-sm justify-end">
            <q-btn flat label="Restablecer" @click="onResetColors" />
            <q-btn color="primary" label="Guardar colores" :loading="savingColors" @click="onSaveColors" />
          </div>
        </q-card-section>
      </q-card>

      <!-- Company Info -->
      <q-card flat bordered>
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-md">Información de la Empresa</div>
          <q-form @submit.prevent="onSaveCompany" class="q-gutter-md">
            <q-input v-model="companyForm.name" label="Nombre de la empresa" outlined :rules="[val => !!val || 'Requerido']" />
            <q-input v-model="companyForm.ruc" label="RUC" outlined mask="###########" hint="11 dígitos" />
            <div class="row items-center">
              <div class="text-caption text-grey">
                Plan: <q-badge :color="tenant?.plan === 'trial' ? 'warning' : 'positive'">{{ tenant?.plan }}</q-badge>
              </div>
              <q-space />
              <q-btn type="submit" color="primary" label="Guardar" :loading="savingCompany" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>

      <!-- Subdomain Info -->
      <q-card flat bordered>
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-md">Subdominio</div>
          <q-banner class="bg-blue-1 q-mb-md" rounded>
            <div class="text-body2">
              Tu portal de soporte está disponible en:
            </div>
            <div class="text-subtitle1 text-weight-bold text-primary q-mt-xs">
              {{ tenant?.slug }}.autoservice.test
            </div>
          </q-banner>
          <div class="text-caption text-grey">
            El subdominio se genera automáticamente a partir del nombre de tu empresa y no puede ser modificado.
          </div>
        </q-card-section>
      </q-card>

      <!-- Custom Domain -->
      <q-card flat bordered>
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-sm">Dominio Personalizado</div>
          <div class="text-body2 text-grey q-mb-md">
            Configura un dominio propio para tu portal de soporte (ej: soporte.tuempresa.com)
          </div>

          <q-form @submit.prevent="onSaveDomain" class="q-gutter-md">
            <q-input
              v-model="domainForm.custom_domain"
              label="Dominio personalizado"
              outlined
              placeholder="soporte.tuempresa.com"
              hint="Deja vacío para usar solo el subdominio por defecto"
            >
              <template v-slot:prepend>
                <q-icon name="language" />
              </template>
            </q-input>

            <!-- Instructions -->
            <q-banner v-if="domainForm.custom_domain" class="bg-orange-1" rounded>
              <template v-slot:avatar>
                <q-icon name="info" color="orange" />
              </template>
              <div class="text-body2">
                <strong>Configuración DNS requerida:</strong>
                <br>Agrega un registro <strong>CNAME</strong> en tu proveedor de DNS:
                <br><code>{{ domainForm.custom_domain }} → autoservice.pe</code>
                <br><br>
                O un registro <strong>A</strong> apuntando a la IP del servidor.
              </div>
            </q-banner>

            <!-- Verification Result -->
            <q-banner v-if="verificationResult" :class="verificationResult.verified ? 'bg-green-1' : 'bg-red-1'" rounded>
              <template v-slot:avatar>
                <q-icon :name="verificationResult.verified ? 'check_circle' : 'error'" :color="verificationResult.verified ? 'positive' : 'negative'" />
              </template>
              {{ verificationResult.message }}
            </q-banner>

            <div class="row q-gutter-sm justify-end">
              <q-btn v-if="tenant?.custom_domain" flat color="negative" label="Eliminar dominio" @click="onRemoveDomain" />
              <q-btn v-if="tenant?.custom_domain" outline color="primary" label="Verificar DNS" :loading="verifying" @click="onVerifyDomain" />
              <q-btn type="submit" color="primary" label="Guardar dominio" :loading="savingDomain" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>

      <!-- Admin Links -->
      <q-card flat bordered>
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-md">Administración</div>
          <q-list>
            <q-item clickable v-ripple to="/settings/users">
              <q-item-section avatar><q-icon name="people" /></q-item-section>
              <q-item-section>
                <q-item-label>Gestionar Usuarios</q-item-label>
                <q-item-label caption>Crear y administrar usuarios del sistema</q-item-label>
              </q-item-section>
              <q-item-section side><q-icon name="chevron_right" /></q-item-section>
            </q-item>
            <q-item clickable v-ripple to="/settings/categories">
              <q-item-section avatar><q-icon name="category" /></q-item-section>
              <q-item-section>
                <q-item-label>Categorías</q-item-label>
                <q-item-label caption>Configurar categorías de tickets</q-item-label>
              </q-item-section>
              <q-item-section side><q-icon name="chevron_right" /></q-item-section>
            </q-item>
            <q-item clickable v-ripple to="/settings/sla">
              <q-item-section avatar><q-icon name="timer" /></q-item-section>
              <q-item-section>
                <q-item-label>Políticas SLA</q-item-label>
                <q-item-label caption>Configurar tiempos de respuesta y resolución</q-item-label>
              </q-item-section>
              <q-item-section side><q-icon name="chevron_right" /></q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<style scoped>
.branding-upload-area {
  width: 100%;
  height: 140px;
  border: 2px dashed #e0e0e0;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  transition: border-color 0.2s, background 0.2s;
  background: #fafafa;
}
.branding-upload-area:hover {
  border-color: #1976d2;
  background: #f0f7ff;
}
.branding-upload-area--small {
  height: 100px;
}
.branding-preview-img {
  max-height: 100px;
  max-width: 90%;
  object-fit: contain;
  border-radius: 8px;
}
.branding-preview-favicon {
  max-height: 48px;
  max-width: 48px;
  object-fit: contain;
}
.color-swatch {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 2px solid #e0e0e0;
  flex-shrink: 0;
  transition: transform 0.15s;
}
.color-swatch:hover {
  transform: scale(1.1);
}
</style>
