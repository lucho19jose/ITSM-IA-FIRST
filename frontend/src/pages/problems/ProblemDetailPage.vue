<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { Notify, useQuasar } from 'quasar'
import {
  getProblem, updateProblem, linkTickets, unlinkTicket,
  promoteToKnownError, updateRootCause, resolveProblem, closeProblem,
} from '@/api/problems'
import { getCategories } from '@/api/categories'
import { getAgents } from '@/api/users'
import { getDepartments } from '@/api/departments'
import { getTickets } from '@/api/tickets'
import { getRecentActivities } from '@/api/activities'
import type { Problem, Category, User, Department, Ticket, ActivityLog } from '@/types'

const props = defineProps<{ id: string }>()
const { t } = useI18n()
const router = useRouter()
const $q = useQuasar()

const loading = ref(true)
const problem = ref<Problem | null>(null)
const categories = ref<Category[]>([])
const agents = ref<User[]>([])
const departments = ref<Department[]>([])
const activeTab = ref('details')
const saving = ref(false)

// Status workflow steps
const statusSteps = [
  'logged', 'categorized', 'investigating', 'root_cause_identified',
  'known_error', 'resolved', 'closed',
]

const currentStepIndex = computed(() => {
  if (!problem.value) return 0
  return statusSteps.indexOf(problem.value.status)
})

function statusColor(status: string) {
  const colors: Record<string, string> = {
    logged: 'blue-grey', categorized: 'blue', investigating: 'orange',
    root_cause_identified: 'deep-purple', known_error: 'red',
    resolved: 'green', closed: 'grey',
  }
  return colors[status] || 'grey'
}

function priorityColor(priority: string) {
  const colors: Record<string, string> = {
    low: 'green', medium: 'orange', high: 'red', critical: 'deep-purple',
  }
  return colors[priority] || 'grey'
}

function impactColor(impact: string) {
  const colors: Record<string, string> = {
    low: 'green', medium: 'orange', high: 'red', extensive: 'deep-purple',
  }
  return colors[impact] || 'grey'
}

// ─── Load data ───────────────────────────────────────────────────────────────
async function loadProblem() {
  loading.value = true
  try {
    const res = await getProblem(Number(props.id))
    problem.value = res.data
  } catch {
    router.push({ name: 'problems' })
  } finally { loading.value = false }
}

async function loadRefData() {
  try {
    const [catRes, agentRes, deptRes] = await Promise.all([
      getCategories(), getAgents(), getDepartments(),
    ])
    categories.value = catRes.data || []
    agents.value = agentRes.data || []
    departments.value = deptRes.data || []
  } catch { /* ignore */ }
}

// ─── Property updates ────────────────────────────────────────────────────────
async function onUpdateField(field: string, value: any) {
  if (!problem.value) return
  saving.value = true
  try {
    const res = await updateProblem(problem.value.id, { [field]: value } as any)
    problem.value = { ...problem.value, ...res.data }
    Notify.create({ type: 'positive', message: t('problems.updated') })
  } finally { saving.value = false }
}

async function onUpdateStatus(newStatus: string) {
  await onUpdateField('status', newStatus)
}

// ─── Root Cause ──────────────────────────────────────────────────────────────
const showRootCauseDialog = ref(false)
const rootCauseForm = reactive({ root_cause: '', workaround: '' })

function openRootCauseDialog() {
  rootCauseForm.root_cause = problem.value?.root_cause || ''
  rootCauseForm.workaround = problem.value?.workaround || ''
  showRootCauseDialog.value = true
}

async function onSaveRootCause() {
  if (!problem.value) return
  saving.value = true
  try {
    const res = await updateRootCause(problem.value.id, {
      root_cause: rootCauseForm.root_cause,
      workaround: rootCauseForm.workaround || undefined,
    })
    problem.value = { ...problem.value, ...res.data }
    showRootCauseDialog.value = false
    Notify.create({ type: 'positive', message: res.message })
  } finally { saving.value = false }
}

// ─── Promote to Known Error ──────────────────────────────────────────────────
async function onPromoteKnownError() {
  if (!problem.value) return
  $q.dialog({
    title: t('problems.promoteKnownError'),
    message: t('problems.promoteKnownErrorConfirm'),
    cancel: true,
  }).onOk(async () => {
    saving.value = true
    try {
      const res = await promoteToKnownError(problem.value!.id)
      problem.value = { ...problem.value!, ...res.data }
      Notify.create({ type: 'positive', message: res.message })
    } finally { saving.value = false }
  })
}

// ─── Resolve ─────────────────────────────────────────────────────────────────
const showResolveDialog = ref(false)
const resolutionText = ref('')

async function onResolve() {
  if (!problem.value) return
  saving.value = true
  try {
    const res = await resolveProblem(problem.value.id, resolutionText.value)
    problem.value = { ...problem.value, ...res.data }
    showResolveDialog.value = false
    Notify.create({ type: 'positive', message: res.message })
  } finally { saving.value = false }
}

// ─── Close ───────────────────────────────────────────────────────────────────
async function onClose() {
  if (!problem.value) return
  $q.dialog({
    title: t('problems.closeProblem'),
    message: t('problems.closeConfirm'),
    cancel: true,
  }).onOk(async () => {
    saving.value = true
    try {
      const res = await closeProblem(problem.value!.id)
      problem.value = { ...problem.value!, ...res.data }
      Notify.create({ type: 'positive', message: res.message })
    } finally { saving.value = false }
  })
}

// ─── Link tickets ────────────────────────────────────────────────────────────
const showLinkDialog = ref(false)
const ticketSearch = ref('')
const ticketSearchResults = ref<Ticket[]>([])
const ticketSearching = ref(false)

async function onSearchTicketsForLink() {
  if (!ticketSearch.value.trim()) return
  ticketSearching.value = true
  try {
    const existingIds = problem.value?.tickets?.map(t => t.id) || []
    const res = await getTickets({ search: ticketSearch.value, per_page: 10 })
    ticketSearchResults.value = (res.data || []).filter(
      (t: Ticket) => !existingIds.includes(t.id)
    )
  } catch { /* ignore */ }
  finally { ticketSearching.value = false }
}

async function onLinkTicket(ticketId: number) {
  if (!problem.value) return
  try {
    const res = await linkTickets(problem.value.id, [ticketId])
    problem.value = { ...problem.value, ...res.data }
    ticketSearch.value = ''
    ticketSearchResults.value = []
    showLinkDialog.value = false
    Notify.create({ type: 'positive', message: res.message })
  } catch { /* ignore */ }
}

async function onUnlinkTicket(ticketId: number) {
  if (!problem.value) return
  try {
    const res = await unlinkTicket(problem.value.id, ticketId)
    problem.value = { ...problem.value, ...res.data }
    Notify.create({ type: 'positive', message: res.message })
  } catch { /* ignore */ }
}

// ─── Activity Log ────────────────────────────────────────────────────────────
const activities = ref<ActivityLog[]>([])
const activitiesLoading = ref(false)

async function loadActivities() {
  activitiesLoading.value = true
  try {
    const res = await getRecentActivities({ per_page: 50 })
    activities.value = (res.data || []).filter(
      (a: ActivityLog) => a.properties?.problem_id === problem.value?.id
    )
  } catch { /* ignore */ }
  finally { activitiesLoading.value = false }
}

onMounted(async () => {
  await Promise.all([loadProblem(), loadRefData()])
  loadActivities()
})
</script>

<template>
  <q-page padding>
    <q-inner-loading :showing="loading" />

    <template v-if="problem">
      <!-- Header -->
      <div class="row items-center q-mb-md">
        <q-btn flat icon="arrow_back" @click="router.push({ name: 'problems' })" />
        <div class="col q-ml-sm">
          <div class="text-h5 text-weight-bold">{{ problem.title }}</div>
          <div class="text-caption text-grey">
            {{ t('problems.createdAt') }}: {{ new Date(problem.created_at).toLocaleString() }}
          </div>
        </div>
        <div class="q-gutter-sm">
          <q-btn
            v-if="!['resolved', 'closed'].includes(problem.status)"
            outline color="deep-purple"
            icon="search"
            :label="t('problems.updateRootCause')"
            @click="openRootCauseDialog"
          />
          <q-btn
            v-if="problem.root_cause && !problem.is_known_error && !['resolved', 'closed'].includes(problem.status)"
            outline color="red"
            icon="warning"
            :label="t('problems.promoteKnownError')"
            @click="onPromoteKnownError"
          />
          <q-btn
            v-if="!['resolved', 'closed'].includes(problem.status)"
            color="green"
            icon="check_circle"
            :label="t('problems.resolve')"
            @click="showResolveDialog = true"
          />
          <q-btn
            v-if="problem.status !== 'closed'"
            outline color="grey"
            icon="lock"
            :label="t('problems.close')"
            @click="onClose"
          />
        </div>
      </div>

      <!-- Status workflow stepper -->
      <q-card flat bordered class="q-mb-md">
        <q-card-section class="q-pa-sm">
          <div class="row items-center justify-between">
            <div
              v-for="(step, idx) in statusSteps"
              :key="step"
              class="col text-center"
              :class="{ 'cursor-pointer': idx <= currentStepIndex + 1 && !['resolved','closed'].includes(problem.status) }"
              @click="idx <= currentStepIndex + 1 && !['resolved','closed'].includes(problem.status) && onUpdateStatus(step)"
            >
              <q-icon
                :name="idx <= currentStepIndex ? 'check_circle' : 'radio_button_unchecked'"
                :color="idx <= currentStepIndex ? statusColor(problem.status) : 'grey-5'"
                size="24px"
              />
              <div class="text-caption" :class="idx <= currentStepIndex ? 'text-weight-bold' : 'text-grey'">
                {{ t(`problems.statuses.${step}`) }}
              </div>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <div class="row q-col-gutter-md">
        <!-- Main content -->
        <div class="col-12 col-md-8">
          <q-card flat bordered>
            <q-tabs v-model="activeTab" dense align="left" class="text-grey" active-color="primary" indicator-color="primary">
              <q-tab name="details" :label="t('problems.tabs.details')" />
              <q-tab name="incidents" :label="t('problems.tabs.relatedIncidents')" />
              <q-tab name="known_errors" :label="t('problems.tabs.knownErrors')" />
              <q-tab name="activity" :label="t('problems.tabs.activity')" />
            </q-tabs>
            <q-separator />

            <q-tab-panels v-model="activeTab" animated>
              <!-- Details Tab -->
              <q-tab-panel name="details">
                <div class="text-subtitle2 text-weight-bold q-mb-sm">{{ t('problems.fields.description') }}</div>
                <div class="q-mb-lg" style="white-space: pre-wrap;">{{ problem.description }}</div>

                <q-separator class="q-my-md" />

                <div class="text-subtitle2 text-weight-bold q-mb-sm">{{ t('problems.fields.rootCause') }}</div>
                <div v-if="problem.root_cause" style="white-space: pre-wrap;" class="q-mb-md">{{ problem.root_cause }}</div>
                <div v-else class="text-grey text-italic q-mb-md">{{ t('problems.noRootCause') }}</div>

                <div class="text-subtitle2 text-weight-bold q-mb-sm">{{ t('problems.fields.workaround') }}</div>
                <div v-if="problem.workaround" style="white-space: pre-wrap;" class="q-mb-md">{{ problem.workaround }}</div>
                <div v-else class="text-grey text-italic q-mb-md">{{ t('problems.noWorkaround') }}</div>

                <q-separator class="q-my-md" />

                <div class="text-subtitle2 text-weight-bold q-mb-sm">{{ t('problems.fields.resolution') }}</div>
                <div v-if="problem.resolution" style="white-space: pre-wrap;">{{ problem.resolution }}</div>
                <div v-else class="text-grey text-italic">{{ t('problems.noResolution') }}</div>
              </q-tab-panel>

              <!-- Related Incidents Tab -->
              <q-tab-panel name="incidents">
                <div class="row items-center q-mb-md">
                  <div class="col text-subtitle1 text-weight-medium">
                    {{ t('problems.tabs.relatedIncidents') }} ({{ problem.tickets?.length || 0 }})
                  </div>
                  <q-btn
                    v-if="!['resolved', 'closed'].includes(problem.status)"
                    outline color="primary" icon="add" :label="t('problems.linkTicket')"
                    @click="showLinkDialog = true"
                  />
                </div>

                <q-list v-if="problem.tickets?.length" bordered separator class="rounded-borders">
                  <q-item v-for="ticket in problem.tickets" :key="ticket.id" clickable @click="router.push({ name: 'ticket-detail', params: { id: ticket.id } })">
                    <q-item-section>
                      <q-item-label>{{ ticket.ticket_number }} - {{ ticket.title }}</q-item-label>
                      <q-item-label caption>
                        <q-chip dense size="sm" :color="ticket.status === 'closed' ? 'grey' : 'blue'" text-color="white">{{ ticket.status }}</q-chip>
                        <q-chip dense size="sm" class="q-ml-xs">{{ ticket.priority }}</q-chip>
                        <span v-if="ticket.assignee" class="q-ml-sm text-grey">{{ ticket.assignee.name }}</span>
                      </q-item-label>
                    </q-item-section>
                    <q-item-section side v-if="!['resolved', 'closed'].includes(problem.status)">
                      <q-btn flat dense round icon="link_off" color="negative" @click.stop="onUnlinkTicket(ticket.id)">
                        <q-tooltip>{{ t('problems.unlinkTicket') }}</q-tooltip>
                      </q-btn>
                    </q-item-section>
                  </q-item>
                </q-list>
                <div v-else class="text-grey text-center q-pa-lg">
                  <q-icon name="confirmation_number" size="48px" class="q-mb-sm" />
                  <div>{{ t('problems.noLinkedTickets') }}</div>
                </div>
              </q-tab-panel>

              <!-- Known Errors Tab -->
              <q-tab-panel name="known_errors">
                <div class="row items-center q-mb-md">
                  <div class="col text-subtitle1 text-weight-medium">
                    {{ t('problems.tabs.knownErrors') }} ({{ problem.known_errors?.length || 0 }})
                  </div>
                  <q-btn
                    v-if="problem.root_cause && !problem.is_known_error && !['resolved', 'closed'].includes(problem.status)"
                    outline color="red" icon="warning" :label="t('problems.promoteKnownError')"
                    @click="onPromoteKnownError"
                  />
                </div>

                <q-list v-if="problem.known_errors?.length" bordered separator class="rounded-borders">
                  <q-item v-for="ke in problem.known_errors" :key="ke.id">
                    <q-item-section>
                      <q-item-label class="text-weight-medium">{{ ke.title }}</q-item-label>
                      <q-item-label caption>{{ ke.description }}</q-item-label>
                      <q-item-label v-if="ke.workaround" caption class="q-mt-xs">
                        <strong>{{ t('problems.fields.workaround') }}:</strong> {{ ke.workaround }}
                      </q-item-label>
                    </q-item-section>
                    <q-item-section side>
                      <q-chip dense :color="ke.status === 'resolved' ? 'green' : 'orange'" text-color="white" size="sm">
                        {{ ke.status }}
                      </q-chip>
                    </q-item-section>
                  </q-item>
                </q-list>
                <div v-else class="text-grey text-center q-pa-lg">
                  <q-icon name="warning" size="48px" class="q-mb-sm" />
                  <div>{{ t('problems.noKnownErrors') }}</div>
                </div>
              </q-tab-panel>

              <!-- Activity Tab -->
              <q-tab-panel name="activity">
                <q-inner-loading :showing="activitiesLoading" />
                <q-timeline v-if="activities.length" color="primary">
                  <q-timeline-entry
                    v-for="activity in activities"
                    :key="activity.id"
                    :subtitle="new Date(activity.created_at).toLocaleString()"
                  >
                    <div class="text-weight-medium">{{ activity.user?.name }}</div>
                    <div>{{ activity.description }}</div>
                  </q-timeline-entry>
                </q-timeline>
                <div v-else class="text-grey text-center q-pa-lg">
                  {{ t('problems.noActivity') }}
                </div>
              </q-tab-panel>
            </q-tab-panels>
          </q-card>
        </div>

        <!-- Sidebar -->
        <div class="col-12 col-md-4">
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle2 text-weight-bold q-mb-md">{{ t('problems.properties') }}</div>

              <!-- Status -->
              <div class="q-mb-md">
                <div class="text-caption text-grey">{{ t('common.status') }}</div>
                <q-chip :color="statusColor(problem.status)" text-color="white">
                  {{ t(`problems.statuses.${problem.status}`) }}
                </q-chip>
              </div>

              <!-- Priority -->
              <div class="q-mb-md">
                <div class="text-caption text-grey">{{ t('common.priority') }}</div>
                <q-select
                  :model-value="problem.priority"
                  :options="[
                    { label: t('problems.priorities.low'), value: 'low' },
                    { label: t('problems.priorities.medium'), value: 'medium' },
                    { label: t('problems.priorities.high'), value: 'high' },
                    { label: t('problems.priorities.critical'), value: 'critical' },
                  ]"
                  emit-value map-options dense outlined
                  @update:model-value="(v: string) => onUpdateField('priority', v)"
                />
              </div>

              <!-- Impact -->
              <div class="q-mb-md">
                <div class="text-caption text-grey">{{ t('problems.fields.impact') }}</div>
                <q-select
                  :model-value="problem.impact"
                  :options="[
                    { label: t('problems.impacts.low'), value: 'low' },
                    { label: t('problems.impacts.medium'), value: 'medium' },
                    { label: t('problems.impacts.high'), value: 'high' },
                    { label: t('problems.impacts.extensive'), value: 'extensive' },
                  ]"
                  emit-value map-options dense outlined
                  @update:model-value="(v: string) => onUpdateField('impact', v)"
                />
              </div>

              <!-- Urgency -->
              <div class="q-mb-md">
                <div class="text-caption text-grey">{{ t('problems.fields.urgency') }}</div>
                <q-select
                  :model-value="problem.urgency"
                  :options="[
                    { label: t('problems.urgencies.low'), value: 'low' },
                    { label: t('problems.urgencies.medium'), value: 'medium' },
                    { label: t('problems.urgencies.high'), value: 'high' },
                    { label: t('problems.urgencies.critical'), value: 'critical' },
                  ]"
                  emit-value map-options dense outlined
                  @update:model-value="(v: string) => onUpdateField('urgency', v)"
                />
              </div>

              <!-- Category -->
              <div class="q-mb-md">
                <div class="text-caption text-grey">{{ t('common.category') }}</div>
                <q-select
                  :model-value="problem.category_id"
                  :options="categories.map(c => ({ label: c.name, value: c.id }))"
                  emit-value map-options dense outlined clearable
                  @update:model-value="(v: number | null) => onUpdateField('category_id', v)"
                />
              </div>

              <!-- Assigned To -->
              <div class="q-mb-md">
                <div class="text-caption text-grey">{{ t('tickets.assignedTo') }}</div>
                <q-select
                  :model-value="problem.assigned_to"
                  :options="agents.map(a => ({ label: a.name, value: a.id }))"
                  emit-value map-options dense outlined clearable
                  @update:model-value="(v: number | null) => onUpdateField('assigned_to', v)"
                />
              </div>

              <!-- Department -->
              <div class="q-mb-md">
                <div class="text-caption text-grey">{{ t('problems.fields.department') }}</div>
                <q-select
                  :model-value="problem.department_id"
                  :options="departments.map(d => ({ label: d.name, value: d.id }))"
                  emit-value map-options dense outlined clearable
                  @update:model-value="(v: number | null) => onUpdateField('department_id', v)"
                />
              </div>

              <q-separator class="q-my-md" />

              <!-- Dates -->
              <div class="q-mb-sm">
                <div class="text-caption text-grey">{{ t('problems.fields.detectedAt') }}</div>
                <div>{{ problem.detected_at ? new Date(problem.detected_at).toLocaleString() : '-' }}</div>
              </div>
              <div class="q-mb-sm">
                <div class="text-caption text-grey">{{ t('problems.fields.resolvedAt') }}</div>
                <div>{{ problem.resolved_at ? new Date(problem.resolved_at).toLocaleString() : '-' }}</div>
              </div>
              <div>
                <div class="text-caption text-grey">{{ t('problems.fields.closedAt') }}</div>
                <div>{{ problem.closed_at ? new Date(problem.closed_at).toLocaleString() : '-' }}</div>
              </div>

              <!-- Known Error indicator -->
              <template v-if="problem.is_known_error">
                <q-separator class="q-my-md" />
                <q-chip color="red" text-color="white" icon="warning">
                  {{ t('problems.knownError') }}: {{ problem.known_error_id }}
                </q-chip>
              </template>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </template>

    <!-- Root Cause Dialog -->
    <q-dialog v-model="showRootCauseDialog" persistent>
      <q-card style="min-width: 500px;">
        <q-card-section>
          <div class="text-h6">{{ t('problems.updateRootCause') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model="rootCauseForm.root_cause"
            :label="t('problems.fields.rootCause')"
            type="textarea" outlined autogrow
            :rules="[(v: string) => !!v || t('common.required')]"
            class="q-mb-md"
          />
          <q-input
            v-model="rootCauseForm.workaround"
            :label="t('problems.fields.workaround')"
            type="textarea" outlined autogrow
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="primary" :label="t('common.save')" :loading="saving" @click="onSaveRootCause" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Resolve Dialog -->
    <q-dialog v-model="showResolveDialog" persistent>
      <q-card style="min-width: 500px;">
        <q-card-section>
          <div class="text-h6">{{ t('problems.resolve') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model="resolutionText"
            :label="t('problems.fields.resolution')"
            type="textarea" outlined autogrow
            :rules="[(v: string) => !!v || t('common.required')]"
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="green" :label="t('problems.resolve')" :loading="saving" @click="onResolve" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Link Tickets Dialog -->
    <q-dialog v-model="showLinkDialog">
      <q-card style="min-width: 500px;">
        <q-card-section>
          <div class="text-h6">{{ t('problems.linkTicket') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model="ticketSearch"
            :placeholder="t('problems.searchTickets')"
            dense outlined
            @keyup.enter="onSearchTicketsForLink"
          >
            <template #append>
              <q-btn flat dense icon="search" :loading="ticketSearching" @click="onSearchTicketsForLink" />
            </template>
          </q-input>

          <q-list v-if="ticketSearchResults.length" bordered separator class="q-mt-sm rounded-borders">
            <q-item v-for="ticket in ticketSearchResults" :key="ticket.id" clickable @click="onLinkTicket(ticket.id)">
              <q-item-section>
                <q-item-label>{{ ticket.ticket_number }} - {{ ticket.title }}</q-item-label>
                <q-item-label caption>{{ ticket.status }} | {{ ticket.priority }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-icon name="add_circle" color="primary" />
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
