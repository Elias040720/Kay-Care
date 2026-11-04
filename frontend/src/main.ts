import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin } from '@tanstack/vue-query'
import { queryClient } from '@/app/query'
import { router } from '@/router'
import App from './App.vue'
import './styles/main.css'
const app = createApp(App)
app.use(createPinia()).use(VueQueryPlugin, { queryClient }).use(router).mount('#app')
