<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Lock, ShoppingBag } from 'lucide-vue-next'
import DemoBanner from '@/components/DemoBanner.vue'
import EmptyState from '@/components/EmptyState.vue'
import ProductIllustration from '@/components/ProductIllustration.vue'
import { storeApi } from '@/api/store'
import { ApiError } from '@/api/client'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'
import type { CartIssue } from '@/types'
import { formatMoney, newIdempotencyKey, validateBuyerAlias } from '@/utils/format'
import { readJson, removeKey, writeJson } from '@/utils/storage'

const cart = useCartStore()
const toast = useToastStore()
const router = useRouter()

const alias = ref('')
const accepted = ref(false)
const submitting = ref(false)
const touched = ref(false)
const serverErrors = ref<Record<string, string[]>>({})
const formError = ref<string | null>(null)

const aliasError = computed(() => serverErrors.value.buyer_alias?.[0] ?? (touched.value ? validateBuyerAlias(alias.value) : null))
const termsError = computed(() => serverErrors.value.accept_demo_terms?.[0] ?? (touched.value && !accepted.value ? 'Confirma que entiendes que es una simulación.' : null))
const canSubmit = computed(() => cart.lines.length > 0 && !submitting.value && !cart.hasIssues)

const KEY_STORAGE = 'kcf.checkout.idempotency'

/**
 * La clave se conserva mientras el contenido del carrito no cambie: si la
 * red falla y el usuario reintenta, el servidor reconoce la clave y no crea
 * un pedido duplicado.
 */
function idempotencyKeyFor(signature: string): string {
  const saved = readJson<{ key: string; signature: string } | null>('session', KEY_STORAGE, null)
  if (saved && saved.signature === signature) return saved.key
  const key = newIdempotencyKey()
  writeJson('session', KEY_STORAGE, { key, signature })
  return key
}

onMounted(() => {
  cart.validate().catch(() => {
    /* el error se mostrará al enviar */
  })
})

async function submit() {
  touched.value = true
  serverErrors.value = {}
  formError.value = null
  if (validateBuyerAlias(alias.value) || !accepted.value || !canSubmit.value) return

  submitting.value = true
  const items = cart.payload()
  const signature = JSON.stringify({ alias: alias.value.trim().toLowerCase(), items })
  try {
    const result = await storeApi.checkout({ buyer_alias: alias.value.trim(), accept_demo_terms: accepted.value, items }, idempotencyKeyFor(signature))
    removeKey('session', KEY_STORAGE)
    cart.clear()
    toast.success(result.replayed ? 'Pedido ya registrado' : 'Pedido simulado registrado', result.data.number)
    await router.push({ name: 'confirmation', params: { number: result.data.number }, query: { token: result.lookup_token } })
  } catch (e) {
    if (e instanceof ApiError) {
      if (e.code === 'insufficient_stock' || e.code === 'product_unavailable') {
        cart.applyIssues((e.details.issues as CartIssue[]) ?? [])
        formError.value = 'El stock cambió mientras comprabas. Ajusta las cantidades marcadas e inténtalo de nuevo.'
        removeKey('session', KEY_STORAGE)
      } else if (e.code === 'validation_failed') {
        serverErrors.value = e.errors
        formError.value = 'Revisa los campos marcados.'
      } else {
        formError.value = e.message
      }
    } else {
      formError.value = 'Ocurrió un error inesperado.'
    }
    toast.error('No se pudo completar el pedido simulado', formError.value ?? undefined)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="container-page py-8 sm:py-10">
    <div class="mb-6"><DemoBanner /></div>
    <h1 class="text-3xl sm:text-4xl">Checkout simulado</h1>
    <p class="mt-1 text-sm text-ink-500">Sin pagos, sin envíos y sin datos personales. Solo un identificador ficticio.</p>

    <EmptyState v-if="cart.lines.length === 0" class="mt-8" :icon="ShoppingBag" title="No hay productos en el carrito" description="Añade productos ficticios desde el catálogo para simular un pedido.">
      <RouterLink to="/catalogo" class="btn-primary">Ir al catálogo</RouterLink>
    </EmptyState>

    <div v-else class="mt-8 grid gap-8 lg:grid-cols-[1fr_380px]">
      <form class="card space-y-6 p-6 sm:p-8" novalidate data-testid="checkout-form" @submit.prevent="submit">
        <div>
          <label for="buyer-alias" class="field-label">Identificador ficticio de comprador</label>
          <input
            id="buyer-alias"
            v-model="alias"
            type="text"
            maxlength="40"
            autocomplete="off"
            spellcheck="false"
            placeholder="Ej.: Comprador Demo 07"
            class="field"
            :aria-invalid="!!aliasError"
            aria-describedby="alias-help alias-error"
            data-testid="buyer-alias"
            @blur="touched = true"
          />
          <p id="alias-help" class="mt-1.5 text-xs text-ink-400">No escribas tu nombre real, DNI, teléfono, correo ni dirección.</p>
          <p v-if="aliasError" id="alias-error" class="field-error" role="alert">{{ aliasError }}</p>
        </div>

        <div class="rounded-2xl bg-cream-100 p-4 text-sm text-ink-500">
          <p class="flex items-center gap-2 font-semibold text-ink-700"><Lock class="h-4 w-4" aria-hidden="true" /> Pago simulado</p>
          <p class="mt-1">Esta tienda no tiene pasarela de pago. Al confirmar se registra un pedido ficticio para la demostración y se descuenta el stock demo.</p>
        </div>

        <div>
          <label class="flex cursor-pointer items-start gap-3 text-sm text-ink-700">
            <input v-model="accepted" type="checkbox" class="mt-0.5 h-4 w-4 accent-sage-600" :aria-invalid="!!termsError" data-testid="accept-terms" />
            <span>Entiendo que es un <strong>proyecto académico</strong>: no se realizan ventas reales, no se cobra nada y no recibiré ningún producto.</span>
          </label>
          <p v-if="termsError" class="field-error" role="alert">{{ termsError }}</p>
        </div>

        <p v-if="formError" class="rounded-xl bg-clay-100 px-4 py-3 text-sm text-clay-700" role="alert" data-testid="checkout-error">{{ formError }}</p>

        <button type="submit" class="btn-primary w-full py-3 text-base" :disabled="!canSubmit" data-testid="place-order">
          {{ submitting ? 'Registrando pedido simulado…' : 'Confirmar pedido simulado' }}
        </button>
      </form>

      <aside class="card h-fit p-6" aria-labelledby="summary-title">
        <h2 id="summary-title" class="text-xl">Resumen</h2>
        <ul class="mt-4 divide-y divide-cream-200">
          <li v-for="line in cart.lines" :key="line.product_id" class="flex items-center gap-3 py-3">
            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg">
              <ProductIllustration :kind="line.illustration" :image-url="line.image_url" :alt="line.name" size="sm" />
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium">{{ line.name }}</p>
              <p class="text-xs text-ink-500">{{ line.quantity }} × {{ formatMoney(line.price_cents) }}</p>
              <p v-if="cart.issueFor(line.product_id)" class="text-xs text-clay-700" role="alert">
                {{ cart.issueFor(line.product_id)?.code === 'unavailable' ? 'No disponible' : `Stock disponible: ${cart.issueFor(line.product_id)?.available}` }}
              </p>
            </div>
            <p class="text-sm font-semibold">{{ formatMoney(line.price_cents * line.quantity) }}</p>
          </li>
        </ul>
        <div class="mt-4 flex items-center justify-between border-t border-cream-200 pt-4">
          <span class="text-sm text-ink-500">Total estimado</span>
          <span class="text-xl font-semibold" data-testid="checkout-total">{{ formatMoney(cart.totalCents) }}</span>
        </div>
        <p class="mt-1 text-xs text-ink-400">IGV incluido. El servidor recalcula el importe definitivo.</p>
        <button v-if="cart.hasIssues" type="button" class="btn-ghost mt-4 w-full" @click="cart.open()">Ajustar carrito</button>
      </aside>
    </div>
  </div>
</template>
