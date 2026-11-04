<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import ToastHost from '@/components/ui/ToastHost.vue'
import { useAuthStore } from '@/features/auth/store'
const auth = useAuthStore()
const router = useRouter()
function expired() {
  auth.clear(true)
  void router.replace({ name: 'login', query: { expired: '1' } })
}
onMounted(() => window.addEventListener('kay:session-expired', expired))
onBeforeUnmount(() => window.removeEventListener('kay:session-expired', expired))
</script>
<template><RouterView /><ToastHost /></template>
