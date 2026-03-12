<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePortalStore } from '@/stores/portal'
import { getTickets } from '@/api/tickets'
import type { Ticket } from '@/types'

const route = useRoute()
const router = useRouter()
const portal = usePortalStore()
const tenantSlug = route.params.tenantSlug as string

const tickets = ref<Ticket[]>([])
const loading = ref(true)
const filter = ref<string>('all')

const filteredTickets = computed(() => {
  if (filter.value === 'all') return tickets.value
  return tickets.value.filter(t => t.status === filter.value)
})

const statusLabels: Record<string, string> = {
  open: 'Abierto',
  in_progress: 'En Progreso',
  pending: 'Pendiente',
  resolved: 'Resuelto',
  closed: 'Cerrado',
}

const statusColors: Record<string, string> = {
  open: 'blue',
  in_progress: 'orange',
  pending: 'grey',
  resolved: 'green',
  closed: 'grey-6',
}

const priorityColors: Record<string, string> = {
  low: 'green',
  medium: 'blue',
  high: 'orange',
  urgent: 'red',
}

onMounted(async () => {
  try {
    const res = await getTickets({ per_page: 50 })
    tickets.value = res.data
  } catch {
    // handled by interceptor
  } finally {
    loading.value = false
  }
})

function viewTicket(id: number) {
  router.push(`/portal/${tenantSlug}/tickets/${id}`)
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('es-PE', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}
</script>

<template>
  <q-page class="portal-page">
    <div class="portal-container">
      <div class="row items-center justify-between q-mb-lg">
        <div class="text-h5 text-weight-bold">Mis Tickets</div>
        <q-btn
          color="primary"
          icon="add"
          label="Nuevo Ticket"
          no-caps
          :to="`/portal/${tenantSlug}/tickets/create`"
        />
      </div>

      <!-- Status filter tabs -->
      <q-tabs v-model="filter" dense class="q-mb-md" active-color="primary" indicator-color="primary" align="left" no-caps>
        <q-tab name="all" label="Todos" />
        <q-tab name="open" label="Abiertos" />
        <q-tab name="in_progress" label="En Progreso" />
        <q-tab name="pending" label="Pendientes" />
        <q-tab name="resolved" label="Resueltos" />
        <q-tab name="closed" label="Cerrados" />
      </q-tabs>

      <!-- Loading -->
      <div v-if="loading" class="text-center q-pa-xl">
        <q-spinner size="40px" color="primary" />
      </div>

      <!-- Empty state -->
      <div v-else-if="filteredTickets.length === 0" class="text-center q-pa-xl">
        <q-icon name="confirmation_number" size="64px" color="grey-4" />
        <div class="text-h6 text-grey-6 q-mt-md">No hay tickets</div>
        <div class="text-body2 text-grey-5 q-mt-xs">Crea un nuevo ticket para reportar un problema</div>
        <q-btn
          color="primary"
          label="Crear Ticket"
          class="q-mt-md"
          no-caps
          :to="`/portal/${tenantSlug}/tickets/create`"
        />
      </div>

      <!-- Ticket list -->
      <q-list v-else separator class="portal-ticket-list">
        <q-item
          v-for="ticket in filteredTickets"
          :key="ticket.id"
          clickable
          v-ripple
          @click="viewTicket(ticket.id)"
          class="portal-ticket-item"
        >
          <q-item-section>
            <q-item-label class="row items-center q-gutter-sm">
              <span class="text-grey-7 text-caption">{{ ticket.ticket_number }}</span>
              <q-badge :color="statusColors[ticket.status] || 'grey'" :label="statusLabels[ticket.status] || ticket.status" />
              <q-badge
                v-if="ticket.priority === 'high' || ticket.priority === 'urgent'"
                :color="priorityColors[ticket.priority]"
                :label="ticket.priority === 'urgent' ? 'Urgente' : 'Alta'"
                outline
              />
            </q-item-label>
            <q-item-label class="text-weight-medium q-mt-xs">{{ ticket.title }}</q-item-label>
            <q-item-label caption class="q-mt-xs">
              <span v-if="ticket.category">{{ ticket.category.name }} &middot; </span>
              Creado {{ formatDate(ticket.created_at) }}
              <span v-if="ticket.assignee"> &middot; Asignado a {{ ticket.assignee.name }}</span>
            </q-item-label>
          </q-item-section>
          <q-item-section side>
            <q-icon name="chevron_right" color="grey-5" />
          </q-item-section>
        </q-item>
      </q-list>
    </div>
  </q-page>
</template>

<style scoped>
.portal-page {
  background: #f5f5f5;
  min-height: calc(100vh - 50px);
}
.portal-container {
  max-width: 900px;
  margin: 0 auto;
  padding: 32px 24px;
}
.portal-ticket-list {
  background: white;
  border-radius: 8px;
  border: 1px solid #e0e0e0;
}
.portal-ticket-item {
  padding: 16px;
}
.body--dark .portal-page {
  background: #121212;
}
.body--dark .portal-ticket-list {
  background: #1e1e1e;
  border-color: #404040;
}
</style>
