<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Notify } from 'quasar'
import { getUsers, createUser, updateUser, deleteUser } from '@/api/users'
import type { User } from '@/types'

const loading = ref(true)
const users = ref<User[]>([])
const showDialog = ref(false)
const editingUser = ref<User | null>(null)
const form = ref({ name: '', email: '', password: '', role: 'end_user' })

const roleOptions = [
  { label: 'Admin', value: 'admin' },
  { label: 'Agente', value: 'agent' },
  { label: 'Usuario Final', value: 'end_user' },
]

const columns = [
  { name: 'name', label: 'Nombre', field: 'name', align: 'left' as const },
  { name: 'email', label: 'Email', field: 'email', align: 'left' as const },
  { name: 'role', label: 'Rol', field: 'role', align: 'center' as const },
  { name: 'actions', label: 'Acciones', field: 'id', align: 'center' as const },
]

onMounted(async () => {
  try {
    const res = await getUsers()
    users.value = res.data
  } finally {
    loading.value = false
  }
})

function openCreate() {
  editingUser.value = null
  form.value = { name: '', email: '', password: '', role: 'end_user' }
  showDialog.value = true
}

function openEdit(user: User) {
  editingUser.value = user
  form.value = { name: user.name, email: user.email, password: '', role: user.role }
  showDialog.value = true
}

async function onSubmit() {
  try {
    if (editingUser.value) {
      const data: any = { name: form.value.name, email: form.value.email, role: form.value.role }
      if (form.value.password) data.password = form.value.password
      const res = await updateUser(editingUser.value.id, data)
      const idx = users.value.findIndex(u => u.id === editingUser.value!.id)
      if (idx >= 0) users.value[idx] = res.data
      Notify.create({ type: 'positive', message: 'Usuario actualizado' })
    } else {
      const res = await createUser(form.value)
      users.value.push(res.data)
      Notify.create({ type: 'positive', message: 'Usuario creado' })
    }
    showDialog.value = false
  } catch {
    /* handled */
  }
}

async function onDelete(user: User) {
  try {
    await deleteUser(user.id)
    users.value = users.value.filter(u => u.id !== user.id)
    Notify.create({ type: 'positive', message: 'Usuario eliminado' })
  } catch {
    /* handled */
  }
}

function getRoleBadge(role: string): { color: string; label: string } {
  const map: Record<string, { color: string; label: string }> = {
    admin: { color: 'negative', label: 'Admin' },
    agent: { color: 'primary', label: 'Agente' },
    end_user: { color: 'grey', label: 'Usuario' },
  }
  return map[role] || { color: 'grey', label: role }
}
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="text-h5">Usuarios</div>
      <q-space />
      <q-btn color="primary" icon="add" label="Nuevo Usuario" @click="openCreate" />
    </div>

    <q-table
      flat bordered
      :rows="users"
      :columns="columns"
      row-key="id"
      :loading="loading"
    >
      <template v-slot:body-cell-role="props">
        <q-td :props="props">
          <q-badge :color="getRoleBadge(props.row.role).color">
            {{ getRoleBadge(props.row.role).label }}
          </q-badge>
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
      <q-card style="width: 400px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">{{ editingUser ? 'Editar' : 'Nuevo' }} Usuario</div>
        </q-card-section>
        <q-card-section>
          <q-form @submit.prevent="onSubmit" class="q-gutter-md">
            <q-input v-model="form.name" label="Nombre" outlined :rules="[val => !!val || 'Requerido']" />
            <q-input v-model="form.email" label="Email" outlined type="email" :rules="[val => !!val || 'Requerido']" />
            <q-input v-model="form.password" :label="editingUser ? 'Contrasena (dejar vacio para no cambiar)' : 'Contrasena'"
              outlined type="password" :rules="editingUser ? [] : [(val: string) => !!val || 'Requerido']" />
            <q-select v-model="form.role" :options="roleOptions" label="Rol" outlined emit-value map-options />
            <div class="row justify-end q-gutter-sm">
              <q-btn flat label="Cancelar" v-close-popup />
              <q-btn type="submit" color="primary" :label="editingUser ? 'Actualizar' : 'Crear'" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>
