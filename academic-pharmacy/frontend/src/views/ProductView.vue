<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { PackageCheck, ShieldCheck, ShoppingCart, Store } from 'lucide-vue-next'
import ProductIllustration from '@/components/ProductIllustration.vue'
import ProductCard from '@/components/ProductCard.vue'
import StockBadge from '@/components/StockBadge.vue'
import QuantityStepper from '@/components/QuantityStepper.vue'
import EmptyState from '@/components/EmptyState.vue'
import { storeApi } from '@/api/store'
import { ApiError } from '@/api/client'
import { useCartStore, MAX_QUANTITY } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'
import { formatMoney } from '@/utils/format'
import type { Product } from '@/types'

const props = defineProps<{ slug: string }>()
const cart = useCartStore()
const toast = useToastStore()

const product = ref<Product | null>(null)
const related = ref<Product[]>([])
const loading = ref(true)
const notFound = ref(false)
const failed = ref(false)
const quantity = ref(1)

const hasPromo = computed(() => (product.value?.discount_percent ?? 0) > 0 && !!product.value?.compare_at_price_cents)
const inCart = computed(() => cart.lines.find((l) => l.product_id === product.value?.id)?.quantity ?? 0)
const maxSelectable = computed(() => Math.max(1, Math.min(MAX_QUANTITY, (product.value?.stock ?? 0)) - inCart.value))

async function load(slug: string) {
  loading.value = true
  notFound.value = false
  failed.value = false
  quantity.value = 1
  try {
    product.value = await storeApi.product(slug)
    document.title = `${product.value.name} · Kay Care Farmacia`
    storeApi.related(slug).then((r) => (related.value = r)).catch(() => (related.value = []))
  } catch (e) {
    product.value = null
    if (e instanceof ApiError && e.status === 404) notFound.value = true
    else failed.value = true
  } finally {
    loading.value = false
  }
}

watch(() => props.slug, load, { immediate: true })

function addToCart() {
  if (!product.value) return
  const result = cart.add(product.value, quantity.value)
  if (result === 'out_of_stock') return toast.error('Producto agotado')
  if (result === 'cart_full') return toast.error('Carrito completo', 'Puedes tener hasta 20 productos distintos.')
  if (result === 'capped') toast.info('Cantidad ajustada', 'Llegaste al máximo disponible para este producto.')
  else toast.success('Añadido al carrito', `${quantity.value} × ${product.value.name}`)
  cart.open()
}
</script>

<template>
  <div class="container-page py-8 sm:py-10">
    <nav class="mb-6 text-xs text-ink-500" aria-label="Ruta de navegación">
      <RouterLink to="/" class="hover:text-clinic-700 hover:underline">Inicio</RouterLink>
      <span aria-hidden="true"> / </span>
      <RouterLink to="/catalogo" class="hover:text-clinic-700 hover:underline">Catálogo</RouterLink>
      <template v-if="product?.category">
        <span aria-hidden="true"> / </span>
        <RouterLink :to="{ name: 'catalog', query: { categoria: product.category.slug } }" class="hover:text-clinic-700 hover:underline">{{ product.category.name }}</RouterLink>
      </template>
    </nav>

    <div v-if="loading" class="grid gap-10 md:grid-cols-2" aria-busy="true">
      <div class="skeleton aspect-square rounded-xl2" />
      <div class="space-y-4">
        <div class="skeleton h-4 w-24" />
        <div class="skeleton h-10 w-3/4" />
        <div class="skeleton h-4 w-full" />
        <div class="skeleton h-4 w-5/6" />
        <div class="skeleton h-12 w-40 rounded-full" />
      </div>
    </div>

    <EmptyState v-else-if="notFound" title="Producto no encontrado" description="Puede que ya no esté disponible en la tienda demostrativa.">
      <RouterLink to="/catalogo" class="btn-primary">Ir al catálogo</RouterLink>
    </EmptyState>

    <div v-else-if="failed" class="card p-6" role="alert">
      <p class="font-semibold text-clay-700">No se pudo cargar el producto.</p>
      <button type="button" class="btn-ghost mt-3" @click="load(slug)">Reintentar</button>
    </div>

    <template v-else-if="product">
      <article class="grid gap-10 md:grid-cols-2">
        <div class="relative overflow-hidden rounded-3xl border border-sage-100 bg-white p-4 shadow-lift motion-safe:animate-rise">
          <span v-if="hasPromo" class="absolute left-6 top-6 z-10 rounded-full bg-promo-600 px-3 py-1 text-sm font-bold text-white">-{{ product.discount_percent }}%</span>
          <ProductIllustration :kind="product.illustration" :image-url="product.image_url" :alt="product.name" size="lg" />
        </div>
        <div class="motion-safe:animate-rise">
          <p class="text-xs font-bold uppercase tracking-[0.2em] text-sage-700">{{ product.brand }} · {{ product.category?.name }}</p>
          <h1 class="mt-2 text-3xl sm:text-4xl">{{ product.name }}</h1>
          <p class="mt-2 text-sm text-ink-500">{{ product.presentation }} · SKU {{ product.sku }}</p>
          <div class="mt-5 rounded-2xl bg-sage-50/70 p-4">
            <p v-if="hasPromo" class="text-sm text-ink-500">
              Antes <s>{{ formatMoney(product.compare_at_price_cents ?? 0) }}</s>
              <span class="ml-2 rounded-full bg-promo-50 px-2 py-0.5 text-xs font-bold text-promo-700">Ahorras {{ formatMoney((product.compare_at_price_cents ?? 0) - product.price_cents) }}</span>
            </p>
            <div class="flex flex-wrap items-center gap-3">
              <p class="text-4xl font-extrabold" :class="hasPromo ? 'text-promo-700' : 'text-ink-900'" data-testid="detail-price">{{ formatMoney(product.price_cents) }}</p>
              <StockBadge :status="product.stock_status" :stock="product.stock" />
            </div>
            <p class="mt-1 text-xs text-ink-500">Precio ficticio en soles con IGV incluido · Stock disponible: {{ product.stock }}</p>
          </div>

          <p class="mt-6 whitespace-pre-line text-[15px] leading-relaxed text-ink-700">{{ product.description }}</p>

          <div v-if="product.stock > 0" class="mt-8 flex flex-wrap items-center gap-3">
            <QuantityStepper v-model="quantity" :max="maxSelectable" :label="product.name" />
            <button type="button" class="btn-primary px-6 py-3" :disabled="inCart >= Math.min(MAX_QUANTITY, product.stock)" data-testid="detail-add" @click="addToCart">
              <ShoppingCart class="h-4 w-4" aria-hidden="true" /> Agregar al carrito
            </button>
          </div>
          <p v-else class="mt-8 rounded-xl bg-clay-100 px-4 py-3 text-sm text-clay-700">Producto agotado en la tienda demostrativa.</p>
          <p v-if="inCart" class="mt-3 text-sm text-ink-500">Ya tienes {{ inCart }} en tu carrito.</p>

          <ul class="mt-8 grid gap-3 sm:grid-cols-3">
            <li class="flex items-start gap-2 rounded-xl border border-sage-100 p-3 text-xs text-ink-500"><PackageCheck class="h-4 w-4 shrink-0 text-clinic-600" aria-hidden="true" /> Stock validado al confirmar</li>
            <li class="flex items-start gap-2 rounded-xl border border-sage-100 p-3 text-xs text-ink-500"><ShieldCheck class="h-4 w-4 shrink-0 text-clinic-600" aria-hidden="true" /> Compra simulada, sin cobros</li>
            <li class="flex items-start gap-2 rounded-xl border border-sage-100 p-3 text-xs text-ink-500"><Store class="h-4 w-4 shrink-0 text-clinic-600" aria-hidden="true" /> Retiro en tienda ficticio</li>
          </ul>
          <p class="mt-4 text-xs text-ink-500">
            Contenido informativo de un producto inventado para fines académicos. No constituye recomendación médica ni indicación de uso.
          </p>
        </div>
      </article>

      <section v-if="related.length" class="mt-16" aria-labelledby="relacionados">
        <h2 id="relacionados" class="mb-6 text-2xl">También en {{ product.category?.name }}</h2>
        <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
          <ProductCard v-for="item in related" :key="item.id" :product="item" />
        </div>
      </section>
    </template>
  </div>
</template>
