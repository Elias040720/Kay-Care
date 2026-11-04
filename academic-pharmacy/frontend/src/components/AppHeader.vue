<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Search, ShoppingBag } from 'lucide-vue-next'
import DemoBanner from './DemoBanner.vue'
import BrandMark from './BrandMark.vue'
import { useCartStore } from '@/stores/cart'

const cart = useCartStore()
const route = useRoute()
const router = useRouter()
const term = ref(typeof route.query.q === 'string' ? route.query.q : '')

watch(() => route.query.q, (q) => {
  term.value = typeof q === 'string' ? q : ''
})

function submitSearch() {
  const q = term.value.trim()
  router.push({ name: 'catalog', query: q ? { q } : {} })
}
</script>

<template>
  <header class="sticky top-0 z-30">
    <DemoBanner variant="bar" />
    <div class="border-b border-cream-200 bg-cream-50/90 backdrop-blur supports-[backdrop-filter]:bg-cream-50/75">
      <div class="container-page flex h-16 items-center gap-4">
        <RouterLink to="/" class="flex items-center gap-2.5" aria-label="Kay Care Farmacia, inicio">
          <BrandMark />
        </RouterLink>

        <nav class="ml-4 hidden items-center gap-1 text-sm md:flex" aria-label="Principal">
          <RouterLink to="/" class="rounded-full px-3 py-1.5 text-ink-500 transition-colors hover:text-ink-900" exact-active-class="bg-sage-50 text-sage-700">Inicio</RouterLink>
          <RouterLink to="/catalogo" class="rounded-full px-3 py-1.5 text-ink-500 transition-colors hover:text-ink-900" active-class="bg-sage-50 text-sage-700">Catálogo</RouterLink>
        </nav>

        <form class="ml-auto hidden max-w-sm flex-1 sm:block" role="search" @submit.prevent="submitSearch">
          <label for="header-search" class="sr-only">Buscar productos</label>
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" aria-hidden="true" />
            <input
              id="header-search"
              v-model="term"
              type="search"
              maxlength="80"
              placeholder="Buscar vitaminas, protector solar…"
              class="field rounded-full pl-9"
            />
          </div>
        </form>

        <RouterLink to="/catalogo" class="btn-icon ml-auto sm:hidden" aria-label="Buscar en el catálogo">
          <Search class="h-5 w-5" aria-hidden="true" />
        </RouterLink>

        <button
          type="button"
          class="relative inline-flex items-center gap-2 rounded-full bg-sage-600 px-4 py-2 text-sm font-medium text-cream-50 shadow-soft transition-transform hover:bg-sage-700 motion-safe:active:scale-95"
          :aria-label="`Abrir carrito, ${cart.count} artículos`"
          data-testid="open-cart"
          @click="cart.open()"
        >
          <ShoppingBag class="h-4 w-4" aria-hidden="true" />
          <span class="hidden sm:inline">Carrito</span>
          <span class="inline-flex min-w-[1.25rem] justify-center rounded-full bg-cream-50 px-1.5 text-xs font-semibold text-sage-700" aria-hidden="true">{{ cart.count }}</span>
        </button>
      </div>
    </div>
  </header>
</template>
