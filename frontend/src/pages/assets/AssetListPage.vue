<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import { getAssets, getAssetDashboard, deleteAsset, exportAssets } from '@/api/assets'
import { getAssetTypes } from '@/api/assetTypes'
import { getAgents } from '@/api/users'
import { getDepartments } from '@/api/departments'
import type { Asset, AssetType, User, Department } from '@/types'

const { t } = useI18n()
const router = useRouter()
const $q = useQuasar()
const auth = useAuthStore()

const loading = ref(false)
const assets = ref<Asset[]>([])
const assetTypes = ref<AssetType[]>([])
const agents = ref<User[]>([])
const departments = ref<Department[]>([])

// Dashboard stats
const stats = ref({ total: 0, active: 0, maintenance: 0, retired: 0, expiring_warranties: 0, by_status: {} as Record<string, number>, by_type: {} as Record<string, number> })

// Pagination
const currentPage = ref(1)
const perPage = ref(15)
const totalItems = ref(0)
const totalPages = computed(() => Math.ceil(totalItems.value / perPage.value) || 1)

// Filters
const search = ref('')
const filterType = ref<number | null>(null)
const filterStatus = ref<string | null>(null)
const filterAssignedTo = ref<number | null>(null)
const filterDepartment = ref<number | null>(null)

const statusOptions = [
  { label: t('assets.statuses.active'), value: 'active', color: 'green' },
  { label: t('assets.statuses.inactive'), value: 'inactive', color: 'grey' },
  { label: t('assets.statuses.maintenance'), value: 'maintenance', color: 'orange' },
  { label: t('assets.statuses.retired'), value: 'retired', color: 'blue-grey' },
  { label: t('assets.statuses.lost'), value: 'lost', color: 'red' },
  { label: t('assets.statuses.disposed'), value: 'disposed', color: 'brown' },
]

const statusColorMap: Record<string, string> = {
  active: 'green', inactive: 'grey', maintenance: 'orange',
  retired: 'blue-grey', lost: 'red', disposed: 'brown',
}

const conditionColorMap: Record<string, string> = {
  new: 'green', good: 'teal', fair: 'amber', poor: 'orange', broken: 'red',
}

async function loadAssets() {
  loading.value = true
  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: perPage.value,
    }
    if (search.value) params.search = search.value
    if (filterType.value) params.asset_type_id = filterType.value
    if (filterStatus.value) params.status = filterStatus.value
    if (filterAssignedTo.value) params.assigned_to = filterAssignedTo.value
    if (filterDepartment.value) params.department_id = filterDepartment.value

    const res = await getAssets(params)
    assets.value = res.data
    totalItems.value = res.meta.total
  } catch {
    $q.notify({ type: 'negative', message: t('assets.loadError') })
  } finally {
    loading.value = false
  }
}

async function loadDashboard() {
  try {
    const res = await getAssetDashboard()
    stats.value = res.data
  } catch { /* ignore */ }
}

async function loadFilters() {
  try {
    const [typesRes, agentsRes, deptsRes] = await Promise.all([
      getAssetTypes(),
      getAgents(),
      getDepartments(),
    ])
    assetTypes.value = typesRes.data
    agents.value = agentsRes.data
    departments.value = deptsRes.data
  } catch { /* ignore */ }
}

function onSearch() {
  currentPage.value = 1
  loadAssets()
}

function clearFilters() {
  search.value = ''
  filterType.value = null
  filterStatus.value = null
  filterAssignedTo.value = null
  filterDepartment.value = null
  currentPage.value = 1
  loadAssets()
}

function onPageChange(page: number) {
  currentPage.value = page
  loadAssets()
}

function goToDetail(asset: Asset) {
  router.push(`/assets/${asset.id}`)
}

function goToCreate() {
  router.push('/assets/create')
}

async function onDelete(asset: Asset) {
  $q.dialog({
    title: t('common.confirm'),
    message: t('assets.confirmDelete', { name: asset.name }),
    cancel: true,
  }).onOk(async () => {
    try {
      await deleteAsset(asset.id)
      $q.notify({ type: 'positive', message: t('assets.deleted') })
      loadAssets()
      loadDashboard()
    } catch {
      $q.notify({ type: 'negative', message: t('assets.deleteError') })
    }
  })
}

async function onExport() {
  try {
    const params: Record<string, any> = {}
    if (filterStatus.value) params.status = filterStatus.value
    if (filterType.value) params.asset_type_id = filterType.value

    const res = await exportAssets(params)
    const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `assets_export_${new Date().toISOString().slice(0, 10)}.csv`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch {
    $q.notify({ type: 'negative', message: 'Error al exportar' })
  }
}

function warrantyStatus(asset: Asset): { label: string; color: string } {
  if (!asset.warranty_expiry) return { label: t('assets.noWarranty'), color: 'grey' }
  const expiry = new Date(asset.warranty_expiry)
  const now = new Date()
  const daysLeft = Math.ceil((expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
  if (daysLeft < 0) return { label: t('assets.warrantyExpired'), color: 'red' }
  if (daysLeft <= 30) return { label: t('assets.warrantyExpiringSoon'), color: 'orange' }
  return { label: t('assets.warrantyActive'), color: 'green' }
}

onMounted(() => {
  loadAssets()
  loadDashboard()
  loadFilters()
})
</script>

<template>
  <q-page padding>
    <!-- Header -->
    <div class="row items-center q-mb-md">
      <div class="col">
        <div class="text-h5 text-weight-bold">{{ t('assets.title') }}</div>
        <div class="text-caption text-grey">{{ t('assets.subtitle') }}</div>
      </div>
      <div class="col-auto q-gutter-sm">
        <q-btn outline color="primary" icon="download" :label="t('ticketList.export')" no-caps @click="onExport" />
        <q-btn color="primary" icon="add" :label="t('assets.create')" no-caps @click="goToCreate" />
      </div>
    </div>

    <!-- Dashboard Cards -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-caption text-grey">{{ t('assets.totalAssets') }}</div>
            <div class="text-h4 text-weight-bold">{{ stats.total }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-caption text-grey">{{ t('assets.statuses.active') }}</div>
            <div class="text-h4 text-weight-bold text-green">{{ stats.active }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-caption text-grey">{{ t('assets.statuses.maintenance') }}</div>
            <div class="text-h4 text-weight-bold text-orange">{{ stats.maintenance }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-caption text-grey">{{ t('assets.expiringWarranties') }}</div>
            <div class="text-h4 text-weight-bold text-red">{{ stats.expiring_warranties }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Filters -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="q-pa-sm">
        <div class="row q-col-gutter-sm items-center">
          <div class="col-12 col-sm-4 col-md-3">
            <q-input
              v-model="search"
              :placeholder="t('common.search')"
              dense outlined
              @keyup.enter="onSearch"
            >
              <template v-slot:prepend><q-icon name="search" size="18px" /></template>
              <template v-slot:append>
                <q-icon v-if="search" name="close" class="cursor-pointer" @click="search = ''; onSearch()" size="18px" />
              </template>
            </q-input>
          </div>
          <div class="col-6 col-sm-2">
            <q-select
              v-model="filterType"
              :options="assetTypes"
              option-value="id"
              option-label="name"
              emit-value map-options
              :label="t('assets.type')"
              dense outlined clearable
              @update:model-value="onSearch"
            />
          </div>
          <div class="col-6 col-sm-2">
            <q-select
              v-model="filterStatus"
              :options="statusOptions"
              option-value="value"
              option-label="label"
              emit-value map-options
              :label="t('common.status')"
              dense outlined clearable
              @update:model-value="onSearch"
            />
          </div>
          <div class="col-6 col-sm-2">
            <q-select
              v-model="filterAssignedTo"
              :options="agents"
              option-value="id"
              option-label="name"
              emit-value map-options
              :label="t('tickets.assignedTo')"
              dense outlined clearable
              @update:model-value="onSearch"
            />
          </div>
          <div class="col-6 col-sm-2">
            <q-select
              v-model="filterDepartment"
              :options="departments"
              option-value="id"
              option-label="name"
              emit-value map-options
              :label="t('ticketForm.department')"
              dense outlined clearable
              @update:model-value="onSearch"
            />
          </div>
          <div class="col-auto">
            <q-btn flat dense icon="filter_alt_off" @click="clearFilters">
              <q-tooltip>{{ t('ticketList.clearFilters') }}</q-tooltip>
            </q-btn>
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Table -->
    <q-card flat bordered>
      <q-table
        :rows="assets"
        :columns="[
          { name: 'asset_tag', label: t('assets.assetTag'), field: 'asset_tag', align: 'left', sortable: true },
          { name: 'name', label: t('assets.name'), field: 'name', align: 'left', sortable: true },
          { name: 'type', label: t('assets.type'), field: (row: any) => row.asset_type?.name, align: 'left' },
          { name: 'status', label: t('common.status'), field: 'status', align: 'center' },
          { name: 'condition', label: t('assets.condition'), field: 'condition', align: 'center' },
          { name: 'assignee', label: t('tickets.assignedTo'), field: (row: any) => row.assignee?.name, align: 'left' },
          { name: 'location', label: t('assets.location'), field: 'location', align: 'left' },
          { name: 'warranty', label: t('assets.warranty'), field: 'warranty_expiry', align: 'center' },
          { name: 'actions', label: t('common.actions'), field: 'actions', align: 'center' },
        ]"
        row-key="id"
        :loading="loading"
        flat
        hide-pagination
        :rows-per-page-options="[0]"
        @row-click="(_e: any, row: Asset) => goToDetail(row)"
        class="cursor-pointer"
      >
        <template v-slot:body-cell-asset_tag="props">
          <q-td :props="props">
            <span class="text-weight-medium text-primary">{{ props.row.asset_tag }}</span>
          </q-td>
        </template>

        <template v-slot:body-cell-type="props">
          <q-td :props="props">
            <q-chip
              v-if="props.row.asset_type"
              :icon="props.row.asset_type.icon || 'devices'"
              size="sm"
              color="blue-1"
              text-color="blue-9"
              dense
            >
              {{ props.row.asset_type.name }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-status="props">
          <q-td :props="props">
            <q-badge :color="statusColorMap[props.row.status] || 'grey'" :label="t(`assets.statuses.${props.row.status}`)" />
          </q-td>
        </template>

        <template v-slot:body-cell-condition="props">
          <q-td :props="props">
            <q-badge :color="conditionColorMap[props.row.condition] || 'grey'" :label="t(`assets.conditions.${props.row.condition}`)" />
          </q-td>
        </template>

        <template v-slot:body-cell-assignee="props">
          <q-td :props="props">
            <div v-if="props.row.assignee" class="row items-center no-wrap q-gutter-xs">
              <q-avatar size="22px" color="primary" text-color="white" font-size="10px">
                {{ props.row.assignee.name?.charAt(0)?.toUpperCase() }}
              </q-avatar>
              <span>{{ props.row.assignee.name }}</span>
            </div>
            <span v-else class="text-grey">-</span>
          </q-td>
        </template>

        <template v-slot:body-cell-warranty="props">
          <q-td :props="props">
            <q-badge
              :color="warrantyStatus(props.row).color"
              :label="warrantyStatus(props.row).label"
            />
          </q-td>
        </template>

        <template v-slot:body-cell-actions="props">
          <q-td :props="props" @click.stop>
            <q-btn flat round dense icon="more_vert" size="sm">
              <q-menu>
                <q-list dense>
                  <q-item clickable v-close-popup @click="goToDetail(props.row)">
                    <q-item-section side><q-icon name="visibility" size="18px" /></q-item-section>
                    <q-item-section>{{ t('common.edit') }}</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="onDelete(props.row)" class="text-negative">
                    <q-item-section side><q-icon name="delete" size="18px" color="negative" /></q-item-section>
                    <q-item-section>{{ t('common.delete') }}</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>
          </q-td>
        </template>

        <template v-slot:no-data>
          <div class="full-width text-center q-pa-xl text-grey">
            <q-icon name="inventory_2" size="48px" class="q-mb-sm" />
            <div>{{ t('assets.noAssets') }}</div>
          </div>
        </template>
      </q-table>

      <!-- Pagination -->
      <q-separator />
      <div class="row items-center justify-between q-px-md q-py-sm">
        <div class="text-caption text-grey">
          {{ t('ticketList.showing') }} {{ assets.length ? ((currentPage - 1) * perPage + 1) : 0 }}–{{ Math.min(currentPage * perPage, totalItems) }} {{ t('ticketList.of') }} {{ totalItems }}
        </div>
        <q-pagination
          v-model="currentPage"
          :max="totalPages"
          :max-pages="7"
          direction-links
          boundary-links
          @update:model-value="onPageChange"
        />
      </div>
    </q-card>
  </q-page>
</template>
