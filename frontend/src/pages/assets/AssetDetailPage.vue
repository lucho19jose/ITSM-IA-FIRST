<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import {
  getAsset, updateAsset, assignAsset, unassignAsset,
  linkTicketToAsset, unlinkTicketFromAsset,
  getAssetRelationships, addAssetRelationship, removeAssetRelationship,
  getAssetTimeline
} from '@/api/assets'
import { getAssets } from '@/api/assets'
import { getAssetTypes } from '@/api/assetTypes'
import { getAgents } from '@/api/users'
import { getTickets } from '@/api/tickets'
import { getDepartments } from '@/api/departments'
import type { Asset, AssetType, AssetRelationship, AssetLog, User, Ticket, Department } from '@/types'

const props = defineProps<{ id: string }>()
const { t } = useI18n()
const router = useRouter()
const $q = useQuasar()
const auth = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const asset = ref<Asset | null>(null)
const assetTypes = ref<AssetType[]>([])
const agents = ref<User[]>([])
const departments = ref<Department[]>([])
const activeTab = ref('details')
const isEditing = ref(false)

// Relationships
const relationships = ref<AssetRelationship[]>([])
const relLoading = ref(false)
const showAddRelDialog = ref(false)
const relForm = ref({ target_asset_id: null as number | null, relationship_type: 'connected_to' })
const relAssetSearch = ref('')
const relAssetOptions = ref<Asset[]>([])

// Timeline
const timeline = ref<AssetLog[]>([])
const timelineLoading = ref(false)

// Tickets
const showLinkTicketDialog = ref(false)
const ticketSearch = ref('')
const ticketOptions = ref<Ticket[]>([])

// Assign
const showAssignDialog = ref(false)
const assignUserId = ref<number | null>(null)

// Edit form
const editForm = ref<Partial<Asset>>({})

const statusOptions = [
  { label: t('assets.statuses.active'), value: 'active' },
  { label: t('assets.statuses.inactive'), value: 'inactive' },
  { label: t('assets.statuses.maintenance'), value: 'maintenance' },
  { label: t('assets.statuses.retired'), value: 'retired' },
  { label: t('assets.statuses.lost'), value: 'lost' },
  { label: t('assets.statuses.disposed'), value: 'disposed' },
]

const conditionOptions = [
  { label: t('assets.conditions.new'), value: 'new' },
  { label: t('assets.conditions.good'), value: 'good' },
  { label: t('assets.conditions.fair'), value: 'fair' },
  { label: t('assets.conditions.poor'), value: 'poor' },
  { label: t('assets.conditions.broken'), value: 'broken' },
]

const statusColorMap: Record<string, string> = {
  active: 'green', inactive: 'grey', maintenance: 'orange',
  retired: 'blue-grey', lost: 'red', disposed: 'brown',
}

const conditionColorMap: Record<string, string> = {
  new: 'green', good: 'teal', fair: 'amber', poor: 'orange', broken: 'red',
}

const relationshipTypeOptions = [
  { label: t('assets.relTypes.contains'), value: 'contains' },
  { label: t('assets.relTypes.depends_on'), value: 'depends_on' },
  { label: t('assets.relTypes.connected_to'), value: 'connected_to' },
  { label: t('assets.relTypes.installed_on'), value: 'installed_on' },
  { label: t('assets.relTypes.runs_on'), value: 'runs_on' },
]

const currentType = computed(() => assetTypes.value.find(t => t.id === asset.value?.asset_type_id))

async function loadAsset() {
  loading.value = true
  try {
    const [assetRes, typesRes, agentsRes, deptsRes] = await Promise.all([
      getAsset(Number(props.id)),
      getAssetTypes(),
      getAgents(),
      getDepartments(),
    ])
    asset.value = assetRes.data
    assetTypes.value = typesRes.data
    agents.value = agentsRes.data
    departments.value = deptsRes.data
    editForm.value = { ...assetRes.data }
  } catch {
    $q.notify({ type: 'negative', message: t('assets.loadError') })
    router.push('/assets')
  } finally {
    loading.value = false
  }
}

async function loadRelationships() {
  relLoading.value = true
  try {
    const res = await getAssetRelationships(Number(props.id))
    relationships.value = res.data
  } catch { /* ignore */ }
  finally { relLoading.value = false }
}

async function loadTimeline() {
  timelineLoading.value = true
  try {
    const res = await getAssetTimeline(Number(props.id))
    timeline.value = res.data
  } catch { /* ignore */ }
  finally { timelineLoading.value = false }
}

function startEditing() {
  editForm.value = { ...asset.value }
  isEditing.value = true
}

function cancelEditing() {
  isEditing.value = false
  editForm.value = { ...asset.value }
}

async function saveAsset() {
  if (!asset.value) return
  saving.value = true
  try {
    const res = await updateAsset(asset.value.id, editForm.value)
    asset.value = res.data
    isEditing.value = false
    $q.notify({ type: 'positive', message: t('assets.updated') })
  } catch {
    $q.notify({ type: 'negative', message: t('assets.updateError') })
  } finally {
    saving.value = false
  }
}

async function onAssign() {
  if (!asset.value || !assignUserId.value) return
  try {
    const res = await assignAsset(asset.value.id, assignUserId.value)
    asset.value = res.data
    showAssignDialog.value = false
    assignUserId.value = null
    $q.notify({ type: 'positive', message: res.message })
  } catch {
    $q.notify({ type: 'negative', message: 'Error al asignar' })
  }
}

async function onUnassign() {
  if (!asset.value) return
  try {
    const res = await unassignAsset(asset.value.id)
    asset.value = res.data
    $q.notify({ type: 'positive', message: res.message })
  } catch {
    $q.notify({ type: 'negative', message: 'Error al desasignar' })
  }
}

async function searchTickets(val: string) {
  if (val.length < 2) { ticketOptions.value = []; return }
  try {
    const res = await getTickets({ search: val, per_page: 10 })
    ticketOptions.value = res.data
  } catch { /* ignore */ }
}

async function onLinkTicket(ticketId: number) {
  if (!asset.value) return
  try {
    await linkTicketToAsset(asset.value.id, ticketId)
    showLinkTicketDialog.value = false
    ticketSearch.value = ''
    $q.notify({ type: 'positive', message: t('assets.ticketLinked') })
    loadAsset()
  } catch {
    $q.notify({ type: 'negative', message: 'Error al vincular ticket' })
  }
}

async function onUnlinkTicket(ticketId: number) {
  if (!asset.value) return
  try {
    await unlinkTicketFromAsset(asset.value.id, ticketId)
    $q.notify({ type: 'positive', message: t('assets.ticketUnlinked') })
    loadAsset()
  } catch {
    $q.notify({ type: 'negative', message: 'Error al desvincular ticket' })
  }
}

async function searchRelAssets(val: string) {
  if (val.length < 2) { relAssetOptions.value = []; return }
  try {
    const res = await getAssets({ search: val, per_page: 10 })
    relAssetOptions.value = res.data.filter(a => a.id !== Number(props.id))
  } catch { /* ignore */ }
}

async function onAddRelationship() {
  if (!asset.value || !relForm.value.target_asset_id) return
  try {
    await addAssetRelationship(asset.value.id, {
      target_asset_id: relForm.value.target_asset_id,
      relationship_type: relForm.value.relationship_type,
    })
    showAddRelDialog.value = false
    relForm.value = { target_asset_id: null, relationship_type: 'connected_to' }
    $q.notify({ type: 'positive', message: t('assets.relationshipAdded') })
    loadRelationships()
  } catch {
    $q.notify({ type: 'negative', message: 'Error al agregar relación' })
  }
}

async function onRemoveRelationship(relId: number) {
  if (!asset.value) return
  try {
    await removeAssetRelationship(asset.value.id, relId)
    $q.notify({ type: 'positive', message: t('assets.relationshipRemoved') })
    loadRelationships()
  } catch {
    $q.notify({ type: 'negative', message: 'Error al eliminar relación' })
  }
}

function timelineIcon(action: string): string {
  const map: Record<string, string> = {
    created: 'add_circle', updated: 'edit', assigned: 'person_add',
    unassigned: 'person_remove', status_changed: 'swap_horiz',
    maintenance: 'build', note_added: 'note_add',
    ticket_linked: 'link', ticket_unlinked: 'link_off',
    relationship_added: 'device_hub', relationship_removed: 'remove_circle',
  }
  return map[action] || 'info'
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('es-PE', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

function formatDateTime(dateStr: string | null): string {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('es-PE', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

onMounted(() => {
  loadAsset()
  loadRelationships()
  loadTimeline()
})
</script>

<template>
  <q-page padding>
    <q-linear-progress v-if="loading" indeterminate color="primary" />

    <template v-if="asset && !loading">
      <!-- Header -->
      <div class="row items-center q-mb-md">
        <q-btn flat round icon="arrow_back" @click="router.push('/assets')" class="q-mr-sm" />
        <div class="col">
          <div class="row items-center q-gutter-sm">
            <span class="text-h5 text-weight-bold">{{ asset.name }}</span>
            <q-chip dense color="blue-1" text-color="blue-9" :label="asset.asset_tag" />
            <q-badge :color="statusColorMap[asset.status]" :label="t(`assets.statuses.${asset.status}`)" />
            <q-badge :color="conditionColorMap[asset.condition]" :label="t(`assets.conditions.${asset.condition}`)" />
          </div>
          <div class="text-caption text-grey q-mt-xs">
            {{ asset.asset_type?.name }} | {{ t('tickets.createdAt') }}: {{ formatDate(asset.created_at) }}
          </div>
        </div>
        <div class="col-auto q-gutter-sm">
          <q-btn v-if="!isEditing" outline color="primary" icon="edit" :label="t('common.edit')" no-caps @click="startEditing" />
          <q-btn outline color="primary" icon="person_add" :label="t('assets.assign')" no-caps @click="showAssignDialog = true" />
          <q-btn v-if="asset.assigned_to" outline color="orange" icon="person_remove" :label="t('assets.unassign')" no-caps @click="onUnassign" />
        </div>
      </div>

      <div class="row q-col-gutter-md">
        <!-- Main Content -->
        <div class="col-12 col-md-8">
          <q-card flat bordered>
            <q-tabs v-model="activeTab" dense align="left" active-color="primary" indicator-color="primary" narrow-indicator>
              <q-tab name="details" :label="t('assets.details')" />
              <q-tab name="relationships" :label="t('assets.relationships')" @click="loadRelationships" />
              <q-tab name="tickets" :label="t('tickets.title')" />
              <q-tab name="timeline" :label="t('assets.timeline')" @click="loadTimeline" />
            </q-tabs>

            <q-separator />

            <q-tab-panels v-model="activeTab" animated>
              <!-- Details Tab -->
              <q-tab-panel name="details">
                <template v-if="isEditing">
                  <div class="row q-col-gutter-md">
                    <div class="col-12 col-sm-6">
                      <q-input v-model="editForm.name" :label="t('assets.name')" outlined dense />
                    </div>
                    <div class="col-12 col-sm-6">
                      <q-select v-model="editForm.asset_type_id" :options="assetTypes" option-value="id" option-label="name" emit-value map-options :label="t('assets.type')" outlined dense />
                    </div>
                    <div class="col-12 col-sm-6">
                      <q-select v-model="editForm.status" :options="statusOptions" option-value="value" option-label="label" emit-value map-options :label="t('common.status')" outlined dense />
                    </div>
                    <div class="col-12 col-sm-6">
                      <q-select v-model="editForm.condition" :options="conditionOptions" option-value="value" option-label="label" emit-value map-options :label="t('assets.condition')" outlined dense />
                    </div>
                    <div class="col-12 col-sm-6">
                      <q-input v-model="editForm.serial_number" :label="t('assets.serialNumber')" outlined dense />
                    </div>
                    <div class="col-12 col-sm-6">
                      <q-input v-model="editForm.location" :label="t('assets.location')" outlined dense />
                    </div>
                    <div class="col-12 col-sm-6">
                      <q-input v-model="editForm.manufacturer" :label="t('assets.manufacturer')" outlined dense />
                    </div>
                    <div class="col-12 col-sm-6">
                      <q-input v-model="editForm.model" :label="t('assets.model')" outlined dense />
                    </div>
                    <div class="col-12 col-sm-6">
                      <q-input v-model="editForm.ip_address" :label="t('assets.ipAddress')" outlined dense />
                    </div>
                    <div class="col-12 col-sm-6">
                      <q-input v-model="editForm.mac_address" :label="t('assets.macAddress')" outlined dense />
                    </div>
                    <div class="col-12 col-sm-6">
                      <q-input v-model="editForm.vendor" :label="t('assets.vendor')" outlined dense />
                    </div>
                    <div class="col-12 col-sm-6">
                      <q-select v-model="editForm.department_id" :options="departments" option-value="id" option-label="name" emit-value map-options :label="t('ticketForm.department')" outlined dense clearable />
                    </div>
                    <div class="col-12 col-sm-4">
                      <q-input v-model="editForm.purchase_date" :label="t('assets.purchaseDate')" outlined dense type="date" />
                    </div>
                    <div class="col-12 col-sm-4">
                      <q-input v-model="editForm.purchase_cost" :label="t('assets.purchaseCost')" outlined dense type="number" prefix="S/" />
                    </div>
                    <div class="col-12 col-sm-4">
                      <q-input v-model="editForm.warranty_expiry" :label="t('assets.warrantyExpiry')" outlined dense type="date" />
                    </div>
                    <div class="col-12">
                      <q-input v-model="editForm.notes" :label="t('assets.notes')" outlined dense type="textarea" rows="3" />
                    </div>

                    <!-- Custom fields -->
                    <template v-if="currentType?.fields?.length">
                      <div class="col-12">
                        <q-separator class="q-my-sm" />
                        <div class="text-subtitle2 q-mb-sm">{{ t('assets.customFields') }}</div>
                      </div>
                      <div v-for="field in currentType.fields" :key="field.name" class="col-12 col-sm-6">
                        <q-select
                          v-if="field.type === 'select' && field.options"
                          v-model="(editForm.custom_fields as any)[field.name]"
                          :options="field.options"
                          :label="field.label"
                          outlined dense
                        />
                        <q-checkbox
                          v-else-if="field.type === 'checkbox'"
                          v-model="(editForm.custom_fields as any)[field.name]"
                          :label="field.label"
                        />
                        <q-input
                          v-else
                          v-model="(editForm.custom_fields as any)[field.name]"
                          :label="field.label"
                          :type="field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'"
                          outlined dense
                        />
                      </div>
                    </template>

                    <div class="col-12 q-mt-sm">
                      <q-btn color="primary" :label="t('common.save')" no-caps :loading="saving" @click="saveAsset" class="q-mr-sm" />
                      <q-btn flat :label="t('common.cancel')" no-caps @click="cancelEditing" />
                    </div>
                  </div>
                </template>

                <template v-else>
                  <div class="row q-col-gutter-md">
                    <div class="col-12 col-sm-6">
                      <div class="text-caption text-grey">{{ t('assets.serialNumber') }}</div>
                      <div>{{ asset.serial_number || '-' }}</div>
                    </div>
                    <div class="col-12 col-sm-6">
                      <div class="text-caption text-grey">{{ t('assets.location') }}</div>
                      <div>{{ asset.location || '-' }}</div>
                    </div>
                    <div class="col-12 col-sm-6">
                      <div class="text-caption text-grey">{{ t('assets.manufacturer') }}</div>
                      <div>{{ asset.manufacturer || '-' }}</div>
                    </div>
                    <div class="col-12 col-sm-6">
                      <div class="text-caption text-grey">{{ t('assets.model') }}</div>
                      <div>{{ asset.model || '-' }}</div>
                    </div>
                    <div class="col-12 col-sm-6">
                      <div class="text-caption text-grey">{{ t('assets.ipAddress') }}</div>
                      <div>{{ asset.ip_address || '-' }}</div>
                    </div>
                    <div class="col-12 col-sm-6">
                      <div class="text-caption text-grey">{{ t('assets.macAddress') }}</div>
                      <div>{{ asset.mac_address || '-' }}</div>
                    </div>
                    <div class="col-12 col-sm-6">
                      <div class="text-caption text-grey">{{ t('assets.vendor') }}</div>
                      <div>{{ asset.vendor || '-' }}</div>
                    </div>
                    <div class="col-12 col-sm-6">
                      <div class="text-caption text-grey">{{ t('ticketForm.department') }}</div>
                      <div>{{ asset.department?.name || '-' }}</div>
                    </div>

                    <!-- Custom fields display -->
                    <template v-if="asset.custom_fields && currentType?.fields?.length">
                      <div class="col-12"><q-separator class="q-my-sm" /></div>
                      <div class="col-12"><div class="text-subtitle2">{{ t('assets.customFields') }}</div></div>
                      <div v-for="field in currentType.fields" :key="field.name" class="col-12 col-sm-6">
                        <div class="text-caption text-grey">{{ field.label }}</div>
                        <div>{{ asset.custom_fields[field.name] ?? '-' }}</div>
                      </div>
                    </template>

                    <!-- Notes -->
                    <div v-if="asset.notes" class="col-12">
                      <q-separator class="q-my-sm" />
                      <div class="text-caption text-grey">{{ t('assets.notes') }}</div>
                      <div class="q-mt-xs" style="white-space: pre-wrap;">{{ asset.notes }}</div>
                    </div>
                  </div>
                </template>
              </q-tab-panel>

              <!-- Relationships Tab -->
              <q-tab-panel name="relationships">
                <div class="row items-center q-mb-md">
                  <div class="col text-subtitle2">{{ t('assets.relationships') }}</div>
                  <q-btn flat color="primary" icon="add" :label="t('assets.addRelationship')" no-caps @click="showAddRelDialog = true" />
                </div>

                <q-linear-progress v-if="relLoading" indeterminate color="primary" />

                <q-list v-if="relationships.length" bordered separator>
                  <q-item v-for="rel in relationships" :key="rel.id">
                    <q-item-section avatar>
                      <q-icon name="device_hub" color="primary" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>
                        <router-link :to="`/assets/${rel.related_asset.id}`" class="text-primary">
                          {{ rel.related_asset.name }}
                        </router-link>
                        <q-chip dense size="sm" class="q-ml-sm">{{ rel.related_asset.asset_tag }}</q-chip>
                      </q-item-label>
                      <q-item-label caption>
                        {{ t(`assets.relTypes.${rel.relationship_type}`) }}
                        ({{ rel.direction === 'outgoing' ? t('assets.relOutgoing') : t('assets.relIncoming') }})
                      </q-item-label>
                    </q-item-section>
                    <q-item-section side>
                      <q-btn flat round dense icon="delete" color="negative" size="sm" @click="onRemoveRelationship(rel.id)" />
                    </q-item-section>
                  </q-item>
                </q-list>

                <div v-else-if="!relLoading" class="text-center q-pa-lg text-grey">
                  <q-icon name="device_hub" size="40px" class="q-mb-sm" />
                  <div>{{ t('assets.noRelationships') }}</div>
                </div>
              </q-tab-panel>

              <!-- Tickets Tab -->
              <q-tab-panel name="tickets">
                <div class="row items-center q-mb-md">
                  <div class="col text-subtitle2">{{ t('assets.linkedTickets') }}</div>
                  <q-btn flat color="primary" icon="link" :label="t('assets.linkTicket')" no-caps @click="showLinkTicketDialog = true" />
                </div>

                <q-list v-if="asset.tickets?.length" bordered separator>
                  <q-item v-for="ticket in asset.tickets" :key="ticket.id" clickable @click="router.push(`/tickets/${ticket.id}`)">
                    <q-item-section>
                      <q-item-label>
                        <span class="text-weight-medium text-primary">{{ ticket.ticket_number }}</span>
                        - {{ ticket.title }}
                      </q-item-label>
                      <q-item-label caption>
                        {{ ticket.requester?.name }} | {{ formatDate(ticket.created_at) }}
                      </q-item-label>
                    </q-item-section>
                    <q-item-section side>
                      <div class="row items-center q-gutter-sm">
                        <q-badge :color="ticket.status === 'open' ? 'blue' : ticket.status === 'resolved' ? 'green' : 'orange'" :label="t(`tickets.statuses.${ticket.status}`)" />
                        <q-btn flat round dense icon="link_off" color="negative" size="sm" @click.stop="onUnlinkTicket(ticket.id)">
                          <q-tooltip>{{ t('assets.unlinkTicket') }}</q-tooltip>
                        </q-btn>
                      </div>
                    </q-item-section>
                  </q-item>
                </q-list>

                <div v-else class="text-center q-pa-lg text-grey">
                  <q-icon name="confirmation_number" size="40px" class="q-mb-sm" />
                  <div>{{ t('assets.noLinkedTickets') }}</div>
                </div>
              </q-tab-panel>

              <!-- Timeline Tab -->
              <q-tab-panel name="timeline">
                <q-linear-progress v-if="timelineLoading" indeterminate color="primary" />

                <q-timeline v-if="timeline.length" color="primary">
                  <q-timeline-entry
                    v-for="entry in timeline"
                    :key="entry.id"
                    :icon="timelineIcon(entry.action)"
                    :subtitle="formatDateTime(entry.created_at)"
                  >
                    <template v-slot:title>
                      <span class="text-body2">{{ entry.description }}</span>
                    </template>
                    <div v-if="entry.user" class="text-caption text-grey q-mt-xs">
                      {{ entry.user.name }}
                    </div>
                  </q-timeline-entry>
                </q-timeline>

                <div v-else-if="!timelineLoading" class="text-center q-pa-lg text-grey">
                  <q-icon name="history" size="40px" class="q-mb-sm" />
                  <div>{{ t('assets.noTimeline') }}</div>
                </div>
              </q-tab-panel>
            </q-tab-panels>
          </q-card>
        </div>

        <!-- Sidebar -->
        <div class="col-12 col-md-4">
          <!-- Assigned User -->
          <q-card flat bordered class="q-mb-md">
            <q-card-section>
              <div class="text-subtitle2 q-mb-sm">{{ t('tickets.assignedTo') }}</div>
              <div v-if="asset.assignee" class="row items-center q-gutter-sm">
                <q-avatar size="36px" color="primary" text-color="white" font-size="16px">
                  {{ asset.assignee.name?.charAt(0)?.toUpperCase() }}
                </q-avatar>
                <div>
                  <div class="text-weight-medium">{{ asset.assignee.name }}</div>
                  <div class="text-caption text-grey">{{ asset.assignee.email }}</div>
                </div>
              </div>
              <div v-else class="text-grey">{{ t('ticketList.unassigned') }}</div>
            </q-card-section>
          </q-card>

          <!-- Department -->
          <q-card flat bordered class="q-mb-md">
            <q-card-section>
              <div class="text-subtitle2 q-mb-sm">{{ t('ticketForm.department') }}</div>
              <div>{{ asset.department?.name || '-' }}</div>
            </q-card-section>
          </q-card>

          <!-- Financial Info -->
          <q-card flat bordered class="q-mb-md">
            <q-card-section>
              <div class="text-subtitle2 q-mb-sm">{{ t('assets.financialInfo') }}</div>
              <div class="row q-col-gutter-sm">
                <div class="col-6">
                  <div class="text-caption text-grey">{{ t('assets.purchaseDate') }}</div>
                  <div>{{ formatDate(asset.purchase_date) }}</div>
                </div>
                <div class="col-6">
                  <div class="text-caption text-grey">{{ t('assets.purchaseCost') }}</div>
                  <div>{{ asset.purchase_cost ? `S/ ${Number(asset.purchase_cost).toFixed(2)}` : '-' }}</div>
                </div>
                <div class="col-6">
                  <div class="text-caption text-grey">{{ t('assets.warrantyExpiry') }}</div>
                  <div>{{ formatDate(asset.warranty_expiry) }}</div>
                </div>
                <div class="col-6">
                  <div class="text-caption text-grey">{{ t('assets.vendor') }}</div>
                  <div>{{ asset.vendor || '-' }}</div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </template>

    <!-- Assign Dialog -->
    <q-dialog v-model="showAssignDialog">
      <q-card style="min-width: 350px;">
        <q-card-section>
          <div class="text-h6">{{ t('assets.assign') }}</div>
        </q-card-section>
        <q-card-section>
          <q-select
            v-model="assignUserId"
            :options="agents"
            option-value="id"
            option-label="name"
            emit-value map-options
            :label="t('tickets.assignedTo')"
            outlined
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="primary" :label="t('assets.assign')" :disable="!assignUserId" @click="onAssign" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Link Ticket Dialog -->
    <q-dialog v-model="showLinkTicketDialog">
      <q-card style="min-width: 400px;">
        <q-card-section>
          <div class="text-h6">{{ t('assets.linkTicket') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model="ticketSearch"
            :label="t('common.search')"
            outlined dense
            @update:model-value="(val: string | number | null) => searchTickets(String(val || ''))"
          />
          <q-list v-if="ticketOptions.length" bordered separator class="q-mt-sm" style="max-height: 300px; overflow: auto;">
            <q-item v-for="ticket in ticketOptions" :key="ticket.id" clickable @click="onLinkTicket(ticket.id)">
              <q-item-section>
                <q-item-label>
                  <span class="text-weight-medium">{{ ticket.ticket_number }}</span> - {{ ticket.title }}
                </q-item-label>
                <q-item-label caption>{{ ticket.requester?.name }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Add Relationship Dialog -->
    <q-dialog v-model="showAddRelDialog">
      <q-card style="min-width: 400px;">
        <q-card-section>
          <div class="text-h6">{{ t('assets.addRelationship') }}</div>
        </q-card-section>
        <q-card-section class="q-gutter-sm">
          <q-input
            v-model="relAssetSearch"
            :label="t('assets.searchAsset')"
            outlined dense
            @update:model-value="(val: string | number | null) => searchRelAssets(String(val || ''))"
          />
          <q-select
            v-if="relAssetOptions.length"
            v-model="relForm.target_asset_id"
            :options="relAssetOptions"
            :option-value="(item: Asset) => item.id"
            :option-label="(item: Asset) => `${item.asset_tag} - ${item.name}`"
            emit-value map-options
            :label="t('assets.targetAsset')"
            outlined dense
          />
          <q-select
            v-model="relForm.relationship_type"
            :options="relationshipTypeOptions"
            option-value="value"
            option-label="label"
            emit-value map-options
            :label="t('assets.relationshipType')"
            outlined dense
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="primary" :label="t('common.save')" :disable="!relForm.target_asset_id" @click="onAddRelationship" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
