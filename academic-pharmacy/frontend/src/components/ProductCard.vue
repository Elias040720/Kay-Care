<script setup lang="ts">
import { Plus } from 'lucide-vue-next'
import ProductIllustration from './ProductIllustration.vue'
import StockBadge from './StockBadge.vue'
import type { Product } from '@/types'
import { formatMoney } from '@/utils/format'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

const props = defineProps<{ product: Product }>()
const cart = useCartStore()
const toast = useToastStore()

function addToCart() {
  const result = cart.add(props.product, 1)
  if (result === 'out_of_stock') toast.error('Producto agotado', props.product.name)
  else if (result === 'cart_full') toast.error('Carrito completo', 'Puedes tener hasta 20 productos distintos.')
  else if (result === 'capped') toast.info('Cantidad máxima alcanzada', 'Ajustamos la cantidad al stock disponible.')
  else toast.success('Añadido al carrito', props.product.name)
}
</script>

<template>
  <article
    class="group card flex flex-col overflow-hidden transition-[transform,box-shadow] duration-300 ease-out motion-safe:hover:-translate-y-1 hover:shadow-lift"
    data-testid="product-card"
  >
    <RouterLink :to="{ name: 'product', params: { slug: product.slug } }" class="block" :aria-label="`Ver ${product.name}`">
      <div class="overflow-hidden">
        <div class="transition-transform duration-500 ease-out motion-safe:group-hover:scale-[1.04]">
          <ProductIllustration :kind="product.illustration" :image-url="product.image_url" :alt="product.name" />
        </div>
      </div>
    </RouterLink>
    <div class="flex flex-1 flex-col gap-2 p-4">
      <div class="flex items-center justify-between gap-2">
        <span class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sage-600">{{ product.brand }}</span>
        <StockBadge :status="product.stock_status" :stock="product.stock" />
      </div>
      <h3 class="font-sans text-[15px] font-semibold leading-snug text-ink-900">
        <RouterLink :to="{ name: 'product', params: { slug: product.slug } }" class="hover:text-sage-700">{{ product.name }}</RouterLink>
      </h3>
      <p class="text-xs text-ink-500">{{ product.presentation }}</p>
      <div class="mt-auto flex items-end justify-between pt-2">
        <p class="text-lg font-semibold text-ink-900" data-testid="product-price">{{ formatMoney(product.price_cents) }}</p>
        <button
          type="button"
          class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-sage-600 text-cream-50 shadow-soft transition-[transform,background-color] hover:bg-sage-700 motion-safe:active:scale-90 disabled:cursor-not-allowed disabled:bg-cream-300 disabled:text-ink-400"
          :disabled="product.stock <= 0"
          :aria-label="product.stock > 0 ? `Añadir ${product.name} al carrito` : `${product.name} agotado`"
          data-testid="add-to-cart"
          @click="addToCart"
        >
          <Plus class="h-5 w-5" aria-hidden="true" />
        </button>
      </div>
    </div>
  </article>
</template>
