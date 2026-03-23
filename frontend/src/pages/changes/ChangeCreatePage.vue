<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { createChangeRequest } from '@/api/changeRequests'
import { getCategories } from '@/api/categories'
import { getAgents } from '@/api/users'
import { getDepartments } from '@/api/departments'
import type { Category, User, Department } from '@/types'

const { t } = useI18n()
const router = useRouter()

const loading = ref(false)
const categories = ref<Category[]>([])
const agents = ref<User[]>([])
const departments = ref<Department[]>([])

const form = ref({
  title: '',
  description: '',
  type: 'normal' as 'standard' | 'normal' | 'emergency',
  priority: 'medium' as 'low' | 'medium' | 'high' | 'critical',
  risk_level: 'medium' as 'low' | 'medium' | 'high' | 'very_high',
  impact: 'medium' as 'low' | 'medium' | 'high' | 'extensive',
  category_id: null as number | null,
  assigned_to: null as number | null,
  department_id: null as number | null,
  reason_for_change: '',
  implementation_plan: '',
  rollback_plan: '',
  test_plan: '',
})

const typeOptions = [
  { label: t('changes.types.standard'), value: 'standard' },
  { label: t('changes.types.normal'), value: 'normal' },
  { label: t('changes.types.emergency'), value: 'emergency' },
]

const priorityOptions = [
  { label: t('changes.priorities.low'), value: 'low' },
  { label: t('changes.priorities.medium'), value: 'medium' },
  { label: t('changes.priorities.high'), value: 'high' },
  { label: t('changes.priorities.critical'), value: 'critical' },
]

const riskOptions = [
  { label: t('changes.riskLevels.low'), value: 'low' },
  { label: t('changes.riskLevels.medium'), value: 'medium' },
  { label: t('changes.riskLevels.high'), value: 'high' },
  { label: t('changes.riskLevels.very_high'), value: 'very_high' },
]

const impactOptions = [
  { label: t('changes.impacts.low'), value: 'low' },
  { label: t('changes.impacts.medium'), value: 'medium' },
  { label: t('changes.impacts.high'), value: 'high' },
  { label: t('changes.impacts.extensive'), value: 'extensive' },
]

async function onSubmit() {
  loading.value = true
  try {
    const res = await createChangeRequest(form.value)
    Notify.create({ type: 'positive', message: t('changes.created') })
    router.push(`/changes/${res.data.id}`)
  } catch { /* handled by interceptor */ }
  finally { loading.value = false }
}

onMounted(async () => {
  try {
    const [catRes, agentRes, deptRes] = await Promise.all([
      getCategories(), getAgents(), getDepartments(),
    ])
    categories.value = catRes.data || []
    agents.value = agentRes.data || []
    departments.value = deptRes.data || []
  } catch { /* ignore */ }
})
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <q-btn flat icon="arrow_back" :label="t('common.back')" no-caps @click="router.push('/changes')" />
      <div class="text-h5 text-weight-bold q-ml-sm">{{ t('changes.create') }}</div>
    </div>

    <q-form @submit.prevent="onSubmit">
      <div class="row q-col-gutter-md">
        <!-- Left column: Main info -->
        <div class="col-12 col-md-8">
          <q-card flat bordered class="q-mb-md">
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('changes.basicInfo') }}</div>

              <q-input
                v-model="form.title"
                :label="t('changes.titleField') + ' *'"
                outlined dense
                :rules="[(v: string) => !!v || t('changes.required')]"
                class="q-mb-sm"
              />

              <q-input
                v-model="form.description"
                :label="t('tickets.description') + ' *'"
                outlined dense type="textarea" autogrow
                :rules="[(v: string) => !!v || t('changes.required')]"
                class="q-mb-sm"
              />

              <q-input
                v-model="form.reason_for_change"
                :label="t('changes.reasonForChange') + ' *'"
                outlined dense type="textarea" autogrow
                :rules="[(v: string) => !!v || t('changes.required')]"
                class="q-mb-sm"
              />
            </q-card-section>
          </q-card>

          <q-card flat bordered class="q-mb-md">
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('changes.plans') }}</div>

              <q-input
                v-model="form.implementation_plan"
                :label="t('changes.implementationPlan')"
                outlined dense type="textarea" autogrow
                class="q-mb-sm"
              />

              <q-input
                v-model="form.rollback_plan"
                :label="t('changes.rollbackPlan')"
                outlined dense type="textarea" autogrow
                class="q-mb-sm"
              />

              <q-input
                v-model="form.test_plan"
                :label="t('changes.testPlan')"
                outlined dense type="textarea" autogrow
              />
            </q-card-section>
          </q-card>
        </div>

        <!-- Right column: Properties -->
        <div class="col-12 col-md-4">
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('changes.properties') }}</div>

              <q-select
                v-model="form.type"
                :options="typeOptions"
                :label="t('changes.type')"
                emit-value map-options outlined dense
                class="q-mb-sm"
              />

              <q-select
                v-model="form.priority"
                :options="priorityOptions"
                :label="t('common.priority')"
                emit-value map-options outlined dense
                class="q-mb-sm"
              />

              <q-select
                v-model="form.risk_level"
                :options="riskOptions"
                :label="t('changes.riskLevel')"
                emit-value map-options outlined dense
                class="q-mb-sm"
              />

              <q-select
                v-model="form.impact"
                :options="impactOptions"
                :label="t('changes.impact')"
                emit-value map-options outlined dense
                class="q-mb-sm"
              />

              <q-select
                v-model="form.category_id"
                :options="categories.map(c => ({ label: c.name, value: c.id }))"
                :label="t('common.category')"
                emit-value map-options outlined dense clearable
                class="q-mb-sm"
              />

              <q-select
                v-model="form.department_id"
                :options="departments.map(d => ({ label: d.name, value: d.id }))"
                :label="t('changes.department')"
                emit-value map-options outlined dense clearable
                class="q-mb-sm"
              />

              <q-select
                v-model="form.assigned_to"
                :options="agents.map(a => ({ label: a.name, value: a.id }))"
                :label="t('changes.assignee')"
                emit-value map-options outlined dense clearable
              />
            </q-card-section>
          </q-card>
        </div>
      </div>

      <div class="row justify-end q-mt-md q-gutter-sm">
        <q-btn flat :label="t('common.cancel')" no-caps @click="router.push('/changes')" />
        <q-btn type="submit" color="primary" :label="t('changes.saveDraft')" :loading="loading" no-caps />
      </div>
    </q-form>
  </q-page>
</template>
