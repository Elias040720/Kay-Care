import { defineStore } from 'pinia'
import { ref } from 'vue'
import { adminApi } from '@/api/admin'
import { ApiError } from '@/api/client'
import type { AdminUser } from '@/types'

/**
 * Sesión de administrador. La credencial real es una cookie HttpOnly que
 * JavaScript no puede leer; aquí solo se guarda el perfil en memoria.
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref<AdminUser | null>(null)
  const checked = ref(false)

  async function fetchUser(): Promise<AdminUser | null> {
    try {
      user.value = await adminApi.me()
    } catch (error) {
      if (!(error instanceof ApiError) || error.status !== 401) throw error
      user.value = null
    } finally {
      checked.value = true
    }
    return user.value
  }

  async function login(email: string, password: string) {
    user.value = await adminApi.login(email, password)
    checked.value = true
  }

  async function logout() {
    try {
      await adminApi.logout()
    } finally {
      user.value = null
    }
  }

  function reset() {
    user.value = null
    checked.value = false
  }

  return { user, checked, fetchUser, login, logout, reset }
})
