<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Notify, useQuasar } from 'quasar'
import {
  getAutomationRules,
  deleteAutomationRule,
  toggleAutomationRule,
  reorderAutomationRules,
  getAutomationTemplates,
  createAutomationRule,
  getAutomationRuleLogs,
} from '@/api/automationRules'
import type { AutomationRule, AutomationLog } from '@/types'
import type { AutomationTemplate } from '@/api/automationRules'

const { t } = useI18n()
const $q = useQuasar()
const router = useRouter()

const loading = ref(true)
const rules = ref<AutomationRule[]>([])
const templates = ref<AutomationTemplate[]>([])
const showDeleteDialog = ref(false)
const deletingRule = ref<AutomationRule | null>(null)
const showLogsDialog = ref(false)
const logsRule = ref<AutomationRule | null>(null)
const logs = ref<AutomationLog[]>([])
const logsLoading = ref(false)
const showTemplatesDialog = ref(false)
const templatesLoading = ref(false)

const triggerEventLabels: Record<string, string> = {
  ticket_created: t('automation.triggers.ticket_created'),
  ticket_updated: t('automation.triggers.ticket_updated'),
  ticket_assigned: t('automation.triggers.ticket_assigned'),
  ticket_closed: t('automation.triggers.ticket_closed'),
  ticket_reopened: t('automation.triggers.ticket_reopened'),
  sla_approaching: t('automation.triggers.sla_approaching'),
  sla_breached: t('automation.triggers.sla_breached'),
  comment_added: t('automation.triggers.comment_added'),
  time_based: t('automation.triggers.time_based'),
}

const triggerEventColors: Record<string, string> = {
  ticket_created: 'positive',
  ticket_updated: 'primary',
  ticket_assigned: 'info',
  ticket_closed: 'grey',
  ticket_reopened: 'warning',
  sla_approaching: 'orange',
  sla_breached: 'negative',
  comment_added: 'teal',
  time_based: 'purple',
}

onMounted(async () => {
  await loadRules()
})

async function loadRules() {
  loading.value = true
  try {
    const res = await getAutomationRules()
    rules.value = res.data
  } finally {
    loading.value = false
  }
}

function openCreate() {
  router.push({ name: 'automation-rule-builder-new' })
}

function openEdit(rule: AutomationRule) {
  router.push({ name: 'automation-rule-builder', params: { id: rule.id } })
}

function confirmDelete(rule: AutomationRule) {
  deletingRule.value = rule
  showDeleteDialog.value = true
}

async function handleDelete() {
  if (!deletingRule.value) return
  try {
    await deleteAutomationRule(deletingRule.value.id)
    Notify.create({ type: 'positive', message: t('automation.deleted') })
    await loadRules()
  } catch {
    Notify.create({ type: 'negative', message: t('automation.deleteError') })
  }
  showDeleteDialog.value = false
  deletingRule.value = null
}

async function handleToggle(rule: AutomationRule) {
  try {
    const res = await toggleAutomationRule(rule.id)
    const idx = rules.value.findIndex(r => r.id === rule.id)
    if (idx >= 0) rules.value[idx] = res.data
    Notify.create({ type: 'positive', message: res.message })
  } catch {
    Notify.create({ type: 'negative', message: t('automation.toggleError') })
  }
}

async function moveUp(index: number) {
  if (index === 0) return
  const list = [...rules.value]
  ;[list[index - 1], list[index]] = [list[index], list[index - 1]]
  rules.value = list
  await saveOrder()
}

async function moveDown(index: number) {
  if (index >= rules.value.length - 1) return
  const list = [...rules.value]
  ;[list[index], list[index + 1]] = [list[index + 1], list[index]]
  rules.value = list
  await saveOrder()
}

async function saveOrder() {
  const reorderData = rules.value.map((r, i) => ({ id: r.id, execution_order: i }))
  try {
    await reorderAutomationRules(reorderData)
  } catch {
    Notify.create({ type: 'negative', message: t('automation.reorderError') })
  }
}

async function openLogs(rule: AutomationRule) {
  logsRule.value = rule
  showLogsDialog.value = true
  logsLoading.value = true
  try {
    const res = await getAutomationRuleLogs(rule.id)
    logs.value = res.data
  } finally {
    logsLoading.value = false
  }
}

async function openTemplates() {
  showTemplatesDialog.value = true
  templatesLoading.value = true
  try {
    const res = await getAutomationTemplates()
    templates.value = res.data
  } finally {
    templatesLoading.value = false
  }
}

async function useTemplate(template: AutomationTemplate) {
  try {
    await createAutomationRule({
      name: template.name,
      description: template.description,
      trigger_event: template.trigger_event as any,
      conditions: template.conditions,
      actions: template.actions,
      stop_on_match: template.stop_on_match,
      is_active: false,
    })
    Notify.create({ type: 'positive', message: t('automation.templateCreated') })
    showTemplatesDialog.value = false
    await loadRules()
  } catch {
    Notify.create({ type: 'negative', message: t('automation.templateError') })
  }
}

function formatDate(date: string | null): string {
  if (!date) return '-'
  return new Date(date).toLocaleString()
}

function conditionsCount(rule: AutomationRule): number {
  return (rule.conditions || []).reduce((acc, group) => acc + (group?.length || 0), 0)
}
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-lg">
      <div class="col">
        <h5 class="q-ma-none">{{ t('automation.pageTitle') }}</h5>
        <p class="text-grey q-mb-none">{{ t('automation.pageDescription') }}</p>
      </div>
      <div class="col-auto q-gutter-sm">
        <q-btn
          outline
          color="secondary"
          icon="auto_fix_high"
          :label="t('automation.templates')"
          @click="openTemplates"
        />
        <q-btn
          color="primary"
          icon="add"
          :label="t('automation.newRule')"
          @click="openCreate"
        />
      </div>
    </div>

    <q-card>
      <q-table
        :rows="rules"
        :columns="[
          { name: 'order', label: '#', field: 'execution_order', align: 'center' as const, style: 'width: 60px' },
          { name: 'name', label: t('automation.name'), field: 'name', align: 'left' as const },
          { name: 'trigger', label: t('automation.trigger'), field: 'trigger_event', align: 'center' as const },
          { name: 'conditions', label: t('automation.conditions'), field: 'id', align: 'center' as const },
          { name: 'actions', label: t('automation.actionsCount'), field: 'id', align: 'center' as const },
          { name: 'is_active', label: t('automation.active'), field: 'is_active', align: 'center' as const },
          { name: 'trigger_count', label: t('automation.triggerCount'), field: 'trigger_count', align: 'center' as const },
          { name: 'last_triggered', label: t('automation.lastTriggered'), field: 'last_triggered_at', align: 'center' as const },
          { name: 'controls', label: t('common.actions'), field: 'id', align: 'center' as const },
        ]"
        :loading="loading"
        row-key="id"
        flat
        :rows-per-page-options="[0]"
        hide-pagination
      >
        <template #body-cell-order="props">
          <q-td :props="props" class="text-center">
            <div class="q-gutter-xs">
              <q-btn
                flat round dense size="xs"
                icon="arrow_upward"
                :disable="props.rowIndex === 0"
                @click="moveUp(props.rowIndex)"
              />
              <q-btn
                flat round dense size="xs"
                icon="arrow_downward"
                :disable="props.rowIndex >= rules.length - 1"
                @click="moveDown(props.rowIndex)"
              />
            </div>
          </q-td>
        </template>

        <template #body-cell-name="props">
          <q-td :props="props">
            <div class="text-weight-medium">{{ props.row.name }}</div>
            <div v-if="props.row.description" class="text-caption text-grey ellipsis" style="max-width: 300px">
              {{ props.row.description }}
            </div>
            <q-badge v-if="props.row.stop_on_match" color="orange" class="q-ml-xs" dense>
              {{ t('automation.stopOnMatch') }}
            </q-badge>
          </q-td>
        </template>

        <template #body-cell-trigger="props">
          <q-td :props="props" class="text-center">
            <q-badge
              :color="triggerEventColors[props.row.trigger_event] || 'grey'"
              :label="triggerEventLabels[props.row.trigger_event] || props.row.trigger_event"
            />
          </q-td>
        </template>

        <template #body-cell-conditions="props">
          <q-td :props="props" class="text-center">
            <q-badge color="blue-grey" :label="`${conditionsCount(props.row)} ${t('automation.conditionsLabel')}`" />
          </q-td>
        </template>

        <template #body-cell-actions="props">
          <q-td :props="props" class="text-center">
            <q-badge color="teal" :label="`${(props.row.actions || []).length} ${t('automation.actionsLabel')}`" />
          </q-td>
        </template>

        <template #body-cell-is_active="props">
          <q-td :props="props" class="text-center">
            <q-toggle
              :model-value="props.row.is_active"
              @update:model-value="handleToggle(props.row)"
              color="positive"
            />
          </q-td>
        </template>

        <template #body-cell-trigger_count="props">
          <q-td :props="props" class="text-center">
            {{ props.row.trigger_count }}
          </q-td>
        </template>

        <template #body-cell-last_triggered="props">
          <q-td :props="props" class="text-center text-caption">
            {{ formatDate(props.row.last_triggered_at) }}
          </q-td>
        </template>

        <template #body-cell-controls="props">
          <q-td :props="props" class="text-center">
            <q-btn flat round dense icon="history" size="sm" @click="openLogs(props.row)">
              <q-tooltip>{{ t('automation.viewLogs') }}</q-tooltip>
            </q-btn>
            <q-btn flat round dense icon="edit" size="sm" @click="openEdit(props.row)">
              <q-tooltip>{{ t('common.edit') }}</q-tooltip>
            </q-btn>
            <q-btn flat round dense icon="delete" size="sm" color="negative" @click="confirmDelete(props.row)">
              <q-tooltip>{{ t('common.delete') }}</q-tooltip>
            </q-btn>
          </q-td>
        </template>

        <template #no-data>
          <div class="full-width column items-center q-pa-xl text-grey">
            <q-icon name="smart_toy" size="64px" class="q-mb-md" />
            <p>{{ t('automation.noRules') }}</p>
            <q-btn color="primary" :label="t('automation.newRule')" @click="openCreate" />
          </div>
        </template>
      </q-table>
    </q-card>

    <!-- Delete Confirmation -->
    <q-dialog v-model="showDeleteDialog">
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">{{ t('automation.confirmDelete') }}</div>
        </q-card-section>
        <q-card-section>
          <p>{{ t('automation.deleteWarning') }}</p>
          <p class="text-weight-medium">{{ deletingRule?.name }}</p>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="negative" :label="t('common.delete')" @click="handleDelete" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Logs Dialog -->
    <q-dialog v-model="showLogsDialog" maximized>
      <q-card>
        <q-card-section class="row items-center">
          <div class="text-h6">{{ t('automation.logsTitle') }}: {{ logsRule?.name }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section>
          <q-table
            :rows="logs"
            :columns="[
              { name: 'executed_at', label: t('automation.executedAt'), field: 'executed_at', align: 'left' as const, sortable: true },
              { name: 'ticket', label: 'Ticket', field: 'ticket', align: 'left' as const },
              { name: 'matched', label: t('automation.matched'), field: 'conditions_matched', align: 'center' as const },
              { name: 'actions_executed', label: t('automation.actionsExecuted'), field: 'actions_executed', align: 'left' as const },
              { name: 'error', label: t('automation.error'), field: 'error', align: 'left' as const },
            ]"
            :loading="logsLoading"
            row-key="id"
            flat
            :rows-per-page-options="[20, 50, 100]"
          >
            <template #body-cell-executed_at="props">
              <q-td :props="props">
                {{ formatDate(props.row.executed_at) }}
              </q-td>
            </template>
            <template #body-cell-ticket="props">
              <q-td :props="props">
                <span v-if="props.row.ticket">
                  {{ props.row.ticket.ticket_number }} - {{ props.row.ticket.title }}
                </span>
                <span v-else class="text-grey">-</span>
              </q-td>
            </template>
            <template #body-cell-matched="props">
              <q-td :props="props" class="text-center">
                <q-icon
                  :name="props.row.conditions_matched ? 'check_circle' : 'cancel'"
                  :color="props.row.conditions_matched ? 'positive' : 'grey'"
                  size="sm"
                />
              </q-td>
            </template>
            <template #body-cell-actions_executed="props">
              <q-td :props="props">
                <template v-if="props.row.actions_executed">
                  <q-badge
                    v-for="(action, i) in props.row.actions_executed"
                    :key="i"
                    :color="action.success ? 'positive' : 'negative'"
                    :label="action.type"
                    class="q-mr-xs"
                  />
                </template>
                <span v-else class="text-grey">-</span>
              </q-td>
            </template>
            <template #body-cell-error="props">
              <q-td :props="props">
                <span v-if="props.row.error" class="text-negative">{{ props.row.error }}</span>
                <span v-else class="text-grey">-</span>
              </q-td>
            </template>
          </q-table>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Templates Dialog -->
    <q-dialog v-model="showTemplatesDialog">
      <q-card style="min-width: 600px; max-width: 800px">
        <q-card-section class="row items-center">
          <div class="text-h6">{{ t('automation.templatesTitle') }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section>
          <q-spinner v-if="templatesLoading" color="primary" size="40px" class="full-width text-center" />
          <q-list v-else separator>
            <q-item v-for="(tpl, i) in templates" :key="i" class="q-pa-md">
              <q-item-section>
                <q-item-label class="text-weight-medium">{{ tpl.name }}</q-item-label>
                <q-item-label caption>{{ tpl.description }}</q-item-label>
                <div class="q-mt-sm q-gutter-xs">
                  <q-badge :color="triggerEventColors[tpl.trigger_event] || 'grey'" :label="triggerEventLabels[tpl.trigger_event] || tpl.trigger_event" />
                  <q-badge color="blue-grey" :label="`${tpl.conditions.reduce((a: number, g: any[]) => a + g.length, 0)} condiciones`" />
                  <q-badge color="teal" :label="`${tpl.actions.length} acciones`" />
                </div>
              </q-item-section>
              <q-item-section side>
                <q-btn
                  color="primary"
                  :label="t('automation.useTemplate')"
                  dense
                  @click="useTemplate(tpl)"
                />
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>
