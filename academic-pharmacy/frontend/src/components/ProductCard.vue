<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { Check, ShoppingCart } from 'lucide-vue-next'
import ProductIllustration from './ProductIllustration.vue'
import StockBadge from './StockBadge.vue'
import type { Product } from '@/types'
import { formatMoney } from '@/utils/format'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

const props = defineProps<{ product: Product }>()
const cart = useCartStore()
const toast = useToastStore()
const justAdded = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

const hasPromo = computed(() => (props.product.discount_percent ?? 0) > 0 && !!props.product.compare_at_price_cents)
const soldOut = computed(() => props.product.stock <= 0)

function addToCart() {
  const result = cart.add(props.product, 1)
  if (result === 'out_of_stock') return toast.error('Producto agotado', props.product.name)
  if (result === 'cart_full') return toast.error('Carrito completo', 'Puedes tener hasta 20 productos distintos.')
  if (result === 'capped') toast.info('Cantidad máxima alcanzada', 'Ajustamos la cantidad al stock disponible.')
  else toast.success('Añadido al carrito', props.product.name)
  // Feedback inmediato en el propio botón.
  justAdded.value = true
  clearTimeout(timer)
  timer = setTimeout(() => (justAdded.value = false), 1400)
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <article
    class="group relative flex flex-col overflow-hidden rounded-2xl border border-sage-100 bg-white shadow-soft transition-[transform,box-shadow,border-color] duration-300 ease-out hover:border-clinic-200 hover:shadow-lift motion-safe:hover:-translate-y-1"
    data-testid="product-card"
  >
    <div v-if="hasPromo" class="absolute left-3 top-3 z-10 rounded-full bg-promo-600 px-2.5 py-1 text-xs font-bold text-white shadow-soft" data-testid="promo-badge">
      -{{ product.discount_percent }}%
    </div>
    <RouterLink :to="{ name: 'product', params: { slug: product.slug } }" class="block" :aria-label="`Ver ${product.name}`">
      <div class="overflow-hidden bg-white p-3 pb-0">
        <div class="overflow-hidden rounded-xl transition-transform duration-500 ease-out motion-safe:group-hover:scale-[1.03]">
          <ProductIllustration :kind="product.illustration" :image-url="product.image_url" :alt="product.name" />
        </div>
      </div>
    </RouterLink>
    <div class="flex flex-1 flex-col gap-1.5 p-4">
      <div class="flex items-center justify-between gap-2">
        <span class="truncate text-[11px] font-bold uppercase tracking-[0.14em] text-sage-700">{{ product.brand }}</span>
        <StockBadge :status="product.stock_status" :stock="product.stock" />
      </div>
      <h3 class="line-clamp-2 min-h-[2.6rem] font-sans text-[15px] font-semibold leading-snug text-ink-900">
        <RouterLink :to="{ name: 'product', params: { slug: product.slug } }" class="hover:text-clinic-700">{{ product.name }}</RouterLink>
      </h3>
      <p class="text-xs text-ink-500">{{ product.presentation }}<template v-if="product.category"> · {{ product.category.name }}</template></p>

      <div class="mt-auto pt-3">
        <p v-if="hasPromo" class="text-xs text-ink-500">
          <span class="sr-only">Precio anterior:</span>
          <s>{{ formatMoney(product.compare_at_price_cents ?? 0) }}</s>
        </p>
        <p class="text-xl font-extrabold" :class="hasPromo ? 'text-promo-700' : 'text-ink-900'" data-testid="product-price">
          <span v-if="hasPromo" class="sr-only">Precio de oferta:</span>{{ formatMoney(product.price_cents) }}
        </p>
        <button
          type="button"
          class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold shadow-soft transition-[transform,background-color] motion-safe:active:scale-95 disabled:cursor-not-allowed disabled:shadow-none"
          :class="soldOut ? 'bg-sage-50 text-ink-500' : justAdded ? 'bg-clinic-700 text-white' : 'bg-clinic-600 text-white hover:bg-clinic-700'"
          :disabled="soldOut"
          :aria-label="soldOut ? `Agotado: ${product.name}` : justAdded ? `Agregado al carrito: ${product.name}` : `Agregar ${product.name} al carrito`"
          data-testid="add-to-cart"
          @click="addToCart"
        >
          <template v-if="soldOut">Agotado</template>
          <template v-else-if="justAdded"><Check class="h-4 w-4" aria-hidden="true" /> Agregado</template>
          <template v-else><ShoppingCart class="h-4 w-4" aria-hidden="true" /> Agregar</template>
        </button>
      </div>
    </div>
  </article>
</template>
