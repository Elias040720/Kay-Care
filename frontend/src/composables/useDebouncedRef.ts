import { onBeforeUnmount, ref, watch, type Ref } from 'vue'
export function useDebouncedRef(value: Ref<string>, delay = 300) {
  const debounced = ref(value.value)
  let timer: ReturnType<typeof setTimeout> | undefined
  watch(value, (next) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
      debounced.value = next.trim()
    }, delay)
  })
  onBeforeUnmount(() => clearTimeout(timer))
  return debounced
}
