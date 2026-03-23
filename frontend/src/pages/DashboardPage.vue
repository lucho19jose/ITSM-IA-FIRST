<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { startDashboardTour } from '@/composables/useOnboarding'
import {
  getDashboardSummary,
  getTicketsByStatus,
  getTicketsByPriority,
  getTrends,
  type DashboardSummary,
} from '@/api/dashboard'
import { getTickets } from '@/api/tickets'
import type { Ticket } from '@/types'
import { Doughnut, Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
} from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, PointElement, LineElement, Filler)

const { t } = useI18n()
const auth = useAuthStore()

const loading = ref(true)
const summary = ref<DashboardSummary>({
  total_tickets: 0,
  open_tickets: 0,
  in_progress_tickets: 0,
  pending_tickets: 0,
  resolved_today: 0,
  overdue_tickets: 0,
  due_today: 0,
  unassigned_tickets: 0,
  avg_response_time: 0,
  sla_compliance: 100,
  csat_average: null,
  csat_response_rate: 0,
  csat_total_surveys: 0,
})
const recentTickets = ref<Ticket[]>([])
const statusChartData = ref<any>(null)
const priorityChartData = ref<any>(null)
const trendsChartData = ref<any>(null)

const statusColors: Record<string, string> = {
  open: '#F2C037',
  in_progress: '#1976D2',
  pending: '#9C27B0',
  resolved: '#21BA45',
  closed: '#616161',
}

const priorityColors: Record<string, string> = {
  low: '#4caf50',
  medium: '#2196f3',
  high: '#ff9800',
  urgent: '#f44336',
}

onMounted(async () => {
  try {
    const [summaryRes, statusRes, priorityRes, trendsRes, ticketsRes] = await Promise.all([
      getDashboardSummary(),
      getTicketsByStatus(),
      getTicketsByPriority(),
      getTrends(),
      getTickets({ per_page: 5, sort: 'created_at', direction: 'desc' }),
    ])

    summary.value = summaryRes.data
    recentTickets.value = ticketsRes.data

    const statusData = statusRes.data
    statusChartData.value = {
      labels: Object.keys(statusData).map(s => t(`tickets.statuses.${s}`)),
      datasets: [{
        data: Object.values(statusData),
        backgroundColor: Object.keys(statusData).map(s => statusColors[s] || '#616161'),
      }],
    }

    const priorityData = priorityRes.data
    priorityChartData.value = {
      labels: Object.keys(priorityData).map(p => t(`tickets.priorities.${p}`)),
      datasets: [{
        data: Object.values(priorityData),
        backgroundColor: Object.keys(priorityData).map(p => priorityColors[p] || '#616161'),
      }],
    }

    const trends = trendsRes.data
    trendsChartData.value = {
      labels: trends.map(item => item.date),
      datasets: [
        {
          label: 'Creados',
          data: trends.map(item => item.created),
          borderColor: '#1976D2',
          backgroundColor: 'rgba(25, 118, 210, 0.1)',
          fill: true,
          tension: 0.3,
        },
        {
          label: 'Resueltos',
          data: trends.map(item => item.resolved),
          borderColor: '#21BA45',
          backgroundColor: 'rgba(33, 186, 69, 0.1)',
          fill: true,
          tension: 0.3,
        },
      ],
    }
  } catch (e) {
    console.error('Dashboard load error:', e)
  } finally {
    loading.value = false
  }
  startDashboardTour()
})

// Freshservice-style top stat cards
const freshStatCards = [
  { key: 'overdue_tickets', label: 'Tickets atrasados', color: '#e74c3c' },
  { key: 'due_today', label: 'Tickets que vencen hoy', color: '#f39c12' },
  { key: 'open_tickets', label: 'Tickets abiertos', color: '#3498db' },
  { key: 'pending_tickets', label: 'Tickets en espera', color: '#e67e22' },
  { key: 'unassigned_tickets', label: 'Tickets sin asignar', color: '#95a5a6' },
  { key: 'resolved_today', label: 'Resueltos hoy', color: '#27ae60' },
]

function formatMinutes(minutes: number): string {
  if (minutes < 60) return `${minutes}m`
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return m > 0 ? `${h}h ${m}m` : `${h}h`
}

function getPriorityColor(priority: string): string {
  return priorityColors[priority] || 'grey'
}

function getStatusColor(status: string): string {
  const colors: Record<string, string> = {
    open: 'warning', in_progress: 'primary', pending: 'purple',
    resolved: 'positive', closed: 'grey',
  }
  return colors[status] || 'grey'
}

const doughnutOptions = {
  responsive: true,
  cutout: '65%',
  plugins: {
    legend: { position: 'bottom' as const, labels: { padding: 16, usePointStyle: true } },
  },
}

// Suppress unused variable warning
void auth
</script>

<template>
  <q-page padding>
    <div class="text-h5 q-mb-md">{{ t('dashboard.title') }}</div>

    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <template v-else>
      <!-- Freshservice-style Stat Cards Row -->
      <div class="row q-col-gutter-sm q-mb-lg">
        <div v-for="card in freshStatCards" :key="card.key" class="col-12 col-sm-4 col-md-2">
          <q-card flat bordered class="stat-card" :class="{ 'stat-card-alert': (summary as any)[card.key] > 0 && (card.key === 'overdue_tickets' || card.key === 'due_today') }">
            <q-card-section class="q-pa-md">
              <div class="stat-label">{{ card.label }}</div>
              <div class="stat-number" :style="{ color: card.color }">
                {{ (summary as any)[card.key] }}
              </div>
            </q-card-section>
            <div class="stat-border-bottom" :style="{ backgroundColor: card.color }" />
          </q-card>
        </div>
      </div>

      <!-- Charts Row (Freshservice donut charts + trend line) -->
      <div class="row q-col-gutter-md q-mb-lg">
        <div class="col-12 col-md-4">
          <q-card flat bordered>
            <q-card-section>
              <div class="row items-center q-mb-sm">
                <div class="text-subtitle2 text-weight-bold">Tickets sin solucionar por prioridad</div>
                <q-space />
                <q-btn flat round dense icon="more_vert" size="sm" color="grey-6" />
              </div>
              <div style="max-width: 280px; margin: 0 auto;">
                <Doughnut v-if="priorityChartData" :data="priorityChartData" :options="doughnutOptions" />
              </div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-md-4">
          <q-card flat bordered>
            <q-card-section>
              <div class="row items-center q-mb-sm">
                <div class="text-subtitle2 text-weight-bold">Tickets sin solucionar por estado</div>
                <q-space />
                <q-btn flat round dense icon="more_vert" size="sm" color="grey-6" />
              </div>
              <div style="max-width: 280px; margin: 0 auto;">
                <Doughnut v-if="statusChartData" :data="statusChartData" :options="doughnutOptions" />
              </div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-md-4">
          <q-card flat bordered>
            <q-card-section>
              <div class="row items-center q-mb-sm">
                <div class="text-subtitle2 text-weight-bold">{{ t('dashboard.trends') }}</div>
              </div>
              <Line v-if="trendsChartData" :data="trendsChartData" :options="{ responsive: true, plugins: { legend: { position: 'bottom' as const } }, scales: { x: { display: false } } }" />
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Bottom row: KPIs + Recent Tickets -->
      <div class="row q-col-gutter-md q-mb-lg">
        <!-- SLA & Performance KPIs -->
        <div class="col-12 col-md-4">
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle2 text-weight-bold q-mb-md">Metricas de rendimiento</div>
              <div class="kpi-row">
                <div class="kpi-item">
                  <q-icon name="speed" color="info" size="28px" />
                  <div class="kpi-value">{{ summary.sla_compliance }}%</div>
                  <div class="kpi-label">{{ t('dashboard.slaCompliance') }}</div>
                </div>
                <div class="kpi-item">
                  <q-icon name="timer" color="accent" size="28px" />
                  <div class="kpi-value">{{ formatMinutes(summary.avg_response_time) }}</div>
                  <div class="kpi-label">{{ t('dashboard.avgResponseTime') }}</div>
                </div>
                <div class="kpi-item">
                  <q-icon name="trending_up" color="positive" size="28px" />
                  <div class="kpi-value">{{ summary.resolved_today }}</div>
                  <div class="kpi-label">{{ t('dashboard.resolvedToday') }}</div>
                </div>
                <div v-if="summary.csat_total_surveys > 0" class="kpi-item">
                  <q-icon name="sentiment_satisfied" color="warning" size="28px" />
                  <div class="kpi-value">
                    {{ summary.csat_average ? summary.csat_average.toFixed(1) : '-' }}
                    <span style="font-size: 14px; color: #ffc107;">&#9733;</span>
                  </div>
                  <div class="kpi-label">{{ t('dashboard.csatAverage') }} ({{ summary.csat_response_rate }}% {{ t('dashboard.responseRate') }})</div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Recent Tickets -->
        <div class="col-12 col-md-8">
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle2 text-weight-bold q-mb-sm">{{ t('dashboard.recentTickets') }}</div>
              <q-list separator>
                <q-item v-for="ticket in recentTickets" :key="ticket.id" clickable :to="`/tickets/${ticket.id}`" dense>
                  <q-item-section side>
                    <q-badge :color="getStatusColor(ticket.status)" style="font-size: 10px;">
                      {{ t(`tickets.statuses.${ticket.status}`) }}
                    </q-badge>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>{{ ticket.title }}</q-item-label>
                    <q-item-label caption>{{ ticket.ticket_number }} · {{ ticket.requester?.name }}</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <q-badge outline :style="{ color: getPriorityColor(ticket.priority), borderColor: getPriorityColor(ticket.priority) }">
                      {{ t(`tickets.priorities.${ticket.priority}`) }}
                    </q-badge>
                  </q-item-section>
                </q-item>
                <q-item v-if="recentTickets.length === 0">
                  <q-item-section class="text-grey text-center">{{ t('common.noResults') }}</q-item-section>
                </q-item>
              </q-list>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </template>
  </q-page>
</template>

<style scoped>
/* Freshservice stat cards */
.stat-card {
  position: relative;
  overflow: hidden;
  transition: box-shadow 0.2s;
}
.stat-card:hover {
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}
.stat-card-alert {
  background: #fef5f5;
}
.stat-label {
  font-size: 13px;
  color: #6b7280;
  font-weight: 500;
  margin-bottom: 8px;
  line-height: 1.3;
}
.stat-number {
  font-size: 32px;
  font-weight: 700;
  line-height: 1;
}
.stat-border-bottom {
  height: 3px;
  width: 100%;
}

/* KPI metrics */
.kpi-row {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.kpi-item {
  display: flex;
  align-items: center;
  gap: 12px;
}
.kpi-value {
  font-size: 22px;
  font-weight: 700;
  color: #1a1a2e;
  min-width: 60px;
}
.kpi-label {
  font-size: 12px;
  color: #6b7280;
}
</style>
