<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getAgentPerformance } from '@/api/dashboard'

interface AgentPerf {
  agent_name: string
  total_tickets: number
  resolved_tickets: number
  avg_resolution_minutes: number
}

const loading = ref(true)
const agentData = ref<AgentPerf[]>([])

const columns = [
  { name: 'agent_name', label: 'Agente', field: 'agent_name', align: 'left' as const },
  { name: 'total_tickets', label: 'Total Tickets', field: 'total_tickets', align: 'center' as const },
  { name: 'resolved_tickets', label: 'Resueltos', field: 'resolved_tickets', align: 'center' as const },
  { name: 'resolution_rate', label: '% Resolucion', field: (row: AgentPerf) => row.total_tickets > 0 ? Math.round((row.resolved_tickets / row.total_tickets) * 100) + '%' : '0%', align: 'center' as const },
  { name: 'avg_resolution', label: 'T. Resolucion Prom.', field: 'avg_resolution_minutes', align: 'center' as const },
]

onMounted(async () => {
  try {
    const res = await getAgentPerformance()
    agentData.value = res.data
  } finally {
    loading.value = false
  }
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
