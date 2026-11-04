import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/features/auth/store'
export const router = createRouter({
  history: createWebHistory(),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    {
      path: '/acceso',
      name: 'login',
      component: () => import('@/features/auth/LoginView.vue'),
      meta: { public: true, title: 'Acceso' },
    },
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/features/dashboard/DashboardView.vue'),
          meta: { title: 'Inicio' },
        },
        {
          path: 'pacientes',
          name: 'patients',
          component: () => import('@/features/patients/PatientsView.vue'),
          meta: { title: 'Pacientes' },
        },
        {
          path: 'pacientes/:id',
          name: 'patient-detail',
          component: () => import('@/features/patients/PatientDetail.vue'),
          meta: { title: 'Ficha del paciente' },
        },
        {
          path: 'atenciones/nueva',
          name: 'encounter-new',
          component: () => import('@/features/encounters/EncounterWizard.vue'),
          meta: { title: 'Nueva atención' },
        },
        {
          path: 'atenciones/:id',
          name: 'encounter-detail',
          component: () => import('@/features/encounters/EncounterDetail.vue'),
          meta: { title: 'Detalle de atención' },
        },
        {
          path: 'documentos',
          name: 'documents',
          component: () => import('@/features/documents/DocumentsView.vue'),
          meta: { title: 'Documentación' },
        },
        {
          path: ':pathMatch(.*)*',
          name: 'not-found',
          component: () => import('@/views/NotFound.vue'),
          meta: { title: 'Página no encontrada' },
        },
      ],
    },
  ],
})
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.restore()
  if (!to.meta.public && !auth.user) return { name: 'login', query: { redirect: to.fullPath } }
  if (to.name === 'login' && auth.user) return { name: 'dashboard' }
})
router.afterEach((to) => {
  document.title = `${String(to.meta.title || 'Inicio')} · Kay Care`
  window.setTimeout(() => {
    if (!document.querySelector('dialog[open]'))
      document.querySelector<HTMLElement>('main h1')?.focus({ preventScroll: true })
  }, 80)
})
