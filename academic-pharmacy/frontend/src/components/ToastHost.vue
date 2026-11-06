<script setup lang="ts">
import { CheckCircle2, Info, X, XCircle } from 'lucide-vue-next'
import { useToastStore } from '@/stores/toast'

const store = useToastStore()
const icons = { success: CheckCircle2, error: XCircle, info: Info }
const tones = {
  success: 'border-sage-200 text-sage-700',
  error: 'border-clay-100 text-clay-700',
  info: 'border-teal-100 text-teal-700',
}
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6" aria-live="polite" aria-atomic="false">
    <TransitionGroup name="toast">
      <div
        v-for="toast in store.toasts"
        :key="toast.id"
        class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border bg-white/95 px-4 py-3 shadow-lift backdrop-blur"
        :class="tones[toast.tone]"
        :role="toast.tone === 'error' ? 'alert' : 'status'"
        data-testid="toast"
      >
        <component :is="icons[toast.tone]" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-ink-900">{{ toast.title }}</p>
          <p v-if="toast.message" class="mt-0.5 text-sm text-ink-500">{{ toast.message }}</p>
        </div>
        <button type="button" class="btn-icon -mr-2 -mt-1 h-8 w-8" aria-label="Cerrar notificación" @click="store.dismiss(toast.id)">
          <X class="h-4 w-4" aria-hidden="true" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
