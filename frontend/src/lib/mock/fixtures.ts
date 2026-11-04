import type { Encounter, HisSuggestion, Patient, User } from '@/types'

export const DEMO_USER: User = {
  id: '00000000-0000-4000-8000-000000000001',
  name: 'Enfermera Demo',
  email: 'demo@kaycare.example',
  role: 'nurse',
}

export const DEMO_SUGGESTIONS: HisSuggestion[] = [
  {
    catalog_item_id: '00000000-0000-4000-8000-000000000101',
    label: 'Control de crecimiento y desarrollo (ejemplo)',
    code: 'DEMO-CRED',
    diagnosis_type_suggested: 'D',
    lab_suggested: null,
    verification_status: 'draft',
    source: 'Catálogo ficticio de demostración. No corresponde a un código oficial MINSA.',
  },
  {
    catalog_item_id: '00000000-0000-4000-8000-000000000102',
    label: 'Consejería a la familia (ejemplo)',
    code: 'DEMO-CONSEJERIA',
    diagnosis_type_suggested: null,
    lab_suggested: null,
    verification_status: 'draft',
    source: 'Catálogo ficticio de demostración. Confirmar solo si se realizó la actividad.',
  },
  {
    catalog_item_id: '00000000-0000-4000-8000-000000000103',
    label: 'Registro de hemoglobina (ejemplo)',
    code: 'DEMO-HB',
    diagnosis_type_suggested: null,
    lab_suggested: null,
    verification_status: 'draft',
    source: 'Catálogo ficticio de demostración. El valor LAB requiere revisión manual.',
  },
]

function dateAtPeruOffset(daysAgo: number): string {
  return new Date(Date.now() - daysAgo * 86_400_000 - 5 * 60 * 60 * 1000).toISOString().slice(0, 10)
}

export function createFixtures(): { patients: Patient[]; encounters: Encounter[] } {
  const names: [string, string, Patient['sex'], number][] = [
    ['Lucía', 'Ejemplo', 'F', 183],
    ['Mateo', 'Demostración', 'M', 366],
    ['Valentina', 'Prueba', 'F', 730],
    ['Nicolás', 'Ejemplo', 'M', 92],
    ['Camila', 'Demostración', 'F', 1095],
    ['Gabriel', 'Prueba', 'M', 548],
    ['Alma', 'Ejemplo', 'F', 274],
    ['Leo', 'Demostración', 'M', 1461],
    ['Elena', 'Prueba', 'F', 61],
    ['Tomás', 'Ejemplo', 'M', 913],
    ['Emilia', 'Demostración', 'F', 1220],
    ['Bruno', 'Prueba', 'M', 457],
    ['Alex', 'Ejemplo', 'X', 640],
    ['Inés', 'Demostración', 'F', 30],
  ]
  const patients = names.map(([first_name, last_name, sex, ageDays], index): Patient => ({
    id: `10000000-0000-4000-8000-${String(index + 1).padStart(12, '0')}`,
    chart_number: `DEMO-${String(index + 1).padStart(4, '0')}`,
    first_name,
    last_name,
    sex,
    birth_date: dateAtPeruOffset(ageDays),
    document_type: null,
    document_number: null,
    locality:
      ['Sector ficticio A', 'Sector ficticio B', 'Sector ficticio C'][index % 3] ??
      'Sector ficticio A',
  }))
  const encounters = patients.slice(0, 4).map((patient, index): Encounter => ({
    id: `20000000-0000-4000-8000-${String(index + 1).padStart(12, '0')}`,
    patient_id: patient.id,
    type: 'CRED',
    occurred_at: `${dateAtPeruOffset(index + 1)}T09:${String(index * 15).padStart(2, '0')}:00-05:00`,
    clinical_note:
      'Atención ficticia para explorar Kay Care. No representa una historia clínica real.',
    weight_kg: [7.2, 9.8, 12.1, 5.4][index] ?? null,
    height_cm: [66, 75, 87, 59][index] ?? null,
    hb_g_dl: null,
    status: 'draft',
  }))
  return { patients, encounters }
}
