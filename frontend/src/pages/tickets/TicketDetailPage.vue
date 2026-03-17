<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { Notify } from 'quasar'
import { getTicket, updateTicket, assignTicket, addComment, suggestResponse, improveText, uploadTicketAttachments, deleteTicketAttachment } from '@/api/tickets'
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
