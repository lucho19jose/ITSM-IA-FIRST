import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { login as apiLogin, register as apiRegister, logout as apiLogout, getMe } from '@/api/auth'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => !!user.value)
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isAgent = computed(() => user.value?.role === 'agent')
  const isEndUser = computed(() => user.value?.role === 'end_user')
  const tenant = computed(() => user.value?.tenant)

  async function login(email: string, password: string) {
    loading.value = true
    try {
      const res = await apiLogin({ email, password })
      localStorage.setItem('token', res.token)
      user.value = res.user
      return res
    } finally {
      loading.value = false
    }
  }

  async function register(data: {
    name: string
    email: string
    password: string
    password_confirmation: string
    company_name: string
    ruc?: string
  }) {
    loading.value = true
    try {
      const res = await apiRegister(data)
      localStorage.setItem('token', res.token)
      user.value = res.user
      return res
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await apiLogout()
    } finally {
      localStorage.removeItem('token')
      user.value = null
    }
  }

  async function fetchUser() {
    if (!localStorage.getItem('token')) return
    try {
      const res = await getMe()
      user.value = res.user
    } catch {
      localStorage.removeItem('token')
      user.value = null
    }
  }

  return {
    user, loading,
    isAuthenticated, isSuperAdmin, isAdmin, isAgent, isEndUser, tenant,
    login, register, logout, fetchUser,
  }
})
