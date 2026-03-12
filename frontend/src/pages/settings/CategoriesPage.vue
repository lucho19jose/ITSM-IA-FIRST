<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Notify } from 'quasar'
import { getCategories, createCategory, updateCategory, deleteCategory } from '@/api/categories'
import type { Category } from '@/types'

const loading = ref(true)
const categories = ref<Category[]>([])
const showDialog = ref(false)
const editing = ref<Category | null>(null)
const form = ref({ name: '', description: '', icon: '', sort_order: 0 })

const columns = [
  { name: 'icon', label: '', field: 'icon', align: 'center' as const },
  { name: 'name', label: 'Nombre', field: 'name', align: 'left' as const },
  { name: 'description', label: 'Descripcion', field: 'description', align: 'left' as const },
  { name: 'sort_order', label: 'Orden', field: 'sort_order', align: 'center' as const },
  { name: 'actions', label: 'Acciones', field: 'id', align: 'center' as const },
]

onMounted(async () => {
  try {
    const res = await getCategories()
    categories.value = res.data
  } finally {
    loading.value = false
  }
})

function openCreate() {
  editing.value = null
  form.value = { name: '', description: '', icon: '', sort_order: 0 }
  showDialog.value = true
}

function openEdit(cat: Category) {
  editing.value = cat
  form.value = { name: cat.name, description: cat.description || '', icon: cat.icon || '', sort_order: cat.sort_order }
  showDialog.value = true
}

async function onSubmit() {
  try {
    if (editing.value) {
      const res = await updateCategory(editing.value.id, form.value)
      const idx = categories.value.findIndex(c => c.id === editing.value!.id)
      if (idx >= 0) categories.value[idx] = res.data
      Notify.create({ type: 'positive', message: 'Categoria actualizada' })
    } else {
      const res = await createCategory(form.value)
      categories.value.push(res.data)
      Notify.create({ type: 'positive', message: 'Categoria creada' })
    }
    showDialog.value = false
  } catch {
    /* handled */
  }
}

async function onDelete(cat: Category) {
  try {
    await deleteCategory(cat.id)
    categories.value = categories.value.filter(c => c.id !== cat.id)
    Notify.create({ type: 'positive', message: 'Categoria eliminada' })
  } catch {
    /* handled */
  }
}
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="text-h5">Categorias</div>
      <q-space />
      <q-btn color="primary" icon="add" label="Nueva Categoria" @click="openCreate" />
    </div>

    <q-table
      flat bordered
      :rows="categories"
      :columns="columns"
      row-key="id"
      :loading="loading"
    >
      <template v-slot:body-cell-icon="props">
        <q-td :props="props">
          <q-icon :name="props.row.icon || 'folder'" size="24px" color="primary" />
        </q-td>
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
          <div class="text-h6">{{ editing ? 'Editar' : 'Nueva' }} Categoria</div>
        </q-card-section>
        <q-card-section>
          <q-form @submit.prevent="onSubmit" class="q-gutter-md">
            <q-input v-model="form.name" label="Nombre" outlined :rules="[val => !!val || 'Requerido']" />
            <q-input v-model="form.description" label="Descripcion" outlined type="textarea" autogrow />
            <q-input v-model="form.icon" label="Icono (Material Icons)" outlined hint="Ej: computer, wifi, email" />
            <q-input v-model.number="form.sort_order" label="Orden" outlined type="number" />
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
