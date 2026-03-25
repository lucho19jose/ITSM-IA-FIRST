<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQuasar } from 'quasar'
import { getRecentActivities } from '@/api/activities'
import type { ActivityLog } from '@/types'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const { t } = useI18n()
const $q = useQuasar()
const router = useRouter()
const drawerWidth = computed(() => $q.screen.lt.md ? $q.screen.width : 440)
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

  if (days === 0) return t('time.today')
  if (days === 1) return t('time.yesterday')
  return date.toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

function timeAgo(dateStr: string): string {
  const diffMin = Math.floor((Date.now() - new Date(dateStr).getTime()) / 60000)
  if (diffMin < 1) return t('time.now')
  if (diffMin < 60) return t('time.agoShortMin', { n: diffMin })
  const diffH = Math.floor(diffMin / 60)
  if (diffH < 24) return t('time.agoShortHour', { n: diffH })
  const diffD = Math.floor(diffH / 24)
  return t('time.agoShortDay', { n: diffD })
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
      return t('activities.createdTicket')
    case 'commented':
      return p?.is_internal ? t('activities.internalNoteTicket') : t('activities.repliedTicket')
    case 'assigned':
      return t('activities.assignedTicket')
    case 'closed':
      return t('activities.closedTicket')
    case 'reopened':
      return t('activities.reopenedTicket')
    case 'updated': {
      const fieldLabels: Record<string, string> = {
        status: t('activities.changedStatus'),
        priority: t('activities.changedPriority'),
        planned_start_date: t('activities.changedPlannedStart'),
        planned_end_date: t('activities.changedPlannedEnd'),
      }
      return fieldLabels[p?.field ?? ''] ?? t('activities.updatedTicket')
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
  <q-drawer v-model="drawerOpen" side="right" bordered :width="drawerWidth" overlay behavior="mobile" class="activities-drawer">
    <!-- Header -->
    <div class="drawer-header row items-center q-px-lg q-py-md">
      <div class="text-h6 text-weight-bold">{{ t('activities.title') }}</div>
      <q-space />
      <q-btn flat round dense icon="close" size="sm" @click="drawerOpen = false" />
    </div>

    <q-separator />

    <!-- Loading -->
    <q-linear-progress v-if="loading && activities.length === 0" indeterminate color="primary" />

    <!-- Empty state -->
    <div v-if="!loading && activities.length === 0" class="text-center q-pa-xl text-grey-5">
      <q-icon name="history" size="56px" class="q-mb-md" />
      <div class="text-body1">{{ t('activities.noActivities') }}</div>
      <div class="text-caption q-mt-xs">{{ t('activities.noActivitiesHint') }}</div>
    </div>

    <!-- Activity timeline -->
    <q-scroll-area v-else style="height: calc(100vh - 65px); height: calc(100dvh - 65px);">
      <div class="q-pa-md timeline-container">
        <template v-for="(group, dateLabel, groupIdx) in groupedActivities" :key="dateLabel">
          <!-- Date header -->
          <div class="timeline-date-header">
            <div class="timeline-rail">
              <div class="date-dot" />
            </div>
            <span class="text-weight-bold text-body2">{{ dateLabel }}</span>
          </div>

          <!-- Activity entries -->
          <div
            v-for="(activity, idx) in group"
            :key="activity.id"
            class="timeline-entry"
            :class="{ 'timeline-entry--last-in-group': idx === group.length - 1 && groupIdx === Object.keys(groupedActivities).length - 1 }"
          >
            <div class="timeline-rail">
              <div class="timeline-line" />
            </div>
            <div class="timeline-content">
              <q-avatar size="32px" :color="getActionColor(activity.action)" text-color="white" font-size="13px" class="activity-avatar">
                <img v-if="activity.user.avatar_url" :src="activity.user.avatar_url" />
                <span v-else>{{ activity.user.name.charAt(0).toUpperCase() }}</span>
              </q-avatar>
              <div class="timeline-text">
                <div class="text-body2" style="line-height: 1.45;">
                  <span class="text-weight-bold text-uppercase" style="font-size: 12px;">{{ activity.user.name }}</span>
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
            :label="t('activities.loadMore')"
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
  max-width: 100vw !important;
}

.drawer-header {
  min-height: 56px;
}

/* Timeline layout: fixed left rail + flexible content */
.timeline-container {
  overflow-x: hidden;
}

.timeline-rail {
  width: 24px;
  flex-shrink: 0;
  display: flex;
  justify-content: center;
  position: relative;
}

.date-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #ff9800;
  flex-shrink: 0;
  position: relative;
  z-index: 2;
}

.timeline-date-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0 4px;
}

.timeline-entry {
  display: flex;
  align-items: stretch;
  padding-bottom: 12px;
}

.timeline-line {
  width: 2px;
  background: transparent;
  flex: 1;
  min-height: 100%;
}

.timeline-content {
  display: flex;
  gap: 10px;
  flex: 1;
  min-width: 0;
  padding: 4px 0;
}

.timeline-text {
  flex: 1;
  min-width: 0;
  word-break: break-word;
}

.activity-avatar {
  flex-shrink: 0;
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
.body--dark .date-dot {
  background: #ff9800;
}
</style>
