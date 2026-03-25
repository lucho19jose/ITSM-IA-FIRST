<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Notify } from 'quasar'
import {
  getAutomationRule,
  createAutomationRule,
  updateAutomationRule,
  getAvailableFields,
  testAutomationRule,
} from '@/api/automationRules'
import type {
  AvailableFieldsResponse,
  ConditionFieldMeta,
  ActionMeta,
  OperatorMeta,
  TriggerEventMeta,
  AutomationRuleTestResult,
} from '@/api/automationRules'
import type {
  AutomationRule,
  AutomationConditionGroup,
  AutomationCondition,
  AutomationAction,
  AutomationTriggerEvent,
} from '@/types'
import { getAgents } from '@/api/users'
import { getAgentGroups } from '@/api/agentGroups'
import { getSlaPolicies } from '@/api/sla'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const ruleId = computed(() => route.params.id ? Number(route.params.id) : null)
const isEditing = computed(() => !!ruleId.value)

const loading = ref(true)
const saving = ref(false)

// Form state
const form = ref({
  name: '',
  description: '',
  trigger_event: 'ticket_created' as string,
  conditions: [[]] as AutomationConditionGroup[],
  actions: [] as AutomationAction[],
  is_active: true,
  execution_order: 0,
  stop_on_match: false,
})

// Metadata
const metadata = ref<AvailableFieldsResponse | null>(null)
const conditionFields = computed(() => metadata.value?.condition_fields || [])
const actionTypes = computed(() => metadata.value?.actions || [])
const operators = computed(() => metadata.value?.operators || [])
const triggerEvents = computed(() => metadata.value?.trigger_events || [])

// Reference data for selects
const agents = ref<{ id: number; name: string }[]>([])
const agentGroups = ref<{ id: number; name: string }[]>([])
const slaPolicies = ref<{ id: number; name: string }[]>([])

// Test
const showTestDialog = ref(false)
const testTicketId = ref<number | null>(null)
const testLoading = ref(false)
const testResult = ref<AutomationRuleTestResult | null>(null)

onMounted(async () => {
  loading.value = true
  try {
    const [fieldsRes, agentsRes, groupsRes, slaRes] = await Promise.all([
      getAvailableFields(),
      getAgents(),
      getAgentGroups(),
      getSlaPolicies(),
    ])

    metadata.value = fieldsRes.data
    agents.value = (agentsRes.data || agentsRes).map((a: any) => ({ id: a.id, name: a.name }))
    agentGroups.value = (groupsRes.data || groupsRes).map((g: any) => ({ id: g.id, name: g.name }))
    slaPolicies.value = (slaRes.data || slaRes).map((s: any) => ({ id: s.id, name: s.name }))

    if (ruleId.value) {
      const ruleRes = await getAutomationRule(ruleId.value)
      const rule = ruleRes.data
      form.value = {
        name: rule.name,
        description: rule.description || '',
        trigger_event: rule.trigger_event,
        conditions: rule.conditions?.length ? rule.conditions : [[]],
        actions: rule.actions || [],
        is_active: rule.is_active,
        execution_order: rule.execution_order,
        stop_on_match: rule.stop_on_match,
      }
    }
  } finally {
    loading.value = false
  }
})

// ─── Condition helpers ──────────────────────────────────────────────────────

function addCondition(groupIndex: number) {
  form.value.conditions[groupIndex].push({
    field: '',
    operator: 'equals',
    value: '',
  })
}

function removeCondition(groupIndex: number, condIndex: number) {
  form.value.conditions[groupIndex].splice(condIndex, 1)
  // Remove empty groups (except the first)
  if (form.value.conditions[groupIndex].length === 0 && form.value.conditions.length > 1) {
    form.value.conditions.splice(groupIndex, 1)
  }
}

function addConditionGroup() {
  form.value.conditions.push([])
}

function getFieldMeta(fieldKey: string): ConditionFieldMeta | undefined {
  return conditionFields.value.find(f => f.key === fieldKey)
}

function getOperatorsForField(fieldKey: string): OperatorMeta[] {
  const fieldMeta = getFieldMeta(fieldKey)
  if (!fieldMeta) return operators.value
  return operators.value.filter(op => op.types.includes(fieldMeta.type))
}

function operatorNeedsValue(operator: string): boolean {
  return !['is_empty', 'is_not_empty', 'changed'].includes(operator)
}

// ─── Action helpers ─────────────────────────────────────────────────────────

function addAction() {
  form.value.actions.push({
    type: 'set_field',
  })
}

function removeAction(index: number) {
  form.value.actions.splice(index, 1)
}

function moveActionUp(index: number) {
  if (index === 0) return
  const list = [...form.value.actions]
  ;[list[index - 1], list[index]] = [list[index], list[index - 1]]
  form.value.actions = list
}

function moveActionDown(index: number) {
  if (index >= form.value.actions.length - 1) return
  const list = [...form.value.actions]
  ;[list[index], list[index + 1]] = [list[index + 1], list[index]]
  form.value.actions = list
}

function getActionMeta(type: string): ActionMeta | undefined {
  return actionTypes.value.find(a => a.type === type)
}

function onActionTypeChange(action: AutomationAction, oldType: string) {
  // Reset config when type changes
  const keys = Object.keys(action)
  keys.forEach(k => {
    if (k !== 'type') delete (action as any)[k]
  })
}

// ─── Set-field action: available fields for setting ─────────────────────────

const settableFields = [
  { label: 'Estado', value: 'status' },
  { label: 'Prioridad', value: 'priority' },
  { label: 'Tipo', value: 'type' },
  { label: 'Urgencia', value: 'urgency' },
  { label: 'Impacto', value: 'impact' },
  { label: 'Categoria', value: 'category_id' },
  { label: 'Departamento', value: 'department_id' },
]

const settableFieldValues: Record<string, { label: string; value: string }[]> = {
  status: [
    { label: 'Abierto', value: 'open' },
    { label: 'En Progreso', value: 'in_progress' },
    { label: 'Pendiente', value: 'pending' },
    { label: 'Resuelto', value: 'resolved' },
    { label: 'Cerrado', value: 'closed' },
  ],
  priority: [
    { label: 'Baja', value: 'low' },
    { label: 'Media', value: 'medium' },
    { label: 'Alta', value: 'high' },
    { label: 'Urgente', value: 'urgent' },
  ],
  type: [
    { label: 'Incidente', value: 'incident' },
    { label: 'Solicitud', value: 'request' },
    { label: 'Problema', value: 'problem' },
    { label: 'Cambio', value: 'change' },
  ],
  urgency: [
    { label: 'Baja', value: 'low' },
    { label: 'Media', value: 'medium' },
    { label: 'Alta', value: 'high' },
  ],
  impact: [
    { label: 'Bajo', value: 'low' },
    { label: 'Medio', value: 'medium' },
    { label: 'Alto', value: 'high' },
  ],
}

// ─── Save ───────────────────────────────────────────────────────────────────

async function handleSave() {
  if (!form.value.name.trim()) {
    Notify.create({ type: 'warning', message: t('automation.nameRequired') })
    return
  }
  if (form.value.actions.length === 0) {
    Notify.create({ type: 'warning', message: t('automation.actionsRequired') })
    return
  }

  saving.value = true
  try {
    const payload = {
      name: form.value.name,
      description: form.value.description || null,
      trigger_event: form.value.trigger_event as AutomationTriggerEvent,
      conditions: form.value.conditions.filter(g => g.length > 0),
      actions: form.value.actions,
      is_active: form.value.is_active,
      execution_order: form.value.execution_order,
      stop_on_match: form.value.stop_on_match,
    }

    if (isEditing.value) {
      await updateAutomationRule(ruleId.value!, payload)
      Notify.create({ type: 'positive', message: t('automation.updated') })
    } else {
      await createAutomationRule(payload)
      Notify.create({ type: 'positive', message: t('automation.created') })
    }
    router.push({ name: 'automation-rules' })
  } catch {
    Notify.create({ type: 'negative', message: t('automation.saveError') })
  } finally {
    saving.value = false
  }
}

// ─── Test ───────────────────────────────────────────────────────────────────

function openTest() {
  testResult.value = null
  testTicketId.value = null
  showTestDialog.value = true
}

async function runTest() {
  if (!ruleId.value || !testTicketId.value) return
  testLoading.value = true
  try {
    const res = await testAutomationRule(ruleId.value, testTicketId.value)
    testResult.value = res.data
  } catch {
    Notify.create({ type: 'negative', message: t('automation.testError') })
  } finally {
    testLoading.value = false
  }
}

const triggerEventOptions = computed(() =>
  triggerEvents.value.map(te => ({ label: te.label, value: te.value }))
)

const conditionFieldOptions = computed(() =>
  conditionFields.value.map(f => ({ label: `${f.label} (${f.key})`, value: f.key }))
)

const actionTypeOptions = computed(() =>
  actionTypes.value.map(a => ({ label: a.label, value: a.type }))
)
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-lg">
      <q-btn flat icon="arrow_back" @click="router.push({ name: 'automation-rules' })" class="q-mr-sm" />
      <h5 class="q-ma-none">
        {{ isEditing ? t('automation.editRule') : t('automation.newRule') }}
      </h5>
    </div>

    <q-spinner v-if="loading" color="primary" size="40px" class="full-width text-center q-mt-xl" />

    <template v-else>
      <!-- Name & Description -->
      <q-card class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-sm">{{ t('automation.basicInfo') }}</div>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-6">
              <q-input
                v-model="form.name"
                :label="t('automation.name')"
                outlined
                dense
                :rules="[val => !!val || t('automation.nameRequired')]"
              />
            </div>
            <div class="col-12 col-md-6">
              <q-input
                v-model="form.description"
                :label="t('automation.description')"
                outlined
                dense
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Trigger -->
      <q-card class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-sm">
            <q-icon name="bolt" class="q-mr-xs" />
            {{ t('automation.triggerSection') }}
          </div>
          <q-select
            v-model="form.trigger_event"
            :options="triggerEventOptions"
            :label="t('automation.triggerEvent')"
            outlined
            dense
            emit-value
            map-options
            style="max-width: 400px"
          />
        </q-card-section>
      </q-card>

      <!-- Conditions -->
      <q-card class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-sm">
            <q-icon name="filter_list" class="q-mr-xs" />
            {{ t('automation.conditionsSection') }}
          </div>
          <p class="text-caption text-grey">{{ t('automation.conditionsHelp') }}</p>

          <div v-for="(group, gi) in form.conditions" :key="gi">
            <div v-if="gi > 0" class="text-center q-my-sm">
              <q-badge color="orange" label="OR" class="text-body2 q-pa-xs" />
            </div>

            <q-card flat bordered class="q-mb-sm">
              <q-card-section>
                <div v-for="(cond, ci) in group" :key="ci" class="row q-col-gutter-sm items-center q-mb-sm">
                  <div v-if="ci > 0" class="col-12">
                    <q-badge color="primary" label="AND" class="text-caption" />
                  </div>
                  <div class="col-12 col-md-4">
                    <q-select
                      v-model="cond.field"
                      :options="conditionFieldOptions"
                      :label="t('automation.field')"
                      outlined
                      dense
                      emit-value
                      map-options
                    />
                  </div>
                  <div class="col-12 col-md-3">
                    <q-select
                      v-model="cond.operator"
                      :options="getOperatorsForField(cond.field).map(o => ({ label: o.label, value: o.value }))"
                      :label="t('automation.operator')"
                      outlined
                      dense
                      emit-value
                      map-options
                    />
                  </div>
                  <div class="col-12 col-md-4" v-if="operatorNeedsValue(cond.operator)">
                    <!-- Dynamic value input based on field type -->
                    <q-select
                      v-if="getFieldMeta(cond.field)?.options"
                      v-model="cond.value"
                      :options="getFieldMeta(cond.field)!.options"
                      :label="t('automation.value')"
                      outlined
                      dense
                      emit-value
                      map-options
                      :multiple="cond.operator === 'in' || cond.operator === 'not_in'"
                    />
                    <q-toggle
                      v-else-if="getFieldMeta(cond.field)?.type === 'boolean'"
                      v-model="cond.value"
                      :label="cond.value ? t('common.yes') : t('common.no')"
                    />
                    <q-input
                      v-else-if="getFieldMeta(cond.field)?.type === 'number'"
                      v-model.number="cond.value"
                      :label="t('automation.value')"
                      outlined
                      dense
                      type="number"
                    />
                    <q-input
                      v-else
                      v-model="cond.value"
                      :label="t('automation.value')"
                      outlined
                      dense
                    />
                  </div>
                  <div class="col-auto">
                    <q-btn
                      flat round dense
                      icon="close"
                      size="sm"
                      color="negative"
                      @click="removeCondition(gi, ci)"
                    />
                  </div>
                </div>

                <q-btn
                  flat dense
                  icon="add"
                  :label="t('automation.addCondition')"
                  size="sm"
                  color="primary"
                  @click="addCondition(gi)"
                />
              </q-card-section>
            </q-card>
          </div>

          <q-btn
            outline dense
            icon="add_circle_outline"
            :label="t('automation.addOrGroup')"
            size="sm"
            color="orange"
            class="q-mt-sm"
            @click="addConditionGroup"
          />
        </q-card-section>
      </q-card>

      <!-- Actions -->
      <q-card class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-sm">
            <q-icon name="play_arrow" class="q-mr-xs" />
            {{ t('automation.actionsSection') }}
          </div>

          <div v-for="(action, ai) in form.actions" :key="ai" class="q-mb-md">
            <q-card flat bordered>
              <q-card-section>
                <div class="row items-center q-mb-sm">
                  <div class="col-auto q-gutter-xs q-mr-sm">
                    <q-btn flat round dense size="xs" icon="arrow_upward" :disable="ai === 0" @click="moveActionUp(ai)" />
                    <q-btn flat round dense size="xs" icon="arrow_downward" :disable="ai >= form.actions.length - 1" @click="moveActionDown(ai)" />
                  </div>
                  <div class="col text-weight-medium text-caption">
                    {{ t('automation.actionNum', { n: ai + 1 }) }}
                  </div>
                  <q-btn flat round dense icon="close" size="sm" color="negative" @click="removeAction(ai)" />
                </div>

                <div class="row q-col-gutter-sm">
                  <div class="col-12 col-md-4">
                    <q-select
                      v-model="action.type"
                      :options="actionTypeOptions"
                      :label="t('automation.actionType')"
                      outlined
                      dense
                      emit-value
                      map-options
                    />
                  </div>

                  <!-- Dynamic config based on action type -->
                  <template v-if="action.type === 'set_field'">
                    <div class="col-12 col-md-4">
                      <q-select
                        v-model="action.field"
                        :options="settableFields"
                        :label="t('automation.field')"
                        outlined
                        dense
                        emit-value
                        map-options
                      />
                    </div>
                    <div class="col-12 col-md-4">
                      <q-select
                        v-if="action.field && settableFieldValues[action.field]"
                        v-model="action.value"
                        :options="settableFieldValues[action.field]"
                        :label="t('automation.value')"
                        outlined
                        dense
                        emit-value
                        map-options
                      />
                      <q-input
                        v-else
                        v-model="action.value"
                        :label="t('automation.value')"
                        outlined
                        dense
                      />
                    </div>
                  </template>

                  <template v-else-if="action.type === 'assign_to'">
                    <div class="col-12 col-md-8">
                      <q-select
                        v-model="action.value"
                        :options="[{ label: t('automation.autoAssign'), value: 'auto' }, ...agents.map(a => ({ label: a.name, value: a.id }))]"
                        :label="t('automation.selectAgent')"
                        outlined
                        dense
                        emit-value
                        map-options
                      />
                    </div>
                  </template>

                  <template v-else-if="action.type === 'assign_to_group'">
                    <div class="col-12 col-md-8">
                      <q-select
                        v-model="action.value"
                        :options="agentGroups.map(g => ({ label: g.name, value: g.id }))"
                        :label="t('automation.selectGroup')"
                        outlined
                        dense
                        emit-value
                        map-options
                      />
                    </div>
                  </template>

                  <template v-else-if="action.type === 'add_note'">
                    <div class="col-12 col-md-8">
                      <q-input
                        v-model="action.value"
                        :label="t('automation.noteContent')"
                        outlined
                        dense
                        type="textarea"
                        rows="3"
                      />
                    </div>
                  </template>

                  <template v-else-if="action.type === 'add_tag' || action.type === 'remove_tag'">
                    <div class="col-12 col-md-8">
                      <q-input
                        v-model="action.value"
                        :label="t('automation.tagName')"
                        outlined
                        dense
                      />
                    </div>
                  </template>

                  <template v-else-if="action.type === 'set_sla_policy'">
                    <div class="col-12 col-md-8">
                      <q-select
                        v-model="action.value"
                        :options="slaPolicies.map(s => ({ label: s.name, value: s.id }))"
                        :label="t('automation.selectSla')"
                        outlined
                        dense
                        emit-value
                        map-options
                      />
                    </div>
                  </template>

                  <template v-else-if="action.type === 'send_email'">
                    <div class="col-12 col-md-4">
                      <q-select
                        v-model="action.to"
                        :options="[
                          { label: t('automation.emailAssigned'), value: 'assigned_agent' },
                          { label: t('automation.emailRequester'), value: 'requester' },
                          { label: t('automation.emailAdmins'), value: 'admins' },
                        ]"
                        :label="t('automation.emailTo')"
                        outlined
                        dense
                        emit-value
                        map-options
                      />
                    </div>
                    <div class="col-12 col-md-4">
                      <q-select
                        v-model="action.template"
                        :options="[
                          { label: t('automation.tplEscalation'), value: 'escalation' },
                          { label: t('automation.tplNotification'), value: 'notification' },
                          { label: t('automation.tplGeneric'), value: 'generic' },
                        ]"
                        :label="t('automation.emailTemplate')"
                        outlined
                        dense
                        emit-value
                        map-options
                      />
                    </div>
                  </template>

                  <template v-else-if="action.type === 'send_webhook'">
                    <div class="col-12 col-md-8">
                      <q-input
                        v-model="action.url"
                        :label="t('automation.webhookUrl')"
                        outlined
                        dense
                        placeholder="https://"
                      />
                    </div>
                  </template>
                </div>

                <!-- Action description -->
                <div v-if="getActionMeta(action.type)" class="text-caption text-grey q-mt-xs">
                  {{ getActionMeta(action.type)!.description }}
                </div>
              </q-card-section>
            </q-card>
          </div>

          <q-btn
            outline dense
            icon="add"
            :label="t('automation.addAction')"
            color="primary"
            @click="addAction"
          />
        </q-card-section>
      </q-card>

      <!-- Options -->
      <q-card class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-sm">
            <q-icon name="tune" class="q-mr-xs" />
            {{ t('automation.options') }}
          </div>
          <div class="row q-col-gutter-md items-center">
            <div class="col-auto">
              <q-toggle
                v-model="form.is_active"
                :label="t('automation.active')"
                color="positive"
              />
            </div>
            <div class="col-auto">
              <q-toggle
                v-model="form.stop_on_match"
                :label="t('automation.stopOnMatch')"
                color="orange"
              />
              <q-tooltip>{{ t('automation.stopOnMatchHelp') }}</q-tooltip>
            </div>
            <div class="col-12 col-md-3">
              <q-input
                v-model.number="form.execution_order"
                :label="t('automation.executionOrder')"
                outlined
                dense
                type="number"
                min="0"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Action buttons -->
      <div class="row q-gutter-sm justify-end">
        <q-btn
          v-if="isEditing"
          outline
          color="info"
          icon="science"
          :label="t('automation.testRule')"
          @click="openTest"
        />
        <q-btn
          flat
          :label="t('common.cancel')"
          @click="router.push({ name: 'automation-rules' })"
        />
        <q-btn
          color="primary"
          icon="save"
          :label="t('common.save')"
          :loading="saving"
          @click="handleSave"
        />
      </div>
    </template>

    <!-- Test Dialog -->
    <q-dialog v-model="showTestDialog">
      <q-card style="width: 500px; max-width: 90vw;"
        <q-card-section>
          <div class="text-h6">{{ t('automation.testRule') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model.number="testTicketId"
            :label="t('automation.testTicketId')"
            outlined
            dense
            type="number"
            class="q-mb-md"
          />

          <template v-if="testResult">
            <q-banner :class="testResult.conditions_matched ? 'bg-positive text-white' : 'bg-grey-4'" class="q-mb-md" rounded>
              <template #avatar>
                <q-icon :name="testResult.conditions_matched ? 'check_circle' : 'cancel'" />
              </template>
              {{ testResult.conditions_matched ? t('automation.testMatched') : t('automation.testNotMatched') }}
            </q-banner>

            <!-- Condition details -->
            <div v-for="(group, gi) in testResult.condition_details" :key="gi" class="q-mb-sm">
              <div v-if="gi > 0" class="text-center q-my-xs">
                <q-badge color="orange" label="OR" />
              </div>
              <q-card flat bordered>
                <q-card-section class="q-pa-sm">
                  <div v-for="(cond, ci) in group.conditions" :key="ci" class="row items-center q-pa-xs">
                    <q-icon
                      :name="cond.matched ? 'check' : 'close'"
                      :color="cond.matched ? 'positive' : 'negative'"
                      class="q-mr-sm"
                    />
                    <span class="text-caption">
                      <strong>{{ cond.field }}</strong>
                      {{ cond.operator }}
                      <template v-if="cond.expected_value !== undefined">
                        "{{ cond.expected_value }}"
                      </template>
                      <span class="text-grey q-ml-sm">({{ t('automation.actualValue') }}: {{ cond.actual_value }})</span>
                    </span>
                  </div>
                </q-card-section>
              </q-card>
            </div>

            <!-- Actions preview -->
            <div v-if="testResult.actions_preview.length" class="q-mt-md">
              <div class="text-subtitle2 q-mb-xs">{{ t('automation.actionsWouldExecute') }}</div>
              <q-list dense separator>
                <q-item v-for="(ap, i) in testResult.actions_preview" :key="i">
                  <q-item-section avatar>
                    <q-icon name="play_arrow" color="primary" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>{{ ap.description }}</q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </div>
          </template>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn
            color="primary"
            :label="t('automation.runTest')"
            :loading="testLoading"
            :disable="!testTicketId"
            @click="runTest"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
