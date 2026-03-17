<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import { getTicketViews, createTicketView, updateTicketView, deleteTicketView } from '@/api/ticketViews'
import type { TicketViewData } from '@/api/ticketViews'

const $q = useQuasar()
const auth = useAuthStore()

export interface TicketView {
  id: string
  label: string
  icon: string
  filters: Record<string, any>
  separator?: boolean
  adminOnly?: boolean
  agentOnly?: boolean
  isCustom?: boolean
  dbId?: number
  isShared?: boolean
}

const props = defineProps<{
  modelValue: boolean
  currentViewId: string
  currentFilters?: Record<string, any>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'select-view': [view: TicketView]
}>()

const searchQuery = ref('')
const customViews = ref<TicketViewData[]>([])
const loadingCustom = ref(false)

// --- Create/Edit dialog ---
const showCreateDialog = ref(false)
const editingView = ref<TicketViewData | null>(null)
const formName = ref('')
const formIcon = ref('bookmark')
const formShared = ref(false)
const savingView = ref(false)

const iconOptions = [
  'bookmark', 'star', 'flag', 'label', 'folder',
  'visibility', 'filter_alt', 'tune', 'dashboard',
  'inventory', 'assignment', 'task_alt', 'rule',
  'bolt', 'trending_up', 'schedule', 'person_search',
]

// --- Predefined views ---
const predefinedViews = computed<TicketView[]>(() => {
  const views: TicketView[] = [
    {
      id: 'urgent_high',
      label: 'Tickets urgentes y alta prioridad',
      icon: 'priority_high',
      filters: { priority: 'high,urgent', status_not_in: 'resolved,closed' },
    },
    {
      id: 'my_open_pending',
      label: 'Mis tickets abiertos y pendientes',
      icon: 'person',
      filters: { assigned_to_me: true, status: 'open,in_progress,pending' },
      agentOnly: true,
    },
    {
      id: 'my_overdue',
      label: 'Mis tickets atrasados',
      icon: 'alarm',
      filters: { assigned_to_me: true, overdue: true },
      agentOnly: true,
    },
    {
      id: 'incidents',
      label: 'Incidencias',
      icon: 'bug_report',
      filters: { type: 'incident', status_not_in: 'resolved,closed' },
    },
    {
      id: 'service_requests',
      label: 'Solicitudes de servicio',
      icon: 'build',
      filters: { type: 'request', status_not_in: 'resolved,closed' },
    },
    {
      id: 'major_incidents',
      label: 'Incidencias graves',
      icon: 'warning',
      filters: { priority: 'urgent', type: 'incident', status_not_in: 'resolved,closed' },
    },
    {
      id: 'unresolved',
      label: 'Todos los tickets sin resolver',
      icon: 'pending_actions',
      filters: { status_not_in: 'resolved,closed' },
      separator: true,
    },
    {
      id: 'new_and_open',
      label: 'Tickets nuevos y abiertos',
      icon: 'fiber_new',
      filters: { status: 'open' },
    },
    {
      id: 'all',
      label: 'Todos los tickets',
      icon: 'list',
      filters: {},
    },
    {
      id: 'my_requested',
      label: 'Tickets que he solicitado',
      icon: 'outgoing_mail',
      filters: { requester_id: '__me__' },
    },
    {
      id: 'in_progress',
      label: 'Tickets en progreso',
      icon: 'sync',
      filters: { status: 'in_progress' },
      separator: true,
    },
    {
      id: 'pending',
      label: 'Tickets pendientes',
      icon: 'hourglass_empty',
      filters: { status: 'pending' },
    },
    {
      id: 'resolved',
      label: 'Tickets resueltos',
      icon: 'check_circle_outline',
      filters: { status: 'resolved' },
    },
    {
      id: 'closed',
      label: 'Tickets cerrados',
      icon: 'archive',
      filters: { status: 'closed' },
    },
  ]

  return views.filter(v => {
    if (v.adminOnly && !auth.isAdmin) return false
    if (v.agentOnly && !(auth.isAdmin || auth.isAgent)) return false
    return true
  })
})

// Convert DB custom views to TicketView format
const customTicketViews = computed<TicketView[]>(() =>
  customViews.value.map(v => ({
    id: `custom_${v.id}`,
    label: v.name,
    icon: v.icon || 'bookmark',
    filters: v.filters || {},
    isCustom: true,
    dbId: v.id,
    isShared: v.is_shared,
  }))
)

const filteredPredefined = computed(() => {
  if (!searchQuery.value) return predefinedViews.value
  const q = searchQuery.value.toLowerCase()
  return predefinedViews.value.filter(v => v.label.toLowerCase().includes(q))
})

const filteredCustom = computed(() => {
  if (!searchQuery.value) return customTicketViews.value
  const q = searchQuery.value.toLowerCase()
  return customTicketViews.value.filter(v => v.label.toLowerCase().includes(q))
})

// --- Actions ---
function selectView(view: TicketView) {
  emit('select-view', view)
  emit('update:modelValue', false)
}

async function loadCustomViews() {
  if (!(auth.isAdmin || auth.isAgent)) return
  loadingCustom.value = true
  try {
    const res = await getTicketViews()
    customViews.value = res.data
  } catch {
    // Silent fail — custom views are optional
  } finally {
    loadingCustom.value = false
  }
}

function openCreateDialog() {
  editingView.value = null
  formName.value = ''
  formIcon.value = 'bookmark'
  formShared.value = false
  showCreateDialog.value = true
}

function openEditDialog(view: TicketView) {
  const dbView = customViews.value.find(v => v.id === view.dbId)
  if (!dbView) return
  editingView.value = dbView
  formName.value = dbView.name
  formIcon.value = dbView.icon || 'bookmark'
  formShared.value = dbView.is_shared || false
  showCreateDialog.value = true
}

async function saveView() {
  if (!formName.value.trim()) return
  savingView.value = true
  try {
    const payload = {
      name: formName.value.trim(),
      icon: formIcon.value,
      filters: props.currentFilters || {},
      is_shared: formShared.value,
    }

    if (editingView.value) {
      // Update existing
      await updateTicketView(editingView.value.id!, {
        name: payload.name,
        icon: payload.icon,
        is_shared: payload.is_shared,
      })
      $q.notify({ type: 'positive', message: 'Vista actualizada', timeout: 2000 })
    } else {
      // Create new with current filters
      await createTicketView(payload)
      $q.notify({ type: 'positive', message: 'Vista guardada', timeout: 2000 })
    }
    showCreateDialog.value = false
    await loadCustomViews()
  } catch {
    $q.notify({ type: 'negative', message: 'Error al guardar la vista' })
  } finally {
    savingView.value = false
  }
}

function confirmDeleteView(view: TicketView) {
  $q.dialog({
    title: 'Eliminar vista',
    message: `¿Eliminar la vista "${view.label}"?`,
    cancel: { label: 'Cancelar', flat: true, noCaps: true },
    ok: { label: 'Eliminar', color: 'negative', noCaps: true, unelevated: true },
    persistent: true,
  }).onOk(async () => {
    try {
      await deleteTicketView(view.dbId!)
      $q.notify({ type: 'positive', message: 'Vista eliminada', timeout: 2000 })
      await loadCustomViews()
    } catch {
      $q.notify({ type: 'negative', message: 'Error al eliminar la vista' })
    }
  })
}

// Load custom views when the sidebar opens
watch(() => props.modelValue, (open) => {
  if (open) loadCustomViews()
})

onMounted(() => {
  if (props.modelValue) loadCustomViews()
})
</script>

<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
    position="left"
    full-height
    transition-show="slide-right"
    transition-hide="slide-left"
  >
    <q-card class="views-sidebar-card column full-height" flat>
      <!-- Search -->
      <q-card-section class="q-pb-xs">
        <q-input
          v-model="searchQuery"
          placeholder="Buscar en las vistas del ticket"
          dense
          outlined
          clearable
          autofocus
        >
          <template v-slot:prepend>
            <q-icon name="search" size="18px" />
          </template>
        </q-input>
      </q-card-section>

      <!-- Views list -->
      <q-card-section class="col scroll q-pt-xs q-px-sm">
        <!-- Custom views section -->
        <template v-if="(auth.isAdmin || auth.isAgent) && filteredCustom.length > 0">
          <div class="text-overline text-grey-7 q-px-sm q-pb-xs" style="font-size: 10px; letter-spacing: 1.5px;">
            MIS VISTAS
          </div>

          <q-list dense>
            <q-item
              v-for="view in filteredCustom"
              :key="view.id"
              clickable
              v-close-popup
              :active="currentViewId === view.id"
              active-class="view-active"
              class="view-item rounded-borders"
              @click="selectView(view)"
            >
              <q-item-section side style="min-width: 32px;">
                <q-icon :name="view.icon" size="20px" :color="currentViewId === view.id ? 'primary' : 'grey-7'" />
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-body2" :class="currentViewId === view.id ? 'text-weight-bold' : ''">
                  {{ view.label }}
                </q-item-label>
                <q-item-label v-if="view.isShared" caption class="text-grey-6">
                  Compartida
                </q-item-label>
              </q-item-section>
              <q-item-section v-if="currentViewId === view.id" side>
                <q-icon name="check" color="primary" size="18px" />
              </q-item-section>
              <q-item-section side>
                <q-btn flat dense round icon="more_vert" size="xs" color="grey-6" @click.stop>
                  <q-menu>
                    <q-list dense style="min-width: 140px;">
                      <q-item clickable v-close-popup @click.stop="openEditDialog(view)">
                        <q-item-section side><q-icon name="edit" size="18px" /></q-item-section>
                        <q-item-section>Editar</q-item-section>
                      </q-item>
                      <q-item clickable v-close-popup @click.stop="confirmDeleteView(view)">
                        <q-item-section side><q-icon name="delete" size="18px" color="negative" /></q-item-section>
                        <q-item-section class="text-negative">Eliminar</q-item-section>
                      </q-item>
                    </q-list>
                  </q-menu>
                </q-btn>
              </q-item-section>
            </q-item>
          </q-list>

          <q-separator class="q-my-sm" />
        </template>

        <!-- Predefined views -->
        <div class="text-overline text-grey-7 q-px-sm q-pb-xs" style="font-size: 10px; letter-spacing: 1.5px;">
          TODAS LAS VISTAS
        </div>

        <q-list dense>
          <template v-for="view in filteredPredefined" :key="view.id">
            <q-separator v-if="view.separator" class="q-my-xs" />
            <q-item
              clickable
              v-close-popup
              :active="currentViewId === view.id"
              active-class="view-active"
              class="view-item rounded-borders"
              @click="selectView(view)"
            >
              <q-item-section side style="min-width: 32px;">
                <q-icon :name="view.icon" size="20px" :color="currentViewId === view.id ? 'primary' : 'grey-7'" />
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-body2" :class="currentViewId === view.id ? 'text-weight-bold' : ''">
                  {{ view.label }}
                </q-item-label>
              </q-item-section>
              <q-item-section v-if="currentViewId === view.id" side>
                <q-icon name="check" color="primary" size="18px" />
              </q-item-section>
            </q-item>
          </template>
        </q-list>
      </q-card-section>

      <!-- Save current view button -->
      <q-card-section v-if="auth.isAdmin || auth.isAgent" class="q-pt-none q-pb-sm q-px-sm">
        <q-btn
          color="primary"
          icon="add"
          label="Guardar vista actual"
          no-caps
          unelevated
          dense
          class="full-width"
          @click="openCreateDialog"
        />
      </q-card-section>
    </q-card>
  </q-dialog>

  <!-- Create/Edit View Dialog -->
  <q-dialog v-model="showCreateDialog" persistent>
    <q-card style="min-width: 380px;">
      <q-card-section>
        <div class="text-h6">{{ editingView ? 'Editar vista' : 'Guardar vista personalizada' }}</div>
        <div v-if="!editingView" class="text-caption text-grey-7">
          Se guardarán los filtros actualmente aplicados
        </div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-input
          v-model="formName"
          label="Nombre de la vista"
          dense
          outlined
          autofocus
          :rules="[(v: string) => !!v.trim() || 'Requerido']"
          class="q-mb-md"
        />

        <div class="text-caption text-grey-7 text-weight-medium q-mb-xs">Icono</div>
        <div class="icon-grid q-mb-md">
          <q-btn
            v-for="icon in iconOptions"
            :key="icon"
            :icon="icon"
            flat
            round
            dense
            :color="formIcon === icon ? 'primary' : 'grey-6'"
            :class="{ 'icon-selected': formIcon === icon }"
            @click="formIcon = icon"
          >
            <q-tooltip>{{ icon }}</q-tooltip>
          </q-btn>
        </div>

        <q-toggle
          v-if="auth.isAdmin"
          v-model="formShared"
          label="Compartir con todo el equipo"
          dense
        />
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat no-caps label="Cancelar" color="grey-7" v-close-popup />
        <q-btn
          color="primary"
          no-caps
          unelevated
          :label="editingView ? 'Actualizar' : 'Guardar'"
          :loading="savingView"
          :disable="!formName.trim()"
          @click="saveView"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<style scoped>
.views-sidebar-card {
  width: 320px;
  max-width: 85vw;
  border-radius: 0;
}

.view-item {
  margin: 1px 0;
  padding: 8px 12px;
  min-height: 38px;
  border-radius: 6px;
  transition: background-color 0.15s;
}

.view-item:hover {
  background-color: #f0f4f8;
}

.view-active {
  background-color: #e8f0fe !important;
}

.view-active .q-item__label {
  color: #1a73e8;
}

.icon-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 2px;
}

.icon-selected {
  background-color: #e8f0fe;
  border-radius: 50%;
}

/* Dark mode */
.body--dark .view-item:hover {
  background-color: #2a2a3a;
}

.body--dark .view-active {
  background-color: #1a2a4a !important;
}

.body--dark .icon-selected {
  background-color: #1a2a4a;
}
</style>
