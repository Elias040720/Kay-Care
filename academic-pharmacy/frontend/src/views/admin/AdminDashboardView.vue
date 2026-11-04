<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { AlertTriangle, Banknote, ClipboardList, Package, RefreshCw } from 'lucide-vue-next'
import { adminApi } from '@/api/admin'
import { ApiError } from '@/api/client'
import type { DashboardSummary, OrderStatus } from '@/types'
import { formatDate, formatMoney, ORDER_STATUS_LABELS, ORDER_STATUS_TONES } from '@/utils/format'

const data = ref<DashboardSummary | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    data.value = await adminApi.dashboard()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo cargar el panel.'
  } finally {
    loading.value = false
  }
}
onMounted(load)

const maxDaily = computed(() => Math.max(1, ...(data.value?.daily.map((d) => d.revenue_cents) ?? [1])))
const statuses = computed(() => Object.entries(data.value?.orders_by_status ?? {}) as [OrderStatus, number][])
const dayLabel = (iso: string) => new Intl.DateTimeFormat('es-PE', { day: '2-digit', month: 'short' }).format(new Date(`${iso}T12:00:00`))
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-3xl">Panel</h1>
        <p class="text-sm text-ink-500">{{ data?.disclaimer ?? 'Indicadores de la tienda demostrativa.' }}</p>
      </div>
      <button type="button" class="btn-ghost" :disabled="loading" @click="load"><RefreshCw class="h-4 w-4" :class="loading && 'motion-safe:animate-spin'" aria-hidden="true" /> Actualizar</button>
    </div>

    <div v-if="error" class="card p-6 text-sm text-clay-700" role="alert">{{ error }}</div>

    <div v-else-if="loading && !data" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-busy="true">
      <div v-for="n in 4" :key="n" class="skeleton h-28 rounded-xl2" />
      <div class="skeleton h-64 rounded-xl2 sm:col-span-2 xl:col-span-3" />
      <div class="skeleton h-64 rounded-xl2" />
    </div>

    <template v-else-if="data">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5">
          <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-ink-400"><Banknote class="h-4 w-4" aria-hidden="true" /> Ventas simuladas</p>
          <p class="mt-2 text-2xl font-semibold" data-testid="kpi-revenue">{{ formatMoney(data.revenue_cents) }}</p>
          <p class="text-xs text-ink-500">Excluye cancelados</p>
        </div>
        <div class="card p-5">
          <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-ink-400"><ClipboardList class="h-4 w-4" aria-hidden="true" /> Pedidos</p>
          <p class="mt-2 text-2xl font-semibold" data-testid="kpi-orders">{{ data.orders_total }}</p>
          <p class="text-xs text-ink-500">Ticket promedio {{ formatMoney(data.average_ticket_cents) }}</p>
        </div>
        <div class="card p-5">
          <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-ink-400"><Package class="h-4 w-4" aria-hidden="true" /> Productos activos</p>
          <p class="mt-2 text-2xl font-semibold">{{ data.products_active }} <span class="text-sm font-normal text-ink-400">/ {{ data.products_total }}</span></p>
          <p class="text-xs text-ink-500">{{ data.units_in_stock }} unidades en stock</p>
        </div>
        <div class="card p-5">
          <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-ink-400"><AlertTriangle class="h-4 w-4" aria-hidden="true" /> Stock bajo</p>
          <p class="mt-2 text-2xl font-semibold text-clay-700">{{ data.low_stock.length }}</p>
          <RouterLink :to="{ name: 'admin-stock' }" class="text-xs font-medium text-sage-700 hover:underline">Gestionar stock</RouterLink>
        </div>
      </div>

      <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <section class="card p-5 xl:col-span-2" aria-labelledby="daily-title">
          <h2 id="daily-title" class="font-sans text-sm font-semibold">Ventas simuladas por día (últimos 14 días)</h2>
          <div class="mt-6 flex h-48 items-end gap-1.5" role="img" :aria-label="`Gráfico de barras de ventas diarias; máximo ${formatMoney(maxDaily)}`">
            <div v-for="day in data.daily" :key="day.date" class="group flex flex-1 flex-col items-center justify-end gap-1">
              <div
                class="w-full origin-bottom rounded-t-md bg-gradient-to-t from-sage-500 to-teal-300 transition-transform duration-500"
                :style="{ height: `${Math.max(2, (day.revenue_cents / maxDaily) * 100)}%` }"
                :title="`${dayLabel(day.date)}: ${day.orders} pedidos, ${formatMoney(day.revenue_cents)}`"
              />
              <span class="text-[10px] text-ink-400">{{ dayLabel(day.date).split(' ')[0] }}</span>
            </div>
          </div>
        </section>

        <section class="card p-5" aria-labelledby="status-title">
          <h2 id="status-title" class="font-sans text-sm font-semibold">Pedidos por estado</h2>
          <ul class="mt-4 space-y-2">
            <li v-for="[status, total] in statuses" :key="status" class="flex items-center justify-between text-sm">
              <RouterLink :to="{ name: 'admin-orders', query: { estado: status } }" class="chip" :class="ORDER_STATUS_TONES[status]">{{ ORDER_STATUS_LABELS[status] }}</RouterLink>
              <span class="font-semibold">{{ total }}</span>
            </li>
          </ul>
        </section>

        <section class="card p-5 xl:col-span-2" aria-labelledby="top-title">
          <h2 id="top-title" class="font-sans text-sm font-semibold">Productos más vendidos</h2>
          <table class="mt-3 w-full text-sm">
            <thead><tr class="text-left text-xs uppercase tracking-wider text-ink-400"><th scope="col" class="py-2 font-medium">Producto</th><th scope="col" class="py-2 text-right font-medium">Unidades</th><th scope="col" class="py-2 text-right font-medium">Importe</th></tr></thead>
            <tbody>
              <tr v-for="item in data.top_products" :key="item.name" class="border-t border-cream-100">
                <td class="py-2">{{ item.name }}</td>
                <td class="py-2 text-right">{{ item.units }}</td>
                <td class="py-2 text-right">{{ formatMoney(item.revenue_cents) }}</td>
              </tr>
              <tr v-if="data.top_products.length === 0"><td colspan="3" class="py-4 text-center text-ink-400">Aún no hay ventas simuladas.</td></tr>
            </tbody>
          </table>
        </section>

        <section class="card p-5" aria-labelledby="low-title">
          <h2 id="low-title" class="font-sans text-sm font-semibold">Alertas de stock</h2>
          <ul class="mt-3 space-y-2 text-sm">
            <li v-for="item in data.low_stock" :key="item.id" class="flex items-center justify-between gap-2">
              <span class="truncate">{{ item.name }}</span>
              <span class="chip" :class="item.stock === 0 ? 'bg-clay-100 text-clay-700' : 'bg-honey-100 text-honey-600'">{{ item.stock }} u.</span>
            </li>
            <li v-if="data.low_stock.length === 0" class="text-ink-400">Sin alertas.</li>
          </ul>
        </section>
      </div>
      <p class="mt-4 text-xs text-ink-400">Generado {{ formatDate(data.generated_at) }}</p>
    </template>
  </div>
</template>
