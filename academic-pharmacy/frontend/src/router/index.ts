import { createRouter, createWebHistory, type RouteRecordRaw, type Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export const routes: RouteRecordRaw[] = [
  { path: '/', name: 'home', component: () => import('@/views/HomeView.vue'), meta: { title: 'Inicio' } },
  { path: '/catalogo', name: 'catalog', component: () => import('@/views/CatalogView.vue'), meta: { title: 'Catálogo' } },
  { path: '/producto/:slug', name: 'product', component: () => import('@/views/ProductView.vue'), props: true, meta: { title: 'Producto' } },
  { path: '/checkout', name: 'checkout', component: () => import('@/views/CheckoutView.vue'), meta: { title: 'Checkout simulado' } },
  { path: '/pedido/:number', name: 'confirmation', component: () => import('@/views/ConfirmationView.vue'), props: true, meta: { title: 'Pedido simulado' } },
  { path: '/admin/login', name: 'admin-login', component: () => import('@/views/admin/AdminLoginView.vue'), meta: { title: 'Acceso administración', guestOnly: true } },
  {
    path: '/admin',
    component: () => import('@/views/admin/AdminLayout.vue'),
    meta: { requiresAdmin: true },
    children: [
      { path: '', name: 'admin-dashboard', component: () => import('@/views/admin/AdminDashboardView.vue'), meta: { title: 'Panel' } },
      { path: 'productos', name: 'admin-products', component: () => import('@/views/admin/AdminProductsView.vue'), meta: { title: 'Productos' } },
      { path: 'stock', name: 'admin-stock', component: () => import('@/views/admin/AdminStockView.vue'), meta: { title: 'Stock' } },
      { path: 'pedidos', name: 'admin-orders', component: () => import('@/views/admin/AdminOrdersView.vue'), meta: { title: 'Pedidos' } },
      { path: 'pedidos/:id(\\d+)', name: 'admin-order', component: () => import('@/views/admin/AdminOrderDetailView.vue'), props: (r) => ({ id: Number(r.params.id) }), meta: { title: 'Detalle de pedido' } },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue'), meta: { title: 'Página no encontrada' } },
]

export function installGuards(router: Router): Router {
  router.beforeEach(async (to) => {
    const needsAdmin = to.matched.some((r) => r.meta.requiresAdmin)
    const guestOnly = to.matched.some((r) => r.meta.guestOnly)
    if (!needsAdmin && !guestOnly) return true

    const auth = useAuthStore()
    if (!auth.checked) {
      try {
        await auth.fetchUser()
      } catch {
        // Error de red: se trata como no autenticado.
      }
    }

    if (needsAdmin && !auth.user) {
      return { name: 'admin-login', query: { redirect: to.fullPath } }
    }
    if (guestOnly && auth.user) {
      return { name: 'admin-dashboard' }
    }
    return true
  })

  router.afterEach((to) => {
    const title = (to.meta.title as string | undefined) ?? 'Tienda demostrativa'
    document.title = `${title} · Kay Care Farmacia`
  })

  return router
}

export function createAppRouter(): Router {
  const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, _from, saved) {
      if (saved) return saved
      if (to.hash) return { el: to.hash }
      return { top: 0 }
    },
  })
  return installGuards(router)
}
