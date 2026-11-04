<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { HeartPulse, ArrowRight, ShieldCheck, Check, Eye, EyeOff } from '@lucide/vue'
import { z } from 'zod'
import { useAuthStore } from './store'
import { isDemo } from '@/lib/api'
import { normalizeError } from '@/lib/api/errors'
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const values = reactive({ email: '', password: '' })
const errors = ref<Record<string, string>>({})
const error = ref('')
const busy = ref(false)
const showPassword = ref(false)
const schema = z.object({
  email: z.email('Ingresa un correo válido.'),
  password: z.string().min(1, 'Ingresa tu contraseña.'),
})
async function submit(demo = false) {
  errors.value = {}
  error.value = ''
  const data = demo ? { email: 'enfermera@example.test', password: 'demostracion' } : values
  const parsed = schema.safeParse(data)
  if (!parsed.success) {
    parsed.error.issues.forEach((i) => {
      errors.value[String(i.path[0])] = i.message
    })
    return
  }
  busy.value = true
  try {
    await auth.login(parsed.data)
    const redirect = String(route.query.redirect || '/')
    await router.replace(
      redirect.startsWith('/') && !redirect.startsWith('//') && !redirect.startsWith('/acceso')
        ? redirect
        : '/',
    )
  } catch (e) {
    error.value = normalizeError(e).message
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <main class="login-page">
    <section class="login-story">
      <a href="/" class="brand"
        ><span class="brand-mark"><HeartPulse :size="27" /></span
        ><span>kay<span class="brand-light">care</span></span></a
      >
      <div class="story-content">
        <p class="eyebrow">CERCA DE QUIENES CUIDAN</p>
        <h1>
          Menos tiempo<br />registrando.<br /><em>Más tiempo<br />cuidando.</em>
        </h1>
        <p>Un espacio sereno para tus pacientes, sus controles y cada pequeño gran avance.</p>
        <div class="story-checks">
          <span><Check :size="16" /> Atenciones CRED, paso a paso</span
          ><span><Check :size="16" /> Actividades HIS bajo tu revisión</span
          ><span><Check :size="16" /> La información que necesitas, a mano</span>
        </div>
      </div>
      <span class="story-footer">Hecho para el cuidado en el primer nivel de atención.</span>
      <div class="story-orbit" aria-hidden="true">
        <div />
        <div />
        <HeartPulse :size="150" :stroke-width="0.75" />
      </div>
    </section>
    <section class="login-form-area">
      <div class="login-form">
        <span v-if="isDemo" class="badge badge-demo">ENTORNO DEMO</span>
        <p v-else class="eyebrow">BIENVENIDA A TU ESPACIO</p>
        <h2>{{ isDemo ? 'Conoce una forma más simple de cuidar.' : 'Qué bueno tenerte aquí.' }}</h2>
        <p class="login-intro">
          {{
            isDemo
              ? 'Explora Kay Care con pacientes ficticios. Puedes registrar, editar y recorrer una atención completa.'
              : 'Ingresa a tu cuenta para continuar con tus atenciones.'
          }}
        </p>
        <div v-if="auth.expired || route.query.expired" class="notice notice-warning" role="alert">
          Tu sesión ha expirado. Vuelve a ingresar para continuar. Los cambios sin guardar pueden
          requerir revisión.
        </div>
        <div v-if="auth.connectionError" class="notice notice-warning" role="alert">
          {{ auth.connectionError }}
        </div>
        <div v-if="error" class="notice notice-error" role="alert">{{ error }}</div>
        <template v-if="isDemo"
          ><div class="demo-login-card">
            <div class="avatar">ED</div>
            <div>
              <strong>Enfermera de demostración</strong>
              <p>Acceso de prueba · sin credenciales reales</p>
            </div>
          </div>
          <button class="btn btn-primary login-submit" :disabled="busy" @click="submit(true)">
            {{ busy ? 'Preparando tu espacio…' : 'Explorar demostración' }}<ArrowRight :size="18" />
          </button>
          <p class="demo-disclaimer">
            Este acceso no representa autenticación segura. No ingreses datos de pacientes reales.
            Los registros se reinician al recargar la página.
          </p></template
        >
        <form v-else novalidate @submit.prevent="submit()">
          <div class="field">
            <label for="email">Correo electrónico</label
            ><input
              id="email"
              v-model="values.email"
              type="email"
              autocomplete="username"
              placeholder="nombre@establecimiento.pe"
              :aria-invalid="!!errors.email"
              aria-describedby="email-error"
            /><span id="email-error" class="field-error">{{ errors.email }}</span>
          </div>
          <div class="field">
            <label for="password">Contraseña</label>
            <div class="password-input">
              <input
                id="password"
                v-model="values.password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                :aria-invalid="!!errors.password"
                aria-describedby="password-error"
              /><button
                type="button"
                class="icon-btn"
                :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                @click="showPassword = !showPassword"
              >
                <EyeOff v-if="showPassword" :size="18" /><Eye v-else :size="18" />
              </button>
            </div>
            <span id="password-error" class="field-error">{{ errors.password }}</span>
          </div>
          <button class="btn btn-primary login-submit" :disabled="busy" type="submit">
            {{ busy ? 'Ingresando…' : 'Ingresar a Kay Care' }}<ArrowRight :size="18" />
          </button>
          <p class="demo-disclaimer">
            Si necesitas acceso, comunícate con la administración de tu establecimiento.
          </p>
        </form>
        <div class="login-privacy">
          <ShieldCheck :size="17" /><span>Tu atención merece un espacio de confianza.</span>
        </div>
      </div>
      <p class="login-copyright">
        Kay Care © {{ new Date().getFullYear() }} · Cuidado que conecta.
      </p>
    </section>
  </main>
</template>
