<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { getAgentPerformance } from '@/api/dashboard'
import { getSurveyStats, type SurveyStats } from '@/api/satisfactionSurveys'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Tooltip,
  Legend,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)

interface AgentPerf {
  agent_name: string
  total_tickets: number
  resolved_tickets: number
  avg_resolution_minutes: number
}

const loading = ref(true)
const agentData = ref<AgentPerf[]>([])
const csatStats = ref<SurveyStats | null>(null)
const csatLoading = ref(true)

const columns = [
  { name: 'agent_name', label: 'Agente', field: 'agent_name', align: 'left' as const },
  { name: 'total_tickets', label: 'Total Tickets', field: 'total_tickets', align: 'center' as const },
  { name: 'resolved_tickets', label: 'Resueltos', field: 'resolved_tickets', align: 'center' as const },
  { name: 'resolution_rate', label: '% Resolucion', field: (row: AgentPerf) => row.total_tickets > 0 ? Math.round((row.resolved_tickets / row.total_tickets) * 100) + '%' : '0%', align: 'center' as const },
  { name: 'avg_resolution', label: 'T. Resolucion Prom.', field: 'avg_resolution_minutes', align: 'center' as const },
]

const ratingChartData = computed(() => {
  if (!csatStats.value) return null
  const dist = csatStats.value.rating_distribution
  return {
    labels: ['1 - Muy insatisfecho', '2 - Insatisfecho', '3 - Neutral', '4 - Satisfecho', '5 - Muy satisfecho'],
    datasets: [{
      label: 'Respuestas',
      data: [dist[1] || 0, dist[2] || 0, dist[3] || 0, dist[4] || 0, dist[5] || 0],
      backgroundColor: ['#f44336', '#ff9800', '#ffc107', '#8bc34a', '#4caf50'],
      borderRadius: 6,
    }],
  }
})

const ratingChartOptions = {
  responsive: true,
  indexAxis: 'y' as const,
  plugins: {
    legend: { display: false },
  },
  scales: {
    x: {
      beginAtZero: true,
      ticks: { stepSize: 1, precision: 0 },
    },
  },
}

onMounted(async () => {
  const promises: Promise<any>[] = [
    getAgentPerformance().then(res => { agentData.value = res.data }),
    getSurveyStats().then(res => { csatStats.value = res.data }).catch(() => {}),
  ]
  await Promise.allSettled(promises)
  loading.value = false
  csatLoading.value = false
})

function formatTime(minutes: number): string {
  if (minutes < 60) return `${minutes}m`
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return m > 0 ? `${h}h ${m}m` : `${h}h`
}
</script>

<template>
  <q-page padding>
    <div class="text-h5 q-mb-md">Reportes</div>

    <!-- CSAT Section -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-subtitle1 text-weight-medium q-mb-md">Satisfaccion del Cliente (CSAT)</div>

            <div v-if="csatLoading" class="flex flex-center q-pa-lg">
              <q-spinner-dots size="32px" color="primary" />
            </div>

            <div v-else-if="!csatStats || csatStats.total_surveys === 0" class="text-grey-6 q-pa-md text-center">
              No hay encuestas de satisfaccion registradas todavia.
            </div>

            <template v-else>
              <div class="row q-col-gutter-md q-mb-lg">
                <!-- Summary stats -->
                <div class="col-12 col-sm-3">
                  <div class="csat-metric-card">
                    <div class="csat-metric-value">
                      {{ csatStats.average_rating ? csatStats.average_rating.toFixed(1) : '-' }}
                      <span class="csat-star">&#9733;</span>
                    </div>
                    <div class="csat-metric-label">Calificacion promedio</div>
                  </div>
                </div>
                <div class="col-12 col-sm-3">
                  <div class="csat-metric-card">
                    <div class="csat-metric-value">{{ csatStats.response_rate }}%</div>
                    <div class="csat-metric-label">Tasa de respuesta</div>
                  </div>
                </div>
                <div class="col-12 col-sm-3">
                  <div class="csat-metric-card">
                    <div class="csat-metric-value">{{ csatStats.responded_surveys }}</div>
                    <div class="csat-metric-label">Encuestas respondidas</div>
                  </div>
                </div>
                <div class="col-12 col-sm-3">
                  <div class="csat-metric-card">
                    <div class="csat-metric-value">{{ csatStats.total_surveys }}</div>
                    <div class="csat-metric-label">Total encuestas enviadas</div>
                  </div>
                </div>
              </div>

              <!-- Rating distribution chart -->
              <div v-if="ratingChartData" style="max-width: 600px;">
                <div class="text-subtitle2 text-weight-bold q-mb-sm">Distribucion de calificaciones</div>
                <Bar :data="ratingChartData" :options="ratingChartOptions" />
              </div>
            </template>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Agent Performance -->
    <q-card flat bordered>
      <q-card-section>
        <div class="text-subtitle1 text-weight-medium q-mb-md">Rendimiento de Agentes</div>
        <q-table
          flat
          :rows="agentData"
          :columns="columns"
          row-key="agent_name"
          :loading="loading"
        >
          <template v-slot:body-cell-avg_resolution="props">
            <q-td :props="props">{{ formatTime(props.row.avg_resolution_minutes) }}</q-td>
          </template>
        </q-table>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<style scoped>
.csat-metric-card {
  text-align: center;
  padding: 16px;
  background: #f8f9fa;
  border-radius: 10px;
}

.csat-metric-value {
  font-size: 28px;
  font-weight: 700;
  color: #1a1a2e;
  line-height: 1.2;
}

.csat-star {
  color: #ffc107;
  font-size: 22px;
}

.csat-metric-label {
  font-size: 12px;
  color: #6b7280;
  margin-top: 4px;
}
</style>
