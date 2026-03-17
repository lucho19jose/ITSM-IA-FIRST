<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePortalStore } from '@/stores/portal'
import { createTicket, uploadTicketAttachments } from '@/api/tickets'
import { getCategories } from '@/api/categories'
import { Notify } from 'quasar'
import type { Category, Ticket } from '@/types'

const route = useRoute()
const router = useRouter()
const portal = usePortalStore()
const tenantSlug = route.params.tenantSlug as string

const categories = ref<Category[]>([])
const loading = ref(false)
const attachedFiles = ref<File[]>([])

const form = ref({
  title: '',
  description: '',
  type: 'incident' as Ticket['type'],
  priority: 'medium' as Ticket['priority'],
  category_id: null as number | null,
})

const typeOptions = [
  { label: 'Incidente', value: 'incident', icon: 'error' },
  { label: 'Solicitud', value: 'request', icon: 'help' },
  { label: 'Problema', value: 'problem', icon: 'warning' },
  { label: 'Cambio', value: 'change', icon: 'swap_horiz' },
]

const priorityOptions = [
  { label: 'Baja', value: 'low', color: 'green' },
  { label: 'Media', value: 'medium', color: 'yellow-8' },
  { label: 'Alta', value: 'high', color: 'orange' },
  { label: 'Urgente', value: 'urgent', color: 'red' },
]

onMounted(async () => {
  try {
    const res = await getCategories()
    categories.value = res.data
  } catch {
    // handled
  }
})

async function onSubmit() {
  loading.value = true
  try {
    const res = await createTicket(form.value)

    if (attachedFiles.value.length > 0) {
      try {
        await uploadTicketAttachments(res.data.id, attachedFiles.value)
      } catch {
        Notify.create({ type: 'warning', message: 'Ticket creado pero algunos archivos no se subieron' })
      }
    }

    Notify.create({ type: 'positive', message: 'Ticket creado correctamente' })
    router.push(`/portal/${tenantSlug}/tickets/${res.data.id}`)
  } catch {
    // handled by interceptor
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <q-page class="portal-page">
    <div class="portal-content">
      <div class="row q-col-gutter-xl">
        <!-- Left: Form -->
        <div class="col-12 col-md-8">
          <div class="form-container">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
              <router-link :to="`/portal/${tenantSlug}`" class="breadcrumb-link">Inicio</router-link>
              <q-icon name="chevron_right" size="14px" color="grey-6" class="q-mx-xs" />
              <span class="text-grey-7">Informar sobre un problema</span>
            </div>

            <!-- Title -->
            <h1 class="form-title">Informar sobre un problema</h1>

            <!-- Form card -->
            <div class="form-card">
              <q-form @submit.prevent="onSubmit">
                <!-- Solicitante (read-only) -->
                <div class="field-group">
                  <label class="field-label">Solicitante<span class="required">*</span></label>
                  <div class="requester-field">
                    {{ portal.user?.email || 'usuario@empresa.com' }}
                  </div>
                </div>

                <!-- Asunto -->
                <div class="field-group">
                  <label class="field-label">Asunto<span class="required">*</span></label>
                  <q-input
                    v-model="form.title"
                    outlined
                    dense
                    :rules="[val => !!val || 'Campo requerido']"
                    lazy-rules
                    placeholder="Escribe un asunto breve y descriptivo"
                    class="portal-input"
                  />
                </div>

                <!-- Descripcion -->
                <div class="field-group">
                  <label class="field-label">Descripción<span class="required">*</span></label>
                  <q-editor
                    v-model="form.description"
                    placeholder="Describe tu problema o solicitud en detalle..."
                    min-height="180px"
                    :toolbar="[
                      ['bold', 'italic', 'underline'],
                      ['unordered', 'ordered'],
                      ['link'],
                      ['undo', 'redo'],
                    ]"
                    flat
                    class="portal-editor"
                  />
                </div>

                <!-- Adjuntar archivo -->
                <div class="field-group">
                  <q-file
                    v-model="attachedFiles"
                    outlined
                    dense
                    multiple
                    append
                    counter
                    max-file-size="41943040"
                    accept=".png,.jpg,.jpeg,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"
                    class="portal-input"
                  >
                    <template #prepend>
                      <q-icon name="attach_file" size="18px" color="grey-7" />
                    </template>
                    <template #default>
                      <div v-if="!attachedFiles.length" class="text-grey-6" style="font-size: 13px;">
                        Adjuntar un archivo
                      </div>
                    </template>
                    <template #hint>
                      <span class="text-grey-5">Tamaño de archivo &lt; 40 MB</span>
                    </template>
                    <template #file="{ file, index }">
                      <q-chip
                        removable
                        dense
                        size="sm"
                        color="primary"
                        text-color="white"
                        @remove="attachedFiles.splice(index, 1)"
                      >
                        {{ file.name }}
                      </q-chip>
                    </template>
                  </q-file>
                </div>

                <!-- Categoria del ticket -->
                <div class="field-group" v-if="categories.length">
                  <label class="field-label">Categoría del ticket<span class="required">*</span></label>
                  <q-select
                    v-model="form.category_id"
                    :options="[{ label: '...', value: null }, ...categories.map(c => ({ label: c.name, value: c.id }))]"
                    outlined
                    dense
                    emit-value
                    map-options
                    class="portal-input category-select"
                    style="max-width: 280px;"
                  />
                </div>

                <!-- Tipo & Prioridad (hidden for portal - use defaults, but admins may see them) -->
                <div class="field-group">
                  <div class="row q-col-gutter-md">
                    <div class="col-12 col-sm-6">
                      <label class="field-label">Tipo</label>
                      <q-select
                        v-model="form.type"
                        :options="typeOptions"
                        outlined
                        dense
                        emit-value
                        map-options
                        class="portal-input"
                      >
                        <template v-slot:option="scope">
                          <q-item v-bind="scope.itemProps" dense>
                            <q-item-section avatar style="min-width: 28px;">
                              <q-icon :name="scope.opt.icon" size="18px" color="grey-7" />
                            </q-item-section>
                            <q-item-section>{{ scope.opt.label }}</q-item-section>
                          </q-item>
                        </template>
                      </q-select>
                    </div>

                    <div class="col-12 col-sm-6">
                      <label class="field-label">Prioridad</label>
                      <q-select
                        v-model="form.priority"
                        :options="priorityOptions"
                        outlined
                        dense
                        emit-value
                        map-options
                        class="portal-input"
                      >
                        <template v-slot:option="scope">
                          <q-item v-bind="scope.itemProps" dense>
                            <q-item-section avatar style="min-width: 28px;">
                              <q-icon name="circle" :color="scope.opt.color" size="10px" />
                            </q-item-section>
                            <q-item-section>{{ scope.opt.label }}</q-item-section>
                          </q-item>
                        </template>
                      </q-select>
                    </div>
                  </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                  <q-btn
                    flat
                    no-caps
                    label="Cancelar"
                    :to="`/portal/${tenantSlug}`"
                    class="cancel-btn"
                  />
                  <q-btn
                    type="submit"
                    no-caps
                    unelevated
                    label="Enviar"
                    :loading="loading"
                    color="primary"
                    class="submit-btn"
                  />
                </div>
              </q-form>
            </div>
          </div>
        </div>

        <!-- Right: Help sidebar -->
        <div class="col-12 col-md-4 gt-sm">
          <div class="help-sidebar">
            <div class="help-illustration">
              <q-icon name="find_in_page" size="64px" color="grey-4" />
            </div>
            <div class="help-title">¿Desea resolver su problema rápidamente?</div>
            <div class="help-text">
              ¡Añada más información sobre el tema para ver los artículos relevantes aquí mismo!
            </div>
            <q-btn
              flat
              no-caps
              dense
              color="primary"
              label="Buscar en Base de Conocimiento"
              icon="search"
              :to="`/portal/${tenantSlug}/kb`"
              class="q-mt-md"
            />
          </div>
        </div>
      </div>
    </div>
  </q-page>
</template>

<style scoped>
.portal-page {
  background: #f0f2f5;
  min-height: calc(100vh - 50px);
}

.portal-content {
  max-width: 1140px;
  margin: 0 auto;
  padding: 24px 24px 48px;
}

/* Breadcrumb */
.breadcrumb {
  display: flex;
  align-items: center;
  font-size: 13px;
  margin-bottom: 8px;
}

.breadcrumb-link {
  color: var(--q-primary);
  text-decoration: none;
  font-weight: 500;
}

.breadcrumb-link:hover {
  text-decoration: underline;
}

/* Title */
.form-title {
  font-size: 22px;
  font-weight: 700;
  color: #1a2332;
  margin: 0 0 20px 0;
  line-height: 1.3;
}

/* Form card */
.form-card {
  background: #fff;
  border: 1px solid #e0e6ed;
  border-radius: 8px;
  padding: 32px 40px 24px;
}

/* Field groups */
.field-group {
  margin-bottom: 20px;
}

.field-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  margin-bottom: 6px;
}

.required {
  color: #dc2626;
  margin-left: 2px;
}

/* Requester field */
.requester-field {
  padding: 9px 12px;
  border: 1px solid #d0d6dd;
  border-radius: 4px;
  background: #f8fafc;
  font-size: 14px;
  color: #334155;
}

/* Portal inputs — clean underlines like Freshservice */
.portal-input :deep(.q-field__control) {
  border-radius: 4px;
}

/* Category select smaller */
.category-select :deep(.q-field__control) {
  min-width: 180px;
}

/* Editor */
.portal-editor {
  border: 1px solid #d0d6dd;
  border-radius: 4px;
}

.portal-editor:focus-within {
  border-color: var(--q-primary);
}

/* Form actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 28px;
  padding-top: 20px;
  border-top: 1px solid #e0e6ed;
}

.cancel-btn {
  border: 1px solid #d0d6dd;
  border-radius: 4px;
  padding: 6px 28px;
  color: #475569;
  font-weight: 500;
}

.submit-btn {
  padding: 6px 32px;
  font-weight: 600;
  border-radius: 4px;
  letter-spacing: 0.3px;
}

/* Help sidebar */
.help-sidebar {
  background: #fff;
  border: 1px solid #e0e6ed;
  border-radius: 8px;
  padding: 32px 24px;
  text-align: center;
  position: sticky;
  top: 80px;
}

.help-illustration {
  margin-bottom: 16px;
  opacity: 0.7;
}

.help-title {
  font-size: 15px;
  font-weight: 700;
  color: #1a2332;
  margin-bottom: 8px;
  line-height: 1.4;
}

.help-text {
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
}

/* Dark mode */
.body--dark .portal-page {
  background: #0f0f1a;
}

.body--dark .form-card,
.body--dark .help-sidebar {
  background: #1e1e2e;
  border-color: #3a3a4a;
}

.body--dark .form-title {
  color: #e0e0e0;
}

.body--dark .field-label {
  color: #a0a0b0;
}

.body--dark .requester-field {
  background: #252535;
  border-color: #3a3a4a;
  color: #d0d0d8;
}

.body--dark .portal-editor {
  border-color: #3a3a4a;
}

.body--dark .form-actions {
  border-color: #3a3a4a;
}

.body--dark .cancel-btn {
  border-color: #3a3a4a;
  color: #b0b0b8;
}

.body--dark .help-title {
  color: #e0e0e0;
}

.body--dark .help-text {
  color: #8888a0;
}

/* Responsive */
@media (max-width: 599px) {
  .form-card {
    padding: 20px 16px 16px;
  }
  .portal-content {
    padding: 16px 12px 32px;
  }
}
</style>
