<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { Notify } from 'quasar'
import {
  getTenants,
  createTenant,
  updateTenant,
  deleteTenant,
  toggleTenantActive,
  getTenantUsers,
  impersonateTenant,
  getPlatformStats,
} from '@/api/admin'
import type { PlatformStats } from '@/api/admin'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import type { Tenant, User } from '@/types'

const auth = useAuthStore()
const router = useRouter()

// Stats
const stats = ref<PlatformStats | null>(null)
const statsLoading = ref(true)

// Table
const loading = ref(true)
const tenants = ref<(Tenant & { users_count?: number; tickets_count?: number })[]>([])
const pagination = ref({ page: 1, rowsPerPage: 15, rowsNumber: 0 })

const filters = ref({
  search: '',
  plan: null as string | null,
  is_active: null as boolean | null,
})

// Create/Edit dialog
const showDialog = ref(false)
const editing = ref<Tenant | null>(null)
const submitting = ref(false)
const form = ref({
  name: '',
  slug: '',
  ruc: '',
  plan: 'trial' as string,
  is_active: true,
  trial_ends_at: '',
  custom_domain: '',
  admin_name: '',
  admin_email: '',
  admin_password: '',
})

// Delete dialog
const showDeleteDialog = ref(false)
const deletingTenant = ref<Tenant | null>(null)
const deleteConfirmText = ref('')

// Toggle active dialog
const showToggleDialog = ref(false)
const togglingTenant = ref<Tenant | null>(null)

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

const planEditOptions = [
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

const columns = [
  { name: 'name', label: 'Nombre', field: 'name', align: 'left' as const, sortable: true },
  { name: 'slug', label: 'Slug', field: 'slug', align: 'left' as const },
  { name: 'custom_domain', label: 'Dominio', field: 'custom_domain', align: 'left' as const },
  { name: 'plan', label: 'Plan', field: 'plan', align: 'center' as const },
  { name: 'is_active', label: 'Estado', field: 'is_active', align: 'center' as const },
  { name: 'users_count', label: 'Usuarios', field: 'users_count', align: 'center' as const, sortable: true },
  { name: 'created_at', label: 'Creado', field: 'created_at', align: 'left' as const, sortable: true },
  { name: 'actions', label: 'Acciones', field: 'id', align: 'center' as const },
]

const usersColumns = [
  { name: 'name', label: 'Nombre', field: 'name', align: 'left' as const },
  { name: 'email', label: 'Email', field: 'email', align: 'left' as const },
  { name: 'role', label: 'Rol', field: 'role', align: 'center' as const },
  { name: 'is_active', label: 'Activo', field: 'is_active', align: 'center' as const },
]

const deleteConfirmValid = computed(() => {
  return deleteConfirmText.value === deletingTenant.value?.name
})

// Auto-generate slug from name
watch(() => form.value.name, (newName) => {
  if (!editing.value) {
    form.value.slug = newName
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
  }
})

async function loadStats() {
  statsLoading.value = true
  try {
    const res = await getPlatformStats()
    stats.value = res.data
  } catch {
    /* handled */
  } finally {
    statsLoading.value = false
  }
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

onMounted(() => {
  loadStats()
  loadTenants()
})

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
  form.value = {
    name: '',
    slug: '',
    ruc: '',
    plan: 'trial',
    is_active: true,
    trial_ends_at: '',
    custom_domain: '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
  }
  showDialog.value = true
}

function openEdit(tenant: Tenant) {
  editing.value = tenant
  form.value = {
    name: tenant.name,
    slug: tenant.slug,
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
  submitting.value = true
  try {
    if (editing.value) {
      await updateTenant(editing.value.id, {
        name: form.value.name,
        slug: form.value.slug,
        ruc: form.value.ruc || null,
        plan: form.value.plan,
        is_active: form.value.is_active,
        trial_ends_at: form.value.trial_ends_at || null,
        custom_domain: form.value.custom_domain || null,
      })
      Notify.create({ type: 'positive', message: 'Tenant actualizado correctamente' })
    } else {
      await createTenant({
        name: form.value.name,
        slug: form.value.slug,
        ruc: form.value.ruc || null,
        plan: form.value.plan,
        admin_name: form.value.admin_name,
        admin_email: form.value.admin_email,
        admin_password: form.value.admin_password,
      })
      Notify.create({ type: 'positive', message: 'Tenant creado correctamente' })
    }
    showDialog.value = false
    loadTenants()
    loadStats()
  } catch {
    /* handled by interceptor */
  } finally {
    submitting.value = false
  }
}

function confirmToggleActive(tenant: Tenant) {
  togglingTenant.value = tenant
  showToggleDialog.value = true
}

async function onToggleActive() {
  if (!togglingTenant.value) return
  try {
    await toggleTenantActive(togglingTenant.value.id)
    Notify.create({
      type: 'positive',
      message: togglingTenant.value.is_active
        ? 'Tenant desactivado'
        : 'Tenant activado',
    })
    showToggleDialog.value = false
    togglingTenant.value = null
    loadTenants()
    loadStats()
  } catch {
    /* handled by interceptor */
  }
}

function confirmDelete(tenant: Tenant) {
  deletingTenant.value = tenant
  deleteConfirmText.value = ''
  showDeleteDialog.value = true
}

async function onDelete() {
  if (!deletingTenant.value || !deleteConfirmValid.value) return
  try {
    await deleteTenant(deletingTenant.value.id)
    Notify.create({ type: 'positive', message: 'Tenant eliminado correctamente' })
    showDeleteDialog.value = false
    deletingTenant.value = null
    deleteConfirmText.value = ''
    loadTenants()
    loadStats()
  } catch {
    /* handled by interceptor */
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
    localStorage.setItem('super_admin_token', localStorage.getItem('token') || '')
    localStorage.setItem('token', res.token)
    auth.user = res.user
    Notify.create({ type: 'info', message: `Impersonando a ${tenant.name}` })
    router.push('/dashboard')
  } catch {
    Notify.create({ type: 'negative', message: 'Error al impersonar' })
  }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function getRoleBadge(role: string): { color: string; label: string } {
  const map: Record<string, { color: string; label: string }> = {
    admin: { color: 'negative', label: 'Admin' },
    agent: { color: 'primary', label: 'Agente' },
    end_user: { color: 'grey', label: 'Usuario' },
    super_admin: { color: 'purple', label: 'Super Admin' },
  }
  return map[role] || { color: 'grey', label: role }
}
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-lg">
      <div class="text-h5">Gestion de Tenants</div>
      <q-space />
      <q-btn color="primary" icon="add" label="Nuevo Tenant" @click="openCreate" />
    </div>

    <!-- Stats Cards -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section>
            <div class="row items-center no-wrap">
              <div class="q-mr-md">
                <q-icon name="business" size="36px" color="primary" />
              </div>
              <div>
                <div class="text-caption text-grey">Total Tenants</div>
                <div class="text-h5 text-weight-bold">
                  <q-skeleton v-if="statsLoading" type="text" width="40px" />
                  <span v-else>{{ stats?.total_tenants ?? 0 }}</span>
                </div>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section>
            <div class="row items-center no-wrap">
              <div class="q-mr-md">
                <q-icon name="check_circle" size="36px" color="positive" />
              </div>
              <div>
                <div class="text-caption text-grey">Tenants Activos</div>
                <div class="text-h5 text-weight-bold">
                  <q-skeleton v-if="statsLoading" type="text" width="40px" />
                  <span v-else>{{ stats?.active_tenants ?? 0 }}</span>
                </div>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section>
            <div class="row items-center no-wrap">
              <div class="q-mr-md">
                <q-icon name="people" size="36px" color="info" />
              </div>
              <div>
                <div class="text-caption text-grey">Total Usuarios</div>
                <div class="text-h5 text-weight-bold">
                  <q-skeleton v-if="statsLoading" type="text" width="40px" />
                  <span v-else>{{ stats?.total_users ?? 0 }}</span>
                </div>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section>
            <div class="row items-center no-wrap">
              <div class="q-mr-md">
                <q-icon name="confirmation_number" size="36px" color="warning" />
              </div>
              <div>
                <div class="text-caption text-grey">Total Tickets</div>
                <div class="text-h5 text-weight-bold">
                  <q-skeleton v-if="statsLoading" type="text" width="40px" />
                  <span v-else>{{ stats?.total_tickets ?? 0 }}</span>
                </div>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Filters -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="row q-col-gutter-sm items-center">
          <div class="col-12 col-sm-4">
            <q-input
              v-model="filters.search"
              placeholder="Buscar por nombre, slug o RUC..."
              dense
              outlined
              clearable
            >
              <template v-slot:prepend><q-icon name="search" /></template>
            </q-input>
          </div>
          <div class="col-6 col-sm-3">
            <q-select
              v-model="filters.plan"
              :options="planOptions"
              dense
              outlined
              emit-value
              map-options
              label="Plan"
            />
          </div>
          <div class="col-6 col-sm-3">
            <q-select
              v-model="filters.is_active"
              :options="[
                { label: 'Todos', value: null },
                { label: 'Activos', value: true },
                { label: 'Inactivos', value: false },
              ]"
              dense
              outlined
              emit-value
              map-options
              label="Estado"
            />
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
      :no-data-label="'No hay tenants registrados'"
    >
      <template v-slot:body-cell-slug="props">
        <q-td :props="props">
          <code class="text-primary">{{ props.row.slug }}</code>
        </q-td>
      </template>
      <template v-slot:body-cell-custom_domain="props">
        <q-td :props="props">
          <span v-if="props.row.custom_domain" class="text-accent">{{ props.row.custom_domain }}</span>
          <span v-else class="text-grey-5">--</span>
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
          <q-chip
            dense
            :color="props.row.is_active ? 'positive' : 'negative'"
            text-color="white"
            size="sm"
          >
            {{ props.row.is_active ? 'Activo' : 'Inactivo' }}
          </q-chip>
        </q-td>
      </template>
      <template v-slot:body-cell-created_at="props">
        <q-td :props="props">{{ formatDate(props.row.created_at) }}</q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense size="sm" icon="edit" color="primary" @click="openEdit(props.row)">
            <q-tooltip>Editar</q-tooltip>
          </q-btn>
          <q-btn flat dense size="sm" icon="people" color="info" @click="onViewUsers(props.row)">
            <q-tooltip>Ver usuarios</q-tooltip>
          </q-btn>
          <q-btn flat dense size="sm" icon="login" color="accent" @click="onImpersonate(props.row)">
            <q-tooltip>Impersonar</q-tooltip>
          </q-btn>
          <q-btn
            flat dense size="sm"
            :icon="props.row.is_active ? 'block' : 'check_circle'"
            :color="props.row.is_active ? 'warning' : 'positive'"
            @click="confirmToggleActive(props.row)"
          >
            <q-tooltip>{{ props.row.is_active ? 'Desactivar' : 'Activar' }}</q-tooltip>
          </q-btn>
          <q-btn flat dense size="sm" icon="delete" color="negative" @click="confirmDelete(props.row)">
            <q-tooltip>Eliminar</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 520px">
        <q-card-section>
          <div class="text-h6">{{ editing ? 'Editar' : 'Nuevo' }} Tenant</div>
        </q-card-section>
        <q-card-section>
          <q-form @submit.prevent="onSubmit" class="q-gutter-md">
            <q-input
              v-model="form.name"
              label="Nombre de la empresa *"
              outlined
              :rules="[val => !!val || 'El nombre es requerido']"
            />
            <q-input
              v-model="form.slug"
              label="Slug *"
              outlined
              hint="Identificador unico en URL"
              :rules="[
                (val: string) => !!val || 'El slug es requerido',
                (val: string) => /^[a-z0-9-]+$/.test(val) || 'Solo minusculas, numeros y guiones',
              ]"
            />
            <q-input
              v-model="form.ruc"
              label="RUC"
              outlined
              mask="###########"
              hint="11 digitos"
            />
            <div class="row q-col-gutter-md">
              <div class="col-6">
                <q-select
                  v-model="form.plan"
                  :options="planEditOptions"
                  label="Plan"
                  outlined
                  emit-value
                  map-options
                />
              </div>
              <div class="col-6 flex items-center">
                <q-toggle v-model="form.is_active" label="Activo" v-if="editing" />
              </div>
            </div>
            <q-input
              v-if="editing"
              v-model="form.custom_domain"
              label="Dominio personalizado"
              outlined
              placeholder="soporte.empresa.com"
            />
            <q-input
              v-if="editing"
              v-model="form.trial_ends_at"
              label="Trial expira"
              outlined
              type="date"
            />

            <template v-if="!editing">
              <q-separator />
              <div class="text-subtitle2 text-grey-8">Administrador del tenant</div>
              <q-input
                v-model="form.admin_name"
                label="Nombre del admin *"
                outlined
                :rules="[val => !!val || 'El nombre del admin es requerido']"
              />
              <q-input
                v-model="form.admin_email"
                label="Email del admin *"
                outlined
                type="email"
                :rules="[val => !!val || 'El email es requerido']"
              />
              <q-input
                v-model="form.admin_password"
                label="Contrasena *"
                outlined
                type="password"
                :rules="[(val: string) => val.length >= 8 || 'Minimo 8 caracteres']"
              />
            </template>

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

    <!-- Toggle Active Confirmation Dialog -->
    <q-dialog v-model="showToggleDialog">
      <q-card style="min-width: 380px">
        <q-card-section class="row items-center">
          <q-icon
            :name="togglingTenant?.is_active ? 'block' : 'check_circle'"
            :color="togglingTenant?.is_active ? 'warning' : 'positive'"
            size="28px"
            class="q-mr-sm"
          />
          <span class="text-h6">
            {{ togglingTenant?.is_active ? 'Desactivar' : 'Activar' }} tenant
          </span>
        </q-card-section>
        <q-card-section>
          <p v-if="togglingTenant?.is_active">
            ¿Estas seguro de que deseas <strong>desactivar</strong> el tenant
            <strong>{{ togglingTenant?.name }}</strong>?
            Los usuarios de este tenant no podran acceder al sistema.
          </p>
          <p v-else>
            ¿Deseas <strong>activar</strong> el tenant
            <strong>{{ togglingTenant?.name }}</strong>?
            Los usuarios podran acceder nuevamente al sistema.
          </p>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn
            :color="togglingTenant?.is_active ? 'warning' : 'positive'"
            :label="togglingTenant?.is_active ? 'Desactivar' : 'Activar'"
            @click="onToggleActive"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Delete Confirmation Dialog -->
    <q-dialog v-model="showDeleteDialog">
      <q-card style="min-width: 420px" class="bg-red-1">
        <q-card-section class="row items-center">
          <q-icon name="dangerous" color="negative" size="32px" class="q-mr-sm" />
          <span class="text-h6 text-negative">Eliminar tenant</span>
        </q-card-section>
        <q-card-section>
          <p>
            Esta a punto de eliminar permanentemente el tenant
            <strong>{{ deletingTenant?.name }}</strong> y todos sus datos asociados
            (usuarios, tickets, categorias, etc.).
          </p>
          <p class="text-weight-bold text-negative">
            Esta accion no se puede deshacer.
          </p>
          <p class="text-caption">
            Para confirmar, escriba el nombre del tenant:
            <strong>{{ deletingTenant?.name }}</strong>
          </p>
          <q-input
            v-model="deleteConfirmText"
            outlined
            dense
            :placeholder="deletingTenant?.name"
            class="q-mt-sm"
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn
            color="negative"
            label="Eliminar permanentemente"
            icon="delete_forever"
            :disable="!deleteConfirmValid"
            @click="onDelete"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Users Dialog -->
    <q-dialog v-model="showUsersDialog">
      <q-card style="min-width: 600px">
        <q-card-section class="row items-center">
          <q-icon name="people" color="info" size="24px" class="q-mr-sm" />
          <div class="text-h6">Usuarios de {{ selectedTenant?.name }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section>
          <div v-if="usersLoading" class="flex flex-center q-pa-xl">
            <q-spinner-dots size="40px" color="primary" />
          </div>
          <template v-else>
            <div v-if="tenantUsers.length === 0" class="text-center text-grey q-pa-lg">
              <q-icon name="people_outline" size="48px" class="q-mb-sm" />
              <div>Este tenant no tiene usuarios registrados</div>
            </div>
            <q-table
              v-else
              flat
              :rows="tenantUsers"
              :columns="usersColumns"
              row-key="id"
              hide-bottom
              :rows-per-page-options="[0]"
            >
              <template v-slot:body-cell-role="props">
                <q-td :props="props">
                  <q-badge :color="getRoleBadge(props.row.role).color">
                    {{ getRoleBadge(props.row.role).label }}
                  </q-badge>
                </q-td>
              </template>
              <template v-slot:body-cell-is_active="props">
                <q-td :props="props">
                  <q-icon
                    :name="props.row.is_active ? 'check_circle' : 'cancel'"
                    :color="props.row.is_active ? 'positive' : 'negative'"
                    size="20px"
                  />
                </q-td>
              </template>
            </q-table>
          </template>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cerrar" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
