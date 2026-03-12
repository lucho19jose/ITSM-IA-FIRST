<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { Notify } from 'quasar'
import { getTicket, updateTicket, assignTicket, addComment, suggestResponse, improveText } from '@/api/tickets'
import { getAgents } from '@/api/users'
import { getCategories } from '@/api/categories'
import { getEcho } from '@/utils/echo'
import type { Ticket, User, Category } from '@/types'

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
})

const canManage = computed(() => auth.isAdmin || auth.isAgent)

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
    editProps.assigned_to !== ticket.value.assigned_to
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

    // ─── Real-time: listen for updates and new comments ────────────
    const echo = getEcho()
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
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  const echo = getEcho()
  echo.leave(`ticket.${props.id}`)
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
    ticket.value?.comments?.push(res.data)
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
            <q-btn flat dense icon="star_border" color="grey-6" size="sm">
              <q-tooltip>Favorito</q-tooltip>
            </q-btn>

            <q-btn v-if="canManage" outline color="grey-8" no-caps dense class="topbar-btn">
              Compartir
              <q-icon name="arrow_drop_down" size="18px" />
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
                <q-list dense style="min-width: 160px">
                  <q-item clickable v-close-popup>
                    <q-item-section avatar><q-icon name="delete" size="18px" /></q-item-section>
                    <q-item-section>Eliminar</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup>
                    <q-item-section avatar><q-icon name="print" size="18px" /></q-item-section>
                    <q-item-section>Imprimir</q-item-section>
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
                  <span class="text-weight-medium text-dark">{{ ticket.requester?.name }}</span>
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
            <q-tab name="related" label="Tickets relacionados" />
            <q-tab name="tasks" label="Tareas" />
            <q-tab name="activity" label="Actividad" />
            <q-tab name="resolution" label="Resolucion" />
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
                <div class="row items-center q-mt-sm">
                  <q-btn flat no-caps dense color="grey-7" icon="attach_file" label="Adjuntar" />
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
            <q-tab-panel name="related" class="q-pa-lg">
              <div class="text-grey-5 text-center q-pa-xl">
                <q-icon name="link" size="48px" class="q-mb-sm" /><br>
                No hay tickets relacionados
              </div>
            </q-tab-panel>

            <!-- Tasks Tab -->
            <q-tab-panel name="tasks" class="q-pa-lg">
              <div class="text-grey-5 text-center q-pa-xl">
                <q-icon name="task" size="48px" class="q-mb-sm" /><br>
                No hay tareas asociadas
              </div>
            </q-tab-panel>

            <!-- Activity Tab -->
            <q-tab-panel name="activity" class="q-pa-lg">
              <div class="text-grey-5 text-center q-pa-xl">
                <q-icon name="history" size="48px" class="q-mb-sm" /><br>
                Historial de actividad del ticket
              </div>
            </q-tab-panel>

            <!-- Resolution Tab -->
            <q-tab-panel name="resolution" class="q-pa-lg">
              <div v-if="ticket.resolved_at" class="q-pa-md">
                <div class="text-subtitle2 q-mb-sm">Resuelto el</div>
                <div>{{ formatDate(ticket.resolved_at) }}</div>
              </div>
              <div v-else class="text-grey-5 text-center q-pa-xl">
                <q-icon name="task_alt" size="48px" class="q-mb-sm" /><br>
                Este ticket aun no ha sido resuelto
              </div>
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
                  <div class="text-weight-medium text-primary" style="font-size: 14px; cursor: pointer">
                    {{ ticket.requester?.name }}
                  </div>
                </div>
              </div>
              <div class="text-caption text-primary q-ml-sm" style="cursor: pointer; margin-left: 48px">
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

                <!-- Departamento (full width) -->
                <div class="prop-row">
                  <div class="prop-field full-width">
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
