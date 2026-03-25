<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Notify, useQuasar } from 'quasar'
import {
  getApprovalWorkflows,
  createApprovalWorkflow,
  updateApprovalWorkflow,
  deleteApprovalWorkflow,
} from '@/api/approvalWorkflows'
import type { ApprovalWorkflow, ApprovalWorkflowStep, User } from '@/types'
import { get } from '@/utils/api'

const { t } = useI18n()
const $q = useQuasar()

const loading = ref(true)
const workflows = ref<ApprovalWorkflow[]>([])
const showDialog = ref(false)
const showDeleteDialog = ref(false)
const editing = ref<ApprovalWorkflow | null>(null)
const deletingWorkflow = ref<ApprovalWorkflow | null>(null)
const submitting = ref(false)
const users = ref<User[]>([])

const form = ref({
  name: '',
  description: '',
  is_active: true,
  steps: [
    { step_order: 1, approver_type: 'role' as const, approver_id: null as number | null, approver_role: 'admin', auto_approve_after_hours: null as number | null },
  ] as ApprovalWorkflowStep[],
})

const approverTypeOptions = [
  { label: t('approvalWorkflows.approverTypeUser'), value: 'user' },
  { label: t('approvalWorkflows.approverTypeRole'), value: 'role' },
  { label: t('approvalWorkflows.approverTypeDeptHead'), value: 'department_head' },
]

const roleOptions = [
  { label: 'Admin', value: 'admin' },
  { label: 'Agente', value: 'agent' },
]

const columns = [
  { name: 'name', label: t('approvalWorkflows.name'), field: 'name', align: 'left' as const, sortable: true },
  { name: 'steps', label: t('approvalWorkflows.steps'), field: 'id', align: 'center' as const },
  { name: 'is_active', label: t('approvalWorkflows.active'), field: 'is_active', align: 'center' as const },
  { name: 'actions', label: t('common.actions'), field: 'id', align: 'center' as const },
]

onMounted(async () => {
  await Promise.all([loadWorkflows(), loadUsers()])
})

async function loadWorkflows() {
  loading.value = true
  try {
    const res = await getApprovalWorkflows()
    workflows.value = res.data
  } finally {
    loading.value = false
  }
}

async function loadUsers() {
  try {
    const res = await get<{ data: User[] }>('users/agents/list')
    users.value = res.data || []
  } catch { /* ignore */ }
}

function openCreate() {
  editing.value = null
  form.value = {
    name: '',
    description: '',
    is_active: true,
    steps: [
      { step_order: 1, approver_type: 'role', approver_id: null, approver_role: 'admin', auto_approve_after_hours: null },
    ],
  }
  showDialog.value = true
}

function openEdit(workflow: ApprovalWorkflow) {
  editing.value = workflow
  form.value = {
    name: workflow.name,
    description: workflow.description || '',
    is_active: workflow.is_active,
    steps: workflow.steps.map(s => ({
      step_order: s.step_order,
      approver_type: s.approver_type,
      approver_id: s.approver_id,
      approver_role: s.approver_role,
      auto_approve_after_hours: s.auto_approve_after_hours,
    })),
  }
  showDialog.value = true
}

function addStep() {
  const nextOrder = form.value.steps.length + 1
  form.value.steps.push({
    step_order: nextOrder,
    approver_type: 'role',
    approver_id: null,
    approver_role: 'admin',
    auto_approve_after_hours: null,
  })
}

function removeStep(index: number) {
  form.value.steps.splice(index, 1)
  // Re-order
  form.value.steps.forEach((s, i) => { s.step_order = i + 1 })
}

async function saveWorkflow() {
  submitting.value = true
  try {
    const payload = {
      name: form.value.name,
      description: form.value.description || null,
      is_active: form.value.is_active,
      steps: form.value.steps,
    }

    if (editing.value) {
      await updateApprovalWorkflow(editing.value.id, payload)
      Notify.create({ type: 'positive', message: t('approvalWorkflows.updated') })
    } else {
      await createApprovalWorkflow(payload)
      Notify.create({ type: 'positive', message: t('approvalWorkflows.created') })
    }
    showDialog.value = false
    await loadWorkflows()
  } finally {
    submitting.value = false
  }
}

function confirmDelete(workflow: ApprovalWorkflow) {
  deletingWorkflow.value = workflow
  showDeleteDialog.value = true
}

async function doDelete() {
  if (!deletingWorkflow.value) return
  try {
    await deleteApprovalWorkflow(deletingWorkflow.value.id)
    Notify.create({ type: 'positive', message: t('approvalWorkflows.deleted') })
    showDeleteDialog.value = false
    await loadWorkflows()
  } catch { /* error handled by interceptor */ }
}

function getUserName(id: number | null): string {
  if (!id) return '-'
  const u = users.value.find(u => u.id === id)
  return u ? u.name : `ID ${id}`
}
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="text-h5">{{ t('approvalWorkflows.title') }}</div>
      <q-space />
      <q-btn color="primary" icon="add" :label="t('common.create')" @click="openCreate" />
    </div>

    <q-table
      :rows="workflows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat bordered
    >
      <template v-slot:body-cell-steps="props">
        <q-td :props="props">
          <q-badge color="primary">{{ props.row.steps?.length || 0 }} {{ t('approvalWorkflows.stepsCount') }}</q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-is_active="props">
        <q-td :props="props">
          <q-badge :color="props.row.is_active ? 'positive' : 'grey'">
            {{ props.row.is_active ? t('common.yes') : t('common.no') }}
          </q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat round dense icon="edit" color="primary" @click="openEdit(props.row)" />
          <q-btn flat round dense icon="delete" color="negative" @click="confirmDelete(props.row)" />
        </q-td>
      </template>
    </q-table>

    <!-- Create / Edit Dialog -->
    <q-dialog v-model="showDialog" persistent maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card>
        <q-toolbar class="bg-primary text-white">
          <q-toolbar-title>
            {{ editing ? t('approvalWorkflows.edit') : t('approvalWorkflows.create') }}
          </q-toolbar-title>
          <q-btn flat round icon="close" v-close-popup />
        </q-toolbar>

        <q-card-section class="q-pa-lg" style="max-width: 800px; margin: 0 auto;">
          <q-input
            v-model="form.name"
            :label="t('approvalWorkflows.name')"
            outlined
            class="q-mb-md"
            :rules="[val => !!val || t('approvalWorkflows.nameRequired')]"
          />

          <q-input
            v-model="form.description"
            :label="t('approvalWorkflows.description')"
            outlined
            type="textarea"
            autogrow
            class="q-mb-md"
          />

          <q-toggle
            v-model="form.is_active"
            :label="t('approvalWorkflows.active')"
            class="q-mb-md"
          />

          <div class="text-subtitle1 text-weight-medium q-mb-sm">{{ t('approvalWorkflows.stepsTitle') }}</div>

          <div
            v-for="(step, index) in form.steps"
            :key="index"
            class="row q-col-gutter-sm items-end q-mb-sm"
            style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 12px;"
          >
            <div class="col-12 text-subtitle2 text-grey-7">
              {{ t('approvalWorkflows.step') }} {{ step.step_order }}
              <q-btn
                v-if="form.steps.length > 1"
                flat round dense icon="close" size="sm" color="negative"
                class="float-right"
                @click="removeStep(index)"
              />
            </div>

            <div class="col-12 col-sm-4">
              <q-select
                v-model="step.approver_type"
                :options="approverTypeOptions"
                :label="t('approvalWorkflows.approverType')"
                outlined dense
                emit-value map-options
              />
            </div>

            <div class="col-12 col-sm-4" v-if="step.approver_type === 'user'">
              <q-select
                v-model="step.approver_id"
                :options="users"
                :label="t('approvalWorkflows.approver')"
                outlined dense
                option-value="id"
                option-label="name"
                emit-value map-options
              />
            </div>

            <div class="col-12 col-sm-4" v-if="step.approver_type === 'role'">
              <q-select
                v-model="step.approver_role"
                :options="roleOptions"
                :label="t('approvalWorkflows.role')"
                outlined dense
                emit-value map-options
              />
            </div>

            <div class="col-12 col-sm-4" v-if="step.approver_type === 'department_head'">
              <div class="text-caption text-grey q-mt-sm">{{ t('approvalWorkflows.deptHeadHint') }}</div>
            </div>

            <div class="col-12 col-sm-4">
              <q-input
                v-model.number="step.auto_approve_after_hours"
                :label="t('approvalWorkflows.autoApproveHours')"
                outlined dense
                type="number"
                :hint="t('approvalWorkflows.autoApproveHint')"
              />
            </div>
          </div>

          <q-btn flat color="primary" icon="add" :label="t('approvalWorkflows.addStep')" @click="addStep" class="q-mt-sm" />
        </q-card-section>

        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn
            color="primary"
            :label="t('common.save')"
            :loading="submitting"
            @click="saveWorkflow"
            :disable="!form.name || form.steps.length === 0"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Delete confirmation -->
    <q-dialog v-model="showDeleteDialog">
      <q-card style="width: 350px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">{{ t('approvalWorkflows.confirmDelete') }}</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          {{ t('approvalWorkflows.confirmDeleteMsg', { name: deletingWorkflow?.name }) }}
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn flat color="negative" :label="t('common.delete')" @click="doDelete" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
