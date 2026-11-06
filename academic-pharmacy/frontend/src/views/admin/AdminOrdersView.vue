<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Search } from 'lucide-vue-next'
import { adminApi } from '@/api/admin'
import { ApiError } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import type { Order, OrderStatus, PageMeta } from '@/types'
import { formatDate, formatMoney, ORDER_STATUS_LABELS, ORDER_STATUS_TONES } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()

const STATUSES = Object.keys(ORDER_STATUS_LABELS) as OrderStatus[]
const initial = typeof route.query.estado === 'string' && STATUSES.includes(route.query.estado as OrderStatus) ? (route.query.estado as OrderStatus) : ''

const orders = ref<Order[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(true)
const status = ref<OrderStatus | ''>(initial)
const q = ref('')
const page = ref(1)
let debounce: ReturnType<typeof setTimeout> | undefined

async function load() {
  loading.value = true
  try {
    const result = await adminApi.orders({ status: status.value || undefined, q: q.value || undefined, page: page.value, per_page: 15 })
    orders.value = result.data
    meta.value = result.meta
  } catch (e) {
    toast.error('No se pudieron cargar los pedidos', e instanceof ApiError ? e.message : undefined)
  } finally {
    loading.value = false
  }
}

function setStatus(value: OrderStatus | '') {
  status.value = value
  page.value = 1
  router.replace({ query: value ? { estado: value } : {} })
  load()
}

function onSearch() {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-3xl">Pedidos simulados</h1>
      <p class="text-sm text-ink-500">{{ meta?.total ?? '…' }} pedidos ficticios registrados</p>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filtrar por estado">
        <button type="button" class="chip px-3 py-1.5 text-sm" :class="status === '' ? 'bg-ink-900 text-white' : 'bg-white text-ink-700 ring-1 ring-cream-300'" :aria-pressed="status === ''" @click="setStatus('')">Todos</button>
        <button
          v-for="s in STATUSES"
          :key="s"
          type="button"
          class="chip px-3 py-1.5 text-sm"
          :class="status === s ? 'bg-ink-900 text-white' : 'bg-white text-ink-700 ring-1 ring-cream-300'"
          :aria-pressed="status === s"
          @click="setStatus(s)"
        >
          {{ ORDER_STATUS_LABELS[s] }}
        </button>
      </div>
      <div class="relative ml-auto w-full sm:w-64">
        <label for="order-search" class="sr-only">Buscar por número o alias</label>
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" aria-hidden="true" />
        <input id="order-search" v-model="q" type="search" maxlength="40" class="field pl-9" placeholder="N.º de pedido o alias" @input="onSearch" />
      </div>
    </div>

    <div class="card overflow-x-auto">
      <table class="w-full min-w-[720px] text-sm">
        <caption class="sr-only">Pedidos simulados</caption>
        <thead class="bg-cream-50 text-left text-xs uppercase tracking-wider text-ink-400">
          <tr>
            <th scope="col" class="px-4 py-3 font-medium">Pedido</th>
            <th scope="col" class="px-4 py-3 font-medium">Comprador ficticio</th>
            <th scope="col" class="px-4 py-3 font-medium">Fecha</th>
            <th scope="col" class="px-4 py-3 text-center font-medium">Artículos</th>
            <th scope="col" class="px-4 py-3 text-right font-medium">Total</th>
            <th scope="col" class="px-4 py-3 font-medium">Estado</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading"><tr v-for="n in 6" :key="n" class="border-t border-cream-100"><td colspan="6" class="px-4 py-3"><div class="skeleton h-6" /></td></tr></template>
          <tr v-for="order in orders" v-else :key="order.id" class="border-t border-cream-100 hover:bg-cream-50/60" data-testid="order-row">
            <td class="px-4 py-3"><RouterLink :to="{ name: 'admin-order', params: { id: order.id } }" class="font-mono font-semibold text-sage-700 hover:underline">{{ order.number }}</RouterLink></td>
            <td class="px-4 py-3">{{ order.buyer_alias }}</td>
            <td class="px-4 py-3 text-ink-500">{{ formatDate(order.created_at) }}</td>
            <td class="px-4 py-3 text-center">{{ order.items_count }}</td>
            <td class="px-4 py-3 text-right font-medium">{{ formatMoney(order.total_cents) }}</td>
            <td class="px-4 py-3"><span class="chip" :class="ORDER_STATUS_TONES[order.status]">{{ ORDER_STATUS_LABELS[order.status] }}</span></td>
          </tr>
          <tr v-if="!loading && orders.length === 0"><td colspan="6" class="px-4 py-10 text-center text-ink-400">No hay pedidos con esos filtros.</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center justify-end gap-2 text-sm">
      <button type="button" class="btn-ghost" :disabled="page <= 1" @click="page--; load()">Anterior</button>
      <span class="text-ink-500">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button type="button" class="btn-ghost" :disabled="page >= meta.last_page" @click="page++; load()">Siguiente</button>
    </div>
  </div>
</template>
