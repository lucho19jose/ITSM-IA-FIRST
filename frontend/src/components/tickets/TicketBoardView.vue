<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { quickUpdateTicket } from '@/api/tickets'
import { Notify } from 'quasar'
import type { Ticket } from '@/types'

const props = defineProps<{
  tickets: Ticket[]
  loading: boolean
}>()

const emit = defineEmits<{
  'ticket-updated': [ticket: Ticket]
}>()

const router = useRouter()
const draggingTicketId = ref<number | null>(null)
const dropTargetStatus = ref<string | null>(null)

const statusColumns = [
  { key: 'open', label: 'Abierto', color: '#e67e22' },
  { key: 'in_progress', label: 'En Progreso', color: '#2196f3' },
  { key: 'pending', label: 'Pendiente', color: '#9b59b6' },
  { key: 'resolved', label: 'Resuelto', color: '#27ae60' },
  { key: 'closed', label: 'Cerrado', color: '#7f8c8d' },
]

const ticketsByStatus = computed(() => {
  const map: Record<string, Ticket[]> = {}
  for (const col of statusColumns) {
    map[col.key] = props.tickets.filter(t => t.status === col.key)
  }
  return map
})

function getPriorityDot(priority: string): string {
  const colors: Record<string, string> = {
    low: '#4caf50', medium: '#2196f3', high: '#ff9800', urgent: '#f44336',
  }
  return colors[priority] || '#9e9e9e'
}

function getPriorityLabel(priority: string): string {
  const labels: Record<string, string> = {
    low: 'Baja', medium: 'Media', high: 'Alta', urgent: 'Urgente',
  }
  return labels[priority] || priority
}

function getStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    open: 'Abierto', in_progress: 'En Progreso', pending: 'Pendiente',
    resolved: 'Resuelto', closed: 'Cerrado',
  }
  return labels[status] || status
}

function getSlaText(ticket: Ticket): { text: string; overdue: boolean } | null {
  if (!ticket.resolution_due_at) return null
  if (ticket.status === 'resolved' || ticket.status === 'closed') {
    return { text: 'Resuelto a tiempo', overdue: false }
  }
  const diff = new Date(ticket.resolution_due_at).getTime() - Date.now()
  if (diff < 0) {
    const days = Math.abs(Math.floor(diff / 86400000))
    return { text: days > 0 ? `Atrasado ${days}d` : 'Atrasado', overdue: true }
  }
  const days = Math.floor(diff / 86400000)
  const hours = Math.floor((diff % 86400000) / 3600000)
  return { text: days > 0 ? `${days}d ${hours}h restantes` : `${hours}h restantes`, overdue: false }
}

function getAvatarColor(name?: string): string {
  if (!name) return '#9e9e9e'
  const colors = ['#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#009688', '#4caf50', '#ff9800', '#795548']
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  return colors[Math.abs(hash) % colors.length]
}

function goToTicket(ticket: Ticket) {
  router.push(`/tickets/${ticket.id}`)
}

// ─── Drag and Drop ──────────────────────────────────────────────────────────
function onDragStart(e: DragEvent, ticket: Ticket) {
  draggingTicketId.value = ticket.id
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', String(ticket.id))
  }
}

function onDragEnd() {
  draggingTicketId.value = null
  dropTargetStatus.value = null
}

function onDragOver(e: DragEvent, status: string) {
  e.preventDefault()
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
  dropTargetStatus.value = status
}

function onDragLeave(e: DragEvent, status: string) {
  // Only clear if leaving the column, not entering a child
  const relatedTarget = e.relatedTarget as HTMLElement
  const column = (e.currentTarget as HTMLElement)
  if (!column.contains(relatedTarget)) {
    if (dropTargetStatus.value === status) dropTargetStatus.value = null
  }
}

async function onDrop(e: DragEvent, newStatus: string) {
  e.preventDefault()
  dropTargetStatus.value = null
  const ticketId = draggingTicketId.value
  draggingTicketId.value = null

  if (!ticketId) return

  const ticket = props.tickets.find(t => t.id === ticketId)
  if (!ticket || ticket.status === newStatus) return

  try {
    const res = await quickUpdateTicket(ticketId, { status: newStatus })
    emit('ticket-updated', res.data)
    Notify.create({ type: 'positive', message: `Ticket movido a ${getStatusLabel(newStatus)}`, timeout: 2000 })
  } catch {
    Notify.create({ type: 'negative', message: 'Error al cambiar estado' })
  }
}
</script>

<template>
  <div class="board-container">
    <div
      v-for="col in statusColumns"
      :key="col.key"
      class="board-column"
      :class="{ 'drop-target': dropTargetStatus === col.key && draggingTicketId !== null }"
      @dragover="onDragOver($event, col.key)"
      @dragleave="onDragLeave($event, col.key)"
      @drop="onDrop($event, col.key)"
    >
      <!-- Column header -->
      <div class="column-header">
        <div class="column-header-bar" :style="{ backgroundColor: col.color }" />
        <span class="column-title">{{ col.label }}</span>
        <q-badge color="grey-4" text-color="grey-8" class="q-ml-sm">
          {{ ticketsByStatus[col.key]?.length || 0 }}
        </q-badge>
      </div>

      <!-- Cards -->
      <div class="column-cards">
        <div
          v-for="ticket in ticketsByStatus[col.key]"
          :key="ticket.id"
          class="board-card"
          :class="{ 'card-dragging': draggingTicketId === ticket.id }"
          draggable="true"
          @dragstart="onDragStart($event, ticket)"
          @dragend="onDragEnd"
          @click="goToTicket(ticket)"
        >
          <!-- SLA indicator -->
          <div class="card-top-row">
            <span class="card-ticket-number">{{ ticket.ticket_number }}</span>
            <template v-if="getSlaText(ticket)">
              <q-icon
                name="schedule"
                size="13px"
                :color="getSlaText(ticket)!.overdue ? 'red' : 'green'"
                class="q-ml-xs"
              />
              <span
                class="card-sla text-caption"
                :class="getSlaText(ticket)!.overdue ? 'text-red' : 'text-green'"
              >
                {{ getSlaText(ticket)!.text }}
              </span>
            </template>
          </div>

          <!-- Title -->
          <div class="card-title">{{ ticket.title }}</div>

          <!-- Bottom: priority + status + agent -->
          <div class="card-bottom-row">
            <div class="row items-center q-gutter-x-xs">
              <span class="priority-dot" :style="{ backgroundColor: getPriorityDot(ticket.priority) }" />
              <span class="text-caption">{{ getPriorityLabel(ticket.priority) }}</span>
              <q-badge
                :style="{ backgroundColor: col.color }"
                text-color="white"
                class="q-ml-xs"
                style="font-size: 10px;"
              >
                {{ getStatusLabel(ticket.status) }}
              </q-badge>
            </div>
            <q-avatar
              v-if="ticket.assignee"
              size="24px"
              :style="{ backgroundColor: getAvatarColor(ticket.assignee.name) }"
              text-color="white"
              font-size="11px"
            >
              <img v-if="ticket.assignee.avatar_url" :src="ticket.assignee.avatar_url" />
              <span v-else>{{ ticket.assignee.name.charAt(0).toUpperCase() }}</span>
              <q-tooltip>{{ ticket.assignee.name }}</q-tooltip>
            </q-avatar>
          </div>
        </div>

        <!-- Empty column -->
        <div v-if="!ticketsByStatus[col.key]?.length && !loading" class="card-empty text-grey-5 text-caption text-center q-pa-md">
          Sin tickets
        </div>
      </div>
    </div>

    <!-- Loading overlay -->
    <div v-if="loading" class="board-loading">
      <q-spinner-dots size="40px" color="primary" />
    </div>
  </div>
</template>

<style scoped>
.board-container {
  display: flex;
  gap: 12px;
  padding: 16px;
  overflow-x: auto;
  min-height: calc(100vh - 200px);
  align-items: flex-start;
  position: relative;
}

.board-column {
  flex: 1;
  min-width: 260px;
  max-width: 340px;
  background: #f8f9fa;
  border-radius: 8px;
  transition: background 0.2s, box-shadow 0.2s;
}

.board-column.drop-target {
  background: #e3f2fd;
  box-shadow: inset 0 0 0 2px #1976d2;
}

.body--dark .board-column {
  background: #1e1e1e;
}

.body--dark .board-column.drop-target {
  background: #1a237e;
  box-shadow: inset 0 0 0 2px #42a5f5;
}

.column-header {
  padding: 12px 14px 8px;
  display: flex;
  align-items: center;
  position: relative;
}

.column-header-bar {
  width: 4px;
  height: 18px;
  border-radius: 2px;
  margin-right: 8px;
  flex-shrink: 0;
}

.column-title {
  font-weight: 600;
  font-size: 14px;
}

.column-cards {
  padding: 4px 10px 10px;
  max-height: calc(100vh - 260px);
  overflow-y: auto;
}

.board-card {
  background: white;
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 8px;
  cursor: grab;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  transition: box-shadow 0.15s, opacity 0.15s, transform 0.15s;
  border: 1px solid #eee;
}

.board-card:hover {
  box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12);
  transform: translateY(-1px);
}

.board-card:active {
  cursor: grabbing;
}

.board-card.card-dragging {
  opacity: 0.4;
  transform: scale(0.95);
}

.body--dark .board-card {
  background: #2d2d2d;
  border-color: #444;
}

.card-top-row {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 6px;
}

.card-ticket-number {
  font-size: 12px;
  font-weight: 600;
  color: #1976d2;
}

.card-sla {
  font-size: 11px;
}

.card-title {
  font-size: 13px;
  font-weight: 500;
  line-height: 1.4;
  margin-bottom: 10px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-bottom-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.priority-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

.card-empty {
  padding: 32px 16px;
}

.board-loading {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.6);
  border-radius: 8px;
}

.body--dark .board-loading {
  background: rgba(0, 0, 0, 0.4);
}
</style>
