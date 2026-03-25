<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Notify } from 'quasar'
import { getApprovals, getMyPendingApprovals, approveApproval, rejectApproval, getApproval } from '@/api/approvals'
import { useAuthStore } from '@/stores/auth'
import type { Approval, ApprovalAction } from '@/types'

const { t } = useI18n()
const auth = useAuthStore()

const tab = ref('pending')
const loading = ref(true)
const pendingApprovals = ref<Approval[]>([])
const allApprovals = ref<Approval[]>([])
const showDetailDialog = ref(false)
const selectedApproval = ref<Approval | null>(null)
const detailLoading = ref(false)
const showRejectDialog = ref(false)
const rejectComment = ref('')
const approveComment = ref('')
const showApproveDialog = ref(false)
const actionLoading = ref(false)

const statusColorMap: Record<string, string> = {
  pending: 'warning',
  approved: 'positive',
  rejected: 'negative',
  canceled: 'grey',
}

const statusLabelMap: Record<string, string> = {
  pending: 'Pendiente',
  approved: 'Aprobada',
  rejected: 'Rechazada',
  canceled: 'Cancelada',
}

const actionLabelMap: Record<string, string> = {
  approved: 'Aprobado',
  rejected: 'Rechazado',
  delegated: 'Delegado',
}

const actionColorMap: Record<string, string> = {
  approved: 'positive',
  rejected: 'negative',
  delegated: 'info',
}

onMounted(async () => {
  await loadData()
})

async function loadData() {
  loading.value = true
  try {
    const [pendingRes, allRes] = await Promise.all([
      getMyPendingApprovals(),
      (auth.isAdmin || auth.isAgent) ? getApprovals() : Promise.resolve({ data: [] as Approval[], meta: null }),
    ])
    pendingApprovals.value = pendingRes.data
    allApprovals.value = allRes.data || []
  } finally {
    loading.value = false
  }
}

async function openDetail(approval: Approval) {
  detailLoading.value = true
  showDetailDialog.value = true
  try {
    const res = await getApproval(approval.id)
    selectedApproval.value = res.data
  } finally {
    detailLoading.value = false
  }
}

function openApproveDialog(approval: Approval) {
  selectedApproval.value = approval
  approveComment.value = ''
  showApproveDialog.value = true
}

function openRejectDialog(approval: Approval) {
  selectedApproval.value = approval
  rejectComment.value = ''
  showRejectDialog.value = true
}

async function doApprove() {
  if (!selectedApproval.value) return
  actionLoading.value = true
  try {
    await approveApproval(selectedApproval.value.id, approveComment.value || undefined)
    Notify.create({ type: 'positive', message: t('approvals.approvedSuccess') })
    showApproveDialog.value = false
    showDetailDialog.value = false
    await loadData()
  } finally {
    actionLoading.value = false
  }
}

async function doReject() {
  if (!selectedApproval.value || !rejectComment.value) return
  actionLoading.value = true
  try {
    await rejectApproval(selectedApproval.value.id, rejectComment.value)
    Notify.create({ type: 'positive', message: t('approvals.rejectedSuccess') })
    showRejectDialog.value = false
    showDetailDialog.value = false
    await loadData()
  } finally {
    actionLoading.value = false
  }
}

function getApprovableName(approval: Approval): string {
  if (approval.approvable && 'name' in approval.approvable) {
    return (approval.approvable as any).name
  }
  return `${approval.approvable_type.split('\\').pop()} #${approval.approvable_id}`
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('es-PE', {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function getStepIcon(stepOrder: number): string {
  if (!selectedApproval.value) return 'circle'
  const actions = getActionsForStep(stepOrder)
  if (actions.length > 0) {
    const lastAction = actions[actions.length - 1]
    if (lastAction.action === 'approved') return 'check_circle'
    if (lastAction.action === 'rejected') return 'cancel'
  }
  if (stepOrder === selectedApproval.value.current_step && selectedApproval.value.status === 'pending') return 'hourglass_empty'
  return 'circle'
}

function getStepColor(stepOrder: number): string {
  if (!selectedApproval.value) return 'grey'
  const actions = getActionsForStep(stepOrder)
  if (actions.length > 0) {
    const lastAction = actions[actions.length - 1]
    if (lastAction.action === 'approved') return 'positive'
    if (lastAction.action === 'rejected') return 'negative'
  }
  if (stepOrder === selectedApproval.value.current_step && selectedApproval.value.status === 'pending') return 'warning'
  return 'grey'
}

function getActionsForStep(stepOrder: number): ApprovalAction[] {
  if (!selectedApproval.value?.actions) return []
  return selectedApproval.value.actions.filter(a => a.step_order === stepOrder)
}

const pendingColumns = [
  { name: 'item', label: t('approvals.item'), field: 'id', align: 'left' as const },
  { name: 'requester', label: t('approvals.requester'), field: 'id', align: 'left' as const },
  { name: 'step', label: t('approvals.currentStep'), field: 'current_step', align: 'center' as const },
  { name: 'date', label: t('approvals.requestedAt'), field: 'created_at', align: 'left' as const, sortable: true },
  { name: 'actions', label: t('common.actions'), field: 'id', align: 'center' as const },
]

const allColumns = [
  { name: 'item', label: t('approvals.item'), field: 'id', align: 'left' as const },
  { name: 'requester', label: t('approvals.requester'), field: 'id', align: 'left' as const },
  { name: 'status', label: t('common.status'), field: 'status', align: 'center' as const, sortable: true },
  { name: 'step', label: t('approvals.currentStep'), field: 'current_step', align: 'center' as const },
  { name: 'date', label: t('approvals.requestedAt'), field: 'created_at', align: 'left' as const, sortable: true },
  { name: 'actions', label: t('common.actions'), field: 'id', align: 'center' as const },
]
</script>

<template>
  <q-page padding>
    <div class="text-h5 q-mb-md">{{ t('approvals.title') }}</div>

    <q-tabs v-model="tab" dense class="text-grey" active-color="primary" indicator-color="primary" align="left">
      <q-tab name="pending" :label="t('approvals.myPending')">
        <q-badge v-if="pendingApprovals.length > 0" color="red" floating>{{ pendingApprovals.length }}</q-badge>
      </q-tab>
      <q-tab v-if="auth.isAdmin || auth.isAgent" name="all" :label="t('approvals.allApprovals')" />
    </q-tabs>

    <q-separator />

    <q-tab-panels v-model="tab" animated>
      <!-- My Pending -->
      <q-tab-panel name="pending">
        <q-table
          :rows="pendingApprovals"
          :columns="pendingColumns"
          row-key="id"
          :loading="loading"
          flat bordered
          :rows-per-page-options="[10, 20, 50]"
        >
          <template v-slot:body-cell-item="props">
            <q-td :props="props">
              <span class="cursor-pointer text-primary" @click="openDetail(props.row)">
                {{ getApprovableName(props.row) }}
              </span>
            </q-td>
          </template>

          <template v-slot:body-cell-requester="props">
            <q-td :props="props">
              {{ props.row.requester?.name || '-' }}
            </q-td>
          </template>

          <template v-slot:body-cell-step="props">
            <q-td :props="props">
              {{ props.row.current_step }} / {{ props.row.workflow?.steps?.length || '?' }}
            </q-td>
          </template>

          <template v-slot:body-cell-date="props">
            <q-td :props="props">
              {{ formatDate(props.row.created_at) }}
            </q-td>
          </template>

          <template v-slot:body-cell-actions="props">
            <q-td :props="props">
              <q-btn flat dense color="positive" icon="check" @click="openApproveDialog(props.row)">
                <q-tooltip>{{ t('approvals.approve') }}</q-tooltip>
              </q-btn>
              <q-btn flat dense color="negative" icon="close" @click="openRejectDialog(props.row)">
                <q-tooltip>{{ t('approvals.reject') }}</q-tooltip>
              </q-btn>
              <q-btn flat dense color="primary" icon="visibility" @click="openDetail(props.row)">
                <q-tooltip>{{ t('approvals.viewDetail') }}</q-tooltip>
              </q-btn>
            </q-td>
          </template>

          <template v-slot:no-data>
            <div class="full-width text-center q-pa-xl text-grey-5">
              <q-icon name="task_alt" size="48px" class="q-mb-sm" />
              <div>{{ t('approvals.noPending') }}</div>
            </div>
          </template>
        </q-table>
      </q-tab-panel>

      <!-- All Approvals -->
      <q-tab-panel name="all" v-if="auth.isAdmin || auth.isAgent">
        <q-table
          :rows="allApprovals"
          :columns="allColumns"
          row-key="id"
          :loading="loading"
          flat bordered
          :rows-per-page-options="[10, 20, 50]"
        >
          <template v-slot:body-cell-item="props">
            <q-td :props="props">
              <span class="cursor-pointer text-primary" @click="openDetail(props.row)">
                {{ getApprovableName(props.row) }}
              </span>
            </q-td>
          </template>

          <template v-slot:body-cell-requester="props">
            <q-td :props="props">
              {{ props.row.requester?.name || '-' }}
            </q-td>
          </template>

          <template v-slot:body-cell-status="props">
            <q-td :props="props">
              <q-badge :color="statusColorMap[props.row.status] || 'grey'">
                {{ statusLabelMap[props.row.status] || props.row.status }}
              </q-badge>
            </q-td>
          </template>

          <template v-slot:body-cell-step="props">
            <q-td :props="props">
              {{ props.row.current_step }} / {{ props.row.workflow?.steps?.length || '?' }}
            </q-td>
          </template>

          <template v-slot:body-cell-date="props">
            <q-td :props="props">
              {{ formatDate(props.row.created_at) }}
            </q-td>
          </template>

          <template v-slot:body-cell-actions="props">
            <q-td :props="props">
              <q-btn flat dense color="primary" icon="visibility" @click="openDetail(props.row)">
                <q-tooltip>{{ t('approvals.viewDetail') }}</q-tooltip>
              </q-btn>
            </q-td>
          </template>
        </q-table>
      </q-tab-panel>
    </q-tab-panels>

    <!-- Detail Dialog -->
    <q-dialog v-model="showDetailDialog" maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card>
        <q-toolbar class="bg-primary text-white">
          <q-toolbar-title>{{ t('approvals.detail') }}</q-toolbar-title>
          <q-btn flat round icon="close" v-close-popup />
        </q-toolbar>

        <q-card-section v-if="detailLoading" class="flex flex-center q-pa-xl">
          <q-spinner-dots size="40px" color="primary" />
        </q-card-section>

        <q-card-section v-else-if="selectedApproval" class="q-pa-lg" style="max-width: 800px; margin: 0 auto;">
          <!-- Header info -->
          <div class="row q-col-gutter-md q-mb-lg">
            <div class="col-12 col-sm-6">
              <div class="text-caption text-grey">{{ t('approvals.item') }}</div>
              <div class="text-subtitle1 text-weight-medium">{{ getApprovableName(selectedApproval) }}</div>
            </div>
            <div class="col-12 col-sm-3">
              <div class="text-caption text-grey">{{ t('common.status') }}</div>
              <q-badge :color="statusColorMap[selectedApproval.status]" class="q-pa-xs">
                {{ statusLabelMap[selectedApproval.status] }}
              </q-badge>
            </div>
            <div class="col-12 col-sm-3">
              <div class="text-caption text-grey">{{ t('approvals.currentStep') }}</div>
              <div>{{ selectedApproval.current_step }} / {{ selectedApproval.workflow?.steps?.length || '?' }}</div>
            </div>
          </div>

          <div class="row q-col-gutter-md q-mb-lg">
            <div class="col-12 col-sm-6">
              <div class="text-caption text-grey">{{ t('approvals.requester') }}</div>
              <div>{{ selectedApproval.requester?.name || '-' }}</div>
            </div>
            <div class="col-12 col-sm-6">
              <div class="text-caption text-grey">{{ t('approvals.workflow') }}</div>
              <div>{{ selectedApproval.workflow?.name || '-' }}</div>
            </div>
          </div>

          <q-separator class="q-my-md" />

          <!-- Approval Timeline -->
          <div class="text-subtitle1 text-weight-medium q-mb-md">{{ t('approvals.history') }}</div>

          <q-timeline color="primary">
            <q-timeline-entry
              v-for="step in selectedApproval.workflow?.steps || []"
              :key="step.step_order"
              :title="`${t('approvalWorkflows.step')} ${step.step_order}`"
              :subtitle="step.approver_type === 'user' ? `Usuario` : step.approver_type === 'role' ? `Rol: ${step.approver_role}` : t('approvalWorkflows.approverTypeDeptHead')"
              :icon="getStepIcon(step.step_order)"
              :color="getStepColor(step.step_order)"
            >
              <template v-if="getActionsForStep(step.step_order).length > 0">
                <div v-for="action in getActionsForStep(step.step_order)" :key="action.id" class="q-mb-xs">
                  <q-badge :color="actionColorMap[action.action]">{{ actionLabelMap[action.action] }}</q-badge>
                  <span class="q-ml-sm">{{ action.approver?.name || '-' }}</span>
                  <span class="text-caption text-grey q-ml-sm">{{ formatDate(action.acted_at) }}</span>
                  <div v-if="action.comment" class="text-body2 text-grey-8 q-mt-xs" style="padding-left: 8px; border-left: 2px solid #e0e0e0;">
                    {{ action.comment }}
                  </div>
                </div>
              </template>
              <template v-else-if="step.step_order === selectedApproval.current_step && selectedApproval.status === 'pending'">
                <div class="text-grey-6">{{ t('approvals.waitingForApproval') }}</div>
              </template>
              <template v-else-if="step.step_order > (selectedApproval?.current_step || 0)">
                <div class="text-grey-5">{{ t('approvals.pendingStep') }}</div>
              </template>
            </q-timeline-entry>
          </q-timeline>

          <!-- Action buttons for pending approvals -->
          <div v-if="selectedApproval.status === 'pending'" class="row q-gutter-sm q-mt-md">
            <q-btn color="positive" icon="check" :label="t('approvals.approve')" @click="openApproveDialog(selectedApproval)" />
            <q-btn color="negative" icon="close" :label="t('approvals.reject')" @click="openRejectDialog(selectedApproval)" />
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Approve Dialog -->
    <q-dialog v-model="showApproveDialog">
      <q-card style="width: 400px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">{{ t('approvals.confirmApprove') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model="approveComment"
            :label="t('approvals.commentOptional')"
            outlined
            type="textarea"
            autogrow
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="positive" :label="t('approvals.approve')" :loading="actionLoading" @click="doApprove" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Reject Dialog -->
    <q-dialog v-model="showRejectDialog">
      <q-card style="width: 400px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">{{ t('approvals.confirmReject') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model="rejectComment"
            :label="t('approvals.commentRequired')"
            outlined
            type="textarea"
            autogrow
            :rules="[val => !!val || t('approvals.commentRequiredError')]"
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="negative" :label="t('approvals.reject')" :loading="actionLoading" :disable="!rejectComment" @click="doReject" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
