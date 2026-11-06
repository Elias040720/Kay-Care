<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'

const props = withDefaults(defineProps<{ open: boolean; title: string; size?: 'md' | 'lg' }>(), { size: 'md' })
const emit = defineEmits<{ close: [] }>()
const panel = ref<HTMLElement | null>(null)

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('close')
}

watch(
  () => props.open,
  async (open) => {
    if (open) {
      document.addEventListener('keydown', onKey)
      await nextTick()
      panel.value?.querySelector<HTMLElement>('input, select, textarea, button')?.focus()
    } else {
      document.removeEventListener('keydown', onKey)
    }
  },
  { immediate: true },
)
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-ink-900/40 p-0 backdrop-blur-[2px] sm:items-center sm:p-6" @click.self="emit('close')">
        <div
          ref="panel"
          class="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-cream-50 shadow-lift sm:rounded-3xl"
          :class="size === 'lg' ? 'sm:max-w-2xl' : 'sm:max-w-md'"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
        >
          <header class="sticky top-0 z-10 flex items-center justify-between border-b border-cream-200 bg-cream-50 px-6 py-4">
            <h2 class="text-xl">{{ title }}</h2>
            <button type="button" class="btn-icon" aria-label="Cerrar" @click="emit('close')"><X class="h-5 w-5" aria-hidden="true" /></button>
          </header>
          <div class="px-6 py-5"><slot /></div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
