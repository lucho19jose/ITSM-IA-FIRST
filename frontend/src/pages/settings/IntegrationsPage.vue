<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Notify, useQuasar } from 'quasar'
import {
  getIntegrations,
  createIntegration,
  updateIntegration,
  deleteIntegration,
  testIntegration,
} from '@/api/integrations'
import type { Integration, IntegrationProvider, IntegrationEvent } from '@/types'

const { t } = useI18n()
const $q = useQuasar()

const loading = ref(true)
const integrations = ref<Integration[]>([])
const showDialog = ref(false)
const editing = ref<Integration | null>(null)
const saving = ref(false)
const testing = ref<number | null>(null)

const providerOptions = [
  { label: 'Slack', value: 'slack' as IntegrationProvider, icon: 'tag', color: 'purple' },
  { label: 'Microsoft Teams', value: 'teams' as IntegrationProvider, icon: 'groups', color: 'blue' },
  { label: 'Webhook generico', value: 'generic_webhook' as IntegrationProvider, icon: 'webhook', color: 'grey-8' },
]

const eventOptions: { label: string; value: IntegrationEvent }[] = [
  { label: t('integrations.events.ticket_created'), value: 'ticket_created' },
  { label: t('integrations.events.ticket_assigned'), value: 'ticket_assigned' },
  { label: t('integrations.events.ticket_closed'), value: 'ticket_closed' },
  { label: t('integrations.events.sla_breach'), value: 'sla_breach' },
  { label: t('integrations.events.ticket_commented'), value: 'ticket_commented' },
]

const defaultForm = () => ({
  provider: 'slack' as IntegrationProvider,
  name: '',
  config: {
    incoming_webhook_url: '',
    channel: '',
    bot_token: '',
    signing_secret: '',
    security_token: '',
  },
  events: ['ticket_created', 'ticket_assigned', 'ticket_closed', 'sla_breach'] as IntegrationEvent[],
  is_active: true,
})

const form = ref(defaultForm())

function getProviderMeta(provider: IntegrationProvider) {
  return providerOptions.find(p => p.value === provider) || providerOptions[2]
}

function getEventLabel(event: IntegrationEvent): string {
  const opt = eventOptions.find(e => e.value === event)
  return opt?.label ?? event
}

const showSlackFields = computed(() => form.value.provider === 'slack')
const showTeamsFields = computed(() => form.value.provider === 'teams')

onMounted(async () => {
  await loadIntegrations()
})

async function loadIntegrations() {
  loading.value = true
  try {
    const res = await getIntegrations()
    integrations.value = res.data
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = defaultForm()
  showDialog.value = true
}

function openEdit(integration: Integration) {
  editing.value = integration
  form.value = {
    provider: integration.provider,
    name: integration.name,
    config: {
      incoming_webhook_url: integration.config.incoming_webhook_url || '',
      channel: integration.config.channel || '',
      bot_token: integration.config.bot_token || '',
      signing_secret: integration.config.signing_secret || '',
      security_token: integration.config.security_token || '',
    },
    events: [...integration.events],
    is_active: integration.is_active,
  }
  showDialog.value = true
}

async function onSave() {
  saving.value = true
  try {
    const payload = {
      provider: form.value.provider,
      name: form.value.name,
      config: {
        incoming_webhook_url: form.value.config.incoming_webhook_url,
        ...(form.value.provider === 'slack' ? {
          channel: form.value.config.channel || undefined,
          bot_token: form.value.config.bot_token || undefined,
          signing_secret: form.value.config.signing_secret || undefined,
        } : {}),
        ...(form.value.provider === 'teams' ? {
          security_token: form.value.config.security_token || undefined,
        } : {}),
      },
      events: form.value.events,
      is_active: form.value.is_active,
    }

    if (editing.value) {
      const res = await updateIntegration(editing.value.id, payload)
      const idx = integrations.value.findIndex(i => i.id === editing.value!.id)
      if (idx !== -1) integrations.value[idx] = res.data
      Notify.create({ type: 'positive', message: t('integrations.updated') })
    } else {
      const res = await createIntegration(payload)
      integrations.value.unshift(res.data)
      Notify.create({ type: 'positive', message: t('integrations.created') })
    }
    showDialog.value = false
  } finally {
    saving.value = false
  }
}

async function onDelete(integration: Integration) {
  $q.dialog({
    title: t('integrations.confirmDelete'),
    message: t('integrations.confirmDeleteMsg', { name: integration.name }),
    cancel: t('common.cancel'),
    ok: { label: t('common.delete'), color: 'negative' },
    persistent: true,
  }).onOk(async () => {
    await deleteIntegration(integration.id)
    integrations.value = integrations.value.filter(i => i.id !== integration.id)
    Notify.create({ type: 'positive', message: t('integrations.deleted') })
  })
}

async function onTest(integration: Integration) {
  testing.value = integration.id
  try {
    const res = await testIntegration(integration.id)
    Notify.create({
      type: res.success ? 'positive' : 'negative',
      message: res.message,
    })
  } catch {
    Notify.create({ type: 'negative', message: t('integrations.testFailed') })
  } finally {
    testing.value = null
  }
}

async function onToggleActive(integration: Integration) {
  try {
    const res = await updateIntegration(integration.id, {
      is_active: !integration.is_active,
    })
    const idx = integrations.value.findIndex(i => i.id === integration.id)
    if (idx !== -1) integrations.value[idx] = res.data
  } catch {
    // reverted by reload
    await loadIntegrations()
  }
}
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="text-h5">{{ t('integrations.title') }}</div>
      <q-space />
      <q-btn color="primary" icon="add" :label="t('integrations.add')" no-caps @click="openCreate" />
    </div>

    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <!-- Empty state -->
    <div v-else-if="integrations.length === 0" class="text-center q-pa-xl">
      <q-icon name="hub" size="64px" color="grey-4" />
      <div class="text-h6 text-grey-6 q-mt-md">{{ t('integrations.noIntegrations') }}</div>
      <div class="text-body2 text-grey q-mt-sm q-mb-lg">{{ t('integrations.noIntegrationsDesc') }}</div>
      <q-btn color="primary" icon="add" :label="t('integrations.add')" no-caps @click="openCreate" />
    </div>

    <!-- Integration cards -->
    <div v-else class="row q-col-gutter-md">
      <div v-for="integ in integrations" :key="integ.id" class="col-12 col-sm-6 col-md-4">
        <q-card flat bordered>
          <q-card-section>
            <div class="row items-center no-wrap q-mb-sm">
              <q-avatar :color="getProviderMeta(integ.provider).color" text-color="white" size="40px">
                <q-icon :name="getProviderMeta(integ.provider).icon" size="22px" />
              </q-avatar>
              <div class="col q-ml-sm">
                <div class="text-subtitle1 text-weight-bold ellipsis">{{ integ.name }}</div>
                <div class="text-caption text-grey">{{ getProviderMeta(integ.provider).label }}</div>
              </div>
              <q-toggle
                :model-value="integ.is_active"
                color="positive"
                @update:model-value="onToggleActive(integ)"
              />
            </div>

            <div class="q-mt-sm">
              <q-badge
                v-for="event in integ.events"
                :key="event"
                outline
                color="primary"
                class="q-mr-xs q-mb-xs"
                :label="getEventLabel(event)"
              />
            </div>
          </q-card-section>

          <q-separator />

          <q-card-actions>
            <q-btn flat dense icon="send" :label="t('integrations.test')" color="primary" no-caps :loading="testing === integ.id" @click="onTest(integ)" />
            <q-space />
            <q-btn flat dense icon="edit" color="grey-7" @click="openEdit(integ)">
              <q-tooltip>{{ t('common.edit') }}</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="delete" color="negative" @click="onDelete(integ)">
              <q-tooltip>{{ t('common.delete') }}</q-tooltip>
            </q-btn>
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 540px; max-width: 640px;">
        <q-card-section>
          <div class="text-h6">{{ editing ? t('integrations.edit') : t('integrations.add') }}</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-form @submit.prevent="onSave" class="q-gutter-md">
            <!-- Provider -->
            <q-select
              v-model="form.provider"
              :options="providerOptions"
              option-value="value"
              option-label="label"
              emit-value
              map-options
              outlined
              :label="t('integrations.provider')"
              :disable="!!editing"
            >
              <template v-slot:option="scope">
                <q-item v-bind="scope.itemProps">
                  <q-item-section avatar>
                    <q-avatar :color="scope.opt.color" text-color="white" size="32px">
                      <q-icon :name="scope.opt.icon" size="18px" />
                    </q-avatar>
                  </q-item-section>
                  <q-item-section>{{ scope.opt.label }}</q-item-section>
                </q-item>
              </template>
            </q-select>

            <!-- Name -->
            <q-input
              v-model="form.name"
              outlined
              :label="t('integrations.name')"
              :placeholder="t('integrations.namePlaceholder')"
              :rules="[val => !!val || t('integrations.nameRequired')]"
            />

            <!-- Webhook URL -->
            <q-input
              v-model="form.config.incoming_webhook_url"
              outlined
              :label="t('integrations.webhookUrl')"
              :placeholder="form.provider === 'slack' ? 'https://hooks.slack.com/services/...' : form.provider === 'teams' ? 'https://outlook.office.com/webhook/...' : 'https://...'"
              :rules="[val => !!val || t('integrations.webhookUrlRequired'), val => /^https?:\/\/.+/.test(val) || t('integrations.webhookUrlInvalid')]"
            >
              <template v-slot:prepend>
                <q-icon name="link" />
              </template>
            </q-input>

            <!-- Slack-specific fields -->
            <template v-if="showSlackFields">
              <q-input
                v-model="form.config.channel"
                outlined
                :label="t('integrations.slackChannel')"
                placeholder="#general"
                hint="Opcional"
              />
              <q-input
                v-model="form.config.signing_secret"
                outlined
                :label="t('integrations.slackSigningSecret')"
                hint="Opcional - para verificar solicitudes entrantes"
                type="password"
              />
            </template>

            <!-- Teams-specific fields -->
            <template v-if="showTeamsFields">
              <q-input
                v-model="form.config.security_token"
                outlined
                :label="t('integrations.teamsSecurityToken')"
                hint="Opcional - para verificar solicitudes entrantes"
                type="password"
              />
            </template>

            <!-- Events -->
            <div>
              <div class="text-subtitle2 q-mb-sm">{{ t('integrations.selectEvents') }}</div>
              <div class="q-gutter-sm">
                <q-checkbox
                  v-for="ev in eventOptions"
                  :key="ev.value"
                  v-model="form.events"
                  :val="ev.value"
                  :label="ev.label"
                />
              </div>
            </div>

            <!-- Active toggle -->
            <q-toggle v-model="form.is_active" :label="t('integrations.active')" color="positive" />

            <!-- Setup instructions -->
            <q-expansion-item
              icon="help_outline"
              :label="t('integrations.setupInstructions')"
              header-class="text-primary"
            >
              <q-card flat>
                <q-card-section class="text-body2">
                  <template v-if="form.provider === 'slack'">
                    <ol class="q-pl-md q-my-none">
                      <li>{{ t('integrations.slackStep1') }}</li>
                      <li>{{ t('integrations.slackStep2') }}</li>
                      <li>{{ t('integrations.slackStep3') }}</li>
                      <li>{{ t('integrations.slackStep4') }}</li>
                      <li>{{ t('integrations.slackStep5') }}</li>
                    </ol>
                  </template>
                  <template v-else-if="form.provider === 'teams'">
                    <ol class="q-pl-md q-my-none">
                      <li>{{ t('integrations.teamsStep1') }}</li>
                      <li>{{ t('integrations.teamsStep2') }}</li>
                      <li>{{ t('integrations.teamsStep3') }}</li>
                      <li>{{ t('integrations.teamsStep4') }}</li>
                    </ol>
                  </template>
                  <template v-else>
                    <p>{{ t('integrations.genericWebhookDesc') }}</p>
                  </template>
                </q-card-section>
              </q-card>
            </q-expansion-item>
          </q-form>
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="primary" :label="t('common.save')" :loading="saving" @click="onSave" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
