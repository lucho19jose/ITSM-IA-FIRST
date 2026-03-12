<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePortalStore } from '@/stores/portal'
import { Notify } from 'quasar'

const route = useRoute()
const router = useRouter()
const portal = usePortalStore()
const tenantSlug = route.params.tenantSlug as string

const form = ref({ email: '', password: '' })
const showPassword = ref(false)

async function onSubmit() {
  try {
    await portal.login(tenantSlug, form.value.email, form.value.password)
    Notify.create({ type: 'positive', message: 'Sesion iniciada correctamente' })
    const redirect = (route.query.redirect as string) || `/portal/${tenantSlug}`
    router.push(redirect)
  } catch (e: any) {
    if (e.response?.status === 401) {
      Notify.create({ type: 'negative', message: e.response.data.message || 'Credenciales invalidas' })
    }
  }
}
</script>

<template>
  <q-page class="flex flex-center portal-auth-page">
    <div class="portal-auth-card">
      <div class="text-center q-mb-lg">
        <img v-if="portal.tenant?.logo_url" :src="portal.tenant.logo_url" alt="Logo" class="portal-auth-logo q-mb-sm" />
        <div class="text-h5 text-weight-bold">{{ portal.tenant?.name }}</div>
        <div class="text-grey-7 q-mt-xs">Inicia sesion en tu cuenta</div>
      </div>

      <q-form @submit.prevent="onSubmit" class="q-gutter-md">
        <q-input
          v-model="form.email"
          label="Correo electronico"
          type="email"
          outlined
          :rules="[val => !!val || 'Campo requerido', val => /.+@.+\..+/.test(val) || 'Email invalido']"
        />
        <q-input
          v-model="form.password"
          label="Contrasena"
          :type="showPassword ? 'text' : 'password'"
          outlined
          :rules="[val => !!val || 'Campo requerido']"
        >
          <template v-slot:append>
            <q-icon
              :name="showPassword ? 'visibility_off' : 'visibility'"
              class="cursor-pointer"
              @click="showPassword = !showPassword"
            />
          </template>
        </q-input>
        <q-btn
          type="submit"
          color="primary"
          label="Iniciar Sesion"
          class="full-width"
          size="lg"
          :loading="portal.loading"
        />
      </q-form>

      <div class="text-center q-mt-lg">
        ¿No tienes cuenta?
        <router-link :to="`/portal/${tenantSlug}/register`" class="text-primary">Registrarse</router-link>
      </div>

      <div class="text-center q-mt-sm">
        <router-link :to="`/portal/${tenantSlug}`" class="text-grey-7" style="font-size: 13px;">
          Volver al portal
        </router-link>
      </div>
    </div>
  </q-page>
</template>

<style scoped>
.portal-auth-page {
  background: #f5f5f5;
  min-height: calc(100vh - 50px);
}
.portal-auth-card {
  background: white;
  border-radius: 12px;
  padding: 40px 32px;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}
.portal-auth-logo {
  max-width: 64px;
  max-height: 64px;
  object-fit: contain;
}
.body--dark .portal-auth-page {
  background: #121212;
}
.body--dark .portal-auth-card {
  background: #1e1e1e;
}
</style>
