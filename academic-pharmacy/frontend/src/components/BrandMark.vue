<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'dark' | 'light' }>(), { tone: 'dark' })
</script>

<template>
  <!-- Logotipo original de Kay Care Farmacia: cruz redondeada con hoja. -->
  <span class="inline-flex items-center gap-2.5">
    <svg viewBox="0 0 44 44" class="h-10 w-10 shrink-0" aria-hidden="true">
      <defs>
        <linearGradient id="kcf-logo" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#14966A" />
          <stop offset="1" stop-color="#2E736F" />
        </linearGradient>
      </defs>
      <rect width="44" height="44" rx="13" fill="url(#kcf-logo)" />
      <path d="M18.6 10.5h6.8v8.1h8.1v6.8h-8.1v8.1h-6.8v-8.1h-8.1v-6.8h8.1z" fill="#FFFFFF" />
      <path d="M31.5 7.5c-4.2.3-6.6 2.8-6.9 6.9 4.1-.2 6.6-2.7 6.9-6.9z" fill="#A6E0C4" />
    </svg>
    <span class="leading-none">
      <span class="block text-[19px] font-extrabold tracking-tight" :class="tone === 'dark' ? 'text-ink-900' : 'text-white'">
        Kay Care
      </span>
      <span class="mt-1 block text-[10.5px] font-bold uppercase tracking-[0.28em]" :class="tone === 'dark' ? 'text-clinic-600' : 'text-clinic-100'">
        Farmacia
      </span>
    </span>
  </span>
</template>
