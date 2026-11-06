<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Boxes, ClipboardList, LayoutDashboard, LogOut, Menu, Package, Store, X } from 'lucide-vue-next'
import BrandMark from '@/components/BrandMark.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()
const mobileOpen = ref(false)

const links = [
  { to: { name: 'admin-dashboard' }, label: 'Panel', icon: LayoutDashboard, exact: true },
  { to: { name: 'admin-products' }, label: 'Productos', icon: Package, exact: false },
  { to: { name: 'admin-stock' }, label: 'Stock', icon: Boxes, exact: false },
  { to: { name: 'admin-orders' }, label: 'Pedidos', icon: ClipboardList, exact: false },
]

async function logout() {
  await auth.logout().catch(() => undefined)
  toast.info('Sesión cerrada')
  router.replace({ name: 'admin-login' })
}
</script>

<template>
  <div class="min-h-screen bg-cream-100 lg:grid lg:grid-cols-[248px_1fr]">
    <aside
      class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-ink-900 text-cream-100 transition-transform duration-300 lg:static lg:w-auto lg:translate-x-0"
      :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
      aria-label="Navegación de administración"
    >
      <div class="flex h-16 items-center justify-between px-5">
        <BrandMark tone="light" />
        <button type="button" class="btn-icon text-cream-100 hover:bg-white/10 lg:hidden" aria-label="Cerrar menú" @click="mobileOpen = false">
          <X class="h-5 w-5" aria-hidden="true" />
        </button>
      </div>
      <nav class="flex-1 space-y-1 px-3 py-4">
        <RouterLink
          v-for="link in links"
          :key="link.label"
          :to="link.to"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-cream-200/80 transition-colors hover:bg-white/5 hover:text-white"
          :active-class="link.exact ? '' : 'bg-white/10 text-white'"
          :exact-active-class="'bg-white/10 text-white'"
          @click="mobileOpen = false"
        >
          <component :is="link.icon" class="h-4 w-4" aria-hidden="true" /> {{ link.label }}
        </RouterLink>
      </nav>
      <div class="border-t border-white/10 p-4 text-sm">
        <p class="truncate font-medium text-white">{{ auth.user?.name }}</p>
        <p class="truncate text-xs text-cream-200/60">{{ auth.user?.email }}</p>
        <div class="mt-3 grid gap-2">
          <RouterLink to="/" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-cream-200/80 hover:bg-white/5"><Store class="h-4 w-4" aria-hidden="true" /> Ver tienda</RouterLink>
          <button type="button" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs text-cream-200/80 hover:bg-white/5" data-testid="admin-logout" @click="logout">
            <LogOut class="h-4 w-4" aria-hidden="true" /> Cerrar sesión
          </button>
        </div>
      </div>
    </aside>
    <div v-if="mobileOpen" class="fixed inset-0 z-30 bg-ink-900/40 lg:hidden" aria-hidden="true" @click="mobileOpen = false" />

    <div class="min-w-0">
      <header class="sticky top-0 z-20 border-b border-cream-200 bg-cream-50/90 backdrop-blur">
        <div class="flex h-14 items-center gap-3 px-4 sm:px-6">
          <button type="button" class="btn-icon lg:hidden" aria-label="Abrir menú" @click="mobileOpen = true"><Menu class="h-5 w-5" aria-hidden="true" /></button>
          <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-honey-600">Proyecto académico — datos ficticios</p>
        </div>
      </header>
      <div class="p-4 sm:p-6 lg:p-8">
        <RouterView />
      </div>
    </div>
  </div>
</template>
