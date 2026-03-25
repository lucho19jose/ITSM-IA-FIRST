<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Notify, useQuasar } from 'quasar'
import {
  getTicketFormFields,
  bulkUpdateFormFields,
  createCustomField,
  deleteCustomField,
} from '@/api/ticket-form'
import type { TicketFormField } from '@/types'

const { t } = useI18n()
const $q = useQuasar()

const loading = ref(true)
const saving = ref(false)
const fields = ref<TicketFormField[]>([])
const hasChanges = ref(false)

// Dialog state
const dialogOpen = ref(false)
const dialogMode = ref<'create' | 'edit'>('create')
const dialogLoading = ref(false)
const dialogField = ref<Partial<TicketFormField>>({})

const fieldTypeOptions = [
  { label: 'Texto', value: 'text' },
  { label: 'Texto largo', value: 'textarea' },
  { label: 'Editor enriquecido', value: 'rich_text' },
  { label: 'Selección', value: 'select' },
  { label: 'Número', value: 'number' },
  { label: 'Fecha', value: 'date' },
  { label: 'Casilla', value: 'checkbox' },
  { label: 'Email', value: 'email' },
  { label: 'Teléfono', value: 'phone' },
  { label: 'URL', value: 'url' },
  { label: 'Etiquetas', value: 'tags' },
  { label: 'Archivo', value: 'file' },
]

const sectionOptions = [
  { label: 'Principal', value: 'main' },
  { label: 'Detalles', value: 'details' },
]

const roleOptions = [
  { label: 'Administrador', value: 'admin' },
  { label: 'Agente', value: 'agent' },
  { label: 'Usuario final', value: 'end_user' },
]

const fieldTypeColorMap: Record<string, string> = {
  text: 'blue',
  textarea: 'blue-7',
  rich_text: 'indigo',
  select: 'purple',
  number: 'teal',
  date: 'orange',
  checkbox: 'green',
  email: 'cyan',
  phone: 'deep-orange',
  url: 'light-blue',
  tags: 'pink',
  file: 'brown',
}

const sectionColorMap: Record<string, string> = {
  main: 'primary',
  details: 'secondary',
}

// Options string for editing select field options
const optionsText = ref('')

const sortedFields = computed(() =>
  [...fields.value].sort((a, b) => {
    // Group by section first, then sort_order
    if (a.section !== b.section) {
      return a.section === 'main' ? -1 : 1
    }
    return a.sort_order - b.sort_order
  })
)

const mainFields = computed(() =>
  fields.value
    .filter(f => f.section === 'main')
    .sort((a, b) => a.sort_order - b.sort_order)
)

const detailFields = computed(() =>
  fields.value
    .filter(f => f.section === 'details')
    .sort((a, b) => a.sort_order - b.sort_order)
)

onMounted(async () => {
  try {
    const res = await getTicketFormFields()
    fields.value = res.data
  } finally {
    loading.value = false
  }
})

function moveUp(field: TicketFormField) {
  const sectionFields = fields.value
    .filter(f => f.section === field.section)
    .sort((a, b) => a.sort_order - b.sort_order)
  const idx = sectionFields.findIndex(f => f.id === field.id)
  if (idx <= 0) return
  const prev = sectionFields[idx - 1]
  const tempOrder = field.sort_order
  field.sort_order = prev.sort_order
  prev.sort_order = tempOrder
  hasChanges.value = true
}

function moveDown(field: TicketFormField) {
  const sectionFields = fields.value
    .filter(f => f.section === field.section)
    .sort((a, b) => a.sort_order - b.sort_order)
  const idx = sectionFields.findIndex(f => f.id === field.id)
  if (idx >= sectionFields.length - 1) return
  const next = sectionFields[idx + 1]
  const tempOrder = field.sort_order
  field.sort_order = next.sort_order
  next.sort_order = tempOrder
  hasChanges.value = true
}

function toggleVisible(field: TicketFormField) {
  field.is_visible = !field.is_visible
  hasChanges.value = true
}

function toggleRequired(field: TicketFormField) {
  field.is_required = !field.is_required
  hasChanges.value = true
}

function openCreateDialog() {
  dialogMode.value = 'create'
  dialogField.value = {
    field_key: '',
    label: '',
    field_type: 'text',
    section: 'main',
    is_visible: true,
    is_required: false,
    options: null,
    default_value: null,
    placeholder: null,
    help_text: null,
    role_visibility: null,
  }
  optionsText.value = ''
  dialogOpen.value = true
}

function openEditDialog(field: TicketFormField) {
  dialogMode.value = 'edit'
  dialogField.value = { ...field }
  optionsText.value = field.options
    ? field.options.map(o => `${o.value}:${o.label}`).join('\n')
    : ''
  dialogOpen.value = true
}

function parseOptions(): { label: string; value: string }[] | null {
  if (!optionsText.value.trim()) return null
  return optionsText.value
    .split('\n')
    .filter(line => line.trim())
    .map(line => {
      const parts = line.split(':')
      if (parts.length >= 2) {
        return { value: parts[0].trim(), label: parts.slice(1).join(':').trim() }
      }
      return { value: line.trim(), label: line.trim() }
    })
}

async function saveDialog() {
  if (!dialogField.value.field_key || !dialogField.value.label) {
    Notify.create({ type: 'warning', message: 'Completa los campos obligatorios' })
    return
  }

  dialogLoading.value = true
  try {
    if (dialogField.value.field_type === 'select') {
      dialogField.value.options = parseOptions()
    }

    if (dialogMode.value === 'create') {
      const res = await createCustomField(dialogField.value)
      fields.value.push(res.data)
      Notify.create({ type: 'positive', message: t('ticketForm.fieldCreated') })
    } else {
      // For edit, update the local field and mark changes
      const idx = fields.value.findIndex(f => f.id === dialogField.value.id)
      if (idx >= 0) {
        fields.value[idx] = { ...fields.value[idx], ...dialogField.value } as TicketFormField
        hasChanges.value = true
      }
    }
    dialogOpen.value = false
  } catch {
    // Error handled by interceptor
  } finally {
    dialogLoading.value = false
  }
}

async function handleDelete(field: TicketFormField) {
  $q.dialog({
    title: 'Eliminar campo',
    message: t('ticketForm.confirmDelete'),
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await deleteCustomField(field.id)
      fields.value = fields.value.filter(f => f.id !== field.id)
      Notify.create({ type: 'positive', message: t('ticketForm.fieldDeleted') })
    } catch {
      // Error handled by interceptor
    }
  })
}

async function saveAllChanges() {
  saving.value = true
  try {
    const payload = fields.value.map(f => ({
      id: f.id,
      label: f.label,
      is_visible: f.is_visible,
      is_required: f.is_required,
      sort_order: f.sort_order,
      section: f.section,
      options: f.options,
      placeholder: f.placeholder,
      help_text: f.help_text,
      default_value: f.default_value,
      role_visibility: f.role_visibility,
    }))
    const res = await bulkUpdateFormFields(payload)
    fields.value = res.data
    hasChanges.value = false
    Notify.create({ type: 'positive', message: t('ticketForm.fieldSaved') })
  } catch {
    // Error handled by interceptor
  } finally {
    saving.value = false
  }
}

function getFieldTypeLabel(type: string): string {
  const opt = fieldTypeOptions.find(o => o.value === type)
  return opt ? opt.label : type
}

function getRoleLabels(roles: string[] | null): string {
  if (!roles || roles.length === 0) return 'Todos'
  return roles.map(r => {
    const opt = roleOptions.find(o => o.value === r)
    return opt ? opt.label : r
  }).join(', ')
}
</script>

<template>
  <q-page padding>
    <div style="max-width: 1200px; margin: 0 auto;">
      <!-- Header -->
      <div class="row items-center justify-between q-mb-lg">
        <div class="row items-center">
          <q-btn flat round icon="arrow_back" to="/settings" class="q-mr-sm" />
          <div>
            <div class="text-h5 text-weight-bold">{{ t('ticketForm.configTitle') }}</div>
            <div class="text-caption text-grey">
              Configura los campos que aparecen al crear un ticket
            </div>
          </div>
        </div>
        <div class="row q-gutter-sm">
          <q-btn
            outline
            color="primary"
            icon="add"
            :label="t('ticketForm.addCustomField')"
            @click="openCreateDialog"
          />
          <q-btn
            color="primary"
            icon="save"
            :label="t('ticketForm.saveChanges')"
            :loading="saving"
            :disable="!hasChanges"
            @click="saveAllChanges"
          />
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="q-pa-xl text-center">
        <q-spinner-dots size="40px" color="primary" />
        <div class="text-grey q-mt-sm">{{ t('common.loading') }}</div>
      </div>

      <template v-else>
        <!-- Main Section Fields -->
        <q-card flat class="shadow-1 q-mb-lg">
          <q-card-section class="bg-blue-1">
            <div class="row items-center">
              <q-icon name="view_stream" color="primary" size="24px" class="q-mr-sm" />
              <div class="text-subtitle1 text-weight-bold text-primary">
                Sección Principal
              </div>
              <q-badge color="primary" class="q-ml-sm">{{ mainFields.length }}</q-badge>
            </div>
            <div class="text-caption text-grey-7">
              Campos que aparecen en la columna izquierda del formulario (asunto, descripción, etc.)
            </div>
          </q-card-section>
          <q-separator />
          <q-list separator>
            <q-item v-for="field in mainFields" :key="field.id" class="field-row">
              <!-- Reorder buttons -->
              <q-item-section side class="reorder-buttons">
                <div class="column">
                  <q-btn flat dense round icon="keyboard_arrow_up" size="xs" @click="moveUp(field)" />
                  <q-btn flat dense round icon="keyboard_arrow_down" size="xs" @click="moveDown(field)" />
                </div>
              </q-item-section>

              <!-- System badge / icon -->
              <q-item-section side>
                <q-icon
                  :name="field.is_system ? 'lock' : 'extension'"
                  :color="field.is_system ? 'grey-5' : 'purple-4'"
                  size="20px"
                >
                  <q-tooltip>{{ field.is_system ? t('ticketForm.system') : t('ticketForm.custom') }}</q-tooltip>
                </q-icon>
              </q-item-section>

              <!-- Field info -->
              <q-item-section>
                <q-item-label class="text-weight-medium">
                  {{ field.label }}
                  <q-badge
                    :color="fieldTypeColorMap[field.field_type] || 'grey'"
                    class="q-ml-sm"
                    outline
                  >
                    {{ getFieldTypeLabel(field.field_type) }}
                  </q-badge>
                </q-item-label>
                <q-item-label caption>
                  {{ field.field_key }}
                  <span v-if="field.help_text" class="q-ml-sm text-grey-6">
                    &mdash; {{ field.help_text }}
                  </span>
                </q-item-label>
              </q-item-section>

              <!-- Role visibility -->
              <q-item-section side class="role-section gt-sm">
                <q-item-label caption>{{ getRoleLabels(field.role_visibility) }}</q-item-label>
              </q-item-section>

              <!-- Visible toggle -->
              <q-item-section side>
                <div class="column items-center">
                  <q-toggle
                    :model-value="field.is_visible"
                    color="green"
                    dense
                    @update:model-value="toggleVisible(field)"
                  />
                  <span class="text-caption text-grey">Visible</span>
                </div>
              </q-item-section>

              <!-- Required toggle -->
              <q-item-section side>
                <div class="column items-center">
                  <q-toggle
                    :model-value="field.is_required"
                    color="orange"
                    dense
                    @update:model-value="toggleRequired(field)"
                  />
                  <span class="text-caption text-grey">Req.</span>
                </div>
              </q-item-section>

              <!-- Actions -->
              <q-item-section side>
                <div class="row no-wrap">
                  <q-btn
                    flat
                    dense
                    round
                    icon="edit"
                    size="sm"
                    color="grey-7"
                    @click="openEditDialog(field)"
                  >
                    <q-tooltip>{{ t('common.edit') }}</q-tooltip>
                  </q-btn>
                  <q-btn
                    v-if="!field.is_system"
                    flat
                    dense
                    round
                    icon="delete"
                    size="sm"
                    color="red-5"
                    @click="handleDelete(field)"
                  >
                    <q-tooltip>{{ t('common.delete') }}</q-tooltip>
                  </q-btn>
                </div>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card>

        <!-- Details Section Fields -->
        <q-card flat class="shadow-1 q-mb-lg">
          <q-card-section class="bg-orange-1">
            <div class="row items-center">
              <q-icon name="tune" color="orange-8" size="24px" class="q-mr-sm" />
              <div class="text-subtitle1 text-weight-bold text-orange-9">
                Sección Detalles
              </div>
              <q-badge color="orange" class="q-ml-sm">{{ detailFields.length }}</q-badge>
            </div>
            <div class="text-caption text-grey-7">
              Campos que aparecen en el panel lateral derecho (tipo, prioridad, categoría, etc.)
            </div>
          </q-card-section>
          <q-separator />
          <q-list separator>
            <q-item v-for="field in detailFields" :key="field.id" class="field-row">
              <!-- Reorder buttons -->
              <q-item-section side class="reorder-buttons">
                <div class="column">
                  <q-btn flat dense round icon="keyboard_arrow_up" size="xs" @click="moveUp(field)" />
                  <q-btn flat dense round icon="keyboard_arrow_down" size="xs" @click="moveDown(field)" />
                </div>
              </q-item-section>

              <!-- System badge / icon -->
              <q-item-section side>
                <q-icon
                  :name="field.is_system ? 'lock' : 'extension'"
                  :color="field.is_system ? 'grey-5' : 'purple-4'"
                  size="20px"
                >
                  <q-tooltip>{{ field.is_system ? t('ticketForm.system') : t('ticketForm.custom') }}</q-tooltip>
                </q-icon>
              </q-item-section>

              <!-- Field info -->
              <q-item-section>
                <q-item-label class="text-weight-medium">
                  {{ field.label }}
                  <q-badge
                    :color="fieldTypeColorMap[field.field_type] || 'grey'"
                    class="q-ml-sm"
                    outline
                  >
                    {{ getFieldTypeLabel(field.field_type) }}
                  </q-badge>
                </q-item-label>
                <q-item-label caption>
                  {{ field.field_key }}
                  <span v-if="field.help_text" class="q-ml-sm text-grey-6">
                    &mdash; {{ field.help_text }}
                  </span>
                </q-item-label>
              </q-item-section>

              <!-- Role visibility -->
              <q-item-section side class="role-section gt-sm">
                <q-item-label caption>{{ getRoleLabels(field.role_visibility) }}</q-item-label>
              </q-item-section>

              <!-- Visible toggle -->
              <q-item-section side>
                <div class="column items-center">
                  <q-toggle
                    :model-value="field.is_visible"
                    color="green"
                    dense
                    @update:model-value="toggleVisible(field)"
                  />
                  <span class="text-caption text-grey">Visible</span>
                </div>
              </q-item-section>

              <!-- Required toggle -->
              <q-item-section side>
                <div class="column items-center">
                  <q-toggle
                    :model-value="field.is_required"
                    color="orange"
                    dense
                    @update:model-value="toggleRequired(field)"
                  />
                  <span class="text-caption text-grey">Req.</span>
                </div>
              </q-item-section>

              <!-- Actions -->
              <q-item-section side>
                <div class="row no-wrap">
                  <q-btn
                    flat
                    dense
                    round
                    icon="edit"
                    size="sm"
                    color="grey-7"
                    @click="openEditDialog(field)"
                  >
                    <q-tooltip>{{ t('common.edit') }}</q-tooltip>
                  </q-btn>
                  <q-btn
                    v-if="!field.is_system"
                    flat
                    dense
                    round
                    icon="delete"
                    size="sm"
                    color="red-5"
                    @click="handleDelete(field)"
                  >
                    <q-tooltip>{{ t('common.delete') }}</q-tooltip>
                  </q-btn>
                </div>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card>

        <!-- Hint -->
        <div class="text-caption text-grey-6 text-center q-mb-md">
          <q-icon name="info" size="14px" class="q-mr-xs" />
          {{ t('ticketForm.dragHint') }}. Los campos de sistema no pueden eliminarse.
        </div>
      </template>
    </div>

    <!-- Create/Edit Field Dialog -->
    <q-dialog v-model="dialogOpen" persistent>
      <q-card style="width: 600px; max-width: 90vw;">
        <q-card-section class="row items-center">
          <q-icon
            :name="dialogMode === 'create' ? 'add_circle' : 'edit'"
            color="primary"
            size="24px"
            class="q-mr-sm"
          />
          <div class="text-h6">
            {{ dialogMode === 'create' ? t('ticketForm.createField') : t('ticketForm.editField') }}
          </div>
          <q-space />
          <q-btn flat round dense icon="close" @click="dialogOpen = false" />
        </q-card-section>

        <q-separator />

        <q-card-section class="q-gutter-md">
          <!-- Field key (only for create) -->
          <q-input
            v-if="dialogMode === 'create'"
            v-model="dialogField.field_key"
            :label="t('ticketForm.fieldKey') + ' *'"
            hint="Identificador único (e.g. custom_department). Solo letras, números y guión bajo."
            outlined
            dense
            :rules="[
              (v: string) => !!v || 'Requerido',
              (v: string) => /^[a-z][a-z0-9_]*$/.test(v) || 'Solo minúsculas, números y guión bajo. Debe comenzar con letra.'
            ]"
            lazy-rules
          />

          <!-- Label -->
          <q-input
            v-model="dialogField.label"
            :label="t('ticketForm.fieldLabel') + ' *'"
            outlined
            dense
            :rules="[(v: string) => !!v || 'Requerido']"
            lazy-rules
          />

          <div class="row q-col-gutter-md">
            <!-- Field type -->
            <div class="col-6">
              <q-select
                v-model="dialogField.field_type"
                :options="fieldTypeOptions"
                :label="t('ticketForm.fieldType')"
                outlined
                dense
                emit-value
                map-options
                :disable="dialogMode === 'edit' && dialogField.is_system"
              />
            </div>

            <!-- Section -->
            <div class="col-6">
              <q-select
                v-model="dialogField.section"
                :options="sectionOptions"
                :label="t('ticketForm.section')"
                outlined
                dense
                emit-value
                map-options
              />
            </div>
          </div>

          <!-- Options (only for select type) -->
          <q-input
            v-if="dialogField.field_type === 'select'"
            v-model="optionsText"
            :label="t('ticketForm.options')"
            hint="Una opción por línea en formato valor:etiqueta (e.g. dept_it:Departamento TI)"
            outlined
            dense
            type="textarea"
            autogrow
          />

          <!-- Placeholder -->
          <q-input
            v-model="dialogField.placeholder"
            :label="t('ticketForm.placeholder')"
            outlined
            dense
          />

          <!-- Help text -->
          <q-input
            v-model="dialogField.help_text"
            :label="t('ticketForm.helpText')"
            outlined
            dense
          />

          <!-- Default value -->
          <q-input
            v-model="dialogField.default_value"
            :label="t('ticketForm.defaultValue')"
            outlined
            dense
          />

          <!-- Role visibility -->
          <q-select
            v-model="dialogField.role_visibility"
            :options="roleOptions"
            :label="t('ticketForm.roleVisibility')"
            hint="Dejar vacío para que sea visible a todos los roles"
            outlined
            dense
            multiple
            use-chips
            emit-value
            map-options
            clearable
          />
        </q-card-section>

        <q-separator />

        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat :label="t('common.cancel')" @click="dialogOpen = false" />
          <q-btn
            color="primary"
            :label="dialogMode === 'create' ? t('common.create') : t('common.save')"
            :loading="dialogLoading"
            @click="saveDialog"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<style scoped>
.field-row {
  transition: background-color 0.15s ease;
}
.field-row:hover {
  background-color: #f5f5f5;
}
.reorder-buttons {
  min-width: 32px;
}
.role-section {
  min-width: 120px;
}
</style>
