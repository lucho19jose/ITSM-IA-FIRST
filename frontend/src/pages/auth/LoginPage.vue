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
    // End-users go to the portal
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
  <div>
    <div class="text-h6 q-mb-md">{{ t('auth.login') }}</div>
    <q-form @submit.prevent="onSubmit" class="q-gutter-md">
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
        :label="t('auth.login')"
        class="full-width"
        size="lg"
        :loading="auth.loading"
      />
    </q-form>
    <div class="text-center q-mt-md">
      {{ t('auth.noAccount') }}
      <router-link to="/register" class="text-primary">{{ t('auth.register') }}</router-link>
    </div>
  </div>
</template>
