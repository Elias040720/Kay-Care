<script setup lang="ts">
import { FlaskConical } from 'lucide-vue-next'
import { DEMO_NOTICE } from '@/utils/format'

withDefaults(defineProps<{ variant?: 'bar' | 'block' }>(), { variant: 'block' })
</script>

<template>
  <div
    v-if="variant === 'bar'"
    class="bg-ink-900 px-4 py-2 text-center text-[11px] font-semibold uppercase tracking-[0.14em] text-cream-100 sm:text-xs"
    role="note"
    data-testid="demo-notice"
  >
    {{ DEMO_NOTICE }}
  </div>
  <div
    v-else
    class="flex items-start gap-3 rounded-2xl border border-honey-600/20 bg-honey-100 px-4 py-3 text-honey-600"
    role="note"
    aria-label="Aviso de proyecto académico"
    data-testid="demo-notice"
  >
    <FlaskConical class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
    <div>
      <p class="text-sm font-bold tracking-wide">{{ DEMO_NOTICE }}</p>
      <p class="mt-0.5 text-xs text-honey-600/90">
        Productos y marcas ficticios. No se cobra, no se envía nada y no se solicitan datos personales.
      </p>
    </div>
  </div>
</template>
