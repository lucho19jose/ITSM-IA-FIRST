<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Notify } from 'quasar'
import { getReport, executeReport, exportReportUrl } from '@/api/reports'
import type { SavedReport, ReportResultRow } from '@/types'
import { Bar, Line, Doughnut } from 'vue-chartjs'
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
const route = useRoute()
const router = useRouter()

const reportId = computed(() => Number(route.params.id))

const loading = ref(true)
const executing = ref(false)
const report = ref<SavedReport | null>(null)
const resultData = ref<ReportResultRow[]>([])
const resultSummary = ref<Record<string, any>>({})
const resultMeta = ref<{ query_time_ms: number; row_count: number } | null>(null)

const metricLabels: Record<string, string> = {
  count: 'Cantidad',
  avg_resolution_time: 'T. Resolucion Prom. (h)',
  avg_response_time: 'T. Respuesta Prom. (h)',
  sla_compliance_rate: 'Cumplimiento SLA (%)',
  avg_rating: 'Calificacion Prom.',
  total_time_spent: 'Tiempo Total (h)',
}

const chartColors = [
  '#1976D2', '#21BA45', '#F2C037', '#9C27B0', '#FF5722',
  '#00BCD4', '#795548', '#E91E63', '#607D8B', '#FF9800',
]

const chartData = computed(() => {
  if (!resultData.value.length || !report.value) return null

  const config = report.value.config
  const labels = resultData.value.map(r => r.group_label || 'Total')
  const primaryMetric = config.metrics[0] || 'count'
  const values = resultData.value.map(r => r[primaryMetric] ?? 0)

  if (config.chart_type === 'pie') {
    return {
      labels,
      datasets: [{
        data: values,
        backgroundColor: chartColors.slice(0, labels.length),
      }],
    }
  }

  return {
    labels,
    datasets: [{
      label: metricLabels[primaryMetric] || primaryMetric,
      data: values,
      backgroundColor: config.chart_type === 'line' ? 'rgba(25, 118, 210, 0.1)' : chartColors.slice(0, labels.length),
      borderColor: config.chart_type === 'line' ? '#1976D2' : undefined,
      fill: config.chart_type === 'line',
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

const tableColumns = computed(() => {
  if (!report.value) return []

  const cols: any[] = []
  if (resultData.value.some(r => r.group_label)) {
    cols.push({ name: 'group_label', label: 'Grupo', field: 'group_label', align: 'left', sortable: true })
  }

  for (const metric of report.value.config.metrics) {
    cols.push({
      name: metric,
      label: metricLabels[metric] || metric,
      field: metric,
      align: 'center',
      sortable: true,
      format: (val: any) => val !== null && val !== undefined ? val : '-',
    })
  }

  return cols
})

async function loadAndExecute() {
  executing.value = true
  try {
    const res = await executeReport(reportId.value)
    resultData.value = res.data.data
    resultSummary.value = res.data.summary
    resultMeta.value = res.data.meta
  } catch (e) {
    console.error('Execute failed:', e)
    Notify.create({ type: 'negative', message: 'Error al ejecutar el reporte' })
  } finally {
    executing.value = false
  }
}

function onExport() {
  const url = exportReportUrl(reportId.value)
  window.open(url, '_blank')
}

function onEdit() {
  router.push({ name: 'report-edit', params: { id: reportId.value } })
}

onMounted(async () => {
  try {
    const res = await getReport(reportId.value)
    report.value = res.data
    await loadAndExecute()
  } catch (e) {
    console.error('Failed to load report:', e)
    Notify.create({ type: 'negative', message: 'Error al cargar el reporte' })
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <q-page padding>
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <template v-else-if="report">
      <div class="row items-center q-mb-md">
        <q-btn flat round icon="arrow_back" @click="router.push({ name: 'report-list' })" />
        <div class="q-ml-sm">
          <div class="text-h5">{{ report.name }}</div>
          <div v-if="report.description" class="text-caption text-grey">{{ report.description }}</div>
        </div>
        <q-space />
        <q-btn flat icon="refresh" :label="t('reports.rerun')" no-caps :loading="executing" @click="loadAndExecute" class="q-mr-sm" />
        <q-btn flat icon="download" :label="t('reports.export')" no-caps @click="onExport" class="q-mr-sm" />
        <q-btn flat icon="edit" :label="t('common.edit')" no-caps @click="onEdit" />
      </div>

      <!-- Meta info -->
      <div v-if="resultMeta" class="text-caption text-grey q-mb-md">
        {{ resultMeta.row_count }} {{ t('reports.rows') }} &middot;
        {{ resultMeta.query_time_ms }}ms &middot;
        {{ t('reports.lastRun') }}: {{ report.last_run_at ? new Date(report.last_run_at).toLocaleString('es-PE') : '-' }}
      </div>

      <!-- Chart -->
      <q-card v-if="report.config.chart_type !== 'table' && chartData && resultData.length > 0" flat bordered class="q-mb-md">
        <q-card-section>
          <div style="height: 360px;">
            <Bar v-if="report.config.chart_type === 'bar'" :data="chartData" :options="chartOptions" />
            <Line v-else-if="report.config.chart_type === 'line'" :data="chartData" :options="chartOptions" />
            <Doughnut v-else-if="report.config.chart_type === 'pie'" :data="chartData" :options="{ ...chartOptions, cutout: '50%' }" />
          </div>
        </q-card-section>
      </q-card>

      <!-- Data Table -->
      <q-card flat bordered class="q-mb-md">
        <q-card-section>
          <q-table
            flat
            :rows="resultData"
            :columns="tableColumns"
            row-key="group_value"
            :loading="executing"
            :pagination="{ rowsPerPage: 50 }"
          />
        </q-card-section>
      </q-card>

      <!-- Summary -->
      <q-card v-if="Object.keys(resultSummary).length > 0" flat bordered>
        <q-card-section>
          <div class="text-subtitle2 text-weight-bold q-mb-sm">{{ t('reports.totals') }}</div>
          <div class="row q-gutter-lg">
            <div v-for="(val, key) in resultSummary" :key="String(key)" class="text-center">
              <div class="text-h5 text-primary">{{ val !== null && val !== undefined ? val : '-' }}</div>
              <div class="text-caption text-grey">{{ metricLabels[String(key)] || key }}</div>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </template>
  </q-page>
</template>
