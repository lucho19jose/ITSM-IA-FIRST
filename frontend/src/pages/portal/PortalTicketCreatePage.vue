<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePortalStore } from '@/stores/portal'
import { createTicket } from '@/api/tickets'
import { getCategories } from '@/api/categories'
import { Notify } from 'quasar'
import type { Category, Ticket } from '@/types'

const route = useRoute()
const router = useRouter()
const portal = usePortalStore()
const tenantSlug = route.params.tenantSlug as string

const categories = ref<Category[]>([])
const loading = ref(false)

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
    <div class="portal-container">
      <div class="row items-center q-mb-lg q-gutter-sm">
        <q-btn flat round icon="arrow_back" :to="`/portal/${tenantSlug}/tickets`" />
        <div class="text-h5 text-weight-bold">Informar sobre un problema</div>
      </div>

      <q-card class="portal-form-card">
        <q-card-section>
          <q-form @submit.prevent="onSubmit" class="q-gutter-md">
            <q-input
              v-model="form.title"
              label="Asunto"
              outlined
              :rules="[val => !!val || 'Campo requerido']"
              class="text-h6"
            />

            <q-input
              v-model="form.description"
              label="Descripcion del problema"
              type="textarea"
              outlined
              autogrow
              :rules="[val => !!val || 'Campo requerido']"
              hint="Describe el problema con el mayor detalle posible"
            />

            <div class="row q-col-gutter-md">
              <div class="col-12 col-sm-6">
                <q-select
                  v-model="form.type"
                  :options="typeOptions"
                  label="Tipo"
                  outlined
                  emit-value
                  map-options
                >
                  <template v-slot:option="scope">
                    <q-item v-bind="scope.itemProps">
                      <q-item-section avatar>
                        <q-icon :name="scope.opt.icon" />
                      </q-item-section>
                      <q-item-section>{{ scope.opt.label }}</q-item-section>
                    </q-item>
                  </template>
                </q-select>
              </div>

              <div class="col-12 col-sm-6">
                <q-select
                  v-model="form.priority"
                  :options="priorityOptions"
                  label="Prioridad"
                  outlined
                  emit-value
                  map-options
                >
                  <template v-slot:option="scope">
                    <q-item v-bind="scope.itemProps">
                      <q-item-section avatar>
                        <q-icon name="circle" :color="scope.opt.color" size="12px" />
                      </q-item-section>
                      <q-item-section>{{ scope.opt.label }}</q-item-section>
                    </q-item>
                  </template>
                </q-select>
              </div>
            </div>

            <q-select
              v-if="categories.length"
              v-model="form.category_id"
              :options="categories.map(c => ({ label: c.name, value: c.id }))"
              label="Categoria"
              outlined
              emit-value
              map-options
              clearable
            />

            <div class="row justify-end q-mt-md q-gutter-sm">
              <q-btn
                flat
                label="Cancelar"
                no-caps
                :to="`/portal/${tenantSlug}`"
              />
              <q-btn
                type="submit"
                color="primary"
                label="Enviar Ticket"
                no-caps
                :loading="loading"
                icon="send"
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<style scoped>
.portal-page {
  background: #f5f5f5;
  min-height: calc(100vh - 50px);
}
.portal-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 32px 24px;
}
.portal-form-card {
  border-radius: 8px;
}
.body--dark .portal-page {
  background: #121212;
}
</style>
