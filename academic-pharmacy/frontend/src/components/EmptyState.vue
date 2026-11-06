<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{ title: string; description?: string; icon?: Component }>()
</script>

<template>
  <div class="flex flex-col items-center justify-center rounded-xl2 border border-dashed border-cream-300 bg-cream-50 px-6 py-14 text-center">
    <div v-if="icon" class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-sage-50 text-sage-600">
      <component :is="icon" class="h-6 w-6" aria-hidden="true" />
    </div>
    <h2 class="text-xl text-ink-900">{{ title }}</h2>
    <p v-if="description" class="mt-2 max-w-md text-sm text-ink-500">{{ description }}</p>
    <div class="mt-5 flex flex-wrap justify-center gap-2"><slot /></div>
  </div>
</template>
