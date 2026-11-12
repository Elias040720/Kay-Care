<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ArrowRight, BadgePercent, Database, PackageCheck, ShieldCheck, ShoppingCart, Sun, Truck } from 'lucide-vue-next'
import DemoBanner from '@/components/DemoBanner.vue'
import ProductCard from '@/components/ProductCard.vue'
import SkeletonCard from '@/components/SkeletonCard.vue'
import HeroIllustration from '@/components/HeroIllustration.vue'
import { storeApi } from '@/api/store'
import { useCatalogStore } from '@/stores/catalog'
import { categoryMeta } from '@/utils/categories'
import type { Product } from '@/types'

const catalog = useCatalogStore()
const featured = ref<Product[]>([])
const offers = ref<Product[]>([])
const loading = ref(true)
const failed = ref(false)

async function load() {
  loading.value = true
  failed.value = false
  try {
    const [top, sale] = await Promise.all([
      storeApi.products({ featured: true, per_page: 8 }),
      storeApi.products({ on_sale: true, per_page: 4, sort: 'price_asc' }),
      catalog.loadCategories(),
    ])
    featured.value = top.data
    offers.value = sale.data
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Beneficios de la tienda demostrativa: describen la simulación, no un servicio real.
const benefits = [
  { icon: PackageCheck, title: 'Stock en tiempo real', text: 'Cada compra descuenta inventario en PostgreSQL.' },
  { icon: ShieldCheck, title: 'Compra 100 % simulada', text: 'Sin tarjetas, sin cobros y sin datos personales.' },
  { icon: Truck, title: 'Retiro simulado', text: 'El pedido avanza por estados desde el panel.' },
  { icon: Database, title: 'Pedidos persistentes', text: 'Tu pedido demo queda guardado y se puede consultar.' },
]

const steps = [
  { n: 1, title: 'Explora el catálogo', text: 'Busca por nombre o filtra por categoría y precio.' },
  { n: 2, title: 'Agrega al carrito', text: 'El sistema valida el stock disponible al instante.' },
  { n: 3, title: 'Checkout simulado', text: 'Solo un alias ficticio: sin pagos ni datos reales.' },
  { n: 4, title: 'Confirmación', text: 'Recibes el número de tu pedido de demostración.' },
]
</script>

<template>
  <div>
    <!-- HERO -->
    <section class="relative overflow-hidden bg-gradient-to-br from-clinic-50 via-white to-mist-50">
      <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-teal-100/60 blur-3xl" aria-hidden="true" />
      <div class="container-page relative grid items-center gap-10 py-12 md:grid-cols-[1.05fr_0.95fr] md:py-16 lg:py-20">
        <div class="motion-safe:animate-rise">
          <p class="mb-4 inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-semibold text-clinic-700 shadow-soft ring-1 ring-clinic-100">
            <span class="h-2 w-2 rounded-full bg-clinic-500" aria-hidden="true" /> Farmacia online demostrativa
          </p>
          <h1 class="text-4xl leading-[1.05] text-ink-900 sm:text-5xl lg:text-[3.6rem]">
            Tu bienestar, <span class="text-clinic-600">nuestra prioridad</span>
          </h1>
          <p class="mt-5 max-w-lg text-base text-ink-500 sm:text-lg">
            Encuentra productos de salud y cuidado personal en Kay Care Farmacia.
          </p>
          <div class="mt-8 flex flex-wrap gap-3">
            <RouterLink to="/catalogo" class="btn-primary px-7 py-3.5 text-base" data-testid="hero-cta">
              Explorar productos <ArrowRight class="h-4 w-4" aria-hidden="true" />
            </RouterLink>
            <RouterLink :to="{ name: 'catalog', query: { ofertas: '1' } }" class="btn-ghost px-6 py-3.5 text-base">
              <BadgePercent class="h-4 w-4 text-promo-600" aria-hidden="true" /> Ver ofertas
            </RouterLink>
          </div>
          <p class="mt-6 text-xs font-semibold uppercase tracking-[0.14em] text-ink-500">
            Proyecto académico — no se realizan ventas reales
          </p>
        </div>
        <div class="relative mx-auto w-full max-w-lg motion-safe:animate-rise">
          <HeroIllustration />
        </div>
      </div>
    </section>

    <!-- BENEFICIOS -->
    <section class="border-y border-sage-50 bg-white" aria-label="Cómo funciona la tienda demostrativa">
      <ul class="container-page grid grid-cols-1 gap-4 py-6 sm:grid-cols-2 lg:grid-cols-4">
        <li v-for="b in benefits" :key="b.title" class="flex items-start gap-3">
          <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-clinic-50 text-clinic-700">
            <component :is="b.icon" class="h-5 w-5" aria-hidden="true" />
          </span>
          <span>
            <span class="block text-sm font-bold text-ink-900">{{ b.title }}</span>
            <span class="block text-xs text-ink-500">{{ b.text }}</span>
          </span>
        </li>
      </ul>
    </section>

    <!-- CATEGORÍAS -->
    <section class="container-page py-12" aria-labelledby="categorias">
      <div class="mb-6 flex items-end justify-between gap-4">
        <div>
          <h2 id="categorias" class="text-2xl sm:text-3xl">Compra por categoría</h2>
          <p class="mt-1 text-sm text-ink-500">Encuentra rápido lo que necesitas.</p>
        </div>
        <RouterLink to="/catalogo" class="hidden text-sm font-semibold text-clinic-700 hover:underline sm:inline">Ver todo el catálogo</RouterLink>
      </div>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <template v-if="!catalog.loaded && !catalog.failed">
          <div v-for="n in 6" :key="n" class="skeleton h-40 rounded-2xl" />
        </template>
        <RouterLink
          v-for="category in catalog.categories"
          v-else
          :key="category.id"
          :to="{ name: 'catalog', query: { categoria: category.slug } }"
          class="group flex flex-col items-center rounded-2xl border border-sage-100 bg-white p-5 text-center shadow-soft transition-[transform,box-shadow,border-color] hover:border-clinic-200 hover:shadow-lift motion-safe:hover:-translate-y-1"
          :data-testid="`home-category-${category.slug}`"
        >
          <span class="inline-flex h-16 w-16 items-center justify-center rounded-full transition-transform motion-safe:group-hover:scale-110" :class="categoryMeta(category.slug).tone">
            <component :is="categoryMeta(category.slug).icon" class="h-8 w-8" aria-hidden="true" />
          </span>
          <span class="mt-3 text-sm font-bold leading-tight text-ink-900">{{ category.name }}</span>
          <span class="mt-1 text-xs text-ink-500">{{ category.products_count ?? 0 }} productos</span>
        </RouterLink>
      </div>
    </section>

    <!-- BANNERS PROMOCIONALES (ficticios) -->
    <section class="container-page" aria-label="Promociones de la tienda demostrativa">
      <div class="grid gap-4 md:grid-cols-2">
        <RouterLink
          :to="{ name: 'catalog', query: { categoria: 'cuidado-personal' } }"
          class="group relative flex min-h-[170px] items-center overflow-hidden rounded-3xl bg-gradient-to-r from-teal-600 to-clinic-600 p-7 text-white shadow-lift"
        >
          <div class="relative z-10 max-w-[65%]">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-white">Temporada de sol</p>
            <p class="mt-1 text-2xl font-extrabold leading-tight">Protección solar y cuidado de la piel</p>
            <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold underline-offset-4 group-hover:underline">
              Ver productos <ArrowRight class="h-4 w-4" aria-hidden="true" />
            </span>
          </div>
          <Sun class="absolute -right-4 top-1/2 h-40 w-40 -translate-y-1/2 text-white/15 transition-transform duration-700 motion-safe:group-hover:rotate-45" aria-hidden="true" />
        </RouterLink>
        <RouterLink
          :to="{ name: 'catalog', query: { categoria: 'vitaminas-y-bienestar' } }"
          class="group relative flex min-h-[170px] items-center overflow-hidden rounded-3xl bg-gradient-to-r from-mist-600 to-mist-500 p-7 text-white shadow-lift"
        >
          <div class="relative z-10 max-w-[65%]">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-white">Bienestar diario</p>
            <p class="mt-1 text-2xl font-extrabold leading-tight">Vitaminas y complementos para toda la familia</p>
            <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold underline-offset-4 group-hover:underline">
              Ver productos <ArrowRight class="h-4 w-4" aria-hidden="true" />
            </span>
          </div>
          <ShoppingCart class="absolute -right-2 top-1/2 h-36 w-36 -translate-y-1/2 text-white/15 transition-transform duration-700 motion-safe:group-hover:-translate-x-2" aria-hidden="true" />
        </RouterLink>
      </div>
    </section>

    <!-- OFERTAS -->
    <section v-if="loading || offers.length" class="container-page py-12" aria-labelledby="ofertas">
      <div class="mb-6 flex items-end justify-between gap-4">
        <div>
          <h2 id="ofertas" class="flex items-center gap-2 text-2xl sm:text-3xl">
            <BadgePercent class="h-7 w-7 text-promo-600" aria-hidden="true" /> Ofertas de la semana
          </h2>
          <p class="mt-1 text-sm text-ink-500">Precios ficticios de demostración.</p>
        </div>
        <RouterLink :to="{ name: 'catalog', query: { ofertas: '1' } }" class="text-sm font-semibold text-clinic-700 hover:underline">Ver ofertas</RouterLink>
      </div>
      <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
        <template v-if="loading"><SkeletonCard v-for="n in 4" :key="n" /></template>
        <ProductCard v-for="product in offers" v-else :key="product.id" :product="product" />
      </div>
    </section>

    <!-- DESTACADOS -->
    <section class="bg-sage-50/60 py-12" aria-labelledby="destacados">
      <div class="container-page">
        <div class="mb-6 flex items-end justify-between gap-4">
          <div>
            <h2 id="destacados" class="text-2xl sm:text-3xl">Productos destacados</h2>
            <p class="mt-1 text-sm text-ink-500">Lo más elegido en la tienda demostrativa.</p>
          </div>
          <RouterLink :to="{ name: 'catalog', query: { orden: 'featured' } }" class="text-sm font-semibold text-clinic-700 hover:underline">Más productos</RouterLink>
        </div>
        <div v-if="failed" class="card p-6 text-sm text-ink-500" role="alert">
          No pudimos cargar los productos. Verifica que la API esté disponible.
          <button type="button" class="ml-2 font-semibold text-clinic-700 underline" @click="load">Reintentar</button>
        </div>
        <div v-else class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
          <template v-if="loading"><SkeletonCard v-for="n in 8" :key="n" /></template>
          <ProductCard v-for="product in featured" v-else :key="product.id" :product="product" />
        </div>
      </div>
    </section>

    <!-- CÓMO COMPRAR -->
    <section class="container-page py-12" aria-labelledby="como-comprar">
      <h2 id="como-comprar" class="text-2xl sm:text-3xl">¿Cómo funciona la compra de demostración?</h2>
      <ol class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <li v-for="s in steps" :key="s.n" class="rounded-2xl border border-sage-100 bg-white p-5 shadow-soft">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-clinic-600 text-sm font-bold text-white">{{ s.n }}</span>
          <h3 class="mt-3 font-sans text-base font-bold">{{ s.title }}</h3>
          <p class="mt-1 text-sm text-ink-500">{{ s.text }}</p>
        </li>
      </ol>
      <div class="mt-8"><DemoBanner /></div>
    </section>
  </div>
</template>
