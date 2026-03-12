<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Notify } from 'quasar'
import { getSlaPolicies, createSlaPolicy, updateSlaPolicy, deleteSlaPolicy } from '@/api/sla'
import type { SlaPolicy } from '@/types'

const loading = ref(true)
const policies = ref<SlaPolicy[]>([])
const showDialog = ref(false)
const editing = ref<SlaPolicy | null>(null)
const form = ref({ name: '', priority: 'medium' as 'low' | 'medium' | 'high' | 'urgent', response_time: 240, resolution_time: 1440 })

const priorityOptions = [
  { label: 'Baja', value: 'low' },
  { label: 'Media', value: 'medium' },
  { label: 'Alta', value: 'high' },
  { label: 'Urgente', value: 'urgent' },
]

const columns = [
  { name: 'name', label: 'Nombre', field: 'name', align: 'left' as const },
  { name: 'priority', label: 'Prioridad', field: 'priority', align: 'center' as const },
  { name: 'response_time', label: 'T. Respuesta', field: 'response_time', align: 'center' as const },
  { name: 'resolution_time', label: 'T. Resolucion', field: 'resolution_time', align: 'center' as const },
  { name: 'actions', label: 'Acciones', field: 'id', align: 'center' as const },
]

const priorityColorMap: Record<string, string> = {
  low: 'positive',
  medium: 'primary',
  high: 'warning',
  urgent: 'negative',
}

onMounted(async () => {
  try {
    const res = await getSlaPolicies()
    policies.value = res.data
  } finally {
    loading.value = false
  }
})

function formatTime(minutes: number): string {
  if (minutes < 60) return `${minutes}m`
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return m > 0 ? `${h}h ${m}m` : `${h}h`
}

function openCreate() {
  editing.value = null
  form.value = { name: '', priority: 'medium' as const, response_time: 240, resolution_time: 1440 }
  showDialog.value = true
}

function openEdit(p: SlaPolicy) {
  editing.value = p
  form.value = { name: p.name, priority: p.priority as 'low' | 'medium' | 'high' | 'urgent', response_time: p.response_time, resolution_time: p.resolution_time }
  showDialog.value = true
}

async function onSubmit() {
  try {
    if (editing.value) {
      const res = await updateSlaPolicy(editing.value.id, form.value)
      const idx = policies.value.findIndex(p => p.id === editing.value!.id)
      if (idx >= 0) policies.value[idx] = res.data
      Notify.create({ type: 'positive', message: 'Politica actualizada' })
    } else {
      const res = await createSlaPolicy(form.value)
      policies.value.push(res.data)
      Notify.create({ type: 'positive', message: 'Politica creada' })
    }
    showDialog.value = false
  } catch {
    /* handled */
  }
}

async function onDelete(p: SlaPolicy) {
  try {
    await deleteSlaPolicy(p.id)
    policies.value = policies.value.filter(x => x.id !== p.id)
    Notify.create({ type: 'positive', message: 'Politica eliminada' })
  } catch {
    /* handled */
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
    >
      <template v-slot:body-cell-priority="props">
        <q-td :props="props">
          <q-badge :color="priorityColorMap[props.row.priority] || 'grey'">
            {{ props.row.priority }}
          </q-badge>
        </q-td>
      </template>
      <template v-slot:body-cell-response_time="props">
        <q-td :props="props">{{ formatTime(props.row.response_time) }}</q-td>
      </template>
      <template v-slot:body-cell-resolution_time="props">
        <q-td :props="props">{{ formatTime(props.row.resolution_time) }}</q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat size="sm" icon="edit" @click="openEdit(props.row)" />
          <q-btn flat size="sm" icon="delete" color="negative" @click="onDelete(props.row)" />
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="showDialog">
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">{{ editing ? 'Editar' : 'Nueva' }} Politica SLA</div>
        </q-card-section>
        <q-card-section>
          <q-form @submit.prevent="onSubmit" class="q-gutter-md">
            <q-input v-model="form.name" label="Nombre" outlined :rules="[val => !!val || 'Requerido']" />
            <q-select v-model="form.priority" :options="priorityOptions" label="Prioridad" outlined emit-value map-options />
            <q-input v-model.number="form.response_time" label="Tiempo de respuesta (minutos)" outlined type="number" :rules="[(val: number) => val > 0 || 'Debe ser mayor a 0']" />
            <q-input v-model.number="form.resolution_time" label="Tiempo de resolucion (minutos)" outlined type="number" :rules="[(val: number) => val > 0 || 'Debe ser mayor a 0']" />
            <div class="row justify-end q-gutter-sm">
              <q-btn flat label="Cancelar" v-close-popup />
              <q-btn type="submit" color="primary" :label="editing ? 'Actualizar' : 'Crear'" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>
