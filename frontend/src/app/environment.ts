import { isDemo } from '@/lib/api'

// The integrated backend currently ships only a draft DEMO catalog and DEMO PDFs.
// Transport selection never certifies clinical readiness.
export const isDemonstrationData = true
export const environmentMessage = isDemo
  ? 'DEMO · Solo datos ficticios. Se reinician al recargar o cerrar sesión.'
  : 'DEMO con Laravel · Solo datos ficticios. Los registros persisten en la base de datos local.'
