<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Notify } from 'quasar'
import { getReports, deleteReport, getReportTemplates } from '@/api/reports'
import type { SavedReport, ReportConfig } from '@/types'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const reports = ref<SavedReport[]>([])
const templates = ref<Array<{ name: string; description: string; report_type: string; config: ReportConfig }>>([])

const myReports = computed(() => reports.value.filter(r => r.user_id === auth.user?.id))
const sharedReports = computed(() => reports.value.filter(r => r.is_shared && r.user_id !== auth.user?.id))

const reportTypeIcons: Record<string, string> = {
  tickets: 'confirmation_number',
  agents: 'support_agent',
  sla: 'speed',
  categories: 'category',
  trends: 'trending_up',
  custom: 'tune',
}

const reportTypeColors: Record<string, string> = {
  tickets: 'primary',
  agents: 'teal',
  sla: 'orange',
  categories: 'purple',
  trends: 'cyan',
  custom: 'grey',
}

onMounted(async () => {
  try {
    const [reportsRes, templatesRes] = await Promise.all([
      getReports(),
      getReportTemplates(),
    ])
    reports.value = reportsRes.data
    templates.value = templatesRes.data
  } catch (e) {
    console.error('Failed to load reports:', e)
  } finally {
    loading.value = false
  }
})

function createFromTemplate(tmpl: { name: string; description: string; report_type: string; config: ReportConfig }) {
  router.push({
    name: 'report-builder',
    query: { template: JSON.stringify(tmpl) },
  })
}

function onNewReport() {
  router.push({ name: 'report-builder' })
}

function onViewReport(report: SavedReport) {
  router.push({ name: 'report-view', params: { id: report.id } })
}

function onEditReport(report: SavedReport) {
  router.push({ name: 'report-edit', params: { id: report.id } })
}

async function onDeleteReport(report: SavedReport) {
  try {
    await deleteReport(report.id)
    reports.value = reports.value.filter(r => r.id !== report.id)
    Notify.create({ type: 'positive', message: t('reports.deleted') })
  } catch {
    Notify.create({ type: 'negative', message: 'Error al eliminar el reporte' })
  }
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('es-PE', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-lg">
      <div class="text-h5">{{ t('reports.title') }}</div>
      <q-space />
      <q-btn color="primary" icon="add" :label="t('reports.newReport')" no-caps @click="onNewReport" />
    </div>

    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <template v-else>
      <!-- Pre-built templates -->
      <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('reports.templates') }}</div>
      <div class="row q-col-gutter-md q-mb-lg">
        <div v-for="tmpl in templates" :key="tmpl.name" class="col-12 col-sm-6 col-md-4">
          <q-card flat bordered class="cursor-pointer template-card" @click="createFromTemplate(tmpl)">
            <q-card-section>
              <div class="row items-center q-gutter-sm q-mb-sm">
                <q-icon :name="reportTypeIcons[tmpl.report_type] || 'assessment'" :color="reportTypeColors[tmpl.report_type] || 'grey'" size="24px" />
                <div class="text-subtitle2 text-weight-bold">{{ tmpl.name }}</div>
              </div>
              <div class="text-caption text-grey">{{ tmpl.description }}</div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- My reports -->
      <div v-if="myReports.length > 0" class="q-mb-lg">
        <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('reports.myReports') }}</div>
        <div class="row q-col-gutter-md">
          <div v-for="report in myReports" :key="report.id" class="col-12 col-sm-6 col-md-4">
            <q-card flat bordered>
              <q-card-section>
                <div class="row items-center no-wrap q-mb-sm">
                  <q-icon :name="reportTypeIcons[report.report_type] || 'assessment'" :color="reportTypeColors[report.report_type] || 'grey'" size="22px" class="q-mr-sm" />
                  <div class="col ellipsis text-subtitle2 text-weight-bold">{{ report.name }}</div>
                  <q-btn flat round dense icon="more_vert" size="sm">
                    <q-menu>
                      <q-list dense>
                        <q-item clickable v-close-popup @click="onViewReport(report)">
                          <q-item-section side><q-icon name="play_arrow" size="18px" /></q-item-section>
                          <q-item-section>{{ t('reports.run') }}</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup @click="onEditReport(report)">
                          <q-item-section side><q-icon name="edit" size="18px" /></q-item-section>
                          <q-item-section>{{ t('common.edit') }}</q-item-section>
                        </q-item>
                        <q-separator />
                        <q-item clickable v-close-popup @click="onDeleteReport(report)">
                          <q-item-section side><q-icon name="delete" size="18px" color="negative" /></q-item-section>
                          <q-item-section class="text-negative">{{ t('common.delete') }}</q-item-section>
                        </q-item>
                      </q-list>
                    </q-menu>
                  </q-btn>
                </div>
                <div v-if="report.description" class="text-caption text-grey q-mb-sm ellipsis-2-lines">{{ report.description }}</div>
                <div class="row items-center q-gutter-xs">
                  <q-badge :color="reportTypeColors[report.report_type] || 'grey'" :label="t(`reports.types.${report.report_type}`)" />
                  <q-badge v-if="report.is_shared" outline color="primary" label="Compartido" />
                </div>
                <div class="text-caption text-grey-6 q-mt-sm">
                  {{ t('reports.lastRun') }}: {{ formatDate(report.last_run_at) }}
                </div>
              </q-card-section>
              <q-card-actions align="right">
                <q-btn flat dense no-caps color="primary" :label="t('reports.run')" icon="play_arrow" @click="onViewReport(report)" />
              </q-card-actions>
            </q-card>
          </div>
        </div>
      </div>

      <!-- Shared reports -->
      <div v-if="sharedReports.length > 0">
        <div class="text-subtitle1 text-weight-bold q-mb-sm">{{ t('reports.sharedReports') }}</div>
        <div class="row q-col-gutter-md">
          <div v-for="report in sharedReports" :key="report.id" class="col-12 col-sm-6 col-md-4">
            <q-card flat bordered>
              <q-card-section>
                <div class="row items-center no-wrap q-mb-sm">
                  <q-icon :name="reportTypeIcons[report.report_type] || 'assessment'" :color="reportTypeColors[report.report_type] || 'grey'" size="22px" class="q-mr-sm" />
                  <div class="col ellipsis text-subtitle2 text-weight-bold">{{ report.name }}</div>
                </div>
                <div v-if="report.description" class="text-caption text-grey q-mb-sm">{{ report.description }}</div>
                <div class="row items-center q-gutter-xs">
                  <q-badge :color="reportTypeColors[report.report_type] || 'grey'" :label="t(`reports.types.${report.report_type}`)" />
                  <q-badge outline color="grey" :label="report.user?.name" />
                </div>
              </q-card-section>
              <q-card-actions align="right">
                <q-btn flat dense no-caps color="primary" :label="t('reports.run')" icon="play_arrow" @click="onViewReport(report)" />
              </q-card-actions>
            </q-card>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="myReports.length === 0 && sharedReports.length === 0" class="text-center q-pa-xl text-grey-5">
        <q-icon name="assessment" size="64px" class="q-mb-md" />
        <div class="text-h6">{{ t('reports.noReports') }}</div>
        <div class="text-body2 q-mb-md">{{ t('reports.noReportsHint') }}</div>
        <q-btn color="primary" :label="t('reports.newReport')" icon="add" no-caps @click="onNewReport" />
      </div>
    </template>
  </q-page>
</template>

<style scoped>
.template-card {
  transition: all 0.2s;
}
.template-card:hover {
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-1px);
}
</style>
