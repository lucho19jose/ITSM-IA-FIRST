<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { Notify } from 'quasar'
import {
  getTenants,
  createTenant,
  updateTenant,
  deleteTenant,
  toggleTenantActive,
  getTenantUsers,
  impersonateTenant,
} from '@/api/admin'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import type { Tenant, User } from '@/types'

const auth = useAuthStore()
const router = useRouter()

const loading = ref(true)
const tenants = ref<(Tenant & { users_count?: number })[]>([])
const pagination = ref({ page: 1, rowsPerPage: 15, rowsNumber: 0 })

const filters = ref({
  search: '',
  plan: null as string | null,
  is_active: null as boolean | null,
})

// Create/Edit dialog
const showDialog = ref(false)
const editing = ref<Tenant | null>(null)
const form = ref({
  name: '',
  ruc: '',
  plan: 'trial' as string,
  is_active: true,
  trial_ends_at: '',
  custom_domain: '',
  admin_name: '',
  admin_email: '',
  admin_password: '',
})

// Users dialog
const showUsersDialog = ref(false)
const selectedTenant = ref<Tenant | null>(null)
const tenantUsers = ref<User[]>([])
const usersLoading = ref(false)

const planOptions = [
  { label: 'Todos', value: null },
  { label: 'Trial', value: 'trial' },
  { label: 'Basico', value: 'basic' },
  { label: 'Profesional', value: 'professional' },
  { label: 'Enterprise', value: 'enterprise' },
]

const planColors: Record<string, string> = {
  trial: 'warning',
  basic: 'info',
  professional: 'primary',
  enterprise: 'positive',
}

async function loadTenants() {
  loading.value = true
  try {
    const params: Record<string, any> = {
      page: pagination.value.page,
      per_page: pagination.value.rowsPerPage,
    }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.plan) params.plan = filters.value.plan
    if (filters.value.is_active !== null) params.is_active = filters.value.is_active

    const res = await getTenants(params)
    tenants.value = res.data
    pagination.value.rowsNumber = res.total
  } finally {
    loading.value = false
  }
}

onMounted(loadTenants)

let searchTimeout: ReturnType<typeof setTimeout>
watch(() => filters.value.search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    pagination.value.page = 1
    loadTenants()
  }, 400)
})

watch([() => filters.value.plan, () => filters.value.is_active], () => {
  pagination.value.page = 1
  loadTenants()
})

function onRequest(props: any) {
  pagination.value.page = props.pagination.page
  pagination.value.rowsPerPage = props.pagination.rowsPerPage
  loadTenants()
}

function openCreate() {
  editing.value = null
  form.value = { name: '', ruc: '', plan: 'trial', is_active: true, trial_ends_at: '', custom_domain: '', admin_name: '', admin_email: '', admin_password: '' }
  showDialog.value = true
}

function openEdit(tenant: Tenant) {
  editing.value = tenant
  form.value = {
    name: tenant.name,
    ruc: tenant.ruc || '',
    plan: tenant.plan,
    is_active: tenant.is_active,
    trial_ends_at: tenant.trial_ends_at || '',
    custom_domain: tenant.custom_domain || '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
  }
  showDialog.value = true
}

async function onSubmit() {
  try {
    if (editing.value) {
      await updateTenant(editing.value.id, {
        name: form.value.name,
        ruc: form.value.ruc || null,
        plan: form.value.plan,
        is_active: form.value.is_active,
        trial_ends_at: form.value.trial_ends_at || null,
        custom_domain: form.value.custom_domain || null,
      })
      Notify.create({ type: 'positive', message: 'Tenant actualizado' })
    } else {
      await createTenant(form.value)
      Notify.create({ type: 'positive', message: 'Tenant creado' })
    }
    showDialog.value = false
    loadTenants()
  } catch {
    // error handled by axios interceptor
  }
}

async function onToggleActive(tenant: Tenant) {
  try {
    await toggleTenantActive(tenant.id)
    Notify.create({ type: 'positive', message: tenant.is_active ? 'Tenant desactivado' : 'Tenant activado' })
    loadTenants()
  } catch {
    // error handled by axios interceptor
  }
}

async function onDelete(tenant: Tenant) {
  try {
    await deleteTenant(tenant.id)
    Notify.create({ type: 'positive', message: 'Tenant eliminado' })
    loadTenants()
  } catch {
    // error handled by axios interceptor
  }
}

async function onViewUsers(tenant: Tenant) {
  selectedTenant.value = tenant
  usersLoading.value = true
  showUsersDialog.value = true
  try {
    const res = await getTenantUsers(tenant.id)
    tenantUsers.value = res.data
  } finally {
    usersLoading.value = false
  }
}

async function onImpersonate(tenant: Tenant) {
  try {
    const res = await impersonateTenant(tenant.id)
    // Store original super admin token
    localStorage.setItem('super_admin_token', localStorage.getItem('token') || '')
    // Switch to tenant admin
    localStorage.setItem('token', res.token)
    auth.user = res.user
    Notify.create({ type: 'info', message: `Impersonando a ${tenant.name}` })
    router.push('/dashboard')
  } catch {
    Notify.create({ type: 'negative', message: 'Error al impersonar' })
  }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('es-PE')
}

const columns = [
  { name: 'name', label: 'Nombre', field: 'name', align: 'left' as const, sortable: true },
  { name: 'slug', label: 'Slug', field: 'slug', align: 'left' as const },
  { name: 'custom_domain', label: 'Dominio', field: 'custom_domain', align: 'left' as const },
  { name: 'ruc', label: 'RUC', field: 'ruc', align: 'left' as const },
  { name: 'plan', label: 'Plan', field: 'plan', align: 'center' as const },
  { name: 'users_count', label: 'Usuarios', field: 'users_count', align: 'center' as const },
  { name: 'is_active', label: 'Activo', field: 'is_active', align: 'center' as const },
  { name: 'created_at', label: 'Creado', field: 'created_at', align: 'left' as const },
  { name: 'actions', label: 'Acciones', field: 'id', align: 'center' as const },
]

const usersColumns = [
  { name: 'name', label: 'Nombre', field: 'name', align: 'left' as const },
  { name: 'email', label: 'Email', field: 'email', align: 'left' as const },
  { name: 'role', label: 'Rol', field: 'role', align: 'center' as const },
]
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="text-h5">Gestion de Tenants</div>
      <q-space />
      <q-btn color="primary" icon="add" label="Nuevo Tenant" @click="openCreate" />
    </div>

    <!-- Filters -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="row q-col-gutter-sm items-center">
          <div class="col-12 col-sm-4">
            <q-input v-model="filters.search" placeholder="Buscar por nombre, slug o RUC..." dense outlined clearable>
              <template v-slot:prepend><q-icon name="search" /></template>
            </q-input>
          </div>
          <div class="col-6 col-sm-3">
            <q-select v-model="filters.plan" :options="planOptions" dense outlined emit-value map-options label="Plan" />
          </div>
          <div class="col-6 col-sm-3">
            <q-select v-model="filters.is_active" :options="[
              { label: 'Todos', value: null },
              { label: 'Activos', value: true },
              { label: 'Inactivos', value: false },
            ]" dense outlined emit-value map-options label="Estado" />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Table -->
    <q-table
      flat bordered
      :rows="tenants"
      :columns="columns"
      row-key="id"
      :loading="loading"
      v-model:pagination="pagination"
      @request="onRequest"
    >
      <template v-slot:body-cell-slug="props">
        <q-td :props="props">
          <span class="text-primary">{{ props.row.slug }}.autoservice.test</span>
        </q-td>
      </template>
      <template v-slot:body-cell-custom_domain="props">
        <q-td :props="props">
          <span v-if="props.row.custom_domain" class="text-accent">{{ props.row.custom_domain }}</span>
          <span v-else class="text-grey">—</span>
        </q-td>
      </template>
      <template v-slot:body-cell-plan="props">
        <q-td :props="props">
          <q-badge :color="planColors[props.row.plan] || 'grey'">
            {{ props.row.plan.toUpperCase() }}
          </q-badge>
        </q-td>
      </template>
      <template v-slot:body-cell-is_active="props">
        <q-td :props="props">
          <q-icon
            :name="props.row.is_active ? 'check_circle' : 'cancel'"
            :color="props.row.is_active ? 'positive' : 'negative'"
            size="24px"
          />
        </q-td>
      </template>
      <template v-slot:body-cell-created_at="props">
        <q-td :props="props">{{ formatDate(props.row.created_at) }}</q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat size="sm" icon="edit" color="primary" @click="openEdit(props.row)">
            <q-tooltip>Editar</q-tooltip>
          </q-btn>
          <q-btn flat size="sm" icon="people" color="info" @click="onViewUsers(props.row)">
            <q-tooltip>Ver usuarios</q-tooltip>
          </q-btn>
          <q-btn flat size="sm" icon="login" color="accent" @click="onImpersonate(props.row)">
            <q-tooltip>Impersonar</q-tooltip>
          </q-btn>
          <q-btn flat size="sm" :icon="props.row.is_active ? 'block' : 'check_circle'" :color="props.row.is_active ? 'warning' : 'positive'" @click="onToggleActive(props.row)">
            <q-tooltip>{{ props.row.is_active ? 'Desactivar' : 'Activar' }}</q-tooltip>
          </q-btn>
          <q-btn flat size="sm" icon="delete" color="negative" @click="onDelete(props.row)">
            <q-tooltip>Eliminar</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 500px">
        <q-card-section>
          <div class="text-h6">{{ editing ? 'Editar' : 'Nuevo' }} Tenant</div>
        </q-card-section>
        <q-card-section>
          <q-form @submit.prevent="onSubmit" class="q-gutter-md">
            <q-input v-model="form.name" label="Nombre de la empresa *" outlined :rules="[val => !!val || 'Requerido']" />
            <q-input v-model="form.ruc" label="RUC" outlined mask="###########" hint="11 digitos" />
            <div class="row q-col-gutter-md">
              <div class="col-6">
                <q-select v-model="form.plan" :options="[
                  { label: 'Trial', value: 'trial' },
                  { label: 'Basico', value: 'basic' },
                  { label: 'Profesional', value: 'professional' },
                  { label: 'Enterprise', value: 'enterprise' },
                ]" label="Plan" outlined emit-value map-options />
              </div>
              <div class="col-6 flex items-center">
                <q-toggle v-model="form.is_active" label="Activo" />
              </div>
            </div>
            <q-input v-model="form.trial_ends_at" label="Trial expira" outlined type="date" />
            <q-input v-model="form.custom_domain" label="Dominio personalizado" outlined placeholder="soporte.empresa.com" />

            <template v-if="!editing">
              <q-separator />
              <div class="text-subtitle2">Administrador del tenant</div>
              <q-input v-model="form.admin_name" label="Nombre del admin *" outlined :rules="[val => !!val || 'Requerido']" />
              <q-input v-model="form.admin_email" label="Email del admin *" outlined type="email" :rules="[val => !!val || 'Requerido']" />
              <q-input v-model="form.admin_password" label="Contrasena *" outlined type="password" :rules="[val => val.length >= 8 || 'Minimo 8 caracteres']" />
            </template>

            <div class="row justify-end q-gutter-sm">
              <q-btn flat label="Cancelar" v-close-popup />
              <q-btn type="submit" color="primary" :label="editing ? 'Actualizar' : 'Crear'" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Users Dialog -->
    <q-dialog v-model="showUsersDialog">
      <q-card style="min-width: 500px">
        <q-card-section>
          <div class="text-h6">Usuarios de {{ selectedTenant?.name }}</div>
        </q-card-section>
        <q-card-section>
          <div v-if="usersLoading" class="flex flex-center q-pa-md">
            <q-spinner-dots size="30px" color="primary" />
          </div>
          <q-table v-else flat :rows="tenantUsers" :columns="usersColumns" row-key="id" hide-bottom>
            <template v-slot:body-cell-role="props">
              <q-td :props="props">
                <q-badge :color="({ admin: 'negative', agent: 'primary', end_user: 'grey' } as Record<string, string>)[props.row.role] || 'grey'">
                  {{ props.row.role }}
                </q-badge>
              </q-td>
            </template>
          </q-table>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cerrar" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
