<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePortalStore } from '@/stores/portal'
import { Notify } from 'quasar'

const route = useRoute()
const router = useRouter()
const portal = usePortalStore()
const tenantSlug = route.params.tenantSlug as string

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})
const showPassword = ref(false)

async function onSubmit() {
  try {
    await portal.register(tenantSlug, form.value)
    Notify.create({ type: 'positive', message: 'Cuenta creada correctamente' })
    router.push(`/portal/${tenantSlug}`)
  } catch {
    // Validation errors handled by API interceptor
  }
}
</script>

<template>
  <q-page class="flex flex-center portal-auth-page">
    <div class="portal-auth-card">
      <div class="text-center q-mb-lg">
        <img v-if="portal.tenant?.logo_url" :src="portal.tenant.logo_url" alt="Logo" class="portal-auth-logo q-mb-sm" />
        <div class="text-h5 text-weight-bold">{{ portal.tenant?.name }}</div>
        <div class="text-grey-7 q-mt-xs">Crea tu cuenta de usuario</div>
      </div>

      <q-form @submit.prevent="onSubmit" class="q-gutter-md">
        <q-input
          v-model="form.name"
          label="Nombre completo"
          outlined
          :rules="[val => !!val || 'Campo requerido']"
        />
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
          :rules="[val => !!val || 'Campo requerido', val => val.length >= 8 || 'Minimo 8 caracteres']"
        >
          <template v-slot:append>
            <q-icon
              :name="showPassword ? 'visibility_off' : 'visibility'"
              class="cursor-pointer"
              @click="showPassword = !showPassword"
            />
          </template>
        </q-input>
        <q-input
          v-model="form.password_confirmation"
          label="Confirmar contrasena"
          :type="showPassword ? 'text' : 'password'"
          outlined
          :rules="[val => val === form.password || 'Las contrasenas no coinciden']"
        />
        <q-btn
          type="submit"
          color="primary"
          label="Registrarse"
          class="full-width"
          size="lg"
          :loading="portal.loading"
        />
      </q-form>

      <div class="text-center q-mt-lg">
        ¿Ya tienes cuenta?
        <router-link :to="`/portal/${tenantSlug}/login`" class="text-primary">Iniciar Sesion</router-link>
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
