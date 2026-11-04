<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'dark' | 'light' }>(), { tone: 'dark' })
</script>

<template>
  <span class="inline-flex items-center gap-2.5">
    <svg viewBox="0 0 40 40" class="h-9 w-9" aria-hidden="true">
      <rect width="40" height="40" rx="12" fill="#5E8C70" />
      <path d="M17.5 10h5v7.5H30v5h-7.5V30h-5v-7.5H10v-5h7.5z" fill="#FAF6EF" />
      <circle cx="30" cy="10" r="4" fill="#7CC6BF" />
    </svg>
    <span class="leading-tight">
      <span class="block font-display text-[17px] font-semibold" :class="tone === 'dark' ? 'text-ink-900' : 'text-cream-50'">Kay Care</span>
      <span class="block text-[10px] font-semibold uppercase tracking-[0.22em]" :class="tone === 'dark' ? 'text-sage-600' : 'text-sage-200'">Farmacia · demo</span>
    </span>
  </span>
</template>
