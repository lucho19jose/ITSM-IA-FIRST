<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { getRecentActivities } from '@/api/activities'
import type { ActivityLog } from '@/types'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const router = useRouter()
const activities = ref<ActivityLog[]>([])
const loading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)

const drawerOpen = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

watch(() => props.modelValue, (open) => {
  if (open) {
    currentPage.value = 1
    activities.value = []
    loadActivities()
  }
})

async function loadActivities() {
  loading.value = true
  try {
    const res = await getRecentActivities({ page: currentPage.value, per_page: 30 })
    if (currentPage.value === 1) {
      activities.value = res.data || []
    } else {
      activities.value.push(...(res.data || []))
    }
    lastPage.value = res.meta?.last_page ?? 1
  } catch { /* ignore */ }
  finally { loading.value = false }
}

function loadMore() {
  if (currentPage.value < lastPage.value && !loading.value) {
    currentPage.value++
    loadActivities()
  }
}

// Group activities by date
const groupedActivities = computed(() => {
  const groups: Record<string, ActivityLog[]> = {}
  for (const a of activities.value) {
    const date = new Date(a.created_at)
    const key = formatDateGroup(date)
    if (!groups[key]) groups[key] = []
    groups[key].push(a)
  }
  return groups
})

function formatDateGroup(date: Date): string {
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const target = new Date(date.getFullYear(), date.getMonth(), date.getDate())
  const diff = today.getTime() - target.getTime()
  const days = Math.floor(diff / 86400000)

  if (days === 0) return 'Hoy'
  if (days === 1) return 'Ayer'
  return date.toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

function timeAgo(dateStr: string): string {
  const diffMin = Math.floor((Date.now() - new Date(dateStr).getTime()) / 60000)
  if (diffMin < 1) return 'ahora'
  if (diffMin < 60) return `hace ${diffMin} minuto${diffMin > 1 ? 's' : ''}`
  const diffH = Math.floor(diffMin / 60)
  if (diffH < 24) return `hace ${diffH} hora${diffH > 1 ? 's' : ''}`
  const diffD = Math.floor(diffH / 24)
  return `hace ${diffD} día${diffD > 1 ? 's' : ''}`
}

function getActionIcon(action: string): string {
  switch (action) {
    case 'created': return 'add_circle'
    case 'commented': return 'chat_bubble'
    case 'assigned': return 'person_add'
    case 'closed': return 'check_circle'
    case 'reopened': return 'replay'
    case 'updated': return 'edit'
    default: return 'info'
  }
}

function getActionColor(action: string): string {
  switch (action) {
    case 'created': return 'green'
    case 'commented': return 'blue'
    case 'assigned': return 'purple'
    case 'closed': return 'grey'
    case 'reopened': return 'orange'
    case 'updated': return 'amber-8'
    default: return 'grey'
  }
}

function goToTicket(activity: ActivityLog) {
  const ticketId = activity.properties?.ticket_id
  if (ticketId) {
    drawerOpen.value = false
    router.push(`/tickets/${ticketId}`)
  }
}

function getActionText(activity: ActivityLog): string {
  const p = activity.properties
  switch (activity.action) {
    case 'created':
      return 'envió un nuevo ticket'
    case 'commented':
      return p?.is_internal ? 'ha enviado una nota interna al ticket' : 'ha enviado una reply al ticket'
    case 'assigned':
      return `asignó el ticket`
    case 'closed':
      return 'cerró el ticket'
    case 'reopened':
      return 'reabrió el ticket'
    case 'updated': {
      const fieldLabels: Record<string, string> = {
        status: 'cambió el estado del ticket',
        priority: 'cambió la prioridad del ticket',
        planned_start_date: 'cambió la fecha de inicio planificada de',
        planned_end_date: 'cambió la fecha de finalización planificada de',
      }
      return fieldLabels[p?.field ?? ''] ?? 'actualizó el ticket'
    }
    default:
      return activity.description
  }
}

function getActionSuffix(activity: ActivityLog): string {
  const p = activity.properties
  if (activity.action === 'assigned' && p?.assignee_name) {
    return ` a ${p.assignee_name}`
  }
  if (activity.action === 'updated' && p?.display_value) {
    return ` a ${p.display_value}`
  }
  return ''
}
</script>

<template>
  <q-drawer v-model="drawerOpen" side="right" bordered :width="440" overlay behavior="mobile" class="activities-drawer">
    <!-- Header -->
    <div class="drawer-header row items-center q-px-lg q-py-md">
      <div class="text-h6 text-weight-bold">Actividades recientes</div>
      <q-space />
      <q-btn flat round dense icon="close" size="sm" @click="drawerOpen = false" />
    </div>

    <q-separator />

    <!-- Loading -->
    <q-linear-progress v-if="loading && activities.length === 0" indeterminate color="primary" />

    <!-- Empty state -->
    <div v-if="!loading && activities.length === 0" class="text-center q-pa-xl text-grey-5">
      <q-icon name="history" size="56px" class="q-mb-md" />
      <div class="text-body1">Sin actividades recientes</div>
      <div class="text-caption q-mt-xs">Las actividades de tickets aparecerán aquí</div>
    </div>

    <!-- Activity timeline -->
    <q-scroll-area v-else style="height: calc(100vh - 65px);">
      <div class="q-pa-md">
        <template v-for="(group, dateLabel) in groupedActivities" :key="dateLabel">
          <!-- Date header -->
          <div class="row items-center q-mb-md q-mt-sm">
            <div class="date-dot" />
            <span class="text-weight-bold text-body2 q-ml-sm">{{ dateLabel }}</span>
          </div>

          <!-- Activity entries -->
          <div
            v-for="(activity, idx) in group"
            :key="activity.id"
            class="activity-item q-mb-sm"
            :class="{ 'activity-item--last': idx === group.length - 1 }"
          >
            <div class="activity-line" />
            <div class="row no-wrap items-start q-gutter-sm">
              <!-- User avatar -->
              <q-avatar size="34px" :color="getActionColor(activity.action)" text-color="white" font-size="14px" class="activity-avatar">
                <img v-if="activity.user.avatar_url" :src="activity.user.avatar_url" />
                <span v-else>{{ activity.user.name.charAt(0).toUpperCase() }}</span>
              </q-avatar>

              <!-- Content -->
              <div class="col q-pl-xs">
                <div class="text-body2" style="line-height: 1.5;">
                  <span class="text-weight-bold text-uppercase" style="font-size: 13px;">{{ activity.user.name }}</span>
                  {{ ' ' }}
                  <span>{{ getActionText(activity) }}</span>
                  {{ ' ' }}
                  <a
                    v-if="activity.properties?.ticket_title"
                    class="activity-ticket-link"
                    @click.prevent="goToTicket(activity)"
                  >
                    {{ activity.properties.ticket_title }} ({{ activity.properties.ticket_number }})
                  </a>
                  <span v-if="getActionSuffix(activity)">{{ getActionSuffix(activity) }}</span>
                </div>
                <div class="text-caption text-grey q-mt-xs">{{ timeAgo(activity.created_at) }}</div>
              </div>
            </div>
          </div>
        </template>

        <!-- Load more -->
        <div v-if="currentPage < lastPage" class="text-center q-py-md">
          <q-btn
            flat no-caps dense
            color="primary"
            label="Cargar más actividades"
            :loading="loading"
            @click="loadMore"
          />
        </div>

        <q-linear-progress v-if="loading && activities.length > 0" indeterminate color="primary" class="q-mt-sm" />
      </div>
    </q-scroll-area>
  </q-drawer>
</template>

<style scoped>
.activities-drawer {
  z-index: 3000;
}

.drawer-header {
  min-height: 56px;
}

.date-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #ff9800;
  flex-shrink: 0;
}

.activity-item {
  position: relative;
  padding-left: 6px;
  margin-left: 0;
}

.activity-line {
  position: absolute;
  left: 5px;
  top: 0;
  bottom: -8px;
  width: 2px;
  background: #e0e0e0;
}

.activity-item--last .activity-line {
  display: none;
}

.activity-avatar {
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}

.activity-ticket-link {
  color: var(--q-primary, #1976d2);
  text-decoration: underline;
  cursor: pointer;
  word-break: break-word;
}

.activity-ticket-link:hover {
  text-decoration: none;
}

/* Dark mode support */
.body--dark .activity-line {
  background: #444;
}

.body--dark .date-dot {
  background: #ff9800;
}
</style>
