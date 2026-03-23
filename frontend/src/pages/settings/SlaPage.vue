<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Notify, useQuasar } from 'quasar'
import { getSlaPolicies, createSlaPolicy, updateSlaPolicy, deleteSlaPolicy } from '@/api/sla'
import type { SlaPolicy } from '@/types'

const $q = useQuasar()

const loading = ref(true)
const policies = ref<SlaPolicy[]>([])
const showDialog = ref(false)
const showDeleteDialog = ref(false)
const editing = ref<SlaPolicy | null>(null)
const deletingPolicy = ref<SlaPolicy | null>(null)
const submitting = ref(false)
const form = ref({
  name: '',
  description: '',
  priority: 'medium' as 'low' | 'medium' | 'high' | 'urgent',
  response_time: 240,
  resolution_time: 1440,
  is_active: true,
})

const priorityOptions = [
  { label: 'Baja', value: 'low' },
  { label: 'Media', value: 'medium' },
  { label: 'Alta', value: 'high' },
  { label: 'Urgente', value: 'urgent' },
]

const priorityColorMap: Record<string, string> = {
  low: 'positive',
  medium: 'primary',
  high: 'warning',
  urgent: 'negative',
}

const priorityLabelMap: Record<string, string> = {
  low: 'Baja',
  medium: 'Media',
  high: 'Alta',
  urgent: 'Urgente',
}

const columns = [
  { name: 'name', label: 'Nombre', field: 'name', align: 'left' as const, sortable: true },
  { name: 'priority', label: 'Prioridad', field: 'priority', align: 'center' as const, sortable: true },
  { name: 'response_time', label: 'Tiempo de Respuesta', field: 'response_time', align: 'center' as const, sortable: true },
  { name: 'resolution_time', label: 'Tiempo de Resolucion', field: 'resolution_time', align: 'center' as const, sortable: true },
  { name: 'is_active', label: 'Activa', field: 'is_active', align: 'center' as const },
  { name: 'actions', label: 'Acciones', field: 'id', align: 'center' as const },
]

onMounted(async () => {
  await loadPolicies()
})

async function loadPolicies() {
  loading.value = true
  try {
    const res = await getSlaPolicies()
    policies.value = res.data
  } finally {
    loading.value = false
  }
}

function formatTimeHuman(minutes: number): string {
  if (minutes < 60) {
    return `${minutes} minutos`
  }
  const hours = Math.floor(minutes / 60)
  const remaining = minutes % 60
  if (hours < 24) {
    if (remaining > 0) {
      return `${hours} ${hours === 1 ? 'hora' : 'horas'} ${remaining} min`
    }
    return `${hours} ${hours === 1 ? 'hora' : 'horas'}`
  }
  const days = Math.floor(hours / 24)
  const remainingHours = hours % 24
  if (remainingHours > 0) {
    return `${days} ${days === 1 ? 'dia' : 'dias'} ${remainingHours}h`
  }
  return `${days} ${days === 1 ? 'dia' : 'dias'}`
}

function openCreate() {
  editing.value = null
  form.value = {
    name: '',
    description: '',
    priority: 'medium',
    response_time: 240,
    resolution_time: 1440,
    is_active: true,
  }
  showDialog.value = true
}

function openEdit(p: SlaPolicy) {
  editing.value = p
  form.value = {
    name: p.name,
    description: (p as any).description || '',
    priority: p.priority,
    response_time: p.response_time,
    resolution_time: p.resolution_time,
    is_active: p.is_active,
  }
  showDialog.value = true
}

function confirmDelete(p: SlaPolicy) {
  deletingPolicy.value = p
  showDeleteDialog.value = true
}

async function onSubmit() {
  submitting.value = true
  try {
    if (editing.value) {
      const res = await updateSlaPolicy(editing.value.id, form.value)
      const idx = policies.value.findIndex(p => p.id === editing.value!.id)
      if (idx >= 0) policies.value[idx] = res.data
      Notify.create({ type: 'positive', message: 'Politica SLA actualizada' })
    } else {
      const res = await createSlaPolicy(form.value)
      policies.value.push(res.data)
      Notify.create({ type: 'positive', message: 'Politica SLA creada' })
    }
    showDialog.value = false
  } catch {
    /* handled by interceptor */
  } finally {
    submitting.value = false
  }
}

async function onToggleActive(p: SlaPolicy) {
  try {
    const res = await updateSlaPolicy(p.id, { is_active: !p.is_active })
    const idx = policies.value.findIndex(x => x.id === p.id)
    if (idx >= 0) policies.value[idx] = res.data
    Notify.create({
      type: 'positive',
      message: res.data.is_active ? 'Politica activada' : 'Politica desactivada',
    })
  } catch {
    /* handled by interceptor */
  }
}

async function onDelete() {
  if (!deletingPolicy.value) return
  try {
    await deleteSlaPolicy(deletingPolicy.value.id)
    policies.value = policies.value.filter(x => x.id !== deletingPolicy.value!.id)
    Notify.create({ type: 'positive', message: 'Politica SLA eliminada' })
    showDeleteDialog.value = false
    deletingPolicy.value = null
  } catch {
    /* handled by interceptor */
  }
}
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="text-h5">Politicas SLA</div>
      <q-space />
      <q-btn color="primary" icon="add" label="Nueva Politica" @click="openCreate" />
    </div>

    <q-table
      flat bordered
      :rows="policies"
      :columns="columns"
      row-key="id"
      :loading="loading"
      :no-data-label="'No hay politicas SLA configuradas'"
    >
      <template v-slot:body-cell-priority="props">
        <q-td :props="props">
          <q-chip
            dense
            :color="priorityColorMap[props.row.priority] || 'grey'"
            text-color="white"
            size="sm"
          >
            {{ priorityLabelMap[props.row.priority] || props.row.priority }}
          </q-chip>
        </q-td>
      </template>
      <template v-slot:body-cell-response_time="props">
        <q-td :props="props">
          <div class="text-weight-medium">{{ formatTimeHuman(props.row.response_time) }}</div>
          <div class="text-caption text-grey">{{ props.row.response_time }} min</div>
        </q-td>
      </template>
      <template v-slot:body-cell-resolution_time="props">
        <q-td :props="props">
          <div class="text-weight-medium">{{ formatTimeHuman(props.row.resolution_time) }}</div>
          <div class="text-caption text-grey">{{ props.row.resolution_time }} min</div>
        </q-td>
      </template>
      <template v-slot:body-cell-is_active="props">
        <q-td :props="props">
          <q-toggle
            :model-value="props.row.is_active"
            color="positive"
            @update:model-value="onToggleActive(props.row)"
          />
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense size="sm" icon="edit" color="primary" @click="openEdit(props.row)">
            <q-tooltip>Editar</q-tooltip>
          </q-btn>
          <q-btn flat dense size="sm" icon="delete" color="negative" @click="confirmDelete(props.row)">
            <q-tooltip>Eliminar</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="showDialog">
      <q-card style="min-width: 480px">
        <q-card-section>
          <div class="text-h6">{{ editing ? 'Editar' : 'Nueva' }} Politica SLA</div>
        </q-card-section>
        <q-card-section>
          <q-form @submit.prevent="onSubmit" class="q-gutter-md">
            <q-input
              v-model="form.name"
              label="Nombre *"
              outlined
              :rules="[val => !!val || 'El nombre es requerido']"
            />
            <q-input
              v-model="form.description"
              label="Descripcion"
              outlined
              type="textarea"
              autogrow
            />
            <q-select
              v-model="form.priority"
              :options="priorityOptions"
              label="Prioridad"
              outlined
              emit-value
              map-options
            />
            <q-input
              v-model.number="form.response_time"
              label="Tiempo de respuesta (minutos) *"
              outlined
              type="number"
              :rules="[(val: number) => val > 0 || 'Debe ser mayor a 0']"
              :hint="form.response_time > 0 ? formatTimeHuman(form.response_time) : ''"
            />
            <q-input
              v-model.number="form.resolution_time"
              label="Tiempo de resolucion (minutos) *"
              outlined
              type="number"
              :rules="[(val: number) => val > 0 || 'Debe ser mayor a 0']"
              :hint="form.resolution_time > 0 ? formatTimeHuman(form.resolution_time) : ''"
            />
            <q-toggle
              v-model="form.is_active"
              label="Politica activa"
              color="positive"
            />
            <div class="row justify-end q-gutter-sm">
              <q-btn flat label="Cancelar" v-close-popup />
              <q-btn
                type="submit"
                color="primary"
                :label="editing ? 'Actualizar' : 'Crear'"
                :loading="submitting"
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Delete Confirmation Dialog -->
    <q-dialog v-model="showDeleteDialog">
      <q-card style="min-width: 350px">
        <q-card-section class="row items-center">
          <q-icon name="warning" color="negative" size="28px" class="q-mr-sm" />
          <span class="text-h6">Confirmar eliminacion</span>
        </q-card-section>
        <q-card-section>
          <p>
            ¿Estas seguro de que deseas eliminar la politica SLA
            <strong>{{ deletingPolicy?.name }}</strong>?
          </p>
          <p class="text-caption text-grey">
            Los tickets que usen esta politica perderan su configuracion SLA asociada.
          </p>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="negative" label="Eliminar" icon="delete" @click="onDelete" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
