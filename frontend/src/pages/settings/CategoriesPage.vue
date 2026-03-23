<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Notify, useQuasar } from 'quasar'
import { getCategories, createCategory, updateCategory, deleteCategory } from '@/api/categories'
import type { Category } from '@/types'

const $q = useQuasar()

const loading = ref(true)
const categories = ref<Category[]>([])
const showDialog = ref(false)
const showDeleteDialog = ref(false)
const editing = ref<Category | null>(null)
const deletingCategory = ref<Category | null>(null)
const submitting = ref(false)
const form = ref({ name: '', description: '', icon: '', sort_order: 0 })

const columns = [
  { name: 'icon', label: '', field: 'icon', align: 'center' as const, style: 'width: 48px' },
  { name: 'name', label: 'Nombre', field: 'name', align: 'left' as const, sortable: true },
  { name: 'description', label: 'Descripcion', field: 'description', align: 'left' as const },
  { name: 'is_active', label: 'Activa', field: 'is_active', align: 'center' as const },
  { name: 'sort_order', label: 'Orden', field: 'sort_order', align: 'center' as const, sortable: true },
  { name: 'actions', label: 'Acciones', field: 'id', align: 'center' as const },
]

onMounted(async () => {
  await loadCategories()
})

async function loadCategories() {
  loading.value = true
  try {
    const res = await getCategories()
    categories.value = res.data
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { name: '', description: '', icon: '', sort_order: 0 }
  showDialog.value = true
}

function openEdit(cat: Category) {
  editing.value = cat
  form.value = {
    name: cat.name,
    description: cat.description || '',
    icon: cat.icon || '',
    sort_order: cat.sort_order,
  }
  showDialog.value = true
}

function confirmDelete(cat: Category) {
  deletingCategory.value = cat
  showDeleteDialog.value = true
}

async function onSubmit() {
  submitting.value = true
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
    /* handled by interceptor */
  } finally {
    submitting.value = false
  }
}

async function onDelete() {
  if (!deletingCategory.value) return
  try {
    await deleteCategory(deletingCategory.value.id)
    categories.value = categories.value.filter(c => c.id !== deletingCategory.value!.id)
    Notify.create({ type: 'positive', message: 'Categoria eliminada' })
    showDeleteDialog.value = false
    deletingCategory.value = null
  } catch {
    /* handled by interceptor */
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
      :no-data-label="'No hay categorias configuradas'"
    >
      <template v-slot:body-cell-icon="props">
        <q-td :props="props">
          <q-icon :name="props.row.icon || 'folder'" size="24px" color="primary" />
        </q-td>
      </template>
      <template v-slot:body-cell-description="props">
        <q-td :props="props">
          <span v-if="props.row.description">{{ props.row.description }}</span>
          <span v-else class="text-grey-5 text-italic">Sin descripcion</span>
        </q-td>
      </template>
      <template v-slot:body-cell-is_active="props">
        <q-td :props="props">
          <q-chip
            dense
            :color="props.row.is_active ? 'positive' : 'grey'"
            text-color="white"
            size="sm"
          >
            {{ props.row.is_active ? 'Activa' : 'Inactiva' }}
          </q-chip>
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
      <q-card style="min-width: 450px">
        <q-card-section>
          <div class="text-h6">{{ editing ? 'Editar' : 'Nueva' }} Categoria</div>
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
            <q-input
              v-model="form.icon"
              label="Icono (Material Icons)"
              outlined
              hint="Ej: computer, wifi, email, build"
            >
              <template v-slot:prepend>
                <q-icon :name="form.icon || 'folder'" />
              </template>
            </q-input>
            <q-input
              v-model.number="form.sort_order"
              label="Orden"
              outlined
              type="number"
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
            ¿Estas seguro de que deseas eliminar la categoria
            <strong>{{ deletingCategory?.name }}</strong>?
          </p>
          <p class="text-caption text-grey">
            Esta accion no se puede deshacer. Los tickets asociados a esta categoria perderan su asignacion.
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
