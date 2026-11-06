<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ChevronLeft, ShoppingBag } from 'lucide-vue-next'
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
    <RouterLink to="/catalogo" class="mb-6 inline-flex items-center gap-1 text-sm text-ink-500 hover:text-sage-700">
      <ChevronLeft class="h-4 w-4" aria-hidden="true" /> Volver al catálogo
    </RouterLink>

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
        <div class="overflow-hidden rounded-xl2 shadow-lift motion-safe:animate-rise">
          <ProductIllustration :kind="product.illustration" :image-url="product.image_url" :alt="product.name" size="lg" />
        </div>
        <div class="motion-safe:animate-rise">
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sage-600">{{ product.brand }} · {{ product.category?.name }}</p>
          <h1 class="mt-2 text-3xl sm:text-4xl">{{ product.name }}</h1>
          <p class="mt-2 text-sm text-ink-500">{{ product.presentation }} · SKU {{ product.sku }}</p>
          <div class="mt-5 flex items-center gap-3">
            <p class="text-3xl font-semibold" data-testid="detail-price">{{ formatMoney(product.price_cents) }}</p>
            <StockBadge :status="product.stock_status" :stock="product.stock" />
          </div>
          <p class="mt-1 text-xs text-ink-400">Precio ficticio con IGV incluido.</p>

          <p class="mt-6 whitespace-pre-line text-[15px] leading-relaxed text-ink-700">{{ product.description }}</p>

          <div v-if="product.stock > 0" class="mt-8 flex flex-wrap items-center gap-3">
            <QuantityStepper v-model="quantity" :max="maxSelectable" :label="product.name" />
            <button type="button" class="btn-primary px-6 py-3" :disabled="inCart >= Math.min(MAX_QUANTITY, product.stock)" data-testid="detail-add" @click="addToCart">
              <ShoppingBag class="h-4 w-4" aria-hidden="true" /> Añadir al carrito
            </button>
          </div>
          <p v-else class="mt-8 rounded-xl bg-clay-100 px-4 py-3 text-sm text-clay-700">Producto agotado en la tienda demostrativa.</p>
          <p v-if="inCart" class="mt-3 text-sm text-ink-500">Ya tienes {{ inCart }} en tu carrito.</p>

          <div class="mt-8 rounded-2xl bg-white/70 p-4 text-xs text-ink-500 ring-1 ring-cream-200">
            Contenido informativo de un producto inventado para fines académicos. No constituye recomendación médica.
          </div>
        </div>
      </article>

      <section v-if="related.length" class="mt-16" aria-labelledby="relacionados">
        <h2 id="relacionados" class="mb-6 text-2xl">También en {{ product.category?.name }}</h2>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <ProductCard v-for="item in related" :key="item.id" :product="item" />
        </div>
      </section>
    </template>
  </div>
</template>
