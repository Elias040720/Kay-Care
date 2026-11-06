<script setup lang="ts">
import { computed } from 'vue'
import type { StockStatus } from '@/types'
import { STOCK_LABELS } from '@/utils/format'

const props = defineProps<{ status: StockStatus; stock?: number }>()

const tone = computed(() => ({
  in_stock: 'bg-sage-100 text-sage-700',
  low_stock: 'bg-honey-100 text-honey-600',
  out_of_stock: 'bg-clay-100 text-clay-700',
})[props.status])

const label = computed(() =>
  props.status === 'low_stock' && props.stock !== undefined ? `Quedan ${props.stock}` : STOCK_LABELS[props.status],
)
</script>

<template>
  <span class="chip" :class="tone" data-testid="stock-badge">
    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
    {{ label }}
  </span>
</template>
