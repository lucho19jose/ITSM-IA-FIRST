<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { getChangeRequests, deleteChangeRequest } from '@/api/changeRequests'
import { getCategories } from '@/api/categories'
import { getAgents } from '@/api/users'
import type { ChangeRequest, Category, User } from '@/types'

const { t } = useI18n()
const router = useRouter()
const $q = useQuasar()

const loading = ref(false)
const changes = ref<ChangeRequest[]>([])
const categories = ref<Category[]>([])
const agents = ref<User[]>([])

// Pagination
const currentPage = ref(1)
const perPage = ref(15)
const totalItems = ref(0)
const totalPages = computed(() => Math.ceil(totalItems.value / perPage.value) || 1)

// Filters
const filters = ref({
  search: '',
  type: null as string | null,
  status: null as string | null,
  priority: null as string | null,
  risk_level: null as string | null,
  assigned_to: null as number | null,
})

const typeOptions = [
  { label: t('changes.types.standard'), value: 'standard' },
  { label: t('changes.types.normal'), value: 'normal' },
  { label: t('changes.types.emergency'), value: 'emergency' },
]

const statusOptions = [
  { label: t('changes.statuses.draft'), value: 'draft' },
  { label: t('changes.statuses.submitted'), value: 'submitted' },
  { label: t('changes.statuses.assessment'), value: 'assessment' },
  { label: t('changes.statuses.cab_review'), value: 'cab_review' },
  { label: t('changes.statuses.approved'), value: 'approved' },
  { label: t('changes.statuses.rejected'), value: 'rejected' },
  { label: t('changes.statuses.scheduled'), value: 'scheduled' },
  { label: t('changes.statuses.implementing'), value: 'implementing' },
  { label: t('changes.statuses.implemented'), value: 'implemented' },
  { label: t('changes.statuses.review'), value: 'review' },
  { label: t('changes.statuses.closed'), value: 'closed' },
]

const priorityOptions = [
  { label: t('changes.priorities.low'), value: 'low' },
  { label: t('changes.priorities.medium'), value: 'medium' },
  { label: t('changes.priorities.high'), value: 'high' },
  { label: t('changes.priorities.critical'), value: 'critical' },
]

function typeColor(type: string): string {
  return { standard: 'blue', normal: 'orange', emergency: 'red' }[type] || 'grey'
}

function statusColor(status: string): string {
  const colors: Record<string, string> = {
    draft: 'grey', submitted: 'blue-grey', assessment: 'indigo', cab_review: 'purple',
    approved: 'green', rejected: 'red', scheduled: 'teal', implementing: 'amber',
    implemented: 'light-green', review: 'deep-orange', closed: 'blue-grey',
  }
  return colors[status] || 'grey'
}

function priorityColor(p: string): string {
  return { low: 'green', medium: 'orange', high: 'deep-orange', critical: 'red' }[p] || 'grey'
}

function riskColor(r: string): string {
  return { low: 'green', medium: 'orange', high: 'deep-orange', very_high: 'red' }[r] || 'grey'
}

async function loadChanges() {
  loading.value = true
  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: perPage.value,
    }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.type) params.type = filters.value.type
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.priority) params.priority = filters.value.priority
    if (filters.value.risk_level) params.risk_level = filters.value.risk_level
    if (filters.value.assigned_to) params.assigned_to = filters.value.assigned_to

    const res = await getChangeRequests(params)
    changes.value = res.data || []
    totalItems.value = res.meta?.total || 0
  } catch {
    $q.notify({ type: 'negative', message: t('common.loading') })
  } finally {
    loading.value = false
  }
}

async function onDelete(cr: ChangeRequest) {
  $q.dialog({
    title: t('common.confirm'),
    message: t('changes.confirmDelete'),
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await deleteChangeRequest(cr.id)
      $q.notify({ type: 'positive', message: t('changes.deleted') })
      loadChanges()
    } catch { /* handled by interceptor */ }
  })
}

function clearFilters() {
  filters.value = { search: '', type: null, status: null, priority: null, risk_level: null, assigned_to: null }
  currentPage.value = 1
  loadChanges()
}

function applyFilters() {
  currentPage.value = 1
  loadChanges()
}

onMounted(async () => {
  loadChanges()
  try {
    const [catRes, agentRes] = await Promise.all([getCategories(), getAgents()])
    categories.value = catRes.data || []
    agents.value = agentRes.data || []
  } catch { /* ignore */ }
})
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="text-h5 text-weight-bold">{{ t('changes.title') }}</div>
      <q-space />
      <q-btn
        color="primary"
        icon="add"
        :label="t('changes.create')"
        no-caps
        @click="router.push('/changes/create')"
      />
    </div>

    <!-- Filters -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="row q-gutter-sm items-end">
          <q-input
            v-model="filters.search"
            :placeholder="t('common.search')"
            dense outlined
            class="col-12 col-sm-3"
            @keyup.enter="applyFilters"
          >
            <template #prepend><q-icon name="search" /></template>
          </q-input>
          <q-select
            v-model="filters.type"
            :options="typeOptions"
            :label="t('changes.type')"
            emit-value map-options dense outlined clearable
            class="col-12 col-sm-2"
          />
          <q-select
            v-model="filters.status"
            :options="statusOptions"
            :label="t('common.status')"
            emit-value map-options dense outlined clearable
            class="col-12 col-sm-2"
          />
          <q-select
            v-model="filters.priority"
            :options="priorityOptions"
            :label="t('common.priority')"
            emit-value map-options dense outlined clearable
            class="col-12 col-sm-2"
          />
          <div class="col-auto">
            <q-btn color="primary" :label="t('ticketList.apply')" no-caps dense @click="applyFilters" />
            <q-btn flat :label="t('ticketList.clearFilters')" no-caps dense class="q-ml-xs" @click="clearFilters" />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Table -->
    <q-card flat bordered>
      <q-table
        :rows="changes"
        :columns="[
          { name: 'title', label: t('changes.titleField'), field: 'title', align: 'left', sortable: true },
          { name: 'type', label: t('changes.type'), field: 'type', align: 'center', sortable: true },
          { name: 'status', label: t('common.status'), field: 'status', align: 'center', sortable: true },
          { name: 'priority', label: t('common.priority'), field: 'priority', align: 'center', sortable: true },
          { name: 'risk_level', label: t('changes.riskLevel'), field: 'risk_level', align: 'center' },
          { name: 'requester', label: t('changes.requester'), field: (r: any) => r.requester?.name, align: 'left' },
          { name: 'assignee', label: t('changes.assignee'), field: (r: any) => r.assignee?.name, align: 'left' },
          { name: 'created_at', label: t('tickets.createdAt'), field: 'created_at', align: 'left', sortable: true },
          { name: 'actions', label: t('common.actions'), field: 'id', align: 'center' },
        ]"
        :loading="loading"
        row-key="id"
        flat
        :rows-per-page-options="[15, 30, 50]"
        :pagination="{ page: currentPage, rowsPerPage: perPage, rowsNumber: totalItems }"
        @request="(p: any) => { currentPage = p.pagination.page; perPage = p.pagination.rowsPerPage; loadChanges() }"
      >
        <template #body-cell-title="{ row }">
          <q-td>
            <a
              class="text-primary cursor-pointer text-weight-medium"
              style="text-decoration: none;"
              @click="router.push(`/changes/${row.id}`)"
            >
              {{ row.title }}
            </a>
          </q-td>
        </template>

        <template #body-cell-type="{ row }">
          <q-td class="text-center">
            <q-badge :color="typeColor(row.type)" :label="t(`changes.types.${row.type}`)" />
          </q-td>
        </template>

        <template #body-cell-status="{ row }">
          <q-td class="text-center">
            <q-badge :color="statusColor(row.status)" :label="t(`changes.statuses.${row.status}`)" />
          </q-td>
        </template>

        <template #body-cell-priority="{ row }">
          <q-td class="text-center">
            <q-badge :color="priorityColor(row.priority)" :label="t(`changes.priorities.${row.priority}`)" />
          </q-td>
        </template>

        <template #body-cell-risk_level="{ row }">
          <q-td class="text-center">
            <q-badge :color="riskColor(row.risk_level)" :label="t(`changes.riskLevels.${row.risk_level}`)" />
          </q-td>
        </template>

        <template #body-cell-created_at="{ row }">
          <q-td>{{ new Date(row.created_at).toLocaleDateString() }}</q-td>
        </template>

        <template #body-cell-actions="{ row }">
          <q-td class="text-center">
            <q-btn flat round dense icon="visibility" size="sm" @click="router.push(`/changes/${row.id}`)" />
            <q-btn
              v-if="row.status === 'draft'"
              flat round dense icon="delete" size="sm" color="negative"
              @click="onDelete(row)"
            />
          </q-td>
        </template>

        <template #no-data>
          <div class="full-width text-center q-pa-xl text-grey-5">
            <q-icon name="swap_horiz" size="48px" class="q-mb-sm" />
            <div class="text-body1">{{ t('changes.noChanges') }}</div>
          </div>
        </template>
      </q-table>
    </q-card>
  </q-page>
</template>
