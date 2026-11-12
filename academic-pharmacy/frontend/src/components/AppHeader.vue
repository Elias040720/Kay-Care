<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { BadgePercent, LayoutGrid, Menu, ShieldCheck, ShoppingCart, Store, X } from 'lucide-vue-next'
import DemoBanner from './DemoBanner.vue'
import BrandMark from './BrandMark.vue'
import SearchBox from './SearchBox.vue'
import { useCartStore } from '@/stores/cart'
import { useCatalogStore } from '@/stores/catalog'
import { categoryMeta } from '@/utils/categories'

const cart = useCartStore()
const catalog = useCatalogStore()
const route = useRoute()
const menuOpen = ref(false)
const bump = ref(false)

onMounted(() => catalog.loadCategories())

// Microinteracción: el contador del carrito «salta» al añadir productos.
watch(
  () => cart.count,
  (next, prev) => {
    if (next > prev) {
      bump.value = false
      requestAnimationFrame(() => (bump.value = true))
    }
  },
)

watch(() => route.fullPath, () => (menuOpen.value = false))

function isActiveCategory(slug: string) {
  return route.name === 'catalog' && route.query.categoria === slug
}
</script>

<template>
  <header class="sticky top-0 z-30 bg-white/95 shadow-[0_1px_0_rgba(24,32,29,0.06)] backdrop-blur supports-[backdrop-filter]:bg-white/85">
    <DemoBanner variant="bar" />

    <div class="container-page flex items-center gap-3 py-3 lg:gap-6">
      <button
        type="button"
        class="btn-icon lg:hidden"
        :aria-expanded="menuOpen"
        aria-controls="menu-movil"
        @click="menuOpen = true"
      >
        <Menu class="h-6 w-6" aria-hidden="true" />
        <span class="sr-only">Abrir menú</span>
      </button>

      <RouterLink to="/" class="shrink-0" aria-label="Kay Care Farmacia, inicio">
        <BrandMark />
      </RouterLink>

      <div class="hidden flex-1 md:block">
        <SearchBox input-id="header-search" />
      </div>

      <div class="ml-auto flex items-center gap-1 md:ml-0">
        <RouterLink
          :to="{ name: 'catalog', query: { ofertas: '1' } }"
          class="hidden items-center gap-1.5 rounded-full px-3 py-2 text-sm font-semibold text-promo-700 transition-colors hover:bg-promo-50 xl:inline-flex"
        >
          <BadgePercent class="h-5 w-5" aria-hidden="true" /> Ofertas
        </RouterLink>
        <button
          type="button"
          class="relative inline-flex items-center gap-2 rounded-full bg-clinic-600 px-4 py-2.5 text-sm font-semibold text-white shadow-soft transition-[transform,background-color] hover:bg-clinic-700 motion-safe:active:scale-95"
          data-testid="open-cart"
          @click="cart.open()"
        >
          <ShoppingCart class="h-5 w-5" aria-hidden="true" />
          <span class="hidden sm:inline">Mi carrito</span>
          <span
            class="inline-flex min-w-[1.4rem] justify-center rounded-full bg-white px-1.5 text-xs font-bold text-clinic-700"
            :class="bump ? 'motion-safe:animate-bump' : ''"
            @animationend="bump = false"
          >{{ cart.count }}</span>
          <span class="sr-only">{{ cart.count === 1 ? 'artículo' : 'artículos' }} en el carrito</span>
        </button>
      </div>
    </div>

    <!-- Buscador a ancho completo en móvil -->
    <div class="container-page pb-3 md:hidden">
      <SearchBox input-id="header-search-mobile" compact />
    </div>

    <!-- Menú de categorías (escritorio) -->
    <nav class="hidden border-t border-sage-50 lg:block" aria-label="Categorías">
      <div class="container-page flex items-center gap-1 overflow-x-auto py-1.5 text-sm">
        <RouterLink
          to="/catalogo"
          class="inline-flex shrink-0 items-center gap-2 rounded-full px-3 py-2 font-semibold text-ink-700 transition-colors hover:bg-clinic-50 hover:text-clinic-700"
          :class="route.name === 'catalog' && !route.query.categoria ? 'bg-clinic-50 text-clinic-700' : ''"
        >
          <LayoutGrid class="h-4 w-4" aria-hidden="true" /> Todo el catálogo
        </RouterLink>
        <RouterLink
          v-for="category in catalog.categories"
          :key="category.id"
          :to="{ name: 'catalog', query: { categoria: category.slug } }"
          class="inline-flex shrink-0 items-center gap-2 rounded-full px-3 py-2 text-ink-700 transition-colors hover:bg-clinic-50 hover:text-clinic-700"
          :class="isActiveCategory(category.slug) ? 'bg-clinic-50 font-semibold text-clinic-700' : ''"
          :aria-current="isActiveCategory(category.slug) ? 'page' : undefined"
        >
          <component :is="categoryMeta(category.slug).icon" class="h-4 w-4" aria-hidden="true" />
          {{ category.name }}
        </RouterLink>
      </div>
    </nav>

    <!-- Menú lateral móvil -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="menuOpen" class="fixed inset-0 z-50 bg-ink-900/40 lg:hidden" aria-hidden="true" @click="menuOpen = false" />
      </Transition>
      <Transition name="drawer-left">
        <aside
          v-if="menuOpen"
          id="menu-movil"
          class="fixed inset-y-0 left-0 z-50 flex w-[86%] max-w-sm flex-col bg-white shadow-lift lg:hidden"
          role="dialog"
          aria-modal="true"
          aria-label="Menú"
          @keydown.esc="menuOpen = false"
        >
          <div class="flex items-center justify-between border-b border-sage-50 px-4 py-3">
            <BrandMark />
            <button type="button" class="btn-icon" @click="menuOpen = false">
              <X class="h-5 w-5" aria-hidden="true" />
              <span class="sr-only">Cerrar menú</span>
            </button>
          </div>
          <nav class="flex-1 overflow-y-auto p-3" aria-label="Menú móvil">
            <RouterLink to="/" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-ink-700 hover:bg-sage-50">
              <Store class="h-5 w-5 text-clinic-600" aria-hidden="true" /> Inicio
            </RouterLink>
            <RouterLink to="/catalogo" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-ink-700 hover:bg-sage-50">
              <LayoutGrid class="h-5 w-5 text-clinic-600" aria-hidden="true" /> Todo el catálogo
            </RouterLink>
            <RouterLink :to="{ name: 'catalog', query: { ofertas: '1' } }" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-promo-700 hover:bg-promo-50">
              <BadgePercent class="h-5 w-5" aria-hidden="true" /> Ofertas de la semana
            </RouterLink>
            <p class="mb-1 mt-4 px-3 text-xs font-bold uppercase tracking-[0.16em] text-ink-500">Categorías</p>
            <RouterLink
              v-for="category in catalog.categories"
              :key="category.id"
              :to="{ name: 'catalog', query: { categoria: category.slug } }"
              class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-ink-700 hover:bg-sage-50"
            >
              <span class="inline-flex h-9 w-9 items-center justify-center rounded-full" :class="categoryMeta(category.slug).tone">
                <component :is="categoryMeta(category.slug).icon" class="h-5 w-5" aria-hidden="true" />
              </span>
              {{ category.name }}
            </RouterLink>
          </nav>
          <div class="border-t border-sage-50 p-4">
            <RouterLink to="/admin/login" class="flex items-center gap-2 text-sm text-ink-500 hover:text-clinic-700">
              <ShieldCheck class="h-4 w-4" aria-hidden="true" /> Administración (demo)
            </RouterLink>
          </div>
        </aside>
      </Transition>
    </Teleport>
  </header>
</template>
