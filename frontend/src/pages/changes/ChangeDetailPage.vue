<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { Notify } from 'quasar'
import {
  getChangeRequest, updateChangeRequest, submitChangeRequest,
  assessRisk, requestCabReview, approveCab, rejectCab,
  scheduleChange, startImplementation, completeImplementation,
  closeReview, linkTickets,
} from '@/api/changeRequests'
import { getAgents } from '@/api/users'
import { getTickets } from '@/api/tickets'
import type { ChangeRequest, User, Ticket } from '@/types'

const props = defineProps<{ id: string }>()
const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const loading = ref(true)
const cr = ref<ChangeRequest | null>(null)
const agents = ref<User[]>([])
const activeTab = ref('details')
const actionLoading = ref(false)

// ─── Status workflow stepper ────────────────────────────────────────────

const workflowSteps = [
  { key: 'draft', icon: 'edit_note' },
  { key: 'submitted', icon: 'send' },
  { key: 'assessment', icon: 'analytics' },
  { key: 'cab_review', icon: 'groups' },
  { key: 'approved', icon: 'check_circle' },
  { key: 'scheduled', icon: 'event' },
  { key: 'implementing', icon: 'engineering' },
  { key: 'implemented', icon: 'task_alt' },
  { key: 'review', icon: 'rate_review' },
  { key: 'closed', icon: 'lock' },
]

// For rejected status, show it differently
const isRejected = computed(() => cr.value?.status === 'rejected')

const currentStepIndex = computed(() => {
  if (!cr.value) return 0
  if (isRejected.value) return workflowSteps.findIndex(s => s.key === 'cab_review')
  return workflowSteps.findIndex(s => s.key === cr.value!.status)
})

function stepColor(index: number): string {
  if (isRejected.value && index === currentStepIndex.value) return 'red'
  if (index < currentStepIndex.value) return 'green'
  if (index === currentStepIndex.value) return 'primary'
  return 'grey-4'
}

function stepTextColor(index: number): string {
  if (index <= currentStepIndex.value) return 'white'
  return 'grey-6'
}

// ─── Colors ─────────────────────────────────────────────────────────────

function typeColor(type: string): string {
  return { standard: 'blue', normal: 'orange', emergency: 'red' }[type] || 'grey'
}
function statusColor(status: string): string {
  const colors: Record<string, string> = {
    draft: 'grey', submitted: 'blue-grey', assessment: 'indigo', cab_review: 'purple',
    approved: 'green', rejected: 'red', scheduled: 'teal', implementing: 'amber',
    implemented: 'light-green', review: 'deep-orange', closed: 'blue-grey',
  }
  return colors[status] || 'grey'
}
function priorityColor(p: string): string {
  return { low: 'green', medium: 'orange', high: 'deep-orange', critical: 'red' }[p] || 'grey'
}
function riskColor(r: string): string {
  return { low: 'green', medium: 'orange', high: 'deep-orange', very_high: 'red' }[r] || 'grey'
}

// ─── Load data ──────────────────────────────────────────────────────────

async function loadChange() {
  loading.value = true
  try {
    const res = await getChangeRequest(Number(props.id))
    cr.value = res.data
  } catch {
    router.push('/changes')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  loadChange()
  try {
    const agentRes = await getAgents()
    agents.value = agentRes.data || []
  } catch { /* ignore */ }
})

// ─── Actions ────────────────────────────────────────────────────────────

async function onSubmit() {
  actionLoading.value = true
  try {
    const res = await submitChangeRequest(cr.value!.id)
    cr.value = res.data
    Notify.create({ type: 'positive', message: t('changes.submitted') })
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

const aiLoading = ref(false)
async function onAssessRisk() {
  aiLoading.value = true
  try {
    const res = await assessRisk(cr.value!.id)
    cr.value = res.data
    Notify.create({ type: 'positive', message: t('changes.riskAssessed') })
  } catch { /* handled */ }
  finally { aiLoading.value = false }
}

// CAB Review
const showCabDialog = ref(false)
const selectedApprovers = ref<number[]>([])

function openCabDialog() {
  selectedApprovers.value = []
  showCabDialog.value = true
}

async function onRequestCabReview() {
  if (!selectedApprovers.value.length) return
  actionLoading.value = true
  try {
    const res = await requestCabReview(cr.value!.id, selectedApprovers.value)
    cr.value = res.data
    showCabDialog.value = false
    Notify.create({ type: 'positive', message: t('changes.cabRequested') })
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

// Approve / Reject CAB
const showRejectDialog = ref(false)
const rejectComment = ref('')

async function onApproveCab() {
  actionLoading.value = true
  try {
    const res = await approveCab(cr.value!.id)
    cr.value = res.data
    Notify.create({ type: 'positive', message: t('changes.cabApproved') })
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

async function onRejectCab() {
  if (rejectComment.value.length < 5) return
  actionLoading.value = true
  try {
    const res = await rejectCab(cr.value!.id, rejectComment.value)
    cr.value = res.data
    showRejectDialog.value = false
    Notify.create({ type: 'positive', message: t('changes.cabRejected') })
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

// Is current user a pending CAB approver?
const isPendingApprover = computed(() => {
  if (!cr.value?.approvals || !auth.user) return false
  return cr.value.approvals.some(a => a.approver_id === auth.user!.id && a.status === 'pending')
})

// Schedule
const showScheduleDialog = ref(false)
const scheduleForm = reactive({ start: '', end: '' })

async function onSchedule() {
  if (!scheduleForm.start || !scheduleForm.end) return
  actionLoading.value = true
  try {
    const res = await scheduleChange(cr.value!.id, scheduleForm.start, scheduleForm.end)
    cr.value = res.data
    showScheduleDialog.value = false
    Notify.create({ type: 'positive', message: t('changes.scheduled') })
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

async function onStartImplementation() {
  actionLoading.value = true
  try {
    const res = await startImplementation(cr.value!.id)
    cr.value = res.data
    Notify.create({ type: 'positive', message: t('changes.implementationStarted') })
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

async function onCompleteImplementation() {
  actionLoading.value = true
  try {
    const res = await completeImplementation(cr.value!.id)
    cr.value = res.data
    Notify.create({ type: 'positive', message: t('changes.implementationCompleted') })
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

// Close Review
const showCloseDialog = ref(false)
const reviewNotes = ref('')

async function onCloseReview() {
  if (reviewNotes.value.length < 10) return
  actionLoading.value = true
  try {
    const res = await closeReview(cr.value!.id, reviewNotes.value)
    cr.value = res.data
    showCloseDialog.value = false
    Notify.create({ type: 'positive', message: t('changes.closed') })
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

// Link Tickets
const showLinkDialog = ref(false)
const ticketSearch = ref('')
const searchedTickets = ref<Ticket[]>([])
const selectedTicketIds = ref<number[]>([])
const linkRelType = ref('related')

async function searchTickets() {
  if (!ticketSearch.value) return
  try {
    const res = await getTickets({ search: ticketSearch.value, per_page: 10 })
    searchedTickets.value = res.data || []
  } catch { /* ignore */ }
}

async function onLinkTickets() {
  if (!selectedTicketIds.value.length) return
  actionLoading.value = true
  try {
    const res = await linkTickets(cr.value!.id, selectedTicketIds.value, linkRelType.value)
    cr.value = res.data
    showLinkDialog.value = false
    selectedTicketIds.value = []
    Notify.create({ type: 'positive', message: t('changes.ticketsLinked') })
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

// Editing
const editing = ref(false)
const editForm = ref<any>({})

function startEditing() {
  if (!cr.value) return
  editForm.value = {
    title: cr.value.title,
    description: cr.value.description,
    reason_for_change: cr.value.reason_for_change,
    implementation_plan: cr.value.implementation_plan || '',
    rollback_plan: cr.value.rollback_plan || '',
    test_plan: cr.value.test_plan || '',
    type: cr.value.type,
    priority: cr.value.priority,
    risk_level: cr.value.risk_level,
    impact: cr.value.impact,
    assigned_to: cr.value.assigned_to,
  }
  editing.value = true
}

async function saveEdit() {
  actionLoading.value = true
  try {
    const res = await updateChangeRequest(cr.value!.id, editForm.value)
    cr.value = res.data
    editing.value = false
    Notify.create({ type: 'positive', message: t('changes.updated') })
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}
</script>

<template>
  <q-page padding>
    <q-spinner-dots v-if="loading" size="40px" class="absolute-center" />

    <template v-if="cr && !loading">
      <!-- Header -->
      <div class="row items-center q-mb-sm">
        <q-btn flat icon="arrow_back" :label="t('common.back')" no-caps dense @click="router.push('/changes')" />
      </div>

      <div class="row items-center q-mb-md q-gutter-sm">
        <div class="text-h5 text-weight-bold">{{ cr.title }}</div>
        <q-badge :color="typeColor(cr.type)" :label="t(`changes.types.${cr.type}`)" class="text-body2" />
        <q-badge :color="statusColor(cr.status)" :label="t(`changes.statuses.${cr.status}`)" class="text-body2" />
        <q-space />
        <q-btn
          v-if="cr.status === 'draft' || cr.status === 'submitted'"
          flat icon="edit" :label="t('common.edit')" no-caps
          @click="startEditing"
        />
      </div>

      <!-- Workflow Stepper -->
      <q-card flat bordered class="q-mb-md">
        <q-card-section class="q-pa-sm">
          <div class="row items-center justify-center q-gutter-none workflow-stepper">
            <template v-for="(step, i) in workflowSteps" :key="step.key">
              <div class="column items-center workflow-step" :class="{ 'workflow-step--active': i === currentStepIndex }">
                <q-avatar
                  :size="i === currentStepIndex ? '40px' : '32px'"
                  :color="stepColor(i)"
                  :text-color="stepTextColor(i)"
                  font-size="16px"
                >
                  <q-icon :name="isRejected && i === currentStepIndex ? 'close' : step.icon" />
                </q-avatar>
                <div
                  class="text-caption q-mt-xs"
                  :class="i <= currentStepIndex ? 'text-weight-bold' : 'text-grey-5'"
                  style="font-size: 10px; max-width: 72px; text-align: center; line-height: 1.2;"
                >
                  {{ isRejected && i === currentStepIndex ? t('changes.statuses.rejected') : t(`changes.statuses.${step.key}`) }}
                </div>
              </div>
              <div
                v-if="i < workflowSteps.length - 1"
                class="workflow-connector"
                :class="i < currentStepIndex ? 'bg-green' : 'bg-grey-3'"
              />
            </template>
          </div>
        </q-card-section>
      </q-card>

      <!-- Action Buttons -->
      <div class="row q-gutter-sm q-mb-md" v-if="!editing">
        <q-btn v-if="cr.status === 'draft'" color="primary" icon="send" :label="t('changes.submitForReview')" no-caps :loading="actionLoading" @click="onSubmit" />
        <q-btn v-if="['submitted','assessment','draft'].includes(cr.status)" color="indigo" icon="psychology" :label="t('changes.assessRisk')" no-caps :loading="aiLoading" @click="onAssessRisk" />
        <q-btn v-if="['submitted','assessment'].includes(cr.status)" color="purple" icon="groups" :label="t('changes.requestCabReview')" no-caps @click="openCabDialog" />
        <q-btn v-if="cr.status === 'cab_review' && isPendingApprover" color="green" icon="thumb_up" :label="t('changes.approve')" no-caps :loading="actionLoading" @click="onApproveCab" />
        <q-btn v-if="cr.status === 'cab_review' && isPendingApprover" color="red" icon="thumb_down" :label="t('changes.reject')" no-caps @click="showRejectDialog = true" />
        <q-btn v-if="cr.status === 'approved'" color="teal" icon="event" :label="t('changes.schedule')" no-caps @click="showScheduleDialog = true" />
        <q-btn v-if="cr.status === 'scheduled'" color="amber-8" icon="play_arrow" :label="t('changes.startImplementation')" no-caps :loading="actionLoading" @click="onStartImplementation" />
        <q-btn v-if="cr.status === 'implementing'" color="light-green-8" icon="check" :label="t('changes.completeImplementation')" no-caps :loading="actionLoading" @click="onCompleteImplementation" />
        <q-btn v-if="['implemented','review'].includes(cr.status)" color="deep-orange" icon="rate_review" :label="t('changes.closeReviewAction')" no-caps @click="showCloseDialog = true" />
        <q-btn flat icon="link" :label="t('changes.linkTickets')" no-caps @click="showLinkDialog = true" />
      </div>

      <!-- Editing form -->
      <q-card v-if="editing" flat bordered class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('common.edit') }}</div>
          <q-input v-model="editForm.title" :label="t('changes.titleField')" outlined dense class="q-mb-sm" />
          <q-input v-model="editForm.description" :label="t('tickets.description')" outlined dense type="textarea" autogrow class="q-mb-sm" />
          <q-input v-model="editForm.reason_for_change" :label="t('changes.reasonForChange')" outlined dense type="textarea" autogrow class="q-mb-sm" />
          <q-input v-model="editForm.implementation_plan" :label="t('changes.implementationPlan')" outlined dense type="textarea" autogrow class="q-mb-sm" />
          <q-input v-model="editForm.rollback_plan" :label="t('changes.rollbackPlan')" outlined dense type="textarea" autogrow class="q-mb-sm" />
          <q-input v-model="editForm.test_plan" :label="t('changes.testPlan')" outlined dense type="textarea" autogrow class="q-mb-sm" />
          <div class="row q-gutter-sm q-mt-sm">
            <q-btn flat :label="t('common.cancel')" no-caps @click="editing = false" />
            <q-btn color="primary" :label="t('common.save')" no-caps :loading="actionLoading" @click="saveEdit" />
          </div>
        </q-card-section>
      </q-card>

      <!-- Tabs -->
      <q-tabs v-model="activeTab" dense align="left" class="q-mb-md text-grey" active-color="primary" indicator-color="primary">
        <q-tab name="details" :label="t('changes.tabDetails')" no-caps />
        <q-tab name="plans" :label="t('changes.tabPlans')" no-caps />
        <q-tab name="risk" :label="t('changes.tabRisk')" no-caps />
        <q-tab name="approvals" :label="t('changes.tabApprovals')" no-caps />
        <q-tab name="tickets" :label="t('changes.tabTickets')" no-caps />
        <q-tab name="review" :label="t('changes.tabReview')" no-caps />
      </q-tabs>

      <q-tab-panels v-model="activeTab" animated>
        <!-- Details Tab -->
        <q-tab-panel name="details" class="q-pa-none">
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-8">
              <q-card flat bordered>
                <q-card-section>
                  <div class="text-subtitle2 text-grey q-mb-xs">{{ t('tickets.description') }}</div>
                  <div class="text-body1" style="white-space: pre-wrap;">{{ cr.description }}</div>
                  <q-separator class="q-my-md" />
                  <div class="text-subtitle2 text-grey q-mb-xs">{{ t('changes.reasonForChange') }}</div>
                  <div class="text-body1" style="white-space: pre-wrap;">{{ cr.reason_for_change }}</div>
                </q-card-section>
              </q-card>
            </div>
            <div class="col-12 col-md-4">
              <q-card flat bordered>
                <q-card-section>
                  <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('changes.properties') }}</div>
                  <q-list dense>
                    <q-item>
                      <q-item-section>
                        <q-item-label caption>{{ t('changes.type') }}</q-item-label>
                        <q-item-label><q-badge :color="typeColor(cr.type)" :label="t(`changes.types.${cr.type}`)" /></q-item-label>
                      </q-item-section>
                    </q-item>
                    <q-item>
                      <q-item-section>
                        <q-item-label caption>{{ t('common.priority') }}</q-item-label>
                        <q-item-label><q-badge :color="priorityColor(cr.priority)" :label="t(`changes.priorities.${cr.priority}`)" /></q-item-label>
                      </q-item-section>
                    </q-item>
                    <q-item>
                      <q-item-section>
                        <q-item-label caption>{{ t('changes.riskLevel') }}</q-item-label>
                        <q-item-label><q-badge :color="riskColor(cr.risk_level)" :label="t(`changes.riskLevels.${cr.risk_level}`)" /></q-item-label>
                      </q-item-section>
                    </q-item>
                    <q-item>
                      <q-item-section>
                        <q-item-label caption>{{ t('changes.impact') }}</q-item-label>
                        <q-item-label>{{ t(`changes.impacts.${cr.impact}`) }}</q-item-label>
                      </q-item-section>
                    </q-item>
                    <q-item>
                      <q-item-section>
                        <q-item-label caption>{{ t('changes.requester') }}</q-item-label>
                        <q-item-label>{{ cr.requester?.name || '-' }}</q-item-label>
                      </q-item-section>
                    </q-item>
                    <q-item>
                      <q-item-section>
                        <q-item-label caption>{{ t('changes.assignee') }}</q-item-label>
                        <q-item-label>{{ cr.assignee?.name || t('ticketList.unassigned') }}</q-item-label>
                      </q-item-section>
                    </q-item>
                    <q-item v-if="cr.category">
                      <q-item-section>
                        <q-item-label caption>{{ t('common.category') }}</q-item-label>
                        <q-item-label>{{ cr.category.name }}</q-item-label>
                      </q-item-section>
                    </q-item>
                    <q-item v-if="cr.department">
                      <q-item-section>
                        <q-item-label caption>{{ t('changes.department') }}</q-item-label>
                        <q-item-label>{{ cr.department.name }}</q-item-label>
                      </q-item-section>
                    </q-item>
                    <q-item v-if="cr.scheduled_start">
                      <q-item-section>
                        <q-item-label caption>{{ t('changes.scheduledStart') }}</q-item-label>
                        <q-item-label>{{ new Date(cr.scheduled_start).toLocaleString() }}</q-item-label>
                      </q-item-section>
                    </q-item>
                    <q-item v-if="cr.scheduled_end">
                      <q-item-section>
                        <q-item-label caption>{{ t('changes.scheduledEnd') }}</q-item-label>
                        <q-item-label>{{ new Date(cr.scheduled_end).toLocaleString() }}</q-item-label>
                      </q-item-section>
                    </q-item>
                    <q-item>
                      <q-item-section>
                        <q-item-label caption>{{ t('tickets.createdAt') }}</q-item-label>
                        <q-item-label>{{ new Date(cr.created_at).toLocaleString() }}</q-item-label>
                      </q-item-section>
                    </q-item>
                  </q-list>
                </q-card-section>
              </q-card>
            </div>
          </div>
        </q-tab-panel>

        <!-- Plans Tab -->
        <q-tab-panel name="plans" class="q-pa-none">
          <q-card flat bordered class="q-mb-md">
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('changes.implementationPlan') }}</div>
              <div v-if="cr.implementation_plan" class="text-body1" style="white-space: pre-wrap;">{{ cr.implementation_plan }}</div>
              <div v-else class="text-grey-5 text-italic">{{ t('changes.noPlanYet') }}</div>
            </q-card-section>
          </q-card>
          <q-card flat bordered class="q-mb-md">
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('changes.rollbackPlan') }}</div>
              <div v-if="cr.rollback_plan" class="text-body1" style="white-space: pre-wrap;">{{ cr.rollback_plan }}</div>
              <div v-else class="text-grey-5 text-italic">{{ t('changes.noPlanYet') }}</div>
            </q-card-section>
          </q-card>
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('changes.testPlan') }}</div>
              <div v-if="cr.test_plan" class="text-body1" style="white-space: pre-wrap;">{{ cr.test_plan }}</div>
              <div v-else class="text-grey-5 text-italic">{{ t('changes.noPlanYet') }}</div>
            </q-card-section>
          </q-card>
        </q-tab-panel>

        <!-- Risk Assessment Tab -->
        <q-tab-panel name="risk" class="q-pa-none">
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('changes.riskAssessment') }}</div>
              <template v-if="cr.risk_assessment">
                <div class="row q-col-gutter-md q-mb-md">
                  <div class="col-6 col-md-3">
                    <q-card flat bordered class="text-center q-pa-sm">
                      <div class="text-caption text-grey">{{ t('changes.riskLevel') }}</div>
                      <q-badge :color="riskColor(cr.risk_assessment.risk_level || cr.risk_level)" :label="t(`changes.riskLevels.${cr.risk_assessment.risk_level || cr.risk_level}`)" class="text-body1 q-mt-xs" />
                    </q-card>
                  </div>
                  <div v-if="cr.risk_assessment.risk_score" class="col-6 col-md-3">
                    <q-card flat bordered class="text-center q-pa-sm">
                      <div class="text-caption text-grey">{{ t('changes.riskScore') }}</div>
                      <div class="text-h5 text-weight-bold" :class="cr.risk_assessment.risk_score > 7 ? 'text-red' : cr.risk_assessment.risk_score > 4 ? 'text-orange' : 'text-green'">
                        {{ cr.risk_assessment.risk_score }}/10
                      </div>
                    </q-card>
                  </div>
                  <div v-if="cr.risk_assessment.requires_cab !== undefined" class="col-6 col-md-3">
                    <q-card flat bordered class="text-center q-pa-sm">
                      <div class="text-caption text-grey">{{ t('changes.requiresCab') }}</div>
                      <q-icon :name="cr.risk_assessment.requires_cab ? 'check_circle' : 'cancel'" :color="cr.risk_assessment.requires_cab ? 'red' : 'green'" size="28px" class="q-mt-xs" />
                    </q-card>
                  </div>
                </div>
                <div v-if="cr.risk_assessment.summary" class="q-mb-md">
                  <div class="text-subtitle2 text-grey">{{ t('changes.summary') }}</div>
                  <div class="text-body1">{{ cr.risk_assessment.summary }}</div>
                </div>
                <div v-if="cr.risk_assessment.impact_analysis" class="q-mb-md">
                  <div class="text-subtitle2 text-grey">{{ t('changes.impactAnalysis') }}</div>
                  <div class="text-body1">{{ cr.risk_assessment.impact_analysis }}</div>
                </div>
                <div v-if="cr.risk_assessment.risk_factors?.length" class="q-mb-md">
                  <div class="text-subtitle2 text-grey">{{ t('changes.riskFactors') }}</div>
                  <q-list dense>
                    <q-item v-for="(f, i) in cr.risk_assessment.risk_factors" :key="i">
                      <q-item-section avatar><q-icon name="warning" color="orange" size="sm" /></q-item-section>
                      <q-item-section>{{ f }}</q-item-section>
                    </q-item>
                  </q-list>
                </div>
                <div v-if="cr.risk_assessment.mitigation_recommendations?.length">
                  <div class="text-subtitle2 text-grey">{{ t('changes.mitigationRecommendations') }}</div>
                  <q-list dense>
                    <q-item v-for="(r, i) in cr.risk_assessment.mitigation_recommendations" :key="i">
                      <q-item-section avatar><q-icon name="shield" color="green" size="sm" /></q-item-section>
                      <q-item-section>{{ r }}</q-item-section>
                    </q-item>
                  </q-list>
                </div>
              </template>
              <div v-else class="text-center q-pa-xl text-grey-5">
                <q-icon name="analytics" size="48px" class="q-mb-sm" />
                <div class="text-body1">{{ t('changes.noRiskAssessment') }}</div>
                <q-btn
                  v-if="['draft','submitted','assessment'].includes(cr.status)"
                  color="indigo" icon="psychology" :label="t('changes.assessRisk')"
                  no-caps class="q-mt-sm" :loading="aiLoading" @click="onAssessRisk"
                />
              </div>
            </q-card-section>
          </q-card>
        </q-tab-panel>

        <!-- Approvals Tab -->
        <q-tab-panel name="approvals" class="q-pa-none">
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('changes.approvalTimeline') }}</div>

              <div v-if="cr.cab_decision" class="q-mb-md">
                <q-banner :class="cr.status === 'approved' ? 'bg-green-1 text-green-9' : cr.status === 'rejected' ? 'bg-red-1 text-red-9' : 'bg-grey-2'" rounded>
                  <template #avatar>
                    <q-icon :name="cr.status === 'approved' ? 'check_circle' : cr.status === 'rejected' ? 'cancel' : 'info'" :color="cr.status === 'approved' ? 'green' : cr.status === 'rejected' ? 'red' : 'grey'" />
                  </template>
                  <div class="text-weight-bold">{{ t('changes.cabDecision') }}</div>
                  <div>{{ cr.cab_decision }}</div>
                  <div v-if="cr.cab_decided_at" class="text-caption">{{ new Date(cr.cab_decided_at).toLocaleString() }}</div>
                </q-banner>
              </div>

              <q-timeline v-if="cr.approvals?.length" color="primary">
                <q-timeline-entry
                  v-for="approval in cr.approvals"
                  :key="approval.id"
                  :icon="approval.status === 'approved' ? 'thumb_up' : approval.status === 'rejected' ? 'thumb_down' : 'hourglass_empty'"
                  :color="approval.status === 'approved' ? 'green' : approval.status === 'rejected' ? 'red' : 'grey'"
                >
                  <template #title>
                    {{ approval.approver?.name || t('changes.unknownApprover') }} ({{ approval.role }})
                  </template>
                  <template #subtitle>
                    <q-badge
                      :color="approval.status === 'approved' ? 'green' : approval.status === 'rejected' ? 'red' : 'grey'"
                      :label="t(`changes.approvalStatuses.${approval.status}`)"
                    />
                    <span v-if="approval.decided_at" class="q-ml-sm text-caption">{{ new Date(approval.decided_at).toLocaleString() }}</span>
                  </template>
                  <div v-if="approval.comment">{{ approval.comment }}</div>
                </q-timeline-entry>
              </q-timeline>

              <div v-else class="text-center q-pa-lg text-grey-5">
                <q-icon name="groups" size="48px" class="q-mb-sm" />
                <div class="text-body1">{{ t('changes.noApprovals') }}</div>
              </div>
            </q-card-section>
          </q-card>
        </q-tab-panel>

        <!-- Tickets Tab -->
        <q-tab-panel name="tickets" class="q-pa-none">
          <q-card flat bordered>
            <q-card-section>
              <div class="row items-center q-mb-sm">
                <div class="text-subtitle1 text-weight-bold">{{ t('changes.relatedTickets') }}</div>
                <q-space />
                <q-btn flat icon="link" :label="t('changes.linkTickets')" no-caps dense @click="showLinkDialog = true" />
              </div>

              <q-list v-if="cr.tickets?.length" separator>
                <q-item v-for="ticket in cr.tickets" :key="ticket.id" clickable @click="router.push(`/tickets/${ticket.id}`)">
                  <q-item-section avatar>
                    <q-icon name="confirmation_number" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>{{ ticket.ticket_number }} - {{ ticket.title }}</q-item-label>
                    <q-item-label caption>
                      <q-badge :color="ticket.status === 'closed' ? 'grey' : 'primary'" :label="ticket.status" class="q-mr-xs" />
                      <q-badge outline :label="ticket.relationship_type" />
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>

              <div v-else class="text-center q-pa-lg text-grey-5">
                <q-icon name="link_off" size="48px" class="q-mb-sm" />
                <div class="text-body1">{{ t('changes.noLinkedTickets') }}</div>
              </div>
            </q-card-section>
          </q-card>
        </q-tab-panel>

        <!-- Review Tab -->
        <q-tab-panel name="review" class="q-pa-none">
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('changes.postImplementationReview') }}</div>

              <template v-if="cr.review_notes">
                <div class="text-body1" style="white-space: pre-wrap;">{{ cr.review_notes }}</div>
              </template>

              <template v-else-if="cr.actual_start || cr.actual_end">
                <div class="row q-gutter-md q-mb-md">
                  <div v-if="cr.actual_start">
                    <div class="text-caption text-grey">{{ t('changes.actualStart') }}</div>
                    <div>{{ new Date(cr.actual_start).toLocaleString() }}</div>
                  </div>
                  <div v-if="cr.actual_end">
                    <div class="text-caption text-grey">{{ t('changes.actualEnd') }}</div>
                    <div>{{ new Date(cr.actual_end).toLocaleString() }}</div>
                  </div>
                </div>
                <div class="text-grey-5 text-italic">{{ t('changes.noReviewNotes') }}</div>
              </template>

              <div v-else class="text-center q-pa-lg text-grey-5">
                <q-icon name="rate_review" size="48px" class="q-mb-sm" />
                <div class="text-body1">{{ t('changes.reviewNotAvailable') }}</div>
              </div>
            </q-card-section>
          </q-card>
        </q-tab-panel>
      </q-tab-panels>
    </template>

    <!-- Dialogs -->

    <!-- CAB Review Dialog -->
    <q-dialog v-model="showCabDialog" persistent>
      <q-card style="width: 400px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">{{ t('changes.selectApprovers') }}</div>
        </q-card-section>
        <q-card-section>
          <q-select
            v-model="selectedApprovers"
            :options="agents.map(a => ({ label: a.name, value: a.id }))"
            :label="t('changes.cabMembers')"
            emit-value map-options outlined dense multiple
            use-chips
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="primary" :label="t('changes.requestCabReview')" :loading="actionLoading" @click="onRequestCabReview" :disable="!selectedApprovers.length" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Reject Dialog -->
    <q-dialog v-model="showRejectDialog" persistent>
      <q-card style="width: 400px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">{{ t('changes.rejectReason') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input v-model="rejectComment" :label="t('changes.rejectComment')" outlined dense type="textarea" autogrow :rules="[(v: string) => v.length >= 5 || t('changes.minChars')]" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="red" :label="t('changes.reject')" :loading="actionLoading" @click="onRejectCab" :disable="rejectComment.length < 5" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Schedule Dialog -->
    <q-dialog v-model="showScheduleDialog" persistent>
      <q-card style="width: 400px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">{{ t('changes.scheduleImplementation') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input v-model="scheduleForm.start" :label="t('changes.scheduledStart')" outlined dense type="datetime-local" class="q-mb-sm" />
          <q-input v-model="scheduleForm.end" :label="t('changes.scheduledEnd')" outlined dense type="datetime-local" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="primary" :label="t('changes.schedule')" :loading="actionLoading" @click="onSchedule" :disable="!scheduleForm.start || !scheduleForm.end" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Close Review Dialog -->
    <q-dialog v-model="showCloseDialog" persistent>
      <q-card style="width: 400px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">{{ t('changes.postImplementationReview') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input v-model="reviewNotes" :label="t('changes.reviewNotes')" outlined dense type="textarea" autogrow :rules="[(v: string) => v.length >= 10 || t('changes.minCharsReview')]" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="primary" :label="t('changes.closeReviewAction')" :loading="actionLoading" @click="onCloseReview" :disable="reviewNotes.length < 10" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Link Tickets Dialog -->
    <q-dialog v-model="showLinkDialog" persistent>
      <q-card style="width: 500px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">{{ t('changes.linkTickets') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input v-model="ticketSearch" :label="t('common.search')" outlined dense class="q-mb-sm" @keyup.enter="searchTickets">
            <template #append><q-btn flat icon="search" dense @click="searchTickets" /></template>
          </q-input>
          <q-select
            v-model="linkRelType"
            :options="[
              { label: t('changes.relTypes.related'), value: 'related' },
              { label: t('changes.relTypes.caused_by'), value: 'caused_by' },
              { label: t('changes.relTypes.implements'), value: 'implements' },
            ]"
            :label="t('changes.relationshipType')"
            emit-value map-options outlined dense
            class="q-mb-sm"
          />
          <q-list v-if="searchedTickets.length" separator bordered class="rounded-borders">
            <q-item v-for="ticket in searchedTickets" :key="ticket.id" tag="label">
              <q-item-section side>
                <q-checkbox v-model="selectedTicketIds" :val="ticket.id" />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ ticket.ticket_number }} - {{ ticket.title }}</q-item-label>
                <q-item-label caption>{{ ticket.status }} | {{ ticket.priority }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="primary" :label="t('changes.link')" :loading="actionLoading" @click="onLinkTickets" :disable="!selectedTicketIds.length" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<style scoped>
.workflow-stepper {
  overflow-x: auto;
  padding: 8px 0;
}
.workflow-step {
  min-width: 60px;
  transition: all 0.3s;
}
.workflow-step--active {
  transform: scale(1.05);
}
.workflow-connector {
  height: 3px;
  width: 32px;
  min-width: 16px;
  border-radius: 2px;
  margin-top: -16px;
}
</style>
