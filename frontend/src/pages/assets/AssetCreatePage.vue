<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { Notify, type QForm } from 'quasar'
import { createAsset, getNextAssetTag } from '@/api/assets'
import { getAssetTypes } from '@/api/assetTypes'
import { getAgents } from '@/api/users'
import { getDepartments } from '@/api/departments'
import type { AssetType, User, Department } from '@/types'

const { t } = useI18n()
const router = useRouter()

const loading = ref(false)
const loadingData = ref(true)
const formRef = ref<QForm | null>(null)
const assetTypes = ref<AssetType[]>([])
const agents = ref<User[]>([])
const departments = ref<Department[]>([])
const nextTag = ref('')

const form = ref({
  asset_type_id: null as number | null,
  name: '',
  serial_number: '',
  status: 'active',
  condition: 'good',
  assigned_to: null as number | null,
  department_id: null as number | null,
  location: '',
  purchase_date: '',
  purchase_cost: null as number | null,
  warranty_expiry: '',
  vendor: '',
  manufacturer: '',
  model: '',
  ip_address: '',
  mac_address: '',
  notes: '',
  custom_fields: {} as Record<string, any>,
})

const selectedType = computed(() =>
  assetTypes.value.find(t => t.id === form.value.asset_type_id)
)

const statusOptions = [
  { label: t('assets.statuses.active'), value: 'active' },
  { label: t('assets.statuses.inactive'), value: 'inactive' },
  { label: t('assets.statuses.maintenance'), value: 'maintenance' },
  { label: t('assets.statuses.retired'), value: 'retired' },
]

const conditionOptions = [
  { label: t('assets.conditions.new'), value: 'new' },
  { label: t('assets.conditions.good'), value: 'good' },
  { label: t('assets.conditions.fair'), value: 'fair' },
  { label: t('assets.conditions.poor'), value: 'poor' },
  { label: t('assets.conditions.broken'), value: 'broken' },
]

// Reset custom fields when type changes
watch(() => form.value.asset_type_id, () => {
  form.value.custom_fields = {}
})

onMounted(async () => {
  try {
    const [typesRes, agentsRes, deptsRes, tagRes] = await Promise.all([
      getAssetTypes(),
      getAgents(),
      getDepartments(),
      getNextAssetTag(),
    ])
    assetTypes.value = typesRes.data
    agents.value = agentsRes.data
    departments.value = deptsRes.data
    nextTag.value = tagRes.data.asset_tag
  } catch { /* ignore */ }
  finally { loadingData.value = false }
})

async function onSubmit() {
  const valid = await formRef.value?.validate()
  if (!valid) return

  loading.value = true
  try {
    const payload: Record<string, any> = { ...form.value }
    // Clean empty strings
    Object.keys(payload).forEach(key => {
      if (payload[key] === '') payload[key] = null
    })

    const res = await createAsset(payload)
    Notify.create({ type: 'positive', message: t('assets.created') })
    router.push(`/assets/${res.data.id}`)
  } catch {
    Notify.create({ type: 'negative', message: t('assets.createError') })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <q-btn flat round icon="arrow_back" @click="router.push('/assets')" class="q-mr-sm" />
      <div class="col">
        <div class="text-h5 text-weight-bold">{{ t('assets.create') }}</div>
      </div>
    </div>

    <q-linear-progress v-if="loadingData" indeterminate color="primary" />

    <q-form v-else ref="formRef" @submit.prevent="onSubmit">
      <div class="row q-col-gutter-md" style="max-width: 900px;">
        <!-- Asset Tag Preview -->
        <div class="col-12">
          <q-banner dense class="bg-blue-1 text-blue-9 q-mb-sm" rounded>
            <template v-slot:avatar><q-icon name="label" /></template>
            {{ t('assets.nextTagLabel') }}: <strong>{{ nextTag }}</strong>
          </q-banner>
        </div>

        <!-- Asset Type -->
        <div class="col-12">
          <q-select
            v-model="form.asset_type_id"
            :options="assetTypes"
            option-value="id"
            option-label="name"
            emit-value map-options
            :label="t('assets.type') + ' *'"
            outlined
            :rules="[(val: any) => !!val || t('assets.typeRequired')]"
          >
            <template v-slot:option="{ opt, itemProps }">
              <q-item v-bind="itemProps">
                <q-item-section avatar>
                  <q-icon :name="opt.icon || 'devices'" />
                </q-item-section>
                <q-item-section>{{ opt.name }}</q-item-section>
              </q-item>
            </template>
          </q-select>
        </div>

        <!-- Name -->
        <div class="col-12">
          <q-input
            v-model="form.name"
            :label="t('assets.name') + ' *'"
            outlined
            :rules="[(val: string) => !!val || t('assets.nameRequired')]"
            :placeholder="t('assets.namePlaceholder')"
          />
        </div>

        <div class="col-12 col-sm-6">
          <q-select v-model="form.status" :options="statusOptions" option-value="value" option-label="label" emit-value map-options :label="t('common.status')" outlined />
        </div>
        <div class="col-12 col-sm-6">
          <q-select v-model="form.condition" :options="conditionOptions" option-value="value" option-label="label" emit-value map-options :label="t('assets.condition')" outlined />
        </div>

        <div class="col-12 col-sm-6">
          <q-input v-model="form.serial_number" :label="t('assets.serialNumber')" outlined />
        </div>
        <div class="col-12 col-sm-6">
          <q-input v-model="form.location" :label="t('assets.location')" outlined />
        </div>

        <div class="col-12 col-sm-6">
          <q-input v-model="form.manufacturer" :label="t('assets.manufacturer')" outlined />
        </div>
        <div class="col-12 col-sm-6">
          <q-input v-model="form.model" :label="t('assets.model')" outlined />
        </div>

        <div class="col-12 col-sm-6">
          <q-select v-model="form.assigned_to" :options="agents" option-value="id" option-label="name" emit-value map-options :label="t('tickets.assignedTo')" outlined clearable />
        </div>
        <div class="col-12 col-sm-6">
          <q-select v-model="form.department_id" :options="departments" option-value="id" option-label="name" emit-value map-options :label="t('ticketForm.department')" outlined clearable />
        </div>

        <div class="col-12 col-sm-6">
          <q-input v-model="form.ip_address" :label="t('assets.ipAddress')" outlined />
        </div>
        <div class="col-12 col-sm-6">
          <q-input v-model="form.mac_address" :label="t('assets.macAddress')" outlined />
        </div>

        <div class="col-12 col-sm-6">
          <q-input v-model="form.vendor" :label="t('assets.vendor')" outlined />
        </div>
        <div class="col-12 col-sm-6">
          <q-input v-model="form.purchase_date" :label="t('assets.purchaseDate')" outlined type="date" />
        </div>

        <div class="col-12 col-sm-6">
          <q-input v-model="form.purchase_cost" :label="t('assets.purchaseCost')" outlined type="number" prefix="S/" />
        </div>
        <div class="col-12 col-sm-6">
          <q-input v-model="form.warranty_expiry" :label="t('assets.warrantyExpiry')" outlined type="date" />
        </div>

        <div class="col-12">
          <q-input v-model="form.notes" :label="t('assets.notes')" outlined type="textarea" rows="3" />
        </div>

        <!-- Dynamic Custom Fields based on selected type -->
        <template v-if="selectedType?.fields?.length">
          <div class="col-12">
            <q-separator class="q-my-sm" />
            <div class="text-subtitle1 text-weight-medium">{{ t('assets.customFields') }} - {{ selectedType.name }}</div>
          </div>
          <div v-for="field in selectedType.fields" :key="field.name" class="col-12 col-sm-6">
            <q-select
              v-if="field.type === 'select' && field.options"
              v-model="form.custom_fields[field.name]"
              :options="field.options"
              :label="field.label + (field.required ? ' *' : '')"
              outlined
              clearable
            />
            <q-checkbox
              v-else-if="field.type === 'checkbox'"
              v-model="form.custom_fields[field.name]"
              :label="field.label"
            />
            <q-input
              v-else
              v-model="form.custom_fields[field.name]"
              :label="field.label + (field.required ? ' *' : '')"
              :type="field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'"
              outlined
            />
          </div>
        </template>

        <!-- Submit -->
        <div class="col-12 q-mt-md">
          <q-btn type="submit" color="primary" :label="t('assets.create')" no-caps :loading="loading" class="q-mr-sm" />
          <q-btn flat :label="t('common.cancel')" no-caps @click="router.push('/assets')" />
        </div>
      </div>
    </q-form>
  </q-page>
</template>
