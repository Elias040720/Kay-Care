import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/lib/api'
import { AppError } from '@/lib/api/errors'
import type { User } from '@/types'
import { queryClient } from '@/app/query'
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const initialized = ref(false)
  const expired = ref(false)
  const connectionError = ref('')
  let pending: Promise<void> | null = null
  async function restore() {
    if (initialized.value) return
    if (pending) return pending
    pending = (async () => {
      try {
        user.value = await api.me()
        connectionError.value = ''
      } catch (e) {
        if (!(e instanceof AppError && [401, 419].includes(e.status)))
          connectionError.value =
            'No pudimos comprobar la sesión. Verifica la conexión al servidor.'
      } finally {
        initialized.value = true
        pending = null
      }
    })()
    return pending
  }
  async function login(input: { email: string; password: string }) {
    user.value = await api.login(input)
    initialized.value = true
    expired.value = false
    connectionError.value = ''
  }
  async function logout() {
    await api.logout()
    clear()
  }
  function clear(sessionExpired = false) {
    user.value = null
    expired.value = sessionExpired
    queryClient.clear()
  }
  return { user, initialized, expired, connectionError, restore, login, logout, clear }
})
