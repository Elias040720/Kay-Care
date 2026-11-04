<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  HeartPulse,
  LayoutDashboard,
  UsersRound,
  ClipboardPlus,
  Files,
  LogOut,
  Menu,
  X,
  ChevronRight,
  ShieldCheck,
  Leaf,
} from '@lucide/vue'
import { useAuthStore } from '@/features/auth/store'
import { isDemo } from '@/lib/api'
import { isDemonstrationData, environmentMessage } from '@/app/environment'
import { initials } from '@/lib/format'
import { useToast } from '@/composables/useToast'
import { confirmAllUnsaved } from '@/composables/useUnsavedChanges'
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { notify } = useToast()
const menuOpen = ref(false)
const mobile = ref(false)
const sidebar = ref<HTMLElement>()
const menuButton = ref<HTMLButtonElement>()
const media = window.matchMedia('(max-width: 800px)')
function syncMobile() {
  mobile.value = media.matches
  if (!mobile.value) menuOpen.value = false
}
onMounted(() => {
  syncMobile()
  media.addEventListener('change', syncMobile)
})
onBeforeUnmount(() => media.removeEventListener('change', syncMobile))
watch(menuOpen, async (open) => {
  await nextTick()
  if (open) sidebar.value?.querySelector<HTMLElement>('a')?.focus()
  else if (mobile.value) menuButton.value?.focus()
})
function menuKeys(event: KeyboardEvent) {
  if (!mobile.value || !menuOpen.value) return
  if (event.key === 'Escape') {
    event.preventDefault()
    menuOpen.value = false
  }
  if (event.key === 'Tab') {
    const targets = Array.from(
      sidebar.value?.querySelectorAll<HTMLElement>('a, button') || [],
    ).filter((el) => el.offsetParent !== null)
    const first = targets[0]
    const last = targets.at(-1)
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last?.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first?.focus()
    }
  }
}
const loggingOut = ref(false)
const links = [
  { to: '/', label: 'Inicio', icon: LayoutDashboard },
  { to: '/pacientes', label: 'Pacientes', icon: UsersRound },
  { to: '/atenciones/nueva', label: 'Nueva atención', icon: ClipboardPlus },
  { to: '/documentos', label: 'Documentación', icon: Files },
]
async function logout() {
  if (!confirmAllUnsaved()) return
  loggingOut.value = true
  try {
    await auth.logout()
    await router.replace('/acceso')
  } catch {
    notify('No pudimos cerrar la sesión. Inténtalo nuevamente.', 'error')
  } finally {
    loggingOut.value = false
  }
}
</script>
<template>
  <a class="skip-link" href="#contenido">Ir al contenido</a>
  <div class="app-shell">
    <aside
      ref="sidebar"
      :class="['sidebar', { 'is-open': menuOpen }]"
      aria-label="Navegación principal"
      :inert="mobile && !menuOpen"
      :aria-hidden="mobile && !menuOpen ? true : undefined"
      :role="mobile && menuOpen ? 'dialog' : undefined"
      :aria-modal="mobile && menuOpen ? true : undefined"
      @keydown="menuKeys"
    >
      <RouterLink to="/" class="brand" aria-label="Kay Care, inicio" @click="menuOpen = false"
        ><span class="brand-mark"><HeartPulse :size="25" :stroke-width="1.8" /></span
        ><span
          >kay<span class="brand-light">care</span><small>UN ESPACIO PARA CUIDAR</small></span
        ></RouterLink
      >
      <button class="icon-btn mobile-close" aria-label="Cerrar menú" @click="menuOpen = false">
        <X />
      </button>
      <div class="workspace-label">MI ESPACIO DE TRABAJO</div>
      <nav>
        <RouterLink
          v-for="link in links"
          :key="link.to"
          :to="link.to"
          :class="[
            'nav-link',
            { active: link.to === '/' ? route.path === '/' : route.path.startsWith(link.to) },
          ]"
          @click="menuOpen = false"
          ><component :is="link.icon" :size="20" :stroke-width="1.65" /><span>{{ link.label }}</span
          ><span v-if="link.to === '/atenciones/nueva'" class="nav-plus" aria-hidden="true"
            >+</span
          ></RouterLink
        >
      </nav>
      <div class="sidebar-note">
        <span class="leaf-icon"><Leaf :size="20" /></span>
        <p>Tu tiempo, para cuidar.</p>
        <span>El registro acompaña.<br />Tú haces la diferencia.</span>
      </div>
      <div class="sidebar-bottom">
        <span class="status-dot" /><span>{{
          isDemo ? 'Entorno de demostración' : 'API v1 · Sesión protegida'
        }}</span
        ><small>Kay Care · v1.0</small>
      </div>
    </aside>
    <button
      v-if="menuOpen"
      class="menu-backdrop"
      aria-label="Cerrar navegación"
      @click="menuOpen = false"
    />
    <div class="workspace" :inert="mobile && menuOpen">
      <header class="topbar">
        <div class="breadcrumb">
          <button
            ref="menuButton"
            class="icon-btn menu-toggle"
            aria-label="Abrir menú"
            :aria-expanded="menuOpen"
            @click="menuOpen = !menuOpen"
          >
            <Menu :size="22" /></button
          ><span>Espacio clínico</span><ChevronRight :size="14" /><strong>{{
            route.meta.title
          }}</strong>
        </div>
        <div class="topbar-user">
          <span v-if="isDemonstrationData" class="badge badge-demo">DEMO</span
          ><span v-else class="secure-label"><ShieldCheck :size="15" /> Sesión activa</span
          ><span class="user-avatar">{{ initials(auth.user?.name || 'Enfermera') }}</span>
          <div class="user-info">
            <strong>{{ auth.user?.name }}</strong
            ><span>{{
              auth.user?.role === 'admin' ? 'Administración' : 'Profesional de enfermería'
            }}</span>
          </div>
          <button
            class="icon-btn logout-btn"
            :disabled="loggingOut"
            aria-label="Cerrar sesión"
            title="Cerrar sesión"
            @click="logout"
          >
            <LogOut :size="18" />
          </button>
        </div>
      </header>
      <div v-if="isDemonstrationData" class="demo-ribbon">
        <span class="status-dot" /><span>{{ environmentMessage }}</span>
      </div>
      <main id="contenido" class="main-content">
        <RouterView v-slot="{ Component }"
          ><Transition name="page" mode="out-in"
            ><component :is="Component" :key="route.fullPath" /></Transition
        ></RouterView>
      </main>
      <footer class="app-footer">
        <span>Kay Care · Diseñado para acompañar tu cuidado</span
        ><span>Privacidad en cada registro <ShieldCheck :size="14" /></span>
      </footer>
    </div>
  </div>
</template>
