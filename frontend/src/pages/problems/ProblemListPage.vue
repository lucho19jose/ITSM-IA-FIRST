<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { getProblems, deleteProblem } from '@/api/problems'
import { getCategories } from '@/api/categories'
import { getAgents } from '@/api/users'
import type { Problem, Category, User } from '@/types'

const { t } = useI18n()
const router = useRouter()
const $q = useQuasar()

const loading = ref(false)
const problems = ref<Problem[]>([])
const categories = ref<Category[]>([])
const agents = ref<User[]>([])

// Pagination
const currentPage = ref(1)
const perPage = ref(15)
const totalItems = ref(0)
const tablePagination = ref({ page: 1, rowsPerPage: 15, rowsNumber: 0 })
function onTableRequest() { /* handled manually */ }
const totalPages = computed(() => Math.ceil(totalItems.value / perPage.value) || 1)

// Sort
const sortField = ref('created_at')
const sortDirection = ref<'desc' | 'asc'>('desc')

// Filters
const filters = ref({
  search: '',
  status: null as string | null,
  priority: null as string | null,
  assigned_to: null as number | null,
  created_from: '',
  created_to: '',
})

const statusOptions = [
  { label: t('problems.statuses.logged'), value: 'logged' },
  { label: t('problems.statuses.categorized'), value: 'categorized' },
  { label: t('problems.statuses.investigating'), value: 'investigating' },
  { label: t('problems.statuses.root_cause_identified'), value: 'root_cause_identified' },
  { label: t('problems.statuses.known_error'), value: 'known_error' },
  { label: t('problems.statuses.resolved'), value: 'resolved' },
  { label: t('problems.statuses.closed'), value: 'closed' },
]

const priorityOptions = [
  { label: t('problems.priorities.low'), value: 'low' },
  { label: t('problems.priorities.medium'), value: 'medium' },
  { label: t('problems.priorities.high'), value: 'high' },
  { label: t('problems.priorities.critical'), value: 'critical' },
]

function statusColor(status: string) {
  const colors: Record<string, string> = {
    logged: 'blue-grey',
    categorized: 'blue',
    investigating: 'orange',
    root_cause_identified: 'deep-purple',
    known_error: 'red',
    resolved: 'green',
    closed: 'grey',
  }
  return colors[status] || 'grey'
}

function priorityColor(priority: string) {
  const colors: Record<string, string> = {
    low: 'green',
    medium: 'orange',
    high: 'red',
    critical: 'deep-purple',
  }
  return colors[priority] || 'grey'
}

function impactColor(impact: string) {
  const colors: Record<string, string> = {
    low: 'green',
    medium: 'orange',
    high: 'red',
    extensive: 'deep-purple',
  }
  return colors[impact] || 'grey'
}

async function loadProblems() {
  loading.value = true
  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: perPage.value,
      sort: sortField.value,
      direction: sortDirection.value,
    }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.priority) params.priority = filters.value.priority
    if (filters.value.assigned_to) params.assigned_to = filters.value.assigned_to
    if (filters.value.created_from) params.created_from = filters.value.created_from
    if (filters.value.created_to) params.created_to = filters.value.created_to

    const res = await getProblems(params)
    problems.value = res.data
    totalItems.value = res.meta.total
  } catch { /* ignore */ }
  finally { loading.value = false }
}

async function loadFiltersData() {
  try {
    const [catRes, agentRes] = await Promise.all([
      getCategories(),
      getAgents(),
    ])
    categories.value = catRes.data || []
    agents.value = agentRes.data || []
  } catch { /* ignore */ }
}

function onSearch() {
  currentPage.value = 1
  loadProblems()
}

function clearFilters() {
  filters.value = { search: '', status: null, priority: null, assigned_to: null, created_from: '', created_to: '' }
  currentPage.value = 1
  loadProblems()
}

function goToDetail(id: number) {
  router.push({ name: 'problem-detail', params: { id } })
}

async function onDelete(problem: Problem) {
  $q.dialog({
    title: t('common.confirm'),
    message: t('problems.confirmDelete'),
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await deleteProblem(problem.id)
      $q.notify({ type: 'positive', message: t('problems.deleted') })
      loadProblems()
    } catch { /* ignore */ }
  })
}

onMounted(() => {
  loadProblems()
  loadFiltersData()
})
</script>

<template>
  <q-page padding>
    <!-- Header -->
    <div class="row items-center q-mb-md">
      <div class="col">
        <div class="text-h5 text-weight-bold">{{ t('problems.title') }}</div>
        <div class="text-caption text-grey">{{ t('problems.subtitle') }}</div>
      </div>
      <q-btn
        color="primary"
        icon="add"
        :label="t('problems.create')"
        @click="router.push({ name: 'problem-create' })"
      />
    </div>

    <!-- Filters -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="row q-col-gutter-sm items-end">
          <div class="col-12 col-sm-4">
            <q-input
              v-model="filters.search"
              :placeholder="t('common.search')"
              dense outlined clearable
              @keyup.enter="onSearch"
            >
              <template #prepend><q-icon name="search" /></template>
            </q-input>
          </div>
          <div class="col-6 col-sm-2">
            <q-select
              v-model="filters.status"
              :options="statusOptions"
              :label="t('common.status')"
              emit-value map-options dense outlined clearable
              @update:model-value="onSearch"
            />
          </div>
          <div class="col-6 col-sm-2">
            <q-select
              v-model="filters.priority"
              :options="priorityOptions"
              :label="t('common.priority')"
              emit-value map-options dense outlined clearable
              @update:model-value="onSearch"
            />
          </div>
          <div class="col-6 col-sm-2">
            <q-select
              v-model="filters.assigned_to"
              :options="agents.map(a => ({ label: a.name, value: a.id }))"
              :label="t('tickets.assignedTo')"
              emit-value map-options dense outlined clearable
              @update:model-value="onSearch"
            />
          </div>
          <div class="col-6 col-sm-2">
            <q-btn flat dense icon="filter_alt_off" :label="t('ticketList.clearFilters')" @click="clearFilters" />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Table -->
    <q-card flat bordered>
      <q-table
        :rows="problems"
        :columns="[
          { name: 'title', label: t('problems.fields.title'), field: 'title', align: 'left', sortable: true },
          { name: 'status', label: t('common.status'), field: 'status', align: 'center', sortable: true },
          { name: 'priority', label: t('common.priority'), field: 'priority', align: 'center', sortable: true },
          { name: 'impact', label: t('problems.fields.impact'), field: 'impact', align: 'center' },
          { name: 'assigned_to', label: t('tickets.assignedTo'), field: (row: any) => row.assignee?.name || '-', align: 'left' },
          { name: 'related_incidents_count', label: t('problems.fields.relatedIncidents'), field: 'related_incidents_count', align: 'center' },
          { name: 'created_at', label: t('tickets.createdAt'), field: 'created_at', align: 'left', sortable: true },
          { name: 'actions', label: t('common.actions'), field: 'actions', align: 'center' },
        ]"
        row-key="id"
        :loading="loading"
        flat
        :rows-per-page-options="[15, 30, 50]"
        v-model:pagination="tablePagination"
        @request="onTableRequest"
        @row-click="(_evt: any, row: any) => goToDetail(row.id)"
        class="cursor-pointer"
      >
        <template #body-cell-title="props">
          <q-td :props="props">
            <div class="text-weight-medium">{{ props.row.title }}</div>
            <div v-if="props.row.is_known_error" class="text-caption text-red">
              <q-icon name="warning" size="xs" /> {{ t('problems.knownError') }}
            </div>
          </q-td>
        </template>

        <template #body-cell-status="props">
          <q-td :props="props">
            <q-chip dense :color="statusColor(props.row.status)" text-color="white" size="sm">
              {{ t(`problems.statuses.${props.row.status}`) }}
            </q-chip>
          </q-td>
        </template>

        <template #body-cell-priority="props">
          <q-td :props="props">
            <q-chip dense :color="priorityColor(props.row.priority)" text-color="white" size="sm">
              {{ t(`problems.priorities.${props.row.priority}`) }}
            </q-chip>
          </q-td>
        </template>

        <template #body-cell-impact="props">
          <q-td :props="props">
            <q-chip dense :color="impactColor(props.row.impact)" text-color="white" size="sm">
              {{ t(`problems.impacts.${props.row.impact}`) }}
            </q-chip>
          </q-td>
        </template>

        <template #body-cell-related_incidents_count="props">
          <q-td :props="props">
            <q-badge :color="props.row.related_incidents_count > 0 ? 'primary' : 'grey'" :label="props.row.related_incidents_count" />
          </q-td>
        </template>

        <template #body-cell-created_at="props">
          <q-td :props="props">
            {{ new Date(props.row.created_at).toLocaleDateString() }}
          </q-td>
        </template>

        <template #body-cell-actions="props">
          <q-td :props="props">
            <q-btn flat dense round icon="more_vert" @click.stop>
              <q-menu>
                <q-list dense>
                  <q-item clickable v-close-popup @click="goToDetail(props.row.id)">
                    <q-item-section avatar><q-icon name="visibility" /></q-item-section>
                    <q-item-section>{{ t('common.edit') }}</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="onDelete(props.row)">
                    <q-item-section avatar><q-icon name="delete" color="negative" /></q-item-section>
                    <q-item-section class="text-negative">{{ t('common.delete') }}</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>
          </q-td>
        </template>

        <template #no-data>
          <div class="full-width text-center q-pa-xl text-grey">
            <q-icon name="bug_report" size="48px" class="q-mb-sm" />
            <div>{{ t('problems.noProblems') }}</div>
          </div>
        </template>
      </q-table>

      <!-- Manual pagination -->
      <div class="row items-center justify-end q-pa-sm">
        <q-pagination
          v-model="currentPage"
          :max="totalPages"
          :max-pages="7"
          direction-links
          boundary-links
          @update:model-value="loadProblems"
        />
      </div>
    </q-card>
  </q-page>
</template>

