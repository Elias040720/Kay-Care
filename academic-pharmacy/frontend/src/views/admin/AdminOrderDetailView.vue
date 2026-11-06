<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ChevronLeft } from 'lucide-vue-next'
import ModalDialog from '@/components/admin/ModalDialog.vue'
import { adminApi } from '@/api/admin'
import { ApiError } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import type { Order, OrderStatus } from '@/types'
import { formatDate, formatMoney, ORDER_STATUS_LABELS, ORDER_STATUS_TONES } from '@/utils/format'

const props = defineProps<{ id: number }>()
const toast = useToastStore()
const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const pending = ref<OrderStatus | null>(null)
const note = ref('')
const saving = ref(false)

async function load() {
  loading.value = true
  error.value = null
  try {
    order.value = await adminApi.order(props.id)
  } catch (e) {
    error.value = e instanceof ApiError && e.status === 404 ? 'Pedido no encontrado.' : 'No se pudo cargar el pedido.'
  } finally {
    loading.value = false
  }
}

function ask(status: OrderStatus) {
  pending.value = status
  note.value = ''
}

async function apply() {
  if (!order.value || !pending.value) return
  saving.value = true
  try {
    order.value = await adminApi.updateOrderStatus(order.value.id, pending.value, note.value)
    toast.success('Estado actualizado', ORDER_STATUS_LABELS[order.value.status])
    pending.value = null
  } catch (e) {
    toast.error('No se pudo cambiar el estado', e instanceof ApiError ? e.message : undefined)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <RouterLink :to="{ name: 'admin-orders' }" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-500 hover:text-sage-700"><ChevronLeft class="h-4 w-4" aria-hidden="true" /> Pedidos</RouterLink>

    <div v-if="loading" class="space-y-4" aria-busy="true"><div class="skeleton h-10 w-72" /><div class="skeleton h-64 w-full rounded-xl2" /></div>
    <div v-else-if="error" class="card p-6 text-sm text-clay-700" role="alert">{{ error }}</div>

    <template v-else-if="order">
      <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 class="font-mono text-2xl font-semibold sm:text-3xl" data-testid="admin-order-number">{{ order.number }}</h1>
          <p class="text-sm text-ink-500">{{ order.buyer_alias }} · {{ formatDate(order.created_at) }}</p>
        </div>
        <span class="chip px-3 py-1 text-sm" :class="ORDER_STATUS_TONES[order.status]" data-testid="admin-order-status">{{ ORDER_STATUS_LABELS[order.status] }}</span>
      </div>

      <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
        <section class="card overflow-hidden" aria-labelledby="items-title">
          <h2 id="items-title" class="border-b border-cream-200 p-4 font-sans text-sm font-semibold">Productos</h2>
          <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wider text-ink-400"><tr><th scope="col" class="px-4 py-2 font-medium">Producto</th><th scope="col" class="px-4 py-2 text-center font-medium">Cant.</th><th scope="col" class="px-4 py-2 text-right font-medium">Importe</th></tr></thead>
            <tbody>
              <tr v-for="item in order.items" :key="item.sku" class="border-t border-cream-100">
                <td class="px-4 py-3"><p class="font-medium">{{ item.product_name }}</p><p class="text-xs text-ink-400">{{ item.sku }} · {{ formatMoney(item.unit_price_cents) }}</p></td>
                <td class="px-4 py-3 text-center">{{ item.quantity }}</td>
                <td class="px-4 py-3 text-right">{{ formatMoney(item.line_total_cents) }}</td>
              </tr>
            </tbody>
            <tfoot class="border-t border-cream-200">
              <tr><td colspan="2" class="px-4 pt-3 text-ink-500">Subtotal</td><td class="px-4 pt-3 text-right">{{ formatMoney(order.subtotal_cents) }}</td></tr>
              <tr><td colspan="2" class="px-4 text-ink-500">IGV</td><td class="px-4 text-right">{{ formatMoney(order.tax_cents) }}</td></tr>
              <tr><td colspan="2" class="px-4 pb-4 pt-1 font-semibold">Total</td><td class="px-4 pb-4 pt-1 text-right font-semibold">{{ formatMoney(order.total_cents) }}</td></tr>
            </tfoot>
          </table>
        </section>

        <div class="space-y-6">
          <section class="card p-4" aria-labelledby="actions-title">
            <h2 id="actions-title" class="font-sans text-sm font-semibold">Cambiar estado</h2>
            <div v-if="order.allowed_transitions.length" class="mt-3 flex flex-wrap gap-2">
              <button
                v-for="next in order.allowed_transitions"
                :key="next"
                type="button"
                :class="next === 'cancelled' ? 'btn-danger' : 'btn-primary'"
                :data-testid="`transition-${next}`"
                @click="ask(next)"
              >
                {{ next === 'cancelled' ? 'Cancelar pedido' : `Marcar como «${ORDER_STATUS_LABELS[next]}»` }}
              </button>
            </div>
            <p v-else class="mt-2 text-sm text-ink-500">Estado final: no admite más cambios.</p>
          </section>

          <section class="card p-4" aria-labelledby="history-title">
            <h2 id="history-title" class="font-sans text-sm font-semibold">Historial</h2>
            <ol class="mt-3 space-y-3 border-l border-cream-300 pl-4">
              <li v-for="(h, i) in order.history" :key="i" class="relative text-sm">
                <span class="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full bg-sage-500" aria-hidden="true" />
                <p class="font-medium">{{ ORDER_STATUS_LABELS[h.to_status] }}</p>
                <p class="text-xs text-ink-400">{{ formatDate(h.created_at) }}<template v-if="h.admin"> · {{ h.admin }}</template></p>
                <p v-if="h.note" class="text-xs text-ink-500">{{ h.note }}</p>
              </li>
            </ol>
          </section>
        </div>
      </div>
    </template>

    <ModalDialog :open="!!pending" title="Confirmar cambio de estado" @close="pending = null">
      <p class="text-sm text-ink-700">
        El pedido pasará a <strong>{{ pending ? ORDER_STATUS_LABELS[pending] : '' }}</strong>.
        <template v-if="pending === 'cancelled'"> Las unidades volverán al stock.</template>
      </p>
      <label for="status-note" class="field-label mt-4">Nota (opcional)</label>
      <input id="status-note" v-model="note" class="field" maxlength="200" />
      <div class="mt-5 flex justify-end gap-2">
        <button type="button" class="btn-ghost" @click="pending = null">Volver</button>
        <button type="button" :class="pending === 'cancelled' ? 'btn-danger' : 'btn-primary'" :disabled="saving" data-testid="confirm-transition" @click="apply">{{ saving ? 'Guardando…' : 'Confirmar' }}</button>
      </div>
    </ModalDialog>
  </div>
</template>
