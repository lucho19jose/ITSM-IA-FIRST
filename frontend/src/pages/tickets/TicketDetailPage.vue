<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { Notify } from 'quasar'
import { getTicket, updateTicket, assignTicket, addComment, suggestResponse, improveText, uploadTicketAttachments, deleteTicketAttachment, getTickets, mergeTicket, toggleSpam, toggleFavorite } from '@/api/tickets'
import { getAgents, getUser, getUserRecentTickets } from '@/api/users'
import { getCategories } from '@/api/categories'
import { getTimeEntries, addTimeEntry, deleteTimeEntry } from '@/api/timeEntries'
import { getTicketAssociations, createTicketAssociation, deleteTicketAssociation } from '@/api/ticketAssociations'
import { getRecentActivities } from '@/api/activities'
import { getScenarios, executeScenario } from '@/api/scenarios'
import { getAgentGroups } from '@/api/agentGroups'
import { getEcho } from '@/utils/echo'
import type { Ticket, User, Category, TimeEntry, TicketAssociation, ActivityLog, Scenario, AgentGroup } from '@/types'

const props = defineProps<{ id: string }>()
const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const loading = ref(true)
const ticket = ref<Ticket | null>(null)
const agents = ref<User[]>([])
const categories = ref<Category[]>([])
const newComment = ref('')
const isInternal = ref(false)
const commentLoading = ref(false)
const aiLoading = ref(false)
const aiSuggestion = ref<{ suggested_response: string; internal_note: string; relevant_kb_articles: any[] } | null>(null)
const activeTab = ref('details')
const savingProperties = ref(false)
const propertiesExpanded = ref(true)
const showReplyEditor = ref(false)
const improvingText = ref(false)
const replyAttachments = ref<File[]>([])
const uploadingAttachments = ref(false)
const fileInputRef = ref<HTMLInputElement | null>(null)

function triggerFileInput() {
  fileInputRef.value?.click()
}

function onFilesSelected(event: Event) {
  const input = event.target as HTMLInputElement
  if (!input.files?.length) return
  for (const file of Array.from(input.files)) {
    if (file.size > 10485760) {
      Notify.create({ type: 'warning', message: `${file.name} excede 10MB` })
      continue
    }
    replyAttachments.value.push(file)
  }
  input.value = '' // reset so same file can be re-selected
}

// Attachment preview
const showPreview = ref(false)
const previewAttachment = ref<{ filename: string; url: string; mime: string } | null>(null)
const previewZoom = ref(1)
const previewRotation = ref(0)
const previewIndex = ref(-1)

// ─── Time Tracking ──────────────────────────────────────────────────────────
const timeEntries = ref<TimeEntry[]>([])
const showTimeDialog = ref(false)
const timeForm = reactive({ hours: 1, note: '', executed_at: new Date().toISOString().slice(0, 10), billable: false })
const timeLoading = ref(false)

async function loadTimeEntries() {
  if (!ticket.value) return
  try {
    const res = await getTimeEntries(ticket.value.id)
    timeEntries.value = res.data || []
  } catch { /* ignore */ }
}

const totalTimeLogged = computed(() => timeEntries.value.reduce((sum, e) => sum + e.hours, 0))

async function onAddTimeEntry() {
  if (!ticket.value) return
  timeLoading.value = true
  try {
    const res = await addTimeEntry(ticket.value.id, {
      hours: timeForm.hours,
      note: timeForm.note || undefined,
      executed_at: timeForm.executed_at,
      billable: timeForm.billable,
    })
    timeEntries.value.unshift(res.data)
    showTimeDialog.value = false
    timeForm.hours = 1
    timeForm.note = ''
    timeForm.executed_at = new Date().toISOString().slice(0, 10)
    timeForm.billable = false
    Notify.create({ type: 'positive', message: 'Tiempo registrado' })
  } finally { timeLoading.value = false }
}

async function onDeleteTimeEntry(entryId: number) {
  if (!ticket.value) return
  try {
    await deleteTimeEntry(ticket.value.id, entryId)
    timeEntries.value = timeEntries.value.filter(e => e.id !== entryId)
    Notify.create({ type: 'positive', message: 'Entrada eliminada' })
  } catch { /* ignore */ }
}

// ─── Ticket Associations ────────────────────────────────────────────────────
const associations = ref<TicketAssociation[]>([])
const showAssocDialog = ref(false)
const assocForm = reactive({ search: '', type: 'related' as string })
const assocSearchResults = ref<Ticket[]>([])
const assocSearching = ref(false)
const assocLoading = ref(false)

async function loadAssociations() {
  if (!ticket.value) return
  try {
    const res = await getTicketAssociations(ticket.value.id)
    associations.value = res.data || []
  } catch { /* ignore */ }
}

async function onSearchTicketsForAssoc() {
  if (!assocForm.search.trim()) return
  assocSearching.value = true
  try {
    const res = await getTickets({ search: assocForm.search, per_page: 10 })
    assocSearchResults.value = (res.data || []).filter((t: Ticket) => t.id !== ticket.value?.id)
  } catch { /* ignore */ }
  finally { assocSearching.value = false }
}

async function onCreateAssociation(relatedId: number) {
  if (!ticket.value) return
  assocLoading.value = true
  try {
    const res = await createTicketAssociation(ticket.value.id, {
      related_ticket_id: relatedId,
      type: assocForm.type,
    })
    associations.value.unshift(res.data)
    showAssocDialog.value = false
    assocForm.search = ''
    assocSearchResults.value = []
    Notify.create({ type: 'positive', message: 'Ticket asociado' })
  } finally { assocLoading.value = false }
}

async function onDeleteAssociation(assocId: number) {
  if (!ticket.value) return
  try {
    await deleteTicketAssociation(ticket.value.id, assocId)
    associations.value = associations.value.filter(a => a.id !== assocId)
    Notify.create({ type: 'positive', message: 'Asociación eliminada' })
  } catch { /* ignore */ }
}

// ─── Activity Log (per-ticket) ──────────────────────────────────────────────
const ticketActivities = ref<ActivityLog[]>([])
const activitiesLoading = ref(false)

async function loadTicketActivities() {
  if (!ticket.value) return
  activitiesLoading.value = true
  try {
    const res = await getRecentActivities({ per_page: 50 })
    // Filter to only this ticket's activities
    ticketActivities.value = (res.data || []).filter(
      (a: ActivityLog) => a.properties?.ticket_id === ticket.value?.id
    )
  } catch { /* ignore */ }
  finally { activitiesLoading.value = false }
}

// ─── Resolution ─────────────────────────────────────────────────────────────
const resolutionNotes = ref('')
const savingResolution = ref(false)

async function onSaveResolution() {
  if (!ticket.value) return
  savingResolution.value = true
  try {
    const res = await updateTicket(ticket.value.id, { resolution_notes: resolutionNotes.value } as any)
    ticket.value = { ...ticket.value, ...res.data }
    Notify.create({ type: 'positive', message: 'Resolución guardada' })
  } finally { savingResolution.value = false }
}

// ─── Print ──────────────────────────────────────────────────────────────────
function onPrintTicket() {
  window.print()
}

// ─── Merge Tickets ──────────────────────────────────────────────────────────
const showMergeDialog = ref(false)
const mergeSearch = ref('')
const mergeSearchResults = ref<Ticket[]>([])
const mergeSearching = ref(false)
const mergeLoading = ref(false)

async function onSearchTicketsForMerge() {
  if (!mergeSearch.value.trim()) return
  mergeSearching.value = true
  try {
    const res = await getTickets({ search: mergeSearch.value, per_page: 10 })
    mergeSearchResults.value = (res.data || []).filter((t: Ticket) => t.id !== ticket.value?.id)
  } catch { /* ignore */ }
  finally { mergeSearching.value = false }
}

async function onMergeTicket(sourceId: number) {
  if (!ticket.value) return
  mergeLoading.value = true
  try {
    const res = await mergeTicket(ticket.value.id, sourceId)
    ticket.value = res.data
    syncEditProps()
    showMergeDialog.value = false
    mergeSearch.value = ''
    mergeSearchResults.value = []
    Notify.create({ type: 'positive', message: res.message || 'Ticket combinado' })
  } finally { mergeLoading.value = false }
}

// ─── Spam ───────────────────────────────────────────────────────────────────
async function onToggleSpam() {
  if (!ticket.value) return
  try {
    const res = await toggleSpam(ticket.value.id)
    ticket.value = { ...ticket.value, ...res.data }
    Notify.create({ type: 'info', message: res.message })
  } catch { /* ignore */ }
}

// ─── Favorite ───────────────────────────────────────────────────────────────
const isFavorite = ref(false)

async function onToggleFavorite() {
  if (!ticket.value) return
  try {
    const res = await toggleFavorite(ticket.value.id)
    isFavorite.value = res.is_favorite
    Notify.create({ type: 'info', message: res.message })
  } catch { /* ignore */ }
}

// ─── Scenarios ──────────────────────────────────────────────────────────────
const scenarios = ref<Scenario[]>([])
const showScenarioDialog = ref(false)
const scenarioLoading = ref(false)

async function loadScenarios() {
  try {
    const res = await getScenarios()
    scenarios.value = res.data || []
  } catch { /* ignore */ }
}

async function onRunScenario(scenarioId: number) {
  if (!ticket.value) return
  scenarioLoading.value = true
  try {
    const res = await executeScenario(ticket.value.id, scenarioId)
    ticket.value = res.data
    syncEditProps()
    showScenarioDialog.value = false
    Notify.create({ type: 'positive', message: res.message || 'Escenario ejecutado' })
  } finally { scenarioLoading.value = false }
}

// ─── Agent Groups ───────────────────────────────────────────────────────────
const agentGroups = ref<AgentGroup[]>([])

async function loadAgentGroups() {
  try {
    const res = await getAgentGroups()
    agentGroups.value = res.data || []
  } catch { /* ignore */ }
}

// ─── Share (copy link) ──────────────────────────────────────────────────────
function onCopyLink() {
  const url = window.location.href
  navigator.clipboard.writeText(url).then(() => {
    Notify.create({ type: 'positive', message: 'Enlace copiado al portapapeles' })
  })
}

// ─── Participants ───────────────────────────────────────────────────────────
const participants = computed(() => {
  if (!ticket.value) return []
  const map = new Map<number, { id: number; name: string; avatar_url: string | null; role: string; count: number }>()
  // Requester
  if (ticket.value.requester) {
    map.set(ticket.value.requester.id, {
      id: ticket.value.requester.id,
      name: ticket.value.requester.name,
      avatar_url: ticket.value.requester.avatar_url,
      role: 'Solicitante',
      count: 0,
    })
  }
  // Assignee
  if (ticket.value.assignee && !map.has(ticket.value.assignee.id)) {
    map.set(ticket.value.assignee.id, {
      id: ticket.value.assignee.id,
      name: ticket.value.assignee.name,
      avatar_url: ticket.value.assignee.avatar_url,
      role: 'Agente',
      count: 0,
    })
  }
  // Commenters
  for (const c of ticket.value.comments || []) {
    if (c.user) {
      const existing = map.get(c.user.id)
      if (existing) {
        existing.count++
      } else {
        map.set(c.user.id, {
          id: c.user.id,
          name: c.user.name,
          avatar_url: c.user.avatar_url ?? null,
          role: 'Participante',
          count: 1,
        })
      }
    }
  }
  return Array.from(map.values())
})

// Requester detail panel
const showRequesterPanel = ref(false)
const requesterDetail = ref<User | null>(null)
const requesterTickets = ref<Array<{ id: number; ticket_number: string; title: string; status: string; priority: string; assignee_name: string | null; created_at: string }>>([])
const requesterLoading = ref(false)

async function openRequesterPanel() {
  if (!ticket.value?.requester_id) return
  showRequesterPanel.value = true
  requesterLoading.value = true
  try {
    const [userRes, ticketsRes] = await Promise.all([
      getUser(ticket.value.requester_id),
      getUserRecentTickets(ticket.value.requester_id),
    ])
    requesterDetail.value = userRes.data
    requesterTickets.value = ticketsRes.data
  } catch { /* ignore */ }
  finally { requesterLoading.value = false }
}

function getStatusLabel(status: string): string {
  const map: Record<string, string> = {
    open: 'Abierto', in_progress: 'En Progreso', pending: 'Pendiente',
    resolved: 'Resuelto', closed: 'Cerrado',
  }
  return map[status] || status
}

function getStatusBadgeColor(status: string): string {
  const map: Record<string, string> = {
    open: 'warning', in_progress: 'primary', pending: 'purple',
    resolved: 'positive', closed: 'grey',
  }
  return map[status] || 'grey'
}

function getPriorityLabel(priority: string): string {
  const map: Record<string, string> = {
    low: 'Baja', medium: 'Media', high: 'Alta', urgent: 'Urgente',
  }
  return map[priority] || priority
}

function getRoleLabel(role: string): string {
  const map: Record<string, string> = {
    admin: 'Administrador', agent: 'Agente', end_user: 'Usuario final',
  }
  return map[role] || role
}

function getPriorityDotColor(priority: string): string {
  const map: Record<string, string> = {
    low: '#4caf50', medium: '#2196f3', high: '#ff9800', urgent: '#f44336',
  }
  return map[priority] || '#9e9e9e'
}

// Editable properties (local copy)
const editProps = reactive({
  priority: '' as string,
  status: '' as string,
  source: '' as string,
  type: '' as string,
  urgency: null as string | null,
  impact: null as string | null,
  category_id: null as number | null,
  assigned_to: null as number | null,
  agent_group_id: null as number | null,
})

const canManage = computed(() => auth.isAdmin || auth.isAgent)

// Ticket-level attachments (not linked to any comment)
const ticketLevelAttachments = computed(() =>
  (ticket.value?.attachments || []).filter(a => !a.comment_id)
)

const statusOptions = [
  { label: 'Abierto', value: 'open' },
  { label: 'En Progreso', value: 'in_progress' },
  { label: 'Pendiente', value: 'pending' },
  { label: 'Resuelto', value: 'resolved' },
  { label: 'Cerrado', value: 'closed' },
]

const priorityOptions = [
  { label: 'Baja', value: 'low' },
  { label: 'Media', value: 'medium' },
  { label: 'Alta', value: 'high' },
  { label: 'Urgente', value: 'urgent' },
]

const typeOptions = [
  { label: 'Incidente', value: 'incident' },
  { label: 'Solicitud', value: 'request' },
  { label: 'Problema', value: 'problem' },
  { label: 'Cambio', value: 'change' },
]

const sourceOptions = [
  { label: 'Portal', value: 'portal' },
  { label: 'Email', value: 'email' },
  { label: 'Chatbot', value: 'chatbot' },
  { label: 'Catalogo', value: 'catalog' },
  { label: 'API', value: 'api' },
  { label: 'Telefono', value: 'phone' },
]

const urgencyOptions = [
  { label: 'Baja', value: 'low' },
  { label: 'Media', value: 'medium' },
  { label: 'Alta', value: 'high' },
]

const impactOptions = [
  { label: 'Baja', value: 'low' },
  { label: 'Media', value: 'medium' },
  { label: 'Alta', value: 'high' },
]

const categoryOptions = computed(() =>
  categories.value.map(c => ({ label: c.name, value: c.id }))
)

const agentOptions = computed(() => [
  { label: 'Sin asignar', value: null },
  ...agents.value.map(a => ({
    label: a.id === auth.user?.id ? `${a.name} (Yo)` : a.name,
    value: a.id,
  })),
])

const agentGroupOptions = computed(() => [
  { label: 'Sin grupo', value: null },
  ...agentGroups.value.filter(g => g.is_active).map(g => ({
    label: g.name,
    value: g.id,
  })),
])

const propertiesDirty = computed(() => {
  if (!ticket.value) return false
  return (
    editProps.priority !== ticket.value.priority ||
    editProps.status !== ticket.value.status ||
    editProps.type !== ticket.value.type ||
    editProps.source !== ticket.value.source ||
    editProps.urgency !== (ticket.value.urgency || null) ||
    editProps.impact !== (ticket.value.impact || null) ||
    editProps.category_id !== ticket.value.category_id ||
    editProps.assigned_to !== ticket.value.assigned_to ||
    editProps.agent_group_id !== (ticket.value.agent_group_id || null)
  )
})

function syncEditProps() {
  if (!ticket.value) return
  editProps.priority = ticket.value.priority
  editProps.status = ticket.value.status
  editProps.type = ticket.value.type
  editProps.source = ticket.value.source
  editProps.urgency = ticket.value.urgency || null
  editProps.impact = ticket.value.impact || null
  editProps.category_id = ticket.value.category_id
  editProps.assigned_to = ticket.value.assigned_to
  editProps.agent_group_id = ticket.value.agent_group_id || null
}

onMounted(async () => {
  try {
    const promises: Promise<any>[] = [
      getTicket(Number(props.id)),
      getCategories(),
    ]
    if (canManage.value) promises.push(getAgents())

    const results = await Promise.all(promises)
    ticket.value = results[0].data
    categories.value = results[1].data
    if (results[2]) agents.value = results[2].data
    syncEditProps()

    // Initialize resolution notes
    resolutionNotes.value = ticket.value?.resolution_notes || ''

    // Load time entries from eager-loaded data or fetch separately
    if (ticket.value?.time_entries) {
      timeEntries.value = ticket.value.time_entries
    } else if (canManage.value) {
      loadTimeEntries()
    }

    // Load associations, activities, scenarios, groups in background
    if (canManage.value) {
      loadAssociations()
      loadTicketActivities()
      loadScenarios()
      loadAgentGroups()
    }

    // ─── Real-time: listen for updates and new comments ────────────
    const echo = getEcho()
    if (echo) {
      echo.private(`ticket.${props.id}`)
        .listen('TicketUpdated', (e: any) => {
          if (ticket.value) {
            // Merge updated fields into the ticket
            Object.assign(ticket.value, {
              status: e.status,
              priority: e.priority,
              type: e.type,
              assigned_to: e.assigned_to,
              updated_at: e.updated_at,
            })
            syncEditProps()
            Notify.create({
              type: 'info',
              message: `Ticket actualizado: ${e.changed_fields.join(', ')}`,
              icon: 'sync',
              timeout: 3000,
            })
          }
        })
        .listen('TicketCommentAdded', (e: any) => {
          if (ticket.value?.comments) {
            // Avoid duplicates
            if (!ticket.value.comments.find(c => c.id === e.id)) {
              ticket.value.comments.push(e)
              Notify.create({
                type: 'info',
                message: `${e.user?.name || 'Alguien'} agrego un comentario`,
                icon: 'chat',
                timeout: 3000,
              })
            }
          }
        })
    }
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  const echo = getEcho()
  if (echo) echo.leave(`ticket.${props.id}`)
})

async function onSaveProperties() {
  if (!propertiesDirty.value) return
  savingProperties.value = true
  try {
    const payload: Record<string, any> = {}
    if (editProps.priority !== ticket.value!.priority) payload.priority = editProps.priority
    if (editProps.status !== ticket.value!.status) payload.status = editProps.status
    if (editProps.type !== ticket.value!.type) payload.type = editProps.type
    if (editProps.source !== ticket.value!.source) payload.source = editProps.source
    if (editProps.urgency !== (ticket.value!.urgency || null)) payload.urgency = editProps.urgency
    if (editProps.impact !== (ticket.value!.impact || null)) payload.impact = editProps.impact
    if (editProps.category_id !== ticket.value!.category_id) payload.category_id = editProps.category_id

    if (editProps.assigned_to !== ticket.value!.assigned_to) {
      if (editProps.assigned_to) {
        await assignTicket(Number(props.id), editProps.assigned_to)
      }
      payload.assigned_to = editProps.assigned_to
    }

    if (editProps.agent_group_id !== (ticket.value!.agent_group_id || null)) payload.agent_group_id = editProps.agent_group_id

    if (Object.keys(payload).length > 0) {
      const res = await updateTicket(Number(props.id), payload as any)
      ticket.value = { ...ticket.value!, ...res.data }
    }
    syncEditProps()
    Notify.create({ type: 'positive', message: 'Propiedades actualizadas' })
  } catch {
    /* handled by interceptor */
  } finally {
    savingProperties.value = false
  }
}

async function onAddComment() {
  if (!newComment.value.trim()) return
  commentLoading.value = true
  try {
    const res = await addComment(Number(props.id), {
      body: newComment.value,
      is_internal: isInternal.value,
    })
    const newCommentData = res.data
    newCommentData.attachments = []

    // Upload attachments linked to this comment
    if (replyAttachments.value.length > 0 && ticket.value) {
      try {
        const attRes = await uploadTicketAttachments(ticket.value.id, replyAttachments.value, newCommentData.id)
        newCommentData.attachments = attRes.data
        replyAttachments.value = []
      } catch {
        Notify.create({ type: 'warning', message: 'Comentario guardado pero algunos archivos no se subieron' })
      }
    }

    ticket.value?.comments?.push(newCommentData)

    newComment.value = ''
    isInternal.value = false
    showReplyEditor.value = false
    Notify.create({ type: 'positive', message: 'Comentario agregado' })
  } finally {
    commentLoading.value = false
  }
}

function openReply(internal: boolean) {
  isInternal.value = internal
  showReplyEditor.value = true
  activeTab.value = 'details'
}

async function onSuggestResponse() {
  aiLoading.value = true
  try {
    const res = await suggestResponse(Number(props.id))
    aiSuggestion.value = res.data
  } catch {
    Notify.create({ type: 'negative', message: 'Error al obtener sugerencia IA' })
  } finally {
    aiLoading.value = false
  }
}

function useSuggestion() {
  if (aiSuggestion.value) {
    newComment.value = mdToHtml(aiSuggestion.value.suggested_response)
    showReplyEditor.value = true
    aiSuggestion.value = null
  }
}

async function onImproveText() {
  if (!newComment.value.trim()) return
  improvingText.value = true
  try {
    // Strip HTML tags to send plain text to the AI
    const plainText = newComment.value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
    const res = await improveText(plainText)
    // Convert line breaks to HTML for the editor
    newComment.value = res.data.improved_text.replace(/\n/g, '<br>')
    Notify.create({
      type: 'positive',
      message: `Texto mejorado (${res.data.processing_time_ms}ms)`,
      timeout: 2000,
    })
  } catch {
    Notify.create({ type: 'negative', message: 'Error al mejorar el texto' })
  } finally {
    improvingText.value = false
  }
}

async function onCloseTicket() {
  try {
    const res = await updateTicket(Number(props.id), { status: 'closed' } as any)
    ticket.value = { ...ticket.value!, ...res.data }
    syncEditProps()
    Notify.create({ type: 'positive', message: 'Ticket cerrado' })
  } catch { /* handled */ }
}

async function onReopenTicket() {
  try {
    const res = await updateTicket(Number(props.id), { status: 'open' } as any)
    ticket.value = { ...ticket.value!, ...res.data }
    syncEditProps()
    Notify.create({ type: 'positive', message: 'Ticket reabierto' })
  } catch { /* handled */ }
}

function navigateTicket(direction: number) {
  const nextId = Number(props.id) + direction
  if (nextId > 0) router.push(`/tickets/${nextId}`)
}

function getStatusColor(status: string): string {
  const colors: Record<string, string> = {
    open: 'warning', in_progress: 'primary', pending: 'purple',
    resolved: 'positive', closed: 'grey',
  }
  return colors[status] || 'grey'
}

function getPriorityDot(priority: string): string {
  const colors: Record<string, string> = {
    low: '#4caf50', medium: '#2196f3', high: '#ff9800', urgent: '#f44336',
  }
  return colors[priority] || '#9e9e9e'
}

const sourceConfig: Record<string, { icon: string; color: string; label: string }> = {
  portal: { icon: 'language', color: 'primary', label: 'Portal' },
  email: { icon: 'email', color: 'orange', label: 'Email' },
  chatbot: { icon: 'smart_toy', color: 'purple', label: 'Chatbot' },
  catalog: { icon: 'storefront', color: 'teal', label: 'Catalogo' },
  api: { icon: 'api', color: 'cyan', label: 'API' },
  phone: { icon: 'phone', color: 'green', label: 'Telefono' },
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString('es-PE', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 60) return `hace ${mins}m`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `hace ${hours}h`
  const days = Math.floor(hours / 24)
  return `hace ${days} dias`
}

function slaTimeLeft(dateStr: string | null): { text: string; overdue: boolean } | null {
  if (!dateStr) return null
  const diff = new Date(dateStr).getTime() - Date.now()
  const overdue = diff < 0
  const absDiff = Math.abs(diff)
  const days = Math.floor(absDiff / (1000 * 60 * 60 * 24))
  const hours = Math.floor((absDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
  let text = ''
  if (days > 0) text += `${days}d `
  text += `${hours}h`
  if (overdue) text = `-${text}`
  return { text, overdue }
}

function formatSlaDate(dateStr: string | null): string {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleString('es-PE', {
    weekday: 'short', day: 'numeric', month: 'short', year: 'numeric',
    hour: 'numeric', minute: '2-digit', hour12: true,
  })
}

function getInitial(name?: string): string {
  return name ? name.charAt(0).toUpperCase() : '?'
}

function getAvatarColor(name?: string): string {
  if (!name) return '#9e9e9e'
  const colors = ['#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#009688', '#4caf50', '#ff9800', '#795548']
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  return colors[Math.abs(hash) % colors.length]
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}

function getFileIcon(mime: string): string {
  if (mime?.startsWith('image/')) return 'image'
  if (mime === 'application/pdf') return 'picture_as_pdf'
  if (mime?.includes('word') || mime?.includes('document')) return 'description'
  if (mime?.includes('sheet') || mime?.includes('excel')) return 'table_chart'
  return 'attach_file'
}

function getAttachmentUrl(path: string): string {
  return `/storage/${path}`
}

async function onUploadReplyAttachments() {
  if (!replyAttachments.value.length || !ticket.value) return
  uploadingAttachments.value = true
  try {
    const res = await uploadTicketAttachments(ticket.value.id, replyAttachments.value)
    if (ticket.value.attachments) {
      ticket.value.attachments.push(...res.data)
    } else {
      ticket.value.attachments = res.data
    }
    replyAttachments.value = []
    Notify.create({ type: 'positive', message: 'Archivos adjuntados', timeout: 2000 })
  } catch {
    Notify.create({ type: 'negative', message: 'Error al subir archivos' })
  } finally {
    uploadingAttachments.value = false
  }
}

async function onDeleteAttachment(attachmentId: number) {
  if (!ticket.value) return
  try {
    await deleteTicketAttachment(ticket.value.id, attachmentId)
    if (ticket.value.attachments) {
      ticket.value.attachments = ticket.value.attachments.filter(a => a.id !== attachmentId)
    }
    Notify.create({ type: 'positive', message: 'Archivo eliminado', timeout: 2000 })
  } catch {
    Notify.create({ type: 'negative', message: 'Error al eliminar archivo' })
  }
}

function canPreview(mime: string): boolean {
  if (!mime) return false
  return mime.startsWith('image/') || mime === 'application/pdf'
    || mime.startsWith('text/') || mime.startsWith('video/')
}

// All previewable attachments for navigation
const previewableAttachments = computed(() =>
  (ticket.value?.attachments || []).filter(a => canPreview(a.mime_type))
)

function openPreview(att: { filename: string; path: string; mime_type: string }) {
  previewAttachment.value = {
    filename: att.filename,
    url: getAttachmentUrl(att.path),
    mime: att.mime_type,
  }
  previewZoom.value = 1
  previewRotation.value = 0
  // Find index for navigation
  previewIndex.value = previewableAttachments.value.findIndex(
    a => a.path === att.path
  )
  showPreview.value = true
}

function previewZoomIn() {
  previewZoom.value = Math.min(previewZoom.value + 0.25, 5)
}

function previewZoomOut() {
  previewZoom.value = Math.max(previewZoom.value - 0.25, 0.25)
}

function previewResetZoom() {
  previewZoom.value = 1
  previewRotation.value = 0
}

function previewRotateRight() {
  previewRotation.value = (previewRotation.value + 90) % 360
}

function previewRotateLeft() {
  previewRotation.value = (previewRotation.value - 90 + 360) % 360
}

function previewNavigate(direction: 1 | -1) {
  const list = previewableAttachments.value
  if (list.length <= 1) return
  const newIdx = (previewIndex.value + direction + list.length) % list.length
  const att = list[newIdx]
  previewAttachment.value = {
    filename: att.filename,
    url: getAttachmentUrl(att.path),
    mime: att.mime_type,
  }
  previewIndex.value = newIdx
  previewZoom.value = 1
  previewRotation.value = 0
}

function onPreviewWheel(e: WheelEvent) {
  if (!previewAttachment.value?.mime?.startsWith('image/')) return
  e.preventDefault()
  if (e.deltaY < 0) previewZoomIn()
  else previewZoomOut()
}

function mdToHtml(text: string): string {
  if (!text) return ''
  return text
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/^(\d+)\.\s+/gm, '<br>$1. ')
    .replace(/^- /gm, '<br>• ')
    .replace(/\n{2,}/g, '</p><p>')
    .replace(/\n/g, '<br>')
}
</script>

<template>
  <q-page class="ticket-detail-page">
    <!-- Loading -->
    <div v-if="loading" class="flex flex-center q-pa-xl" style="min-height: 400px">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <template v-else-if="ticket">
      <!-- Top Action Bar (Freshservice-style sticky) -->
      <div class="ticket-topbar">
        <div class="row items-center no-wrap">
          <!-- Left: breadcrumb + icons -->
          <q-btn flat dense round icon="check_circle_outline" size="sm" color="grey-6" class="q-mr-xs">
            <q-tooltip>Aprobar</q-tooltip>
          </q-btn>
          <q-btn flat dense round icon="visibility" size="sm" color="grey-6" class="q-mr-xs">
            <q-tooltip>Observar</q-tooltip>
          </q-btn>
          <q-btn flat dense round icon="edit" size="sm" color="grey-6" class="q-mr-sm">
            <q-tooltip>Editar</q-tooltip>
          </q-btn>

          <div class="topbar-title ellipsis">{{ ticket.title }}</div>

          <q-space />

          <!-- Right: action buttons -->
          <div class="row items-center no-wrap q-gutter-x-xs">
            <q-btn flat dense :icon="isFavorite ? 'star' : 'star_border'" :color="isFavorite ? 'amber' : 'grey-6'" size="sm" @click="onToggleFavorite">
              <q-tooltip>{{ isFavorite ? 'Quitar de favoritos' : 'Agregar a favoritos' }}</q-tooltip>
            </q-btn>

            <q-btn v-if="canManage" outline color="grey-8" no-caps dense class="topbar-btn">
              Compartir
              <q-icon name="arrow_drop_down" size="18px" />
              <q-menu>
                <q-list dense style="min-width: 200px">
                  <q-item clickable v-close-popup @click="onCopyLink">
                    <q-item-section avatar><q-icon name="content_copy" size="18px" /></q-item-section>
                    <q-item-section>Copiar enlace</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>

            <q-btn v-if="canManage" outline color="grey-8" no-caps dense class="topbar-btn"
              @click="router.push(`/tickets/${props.id}/edit`)">
              Editar
            </q-btn>

            <q-btn v-if="canManage" outline color="grey-8" no-caps dense class="topbar-btn">
              Responder
              <q-icon name="arrow_drop_down" size="18px" />
              <q-menu>
                <q-list dense style="min-width: 180px">
                  <q-item clickable v-close-popup @click="openReply(false)">
                    <q-item-section avatar><q-icon name="reply" size="18px" /></q-item-section>
                    <q-item-section>Responder</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="openReply(true)">
                    <q-item-section avatar><q-icon name="note" size="18px" /></q-item-section>
                    <q-item-section>Añadir nota</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>

            <q-btn v-if="canManage" outline color="grey-8" no-caps dense class="topbar-btn">
              Asociar
              <q-icon name="arrow_drop_down" size="18px" />
              <q-menu>
                <q-list dense style="min-width: 180px">
                  <q-item clickable v-close-popup @click="assocForm.type = 'related'; showAssocDialog = true">
                    <q-item-section avatar><q-icon name="link" size="18px" /></q-item-section>
                    <q-item-section>Relacionado</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="assocForm.type = 'parent'; showAssocDialog = true">
                    <q-item-section avatar><q-icon name="account_tree" size="18px" /></q-item-section>
                    <q-item-section>Padre</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="assocForm.type = 'child'; showAssocDialog = true">
                    <q-item-section avatar><q-icon name="subdirectory_arrow_right" size="18px" /></q-item-section>
                    <q-item-section>Hijo</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="assocForm.type = 'cause'; showAssocDialog = true">
                    <q-item-section avatar><q-icon name="warning_amber" size="18px" /></q-item-section>
                    <q-item-section>Causa</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>

            <q-btn
              v-if="canManage && ticket.status !== 'closed'"
              outline color="grey-8" no-caps dense class="topbar-btn"
              @click="onCloseTicket"
            >
              Cerrar
            </q-btn>
            <q-btn
              v-if="canManage && ticket.status === 'closed'"
              outline color="primary" no-caps dense class="topbar-btn"
              @click="onReopenTicket"
            >
              Reabrir
            </q-btn>

            <q-btn flat dense round icon="more_vert" color="grey-7" size="sm">
              <q-menu>
                <q-list dense style="min-width: 220px">
                  <q-item v-if="canManage" clickable v-close-popup @click="showMergeDialog = true">
                    <q-item-section avatar><q-icon name="merge_type" size="18px" /></q-item-section>
                    <q-item-section>Combinar</q-item-section>
                  </q-item>
                  <q-item v-if="canManage && scenarios.length" clickable v-close-popup @click="showScenarioDialog = true">
                    <q-item-section avatar><q-icon name="play_circle_outline" size="18px" /></q-item-section>
                    <q-item-section>Ejecutar situación</q-item-section>
                  </q-item>
                  <q-item v-if="canManage" clickable v-close-popup @click="showTimeDialog = true">
                    <q-item-section avatar><q-icon name="schedule" size="18px" /></q-item-section>
                    <q-item-section>Añadir hora</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="onPrintTicket">
                    <q-item-section avatar><q-icon name="print" size="18px" /></q-item-section>
                    <q-item-section>Imprimir</q-item-section>
                  </q-item>
                  <q-separator />
                  <q-item v-if="canManage" clickable v-close-popup @click="onToggleSpam">
                    <q-item-section avatar><q-icon name="report" size="18px" :color="ticket.is_spam ? 'positive' : 'warning'" /></q-item-section>
                    <q-item-section>{{ ticket.is_spam ? 'Desmarcar basura' : 'Marcar como basura' }}</q-item-section>
                  </q-item>
                  <q-item v-if="canManage" clickable v-close-popup class="text-negative">
                    <q-item-section avatar><q-icon name="delete" size="18px" color="negative" /></q-item-section>
                    <q-item-section>Eliminar</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>

            <q-separator vertical class="q-mx-xs" style="height: 24px" />

            <q-btn flat dense round icon="chevron_left" color="grey-7" size="sm" @click="navigateTicket(-1)">
              <q-tooltip>Ticket anterior</q-tooltip>
            </q-btn>
            <q-btn flat dense round icon="chevron_right" color="grey-7" size="sm" @click="navigateTicket(1)">
              <q-tooltip>Ticket siguiente</q-tooltip>
            </q-btn>
          </div>
        </div>
      </div>

      <!-- Breadcrumb bar -->
      <div class="ticket-breadcrumb-bar">
        <q-breadcrumbs class="text-grey-7">
          <q-breadcrumbs-el label="Tickets" to="/tickets" icon="confirmation_number" />
          <q-breadcrumbs-el :label="'#' + ticket.ticket_number" />
        </q-breadcrumbs>
      </div>

      <!-- Main Content Area -->
      <div class="row ticket-body">
        <!-- LEFT: Main content (scrollable) -->
        <div class="col ticket-main">
          <!-- Ticket Header -->
          <div class="ticket-header">
            <div class="row items-start">
              <div class="ticket-type-icon q-mr-md" :style="{ backgroundColor: getPriorityDot(ticket.priority) }">
                <q-icon
                  :name="ticket.type === 'incident' ? 'warning' : ticket.type === 'request' ? 'description' : ticket.type === 'problem' ? 'bug_report' : 'swap_horiz'"
                  size="28px" color="white"
                />
              </div>
              <div class="col">
                <div class="ticket-title">{{ ticket.title }}</div>
                <div class="ticket-meta text-grey-7">
                  <span
                    class="text-weight-medium text-primary"
                    style="cursor: pointer;"
                    @click="openRequesterPanel"
                  >{{ ticket.requester?.name }}</span>
                  informado el {{ timeAgo(ticket.created_at) }}
                  ({{ formatDate(ticket.created_at) }})
                  a traves de
                  <q-icon :name="sourceConfig[ticket.source]?.icon || 'help'" size="16px" class="q-mx-xs" />
                  {{ sourceConfig[ticket.source]?.label || ticket.source }}
                </div>
              </div>
            </div>
          </div>

          <!-- Tabs (Freshservice style) -->
          <q-tabs
            v-model="activeTab"
            no-caps
            active-color="primary"
            indicator-color="primary"
            class="ticket-tabs text-grey-7"
            align="left"
            dense
          >
            <q-tab name="details" label="Detalles" />
            <q-tab name="related">
              <span>Tickets relacionados</span>
              <q-badge v-if="associations.length" color="grey-6" class="q-ml-xs">{{ associations.length }}</q-badge>
            </q-tab>
            <q-tab name="time">
              <span>Tiempo</span>
              <q-badge v-if="timeEntries.length" color="grey-6" class="q-ml-xs">{{ totalTimeLogged.toFixed(1) }}h</q-badge>
            </q-tab>
            <q-tab name="activity" label="Actividad" />
            <q-tab name="participants">
              <span>Participantes</span>
              <q-badge v-if="participants.length" color="grey-6" class="q-ml-xs">{{ participants.length }}</q-badge>
            </q-tab>
            <q-tab name="resolution" label="Resolución" />
          </q-tabs>

          <q-separator />

          <!-- Tab Panels -->
          <q-tab-panels v-model="activeTab" animated class="ticket-panels">
            <!-- Details Tab -->
            <q-tab-panel name="details" class="q-pa-none">
              <!-- Description Card (Freshservice grey bg) -->
              <div class="ticket-section">
                <div class="description-card">
                  <div class="section-title">Descripcion</div>
                  <div class="section-content" v-html="ticket.description"></div>
                </div>
              </div>

              <!-- Attachments (ticket-level only, not linked to comments) -->
              <div v-if="ticketLevelAttachments.length" class="ticket-section">
                <div class="section-title">
                  Archivos adjuntos
                  <q-badge color="grey-6" class="q-ml-sm">{{ ticketLevelAttachments.length }}</q-badge>
                </div>

                <!-- Image thumbnails -->
                <div v-if="ticketLevelAttachments.filter(a => a.mime_type?.startsWith('image/')).length" class="attachment-thumbnails q-mb-sm">
                  <div
                    v-for="att in ticketLevelAttachments.filter(a => a.mime_type?.startsWith('image/'))"
                    :key="'thumb-' + att.id"
                    class="attachment-thumb"
                    @click="openPreview(att)"
                  >
                    <img :src="getAttachmentUrl(att.path)" :alt="att.filename" />
                    <q-tooltip>{{ att.filename }}</q-tooltip>
                  </div>
                </div>

                <!-- File list -->
                <div class="attachments-grid">
                  <div
                    v-for="att in ticketLevelAttachments"
                    :key="att.id"
                    class="attachment-item"
                    :class="{ 'attachment-previewable': canPreview(att.mime_type) }"
                    @click="canPreview(att.mime_type) ? openPreview(att) : undefined"
                  >
                    <div class="row items-center no-wrap">
                      <q-icon :name="getFileIcon(att.mime_type)" size="24px" color="grey-7" class="q-mr-sm" />
                      <div class="col" style="min-width: 0;">
                        <span class="attachment-name" :class="canPreview(att.mime_type) ? 'text-primary' : ''">
                          {{ att.filename }}
                          <q-icon v-if="canPreview(att.mime_type)" name="visibility" size="14px" class="q-ml-xs" />
                        </span>
                        <div class="text-caption text-grey-6">
                          {{ formatFileSize(att.size) }}
                          <span v-if="att.user"> &middot; {{ att.user.name }}</span>
                        </div>
                      </div>
                      <q-btn
                        v-if="canManage"
                        flat dense round
                        icon="delete_outline"
                        size="sm"
                        color="grey-5"
                        @click.stop="onDeleteAttachment(att.id)"
                      >
                        <q-tooltip>Eliminar</q-tooltip>
                      </q-btn>
                      <q-btn
                        flat dense round
                        icon="download"
                        size="sm"
                        color="grey-5"
                        tag="a"
                        :href="getAttachmentUrl(att.path)"
                        target="_blank"
                        @click.stop
                      >
                        <q-tooltip>Descargar</q-tooltip>
                      </q-btn>
                    </div>
                  </div>
                </div>
              </div>

              <!-- AI Copilot -->
              <div v-if="canManage" class="ticket-section ai-copilot">
                <div class="row items-center q-mb-sm">
                  <q-icon name="auto_awesome" color="primary" size="22px" class="q-mr-sm" />
                  <span class="text-weight-medium" style="color: #1976d2">Copiloto IA</span>
                  <q-space />
                  <q-btn
                    flat dense no-caps size="sm" color="primary"
                    icon="psychology" label="Sugerir Respuesta"
                    :loading="aiLoading" @click="onSuggestResponse"
                  />
                </div>
                <template v-if="aiSuggestion">
                  <div class="ai-suggestion-box q-mb-sm">
                    <div class="text-body2 ai-formatted" v-html="mdToHtml(aiSuggestion.suggested_response)"></div>
                  </div>
                  <div v-if="aiSuggestion.internal_note" class="ai-internal-note q-mb-sm">
                    <q-icon name="info" size="14px" class="q-mr-xs" color="orange-8" />
                    <span v-html="mdToHtml(aiSuggestion.internal_note)"></span>
                  </div>
                  <q-btn size="sm" color="primary" no-caps label="Usar esta respuesta" icon="check" @click="useSuggestion" />
                </template>
              </div>

              <!-- Conversations -->
              <div class="ticket-section">
                <div class="section-title">Conversaciones</div>

                <div v-for="comment in ticket.comments" :key="comment.id" class="conversation-item">
                  <div class="row items-start">
                    <div
                      class="conversation-avatar q-mr-md"
                      :style="{ backgroundColor: getAvatarColor(comment.user?.name) }"
                    >
                      {{ getInitial(comment.user?.name) }}
                    </div>
                    <div class="col">
                      <div class="conversation-header">
                        <span class="text-weight-bold">{{ comment.user?.name?.toUpperCase() }}</span>
                        <q-badge v-if="comment.is_internal" color="orange-4" text-color="white" class="q-ml-sm" style="font-size: 10px">
                          Nota interna
                        </q-badge>
                      </div>
                      <div class="text-caption text-grey-6 q-mb-sm">
                        respondio el {{ timeAgo(comment.created_at) }} ({{ formatDate(comment.created_at) }})
                      </div>
                      <div class="conversation-body" v-html="comment.body"></div>
                      <!-- Comment attachments -->
                      <div v-if="comment.attachments?.length" class="comment-attachments q-mt-sm">
                        <div
                          v-for="att in comment.attachments"
                          :key="att.id"
                          class="comment-attachment-item"
                          :class="{ 'attachment-previewable': canPreview(att.mime_type) }"
                          @click="canPreview(att.mime_type) ? openPreview(att) : undefined"
                        >
                          <q-icon :name="getFileIcon(att.mime_type)" size="18px" color="grey-7" class="q-mr-xs" />
                          <span class="text-caption" :class="canPreview(att.mime_type) ? 'text-primary' : ''">
                            {{ att.filename }}
                          </span>
                          <span class="text-caption text-grey-5 q-ml-xs">({{ formatFileSize(att.size) }})</span>
                          <q-btn
                            flat dense round
                            icon="download"
                            size="xs"
                            color="grey-5"
                            tag="a"
                            :href="getAttachmentUrl(att.path)"
                            target="_blank"
                            @click.stop
                            class="q-ml-xs"
                          />
                        </div>
                      </div>
                      <!-- Per-conversation action buttons (Freshservice style) -->
                      <div class="conversation-actions">
                        <q-btn v-if="canManage" flat dense size="sm" icon="shortcut" color="grey-5">
                          <q-tooltip>Reenviar</q-tooltip>
                        </q-btn>
                        <q-btn v-if="canManage" flat dense size="sm" icon="delete_outline" color="grey-5">
                          <q-tooltip>Eliminar</q-tooltip>
                        </q-btn>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="!ticket.comments?.length" class="text-grey-5 text-center q-pa-lg">
                  <q-icon name="chat_bubble_outline" size="48px" class="q-mb-sm" /><br>
                  No hay conversaciones aun
                </div>
              </div>

              <!-- Conversation Quick Actions (Freshservice bottom bar) -->
              <div class="conversation-quick-bar">
                <div
                  class="conversation-avatar q-mr-sm"
                  style="width: 32px; height: 32px; font-size: 13px"
                  :style="{ backgroundColor: getAvatarColor(auth.user?.name) }"
                >
                  {{ getInitial(auth.user?.name) }}
                </div>
                <q-btn outline no-caps dense color="primary" icon="reply" label="Responder" class="q-mr-sm"
                  @click="openReply(false)" />
                <q-btn outline no-caps dense color="grey-7" icon="shortcut" label="Reenviar" class="q-mr-sm" />
                <q-btn outline no-caps dense color="orange-8" icon="note" label="Añadir nota"
                  @click="openReply(true)" />
              </div>

              <!-- Reply Editor (shown on click) -->
              <div v-if="showReplyEditor" class="ticket-section reply-section">
                <div class="row items-center q-mb-sm">
                  <div
                    class="conversation-avatar q-mr-sm"
                    style="width: 32px; height: 32px; font-size: 14px"
                    :style="{ backgroundColor: getAvatarColor(auth.user?.name) }"
                  >
                    {{ getInitial(auth.user?.name) }}
                  </div>
                  <span class="text-weight-medium">{{ isInternal ? 'Añadir nota interna' : 'Responder' }}</span>
                  <q-space />
                  <q-btn-toggle
                    v-if="canManage"
                    v-model="isInternal"
                    flat dense no-caps size="sm"
                    toggle-color="orange"
                    :options="[
                      { label: 'Respuesta', value: false },
                      { label: 'Nota interna', value: true },
                    ]"
                  />
                  <q-btn flat dense round icon="close" size="sm" color="grey-6" class="q-ml-sm"
                    @click="showReplyEditor = false" />
                </div>
                <q-editor
                  v-model="newComment"
                  min-height="120px"
                  :class="{ 'internal-note-editor': isInternal }"
                  :toolbar="[
                    ['bold', 'italic', 'underline', 'strike'],
                    ['unordered_list', 'ordered_list'],
                    ['link', 'code'],
                    ['undo', 'redo'],
                  ]"
                  placeholder="Escribe tu respuesta..."
                />
                <!-- Reply attachments preview -->
                <div v-if="replyAttachments.length" class="q-mt-sm q-mb-xs">
                  <q-chip
                    v-for="(file, idx) in replyAttachments"
                    :key="idx"
                    removable
                    dense
                    size="sm"
                    color="primary"
                    text-color="white"
                    icon="attach_file"
                    @remove="replyAttachments.splice(idx, 1)"
                  >
                    {{ file.name }} ({{ formatFileSize(file.size) }})
                  </q-chip>
                </div>
                <div class="row items-center q-mt-sm">
                  <input
                    ref="fileInputRef"
                    type="file"
                    multiple
                    accept=".png,.jpg,.jpeg,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"
                    style="display: none;"
                    @change="onFilesSelected"
                  />
                  <q-btn flat no-caps dense color="grey-7" icon="attach_file" label="Adjuntar" @click="triggerFileInput" />
                  <q-btn
                    flat no-caps dense size="sm"
                    color="purple"
                    icon="auto_fix_high"
                    label="Mejorar texto"
                    :loading="improvingText"
                    :disable="!newComment.trim()"
                    @click="onImproveText"
                    class="q-ml-sm"
                  >
                    <q-tooltip>Corregir ortografía y mejorar redacción con IA</q-tooltip>
                  </q-btn>
                  <q-space />
                  <q-btn flat no-caps dense color="grey-7" label="Cancelar" class="q-mr-sm"
                    @click="showReplyEditor = false" />
                  <q-btn
                    color="primary" no-caps
                    :label="isInternal ? 'Añadir nota' : 'Enviar'"
                    :icon="isInternal ? 'note' : 'send'"
                    :loading="commentLoading"
                    @click="onAddComment"
                    :disable="!newComment.trim()"
                  />
                </div>
              </div>
            </q-tab-panel>

            <!-- Related Tickets Tab -->
            <!-- Related Tickets Tab -->
            <q-tab-panel name="related" class="q-pa-lg">
              <div class="row items-center q-mb-md">
                <div class="text-subtitle2 text-weight-bold">Tickets asociados</div>
                <q-space />
                <q-btn v-if="canManage" flat dense no-caps color="primary" icon="add" label="Asociar ticket" @click="assocForm.type = 'related'; showAssocDialog = true" />
              </div>

              <div v-if="associations.length === 0" class="text-grey-5 text-center q-pa-xl">
                <q-icon name="link" size="48px" class="q-mb-sm" /><br>
                No hay tickets asociados
              </div>

              <q-list v-else separator>
                <q-item v-for="assoc in associations" :key="assoc.id" clickable @click="$router.push(`/tickets/${assoc.related_ticket.id}`)">
                  <q-item-section avatar>
                    <q-icon :name="assoc.type === 'parent' ? 'account_tree' : assoc.type === 'child' ? 'subdirectory_arrow_right' : assoc.type === 'cause' ? 'warning_amber' : 'link'" color="grey-7" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>
                      <span class="text-primary text-weight-medium">{{ assoc.related_ticket.ticket_number }}</span>
                      <span class="q-ml-sm">{{ assoc.related_ticket.title }}</span>
                    </q-item-label>
                    <q-item-label caption>
                      <q-badge :color="getStatusBadgeColor(assoc.related_ticket.status)" :label="getStatusLabel(assoc.related_ticket.status)" class="q-mr-sm" />
                      <span class="text-capitalize">{{ assoc.type === 'parent' ? 'Padre' : assoc.type === 'child' ? 'Hijo' : assoc.type === 'cause' ? 'Causa' : 'Relacionado' }}</span>
                    </q-item-label>
                  </q-item-section>
                  <q-item-section side v-if="canManage">
                    <q-btn flat dense round icon="close" size="sm" color="grey-5" @click.stop="onDeleteAssociation(assoc.id)" />
                  </q-item-section>
                </q-item>
              </q-list>
            </q-tab-panel>

            <!-- Time Tracking Tab -->
            <q-tab-panel name="time" class="q-pa-lg">
              <div class="row items-center q-mb-md">
                <div class="text-subtitle2 text-weight-bold">Registro de tiempo</div>
                <q-badge v-if="totalTimeLogged > 0" color="primary" class="q-ml-sm">{{ totalTimeLogged.toFixed(1) }}h total</q-badge>
                <q-space />
                <q-btn v-if="canManage" flat dense no-caps color="primary" icon="add" label="Añadir hora" @click="showTimeDialog = true" />
              </div>

              <div v-if="timeEntries.length === 0" class="text-grey-5 text-center q-pa-xl">
                <q-icon name="schedule" size="48px" class="q-mb-sm" /><br>
                No hay tiempo registrado
              </div>

              <q-list v-else separator>
                <q-item v-for="entry in timeEntries" :key="entry.id">
                  <q-item-section avatar>
                    <q-avatar size="32px" color="primary" text-color="white" font-size="13px">
                      <img v-if="entry.user.avatar_url" :src="entry.user.avatar_url" />
                      <span v-else>{{ entry.user.name.charAt(0).toUpperCase() }}</span>
                    </q-avatar>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>
                      <span class="text-weight-bold">{{ entry.hours }}h</span>
                      <q-badge v-if="entry.billable" color="green-2" text-color="green-9" class="q-ml-sm">Facturable</q-badge>
                    </q-item-label>
                    <q-item-label v-if="entry.note" caption>{{ entry.note }}</q-item-label>
                    <q-item-label caption>
                      {{ entry.user.name }} &middot; {{ entry.executed_at }}
                    </q-item-label>
                  </q-item-section>
                  <q-item-section side v-if="canManage">
                    <q-btn flat dense round icon="delete_outline" size="sm" color="grey-5" @click="onDeleteTimeEntry(entry.id)" />
                  </q-item-section>
                </q-item>
              </q-list>
            </q-tab-panel>

            <!-- Activity Tab -->
            <q-tab-panel name="activity" class="q-pa-lg">
              <q-linear-progress v-if="activitiesLoading" indeterminate color="primary" class="q-mb-md" />

              <div v-if="!activitiesLoading && ticketActivities.length === 0" class="text-grey-5 text-center q-pa-xl">
                <q-icon name="history" size="48px" class="q-mb-sm" /><br>
                No hay actividad registrada
              </div>

              <div v-else class="activity-timeline">
                <div v-for="activity in ticketActivities" :key="activity.id" class="activity-entry q-mb-md">
                  <div class="row no-wrap items-start q-gutter-sm">
                    <q-avatar size="30px" color="primary" text-color="white" font-size="12px">
                      <img v-if="activity.user.avatar_url" :src="activity.user.avatar_url" />
                      <span v-else>{{ activity.user.name.charAt(0).toUpperCase() }}</span>
                    </q-avatar>
                    <div class="col">
                      <div class="text-body2">
                        <span class="text-weight-bold">{{ activity.user.name }}</span>
                        {{ ' ' }}
                        <span>{{ activity.description }}</span>
                      </div>
                      <div class="text-caption text-grey">{{ timeAgo(activity.created_at) }} &middot; {{ formatDate(activity.created_at) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </q-tab-panel>

            <!-- Participants Tab -->
            <q-tab-panel name="participants" class="q-pa-lg">
              <div class="text-subtitle2 text-weight-bold q-mb-md">Personas involucradas</div>

              <q-list separator>
                <q-item v-for="p in participants" :key="p.id">
                  <q-item-section avatar>
                    <q-avatar size="36px" :style="{ backgroundColor: getAvatarColor(p.name) }" text-color="white" font-size="15px">
                      <img v-if="p.avatar_url" :src="p.avatar_url" />
                      <span v-else>{{ p.name.charAt(0).toUpperCase() }}</span>
                    </q-avatar>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-weight-medium">{{ p.name }}</q-item-label>
                    <q-item-label caption>
                      {{ p.role }}
                      <span v-if="p.count > 0"> &middot; {{ p.count }} comentario{{ p.count > 1 ? 's' : '' }}</span>
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-tab-panel>

            <!-- Resolution Tab -->
            <q-tab-panel name="resolution" class="q-pa-lg">
              <div v-if="ticket.resolved_at || ticket.closed_at" class="q-mb-lg">
                <div class="row q-gutter-lg">
                  <div v-if="ticket.resolved_at">
                    <div class="text-caption text-grey-6">Resuelto el</div>
                    <div class="text-weight-medium">{{ formatDate(ticket.resolved_at) }}</div>
                  </div>
                  <div v-if="ticket.closed_at">
                    <div class="text-caption text-grey-6">Cerrado el</div>
                    <div class="text-weight-medium">{{ formatDate(ticket.closed_at) }}</div>
                  </div>
                </div>
              </div>

              <div class="text-subtitle2 text-weight-bold q-mb-sm">Notas de resolución</div>
              <q-editor
                v-if="canManage"
                v-model="resolutionNotes"
                min-height="120px"
                placeholder="Describe la solución aplicada, causa raíz, y pasos realizados..."
                :toolbar="[
                  ['bold', 'italic', 'underline'],
                  ['unordered_list', 'ordered_list'],
                  ['undo', 'redo'],
                ]"
              />
              <div v-else-if="ticket.resolution_notes" class="q-pa-md" style="background: #f5f5f5; border-radius: 8px;" v-html="ticket.resolution_notes"></div>
              <div v-else class="text-grey-5 q-pa-md">Sin notas de resolución</div>

              <q-btn
                v-if="canManage"
                color="primary" no-caps
                label="Guardar resolución"
                class="q-mt-md"
                :loading="savingResolution"
                :disable="resolutionNotes === (ticket.resolution_notes || '')"
                @click="onSaveResolution"
              />
            </q-tab-panel>
          </q-tab-panels>
        </div>

        <!-- RIGHT: Sidebar (independently scrollable) -->
        <div class="ticket-sidebar">
          <!-- Status Summary Card (Freshservice style) -->
          <div class="sidebar-status-card">
            <div class="status-header">
              <span class="status-text" :style="{ color: getStatusColor(ticket.status) === 'warning' ? '#e67e22' : getStatusColor(ticket.status) === 'purple' ? '#9b59b6' : getStatusColor(ticket.status) === 'positive' ? '#27ae60' : getStatusColor(ticket.status) === 'primary' ? '#2196f3' : '#7f8c8d' }">
                {{ t(`tickets.statuses.${ticket.status}`) }}
              </span>
            </div>
            <div class="status-row">
              <span class="status-label">Prioridad</span>
              <span class="status-value">
                <span class="priority-square" :style="{ backgroundColor: getPriorityDot(ticket.priority) }"></span>
                {{ t(`tickets.priorities.${ticket.priority}`) }}
              </span>
            </div>

            <!-- SLA Countdown Section (Freshservice style) -->
            <div v-if="ticket.response_due_at" class="sla-countdown-row">
              <div class="sla-countdown-label">Plazo de la primera respuesta</div>
              <div class="sla-countdown-detail">
                <span class="sla-countdown-date">por {{ formatSlaDate(ticket.response_due_at) }}</span>
                <q-badge
                  :color="slaTimeLeft(ticket.response_due_at)?.overdue ? 'red-1' : 'green-1'"
                  :text-color="slaTimeLeft(ticket.response_due_at)?.overdue ? 'red-9' : 'green-9'"
                  class="sla-countdown-badge"
                >
                  <q-icon :name="slaTimeLeft(ticket.response_due_at)?.overdue ? 'warning' : 'schedule'" size="12px" class="q-mr-xs" />
                  {{ slaTimeLeft(ticket.response_due_at)?.text }}
                </q-badge>
              </div>
            </div>

            <div v-if="ticket.resolution_due_at" class="sla-countdown-row">
              <div class="sla-countdown-label">Resolucion pendiente</div>
              <div class="sla-countdown-detail">
                <span class="sla-countdown-date">por {{ formatSlaDate(ticket.resolution_due_at) }}</span>
                <q-badge
                  :color="slaTimeLeft(ticket.resolution_due_at)?.overdue ? 'red-1' : 'green-1'"
                  :text-color="slaTimeLeft(ticket.resolution_due_at)?.overdue ? 'red-9' : 'green-9'"
                  class="sla-countdown-badge"
                >
                  <q-icon :name="slaTimeLeft(ticket.resolution_due_at)?.overdue ? 'warning' : 'schedule'" size="12px" class="q-mr-xs" />
                  {{ slaTimeLeft(ticket.resolution_due_at)?.text }}
                </q-badge>
              </div>
            </div>
          </div>

          <!-- Requester Info (Freshservice style) -->
          <div class="sidebar-section">
            <div class="sidebar-section-header">
              <q-icon name="person_outline" size="20px" class="q-mr-sm" />
              Informacion del solicitante
            </div>
            <div class="requester-info">
              <div class="row items-center q-mb-xs">
                <div
                  class="conversation-avatar q-mr-sm"
                  style="width: 36px; height: 36px; font-size: 15px"
                  :style="{ backgroundColor: getAvatarColor(ticket.requester?.name) }"
                >
                  {{ getInitial(ticket.requester?.name) }}
                </div>
                <div>
                  <div class="text-weight-medium text-primary" style="font-size: 14px; cursor: pointer" @click="openRequesterPanel">
                    {{ ticket.requester?.name }}
                  </div>
                </div>
              </div>
              <div class="text-caption text-primary q-ml-sm" style="cursor: pointer; margin-left: 48px" @click="openRequesterPanel">
                Ver mas detalles
              </div>
            </div>
          </div>

          <!-- Properties Section (Freshservice style) -->
          <div class="sidebar-section">
            <div
              class="sidebar-section-header clickable"
              @click="propertiesExpanded = !propertiesExpanded"
            >
              <q-icon name="tune" size="20px" class="q-mr-sm" />
              Propiedades
              <q-space />
              <q-icon :name="propertiesExpanded ? 'expand_less' : 'expand_more'" size="20px" />
            </div>

            <q-slide-transition>
              <div v-show="propertiesExpanded" class="properties-grid">
                <!-- Priority & Status -->
                <div class="prop-row">
                  <div class="prop-field">
                    <label class="prop-label">Prioridad <span class="required">*</span></label>
                    <q-select
                      v-model="editProps.priority"
                      :options="priorityOptions"
                      emit-value map-options
                      dense outlined
                      :disable="!canManage"
                      class="prop-select"
                    >
                      <template #selected-item="{ opt }">
                        <span class="row items-center no-wrap">
                          <span class="priority-square q-mr-sm" :style="{ backgroundColor: getPriorityDot(editProps.priority) }"></span>
                          {{ opt.label || opt }}
                        </span>
                      </template>
                      <template #option="{ itemProps, opt }">
                        <q-item v-bind="itemProps">
                          <q-item-section side>
                            <span class="priority-square" :style="{ backgroundColor: getPriorityDot(opt.value) }"></span>
                          </q-item-section>
                          <q-item-section>{{ opt.label }}</q-item-section>
                        </q-item>
                      </template>
                    </q-select>
                  </div>
                  <div class="prop-field">
                    <label class="prop-label">Estado <span class="required">*</span></label>
                    <q-select
                      v-model="editProps.status"
                      :options="statusOptions"
                      emit-value map-options
                      dense outlined
                      :disable="!canManage"
                      class="prop-select"
                    />
                  </div>
                </div>

                <!-- Source & Type -->
                <div class="prop-row">
                  <div class="prop-field">
                    <label class="prop-label">Origen</label>
                    <q-select
                      v-model="editProps.source"
                      :options="sourceOptions"
                      emit-value map-options
                      dense outlined
                      :disable="!canManage"
                      class="prop-select"
                    />
                  </div>
                  <div class="prop-field">
                    <label class="prop-label">Tipo</label>
                    <q-select
                      v-model="editProps.type"
                      :options="typeOptions"
                      emit-value map-options
                      dense outlined
                      :disable="!canManage"
                      class="prop-select"
                    />
                  </div>
                </div>

                <!-- Urgencia & Impacto -->
                <div class="prop-row">
                  <div class="prop-field">
                    <label class="prop-label">Urgencia</label>
                    <q-select
                      v-model="editProps.urgency"
                      :options="urgencyOptions"
                      emit-value map-options
                      dense outlined clearable
                      :disable="!canManage"
                      class="prop-select"
                    />
                  </div>
                  <div class="prop-field">
                    <label class="prop-label">Impacto</label>
                    <q-select
                      v-model="editProps.impact"
                      :options="impactOptions"
                      emit-value map-options
                      dense outlined clearable
                      :disable="!canManage"
                      class="prop-select"
                    />
                  </div>
                </div>

                <!-- Grupo & Departamento -->
                <div class="prop-row">
                  <div class="prop-field">
                    <label class="prop-label">Grupo</label>
                    <q-select
                      v-model="editProps.agent_group_id"
                      :options="agentGroupOptions"
                      emit-value map-options
                      dense outlined clearable
                      :disable="!canManage"
                      class="prop-select"
                    />
                  </div>
                  <div class="prop-field">
                    <label class="prop-label">Departamento</label>
                    <div class="prop-value-text">{{ ticket.department?.name || 'Sin asignar' }}</div>
                  </div>
                </div>

                <!-- Category (full width) -->
                <div class="prop-row">
                  <div class="prop-field full-width">
                    <label class="prop-label">Categoria</label>
                    <q-select
                      v-model="editProps.category_id"
                      :options="categoryOptions"
                      emit-value map-options
                      dense outlined clearable
                      :disable="!canManage"
                      class="prop-select"
                    />
                  </div>
                </div>

                <!-- Agent (full width) -->
                <div class="prop-row">
                  <div class="prop-field full-width">
                    <label class="prop-label">Agente</label>
                    <q-select
                      v-model="editProps.assigned_to"
                      :options="agentOptions"
                      emit-value map-options
                      dense outlined clearable
                      :disable="!canManage"
                      class="prop-select"
                    />
                  </div>
                </div>

                <!-- Dates -->
                <div class="prop-row">
                  <div class="prop-field">
                    <label class="prop-label">Creado</label>
                    <div class="prop-value-text">{{ formatDate(ticket.created_at) }}</div>
                  </div>
                  <div class="prop-field">
                    <label class="prop-label">Actualizado</label>
                    <div class="prop-value-text">{{ formatDate(ticket.updated_at) }}</div>
                  </div>
                </div>

                <!-- Update Button (Freshservice red/primary style) -->
                <q-btn
                  v-if="canManage"
                  color="primary" no-caps
                  label="Actualizar"
                  class="full-width q-mt-sm update-btn"
                  :loading="savingProperties"
                  :disable="!propertiesDirty"
                  @click="onSaveProperties"
                />
              </div>
            </q-slide-transition>
          </div>
        </div>
      </div>
    </template>
    <!-- Requester Detail Panel (Freshservice-style) -->
    <q-dialog v-model="showRequesterPanel" position="right" full-height>
      <q-card style="width: 480px; max-width: 90vw;" class="full-height column requester-panel">
        <!-- Header -->
        <q-card-section class="q-pb-sm">
          <div class="row items-center q-mb-md">
            <q-space />
            <q-btn flat dense round icon="close" v-close-popup />
          </div>

          <q-linear-progress v-if="requesterLoading" indeterminate color="primary" class="q-mb-md" />

          <template v-if="requesterDetail">
            <div class="row items-center q-gutter-md q-mb-lg">
              <q-avatar size="56px" :style="{ backgroundColor: getAvatarColor(requesterDetail.name) }" text-color="white" font-size="24px">
                <img v-if="requesterDetail.avatar_url" :src="requesterDetail.avatar_url" />
                <span v-else>{{ getInitial(requesterDetail.name) }}</span>
              </q-avatar>
              <div>
                <div class="text-h6 text-weight-bold">{{ requesterDetail.name }}</div>
                <div class="text-caption text-grey-6">{{ requesterDetail.job_title || getRoleLabel(requesterDetail.role) }}</div>
              </div>
            </div>

            <!-- User details grid -->
            <div class="requester-details-grid">
              <div class="detail-item">
                <div class="detail-label">Correo electrónico</div>
                <div class="detail-value">{{ requesterDetail.email }}</div>
              </div>
              <div class="detail-item" v-if="requesterDetail.phone">
                <div class="detail-label">Teléfono</div>
                <div class="detail-value">{{ requesterDetail.phone }}</div>
              </div>
              <div class="detail-item" v-if="requesterDetail.job_title">
                <div class="detail-label">Título</div>
                <div class="detail-value">{{ requesterDetail.job_title }}</div>
              </div>
              <div class="detail-item" v-if="requesterDetail.department">
                <div class="detail-label">Departamento</div>
                <div class="detail-value">{{ requesterDetail.department.name }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Zona horaria</div>
                <div class="detail-value">{{ requesterDetail.timezone || 'America/Lima' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Idioma</div>
                <div class="detail-value">{{ requesterDetail.language === 'es' ? 'Español' : requesterDetail.language === 'en' ? 'English' : requesterDetail.language }}</div>
              </div>
              <div class="detail-item" v-if="requesterDetail.location">
                <div class="detail-label">Ubicación</div>
                <div class="detail-value">{{ requesterDetail.location }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Formato de hora</div>
                <div class="detail-value">{{ requesterDetail.time_format || '12h' }}</div>
              </div>
              <div class="detail-item" v-if="requesterDetail.is_vip">
                <div class="detail-label">VIP</div>
                <div class="detail-value"><q-icon name="star" color="amber" size="16px" /> Sí</div>
              </div>
            </div>
          </template>
        </q-card-section>

        <q-separator />

        <!-- Recent tickets -->
        <q-card-section class="col scroll q-pt-sm">
          <div class="row items-center q-mb-sm">
            <span class="text-subtitle2 text-weight-bold">Tickets recientes</span>
            <q-badge v-if="requesterTickets.length" color="grey-5" class="q-ml-sm">{{ requesterTickets.length }}</q-badge>
          </div>

          <div v-if="requesterLoading" class="q-pa-md text-center">
            <q-spinner color="primary" size="24px" />
          </div>

          <div v-else-if="requesterTickets.length === 0" class="text-center text-grey-5 q-pa-lg">
            Sin tickets recientes
          </div>

          <q-list v-else separator class="requester-tickets-list">
            <q-item
              v-for="rt in requesterTickets"
              :key="rt.id"
              clickable
              @click="showRequesterPanel = false; $router.push(`/tickets/${rt.id}`)"
              class="q-px-sm"
            >
              <q-item-section>
                <q-item-label class="text-caption text-weight-medium text-primary">
                  {{ rt.ticket_number }}
                  <span class="text-dark q-ml-xs">{{ rt.title }}</span>
                </q-item-label>
                <q-item-label caption>
                  {{ timeAgo(rt.created_at) }}
                </q-item-label>
              </q-item-section>
              <q-item-section side class="text-right" style="min-width: 100px;">
                <q-item-label>
                  <q-badge :color="getStatusBadgeColor(rt.status)" :label="getStatusLabel(rt.status)" class="text-caption" />
                </q-item-label>
                <q-item-label caption class="q-mt-xs">
                  <span class="priority-dot q-mr-xs" :style="{ backgroundColor: getPriorityDotColor(rt.priority) }" />
                  {{ getPriorityLabel(rt.priority) }}
                </q-item-label>
                <q-item-label v-if="rt.assignee_name" caption>
                  {{ rt.assignee_name }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>

          <div v-if="requesterTickets.length > 0" class="q-mt-md text-center">
            <q-btn
              flat no-caps dense color="primary"
              label="Mostrar todos los tickets"
              icon-right="open_in_new"
              @click="showRequesterPanel = false; $router.push({ path: '/tickets', query: { requester_id: String(ticket!.requester_id), requester_name: requesterDetail?.name || '' } })"
            />
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Attachment Preview Dialog -->
    <q-dialog v-model="showPreview" maximized transition-show="fade" transition-hide="fade">
      <div class="preview-overlay column" @click.self="showPreview = false" @wheel.prevent="onPreviewWheel">
        <!-- Top bar -->
        <div class="preview-toolbar row items-center q-px-md q-py-xs">
          <q-icon :name="previewAttachment?.mime?.startsWith('image/') ? 'image' : previewAttachment?.mime === 'application/pdf' ? 'picture_as_pdf' : previewAttachment?.mime?.startsWith('video/') ? 'movie' : 'description'" color="white" size="22px" class="q-mr-sm" />
          <span class="text-white text-body2 ellipsis" style="max-width: 360px;">{{ previewAttachment?.filename }}</span>

          <!-- Navigation counter -->
          <span v-if="previewableAttachments.length > 1" class="text-grey-5 text-caption q-ml-sm">
            ({{ previewIndex + 1 }} / {{ previewableAttachments.length }})
          </span>

          <q-space />

          <!-- Zoom controls (images only) -->
          <template v-if="previewAttachment?.mime?.startsWith('image/')">
            <q-btn flat round dense icon="zoom_out" color="white" size="sm" @click="previewZoomOut" :disable="previewZoom <= 0.25">
              <q-tooltip>Alejar</q-tooltip>
            </q-btn>
            <q-btn flat dense no-caps color="white" size="sm" class="q-px-xs" style="min-width: 50px;" @click="previewResetZoom">
              {{ Math.round(previewZoom * 100) }}%
            </q-btn>
            <q-btn flat round dense icon="zoom_in" color="white" size="sm" @click="previewZoomIn" :disable="previewZoom >= 5">
              <q-tooltip>Acercar</q-tooltip>
            </q-btn>

            <q-separator vertical dark class="q-mx-sm" style="height: 20px;" />

            <!-- Rotate controls -->
            <q-btn flat round dense icon="rotate_left" color="white" size="sm" @click="previewRotateLeft">
              <q-tooltip>Rotar izquierda</q-tooltip>
            </q-btn>
            <q-btn flat round dense icon="rotate_right" color="white" size="sm" @click="previewRotateRight">
              <q-tooltip>Rotar derecha</q-tooltip>
            </q-btn>

            <q-separator vertical dark class="q-mx-sm" style="height: 20px;" />
          </template>

          <!-- Actions -->
          <q-btn flat round dense icon="download" color="white" size="sm" tag="a" :href="previewAttachment?.url" target="_blank">
            <q-tooltip>Descargar</q-tooltip>
          </q-btn>
          <q-btn flat round dense icon="open_in_new" color="white" size="sm" tag="a" :href="previewAttachment?.url" target="_blank">
            <q-tooltip>Abrir en nueva pestaña</q-tooltip>
          </q-btn>
          <q-btn flat round dense icon="close" color="white" size="sm" @click="showPreview = false" class="q-ml-xs" />
        </div>

        <!-- Preview content -->
        <div class="preview-content col" @click.self="showPreview = false">
          <!-- Navigation arrows -->
          <q-btn
            v-if="previewableAttachments.length > 1"
            flat round
            icon="chevron_left"
            color="white"
            size="lg"
            class="preview-nav preview-nav-left"
            @click.stop="previewNavigate(-1)"
          />
          <q-btn
            v-if="previewableAttachments.length > 1"
            flat round
            icon="chevron_right"
            color="white"
            size="lg"
            class="preview-nav preview-nav-right"
            @click.stop="previewNavigate(1)"
          />

          <!-- Image -->
          <div
            v-if="previewAttachment?.mime?.startsWith('image/')"
            class="preview-image-container"
          >
            <img
              :src="previewAttachment?.url"
              :alt="previewAttachment?.filename"
              class="preview-image"
              :style="{
                transform: `scale(${previewZoom}) rotate(${previewRotation}deg)`,
              }"
              draggable="false"
            />
          </div>

          <!-- PDF -->
          <iframe
            v-else-if="previewAttachment?.mime === 'application/pdf'"
            :src="previewAttachment?.url"
            class="preview-pdf"
          />

          <!-- Video -->
          <video
            v-else-if="previewAttachment?.mime?.startsWith('video/')"
            :src="previewAttachment?.url"
            controls
            class="preview-video"
          />

          <!-- Text -->
          <iframe
            v-else-if="previewAttachment?.mime?.startsWith('text/')"
            :src="previewAttachment?.url"
            class="preview-text"
          />

          <!-- Unsupported -->
          <div v-else class="text-center text-white" style="margin: auto;">
            <q-icon name="visibility_off" size="64px" class="q-mb-md" style="opacity: 0.5;" />
            <div class="text-h6">Vista previa no disponible</div>
            <div class="text-caption text-grey-5 q-mb-lg">Este tipo de archivo no se puede previsualizar</div>
            <q-btn
              color="white"
              text-color="dark"
              no-caps
              unelevated
              icon="download"
              label="Descargar archivo"
              tag="a"
              :href="previewAttachment?.url"
              target="_blank"
            />
          </div>
        </div>
      </div>
    </q-dialog>

    <!-- Time Entry Dialog -->
    <q-dialog v-model="showTimeDialog">
      <q-card style="min-width: 400px;">
        <q-card-section>
          <div class="text-h6">Añadir tiempo</div>
        </q-card-section>

        <q-card-section class="q-gutter-md">
          <q-input
            v-model.number="timeForm.hours"
            type="number"
            label="Horas *"
            dense outlined
            min="0.01"
            step="0.25"
            hint="Ej: 1.5 = 1 hora 30 minutos"
          />
          <q-input
            v-model="timeForm.executed_at"
            type="date"
            label="Fecha del trabajo *"
            dense outlined
          />
          <q-input
            v-model="timeForm.note"
            type="textarea"
            label="Descripción del trabajo"
            dense outlined
            rows="3"
            placeholder="¿Qué se realizó?"
          />
          <q-toggle
            v-model="timeForm.billable"
            label="Facturable"
            color="green"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn
            color="primary" no-caps
            label="Registrar tiempo"
            :loading="timeLoading"
            :disable="!timeForm.hours || timeForm.hours <= 0"
            @click="onAddTimeEntry"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Association Dialog -->
    <q-dialog v-model="showAssocDialog">
      <q-card style="min-width: 500px;">
        <q-card-section>
          <div class="text-h6">
            Asociar ticket
            <q-badge color="primary" class="q-ml-sm text-capitalize">
              {{ assocForm.type === 'parent' ? 'Padre' : assocForm.type === 'child' ? 'Hijo' : assocForm.type === 'cause' ? 'Causa' : 'Relacionado' }}
            </q-badge>
          </div>
        </q-card-section>

        <q-card-section>
          <q-input
            v-model="assocForm.search"
            label="Buscar ticket por número o título"
            dense outlined
            @keyup.enter="onSearchTicketsForAssoc"
          >
            <template #append>
              <q-btn flat dense round icon="search" :loading="assocSearching" @click="onSearchTicketsForAssoc" />
            </template>
          </q-input>

          <q-list v-if="assocSearchResults.length" separator class="q-mt-md" style="max-height: 300px; overflow: auto;">
            <q-item v-for="t in assocSearchResults" :key="t.id" clickable @click="onCreateAssociation(t.id)">
              <q-item-section>
                <q-item-label>
                  <span class="text-primary text-weight-medium">{{ t.ticket_number }}</span>
                  <span class="q-ml-sm">{{ t.title }}</span>
                </q-item-label>
                <q-item-label caption>
                  <q-badge :color="getStatusBadgeColor(t.status)" :label="getStatusLabel(t.status)" class="q-mr-sm" />
                  {{ t.requester?.name }}
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-btn flat dense color="primary" icon="link" size="sm" :loading="assocLoading" />
              </q-item-section>
            </q-item>
          </q-list>

          <div v-else-if="assocForm.search && !assocSearching" class="text-grey-5 text-center q-pa-lg">
            Busca un ticket para asociar
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Merge Dialog -->
    <q-dialog v-model="showMergeDialog">
      <q-card style="min-width: 500px;">
        <q-card-section>
          <div class="text-h6">Combinar tickets</div>
          <div class="text-caption text-grey q-mt-xs">
            El ticket seleccionado se combinará en este ticket. Sus comentarios, adjuntos y tiempo serán movidos aquí.
          </div>
        </q-card-section>

        <q-card-section>
          <q-input
            v-model="mergeSearch"
            label="Buscar ticket origen por número o título"
            dense outlined
            @keyup.enter="onSearchTicketsForMerge"
          >
            <template #append>
              <q-btn flat dense round icon="search" :loading="mergeSearching" @click="onSearchTicketsForMerge" />
            </template>
          </q-input>

          <q-list v-if="mergeSearchResults.length" separator class="q-mt-md" style="max-height: 300px; overflow: auto;">
            <q-item v-for="t in mergeSearchResults" :key="t.id" clickable @click="onMergeTicket(t.id)">
              <q-item-section>
                <q-item-label>
                  <span class="text-primary text-weight-medium">{{ t.ticket_number }}</span>
                  <span class="q-ml-sm">{{ t.title }}</span>
                </q-item-label>
                <q-item-label caption>
                  <q-badge :color="getStatusBadgeColor(t.status)" :label="getStatusLabel(t.status)" class="q-mr-sm" />
                  {{ t.requester?.name }}
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-btn flat dense color="primary" icon="merge_type" size="sm" :loading="mergeLoading" />
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Scenario Dialog -->
    <q-dialog v-model="showScenarioDialog">
      <q-card style="min-width: 400px;">
        <q-card-section>
          <div class="text-h6">Ejecutar situación</div>
          <div class="text-caption text-grey q-mt-xs">Selecciona un escenario para aplicar al ticket</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-list separator>
            <q-item v-for="s in scenarios" :key="s.id" clickable @click="onRunScenario(s.id)">
              <q-item-section avatar>
                <q-icon name="play_circle_outline" color="primary" />
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-weight-medium">{{ s.name }}</q-item-label>
                <q-item-label v-if="s.description" caption>{{ s.description }}</q-item-label>
                <q-item-label caption>
                  {{ s.actions.length }} acción{{ s.actions.length > 1 ? 'es' : '' }}
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-spinner v-if="scenarioLoading" size="20px" color="primary" />
                <q-icon v-else name="chevron_right" color="grey-5" />
              </q-item-section>
            </q-item>
          </q-list>

          <div v-if="!scenarios.length" class="text-grey-5 text-center q-pa-lg">
            No hay escenarios configurados
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat no-caps label="Cerrar" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<style scoped>
.ticket-detail-page {
  background: #f5f7fa;
  padding: 0 !important;
}

/* Top Bar */
.ticket-topbar {
  background: #fff;
  border-bottom: 1px solid #e8ecf0;
  padding: 6px 16px;
  position: sticky;
  top: 0;
  z-index: 10;
}

.topbar-title {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-left: 8px;
}

.topbar-btn {
  font-size: 13px;
  padding: 4px 12px;
  min-height: 32px;
}

/* Breadcrumb bar */
.ticket-breadcrumb-bar {
  background: #fff;
  padding: 6px 20px;
  border-bottom: 1px solid #f0f0f0;
  font-size: 13px;
}

/* Body Layout */
.ticket-body {
  flex-wrap: nowrap;
  height: calc(100vh - 155px);
}

.ticket-main {
  background: #fff;
  min-width: 0;
  border-right: 1px solid #e8ecf0;
  overflow-y: auto;
}

.ticket-sidebar {
  width: 340px;
  min-width: 340px;
  max-width: 340px;
  background: #fff;
  overflow-y: auto;
  border-left: 1px solid #e8ecf0;
}

/* Header */
.ticket-header {
  padding: 20px 24px 16px;
}

.ticket-type-icon {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.ticket-title {
  font-size: 17px;
  font-weight: 600;
  color: #1a1a2e;
  line-height: 1.4;
}

.ticket-meta {
  font-size: 13px;
  margin-top: 4px;
  line-height: 1.5;
}

/* Tabs */
.ticket-tabs {
  padding: 0 24px;
}

.ticket-tabs :deep(.q-tab) {
  min-height: 38px;
  padding: 0 14px;
  font-size: 13px;
}

.ticket-panels {
  background: transparent;
}

.ticket-panels :deep(.q-tab-panel) {
  padding: 0;
}

/* Sections */
.ticket-section {
  padding: 20px 24px;
}

/* Description Card (Freshservice grey bg) */
.description-card {
  background: #f9fafb;
  border: 1px solid #eef0f2;
  border-radius: 8px;
  padding: 20px;
}

.section-title {
  font-size: 15px;
  font-weight: 600;
  color: #1a1a2e;
  margin-bottom: 12px;
}

.section-content {
  font-size: 14px;
  line-height: 1.7;
  color: #333;
}

/* Attachments */
.attachments-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.attachment-item {
  padding: 10px 12px;
  border: 1px solid #e8ecf0;
  border-radius: 6px;
  background: #fafbfc;
  transition: background 0.15s;
}

.attachment-item:hover {
  background: #f0f4f8;
}

.attachment-name {
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.attachment-name:hover {
  text-decoration: underline;
}

.body--dark .attachment-item {
  background: #252535;
  border-color: #3a3a4a;
}

.body--dark .attachment-item:hover {
  background: #2a2a3a;
}

.attachment-previewable {
  cursor: pointer;
}

/* Comment-level attachments */
.comment-attachments {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.comment-attachment-item {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  background: #f5f7fa;
  border: 1px solid #e8ecf0;
  border-radius: 4px;
  transition: background 0.15s;
}

.comment-attachment-item:hover {
  background: #edf0f5;
}

.comment-attachment-item.attachment-previewable {
  cursor: pointer;
}

.body--dark .comment-attachment-item {
  background: #252535;
  border-color: #3a3a4a;
}

.body--dark .comment-attachment-item:hover {
  background: #2a2a3a;
}

/* Image thumbnails */
.attachment-thumbnails {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.attachment-thumb {
  width: 80px;
  height: 80px;
  border-radius: 6px;
  border: 1px solid #e8ecf0;
  overflow: hidden;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.attachment-thumb:hover {
  border-color: var(--q-primary);
  box-shadow: 0 2px 8px rgba(25, 118, 210, 0.2);
}

.attachment-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.body--dark .attachment-thumb {
  border-color: #3a3a4a;
}

/* Requester detail panel */
.requester-details-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px 24px;
}

.detail-item .detail-label {
  font-size: 11px;
  font-weight: 600;
  color: #999;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 2px;
}

.detail-item .detail-value {
  font-size: 13px;
  color: #333;
  word-break: break-word;
}

.body--dark .detail-item .detail-value {
  color: #d0d0d8;
}

.requester-tickets-list .q-item {
  border-radius: 6px;
  padding: 10px 8px;
}

.requester-tickets-list .q-item:hover {
  background: #f5f7fa;
}

.body--dark .requester-tickets-list .q-item:hover {
  background: #2a2a3a;
}

/* Preview dialog */
.preview-overlay {
  background: rgba(0, 0, 0, 0.94);
  width: 100%;
  height: 100%;
}

.preview-toolbar {
  background: rgba(30, 30, 40, 0.85);
  flex-shrink: 0;
  backdrop-filter: blur(8px);
  z-index: 2;
}

.preview-content {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

/* Navigation arrows */
.preview-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 3;
  background: rgba(0, 0, 0, 0.4) !important;
  backdrop-filter: blur(4px);
}

.preview-nav:hover {
  background: rgba(0, 0, 0, 0.6) !important;
}

.preview-nav-left {
  left: 16px;
}

.preview-nav-right {
  right: 16px;
}

/* Image preview */
.preview-image-container {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  overflow: auto;
}

.preview-image {
  max-width: 95%;
  max-height: 90vh;
  object-fit: contain;
  border-radius: 4px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
  transition: transform 0.2s ease;
  user-select: none;
}

/* PDF preview */
.preview-pdf {
  width: 90%;
  max-width: 900px;
  height: calc(100vh - 60px);
  border: none;
  border-radius: 4px;
  background: #fff;
}

/* Video preview */
.preview-video {
  max-width: 90%;
  max-height: 85vh;
  border-radius: 4px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

/* Text preview */
.preview-text {
  width: 80%;
  max-width: 800px;
  height: calc(100vh - 60px);
  border: none;
  border-radius: 4px;
  background: #fff;
}

/* AI Copilot */
.ai-copilot {
  background: #f0f7ff;
  border-top: 1px solid #e8ecf0;
  border-bottom: 1px solid #e8ecf0;
}

.ai-suggestion-box {
  background: #fff;
  border: 1px solid #d0e4f7;
  border-radius: 8px;
  padding: 16px 20px;
  font-size: 14px;
  line-height: 1.7;
  max-height: 400px;
  overflow-y: auto;
}

.ai-formatted :deep(strong) {
  font-weight: 600;
  color: #1a1a2e;
}

.ai-formatted :deep(em) {
  font-style: italic;
  color: #555;
}

.ai-formatted :deep(p) {
  margin: 8px 0;
}

.ai-internal-note {
  font-size: 12px;
  color: #8b6914;
  background: #fff8e1;
  border: 1px solid #ffe082;
  border-radius: 6px;
  padding: 10px 14px;
  line-height: 1.6;
  display: flex;
  align-items: flex-start;
  gap: 6px;
}

.ai-internal-note :deep(strong) {
  font-weight: 600;
}

/* Conversations */
.conversation-item {
  padding: 20px 0;
  border-bottom: 1px solid #f0f0f0;
}

.conversation-item:last-child {
  border-bottom: none;
}

.conversation-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: 600;
  font-size: 16px;
  flex-shrink: 0;
}

.conversation-header {
  margin-bottom: 2px;
  font-size: 13px;
}

.conversation-body {
  font-size: 14px;
  line-height: 1.7;
  color: #333;
}

.conversation-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 8px;
  opacity: 0;
  transition: opacity 0.15s;
}

.conversation-item:hover .conversation-actions {
  opacity: 1;
}

/* Conversation Quick Bar (Freshservice bottom reply bar) */
.conversation-quick-bar {
  display: flex;
  align-items: center;
  padding: 12px 24px;
  border-top: 1px solid #e8ecf0;
  background: #fafbfc;
}

/* Reply Section */
.reply-section {
  background: #fafbfc;
  border-top: 1px solid #e8ecf0;
}

.internal-note-editor :deep(.q-editor__content) {
  background: #fff8e1;
}

/* Sidebar */
.sidebar-status-card {
  padding: 16px 20px;
  border-bottom: 1px solid #e8ecf0;
}

.status-header {
  margin-bottom: 10px;
}

.status-text {
  font-size: 18px;
  font-weight: 700;
}

.status-row {
  display: flex;
  align-items: center;
  padding: 3px 0;
  font-size: 13px;
}

.status-label {
  color: #6b7280;
  margin-right: 8px;
}

.status-value {
  display: flex;
  align-items: center;
  color: #1a1a2e;
  font-weight: 500;
}

.priority-square {
  width: 10px;
  height: 10px;
  border-radius: 2px;
  display: inline-block;
  margin-right: 6px;
}

.sla-badge {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 3px;
  font-weight: 600;
}

/* SLA Countdown Rows (Freshservice style) */
.sla-countdown-row {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid #f0f0f0;
}

.sla-countdown-label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 4px;
}

.sla-countdown-detail {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.sla-countdown-date {
  font-size: 11px;
  color: #6b7280;
  flex: 1;
}

.sla-countdown-badge {
  font-size: 11px;
  padding: 3px 8px;
  border-radius: 4px;
  font-weight: 700;
  white-space: nowrap;
}

/* Sidebar Sections */
.sidebar-section {
  border-bottom: 1px solid #e8ecf0;
}

.sidebar-section-header {
  display: flex;
  align-items: center;
  padding: 14px 20px;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  user-select: none;
}

.sidebar-section-header.clickable {
  cursor: pointer;
}

.sidebar-section-header.clickable:hover {
  background: #f5f6f8;
}

.requester-info {
  padding: 0 20px 16px;
}

/* Properties */
.properties-grid {
  padding: 0 20px 16px;
}

.prop-row {
  display: flex;
  gap: 10px;
  margin-bottom: 8px;
}

.prop-field {
  flex: 1;
  min-width: 0;
}

.prop-field.full-width {
  flex: 1 1 100%;
}

.prop-label {
  display: block;
  font-size: 11px;
  font-weight: 500;
  color: #6b7280;
  margin-bottom: 3px;
}

.prop-label .required {
  color: #ef4444;
}

.prop-value-text {
  font-size: 12px;
  color: #374151;
  padding: 6px 0;
}

.prop-select :deep(.q-field__control) {
  min-height: 34px;
  font-size: 13px;
}

.prop-select :deep(.q-field__marginal) {
  height: 34px;
}

.update-btn {
  border-radius: 4px;
  font-weight: 600;
}

/* Responsive */
@media (max-width: 1024px) {
  .ticket-body {
    flex-wrap: wrap;
    height: auto;
  }

  .ticket-sidebar {
    width: 100%;
    min-width: 100%;
    max-width: 100%;
    border-top: 1px solid #e8ecf0;
  }

  .topbar-title {
    display: none;
  }

  .properties-grid .prop-row {
    flex-wrap: wrap;
  }
}
</style>
