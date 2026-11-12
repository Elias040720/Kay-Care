<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { CheckCircle2, Printer } from 'lucide-vue-next'
import DemoBanner from '@/components/DemoBanner.vue'
import EmptyState from '@/components/EmptyState.vue'
import { storeApi } from '@/api/store'
import type { Order } from '@/types'
import { formatDate, formatMoney, ORDER_STATUS_LABELS } from '@/utils/format'

const props = defineProps<{ number: string }>()
const route = useRoute()
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))

const order = ref<Order | null>(null)
const loading = ref(true)
const missing = ref(false)

onMounted(async () => {
  if (!token.value) {
    missing.value = true
    loading.value = false
    return
  }
  try {
    order.value = await storeApi.order(props.number, token.value)
  } catch {
    missing.value = true
  } finally {
    loading.value = false
  }
})

function print() {
  window.print()
}
</script>

<template>
  <div class="container-page max-w-3xl py-8 sm:py-12">
    <div class="mb-6"><DemoBanner /></div>

    <div v-if="loading" class="card space-y-4 p-8" aria-busy="true">
      <div class="skeleton h-12 w-12 rounded-full" />
      <div class="skeleton h-8 w-2/3" />
      <div class="skeleton h-4 w-1/2" />
      <div class="skeleton h-32 w-full" />
    </div>

    <EmptyState v-else-if="missing" title="No encontramos ese pedido" description="El enlace de confirmación no es válido o ha expirado. Los pedidos simulados solo se consultan con su enlace.">
      <RouterLink to="/catalogo" class="btn-primary">Volver al catálogo</RouterLink>
    </EmptyState>

    <article v-else-if="order" class="card overflow-hidden" data-testid="confirmation">
      <div class="bg-gradient-to-br from-sage-50 to-teal-50 px-6 py-8 text-center sm:px-10">
        <CheckCircle2 class="mx-auto h-14 w-14 text-sage-600 motion-safe:animate-rise" aria-hidden="true" />
        <h1 class="mt-4 text-3xl" data-testid="confirmation-title">Pedido realizado en entorno de demostración</h1>
        <p class="mt-2 text-sm text-ink-500">Número de pedido</p>
        <p class="mt-1 font-mono text-2xl font-semibold tracking-wider text-ink-900" data-testid="order-number">{{ order.number }}</p>
        <p class="mt-3 inline-flex rounded-full bg-white/80 px-3 py-1 text-xs font-medium text-sage-700">
          Estado: {{ ORDER_STATUS_LABELS[order.status] }} · {{ formatDate(order.created_at) }}
        </p>
      </div>

      <div class="px-6 py-6 sm:px-10">
        <p class="text-sm text-ink-500">Comprador ficticio: <span class="font-semibold text-ink-700">{{ order.buyer_alias }}</span></p>
        <table class="mt-4 w-full text-sm">
          <caption class="sr-only">Detalle del pedido simulado</caption>
          <thead>
            <tr class="border-b border-cream-200 text-left text-xs uppercase tracking-wider text-ink-400">
              <th scope="col" class="py-2 font-medium">Producto</th>
              <th scope="col" class="py-2 text-center font-medium">Cant.</th>
              <th scope="col" class="py-2 text-right font-medium">Importe</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in order.items" :key="item.sku" class="border-b border-cream-100">
              <td class="py-3">
                <span class="font-medium">{{ item.product_name }}</span>
                <span class="block text-xs text-ink-400">{{ item.sku }} · {{ formatMoney(item.unit_price_cents) }} c/u</span>
              </td>
              <td class="py-3 text-center">{{ item.quantity }}</td>
              <td class="py-3 text-right font-medium">{{ formatMoney(item.line_total_cents) }}</td>
            </tr>
          </tbody>
          <tfoot class="text-sm">
            <tr><td colspan="2" class="pt-4 text-ink-500">Subtotal (sin IGV)</td><td class="pt-4 text-right">{{ formatMoney(order.subtotal_cents) }}</td></tr>
            <tr><td colspan="2" class="pt-1 text-ink-500">IGV (18 %)</td><td class="pt-1 text-right">{{ formatMoney(order.tax_cents) }}</td></tr>
            <tr><td colspan="2" class="pt-2 text-base font-semibold">Total simulado</td><td class="pt-2 text-right text-base font-semibold" data-testid="order-total">{{ formatMoney(order.total_cents) }}</td></tr>
          </tfoot>
        </table>

        <p class="mt-6 rounded-xl bg-sage-50 px-4 py-3 text-xs text-ink-500">
          Este pedido existe solo en la base de datos de demostración. No se realizó ningún cobro ni habrá entrega.
        </p>

        <div class="mt-6 flex flex-wrap gap-2">
          <RouterLink to="/catalogo" class="btn-primary">Seguir explorando</RouterLink>
          <button type="button" class="btn-ghost" @click="print"><Printer class="h-4 w-4" aria-hidden="true" /> Imprimir comprobante demo</button>
        </div>
      </div>
    </article>
  </div>
</template>
