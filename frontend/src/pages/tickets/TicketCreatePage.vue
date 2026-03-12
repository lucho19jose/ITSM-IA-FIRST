<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Notify, type QForm } from 'quasar'
import { createTicket, uploadTicketAttachments, getTicketTags } from '@/api/tickets'
import { getCategories } from '@/api/categories'
import { getAgents } from '@/api/users'
import { getTicketFormFields } from '@/api/ticket-form'
import { useAuthStore } from '@/stores/auth'
import type { Category, TicketFormField, User } from '@/types'

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
    <div class="ticket-create-page" style="max-width: 1200px; margin: 0 auto;">
      <!-- Header -->
      <div class="row items-center q-mb-lg">
        <q-btn flat round icon="arrow_back" to="/tickets" class="q-mr-sm" />
        <div>
          <div class="text-h5 text-weight-bold">{{ t('tickets.create') }}</div>
          <div class="text-caption text-grey">Complete los campos para crear un nuevo ticket</div>
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loadingData" class="row q-col-gutter-lg">
        <div class="col-12 col-md-8">
          <q-card flat class="shadow-2">
            <q-card-section>
              <q-skeleton type="text" width="60%" class="q-mb-md" />
              <q-skeleton height="40px" class="q-mb-lg" />
              <q-skeleton height="200px" />
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-md-4">
          <q-card flat class="bg-grey-1">
            <q-card-section>
              <q-skeleton type="text" width="40%" class="q-mb-md" />
              <q-skeleton height="40px" class="q-mb-sm" />
              <q-skeleton height="40px" class="q-mb-sm" />
              <q-skeleton height="40px" />
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Form -->
      <q-form v-else ref="formRef" @submit.prevent="onSubmit" greedy>
        <div class="row q-col-gutter-lg">
          <!-- Left Column: Main Fields -->
          <div class="col-12 col-md-8">
            <q-card flat class="shadow-2">
              <q-card-section class="q-pa-lg">
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
              </q-card-section>
            </q-card>
          </div>

          <!-- Right Column: Details Sidebar -->
          <div class="col-12 col-md-4">
            <q-card flat class="bg-grey-1 details-sidebar">
              <q-card-section>
                <div class="text-subtitle1 text-weight-bold q-mb-md">
                  <q-icon name="tune" class="q-mr-xs" />
                  Detalles
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
              </q-card-section>
            </q-card>
          </div>
        </div>

        <!-- Bottom Actions -->
        <div class="row justify-end q-mt-lg q-gutter-sm" style="max-width: 1200px;">
          <q-btn
            flat
            :label="t('common.cancel')"
            to="/tickets"
            class="q-px-lg"
          />
          <q-btn
            type="submit"
            color="primary"
            icon="send"
            label="Crear Ticket"
            :loading="loading"
            class="q-px-lg"
          />
        </div>
      </q-form>
    </div>
  </q-page>
</template>

<style scoped>
.ticket-create-page {
  padding-bottom: 32px;
}

.ticket-editor {
  border: 1px solid rgba(0, 0, 0, 0.24);
  border-radius: 4px;
}

.ticket-editor:focus-within {
  border-color: var(--q-primary);
  border-width: 2px;
}

.details-sidebar {
  border-radius: 8px;
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

/* Responsive: on small screens, unset sticky */
@media (max-width: 1023px) {
  .details-sidebar {
    position: static;
  }
}
</style>
