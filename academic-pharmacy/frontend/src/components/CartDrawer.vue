<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, ShoppingBag, Trash2, X } from 'lucide-vue-next'
import ProductIllustration from './ProductIllustration.vue'
import QuantityStepper from './QuantityStepper.vue'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/api/client'
import { formatMoney } from '@/utils/format'

const cart = useCartStore()
const toast = useToastStore()
const router = useRouter()
const panel = ref<HTMLElement | null>(null)
let previouslyFocused: HTMLElement | null = null

watch(
  () => cart.isOpen,
  async (open) => {
    if (open) {
      previouslyFocused = document.activeElement as HTMLElement | null
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      panel.value?.focus()
      cart.validate().catch((e: unknown) => {
        toast.error('No se pudo verificar el stock', e instanceof ApiError ? e.message : undefined)
      })
    } else {
      document.removeEventListener('keydown', onKeydown)
      previouslyFocused?.focus?.()
    }
  },
)

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') cart.close()
  if (event.key === 'Tab' && panel.value) {
    // Trampa de foco simple dentro del drawer.
    const focusable = panel.value.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], input:not([disabled])')
    if (focusable.length === 0) return
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first.focus()
    }
  }
}

function goToCheckout() {
  cart.close()
  router.push({ name: 'checkout' })
}

function issueText(productId: number): string | null {
  const issue = cart.issueFor(productId)
  if (!issue) return null
  return issue.code === 'unavailable' ? 'Ya no está disponible; retíralo para continuar.' : `Solo quedan ${issue.available} unidades.`
}
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="cart.isOpen" class="fixed inset-0 z-40 bg-ink-900/30 backdrop-blur-[2px]" aria-hidden="true" @click="cart.close()" />
    </Transition>
    <Transition name="drawer">
      <aside
        v-if="cart.isOpen"
        ref="panel"
        class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-cream-50 shadow-lift focus:outline-none"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cart-title"
        tabindex="-1"
        data-testid="cart-drawer"
      >
        <header class="flex items-center justify-between border-b border-cream-200 px-5 py-4">
          <div>
            <h2 id="cart-title" class="text-xl">Tu carrito</h2>
            <p class="text-xs text-ink-500">{{ cart.count }} {{ cart.count === 1 ? 'artículo' : 'artículos' }} · compra simulada</p>
          </div>
          <button type="button" class="btn-icon" aria-label="Cerrar carrito" @click="cart.close()">
            <X class="h-5 w-5" aria-hidden="true" />
          </button>
        </header>

        <div v-if="cart.lines.length === 0" class="flex flex-1 flex-col items-center justify-center px-8 text-center">
          <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-sage-50 text-sage-600">
            <ShoppingBag class="h-7 w-7" aria-hidden="true" />
          </div>
          <p class="font-display text-lg">Tu carrito está vacío</p>
          <p class="mt-1 text-sm text-ink-500">Explora el catálogo demostrativo y añade productos ficticios.</p>
          <button type="button" class="btn-primary mt-5" @click="cart.close(); router.push({ name: 'catalog' })">Ver catálogo</button>
        </div>

        <ul v-else class="flex-1 divide-y divide-cream-200 overflow-y-auto px-5">
          <TransitionGroup name="list">
            <li v-for="line in cart.lines" :key="line.product_id" class="flex gap-4 py-4" data-testid="cart-line">
              <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl">
                <ProductIllustration :kind="line.illustration" :image-url="line.image_url" :alt="line.name" size="sm" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-sage-600">{{ line.brand }}</p>
                    <p class="text-sm font-semibold leading-snug text-ink-900">{{ line.name }}</p>
                  </div>
                  <button type="button" class="btn-icon -mr-2 h-8 w-8" :aria-label="`Quitar ${line.name}`" @click="cart.remove(line.product_id)">
                    <Trash2 class="h-4 w-4" aria-hidden="true" />
                  </button>
                </div>
                <p v-if="issueText(line.product_id)" class="mt-1 flex items-center gap-1 text-xs text-clay-700" role="alert">
                  <AlertTriangle class="h-3.5 w-3.5" aria-hidden="true" /> {{ issueText(line.product_id) }}
                </p>
                <div class="mt-2 flex items-center justify-between">
                  <QuantityStepper
                    :model-value="line.quantity"
                    :max="Math.max(1, cart.maxFor(line))"
                    :label="line.name"
                    size="sm"
                    @update:model-value="(q) => cart.setQuantity(line.product_id, q)"
                  />
                  <p class="text-sm font-semibold">{{ formatMoney(line.price_cents * line.quantity) }}</p>
                </div>
              </div>
            </li>
          </TransitionGroup>
        </ul>

        <footer v-if="cart.lines.length > 0" class="border-t border-cream-200 px-5 py-5">
          <div class="flex items-center justify-between text-sm">
            <span class="text-ink-500">Total estimado (IGV incluido)</span>
            <span class="text-lg font-semibold" data-testid="cart-total">{{ formatMoney(cart.totalCents) }}</span>
          </div>
          <p class="mt-1 text-xs text-ink-400">El precio final lo confirma el servidor. No se realiza ningún cobro.</p>
          <div class="mt-4 grid gap-2">
            <button type="button" class="btn-primary w-full" :disabled="cart.hasIssues || cart.validating" data-testid="go-checkout" @click="goToCheckout">
              {{ cart.validating ? 'Verificando stock…' : 'Ir al checkout simulado' }}
            </button>
            <button type="button" class="btn-ghost w-full" @click="cart.close()">Seguir explorando</button>
          </div>
        </footer>
      </aside>
    </Transition>
  </Teleport>
</template>
