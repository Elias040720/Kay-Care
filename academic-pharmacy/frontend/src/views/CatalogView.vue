<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQuery } from 'vue-router'
import { BadgePercent, ChevronLeft, ChevronRight, LayoutGrid, PackageSearch, Search, SlidersHorizontal } from 'lucide-vue-next'
import DemoBanner from '@/components/DemoBanner.vue'
import ProductCard from '@/components/ProductCard.vue'
import SkeletonCard from '@/components/SkeletonCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import { storeApi, type ProductQuery } from '@/api/store'
import { ApiError } from '@/api/client'
import type { PageMeta, Product } from '@/types'
import { useCatalogStore } from '@/stores/catalog'
import { categoryMeta } from '@/utils/categories'

const route = useRoute()
const router = useRouter()

const SORTS = [
  { value: 'featured', label: 'Destacados' },
  { value: 'price_asc', label: 'Precio: menor a mayor' },
  { value: 'price_desc', label: 'Precio: mayor a menor' },
  { value: 'name_asc', label: 'Nombre (A–Z)' },
  { value: 'newest', label: 'Más recientes' },
]

interface Filters {
  q: string
  categoria: string
  orden: string
  min: string
  max: string
  stock: boolean
  ofertas: boolean
  pagina: number
}

function fromQuery(query: LocationQuery): Filters {
  const str = (v: unknown) => (typeof v === 'string' ? v : '')
  const sort = str(query.orden)
  return {
    q: str(query.q).slice(0, 80),
    categoria: /^[a-z0-9-]*$/.test(str(query.categoria)) ? str(query.categoria) : '',
    orden: SORTS.some((s) => s.value === sort) ? sort : 'featured',
    min: /^\d+(\.\d{1,2})?$/.test(str(query.min)) ? str(query.min) : '',
    max: /^\d+(\.\d{1,2})?$/.test(str(query.max)) ? str(query.max) : '',
    stock: str(query.stock) === '1',
    ofertas: str(query.ofertas) === '1',
    pagina: Math.max(1, Number.parseInt(str(query.pagina), 10) || 1),
  }
}

const filters = reactive<Filters>(fromQuery(route.query))
const searchInput = ref(filters.q)
const products = ref<Product[]>([])
const meta = ref<PageMeta | null>(null)
const catalog = useCatalogStore()
const categories = computed(() => catalog.categories)
const loading = ref(true)
const error = ref<string | null>(null)
const showFilters = ref(false)
let controller: AbortController | null = null
let debounce: ReturnType<typeof setTimeout> | undefined

const priceError = computed(() =>
  filters.min !== '' && filters.max !== '' && Number(filters.max) < Number(filters.min) ? 'El máximo debe ser mayor o igual al mínimo.' : null,
)
const activeCount = computed(() => [filters.q, filters.categoria, filters.min, filters.max, filters.stock ? '1' : '', filters.ofertas ? '1' : ''].filter(Boolean).length)
const currentCategory = computed(() => (filters.categoria ? catalog.bySlug(filters.categoria) : undefined))
const heading = computed(() => {
  if (filters.ofertas && !currentCategory.value) return 'Ofertas de la semana'
  if (currentCategory.value) return currentCategory.value.name
  if (filters.q) return `Resultados para «${filters.q}»`
  return 'Catálogo'
})

function toApiQuery(f: Filters): ProductQuery {
  return {
    q: f.q || undefined,
    category: f.categoria || undefined,
    sort: f.orden,
    min_price: f.min === '' ? undefined : Number(f.min),
    max_price: f.max === '' ? undefined : Number(f.max),
    in_stock: f.stock || undefined,
    on_sale: f.ofertas || undefined,
    page: f.pagina,
    per_page: 12,
  }
}

async function load() {
  if (priceError.value) return
  controller?.abort()
  controller = new AbortController()
  loading.value = true
  error.value = null
  try {
    const result = await storeApi.products(toApiQuery(filters), controller.signal)
    products.value = result.data
    meta.value = result.meta
  } catch (e) {
    if ((e as Error).name === 'AbortError') return
    error.value = e instanceof ApiError ? e.message : 'No se pudo cargar el catálogo.'
    products.value = []
  } finally {
    loading.value = false
  }
}

function syncUrl() {
  const query: Record<string, string> = {}
  if (filters.q) query.q = filters.q
  if (filters.categoria) query.categoria = filters.categoria
  if (filters.orden !== 'featured') query.orden = filters.orden
  if (filters.min) query.min = filters.min
  if (filters.max) query.max = filters.max
  if (filters.stock) query.stock = '1'
  if (filters.ofertas) query.ofertas = '1'
  if (filters.pagina > 1) query.pagina = String(filters.pagina)
  router.replace({ name: 'catalog', query })
}

function update(patch: Partial<Filters>) {
  Object.assign(filters, { pagina: 1 }, patch)
  syncUrl()
  load()
}

function onSearchInput() {
  clearTimeout(debounce)
  debounce = setTimeout(() => update({ q: searchInput.value.trim() }), 300)
}

function goToPage(page: number) {
  if (!meta.value || page < 1 || page > meta.value.last_page) return
  filters.pagina = page
  syncUrl()
  load()
}

function clearFilters() {
  searchInput.value = ''
  update({ q: '', categoria: '', min: '', max: '', stock: false, ofertas: false, orden: 'featured' })
}

// Navegación externa (p. ej. buscador del header) → re-sincroniza.
watch(
  () => route.query,
  (query) => {
    if (route.name !== 'catalog') return
    const next = fromQuery(query)
    if (JSON.stringify(next) !== JSON.stringify({ ...filters })) {
      Object.assign(filters, next)
      searchInput.value = next.q
      load()
    }
  },
)

onMounted(async () => {
  load()
  await catalog.loadCategories()
})
onBeforeUnmount(() => {
  controller?.abort()
  clearTimeout(debounce)
})
</script>

<template>
  <div class="container-page py-8 sm:py-10">
    <div class="mb-6"><DemoBanner /></div>

    <!-- Accesos rápidos por categoría -->
    <div class="-mx-4 mb-6 overflow-x-auto px-4 pb-1" role="group" aria-label="Categorías rápidas">
      <div class="flex w-max gap-2">
        <button type="button" class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors" :class="!filters.categoria && !filters.ofertas ? 'border-clinic-600 bg-clinic-600 text-white' : 'border-sage-100 bg-white text-ink-700 hover:border-clinic-200'" :aria-pressed="!filters.categoria && !filters.ofertas" @click="update({ categoria: '', ofertas: false })">
          <LayoutGrid class="h-4 w-4" aria-hidden="true" /> Todo
        </button>
        <button type="button" class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors" :class="filters.ofertas ? 'border-promo-600 bg-promo-600 text-white' : 'border-sage-100 bg-white text-promo-700 hover:border-promo-600'" :aria-pressed="filters.ofertas" data-testid="chip-ofertas" @click="update({ ofertas: !filters.ofertas })">
          <BadgePercent class="h-4 w-4" aria-hidden="true" /> Ofertas
        </button>
        <button v-for="category in categories" :key="category.id" type="button" class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors" :class="filters.categoria === category.slug ? 'border-clinic-600 bg-clinic-600 text-white' : 'border-sage-100 bg-white text-ink-700 hover:border-clinic-200'" :aria-pressed="filters.categoria === category.slug" :data-testid="`chip-${category.slug}`" @click="update({ categoria: filters.categoria === category.slug ? '' : category.slug })">
          <component :is="categoryMeta(category.slug).icon" class="h-4 w-4" aria-hidden="true" /> {{ category.name }}
        </button>
      </div>
    </div>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <nav class="mb-2 text-xs text-ink-500" aria-label="Ruta de navegación">
          <RouterLink to="/" class="hover:text-clinic-700 hover:underline">Inicio</RouterLink>
          <span aria-hidden="true"> / </span>
          <RouterLink to="/catalogo" class="hover:text-clinic-700 hover:underline">Catálogo</RouterLink>
          <template v-if="currentCategory"><span aria-hidden="true"> / </span><span>{{ currentCategory.name }}</span></template>
        </nav>
        <h1 class="text-3xl sm:text-4xl" data-testid="catalog-heading">{{ heading }}</h1>
        <p v-if="currentCategory?.description" class="mt-1 text-sm text-ink-500">{{ currentCategory.description }}</p>
        <p class="mt-1 text-sm text-ink-500" aria-live="polite" data-testid="results-count">
          <template v-if="loading">Buscando productos…</template>
          <template v-else-if="meta">{{ meta.total }} {{ meta.total === 1 ? 'producto' : 'productos' }}</template>
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <div class="relative w-full sm:w-72">
          <label for="catalog-search" class="sr-only">Buscar en el catálogo</label>
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" aria-hidden="true" />
          <input
            id="catalog-search"
            v-model="searchInput"
            type="search"
            maxlength="80"
            placeholder="Nombre, marca o SKU"
            class="field rounded-full pl-9"
            data-testid="catalog-search"
            @input="onSearchInput"
          />
        </div>
        <label for="catalog-sort" class="sr-only">Ordenar por</label>
        <select
          id="catalog-sort"
          class="field w-auto rounded-full pr-8"
          :value="filters.orden"
          data-testid="catalog-sort"
          @change="update({ orden: ($event.target as HTMLSelectElement).value })"
        >
          <option v-for="sort in SORTS" :key="sort.value" :value="sort.value">{{ sort.label }}</option>
        </select>
        <button type="button" class="btn-ghost lg:hidden" :aria-expanded="showFilters" aria-controls="catalog-filters" @click="showFilters = !showFilters">
          <SlidersHorizontal class="h-4 w-4" aria-hidden="true" /> Filtros<span v-if="activeCount">({{ activeCount }})</span>
        </button>
      </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-[240px_1fr]">
      <aside id="catalog-filters" class="space-y-6 lg:block" :class="showFilters ? 'block' : 'hidden'" aria-label="Filtros del catálogo">
        <fieldset>
          <legend class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-ink-700">Categoría</legend>
          <div class="space-y-1">
            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-sage-50">
              <input type="radio" name="categoria" value="" class="accent-clinic-600" :checked="filters.categoria === ''" @change="update({ categoria: '' })" />
              Todas
            </label>
            <label v-for="category in categories" :key="category.id" class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-sage-50">
              <span class="flex items-center gap-2">
                <input
                  type="radio"
                  name="categoria"
                  :value="category.slug"
                  class="accent-clinic-600"
                  :checked="filters.categoria === category.slug"
                  :data-testid="`filter-category-${category.slug}`"
                  @change="update({ categoria: category.slug })"
                />
                {{ category.name }}
              </span>
              <span class="text-xs text-ink-400">{{ category.products_count }}</span>
            </label>
          </div>
        </fieldset>

        <fieldset>
          <legend class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-ink-700">Precio (S/)</legend>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label for="price-min" class="sr-only">Precio mínimo</label>
              <input id="price-min" type="number" min="0" step="0.5" inputmode="decimal" placeholder="Mín." class="field" :value="filters.min" data-testid="price-min" @change="update({ min: ($event.target as HTMLInputElement).value })" />
            </div>
            <div>
              <label for="price-max" class="sr-only">Precio máximo</label>
              <input id="price-max" type="number" min="0" step="0.5" inputmode="decimal" placeholder="Máx." class="field" :value="filters.max" data-testid="price-max" @change="update({ max: ($event.target as HTMLInputElement).value })" />
            </div>
          </div>
          <p v-if="priceError" class="field-error" role="alert">{{ priceError }}</p>
        </fieldset>

        <label class="flex cursor-pointer items-center gap-2 text-sm">
          <input type="checkbox" class="h-4 w-4 accent-clinic-600" :checked="filters.stock" data-testid="filter-in-stock" @change="update({ stock: ($event.target as HTMLInputElement).checked })" />
          Solo con stock disponible
        </label>

        <label class="flex cursor-pointer items-center gap-2 text-sm">
          <input type="checkbox" class="h-4 w-4 accent-promo-600" :checked="filters.ofertas" data-testid="filter-on-sale" @change="update({ ofertas: ($event.target as HTMLInputElement).checked })" />
          Solo ofertas
        </label>

        <button v-if="activeCount" type="button" class="btn-ghost w-full" @click="clearFilters">Limpiar filtros</button>
      </aside>

      <section aria-labelledby="catalog-results-title">
        <h2 id="catalog-results-title" class="sr-only">Resultados</h2>
        <div v-if="error" class="card p-6 text-sm" role="alert">
          <p class="font-semibold text-clay-700">{{ error }}</p>
          <button type="button" class="btn-ghost mt-3" @click="load">Reintentar</button>
        </div>

        <div v-else-if="loading" class="grid grid-cols-2 gap-3 sm:gap-5 xl:grid-cols-3" aria-busy="true">
          <SkeletonCard v-for="n in 6" :key="n" />
        </div>

        <EmptyState
          v-else-if="products.length === 0"
          :icon="PackageSearch"
          title="Sin resultados"
          description="No encontramos productos con esos criterios. Prueba otra búsqueda o limpia los filtros."
        >
          <button type="button" class="btn-primary" @click="clearFilters">Limpiar filtros</button>
        </EmptyState>

        <template v-else>
          <div class="grid grid-cols-2 gap-3 sm:gap-5 xl:grid-cols-3">
            <ProductCard v-for="product in products" :key="product.id" :product="product" />
          </div>

          <nav v-if="meta && meta.last_page > 1" class="mt-10 flex items-center justify-center gap-2" aria-label="Paginación">
            <button type="button" class="btn-icon" :disabled="meta.current_page <= 1" aria-label="Página anterior" @click="goToPage(meta.current_page - 1)">
              <ChevronLeft class="h-5 w-5" aria-hidden="true" />
            </button>
            <button
              v-for="page in meta.last_page"
              :key="page"
              type="button"
              class="h-10 min-w-[2.5rem] rounded-full px-3 text-sm font-medium transition-colors"
              :class="page === meta.current_page ? 'bg-clinic-600 text-white' : 'text-ink-700 hover:bg-sage-50'"
              :aria-current="page === meta.current_page ? 'page' : undefined"
              @click="goToPage(page)"
            >
              {{ page }}
            </button>
            <button type="button" class="btn-icon" :disabled="meta.current_page >= meta.last_page" aria-label="Página siguiente" @click="goToPage(meta.current_page + 1)">
              <ChevronRight class="h-5 w-5" aria-hidden="true" />
            </button>
          </nav>
        </template>
      </section>
    </div>
  </div>
</template>
