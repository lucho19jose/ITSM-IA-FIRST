<script setup lang="ts">
import { onMounted } from 'vue'
import { useTenantStore } from '@/stores/tenant'

const tenantStore = useTenantStore()

onMounted(() => {
  tenantStore.fetchTenantInfo()
})
</script>

<template>
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <q-page class="auth-page flex flex-center">
        <div class="auth-shell column items-center q-pa-md">
          <div class="auth-brand column items-center q-mb-lg">
            <img src="/favicon.svg" alt="Chuymadesk" class="auth-logo" />
            <div class="text-h5 text-weight-bold q-mt-sm auth-brand-name">Chuymadesk</div>
            <div
              v-if="tenantStore.tenantName"
              class="text-caption text-grey-7 q-mt-xs"
            >
              {{ tenantStore.tenantName }}
            </div>
          </div>

          <q-card flat bordered class="auth-card full-width">
            <q-card-section class="q-pa-lg">
              <router-view />
            </q-card-section>
          </q-card>

          <div class="text-caption text-grey-6 q-mt-lg">
            © {{ new Date().getFullYear() }} Chuymadesk
          </div>
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<style scoped>
.auth-page {
  background:
    radial-gradient(1200px 600px at 10% -10%, rgba(25, 118, 210, 0.10), transparent 60%),
    radial-gradient(900px 500px at 110% 110%, rgba(38, 166, 154, 0.10), transparent 60%),
    #f6f8fb;
  min-height: 100vh;
}

.auth-shell {
  width: 100%;
  max-width: 420px;
}

.auth-logo {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  box-shadow: 0 6px 16px rgba(25, 118, 210, 0.25);
}

.auth-brand-name {
  letter-spacing: -0.02em;
  color: #1f2937;
}

.auth-card {
  border-radius: 16px;
  border-color: rgba(15, 23, 42, 0.08);
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.04),
    0 12px 32px rgba(15, 23, 42, 0.06);
  background: #ffffff;
}
</style>
