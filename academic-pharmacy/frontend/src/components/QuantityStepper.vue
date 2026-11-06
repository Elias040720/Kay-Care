<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next'

const props = withDefaults(defineProps<{ modelValue: number; max: number; min?: number; label: string; size?: 'sm' | 'md' }>(), {
  min: 1,
  size: 'md',
})
const emit = defineEmits<{ 'update:modelValue': [value: number] }>()

function set(value: number) {
  const n = Math.floor(Number(value))
  if (!Number.isFinite(n)) return
  emit('update:modelValue', Math.min(Math.max(n, props.min), Math.max(props.max, props.min)))
}

function onInput(event: Event) {
  const raw = (event.target as HTMLInputElement).value
  if (raw === '') return
  set(Number(raw))
}
</script>

<template>
  <div class="inline-flex items-center rounded-full border border-cream-300 bg-white" role="group" :aria-label="label">
    <button
      type="button"
      class="btn-icon"
      :class="size === 'sm' ? 'h-8 w-8' : ''"
      :disabled="modelValue <= min"
      :aria-label="`Disminuir cantidad de ${label}`"
      @click="set(modelValue - 1)"
    >
      <Minus class="h-4 w-4" aria-hidden="true" />
    </button>
    <input
      :value="modelValue"
      type="number"
      inputmode="numeric"
      :min="min"
      :max="max"
      step="1"
      class="w-10 border-0 bg-transparent p-0 text-center text-sm font-semibold text-ink-900 [appearance:textfield] focus:outline-none focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none"
      :aria-label="`Cantidad de ${label}`"
      @change="onInput"
    />
    <button
      type="button"
      class="btn-icon"
      :class="size === 'sm' ? 'h-8 w-8' : ''"
      :disabled="modelValue >= max"
      :aria-label="`Aumentar cantidad de ${label}`"
      @click="set(modelValue + 1)"
    >
      <Plus class="h-4 w-4" aria-hidden="true" />
    </button>
  </div>
</template>
