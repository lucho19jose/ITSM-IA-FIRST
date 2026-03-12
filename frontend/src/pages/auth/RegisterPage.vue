<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { Notify } from 'quasar'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  company_name: '',
  ruc: '',
})

const showPassword = ref(false)

async function onSubmit() {
  try {
    await auth.register(form.value)
    Notify.create({ type: 'positive', message: t('auth.registerSuccess') })
    router.push('/dashboard')
  } catch {
    // Error handled by interceptor
  }
}
</script>

<template>
  <div>
    <div class="text-h6 q-mb-md">{{ t('auth.register') }}</div>
    <q-form @submit.prevent="onSubmit" class="q-gutter-md">
      <q-input
        v-model="form.company_name"
        :label="t('auth.companyName')"
        outlined
        :rules="[val => !!val || 'Campo requerido']"
      />
      <q-input
        v-model="form.ruc"
        :label="t('auth.ruc')"
        outlined
        mask="###########"
        hint="11 dígitos"
      />
      <q-input
        v-model="form.name"
        :label="t('auth.name')"
        outlined
        :rules="[val => !!val || 'Campo requerido']"
      />
      <q-input
        v-model="form.email"
        :label="t('auth.email')"
        type="email"
        outlined
        :rules="[val => !!val || 'Campo requerido', val => /.+@.+\..+/.test(val) || 'Email inválido']"
      />
      <q-input
        v-model="form.password"
        :label="t('auth.password')"
        :type="showPassword ? 'text' : 'password'"
        outlined
        :rules="[val => !!val || 'Campo requerido', val => val.length >= 8 || 'Mínimo 8 caracteres']"
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
        :label="t('auth.confirmPassword')"
        :type="showPassword ? 'text' : 'password'"
        outlined
        :rules="[val => val === form.password || 'Las contraseñas no coinciden']"
      />
      <q-btn
        type="submit"
        color="primary"
        :label="t('auth.register')"
        class="full-width"
        size="lg"
        :loading="auth.loading"
      />
    </q-form>
    <div class="text-center q-mt-md">
      {{ t('auth.hasAccount') }}
      <router-link to="/login" class="text-primary">{{ t('auth.login') }}</router-link>
    </div>
  </div>
</template>
