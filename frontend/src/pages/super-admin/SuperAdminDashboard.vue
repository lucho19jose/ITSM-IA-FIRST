<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getPlatformStats, type PlatformStats } from '@/api/admin'

const loading = ref(true)
const stats = ref<PlatformStats>({
  total_tenants: 0,
  active_tenants: 0,
  total_users: 0,
  total_tickets: 0,
  tenants_by_plan: {},
  recent_tenants: [],
})

onMounted(async () => {
  try {
    const res = await getPlatformStats()
    stats.value = res.data
  } finally {
    loading.value = false
  }
})

const planColors: Record<string, string> = {
  trial: 'warning',
  basic: 'info',
  professional: 'primary',
  enterprise: 'positive',
}
</script>

<template>
  <q-page padding>
    <div class="text-h5 q-mb-md">Dashboard de Plataforma</div>

    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <template v-else>
      <!-- Stats Cards -->
      <div class="row q-col-gutter-md q-mb-lg">
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered>
            <q-card-section class="row items-center no-wrap">
              <q-icon name="business" color="primary" size="40px" class="q-mr-md" />
              <div>
                <div class="text-h4 text-weight-bold">{{ stats.total_tenants }}</div>
                <div class="text-caption text-grey">Total Tenants</div>
              </div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered>
            <q-card-section class="row items-center no-wrap">
              <q-icon name="check_circle" color="positive" size="40px" class="q-mr-md" />
              <div>
                <div class="text-h4 text-weight-bold">{{ stats.active_tenants }}</div>
                <div class="text-caption text-grey">Tenants Activos</div>
              </div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered>
            <q-card-section class="row items-center no-wrap">
              <q-icon name="people" color="info" size="40px" class="q-mr-md" />
              <div>
                <div class="text-h4 text-weight-bold">{{ stats.total_users }}</div>
                <div class="text-caption text-grey">Total Usuarios</div>
              </div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered>
            <q-card-section class="row items-center no-wrap">
              <q-icon name="confirmation_number" color="accent" size="40px" class="q-mr-md" />
              <div>
                <div class="text-h4 text-weight-bold">{{ stats.total_tickets }}</div>
                <div class="text-caption text-grey">Total Tickets</div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <div class="row q-col-gutter-md">
        <!-- Tenants by Plan -->
        <div class="col-12 col-md-6">
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle1 text-weight-medium q-mb-md">Tenants por Plan</div>
              <q-list separator>
                <q-item v-for="(count, plan) in stats.tenants_by_plan" :key="plan">
                  <q-item-section>
                    <q-badge :color="planColors[plan as string] || 'grey'" class="q-pa-sm">
                      {{ (plan as string).toUpperCase() }}
                    </q-badge>
                  </q-item-section>
                  <q-item-section side>
                    <span class="text-h6">{{ count }}</span>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card-section>
          </q-card>
        </div>

        <!-- Recent Tenants -->
        <div class="col-12 col-md-6">
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle1 text-weight-medium q-mb-md">Tenants Recientes</div>
              <q-list separator>
                <q-item v-for="tenant in stats.recent_tenants" :key="tenant.id" clickable :to="`/super-admin/tenants`">
                  <q-item-section>
                    <q-item-label>{{ tenant.name }}</q-item-label>
                    <q-item-label caption>{{ new Date(tenant.created_at).toLocaleDateString('es-PE') }}</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <q-badge :color="planColors[tenant.plan] || 'grey'">{{ tenant.plan }}</q-badge>
                  </q-item-section>
                  <q-item-section side>
                    <q-icon :name="tenant.is_active ? 'check_circle' : 'cancel'" :color="tenant.is_active ? 'positive' : 'negative'" />
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </template>
  </q-page>
</template>
