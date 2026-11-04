<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LockKeyhole } from 'lucide-vue-next'
import BrandMark from '@/components/BrandMark.vue'
import { useAuthStore } from '@/stores/auth'
import { ApiError } from '@/api/client'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const submitting = ref(false)

function safeRedirect(): string {
  const target = typeof route.query.redirect === 'string' ? route.query.redirect : ''
  // Solo rutas internas del panel (evita open redirects).
  return /^\/admin(\/[\w\-/]*)?$/.test(target) ? target : '/admin'
}

async function submit() {
  error.value = null
  if (!email.value || !password.value) {
    error.value = 'Ingresa correo y contraseña.'
    return
  }
  submitting.value = true
  try {
    await auth.login(email.value.trim(), password.value)
    password.value = ''
    await router.replace(safeRedirect())
  } catch (e) {
    if (e instanceof ApiError) {
      error.value = e.status === 429 ? 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.' : (e.firstFieldError('email') ?? e.message)
    } else {
      error.value = 'No se pudo iniciar sesión.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-cream-100 via-cream-50 to-sage-50 px-4 py-12">
    <div class="w-full max-w-sm">
      <div class="mb-8 flex justify-center"><BrandMark /></div>
      <form class="card space-y-5 p-7" novalidate data-testid="admin-login-form" @submit.prevent="submit">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-full bg-sage-50 text-sage-600"><LockKeyhole class="h-5 w-5" aria-hidden="true" /></div>
          <div>
            <h1 class="text-xl">Administración</h1>
            <p class="text-xs text-ink-500">Acceso exclusivo para la cuenta demo. Sin registro público.</p>
          </div>
        </div>
        <div>
          <label for="admin-email" class="field-label">Correo</label>
          <input id="admin-email" v-model="email" type="email" autocomplete="username" class="field" maxlength="190" required data-testid="admin-email" />
        </div>
        <div>
          <label for="admin-password" class="field-label">Contraseña</label>
          <input id="admin-password" v-model="password" type="password" autocomplete="current-password" class="field" maxlength="200" required data-testid="admin-password" />
        </div>
        <p v-if="error" class="rounded-xl bg-clay-100 px-3 py-2 text-sm text-clay-700" role="alert">{{ error }}</p>
        <button type="submit" class="btn-primary w-full" :disabled="submitting" data-testid="admin-submit">
          {{ submitting ? 'Verificando…' : 'Iniciar sesión' }}
        </button>
        <RouterLink to="/" class="block text-center text-xs text-ink-500 hover:text-sage-700">← Volver a la tienda</RouterLink>
      </form>
      <p class="mt-6 text-center text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-400">Proyecto académico — tienda demostrativa</p>
    </div>
  </div>
</template>
