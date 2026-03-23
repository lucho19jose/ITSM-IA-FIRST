<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Notify, type QForm } from 'quasar'
import { createTicket, uploadTicketAttachments, getTicketTags } from '@/api/tickets'
import { getCategories } from '@/api/categories'
import { getAgents } from '@/api/users'
import { getTicketFormFields } from '@/api/ticket-form'
import { getAssets } from '@/api/assets'
import { useAuthStore } from '@/stores/auth'
import type { Asset, Category, TicketFormField, User } from '@/types'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(false)
const loadingData = ref(true)
const formRef = ref<QForm | null>(null)
const formFields = ref<TicketFormField[]>([])
const categories = ref<Category[]>([])
const agents = ref<User[]>([])
const attachedFiles = ref<File[]>([])
const existingTags = ref<string[]>([])
const filteredTags = ref<string[]>([])
const assetOptions = ref<Asset[]>([])
const selectedAssetId = ref<number | null>(null)

async function searchAssets(val: string, update: (fn: () => void) => void) {
  if (val.length < 2) { update(() => { assetOptions.value = [] }); return }
  try {
    const res = await getAssets({ search: val, per_page: 10 })
    update(() => { assetOptions.value = res.data })
  } catch { update(() => { assetOptions.value = [] }) }
}

// Form data - keyed by field_key
const form = ref<Record<string, any>>({})

const visibleMainFields = computed(() =>
  formFields.value
    .filter(f => f.is_visible && f.section === 'main' && isFieldVisibleForRole(f))
    .sort((a, b) => a.sort_order - b.sort_order)
)

const visibleDetailFields = computed(() =>
  formFields.value
    .filter(f => f.is_visible && f.section === 'details' && isFieldVisibleForRole(f))
    .sort((a, b) => a.sort_order - b.sort_order)
)

function isFieldVisibleForRole(field: TicketFormField): boolean {
  if (!field.role_visibility || field.role_visibility.length === 0) return true
  return field.role_visibility.includes(auth.user?.role || '')
}

// Priority colors for visual indicators
const priorityColors: Record<string, string> = {
  low: 'green',
  medium: 'blue',
  high: 'orange',
  urgent: 'red',
}

// Type icons for visual indicators
const typeIcons: Record<string, string> = {
  incident: 'warning',
  request: 'help_outline',
  problem: 'error_outline',
  change: 'swap_horiz',
}

// Source icons
const sourceIcons: Record<string, string> = {
  portal: 'language',
  email: 'email',
  chatbot: 'smart_toy',
  catalog: 'storefront',
  api: 'api',
  phone: 'phone',
}

onMounted(async () => {
  try {
    const [fieldsRes, catsRes] = await Promise.all([
      getTicketFormFields(),
      getCategories(),
    ])
    formFields.value = fieldsRes.data
    categories.value = catsRes.data

    // Load agents if admin/agent
    if (auth.isAdmin || auth.isAgent) {
      const agentsRes = await getAgents()
      agents.value = agentsRes.data
    }

    // Load existing tags for suggestions
    try {
      const tagsRes = await getTicketTags()
      existingTags.value = tagsRes.data
    } catch { /* ignore */ }

    // Initialize form with defaults
    formFields.value.forEach(f => {
      if (f.default_value !== null && f.default_value !== '') {
        form.value[f.field_key] = f.default_value
      } else if (f.field_type === 'tags') {
        form.value[f.field_key] = []
      } else if (f.field_type === 'checkbox') {
        form.value[f.field_key] = false
      } else {
        form.value[f.field_key] = null
      }
    })
  } finally {
    loadingData.value = false
  }
})

// Build options for select fields dynamically
function getFieldOptions(field: TicketFormField) {
  if (field.field_key === 'category_id') {
    return categories.value.map(c => ({ label: c.name, value: c.id }))
  }
  if (field.field_key === 'assigned_to') {
    return agents.value.map(a => ({ label: a.name, value: a.id }))
  }
  if (field.field_key === 'type') {
    return [
      { label: t('tickets.types.incident'), value: 'incident' },
      { label: t('tickets.types.request'), value: 'request' },
      { label: t('tickets.types.problem'), value: 'problem' },
      { label: t('tickets.types.change'), value: 'change' },
    ]
  }
  if (field.field_key === 'priority') {
    return [
      { label: t('tickets.priorities.low'), value: 'low' },
      { label: t('tickets.priorities.medium'), value: 'medium' },
      { label: t('tickets.priorities.high'), value: 'high' },
      { label: t('tickets.priorities.urgent'), value: 'urgent' },
    ]
  }
  if (field.field_key === 'source') {
    return [
      { label: 'Portal', value: 'portal' },
      { label: 'Email', value: 'email' },
      { label: 'Chatbot', value: 'chatbot' },
      { label: 'Catálogo', value: 'catalog' },
      { label: 'API', value: 'api' },
      { label: 'Teléfono', value: 'phone' },
    ]
  }
  return field.options || []
}

// Validation rules
function getFieldRules(field: TicketFormField) {
  const rules: ((val: any) => boolean | string)[] = []
  if (field.is_required) {
    rules.push((val: any) => {
      if (field.field_type === 'checkbox') return true
      if (field.field_type === 'tags') return (Array.isArray(val) && val.length > 0) || 'Campo requerido'
      return (val !== null && val !== undefined && val !== '') || 'Campo requerido'
    })
  }
  if (field.field_type === 'email') {
    rules.push((val: any) => !val || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) || 'Email inválido')
  }
  if (field.field_type === 'url') {
    rules.push((val: any) => !val || /^https?:\/\/.+/.test(val) || 'URL inválida')
  }
  if (field.field_type === 'phone') {
    rules.push((val: any) => !val || /^[+\d\s()-]{6,20}$/.test(val) || 'Teléfono inválido')
  }
  return rules
}

function filterTags(val: string, update: (fn: () => void) => void) {
  update(() => {
    const needle = val.toLowerCase()
    filteredTags.value = needle
      ? existingTags.value.filter(t => t.toLowerCase().includes(needle))
      : existingTags.value.slice(0, 20)
  })
}

async function onSubmit() {
  const valid = await formRef.value?.validate()
  if (!valid) return

  loading.value = true
  try {
    // Build payload - separate system fields from custom_fields
    const systemKeys = formFields.value.filter(f => f.is_system).map(f => f.field_key)
    const payload: Record<string, any> = {}
    const customFields: Record<string, any> = {}

    for (const [key, value] of Object.entries(form.value)) {
      if (value === null || value === '' || value === undefined) continue
      if (key === 'attachments') continue // handled separately
      if (systemKeys.includes(key)) {
        payload[key] = value
      } else {
        customFields[key] = value
      }
    }

    if (Object.keys(customFields).length > 0) {
      payload.custom_fields = customFields
    }

    if (selectedAssetId.value) {
      payload.asset_id = selectedAssetId.value
    }

    const res = await createTicket(payload)

    // Upload attachments if any
    if (attachedFiles.value.length > 0) {
      try {
        await uploadTicketAttachments(res.data.id, attachedFiles.value)
      } catch {
        Notify.create({ type: 'warning', message: 'Ticket creado pero algunos archivos no se subieron' })
      }
    }

    Notify.create({ type: 'positive', message: 'Ticket creado exitosamente' })
    router.push(`/tickets/${res.data.id}`)
  } catch {
    // Error handled by interceptor
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <q-page padding>
    <div class="ticket-create-page">
      <!-- Breadcrumb -->
      <div class="breadcrumb q-mb-sm">
        <router-link to="/tickets" class="breadcrumb-link">Tickets</router-link>
        <q-icon name="chevron_right" size="16px" color="grey-6" class="q-mx-xs" />
        <span class="text-grey-8">Informar sobre un problema</span>
      </div>

      <!-- Header -->
      <div class="text-h5 text-weight-bold q-mb-lg" style="color: #333;">Informar sobre un problema</div>

      <!-- Loading skeleton -->
      <div v-if="loadingData" class="row q-col-gutter-xl">
        <div class="col-12 col-md-8">
          <div class="form-panel">
            <q-skeleton type="text" width="30%" class="q-mb-lg" />
            <q-skeleton height="44px" class="q-mb-xl" />
            <q-skeleton type="text" width="20%" class="q-mb-sm" />
            <q-skeleton height="44px" class="q-mb-xl" />
            <q-skeleton type="text" width="25%" class="q-mb-sm" />
            <q-skeleton height="180px" />
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="help-panel">
            <q-skeleton type="rect" height="120px" class="q-mb-md" />
            <q-skeleton type="text" width="80%" class="q-mb-sm" />
            <q-skeleton type="text" width="90%" />
          </div>
        </div>
      </div>

      <!-- Form -->
      <q-form v-else ref="formRef" @submit.prevent="onSubmit" greedy>
        <div class="row q-col-gutter-xl">
          <!-- Left Column: Main Fields -->
          <div class="col-12 col-md-8">
            <!-- Requester info (read-only) -->
            <div class="form-panel q-mb-lg" v-if="auth.user">
              <div class="field-label">Solicitante</div>
              <div class="requester-display">
                <q-avatar size="32px" color="primary" text-color="white" font-size="14px" class="q-mr-sm">
                  {{ auth.user.name?.charAt(0).toUpperCase() || 'U' }}
                </q-avatar>
                <div>
                  <div class="text-body2 text-weight-medium">{{ auth.user.name }}</div>
                  <div class="text-caption text-grey-6">{{ auth.user.email }}</div>
                </div>
              </div>
            </div>

            <div class="form-panel">
                <template v-for="field in visibleMainFields" :key="field.id">
                  <!-- Title / Subject (special: larger styling) -->
                  <div v-if="field.field_key === 'title'" class="q-mb-md">
                    <q-input
                      v-model="form[field.field_key]"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :placeholder="field.placeholder || 'Escribe un asunto breve y descriptivo...'"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      input-class="text-h6"
                      lazy-rules
                    />
                  </div>

                  <!-- Rich text / Description -->
                  <div v-else-if="field.field_type === 'rich_text'" class="q-mb-md">
                    <div class="text-subtitle2 text-grey-8 q-mb-xs">
                      {{ field.label }}{{ field.is_required ? ' *' : '' }}
                    </div>
                    <q-editor
                      v-model="form[field.field_key]"
                      :placeholder="field.placeholder || 'Describe tu problema o solicitud en detalle...'"
                      min-height="200px"
                      :toolbar="[
                        ['bold', 'italic', 'underline', 'strike'],
                        ['unordered', 'ordered'],
                        ['link'],
                        ['undo', 'redo'],
                        ['removeFormat'],
                      ]"
                      flat
                      class="ticket-editor"
                    />
                    <div v-if="field.help_text" class="text-caption text-grey q-mt-xs">{{ field.help_text }}</div>
                  </div>

                  <!-- Textarea -->
                  <div v-else-if="field.field_type === 'textarea'" class="q-mb-md">
                    <q-input
                      v-model="form[field.field_key]"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :placeholder="field.placeholder || undefined"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      type="textarea"
                      outlined
                      autogrow
                      lazy-rules
                    />
                  </div>

                  <!-- Tags -->
                  <div v-else-if="field.field_type === 'tags'" class="q-mb-md">
                    <q-select
                      v-model="form[field.field_key]"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :hint="field.help_text || 'Escribe y presiona Enter para agregar'"
                      :rules="getFieldRules(field)"
                      :options="filteredTags"
                      outlined
                      use-input
                      use-chips
                      multiple
                      new-value-mode="add-unique"
                      input-debounce="200"
                      lazy-rules
                      @filter="filterTags"
                    >
                      <template #prepend>
                        <q-icon name="sell" color="grey-6" />
                      </template>
                      <template #no-option>
                        <q-item>
                          <q-item-section class="text-grey">Escribe y presiona Enter para crear</q-item-section>
                        </q-item>
                      </template>
                    </q-select>
                  </div>

                  <!-- File upload -->
                  <div v-else-if="field.field_type === 'file'" class="q-mb-md">
                    <q-file
                      v-model="attachedFiles"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      outlined
                      multiple
                      append
                      counter
                      max-file-size="10485760"
                      accept=".png,.jpg,.jpeg,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"
                      :hint="field.help_text || 'PNG, JPG, PDF, DOCX hasta 10MB por archivo'"
                      @rejected="Notify.create({ type: 'warning', message: 'Archivo rechazado: excede 10MB o formato no válido' })"
                    >
                      <template #prepend>
                        <q-icon name="attach_file" color="grey-6" />
                      </template>
                      <template #file="{ file, index }">
                        <q-chip
                          removable
                          dense
                          color="primary"
                          text-color="white"
                          @remove="attachedFiles.splice(index, 1)"
                        >
                          <q-icon name="description" size="16px" class="q-mr-xs" />
                          {{ file.name }} ({{ (file.size / 1024).toFixed(0) }}KB)
                        </q-chip>
                      </template>
                    </q-file>
                  </div>

                  <!-- Select -->
                  <div v-else-if="field.field_type === 'select'" class="q-mb-md">
                    <q-select
                      v-model="form[field.field_key]"
                      :options="getFieldOptions(field)"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :placeholder="field.placeholder || undefined"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      emit-value
                      map-options
                      clearable
                      lazy-rules
                    />
                  </div>

                  <!-- Text / Email / Phone / URL -->
                  <div v-else-if="['text', 'email', 'phone', 'url'].includes(field.field_type)" class="q-mb-md">
                    <q-input
                      v-model="form[field.field_key]"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :type="field.field_type === 'email' ? 'email' : field.field_type === 'phone' ? 'tel' : field.field_type === 'url' ? 'url' : 'text'"
                      :placeholder="field.placeholder || undefined"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      lazy-rules
                    >
                      <template v-if="field.field_type === 'email'" #prepend>
                        <q-icon name="email" color="grey-6" />
                      </template>
                      <template v-else-if="field.field_type === 'phone'" #prepend>
                        <q-icon name="phone" color="grey-6" />
                      </template>
                      <template v-else-if="field.field_type === 'url'" #prepend>
                        <q-icon name="link" color="grey-6" />
                      </template>
                    </q-input>
                  </div>

                  <!-- Number -->
                  <div v-else-if="field.field_type === 'number'" class="q-mb-md">
                    <q-input
                      v-model.number="form[field.field_key]"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      type="number"
                      :placeholder="field.placeholder || undefined"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      lazy-rules
                    />
                  </div>

                  <!-- Date -->
                  <div v-else-if="field.field_type === 'date'" class="q-mb-md">
                    <q-input
                      v-model="form[field.field_key]"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      readonly
                      lazy-rules
                    >
                      <template #prepend>
                        <q-icon name="event" color="grey-6" class="cursor-pointer">
                          <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                            <q-date v-model="form[field.field_key]" mask="YYYY-MM-DD">
                              <div class="row items-center justify-end">
                                <q-btn v-close-popup label="Cerrar" color="primary" flat />
                              </div>
                            </q-date>
                          </q-popup-proxy>
                        </q-icon>
                      </template>
                    </q-input>
                  </div>

                  <!-- Checkbox -->
                  <div v-else-if="field.field_type === 'checkbox'" class="q-mb-md">
                    <q-checkbox
                      v-model="form[field.field_key]"
                      :label="field.label"
                    />
                    <div v-if="field.help_text" class="text-caption text-grey q-ml-lg">{{ field.help_text }}</div>
                  </div>
                </template>
              </div>
          </div>

          <!-- Right Column: Help + Details Sidebar -->
          <div class="col-12 col-md-4">
            <!-- Help suggestion card -->
            <div class="help-panel q-mb-lg">
              <div class="text-center q-mb-md">
                <q-icon name="search" size="48px" color="grey-4" />
              </div>
              <div class="text-subtitle2 text-weight-bold text-center q-mb-xs" style="color: #333;">
                ¿Desea resolver su problema rápidamente?
              </div>
              <div class="text-caption text-grey-7 text-center">
                Añada más información sobre el tema para ver los artículos relevantes aquí mismo.
              </div>
            </div>

            <!-- Details panel -->
            <div class="details-panel">
              <div class="text-subtitle2 text-weight-bold q-mb-md" style="color: #333;">
                <q-icon name="tune" size="18px" class="q-mr-xs" />
                Detalles
              </div>

                <!-- Asset selector (admin/agent only) -->
                <div v-if="auth.isAdmin || auth.isAgent" class="q-mb-md">
                  <q-select
                    v-model="selectedAssetId"
                    :label="t('assets.affectedAsset')"
                    outlined dense
                    use-input
                    emit-value
                    map-options
                    :options="assetOptions"
                    :option-value="(item: any) => item.id"
                    :option-label="(item: any) => `${item.asset_tag} - ${item.name}`"
                    @filter="searchAssets"
                    clearable
                    :placeholder="t('assets.selectAsset')"
                  >
                    <template v-slot:no-option>
                      <q-item>
                        <q-item-section class="text-grey">{{ t('common.noResults') }}</q-item-section>
                      </q-item>
                    </template>
                    <template v-slot:option="scope">
                      <q-item v-bind="scope.itemProps">
                        <q-item-section avatar>
                          <q-icon :name="scope.opt.asset_type?.icon || 'devices'" />
                        </q-item-section>
                        <q-item-section>
                          <q-item-label>{{ scope.opt.asset_tag }} - {{ scope.opt.name }}</q-item-label>
                          <q-item-label caption>{{ scope.opt.asset_type?.name }}</q-item-label>
                        </q-item-section>
                      </q-item>
                    </template>
                  </q-select>
                </div>

                <template v-for="field in visibleDetailFields" :key="field.id">
                  <!-- Priority (special: colored indicators) -->
                  <div v-if="field.field_key === 'priority'" class="q-mb-md">
                    <q-select
                      v-model="form[field.field_key]"
                      :options="getFieldOptions(field)"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      emit-value
                      map-options
                      dense
                      lazy-rules
                    >
                      <template #option="scope">
                        <q-item v-bind="scope.itemProps">
                          <q-item-section side>
                            <div
                              class="priority-dot"
                              :style="{ backgroundColor: `var(--q-${priorityColors[scope.opt.value] || 'grey'})` }"
                            />
                          </q-item-section>
                          <q-item-section>
                            <q-item-label>{{ scope.opt.label }}</q-item-label>
                          </q-item-section>
                        </q-item>
                      </template>
                      <template #selected-item="scope">
                        <div class="row items-center no-wrap">
                          <div
                            class="priority-dot q-mr-sm"
                            :style="{ backgroundColor: `var(--q-${priorityColors[scope.opt.value] || 'grey'})` }"
                          />
                          {{ scope.opt.label }}
                        </div>
                      </template>
                    </q-select>
                  </div>

                  <!-- Type (with icons) -->
                  <div v-else-if="field.field_key === 'type'" class="q-mb-md">
                    <q-select
                      v-model="form[field.field_key]"
                      :options="getFieldOptions(field)"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      emit-value
                      map-options
                      dense
                      lazy-rules
                    >
                      <template #option="scope">
                        <q-item v-bind="scope.itemProps">
                          <q-item-section side>
                            <q-icon :name="typeIcons[scope.opt.value] || 'help'" color="grey-7" size="20px" />
                          </q-item-section>
                          <q-item-section>
                            <q-item-label>{{ scope.opt.label }}</q-item-label>
                          </q-item-section>
                        </q-item>
                      </template>
                    </q-select>
                  </div>

                  <!-- Source (with icons) -->
                  <div v-else-if="field.field_key === 'source'" class="q-mb-md">
                    <q-select
                      v-model="form[field.field_key]"
                      :options="getFieldOptions(field)"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      emit-value
                      map-options
                      dense
                      clearable
                      lazy-rules
                    >
                      <template #option="scope">
                        <q-item v-bind="scope.itemProps">
                          <q-item-section side>
                            <q-icon :name="sourceIcons[scope.opt.value] || 'device_unknown'" color="grey-7" size="20px" />
                          </q-item-section>
                          <q-item-section>
                            <q-item-label>{{ scope.opt.label }}</q-item-label>
                          </q-item-section>
                        </q-item>
                      </template>
                    </q-select>
                  </div>

                  <!-- Select (general, including category_id, assigned_to) -->
                  <div v-else-if="field.field_type === 'select'" class="q-mb-md">
                    <q-select
                      v-model="form[field.field_key]"
                      :options="getFieldOptions(field)"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :placeholder="field.placeholder || undefined"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      emit-value
                      map-options
                      dense
                      clearable
                      lazy-rules
                    />
                  </div>

                  <!-- Date (e.g. due_date) -->
                  <div v-else-if="field.field_type === 'date'" class="q-mb-md">
                    <q-input
                      v-model="form[field.field_key]"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      dense
                      readonly
                      lazy-rules
                    >
                      <template #prepend>
                        <q-icon name="event" color="grey-6" class="cursor-pointer">
                          <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                            <q-date v-model="form[field.field_key]" mask="YYYY-MM-DD">
                              <div class="row items-center justify-end">
                                <q-btn v-close-popup label="Cerrar" color="primary" flat />
                              </div>
                            </q-date>
                          </q-popup-proxy>
                        </q-icon>
                      </template>
                    </q-input>
                  </div>

                  <!-- Text / Email / Phone / URL in details -->
                  <div v-else-if="['text', 'email', 'phone', 'url'].includes(field.field_type)" class="q-mb-md">
                    <q-input
                      v-model="form[field.field_key]"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :type="field.field_type === 'email' ? 'email' : field.field_type === 'phone' ? 'tel' : field.field_type === 'url' ? 'url' : 'text'"
                      :placeholder="field.placeholder || undefined"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      dense
                      lazy-rules
                    />
                  </div>

                  <!-- Number in details -->
                  <div v-else-if="field.field_type === 'number'" class="q-mb-md">
                    <q-input
                      v-model.number="form[field.field_key]"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      type="number"
                      :placeholder="field.placeholder || undefined"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      outlined
                      dense
                      lazy-rules
                    />
                  </div>

                  <!-- Checkbox in details -->
                  <div v-else-if="field.field_type === 'checkbox'" class="q-mb-md">
                    <q-checkbox
                      v-model="form[field.field_key]"
                      :label="field.label"
                      dense
                    />
                    <div v-if="field.help_text" class="text-caption text-grey q-ml-lg">{{ field.help_text }}</div>
                  </div>

                  <!-- Tags in details -->
                  <div v-else-if="field.field_type === 'tags'" class="q-mb-md">
                    <q-select
                      v-model="form[field.field_key]"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :hint="field.help_text || 'Escribe y presiona Enter para agregar'"
                      :rules="getFieldRules(field)"
                      :options="filteredTags"
                      outlined
                      use-input
                      use-chips
                      multiple
                      new-value-mode="add-unique"
                      input-debounce="200"
                      dense
                      lazy-rules
                      @filter="filterTags"
                    >
                      <template #no-option>
                        <q-item dense>
                          <q-item-section class="text-grey text-caption">Escribe y presiona Enter</q-item-section>
                        </q-item>
                      </template>
                    </q-select>
                  </div>

                  <!-- Textarea in details -->
                  <div v-else-if="field.field_type === 'textarea'" class="q-mb-md">
                    <q-input
                      v-model="form[field.field_key]"
                      :label="field.label + (field.is_required ? ' *' : '')"
                      :placeholder="field.placeholder || undefined"
                      :hint="field.help_text || undefined"
                      :rules="getFieldRules(field)"
                      type="textarea"
                      outlined
                      autogrow
                      dense
                      lazy-rules
                    />
                  </div>
                </template>
            </div>
          </div>
        </div>

        <!-- Bottom Actions -->
        <div class="actions-bar">
          <q-btn
            flat
            no-caps
            :label="t('common.cancel')"
            to="/tickets"
            class="q-px-xl"
            color="grey-8"
            style="border: 1px solid #ddd;"
          />
          <q-btn
            type="submit"
            color="primary"
            no-caps
            unelevated
            label="Enviar"
            :loading="loading"
            class="q-px-xl"
            style="min-width: 120px;"
          />
        </div>
      </q-form>
    </div>
  </q-page>
</template>

<style scoped>
.ticket-create-page {
  max-width: 1100px;
  margin: 0 auto;
  padding-bottom: 32px;
}

/* Breadcrumb */
.breadcrumb {
  display: flex;
  align-items: center;
  font-size: 13px;
}

.breadcrumb-link {
  color: var(--q-primary);
  text-decoration: none;
}

.breadcrumb-link:hover {
  text-decoration: underline;
}

/* Form panels */
.form-panel {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 8px;
  padding: 28px 32px;
}

.field-label {
  font-size: 13px;
  font-weight: 600;
  color: #555;
  margin-bottom: 8px;
}

.requester-display {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  background: #f8f9fa;
  border: 1px solid #e8ecf0;
  border-radius: 6px;
}

/* Editor */
.ticket-editor {
  border: 1px solid #d0d6dd;
  border-radius: 6px;
}

.ticket-editor:focus-within {
  border-color: var(--q-primary);
  border-width: 2px;
}

/* Help panel */
.help-panel {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 8px;
  padding: 28px 24px;
}

/* Details panel */
.details-panel {
  background: #f8f9fb;
  border: 1px solid #e8ecf0;
  border-radius: 8px;
  padding: 20px 24px;
  position: sticky;
  top: 76px;
}

.priority-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

/* Actions bar */
.actions-bar {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #e8ecf0;
}

/* Dark mode overrides */
.body--dark .form-panel,
.body--dark .help-panel {
  background: #1e1e2e;
  border-color: #3a3a4a;
}

.body--dark .details-panel {
  background: #252535;
  border-color: #3a3a4a;
}

.body--dark .requester-display {
  background: #252535;
  border-color: #3a3a4a;
}

.body--dark .field-label {
  color: #b0b0b8;
}

.body--dark .actions-bar {
  border-color: #3a3a4a;
}

/* Responsive */
@media (max-width: 1023px) {
  .details-panel {
    position: static;
  }
  .form-panel {
    padding: 20px 16px;
  }
}
</style>
