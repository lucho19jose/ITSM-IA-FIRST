<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import { startTicketListTour } from '@/composables/useOnboarding'
import { getTickets, quickUpdateTicket, bulkUpdateTickets, exportTickets } from '@/api/tickets'
import { getCategories } from '@/api/categories'
import { getAgents } from '@/api/users'
import { getDepartments } from '@/api/departments'
import TicketViewsSidebar from '@/components/tickets/TicketViewsSidebar.vue'
import TicketBoardView from '@/components/tickets/TicketBoardView.vue'
import type { TicketView } from '@/components/tickets/TicketViewsSidebar.vue'
import type { Ticket, Category, User, Department } from '@/types'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const $q = useQuasar()
const auth = useAuthStore()

// --- Views Sidebar ---
const showViewsSidebar = ref(false)
const currentViewId = ref('unresolved')
const currentViewLabel = ref('Todos los tickets sin resolver')

function onSelectView(view: TicketView) {
  currentViewId.value = view.id
  currentViewLabel.value = view.label
  // Reset all filters first
  filters.value = {
    search: '',
    statuses: [],
    priorities: [],
    types: [],
    category_id: null,
    source: null,
    assigned_to: null,
    requester_id: null,
    requester_name: '',
    created_from: '',
    created_to: '',
  }
  // Build API params from view filters
  viewFilters.value = { ...view.filters }
  // Handle special __me__ token for requester_id
  if (viewFilters.value.requester_id === '__me__') {
    viewFilters.value.requester_id = auth.user?.id
  }
  currentPage.value = 1
  loadTickets()
}

// Extra filter params from selected view (sent directly to API)
const viewFilters = ref<Record<string, any>>({
  status_not_in: 'resolved,closed',
})

// --- View Mode ---
const viewMode = ref<'list' | 'board'>(
  (localStorage.getItem('autoservice_ticket_view') as 'list' | 'board') || 'list'
)
function setViewMode(mode: 'list' | 'board') {
  viewMode.value = mode
  localStorage.setItem('autoservice_ticket_view', mode)
  // For board view, load all tickets (no pagination limit)
  if (mode === 'board') {
    perPage.value = 200
    currentPage.value = 1
    loadTickets()
  } else {
    perPage.value = 15
    currentPage.value = 1
    loadTickets()
  }
}

function onBoardTicketUpdated(updatedTicket: Ticket) {
  const idx = tickets.value.findIndex(t => t.id === updatedTicket.id)
  if (idx !== -1) {
    tickets.value[idx] = { ...tickets.value[idx], ...updatedTicket }
  }
}

// --- State ---
const loading = ref(false)
const tickets = ref<Ticket[]>([])
const categories = ref<Category[]>([])
const agents = ref<User[]>([])
const departments = ref<Department[]>([])

// Pagination
const currentPage = ref(1)
const perPage = ref(15)
const totalItems = ref(0)
const totalPages = computed(() => Math.ceil(totalItems.value / perPage.value) || 1)
const paginationFrom = computed(() => totalItems.value === 0 ? 0 : (currentPage.value - 1) * perPage.value + 1)
const paginationTo = computed(() => Math.min(currentPage.value * perPage.value, totalItems.value))

// Sort
const sortField = ref<string>('created_at')
const sortDirection = ref<'desc' | 'asc'>('desc')
const sortOptions = [
  { label: t('ticketList.createdAt'), value: 'created_at' },
  { label: t('common.priority'), value: 'priority' },
  { label: t('common.status'), value: 'status' },
  { label: t('ticketList.updatedAt'), value: 'updated_at' },
]

// Selection
const selectedIds = ref<number[]>([])
const selectAll = computed({
  get: () => tickets.value.length > 0 && selectedIds.value.length === tickets.value.length,
  set: (val: boolean) => {
    selectedIds.value = val ? tickets.value.map(t => t.id) : []
  },
})
const selectAllIndeterminate = computed(() =>
  selectedIds.value.length > 0 && selectedIds.value.length < tickets.value.length
)

function toggleTicketSelection(id: number) {
  const idx = selectedIds.value.indexOf(id)
  if (idx >= 0) selectedIds.value.splice(idx, 1)
  else selectedIds.value.push(id)
}

// Filters
const showFilters = ref(false)
const filterTab = ref('basic')
const filters = ref({
  search: '',
  statuses: [] as string[],
  priorities: [] as string[],
  types: [] as string[],
  category_id: null as number | null,
  source: null as string | null,
  assigned_to: null as number | null,
  requester_id: null as number | null,
  requester_name: '' as string,
  created_from: '',
  created_to: '',
})

// Column visibility — Freshservice-style full column set
const allColumns = [
  { key: 'title', label: t('tickets.subject') },
  { key: 'requester', label: t('tickets.requester') },
  { key: 'lifecycle_state', label: 'Estado' },
  { key: 'status', label: 'Estado del ticket' },
  { key: 'priority', label: t('common.priority') },
  { key: 'assignee', label: t('tickets.assignedTo') },
  { key: 'status_details', label: t('ticketForm.statusDetails') },
  { key: 'department', label: t('ticketForm.department') },
  { key: 'source', label: t('ticketForm.source') },
  { key: 'created_at', label: t('tickets.createdAt') },
  { key: 'updated_at', label: t('ticketForm.updatedAt') },
  { key: 'due_date', label: t('ticketForm.dueDate') },
  { key: 'closed_at', label: t('ticketForm.closedAt') },
  { key: 'approval_status', label: t('ticketForm.approvalStatus') },
  { key: 'planned_start_date', label: t('ticketForm.plannedStartDate') },
  { key: 'planned_end_date', label: t('ticketForm.plannedEndDate') },
  { key: 'planned_effort', label: t('ticketForm.plannedEffort') },
  { key: 'association_type', label: t('ticketForm.associationType') },
  { key: 'requester_location', label: t('ticketForm.requesterLocation') },
  { key: 'requester_vip', label: t('ticketForm.requesterVip') },
  { key: 'resolved_at', label: t('ticketForm.resolvedAt') },
  { key: 'impact', label: t('ticketForm.impact') },
  { key: 'urgency', label: t('ticketForm.urgency') },
  { key: 'category', label: t('common.category') },
  { key: 'subcategory', label: t('ticketForm.subcategory') },
  { key: 'item', label: t('ticketForm.item') },
  { key: 'major_incident_type', label: t('ticketForm.majorIncidentType') },
  { key: 'impacted_locations', label: t('ticketForm.impactedLocations') },
  { key: 'customers_impacted', label: t('ticketForm.customersImpacted') },
  { key: 'specific_subject', label: t('ticketForm.specificSubject') },
  { key: 'contact_number', label: t('ticketForm.contactNumber') },
  { key: 'type', label: t('tickets.type') },
]
const defaultColumns = ['title', 'requester', 'lifecycle_state', 'priority', 'status', 'assignee', 'created_at']
const visibleColumns = ref<string[]>(loadColumns())
const showColumnDialog = ref(false)
const columnDialogModel = ref<string[]>([])

function loadColumns(): string[] {
  try {
    const stored = localStorage.getItem('autoservice_ticket_columns')
    if (stored) return JSON.parse(stored)
  } catch { /* ignore */ }
  return [...defaultColumns]
}

function saveColumns() {
  localStorage.setItem('autoservice_ticket_columns', JSON.stringify(visibleColumns.value))
}

function openColumnSettings() {
  columnDialogModel.value = [...visibleColumns.value]
  columnSearch.value = ''
  showColumnDialog.value = true
}

function applyColumnSettings() {
  visibleColumns.value = [...columnDialogModel.value]
  saveColumns()
  localStorage.setItem('autoservice_ticket_density', rowDensity.value)
  showColumnDialog.value = false
}

// Row density
const rowDensity = ref<'default' | 'compact'>(
  (localStorage.getItem('autoservice_ticket_density') as 'default' | 'compact') || 'default'
)

// Column dialog: search & drag-and-drop
const columnSearch = ref('')
const dragIdx = ref<number | null>(null)

const filteredAllColumns = computed(() => {
  if (!columnSearch.value) return allColumns
  const q = columnSearch.value.toLowerCase()
  return allColumns.filter(c => c.label.toLowerCase().includes(q))
})

const selectedColumnObjects = computed(() =>
  columnDialogModel.value
    .map(key => allColumns.find(c => c.key === key))
    .filter(Boolean) as { key: string; label: string }[]
)

function toggleDialogColumn(key: string) {
  const idx = columnDialogModel.value.indexOf(key)
  if (idx >= 0) columnDialogModel.value.splice(idx, 1)
  else columnDialogModel.value.push(key)
}

function onDragStart(idx: number, e: DragEvent) {
  dragIdx.value = idx
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}

function onDragOver(idx: number, e: DragEvent) {
  e.preventDefault()
  if (dragIdx.value === null || dragIdx.value === idx) return
  const items = [...columnDialogModel.value]
  const [moved] = items.splice(dragIdx.value, 1)
  items.splice(idx, 0, moved)
  columnDialogModel.value = items
  dragIdx.value = idx
}

function onDragEnd() {
  dragIdx.value = null
}

// Column alignment map for dynamic rendering
const colAlignMap: Record<string, string> = {
  status: 'center', priority: 'center', source: 'center',
  approval_status: 'center', association_type: 'center',
  requester_vip: 'center', impact: 'center', urgency: 'center',
  customers_impacted: 'center', type: 'center',
}

function getColLabel(key: string): string {
  return allColumns.find(c => c.key === key)?.label || key
}

// --- Export ---
const showExportPanel = ref(false)
const exportLoading = ref(false)
const exportFormat = ref<'csv' | 'excel'>('csv')
const exportFilterField = ref('created_at')
const exportFilterPeriod = ref('30d')

const exportFieldOptions = [
  { key: 'title', label: 'Asunto' },
  { key: 'type', label: 'Tipo' },
  { key: 'source', label: 'Origen' },
  { key: 'status', label: 'Estado' },
  { key: 'urgency', label: 'Urgencia' },
  { key: 'impact', label: 'Impacto' },
  { key: 'priority', label: 'Prioridad' },
  { key: 'department', label: 'Departamento' },
  { key: 'assignee', label: 'Agente' },
  { key: 'description', label: 'Descripcion' },
  { key: 'category', label: 'Categoria' },
  { key: 'subcategory', label: 'Subcategoria' },
  { key: 'item', label: 'Elemento' },
  { key: 'planned_start_date', label: 'Fecha de inicio planificada' },
  { key: 'planned_end_date', label: 'Fecha de finalizacion planificada' },
  { key: 'planned_effort', label: 'Esfuerzo planificado' },
  { key: 'status_details', label: 'Detalles de estado' },
  { key: 'association_type', label: 'Tipo de asociacion' },
  { key: 'requester_location', label: 'Ubicacion del solicitante' },
  { key: 'requester_vip', label: 'Solicitante VIP' },
  { key: 'ticket_number', label: 'ID del Ticket' },
  { key: 'requester_name', label: 'Nombre del solicitante' },
  { key: 'requester_email', label: 'Correo del solicitante' },
  { key: 'created_at', label: 'Hora de creacion' },
  { key: 'due_date', label: 'Hora de vencimiento' },
  { key: 'resolved_at', label: 'Hora de resolucion' },
  { key: 'closed_at', label: 'Hora de cierre' },
  { key: 'updated_at', label: 'Hora de ultima actualizacion' },
  { key: 'response_due_at', label: 'Tiempo inicial de respuesta' },
  { key: 'resolution_due_at', label: 'Resolucion pendiente' },
  { key: 'approval_status', label: 'Estado de aprobacion' },
  { key: 'major_incident_type', label: 'Major incident type' },
  { key: 'impacted_locations', label: 'Impacted locations' },
  { key: 'customers_impacted', label: 'No. of customers impacted' },
  { key: 'specific_subject', label: 'Asunto especifico' },
  { key: 'contact_number', label: 'Numero de contacto' },
  { key: 'tags', label: 'Etiquetas' },
  { key: 'satisfaction_rating', label: 'Resultado de encuestas' },
]

const defaultExportFields = ['title', 'status', 'priority', 'assignee', 'department', 'requester_name', 'created_at', 'ticket_number', 'category']
const exportSelectedFields = ref<string[]>([...defaultExportFields])

const exportSelectAll = computed({
  get: () => exportSelectedFields.value.length === exportFieldOptions.length,
  set: (val: boolean) => {
    exportSelectedFields.value = val ? exportFieldOptions.map(f => f.key) : []
  },
})

function openExportPanel() {
  exportSelectedFields.value = [...defaultExportFields]
  showExportPanel.value = true
}

async function doExport() {
  if (exportSelectedFields.value.length === 0) return
  exportLoading.value = true
  try {
    const params: Parameters<typeof exportTickets>[0] = {
      fields: exportSelectedFields.value,
      format: exportFormat.value,
      filter_field: exportFilterField.value,
      filter_period: exportFilterPeriod.value,
    }
    // Pass current filters
    if (filters.value.statuses.length === 1) params.status = filters.value.statuses[0]
    if (filters.value.priorities.length === 1) params.priority = filters.value.priorities[0]
    if (filters.value.search) params.search = filters.value.search

    const response = await exportTickets(params)
    const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `tickets_${new Date().toISOString().slice(0, 10)}.csv`
    link.click()
    window.URL.revokeObjectURL(url)
    showExportPanel.value = false
    $q.notify({ type: 'positive', message: 'Exportacion completada', timeout: 2000 })
  } catch {
    $q.notify({ type: 'negative', message: 'Error al exportar tickets' })
  } finally {
    exportLoading.value = false
  }
}

// --- Hover Card ---
const hoveredTicket = ref<Ticket | null>(null)
const hoverCardStyle = ref({ top: '0px', left: '0px' })
let hoverEnterTimer: ReturnType<typeof setTimeout> | undefined
let hoverLeaveTimer: ReturnType<typeof setTimeout> | undefined

function onRowMouseEnter(ticket: Ticket, event: MouseEvent) {
  clearTimeout(hoverLeaveTimer)
  clearTimeout(hoverEnterTimer)
  const target = event.currentTarget as HTMLElement
  hoverEnterTimer = setTimeout(() => {
    const rect = target.getBoundingClientRect()
    const cardWidth = 400
    const cardHeight = 210
    // Position: vertically centered on the title, shifted right past the title column
    const tr = target.closest('tr')
    const trRect = tr ? tr.getBoundingClientRect() : rect
    let left = trRect.left + Math.max(200, trRect.width * 0.15)
    let top = trRect.top + trRect.height / 2 - 10
    // Keep card within viewport
    if (left + cardWidth > window.innerWidth - 16) left = window.innerWidth - cardWidth - 16
    if (top + cardHeight > window.innerHeight - 16) top = trRect.top - cardHeight + trRect.height / 2
    if (top < 8) top = 8
    hoverCardStyle.value = { top: `${top}px`, left: `${left}px` }
    hoveredTicket.value = ticket
  }, 350)
}

function onRowMouseLeave() {
  clearTimeout(hoverEnterTimer)
  hoverLeaveTimer = setTimeout(() => {
    hoveredTicket.value = null
  }, 150)
}

function onHoverCardEnter() {
  clearTimeout(hoverLeaveTimer)
}

function onHoverCardLeave() {
  hoveredTicket.value = null
}

function timeAgo(dateStr: string): string {
  const diffMin = Math.floor((Date.now() - new Date(dateStr).getTime()) / 60000)
  if (diffMin < 1) return 'hace un momento'
  if (diffMin < 60) return `hace ${diffMin} minuto${diffMin > 1 ? 's' : ''}`
  const diffHours = Math.floor(diffMin / 60)
  if (diffHours < 24) return `hace ${diffHours} hora${diffHours > 1 ? 's' : ''}`
  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 30) return `hace ${diffDays} día${diffDays > 1 ? 's' : ''}`
  const diffMonths = Math.floor(diffDays / 30)
  return `hace ${diffMonths} mes${diffMonths > 1 ? 'es' : ''}`
}

function formatFullDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString('es-PE', {
    weekday: 'short', day: 'numeric', month: 'short',
    hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true,
  })
}

function stripHtml(html: string): string {
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  return tmp.textContent || tmp.innerText || ''
}

function truncateText(text: string, maxLen: number): string {
  const clean = stripHtml(text)
  return clean.length > maxLen ? clean.slice(0, maxLen) + '...' : clean
}

function goToTicketReply(ticket: Ticket) {
  hoveredTicket.value = null
  router.push({ path: `/tickets/${ticket.id}`, query: { action: 'reply' } })
}

function goToTicketNote(ticket: Ticket) {
  hoveredTicket.value = null
  router.push({ path: `/tickets/${ticket.id}`, query: { action: 'note' } })
}

// --- Helpers ---
const canInlineEdit = computed(() => auth.isAdmin || auth.isAgent)

const statusValues = ['open', 'in_progress', 'pending', 'resolved', 'closed'] as const
const priorityValues = ['low', 'medium', 'high', 'urgent'] as const
const typeValues = ['incident', 'request', 'problem', 'change'] as const
const sourceValues = ['portal', 'email', 'chatbot', 'catalog', 'api', 'phone'] as const

const sourceConfig: Record<string, { icon: string; color: string; label: string }> = {
  portal: { icon: 'language', color: 'primary', label: 'Portal' },
  email: { icon: 'email', color: 'orange', label: 'Email' },
  chatbot: { icon: 'smart_toy', color: 'purple', label: 'Chatbot' },
  catalog: { icon: 'storefront', color: 'teal', label: 'Catálogo' },
  api: { icon: 'api', color: 'cyan', label: 'API' },
  phone: { icon: 'phone', color: 'green', label: 'Teléfono' },
}

function getStatusColor(status: string): string {
  const colors: Record<string, string> = {
    open: 'warning', in_progress: 'primary', pending: 'purple',
    resolved: 'positive', closed: 'grey',
  }
  return colors[status] || 'grey'
}

function getPriorityColor(priority: string): string {
  const colors: Record<string, string> = {
    low: '#4caf50', medium: '#2196f3', high: '#ff9800', urgent: '#f44336',
  }
  return colors[priority] || '#9e9e9e'
}

function getPriorityBadgeColor(priority: string): string {
  const colors: Record<string, string> = {
    low: 'positive', medium: 'primary', high: 'warning', urgent: 'negative',
  }
  return colors[priority] || 'grey'
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('es-PE', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
  })
}

function getInitial(name?: string): string {
  return name ? name.charAt(0).toUpperCase() : '?'
}

function getAvatarColor(name?: string): string {
  if (!name) return '#9e9e9e'
  const colors = ['#1976d2', '#388e3c', '#f57c00', '#7b1fa2', '#c2185b', '#00796b', '#5d4037']
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  return colors[Math.abs(hash) % colors.length]
}

const impactLabels: Record<string, string> = { low: 'Bajo', medium: 'Medio', high: 'Alto' }
const urgencyLabels: Record<string, string> = { low: 'Baja', medium: 'Media', high: 'Alta' }
const approvalLabels: Record<string, string> = {
  not_requested: 'No solicitado', requested: 'Solicitado', approved: 'Aprobado', rejected: 'Rechazado',
}
const associationLabels: Record<string, string> = {
  parent: 'Padre', child: 'Hijo', related: 'Relacionado', cause: 'Causa',
}

function getImpactColor(v: string): string {
  return { low: 'positive', medium: 'primary', high: 'negative' }[v] || 'grey'
}

function getApprovalColor(v: string): string {
  return { not_requested: 'grey', requested: 'warning', approved: 'positive', rejected: 'negative' }[v] || 'grey'
}

function getDepartmentName(ticket: Ticket): string {
  return ticket.department?.name || '-'
}

// --- API ---
async function loadTickets() {
  loading.value = true
  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: perPage.value,
      sort: sortField.value,
      direction: sortDirection.value,
    }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.statuses.length === 1) params.status = filters.value.statuses[0]
    else if (filters.value.statuses.length > 1) params.status = filters.value.statuses.join(',')
    if (filters.value.priorities.length === 1) params.priority = filters.value.priorities[0]
    else if (filters.value.priorities.length > 1) params.priority = filters.value.priorities.join(',')
    if (filters.value.types.length === 1) params.type = filters.value.types[0]
    else if (filters.value.types.length > 1) params.type = filters.value.types.join(',')
    if (filters.value.category_id) params.category_id = filters.value.category_id
    if (filters.value.source) params.source = filters.value.source
    if (filters.value.assigned_to) params.assigned_to = filters.value.assigned_to
    if (filters.value.requester_id) params.requester_id = filters.value.requester_id
    if (filters.value.created_from) params.created_from = filters.value.created_from
    if (filters.value.created_to) params.created_to = filters.value.created_to

    // Merge view-level filters (from the sidebar views)
    for (const [key, val] of Object.entries(viewFilters.value)) {
      if (val !== null && val !== undefined && val !== '') {
        // Don't override user-set filters
        if (!params[key]) params[key] = val
      }
    }

    const res = await getTickets(params)
    tickets.value = res.data
    totalItems.value = res.meta.total
    // Clear selections that no longer exist
    const ids = new Set(res.data.map((t: Ticket) => t.id))
    selectedIds.value = selectedIds.value.filter(id => ids.has(id))
  } catch {
    $q.notify({ type: 'negative', message: 'Error al cargar tickets' })
  } finally {
    loading.value = false
  }
}

// --- Inline edit ---
async function inlineUpdateStatus(ticket: Ticket, newStatus: string) {
  const old = ticket.status
  ticket.status = newStatus as Ticket['status']
  try {
    await quickUpdateTicket(ticket.id, { status: newStatus })
    $q.notify({ type: 'positive', message: 'Estado actualizado', timeout: 1500 })
  } catch {
    ticket.status = old as Ticket['status']
    $q.notify({ type: 'negative', message: 'Error al actualizar estado' })
  }
}

async function inlineUpdatePriority(ticket: Ticket, newPriority: string) {
  const old = ticket.priority
  ticket.priority = newPriority as Ticket['priority']
  try {
    await quickUpdateTicket(ticket.id, { priority: newPriority })
    $q.notify({ type: 'positive', message: 'Prioridad actualizada', timeout: 1500 })
  } catch {
    ticket.priority = old as Ticket['priority']
    $q.notify({ type: 'negative', message: 'Error al actualizar prioridad' })
  }
}

async function inlineUpdateAssignee(ticket: Ticket, agentId: number | null) {
  const old = ticket.assigned_to
  const oldAssignee = ticket.assignee
  ticket.assigned_to = agentId
  ticket.assignee = agentId ? agents.value.find(a => a.id === agentId) : undefined
  try {
    await quickUpdateTicket(ticket.id, { assigned_to: agentId })
    $q.notify({ type: 'positive', message: 'Asignación actualizada', timeout: 1500 })
  } catch {
    ticket.assigned_to = old
    ticket.assignee = oldAssignee
    $q.notify({ type: 'negative', message: 'Error al actualizar asignación' })
  }
}

// --- Bulk actions ---
async function bulkChangeStatus(newStatus: string) {
  try {
    await bulkUpdateTickets({ ticket_ids: [...selectedIds.value], status: newStatus })
    $q.notify({ type: 'positive', message: `${selectedIds.value.length} ticket(s) actualizados` })
    selectedIds.value = []
    await loadTickets()
  } catch {
    $q.notify({ type: 'negative', message: 'Error en actualización masiva' })
  }
}

async function bulkChangePriority(newPriority: string) {
  try {
    await bulkUpdateTickets({ ticket_ids: [...selectedIds.value], priority: newPriority })
    $q.notify({ type: 'positive', message: `${selectedIds.value.length} ticket(s) actualizados` })
    selectedIds.value = []
    await loadTickets()
  } catch {
    $q.notify({ type: 'negative', message: 'Error en actualización masiva' })
  }
}

async function bulkAssign(agentId: number | null) {
  try {
    await bulkUpdateTickets({ ticket_ids: [...selectedIds.value], assigned_to: agentId })
    $q.notify({ type: 'positive', message: `${selectedIds.value.length} ticket(s) actualizados` })
    selectedIds.value = []
    await loadTickets()
  } catch {
    $q.notify({ type: 'negative', message: 'Error en actualización masiva' })
  }
}

// --- Pagination ---
function prevPage() {
  if (currentPage.value > 1) {
    currentPage.value--
    loadTickets()
  }
}

function nextPage() {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
    loadTickets()
  }
}

// --- Navigation ---
function goToTicket(ticket: Ticket) {
  router.push(`/tickets/${ticket.id}`)
}

// --- Filter actions ---
function applyFilters() {
  currentPage.value = 1
  loadTickets()
}

function clearFilters() {
  filters.value = {
    search: '',
    statuses: [],
    priorities: [],
    types: [],
    category_id: null,
    source: null,
    assigned_to: null,
    requester_id: null,
    requester_name: '',
    created_from: '',
    created_to: '',
  }
  // Reset to "All tickets" view
  currentViewId.value = 'all'
  currentViewLabel.value = 'Todos los tickets'
  viewFilters.value = {}
  currentPage.value = 1
  loadTickets()
}

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.value.search) count++
  if (filters.value.statuses.length) count++
  if (filters.value.priorities.length) count++
  if (filters.value.types.length) count++
  if (filters.value.category_id) count++
  if (filters.value.source) count++
  if (filters.value.assigned_to) count++
  if (filters.value.requester_id) count++
  if (filters.value.created_from || filters.value.created_to) count++
  return count
})

// --- Search debounce ---
let searchTimeout: ReturnType<typeof setTimeout>
watch(() => filters.value.search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    loadTickets()
  }, 400)
})

// --- Sort change ---
function onSortChange(val: string) {
  sortField.value = val
  currentPage.value = 1
  loadTickets()
}

function toggleSortDirection() {
  sortDirection.value = sortDirection.value === 'desc' ? 'asc' : 'desc'
  currentPage.value = 1
  loadTickets()
}

// --- Init ---
onMounted(async () => {
  // Accept search from global navbar search
  if (route.query.search) {
    filters.value.search = String(route.query.search)
  }
  // Accept requester filter from query (e.g. from "Ver todos los tickets" in requester panel)
  if (route.query.requester_id) {
    filters.value.requester_id = Number(route.query.requester_id)
    filters.value.requester_name = String(route.query.requester_name || '')
    // Switch to "all tickets" view and open filters
    currentViewId.value = 'all'
    currentViewLabel.value = 'Todos los tickets'
    viewFilters.value = {}
    showFilters.value = true
    filterTab.value = 'advanced'
  }
  // Load tickets independently so other failures don't block the list
  loadTickets()
  // Load auxiliary data in parallel
  try {
    const promises: Promise<any>[] = [getCategories(), getDepartments()]
    if (auth.isAdmin || auth.isAgent) promises.push(getAgents())
    const results = await Promise.all(promises)
    categories.value = results[0].data
    departments.value = results[1].data
    if (results[2]) agents.value = results[2].data
  } catch {
    // Auxiliary data failed — tickets still load fine
  }
  startTicketListTour()
})
</script>

<template>
  <q-page padding>
    <!-- Views Sidebar -->
    <TicketViewsSidebar
      v-model="showViewsSidebar"
      :current-view-id="currentViewId"
      :current-filters="viewFilters"
      @select-view="onSelectView"
    />

    <div class="ticket-list-page" style="max-width: 100%;">

      <!-- Page header with view name -->
      <div class="row items-center q-mb-sm">
        <q-btn
          flat dense round
          icon="menu"
          size="md"
          class="q-mr-sm"
          @click="showViewsSidebar = !showViewsSidebar"
        >
          <q-tooltip>Vistas de tickets</q-tooltip>
        </q-btn>
        <div>
          <div class="text-h6 text-weight-bold" style="line-height: 1.2;">Tickets</div>
          <div class="text-caption text-grey-7">{{ currentViewLabel }}</div>
        </div>
      </div>

      <!-- Top Toolbar -->
      <div class="toolbar row items-center q-mb-sm q-gutter-x-sm">
        <!-- Left: select all + sort -->
        <q-checkbox
          :model-value="selectAll"
          :indeterminate-value="selectAllIndeterminate ? true : undefined"
          @update:model-value="selectAll = $event"
          dense
          class="q-mr-xs"
        />
        <q-badge v-if="selectedIds.length" color="primary" class="q-mr-sm">
          {{ selectedIds.length }}
        </q-badge>

        <q-separator vertical class="q-mx-xs" style="height: 24px;" />

        <!-- View mode toggle: Lista / Tablero -->
        <q-btn-toggle
          :model-value="viewMode"
          @update:model-value="setViewMode($event)"
          flat dense no-caps
          toggle-color="primary"
          size="sm"
          :options="[
            { icon: 'view_list', value: 'list', slot: 'list' },
            { icon: 'view_kanban', value: 'board', slot: 'board' },
          ]"
          class="q-mr-xs"
        >
          <template v-slot:list><q-tooltip>Lista</q-tooltip></template>
          <template v-slot:board><q-tooltip>Tablero</q-tooltip></template>
        </q-btn-toggle>

        <q-separator vertical class="q-mx-xs" style="height: 24px;" />

        <q-btn-dropdown
          v-if="viewMode === 'list'"
          flat
          dense
          no-caps
          :label="`${t('ticketList.sortBy')}: ${sortOptions.find(o => o.value === sortField)?.label}`"
          class="text-caption"
          content-class="shadow-2"
        >
          <q-list dense>
            <q-item
              v-for="opt in sortOptions"
              :key="opt.value"
              clickable
              v-close-popup
              @click="onSortChange(opt.value)"
              :active="sortField === opt.value"
            >
              <q-item-section>{{ opt.label }}</q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>
        <q-btn
          v-if="viewMode === 'list'"
          flat dense round
          :icon="sortDirection === 'desc' ? 'arrow_downward' : 'arrow_upward'"
          size="sm"
          @click="toggleSortDirection"
        >
          <q-tooltip>{{ sortDirection === 'desc' ? 'Descendente' : 'Ascendente' }}</q-tooltip>
        </q-btn>

        <q-space />

        <!-- Export button (Freshservice style) -->
        <q-btn
          v-if="canInlineEdit"
          flat dense no-caps
          icon="download"
          label="Exportar"
          class="text-caption q-mr-sm"
          @click="openExportPanel"
        />

        <!-- Right: pagination info + controls -->
        <span class="text-caption text-grey-7 q-mr-sm" v-if="totalItems > 0">
          {{ paginationFrom }}-{{ paginationTo }} {{ t('ticketList.of') }} {{ totalItems }}
        </span>
        <q-btn
          flat dense round icon="chevron_left" size="sm"
          :disable="currentPage <= 1"
          @click="prevPage"
        />
        <q-btn
          flat dense round icon="chevron_right" size="sm"
          :disable="currentPage >= totalPages"
          @click="nextPage"
        />

        <q-separator vertical class="q-mx-xs" style="height: 24px;" />

        <q-btn flat dense round icon="settings" size="sm" @click="openColumnSettings">
          <q-tooltip>{{ t('ticketList.columns') }}</q-tooltip>
        </q-btn>

        <q-btn
          flat dense round icon="filter_list" size="sm"
          :color="showFilters || activeFilterCount > 0 ? 'primary' : undefined"
          @click="showFilters = !showFilters"
        >
          <q-badge v-if="activeFilterCount > 0" color="red" floating>{{ activeFilterCount }}</q-badge>
          <q-tooltip>{{ t('ticketList.filters') }}</q-tooltip>
        </q-btn>

        <q-btn
          color="primary"
          icon="add"
          :label="$q.screen.gt.sm ? t('tickets.create') : undefined"
          dense
          no-caps
          to="/tickets/create"
          class="q-ml-sm"
        />
      </div>

      <!-- Bulk Actions Bar -->
      <div
        v-if="selectedIds.length > 0"
        class="bulk-bar row items-center q-mb-sm bg-blue-1 q-pa-sm rounded-borders q-gutter-x-sm"
      >
        <span class="text-body2 text-primary q-mr-sm">
          <strong>{{ selectedIds.length }}</strong> {{ t('ticketList.selected') }}
        </span>

        <q-btn-dropdown
          flat dense no-caps color="primary"
          :label="t('ticketList.bulkStatus')"
          icon="swap_horiz"
          content-class="shadow-2"
        >
          <q-list dense>
            <q-item
              v-for="s in statusValues"
              :key="s"
              clickable
              v-close-popup
              @click="bulkChangeStatus(s)"
            >
              <q-item-section side>
                <q-badge :color="getStatusColor(s)" />
              </q-item-section>
              <q-item-section>{{ t(`tickets.statuses.${s}`) }}</q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>

        <q-btn-dropdown
          flat dense no-caps color="primary"
          :label="t('ticketList.bulkPriority')"
          icon="flag"
          content-class="shadow-2"
        >
          <q-list dense>
            <q-item
              v-for="p in priorityValues"
              :key="p"
              clickable
              v-close-popup
              @click="bulkChangePriority(p)"
            >
              <q-item-section side>
                <span class="priority-dot" :style="{ backgroundColor: getPriorityColor(p) }" />
              </q-item-section>
              <q-item-section>{{ t(`tickets.priorities.${p}`) }}</q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>

        <q-btn-dropdown
          v-if="canInlineEdit"
          flat dense no-caps color="primary"
          :label="t('ticketList.bulkAssign')"
          icon="person_add"
          content-class="shadow-2"
        >
          <q-list dense>
            <q-item clickable v-close-popup @click="bulkAssign(null)">
              <q-item-section>{{ t('ticketList.unassigned') }}</q-item-section>
            </q-item>
            <q-item
              v-for="a in agents"
              :key="a.id"
              clickable
              v-close-popup
              @click="bulkAssign(a.id)"
            >
              <q-item-section avatar>
                <q-avatar size="24px" :style="{ backgroundColor: getAvatarColor(a.name) }" text-color="white" font-size="12px">
                  {{ getInitial(a.name) }}
                </q-avatar>
              </q-item-section>
              <q-item-section>{{ a.name }}</q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>

        <q-space />

        <q-btn flat dense round icon="close" size="sm" @click="selectedIds = []" />
      </div>

      <!-- Main content area: table + optional filter panel -->
      <div class="row no-wrap">
        <!-- Table/Board area -->
        <div class="col">
          <q-linear-progress v-if="loading" indeterminate color="primary" class="q-mb-xs" />

          <!-- Board View (Kanban) -->
          <TicketBoardView
            v-if="viewMode === 'board'"
            :tickets="tickets"
            :loading="loading"
            @ticket-updated="onBoardTicketUpdated"
          />

          <!-- List View (Table) -->
          <q-markup-table v-else flat separator="horizontal" class="ticket-table" :class="{ 'compact-density': rowDensity === 'compact' }" wrap-cells>
            <thead>
              <tr>
                <th style="width: 40px;" />
                <th v-for="colKey in visibleColumns" :key="colKey" :class="`text-${colAlignMap[colKey] || 'left'}`">
                  {{ getColLabel(colKey) }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!loading && tickets.length === 0">
                <td :colspan="1 + visibleColumns.length" class="text-center text-grey q-pa-xl">
                  <q-icon name="inbox" size="48px" class="q-mb-sm" color="grey-4" />
                  <div class="text-subtitle1">{{ t('ticketList.noTickets') }}</div>
                </td>
              </tr>
              <tr
                v-for="ticket in tickets"
                :key="ticket.id"
                class="ticket-row"
                :class="{ 'selected-row': selectedIds.includes(ticket.id) }"
                @click="goToTicket(ticket)"
              >
                <td @click.stop>
                  <q-checkbox
                    :model-value="selectedIds.includes(ticket.id)"
                    @update:model-value="toggleTicketSelection(ticket.id)"
                    dense
                  />
                </td>

                <td
                  v-for="colKey in visibleColumns"
                  :key="colKey"
                  :class="`text-${colAlignMap[colKey] || 'left'}`"
                >
                  <!-- Asunto -->
                  <template v-if="colKey === 'title'">
                    <div
                      class="title-hover-zone"
                      @mouseenter.stop="onRowMouseEnter(ticket, $event)"
                      @mouseleave.stop="onRowMouseLeave"
                    >
                      <div class="text-body2 text-weight-medium ellipsis" style="max-width: 360px;">{{ ticket.title }}</div>
                      <div class="text-caption text-grey">{{ ticket.ticket_number }}</div>
                    </div>
                  </template>

                  <!-- Solicitante -->
                  <template v-else-if="colKey === 'requester'">
                    <div class="row items-center no-wrap q-gutter-x-sm">
                      <q-avatar size="28px" :style="{ backgroundColor: getAvatarColor(ticket.requester?.name) }" text-color="white" font-size="13px">
                        {{ getInitial(ticket.requester?.name) }}
                      </q-avatar>
                      <span class="text-body2 ellipsis" style="max-width: 140px;">{{ ticket.requester?.name || '-' }}</span>
                    </div>
                  </template>

                  <!-- Lifecycle State (Freshservice-style computed tag) -->
                  <template v-else-if="colKey === 'lifecycle_state'">
                    <q-badge
                      v-if="ticket.lifecycle_state === 'new'"
                      color="green-2" text-color="green-9"
                      class="q-pa-xs" style="font-size: 11px;"
                    >
                      Nuevo
                    </q-badge>
                    <q-badge
                      v-else-if="ticket.lifecycle_state === 'overdue'"
                      color="red-2" text-color="red-9"
                      class="q-pa-xs" style="font-size: 11px;"
                    >
                      Atrasadas
                    </q-badge>
                    <q-badge
                      v-else-if="ticket.lifecycle_state === 'requester_replied'"
                      color="blue-2" text-color="blue-9"
                      class="q-pa-xs" style="font-size: 11px; white-space: nowrap;"
                    >
                      El Solicitante Ha Respondido
                    </q-badge>
                    <span v-else class="text-grey-4">-</span>
                  </template>

                  <!-- Estado del ticket (inline edit) -->
                  <template v-else-if="colKey === 'status'">
                    <template v-if="canInlineEdit">
                      <q-badge :color="getStatusColor(ticket.status)" class="cursor-pointer q-pa-xs" style="font-size: 11px;" @click.stop>
                        {{ t(`tickets.statuses.${ticket.status}`) }}
                        <q-popup-proxy transition-show="scale" transition-hide="scale" :offset="[0, 4]">
                          <q-list dense style="min-width: 150px;">
                            <q-item v-for="s in statusValues" :key="s" clickable v-close-popup @click="inlineUpdateStatus(ticket, s)" :active="ticket.status === s" active-class="text-primary bg-blue-1">
                              <q-item-section side><q-badge :color="getStatusColor(s)" style="width: 10px; height: 10px; min-width: 10px; padding: 0; border-radius: 50%;" /></q-item-section>
                              <q-item-section>{{ t(`tickets.statuses.${s}`) }}</q-item-section>
                            </q-item>
                          </q-list>
                        </q-popup-proxy>
                      </q-badge>
                    </template>
                    <q-badge v-else :color="getStatusColor(ticket.status)" class="q-pa-xs" style="font-size: 11px;">
                      {{ t(`tickets.statuses.${ticket.status}`) }}
                    </q-badge>
                  </template>

                  <!-- Prioridad (inline edit) -->
                  <template v-else-if="colKey === 'priority'">
                    <template v-if="canInlineEdit">
                      <span class="row inline items-center no-wrap q-gutter-x-xs cursor-pointer" @click.stop>
                        <span class="priority-dot" :style="{ backgroundColor: getPriorityColor(ticket.priority) }" />
                        <span class="text-body2">{{ t(`tickets.priorities.${ticket.priority}`) }}</span>
                        <q-popup-proxy transition-show="scale" transition-hide="scale" :offset="[0, 4]">
                          <q-list dense style="min-width: 140px;">
                            <q-item v-for="p in priorityValues" :key="p" clickable v-close-popup @click="inlineUpdatePriority(ticket, p)" :active="ticket.priority === p" active-class="text-primary bg-blue-1">
                              <q-item-section side><span class="priority-dot" :style="{ backgroundColor: getPriorityColor(p) }" /></q-item-section>
                              <q-item-section>{{ t(`tickets.priorities.${p}`) }}</q-item-section>
                            </q-item>
                          </q-list>
                        </q-popup-proxy>
                      </span>
                    </template>
                    <span v-else class="row inline items-center no-wrap q-gutter-x-xs">
                      <span class="priority-dot" :style="{ backgroundColor: getPriorityColor(ticket.priority) }" />
                      <span class="text-body2">{{ t(`tickets.priorities.${ticket.priority}`) }}</span>
                    </span>
                  </template>

                  <!-- Asignado (inline edit) -->
                  <template v-else-if="colKey === 'assignee'">
                    <template v-if="canInlineEdit">
                      <span class="cursor-pointer text-body2" :class="ticket.assignee ? '' : 'text-grey'" @click.stop>
                        {{ ticket.assignee?.name || t('ticketList.unassigned') }}
                        <q-popup-proxy transition-show="scale" transition-hide="scale" :offset="[0, 4]">
                          <q-list dense style="min-width: 180px;">
                            <q-item clickable v-close-popup @click="inlineUpdateAssignee(ticket, null)" :active="!ticket.assigned_to" active-class="text-primary bg-blue-1">
                              <q-item-section>{{ t('ticketList.unassigned') }}</q-item-section>
                            </q-item>
                            <q-item v-for="a in agents" :key="a.id" clickable v-close-popup @click="inlineUpdateAssignee(ticket, a.id)" :active="ticket.assigned_to === a.id" active-class="text-primary bg-blue-1">
                              <q-item-section avatar><q-avatar size="24px" :style="{ backgroundColor: getAvatarColor(a.name) }" text-color="white" font-size="12px">{{ getInitial(a.name) }}</q-avatar></q-item-section>
                              <q-item-section>{{ a.name }}</q-item-section>
                            </q-item>
                          </q-list>
                        </q-popup-proxy>
                      </span>
                    </template>
                    <span v-else class="text-body2" :class="ticket.assignee ? '' : 'text-grey'">
                      {{ ticket.assignee?.name || t('ticketList.unassigned') }}
                    </span>
                  </template>

                  <!-- Fuente -->
                  <template v-else-if="colKey === 'source'">
                    <q-chip dense size="sm" :icon="sourceConfig[ticket.source]?.icon || 'help'" :color="sourceConfig[ticket.source]?.color || 'grey'" text-color="white" class="q-ma-none">
                      {{ sourceConfig[ticket.source]?.label || ticket.source }}
                    </q-chip>
                  </template>

                  <!-- Creado -->
                  <template v-else-if="colKey === 'created_at'">
                    <span class="text-caption text-grey-7">{{ formatDate(ticket.created_at) }}</span>
                  </template>

                  <!-- Actualizado -->
                  <template v-else-if="colKey === 'updated_at'">
                    <span class="text-caption text-grey-7">{{ formatDate(ticket.updated_at) }}</span>
                  </template>

                  <!-- Detalles de estado -->
                  <template v-else-if="colKey === 'status_details'">
                    <span class="text-body2">{{ ticket.status_details || '-' }}</span>
                  </template>

                  <!-- Departamento -->
                  <template v-else-if="colKey === 'department'">
                    <span class="text-body2">{{ getDepartmentName(ticket) }}</span>
                  </template>

                  <!-- Fecha vencimiento -->
                  <template v-else-if="colKey === 'due_date'">
                    <span class="text-caption text-grey-7">{{ ticket.due_date ? formatDate(ticket.due_date) : '-' }}</span>
                  </template>

                  <!-- Fecha cierre -->
                  <template v-else-if="colKey === 'closed_at'">
                    <span class="text-caption text-grey-7">{{ ticket.closed_at ? formatDate(ticket.closed_at) : '-' }}</span>
                  </template>

                  <!-- Estado de aprobacion -->
                  <template v-else-if="colKey === 'approval_status'">
                    <q-badge v-if="ticket.approval_status" :color="getApprovalColor(ticket.approval_status)">
                      {{ approvalLabels[ticket.approval_status] || ticket.approval_status }}
                    </q-badge>
                    <span v-else class="text-grey">-</span>
                  </template>

                  <!-- Fecha inicio planificada -->
                  <template v-else-if="colKey === 'planned_start_date'">
                    <span class="text-caption text-grey-7">{{ ticket.planned_start_date ? formatDate(ticket.planned_start_date) : '-' }}</span>
                  </template>

                  <!-- Fecha fin planificada -->
                  <template v-else-if="colKey === 'planned_end_date'">
                    <span class="text-caption text-grey-7">{{ ticket.planned_end_date ? formatDate(ticket.planned_end_date) : '-' }}</span>
                  </template>

                  <!-- Esfuerzo planificado -->
                  <template v-else-if="colKey === 'planned_effort'">
                    <span class="text-body2">{{ ticket.planned_effort || '-' }}</span>
                  </template>

                  <!-- Tipo asociacion -->
                  <template v-else-if="colKey === 'association_type'">
                    <q-badge v-if="ticket.association_type" color="blue-grey">
                      {{ associationLabels[ticket.association_type] || ticket.association_type }}
                    </q-badge>
                    <span v-else class="text-grey">-</span>
                  </template>

                  <!-- Ubicacion solicitante -->
                  <template v-else-if="colKey === 'requester_location'">
                    <span class="text-body2">{{ ticket.requester_location || '-' }}</span>
                  </template>

                  <!-- Solicitante VIP -->
                  <template v-else-if="colKey === 'requester_vip'">
                    <q-icon v-if="ticket.requester?.is_vip" name="star" color="amber" size="20px"><q-tooltip>VIP</q-tooltip></q-icon>
                    <span v-else class="text-grey">-</span>
                  </template>

                  <!-- Fecha resolucion -->
                  <template v-else-if="colKey === 'resolved_at'">
                    <span class="text-caption text-grey-7">{{ ticket.resolved_at ? formatDate(ticket.resolved_at) : '-' }}</span>
                  </template>

                  <!-- Impacto -->
                  <template v-else-if="colKey === 'impact'">
                    <q-badge v-if="ticket.impact" :color="getImpactColor(ticket.impact)">{{ impactLabels[ticket.impact] || ticket.impact }}</q-badge>
                    <span v-else class="text-grey">-</span>
                  </template>

                  <!-- Urgencia -->
                  <template v-else-if="colKey === 'urgency'">
                    <q-badge v-if="ticket.urgency" :color="getImpactColor(ticket.urgency)">{{ urgencyLabels[ticket.urgency] || ticket.urgency }}</q-badge>
                    <span v-else class="text-grey">-</span>
                  </template>

                  <!-- Categoria -->
                  <template v-else-if="colKey === 'category'">
                    <span class="text-body2">{{ ticket.category?.name || '-' }}</span>
                  </template>

                  <!-- Subcategoria -->
                  <template v-else-if="colKey === 'subcategory'">
                    <span class="text-body2">{{ ticket.subcategory || '-' }}</span>
                  </template>

                  <!-- Elemento -->
                  <template v-else-if="colKey === 'item'">
                    <span class="text-body2">{{ ticket.item || '-' }}</span>
                  </template>

                  <!-- Tipo incidente mayor -->
                  <template v-else-if="colKey === 'major_incident_type'">
                    <span class="text-body2">{{ ticket.major_incident_type || '-' }}</span>
                  </template>

                  <!-- Ubicaciones impactadas -->
                  <template v-else-if="colKey === 'impacted_locations'">
                    <span v-if="ticket.impacted_locations?.length" class="text-body2">{{ ticket.impacted_locations.join(', ') }}</span>
                    <span v-else class="text-grey">-</span>
                  </template>

                  <!-- Clientes impactados -->
                  <template v-else-if="colKey === 'customers_impacted'">
                    <span class="text-body2">{{ ticket.customers_impacted ?? '-' }}</span>
                  </template>

                  <!-- Asunto especifico -->
                  <template v-else-if="colKey === 'specific_subject'">
                    <span class="text-body2 ellipsis" style="max-width: 200px;">{{ ticket.specific_subject || '-' }}</span>
                  </template>

                  <!-- Numero de contacto -->
                  <template v-else-if="colKey === 'contact_number'">
                    <span class="text-body2">{{ ticket.contact_number || '-' }}</span>
                  </template>

                  <!-- Tipo -->
                  <template v-else-if="colKey === 'type'">
                    <q-badge :color="ticket.type === 'incident' ? 'red' : ticket.type === 'request' ? 'blue' : ticket.type === 'problem' ? 'orange' : 'teal'" outline>
                      {{ t(`tickets.types.${ticket.type}`) }}
                    </q-badge>
                  </template>
                </td>
              </tr>
            </tbody>
          </q-markup-table>

          <!-- Bottom pagination (for convenience) -->
          <div v-if="totalItems > perPage" class="row items-center justify-end q-mt-sm q-gutter-x-sm">
            <span class="text-caption text-grey-7">
              {{ paginationFrom }}-{{ paginationTo }} {{ t('ticketList.of') }} {{ totalItems }}
            </span>
            <q-btn flat dense round icon="chevron_left" size="sm" :disable="currentPage <= 1" @click="prevPage" />
            <q-btn flat dense round icon="chevron_right" size="sm" :disable="currentPage >= totalPages" @click="nextPage" />
          </div>
        </div>

        <!-- Right Filter Panel -->
        <div v-if="showFilters" class="filter-panel q-ml-md gt-sm" style="width: 300px; flex-shrink: 0;">
          <div class="row items-center q-mb-sm">
            <span class="text-subtitle2 text-weight-bold">{{ t('ticketList.filters') }}</span>
            <q-space />
            <q-btn flat dense round icon="close" size="sm" @click="showFilters = false" />
          </div>

          <q-tabs v-model="filterTab" dense align="left" active-color="primary" indicator-color="primary" narrow-indicator class="q-mb-md">
            <q-tab name="basic" :label="t('ticketList.basicFilters')" no-caps />
            <q-tab name="advanced" :label="t('ticketList.advancedFilters')" no-caps />
          </q-tabs>

          <q-tab-panels v-model="filterTab" animated class="bg-transparent">
            <!-- Basic -->
            <q-tab-panel name="basic" class="q-pa-none">
              <q-input
                v-model="filters.search"
                :placeholder="t('common.search') + '...'"
                dense outlined clearable
                class="q-mb-md"
              >
                <template v-slot:prepend><q-icon name="search" /></template>
              </q-input>

              <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">{{ t('common.status') }}</div>
              <div class="q-mb-md">
                <q-checkbox
                  v-for="s in statusValues"
                  :key="s"
                  v-model="filters.statuses"
                  :val="s"
                  :label="t(`tickets.statuses.${s}`)"
                  dense
                  class="full-width"
                />
              </div>

              <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">{{ t('common.priority') }}</div>
              <div class="q-mb-md">
                <q-checkbox
                  v-for="p in priorityValues"
                  :key="p"
                  v-model="filters.priorities"
                  :val="p"
                  dense
                  class="full-width"
                >
                  <span class="row items-center q-gutter-x-xs">
                    <span class="priority-dot" :style="{ backgroundColor: getPriorityColor(p) }" />
                    <span>{{ t(`tickets.priorities.${p}`) }}</span>
                  </span>
                </q-checkbox>
              </div>

              <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">{{ t('tickets.type') }}</div>
              <div class="q-mb-md">
                <q-checkbox
                  v-for="tp in typeValues"
                  :key="tp"
                  v-model="filters.types"
                  :val="tp"
                  :label="t(`tickets.types.${tp}`)"
                  dense
                  class="full-width"
                />
              </div>
            </q-tab-panel>

            <!-- Advanced -->
            <q-tab-panel name="advanced" class="q-pa-none">
              <!-- Requester filter (shown when set from requester panel) -->
              <div v-if="filters.requester_id" class="q-mb-md">
                <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">Solicitante</div>
                <q-chip
                  removable
                  color="primary"
                  text-color="white"
                  icon="person"
                  :label="filters.requester_name || `Usuario #${filters.requester_id}`"
                  @remove="filters.requester_id = null; filters.requester_name = ''; applyFilters()"
                />
              </div>

              <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">{{ t('common.category') }}</div>
              <q-select
                v-model="filters.category_id"
                :options="[{ label: 'Todas', value: null }, ...categories.map(c => ({ label: c.name, value: c.id }))]"
                dense outlined emit-value map-options
                class="q-mb-md"
              />

              <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">{{ t('ticketForm.source') }}</div>
              <q-select
                v-model="filters.source"
                :options="[{ label: 'Todas', value: null }, ...sourceValues.map(s => ({ label: sourceConfig[s]?.label || s, value: s }))]"
                dense outlined emit-value map-options
                class="q-mb-md"
              />

              <div v-if="canInlineEdit" class="q-mb-md">
                <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">{{ t('tickets.assignedTo') }}</div>
                <q-select
                  v-model="filters.assigned_to"
                  :options="[{ label: 'Todos', value: null }, ...agents.map(a => ({ label: a.name, value: a.id }))]"
                  dense outlined emit-value map-options
                />
              </div>

              <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">{{ t('tickets.createdAt') }} (desde)</div>
              <q-input
                v-model="filters.created_from"
                type="date"
                dense outlined
                class="q-mb-md"
              />

              <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">{{ t('tickets.createdAt') }} (hasta)</div>
              <q-input
                v-model="filters.created_to"
                type="date"
                dense outlined
                class="q-mb-md"
              />
            </q-tab-panel>
          </q-tab-panels>

          <div class="row q-gutter-x-sm q-mt-md">
            <q-btn
              color="primary"
              :label="t('ticketList.apply')"
              no-caps dense unelevated
              class="col"
              @click="applyFilters"
            />
            <q-btn
              flat
              :label="t('ticketList.clearFilters')"
              no-caps dense color="grey-7"
              @click="clearFilters"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Column Settings Dialog (Freshservice style) -->
    <q-dialog v-model="showColumnDialog">
      <q-card style="min-width: 620px; max-width: 680px;">
        <q-card-section class="q-pb-sm">
          <!-- Row density -->
          <div class="text-subtitle2 text-weight-bold q-mb-sm">Densidad de filas</div>
          <div class="row q-gutter-x-md q-mb-md">
            <q-radio v-model="rowDensity" val="default" label="Vista predeterminada" dense />
            <q-radio v-model="rowDensity" val="compact" label="Vista compacta" dense />
          </div>

          <div class="text-subtitle2 text-weight-bold q-mb-sm">Personalizar columnas</div>

          <div class="row no-wrap q-gutter-x-md" style="min-height: 340px;">
            <!-- Left: search + checkbox list -->
            <div class="col" style="min-width: 220px;">
              <q-input
                v-model="columnSearch"
                placeholder="Buscar"
                dense outlined clearable
                class="q-mb-sm"
              >
                <template v-slot:prepend><q-icon name="search" size="18px" /></template>
              </q-input>

              <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">Seleccionar columnas</div>
              <div class="col-select-list">
                <div
                  v-for="col in filteredAllColumns"
                  :key="col.key"
                  class="col-select-item"
                >
                  <q-checkbox
                    :model-value="columnDialogModel.includes(col.key)"
                    @update:model-value="toggleDialogColumn(col.key)"
                    :label="col.label"
                    dense
                    color="primary"
                  />
                </div>
              </div>
            </div>

            <!-- Right: ordered selected columns with drag -->
            <div class="col" style="min-width: 220px;">
              <div class="text-caption text-weight-medium q-mb-xs" style="color: #1976d2;">
                Columnas seleccionadas ({{ columnDialogModel.length }})
              </div>
              <div class="col-ordered-list">
                <div
                  v-for="(col, idx) in selectedColumnObjects"
                  :key="col.key"
                  class="col-ordered-item"
                  :class="{ 'col-dragging': dragIdx === idx }"
                  draggable="true"
                  @dragstart="onDragStart(idx, $event)"
                  @dragover="onDragOver(idx, $event)"
                  @dragend="onDragEnd"
                >
                  <q-icon name="drag_indicator" size="16px" color="grey-5" class="drag-handle q-mr-xs" />
                  <span class="text-body2">{{ col.label }}</span>
                </div>
              </div>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-pt-none">
          <q-btn flat label="Cancelar" no-caps v-close-popup color="grey-7" />
          <q-btn color="primary" label="Actualizar" no-caps unelevated @click="applyColumnSettings" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Mobile Filter Dialog (shown on small screens when showFilters is toggled) -->
    <q-dialog v-model="showFilters" position="right" full-height class="lt-md">
      <q-card style="width: 300px; max-width: 90vw;" class="full-height">
        <q-card-section>
          <div class="row items-center">
            <span class="text-subtitle1 text-weight-bold">{{ t('ticketList.filters') }}</span>
            <q-space />
            <q-btn flat dense round icon="close" v-close-popup />
          </div>
        </q-card-section>
        <q-card-section class="q-pt-none scroll" style="max-height: calc(100vh - 150px);">
          <q-input
            v-model="filters.search"
            :placeholder="t('common.search') + '...'"
            dense outlined clearable
            class="q-mb-md"
          >
            <template v-slot:prepend><q-icon name="search" /></template>
          </q-input>

          <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">{{ t('common.status') }}</div>
          <div class="q-mb-md">
            <q-checkbox
              v-for="s in statusValues"
              :key="s"
              v-model="filters.statuses"
              :val="s"
              :label="t(`tickets.statuses.${s}`)"
              dense class="full-width"
            />
          </div>

          <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">{{ t('common.priority') }}</div>
          <div class="q-mb-md">
            <q-checkbox
              v-for="p in priorityValues"
              :key="p"
              v-model="filters.priorities"
              :val="p"
              :label="t(`tickets.priorities.${p}`)"
              dense class="full-width"
            />
          </div>

          <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">{{ t('tickets.type') }}</div>
          <div class="q-mb-md">
            <q-checkbox
              v-for="tp in typeValues"
              :key="tp"
              v-model="filters.types"
              :val="tp"
              :label="t(`tickets.types.${tp}`)"
              dense class="full-width"
            />
          </div>
        </q-card-section>
        <q-card-actions>
          <q-btn color="primary" :label="t('ticketList.apply')" no-caps dense unelevated class="full-width" @click="applyFilters(); showFilters = false" />
          <q-btn flat :label="t('ticketList.clearFilters')" no-caps dense color="grey-7" class="full-width" @click="clearFilters" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Export Panel (Freshservice-style right drawer) -->
    <q-dialog v-model="showExportPanel" position="right" full-height>
      <q-card style="width: 480px; max-width: 90vw;" class="full-height column">
        <!-- Header -->
        <q-card-section class="q-pb-sm">
          <div class="row items-center">
            <div class="text-h6 text-weight-bold">Exportar tickets</div>
            <q-space />
            <q-btn flat dense round icon="close" v-close-popup />
          </div>
        </q-card-section>

        <q-separator />

        <!-- Scrollable content -->
        <q-card-section class="col scroll">
          <!-- Export format -->
          <div class="q-mb-md">
            <span class="text-body2 text-weight-medium q-mr-sm">Exportar como :</span>
            <q-radio v-model="exportFormat" val="csv" label="CSV" dense class="q-mr-md" />
            <q-radio v-model="exportFormat" val="excel" label="Excel" dense />
          </div>

          <!-- Date filter -->
          <div class="row items-center q-mb-lg q-gutter-x-sm">
            <span class="text-body2 text-weight-medium">Filtrar tickets por :</span>
            <q-select
              v-model="exportFilterField"
              :options="[
                { label: 'Hora Creacion', value: 'created_at' },
                { label: 'Hora Actualizacion', value: 'updated_at' },
                { label: 'Hora Resolucion', value: 'resolved_at' },
                { label: 'Hora Cierre', value: 'closed_at' },
              ]"
              emit-value map-options
              dense outlined
              style="min-width: 140px;"
            />
            <q-select
              v-model="exportFilterPeriod"
              :options="[
                { label: 'Ultimos 7 dias', value: '7d' },
                { label: 'Ultimos 30 dias', value: '30d' },
                { label: 'Ultimos 60 dias', value: '60d' },
                { label: 'Ultimos 90 dias', value: '90d' },
              ]"
              emit-value map-options
              dense outlined
              style="min-width: 140px;"
            />
          </div>

          <q-separator class="q-mb-md" />

          <!-- Field selection -->
          <div class="row items-center q-mb-sm">
            <div class="text-subtitle2 text-weight-bold">
              Seleccionar campos para exportar <span class="text-red">*</span>
            </div>
            <q-space />
            <span class="text-caption text-grey-7">{{ exportSelectedFields.length }} Campos seleccionados</span>
          </div>
          <div class="text-caption text-grey-6 q-mb-md">Elija al menos un campo para continuar</div>

          <!-- Select all -->
          <q-checkbox
            :model-value="exportSelectAll"
            @update:model-value="exportSelectAll = $event"
            label="Seleccionar todo"
            dense
            class="q-mb-sm full-width"
            :indeterminate-value="exportSelectedFields.length > 0 && exportSelectedFields.length < exportFieldOptions.length ? true : undefined"
          />

          <q-separator class="q-mb-sm" />

          <!-- Field checkboxes in two columns -->
          <div class="export-fields-grid">
            <q-checkbox
              v-for="field in exportFieldOptions"
              :key="field.key"
              v-model="exportSelectedFields"
              :val="field.key"
              :label="field.label"
              dense
              class="export-field-item"
            />
          </div>
        </q-card-section>

        <q-separator />

        <!-- Actions -->
        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat no-caps label="Cancelar" color="grey-7" v-close-popup />
          <q-btn
            color="primary" no-caps unelevated
            label="Exportar"
            :loading="exportLoading"
            :disable="exportSelectedFields.length === 0"
            @click="doExport"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
    <!-- Ticket Hover Card (Freshservice-style) -->
    <Teleport to="body">
      <transition name="hover-fade">
        <div
          v-if="hoveredTicket"
          class="ticket-hover-card shadow-4"
          :style="hoverCardStyle"
          @mouseenter="onHoverCardEnter"
          @mouseleave="onHoverCardLeave"
        >
          <div class="row items-center no-wrap q-mb-sm">
            <q-avatar
              size="36px"
              :style="{ backgroundColor: getAvatarColor(hoveredTicket.requester?.name) }"
              text-color="white"
              font-size="15px"
            >
              {{ getInitial(hoveredTicket.requester?.name) }}
            </q-avatar>
            <div class="q-ml-sm" style="min-width: 0;">
              <div class="text-body2">
                <strong>{{ hoveredTicket.requester?.name || 'Usuario' }}</strong>
                <span class="hover-card-meta"> informó {{ timeAgo(hoveredTicket.updated_at) }}</span>
              </div>
              <div class="text-caption hover-card-meta">{{ formatFullDate(hoveredTicket.updated_at) }}</div>
            </div>
          </div>
          <div class="text-body2 text-grey-8 q-mb-md hover-card-desc">
            {{ truncateText(hoveredTicket.description || '', 200) }}
          </div>
          <q-separator class="q-mb-sm" />
          <div class="row justify-center q-gutter-x-md">
            <q-btn
              flat no-caps dense
              icon="reply"
              label="Responder"
              color="grey-7"
              size="sm"
              @click.stop="goToTicketReply(hoveredTicket!)"
            />
            <q-btn
              flat no-caps dense
              icon="note_add"
              label="Añadir nota"
              color="grey-7"
              size="sm"
              @click.stop="goToTicketNote(hoveredTicket!)"
            />
          </div>
        </div>
      </transition>
    </Teleport>
  </q-page>
</template>

<style scoped>
.ticket-table :deep(tbody tr.ticket-row) {
  cursor: pointer;
  transition: background-color 0.15s;
}
.ticket-table :deep(tbody tr.ticket-row:hover) {
  background-color: #f5f9ff;
}
.ticket-table :deep(tbody tr.selected-row) {
  background-color: #e3f2fd;
}
.ticket-table :deep(th) {
  font-weight: 600;
  color: #666;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.priority-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}
.filter-panel {
  border-left: 1px solid #eee;
  padding-left: 16px;
}
.toolbar {
  min-height: 44px;
  padding: 4px 0;
}
.bulk-bar {
  border: 1px solid #90caf9;
}

/* Compact density */
.compact-density :deep(td) {
  padding-top: 4px !important;
  padding-bottom: 4px !important;
  font-size: 12px;
}
.compact-density :deep(th) {
  padding-top: 6px !important;
  padding-bottom: 6px !important;
}

/* Column dialog */
.col-select-list {
  max-height: 280px;
  overflow-y: auto;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  padding: 4px;
}

.col-select-item {
  padding: 2px 6px;
  border-radius: 4px;
}

.col-select-item:hover {
  background: #f5f7fa;
}

.col-ordered-list {
  max-height: 310px;
  overflow-y: auto;
  border: 1px solid #bbdefb;
  border-radius: 6px;
  padding: 4px;
  background: #fafcff;
}

.col-ordered-item {
  display: flex;
  align-items: center;
  padding: 6px 8px;
  margin-bottom: 2px;
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 4px;
  cursor: grab;
  user-select: none;
  transition: box-shadow 0.15s, opacity 0.15s;
}

.col-ordered-item:hover {
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

.col-ordered-item.col-dragging {
  opacity: 0.5;
  box-shadow: 0 2px 8px rgba(25, 118, 210, 0.3);
}

.drag-handle {
  cursor: grab;
}

/* Export fields grid (two columns like Freshservice) */
.export-fields-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2px 16px;
}

.export-field-item {
  padding: 3px 0;
}
</style>

<style>
/* Ticket hover card – unscoped so Teleport to body works */
.ticket-hover-card {
  position: fixed;
  z-index: 9999;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-left: 3px solid #1976d2;
  border-radius: 6px;
  padding: 14px 16px;
  width: 400px;
  max-width: 90vw;
  pointer-events: auto;
  color: #333;
}
.ticket-hover-card .hover-card-desc {
  line-height: 1.5;
  word-break: break-word;
  color: #555;
  font-size: 13px;
}
.ticket-hover-card .hover-card-meta {
  color: #888;
}

/* Dark mode */
.body--dark .ticket-hover-card {
  background: #1e1e2e;
  border-color: #3a3a4a;
  border-left-color: #42a5f5;
  color: #e0e0e0;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}
.body--dark .ticket-hover-card .hover-card-desc {
  color: #b0b0b8;
}
.body--dark .ticket-hover-card .hover-card-meta {
  color: #8888a0;
}
.body--dark .ticket-hover-card .q-separator {
  background-color: #3a3a4a;
}

.hover-fade-enter-active {
  transition: opacity 0.15s ease;
}
.hover-fade-leave-active {
  transition: opacity 0.1s ease;
}
.hover-fade-enter-from,
.hover-fade-leave-to {
  opacity: 0;
}
</style>
