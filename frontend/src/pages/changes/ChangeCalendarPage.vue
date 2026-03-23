<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { getChangeCalendar } from '@/api/changeRequests'

const { t } = useI18n()
const router = useRouter()

const loading = ref(false)
const events = ref<any[]>([])
const currentDate = ref(new Date())

const currentYear = computed(() => currentDate.value.getFullYear())
const currentMonth = computed(() => currentDate.value.getMonth())

const monthLabel = computed(() => {
  return currentDate.value.toLocaleDateString(undefined, { year: 'numeric', month: 'long' })
})

// Calendar grid
const daysInMonth = computed(() => new Date(currentYear.value, currentMonth.value + 1, 0).getDate())
const firstDayOfWeek = computed(() => new Date(currentYear.value, currentMonth.value, 1).getDay())

const calendarDays = computed(() => {
  const days: { day: number; events: any[] }[] = []
  for (let d = 1; d <= daysInMonth.value; d++) {
    const date = new Date(currentYear.value, currentMonth.value, d)
    const dateStr = date.toISOString().slice(0, 10)
    const dayEvents = events.value.filter(e => {
      const start = e.start.slice(0, 10)
      const end = e.end.slice(0, 10)
      return dateStr >= start && dateStr <= end
    })
    days.push({ day: d, events: dayEvents })
  }
  return days
})

const weekDays = computed(() => {
  const days = []
  const base = new Date(2024, 0, 7) // Sunday
  for (let i = 0; i < 7; i++) {
    const d = new Date(base)
    d.setDate(base.getDate() + i)
    days.push(d.toLocaleDateString(undefined, { weekday: 'short' }))
  }
  return days
})

function prevMonth() {
  currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1)
  loadCalendar()
}

function nextMonth() {
  currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1)
  loadCalendar()
}

function typeColor(type: string): string {
  return { standard: 'blue', normal: 'orange', emergency: 'red' }[type] || 'grey'
}

function statusColor(status: string): string {
  const colors: Record<string, string> = {
    scheduled: 'teal', implementing: 'amber', implemented: 'light-green',
    approved: 'green', review: 'deep-orange', closed: 'blue-grey',
  }
  return colors[status] || 'primary'
}

async function loadCalendar() {
  loading.value = true
  try {
    const from = new Date(currentYear.value, currentMonth.value, 1).toISOString()
    const to = new Date(currentYear.value, currentMonth.value + 1, 0, 23, 59, 59).toISOString()
    const res = await getChangeCalendar({ from, to })
    events.value = res.data || []
  } catch { /* ignore */ }
  finally { loading.value = false }
}

onMounted(() => loadCalendar())
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="text-h5 text-weight-bold">{{ t('changes.calendar') }}</div>
      <q-space />
      <q-btn flat icon="chevron_left" dense @click="prevMonth" />
      <div class="text-subtitle1 text-weight-medium q-mx-md" style="min-width: 160px; text-align: center;">
        {{ monthLabel }}
      </div>
      <q-btn flat icon="chevron_right" dense @click="nextMonth" />
    </div>

    <q-linear-progress v-if="loading" indeterminate color="primary" class="q-mb-sm" />

    <q-card flat bordered>
      <q-card-section class="q-pa-none">
        <!-- Week day headers -->
        <div class="row calendar-header">
          <div v-for="wd in weekDays" :key="wd" class="col calendar-header-cell text-center text-caption text-weight-bold text-grey-7 q-py-sm">
            {{ wd }}
          </div>
        </div>

        <!-- Calendar grid -->
        <div class="row" style="flex-wrap: wrap;">
          <!-- Empty cells for offset -->
          <div v-for="n in firstDayOfWeek" :key="'empty-' + n" class="col calendar-cell" style="min-height: 100px;" />

          <!-- Day cells -->
          <div
            v-for="d in calendarDays"
            :key="d.day"
            class="col calendar-cell"
            style="min-height: 100px;"
          >
            <div class="text-caption text-grey-7 q-mb-xs">{{ d.day }}</div>
            <div v-for="ev in d.events.slice(0, 3)" :key="ev.id" class="q-mb-xs">
              <q-badge
                :color="statusColor(ev.status)"
                class="cursor-pointer full-width text-left calendar-event"
                style="font-size: 10px; padding: 2px 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; max-width: 100%;"
                @click="router.push(`/changes/${ev.id}`)"
              >
                <q-icon :name="ev.type === 'emergency' ? 'bolt' : 'swap_horiz'" size="10px" class="q-mr-xs" />
                {{ ev.title }}
                <q-tooltip>
                  <div class="text-weight-bold">{{ ev.title }}</div>
                  <div>{{ t(`changes.types.${ev.type}`) }} | {{ t(`changes.statuses.${ev.status}`) }}</div>
                  <div v-if="ev.assignee">{{ t('changes.assignee') }}: {{ ev.assignee.name }}</div>
                  <div>{{ new Date(ev.start).toLocaleString() }} - {{ new Date(ev.end).toLocaleString() }}</div>
                </q-tooltip>
              </q-badge>
            </div>
            <div v-if="d.events.length > 3" class="text-caption text-primary cursor-pointer">
              +{{ d.events.length - 3 }} {{ t('changes.more') }}
            </div>
          </div>
        </div>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<style scoped>
.calendar-header {
  border-bottom: 2px solid #e0e0e0;
}
.calendar-header-cell {
  min-width: calc(100% / 7);
  max-width: calc(100% / 7);
}
.calendar-cell {
  min-width: calc(100% / 7);
  max-width: calc(100% / 7);
  border: 1px solid #f0f0f0;
  padding: 4px 6px;
}
.calendar-event:hover {
  opacity: 0.85;
}
</style>
