<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { Notify } from 'quasar'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const form = ref({
  email: '',
  password: '',
})

const showPassword = ref(false)

async function onSubmit() {
  try {
    await auth.login(form.value.email, form.value.password)
    Notify.create({ type: 'positive', message: t('auth.loginSuccess') })
    if (auth.isEndUser && auth.tenant?.slug) {
      router.push(`/portal/${auth.tenant.slug}`)
      return
    }
    const redirect = (route.query.redirect as string) || '/dashboard'
    router.push(redirect)
  } catch (e: any) {
    if (e.response?.status === 401) {
      Notify.create({ type: 'negative', message: e.response.data.message })
    }
  }
}
</script>

<template>
  <div class="login-form">
    <div class="text-h6 text-weight-bold q-mb-xs">{{ t('auth.login') }}</div>
    <div class="text-body2 text-grey-7 q-mb-lg">
      Ingresa tus credenciales para continuar
    </div>

    <q-form @submit.prevent="onSubmit" class="q-gutter-md">
      <q-input
        v-model="form.email"
        :label="t('auth.email')"
        type="email"
        outlined
        rounded-md
        dense
        autocomplete="email"
        class="login-input"
        :rules="[val => !!val || 'Campo requerido', val => /.+@.+\..+/.test(val) || 'Email inválido']"
      >
        <template v-slot:prepend>
          <q-icon name="mail_outline" />
        </template>
      </q-input>

      <q-input
        v-model="form.password"
        :label="t('auth.password')"
        :type="showPassword ? 'text' : 'password'"
        outlined
        dense
        autocomplete="current-password"
        class="login-input"
        :rules="[val => !!val || 'Campo requerido']"
      >
        <template v-slot:prepend>
          <q-icon name="lock_outline" />
        </template>
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
        :label="t('auth.login')"
        class="full-width login-btn"
        unelevated
        size="md"
        no-caps
        :loading="auth.loading"
      />
    </q-form>

    <q-separator class="q-my-lg" />

    <div class="text-center text-body2 text-grey-7">
      {{ t('auth.noAccount') }}
      <router-link to="/register" class="text-primary text-weight-medium" style="text-decoration: none">
        {{ t('auth.register') }}
      </router-link>
    </div>
  </div>
</template>

<style scoped>
.login-form :deep(.q-field--outlined .q-field__control) {
  border-radius: 10px;
}

.login-btn {
  border-radius: 10px;
  height: 44px;
  font-weight: 600;
  letter-spacing: 0.01em;
}
</style>
