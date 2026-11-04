<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ArrowRight, Leaf, ShieldCheck, Sparkles } from 'lucide-vue-next'
import DemoBanner from '@/components/DemoBanner.vue'
import ProductCard from '@/components/ProductCard.vue'
import SkeletonCard from '@/components/SkeletonCard.vue'
import ProductIllustration from '@/components/ProductIllustration.vue'
import { storeApi } from '@/api/store'
import type { Category, Product } from '@/types'

const featured = ref<Product[]>([])
const categories = ref<Category[]>([])
const loading = ref(true)
const failed = ref(false)

async function load() {
  loading.value = true
  failed.value = false
  try {
    const [products, cats] = await Promise.all([
      storeApi.products({ featured: true, per_page: 4 }),
      storeApi.categories(),
    ])
    featured.value = products.data
    categories.value = cats
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

onMounted(load)

const pillars = [
  { icon: ShieldCheck, title: 'Checkout transaccional', text: 'Stock bloqueado por fila, precios calculados en el servidor y clave de idempotencia.' },
  { icon: Leaf, title: 'Solo venta libre', text: 'Catálogo ficticio de bienestar y cuidado personal. Sin recetas ni consejo médico.' },
  { icon: Sparkles, title: 'Nube reproducible', text: 'Contenedores sin root, PostgreSQL y Terraform para Azure Container Apps.' },
]
</script>

<template>
  <div>
    <section class="relative overflow-hidden">
      <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-teal-100/70 blur-3xl" aria-hidden="true" />
      <div class="pointer-events-none absolute -left-16 top-40 h-72 w-72 rounded-full bg-sage-100 blur-3xl" aria-hidden="true" />
      <div class="container-page relative grid items-center gap-10 py-14 md:grid-cols-[1.1fr_0.9fr] md:py-20">
        <div class="motion-safe:animate-rise">
          <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-sage-200 bg-white/70 px-3 py-1 text-xs font-medium text-sage-700">
            <span class="h-1.5 w-1.5 rounded-full bg-teal-400" aria-hidden="true" /> Proyecto académico · Tienda demostrativa
          </p>
          <h1 class="text-4xl leading-[1.05] text-ink-900 sm:text-5xl lg:text-6xl">
            Cuidado cotidiano,<br /><span class="text-sage-600">con calma.</span>
          </h1>
          <p class="mt-5 max-w-lg text-base text-ink-500 sm:text-lg">
            Kay Care Farmacia es una tienda e-commerce de demostración: explora productos ficticios de venta libre, arma tu carrito y simula un pedido de principio a fin.
          </p>
          <div class="mt-8 flex flex-wrap gap-3">
            <RouterLink to="/catalogo" class="btn-primary px-6 py-3 text-base">
              Explorar catálogo <ArrowRight class="h-4 w-4" aria-hidden="true" />
            </RouterLink>
            <RouterLink :to="{ name: 'catalog', query: { orden: 'price_asc' } }" class="btn-ghost px-6 py-3 text-base">Ver precios más bajos</RouterLink>
          </div>
          <div class="mt-8 max-w-xl"><DemoBanner /></div>
        </div>
        <div class="relative mx-auto grid w-full max-w-md grid-cols-2 gap-4 motion-safe:animate-rise" aria-hidden="true">
          <div class="translate-y-6 overflow-hidden rounded-[2rem] shadow-lift"><ProductIllustration kind="bottle" size="lg" /></div>
          <div class="overflow-hidden rounded-[2rem] shadow-lift"><ProductIllustration kind="capsule" size="lg" /></div>
          <div class="translate-y-6 overflow-hidden rounded-[2rem] shadow-lift"><ProductIllustration kind="tube" size="lg" /></div>
          <div class="overflow-hidden rounded-[2rem] shadow-lift"><ProductIllustration kind="jar" size="lg" /></div>
        </div>
      </div>
    </section>

    <section class="container-page py-10" aria-labelledby="categorias">
      <div class="mb-6 flex items-end justify-between">
        <h2 id="categorias" class="text-2xl sm:text-3xl">Categorías</h2>
        <RouterLink to="/catalogo" class="text-sm font-medium text-sage-700 hover:underline">Ver todo</RouterLink>
      </div>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <template v-if="loading">
          <div v-for="n in 6" :key="n" class="skeleton h-24 rounded-2xl" />
        </template>
        <RouterLink
          v-for="category in categories"
          v-else
          :key="category.id"
          :to="{ name: 'catalog', query: { categoria: category.slug } }"
          class="group rounded-2xl border border-cream-200 bg-white p-4 shadow-soft transition-[transform,box-shadow] motion-safe:hover:-translate-y-0.5 hover:shadow-lift"
        >
          <p class="font-sans text-sm font-semibold text-ink-900">{{ category.name }}</p>
          <p class="mt-1 text-xs text-ink-500">{{ category.products_count ?? 0 }} productos</p>
        </RouterLink>
      </div>
    </section>

    <section class="container-page py-10" aria-labelledby="destacados">
      <div class="mb-6 flex items-end justify-between">
        <h2 id="destacados" class="text-2xl sm:text-3xl">Destacados de la semana</h2>
        <RouterLink :to="{ name: 'catalog', query: { orden: 'featured' } }" class="text-sm font-medium text-sage-700 hover:underline">Más productos</RouterLink>
      </div>
      <div v-if="failed" class="card p-6 text-sm text-ink-500" role="alert">
        No pudimos cargar los productos destacados.
        <button type="button" class="ml-2 font-semibold text-sage-700 underline" @click="load">Reintentar</button>
      </div>
      <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <template v-if="loading"><SkeletonCard v-for="n in 4" :key="n" /></template>
        <ProductCard v-for="product in featured" v-else :key="product.id" :product="product" />
      </div>
    </section>

    <section class="container-page py-10" aria-labelledby="como-funciona">
      <h2 id="como-funciona" class="sr-only">Cómo está construida la tienda</h2>
      <div class="grid gap-4 md:grid-cols-3">
        <div v-for="pillar in pillars" :key="pillar.title" class="rounded-2xl bg-white/60 p-6 ring-1 ring-cream-200">
          <component :is="pillar.icon" class="h-6 w-6 text-teal-500" aria-hidden="true" />
          <h3 class="mt-3 font-sans text-base font-semibold">{{ pillar.title }}</h3>
          <p class="mt-1 text-sm text-ink-500">{{ pillar.text }}</p>
        </div>
      </div>
    </section>
  </div>
</template>
