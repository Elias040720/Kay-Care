<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '@/components/AppHeader.vue'
import AppFooter from '@/components/AppFooter.vue'
import CartDrawer from '@/components/CartDrawer.vue'
import ToastHost from '@/components/ToastHost.vue'

const route = useRoute()
const isAdmin = computed(() => route.path.startsWith('/admin'))
</script>

<template>
  <a href="#contenido" class="skip-link">Saltar al contenido</a>
  <div class="flex min-h-screen flex-col">
    <AppHeader v-if="!isAdmin" />
    <main id="contenido" class="flex-1" tabindex="-1">
      <RouterView v-slot="{ Component }">
        <Transition name="page" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>
    <AppFooter v-if="!isAdmin" />
  </div>
  <CartDrawer v-if="!isAdmin" />
  <ToastHost />
</template>
