<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Notify } from 'quasar'
import { createReport, updateReport, getReport, previewReport, getAvailableFields } from '@/api/reports'
import { getCategories } from '@/api/categories'
import { getAgents } from '@/api/users'
import type { ReportConfig, ReportFilter, ReportResultRow, SavedReport } from '@/types'
import { Bar, Line, Pie, Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement,
  Filler,
} from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, PointElement, LineElement, Filler)

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

const editId = computed(() => route.params.id ? Number(route.params.id) : null)
const isEditing = computed(() => editId.value !== null)

const step = ref(1)
const loading = ref(false)
const saving = ref(false)
const previewLoading = ref(false)
const showSaveDialog = ref(false)

// Report config
const entity = ref<'tickets' | 'agents' | 'categories'>('tickets')
const filters = ref<ReportFilter[]>([])
const groupBy = ref<string | null>(null)
const selectedMetrics = ref<string[]>(['count'])
const chartType = ref<'bar' | 'line' | 'pie' | 'table'>('bar')
const dateRangeType = ref<string>('last_30_days')
const customDateStart = ref('')
const customDateEnd = ref('')

// Save form
const reportName = ref('')
const reportDescription = ref('')
const reportType = ref<string>('tickets')
const isShared = ref(false)

// Available fields
const availableFilters = ref<Array<{ field: string; label: string; type: string; operators: string[]; options?: string[] }>>([])
const availableMetrics = ref<Array<{ key: string; label: string; description: string }>>([])
const availableGroupings = ref<Array<{ key: string; label: string }>>([])

// Lookup data for filter values
const categoriesOptions = ref<Array<{ label: string; value: number }>>([])
const agentsOptions = ref<Array<{ label: string; value: number }>>([])

// Preview data
const previewData = ref<ReportResultRow[]>([])
const previewSummary = ref<Record<string, any>>({})
const previewMeta = ref<{ query_time_ms: number; row_count: number } | null>(null)

const dateRangeOptions = [
  { label: t('reports.dateRanges.last7days'), value: 'last_7_days' },
  { label: t('reports.dateRanges.last30days'), value: 'last_30_days' },
  { label: t('reports.dateRanges.last90days'), value: 'last_90_days' },
  { label: t('reports.dateRanges.thisMonth'), value: 'this_month' },
  { label: t('reports.dateRanges.lastMonth'), value: 'last_month' },
  { label: t('reports.dateRanges.thisYear'), value: 'this_year' },
  { label: t('reports.dateRanges.custom'), value: 'custom' },
]

const entityOptions = [
  { label: 'Tickets', value: 'tickets' },
  { label: t('reports.entities.agents'), value: 'agents' },
  { label: t('reports.entities.categories'), value: 'categories' },
]

const chartTypeOptions = [
  { label: t('reports.chartTypes.bar'), value: 'bar', icon: 'bar_chart' },
  { label: t('reports.chartTypes.line'), value: 'line', icon: 'show_chart' },
  { label: t('reports.chartTypes.pie'), value: 'pie', icon: 'pie_chart' },
  { label: t('reports.chartTypes.table'), value: 'table', icon: 'table_chart' },
]

const operatorLabels: Record<string, string> = {
  in: 'es',
  not_in: 'no es',
  '=': 'igual a',
  '!=': 'diferente de',
  is_null: 'esta vacio',
  is_not_null: 'no esta vacio',
}

function buildConfig(): ReportConfig {
  const dateRange = dateRangeType.value === 'custom'
    ? { type: 'custom' as const, start: customDateStart.value, end: customDateEnd.value }
    : { type: dateRangeType.value as any }

  const columns = ['group_label', ...selectedMetrics.value]

  return {
    entity: entity.value,
    filters: filters.value.filter(f => f.field && (f.operator === 'is_null' || f.operator === 'is_not_null' || (f.value !== null && f.value !== undefined))),
    group_by: groupBy.value,
    metrics: selectedMetrics.value,
    date_range: dateRange,
    chart_type: chartType.value,
    columns,
  }
}

async function loadAvailableFields() {
  try {
    const res = await getAvailableFields(entity.value)
    availableFilters.value = res.data.filters
    availableMetrics.value = res.data.metrics
    availableGroupings.value = res.data.groupings
  } catch (e) {
    console.error('Failed to load fields:', e)
  }
}

async function loadLookupData() {
  try {
    const [catRes, agentRes] = await Promise.all([
      getCategories().catch(() => ({ data: [] })),
      getAgents().catch(() => ({ data: [] })),
    ])
    categoriesOptions.value = (catRes.data || []).map((c: any) => ({ label: c.name, value: c.id }))
    agentsOptions.value = (agentRes.data || []).map((a: any) => ({ label: a.name, value: a.id }))
  } catch { /* ignore */ }
}

function addFilter() {
  filters.value.push({ field: '', operator: 'in', value: [] })
}

function removeFilter(index: number) {
  filters.value.splice(index, 1)
}

function getFilterOptions(field: string): Array<{ label: string; value: any }> {
  const filterDef = availableFilters.value.find(f => f.field === field)
  if (!filterDef) return []

  if (filterDef.options) {
    return filterDef.options.map(o => ({ label: o, value: o }))
  }

  if (field === 'category_id') return categoriesOptions.value
  if (field === 'assigned_to') return agentsOptions.value

  return []
}

function getFilterOperators(field: string): Array<{ label: string; value: string }> {
  const filterDef = availableFilters.value.find(f => f.field === field)
  if (!filterDef) return []
  return filterDef.operators.map(o => ({ label: operatorLabels[o] || o, value: o }))
}

async function runPreview() {
  previewLoading.value = true
  try {
    const config = buildConfig()
    const res = await previewReport(config)
    previewData.value = res.data.data
    previewSummary.value = res.data.summary
    previewMeta.value = res.data.meta
  } catch (e) {
    console.error('Preview failed:', e)
    Notify.create({ type: 'negative', message: 'Error al generar la vista previa' })
  } finally {
    previewLoading.value = false
  }
}

async function onSave() {
  if (!reportName.value.trim()) {
    Notify.create({ type: 'warning', message: t('reports.nameRequired') })
    return
  }

  saving.value = true
  try {
    const config = buildConfig()
    const payload: Partial<SavedReport> = {
      name: reportName.value,
      description: reportDescription.value || null,
      report_type: reportType.value as any,
      config,
      is_shared: isShared.value,
    }

    if (isEditing.value) {
      await updateReport(editId.value!, payload)
      Notify.create({ type: 'positive', message: t('reports.updated') })
    } else {
      await createReport(payload)
      Notify.create({ type: 'positive', message: t('reports.created') })
    }

    showSaveDialog.value = false
    router.push({ name: 'report-list' })
  } catch (e) {
    Notify.create({ type: 'negative', message: 'Error al guardar el reporte' })
  } finally {
    saving.value = false
  }
}

// Chart data
const chartData = computed(() => {
  if (!previewData.value.length) return null

  const labels = previewData.value.map(r => r.group_label || 'Total')
  const primaryMetric = selectedMetrics.value[0] || 'count'
  const values = previewData.value.map(r => r[primaryMetric] ?? 0)

  const colors = [
    '#1976D2', '#21BA45', '#F2C037', '#9C27B0', '#FF5722',
    '#00BCD4', '#795548', '#E91E63', '#607D8B', '#FF9800',
  ]

  if (chartType.value === 'pie') {
    return {
      labels,
      datasets: [{
        data: values,
        backgroundColor: colors.slice(0, labels.length),
      }],
    }
  }

  return {
    labels,
    datasets: [{
      label: availableMetrics.value.find(m => m.key === primaryMetric)?.label || primaryMetric,
      data: values,
      backgroundColor: chartType.value === 'line' ? 'rgba(25, 118, 210, 0.1)' : colors.slice(0, labels.length),
      borderColor: chartType.value === 'line' ? '#1976D2' : undefined,
      fill: chartType.value === 'line',
      tension: 0.3,
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom' as const },
  },
}

// Table columns for preview
const previewColumns = computed(() => {
  const cols: any[] = []

  if (previewData.value.some(r => r.group_label)) {
    cols.push({ name: 'group_label', label: 'Grupo', field: 'group_label', align: 'left' })
  }

  for (const metric of selectedMetrics.value) {
    const def = availableMetrics.value.find(m => m.key === metric)
    cols.push({
      name: metric,
      label: def?.label || metric,
      field: metric,
      align: 'center',
      format: (val: any) => val !== null && val !== undefined ? val : '-',
    })
  }

  return cols
})

// Watch entity change to reload fields
watch(entity, () => {
  loadAvailableFields()
  filters.value = []
  groupBy.value = null
})

onMounted(async () => {
  loading.value = true
  try {
    await Promise.all([loadAvailableFields(), loadLookupData()])

    // Load from template query param
    const tmplStr = route.query.template as string
    if (tmplStr) {
      try {
        const tmpl = JSON.parse(tmplStr)
        reportName.value = tmpl.name || ''
        reportDescription.value = tmpl.description || ''
        reportType.value = tmpl.report_type || 'tickets'
        if (tmpl.config) {
          entity.value = tmpl.config.entity || 'tickets'
          filters.value = tmpl.config.filters || []
          groupBy.value = tmpl.config.group_by || null
          selectedMetrics.value = tmpl.config.metrics || ['count']
          chartType.value = tmpl.config.chart_type || 'bar'
          if (tmpl.config.date_range) {
            dateRangeType.value = tmpl.config.date_range.type || 'last_30_days'
          }
          await loadAvailableFields()
          runPreview()
        }
      } catch { /* invalid template */ }
    }

    // Load existing report for editing
    if (isEditing.value) {
      const res = await getReport(editId.value!)
      const report = res.data
      reportName.value = report.name
      reportDescription.value = report.description || ''
      reportType.value = report.report_type
      isShared.value = report.is_shared
      if (report.config) {
        entity.value = report.config.entity || 'tickets'
        filters.value = report.config.filters || []
        groupBy.value = report.config.group_by || null
        selectedMetrics.value = report.config.metrics || ['count']
        chartType.value = report.config.chart_type || 'bar'
        if (report.config.date_range) {
          dateRangeType.value = report.config.date_range.type || 'last_30_days'
          if (report.config.date_range.type === 'custom') {
            customDateStart.value = report.config.date_range.start || ''
            customDateEnd.value = report.config.date_range.end || ''
          }
        }
      }
      await loadAvailableFields()
      runPreview()
    }
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <q-btn flat round icon="arrow_back" @click="router.push({ name: 'report-list' })" />
      <div class="text-h5 q-ml-sm">
        {{ isEditing ? t('reports.editReport') : t('reports.newReport') }}
      </div>
      <q-space />
      <q-btn flat color="primary" icon="visibility" :label="t('reports.preview')" no-caps :loading="previewLoading" @click="runPreview" class="q-mr-sm" />
      <q-btn color="primary" icon="save" :label="t('common.save')" no-caps @click="showSaveDialog = true" />
    </div>

    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <template v-else>
      <div class="row q-col-gutter-md">
        <!-- LEFT: Configuration Panel -->
        <div class="col-12 col-md-5 col-lg-4">
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle2 text-weight-bold q-mb-md">{{ t('reports.configuration') }}</div>

              <!-- Step 1: Entity -->
              <div class="q-mb-md">
                <div class="text-caption text-grey-7 q-mb-xs">1. {{ t('reports.selectEntity') }}</div>
                <q-btn-toggle
                  v-model="entity"
                  :options="entityOptions"
                  no-caps spread
                  toggle-color="primary"
                  class="full-width"
                />
              </div>

              <!-- Step 2: Date Range -->
              <div class="q-mb-md">
                <div class="text-caption text-grey-7 q-mb-xs">2. {{ t('reports.dateRange') }}</div>
                <q-select
                  v-model="dateRangeType"
                  :options="dateRangeOptions"
                  emit-value map-options
                  dense outlined
                />
                <div v-if="dateRangeType === 'custom'" class="row q-gutter-sm q-mt-xs">
                  <q-input v-model="customDateStart" type="date" dense outlined label="Desde" class="col" />
                  <q-input v-model="customDateEnd" type="date" dense outlined label="Hasta" class="col" />
                </div>
              </div>

              <!-- Step 3: Filters -->
              <div class="q-mb-md">
                <div class="row items-center q-mb-xs">
                  <div class="text-caption text-grey-7">3. {{ t('reports.filters') }}</div>
                  <q-space />
                  <q-btn flat dense size="sm" icon="add" :label="t('reports.addFilter')" no-caps @click="addFilter" />
                </div>

                <div v-for="(filter, idx) in filters" :key="idx" class="row items-center q-gutter-xs q-mb-xs">
                  <q-select
                    v-model="filter.field"
                    :options="availableFilters.map(f => ({ label: f.label, value: f.field }))"
                    emit-value map-options
                    dense outlined
                    class="col-4"
                    :label="t('reports.field')"
                  />
                  <q-select
                    v-model="filter.operator"
                    :options="getFilterOperators(filter.field)"
                    emit-value map-options
                    dense outlined
                    class="col-3"
                  />
                  <q-select
                    v-if="filter.operator !== 'is_null' && filter.operator !== 'is_not_null'"
                    v-model="filter.value"
                    :options="getFilterOptions(filter.field)"
                    emit-value map-options
                    dense outlined multiple
                    class="col"
                    use-chips
                  />
                  <q-btn flat round dense icon="close" size="sm" color="grey" @click="removeFilter(idx)" />
                </div>
              </div>

              <!-- Step 4: Group By -->
              <div class="q-mb-md">
                <div class="text-caption text-grey-7 q-mb-xs">4. {{ t('reports.groupBy') }}</div>
                <q-select
                  v-model="groupBy"
                  :options="[{ label: t('reports.noGrouping'), value: null }, ...availableGroupings.map(g => ({ label: g.label, value: g.key }))]"
                  emit-value map-options
                  dense outlined
                  clearable
                />
              </div>

              <!-- Step 5: Metrics -->
              <div class="q-mb-md">
                <div class="text-caption text-grey-7 q-mb-xs">5. {{ t('reports.metrics') }}</div>
                <div v-for="metric in availableMetrics" :key="metric.key">
                  <q-checkbox
                    v-model="selectedMetrics"
                    :val="metric.key"
                    :label="metric.label"
                    dense
                  />
                  <q-tooltip>{{ metric.description }}</q-tooltip>
                </div>
              </div>

              <!-- Step 6: Chart Type -->
              <div class="q-mb-sm">
                <div class="text-caption text-grey-7 q-mb-xs">6. {{ t('reports.chartType') }}</div>
                <q-btn-toggle
                  v-model="chartType"
                  :options="chartTypeOptions"
                  no-caps spread
                  toggle-color="primary"
                  class="full-width"
                />
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- RIGHT: Preview Panel -->
        <div class="col-12 col-md-7 col-lg-8">
          <q-card flat bordered class="full-height">
            <q-card-section>
              <div class="row items-center q-mb-md">
                <div class="text-subtitle2 text-weight-bold">{{ t('reports.preview') }}</div>
                <q-space />
                <div v-if="previewMeta" class="text-caption text-grey">
                  {{ previewMeta.row_count }} {{ t('reports.rows') }} &middot; {{ previewMeta.query_time_ms }}ms
                </div>
              </div>

              <div v-if="previewLoading" class="flex flex-center q-pa-xl">
                <q-spinner-dots size="30px" color="primary" />
              </div>

              <template v-else-if="previewData.length > 0">
                <!-- Chart -->
                <div v-if="chartType !== 'table' && chartData" style="height: 320px;" class="q-mb-md">
                  <Bar v-if="chartType === 'bar'" :data="chartData" :options="chartOptions" />
                  <Line v-else-if="chartType === 'line'" :data="chartData" :options="chartOptions" />
                  <Doughnut v-else-if="chartType === 'pie'" :data="chartData" :options="{ ...chartOptions, cutout: '50%' }" />
                </div>

                <!-- Data Table -->
                <q-table
                  flat dense
                  :rows="previewData"
                  :columns="previewColumns"
                  row-key="group_value"
                  :pagination="{ rowsPerPage: 20 }"
                  hide-bottom
                />

                <!-- Summary -->
                <div v-if="Object.keys(previewSummary).length > 0" class="q-mt-md q-pa-sm bg-grey-1 rounded-borders">
                  <div class="text-caption text-weight-bold q-mb-xs">{{ t('reports.totals') }}</div>
                  <div class="row q-gutter-md">
                    <div v-for="(val, key) in previewSummary" :key="String(key)">
                      <span class="text-caption text-grey-7">{{ availableMetrics.find(m => m.key === key)?.label || key }}:</span>
                      <span class="text-weight-bold q-ml-xs">{{ val !== null && val !== undefined ? val : '-' }}</span>
                    </div>
                  </div>
                </div>
              </template>

              <div v-else class="text-center q-pa-xl text-grey-5">
                <q-icon name="assessment" size="48px" class="q-mb-sm" />
                <div class="text-body2">{{ t('reports.clickPreview') }}</div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </template>

    <!-- Save Dialog -->
    <q-dialog v-model="showSaveDialog" persistent>
      <q-card style="width: 420px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">{{ isEditing ? t('reports.editReport') : t('reports.saveReport') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model="reportName"
            :label="t('reports.reportName')"
            outlined dense
            :rules="[val => !!val || t('reports.nameRequired')]"
            class="q-mb-sm"
          />
          <q-input
            v-model="reportDescription"
            :label="t('reports.reportDescription')"
            outlined dense
            type="textarea"
            rows="2"
            class="q-mb-sm"
          />
          <q-select
            v-model="reportType"
            :options="[
              { label: 'Tickets', value: 'tickets' },
              { label: 'Agentes', value: 'agents' },
              { label: 'SLA', value: 'sla' },
              { label: t('reports.entities.categories'), value: 'categories' },
              { label: t('reports.types.trends'), value: 'trends' },
              { label: 'Custom', value: 'custom' },
            ]"
            emit-value map-options
            outlined dense
            :label="t('reports.reportType')"
            class="q-mb-sm"
          />
          <q-toggle v-model="isShared" :label="t('reports.shareReport')" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" no-caps v-close-popup />
          <q-btn color="primary" :label="t('common.save')" no-caps :loading="saving" @click="onSave" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
