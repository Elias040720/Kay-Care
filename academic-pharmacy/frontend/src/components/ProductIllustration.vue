<script setup lang="ts">
import { computed } from 'vue'
import type { Illustration } from '@/types'

/**
 * Ilustraciones vectoriales propias para los productos ficticios: evitan
 * depender de fotos reales de marcas y pesan unos pocos bytes.
 */
const props = withDefaults(defineProps<{ kind?: Illustration; imageUrl?: string | null; alt?: string; size?: 'sm' | 'md' | 'lg' }>(), {
  kind: 'capsule',
  imageUrl: null,
  alt: '',
  size: 'md',
})

const palettes: Record<Illustration, [string, string, string]> = {
  capsule: ['#DFEBE2', '#5E8C70', '#7CC6BF'],
  bottle: ['#D3EEEB', '#3A8F89', '#FAF6EF'],
  drops: ['#EEF8F7', '#52ABA4', '#C1D8C8'],
  tube: ['#F1F6F2', '#7AA78A', '#FAF6EF'],
  box: ['#F2EBDF', '#5E8C70', '#A9DDD7'],
  spray: ['#D3EEEB', '#2E736F', '#DFEBE2'],
  sachet: ['#FBF1D9', '#B8873A', '#FAF6EF'],
  jar: ['#DFEBE2', '#4A7159', '#7CC6BF'],
}

const colors = computed(() => palettes[props.kind] ?? palettes.capsule)
const padding = computed(() => ({ sm: 'p-3', md: 'p-6', lg: 'p-10' })[props.size])
</script>

<template>
  <div class="relative flex aspect-square w-full items-center justify-center overflow-hidden" :style="{ backgroundColor: colors[0] }">
    <img v-if="imageUrl" :src="imageUrl" :alt="alt" class="h-full w-full object-cover" loading="lazy" decoding="async" />
    <svg v-else viewBox="0 0 120 120" class="h-full w-full" :class="padding" role="img" :aria-label="alt || 'Ilustración del producto'">
      <circle cx="96" cy="22" r="14" :fill="colors[2]" opacity="0.6" />
      <g v-if="kind === 'capsule'">
        <rect x="26" y="48" width="68" height="28" rx="14" :fill="colors[1]" transform="rotate(-28 60 62)" />
        <rect x="26" y="48" width="34" height="28" rx="14" :fill="colors[2]" transform="rotate(-28 60 62)" />
        <circle cx="38" cy="92" r="8" :fill="colors[1]" opacity="0.35" />
      </g>
      <g v-else-if="kind === 'bottle'">
        <rect x="46" y="18" width="28" height="12" rx="3" :fill="colors[1]" />
        <rect x="36" y="30" width="48" height="72" rx="12" :fill="colors[1]" />
        <rect x="42" y="52" width="36" height="28" rx="5" :fill="colors[2]" />
        <rect x="48" y="60" width="24" height="4" rx="2" :fill="colors[1]" opacity="0.5" />
      </g>
      <g v-else-if="kind === 'drops'">
        <rect x="52" y="16" width="16" height="18" rx="5" :fill="colors[1]" />
        <rect x="42" y="34" width="36" height="64" rx="10" :fill="colors[1]" opacity="0.85" />
        <path d="M60 50c6 9 9 14 9 19a9 9 0 0 1-18 0c0-5 3-10 9-19z" fill="#FFFFFF" opacity="0.9" />
      </g>
      <g v-else-if="kind === 'tube'">
        <path d="M34 30h52l-8 64H42z" :fill="colors[1]" />
        <rect x="50" y="94" width="20" height="12" rx="3" :fill="colors[1]" opacity="0.7" />
        <rect x="42" y="46" width="36" height="22" rx="4" :fill="colors[2]" />
        <rect x="34" y="26" width="52" height="8" rx="2" :fill="colors[1]" opacity="0.6" />
      </g>
      <g v-else-if="kind === 'box'">
        <path d="M28 44l32-14 32 14v42l-32 14-32-14z" :fill="colors[1]" />
        <path d="M60 58l32-14v42l-32 14z" fill="#000" opacity="0.08" />
        <path d="M28 44l32 14 32-14" fill="none" :stroke="colors[2]" stroke-width="3" />
        <path d="M52 66h16M60 58v16" :stroke="colors[2]" stroke-width="5" stroke-linecap="round" />
      </g>
      <g v-else-if="kind === 'spray'">
        <rect x="50" y="14" width="20" height="10" rx="3" :fill="colors[1]" />
        <rect x="70" y="16" width="14" height="5" rx="2" :fill="colors[1]" opacity="0.7" />
        <rect x="46" y="24" width="28" height="12" rx="4" :fill="colors[1]" opacity="0.8" />
        <rect x="40" y="36" width="40" height="68" rx="12" :fill="colors[1]" />
        <rect x="46" y="56" width="28" height="22" rx="5" :fill="colors[2]" />
      </g>
      <g v-else-if="kind === 'sachet'">
        <rect x="32" y="26" width="56" height="70" rx="6" :fill="colors[1]" />
        <path d="M32 34h56M32 88h56" stroke="#FFFFFF" stroke-width="2" stroke-dasharray="4 4" opacity="0.7" />
        <circle cx="60" cy="61" r="14" :fill="colors[2]" />
      </g>
      <g v-else>
        <rect x="34" y="28" width="52" height="14" rx="5" :fill="colors[1]" opacity="0.8" />
        <rect x="30" y="42" width="60" height="56" rx="14" :fill="colors[1]" />
        <rect x="40" y="58" width="40" height="22" rx="6" :fill="colors[2]" />
      </g>
    </svg>
  </div>
</template>
