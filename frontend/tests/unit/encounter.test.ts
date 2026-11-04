import { describe, expect, it } from 'vitest'
import {
  encounterFormSchema,
  localEncounterDate,
  parseEncounterForm,
  selectedHisLines,
  type HisChoice,
} from '../../src/features/encounters/validation'
import type { Patient } from '../../src/types'

const patient: Patient = {
  id: '5ac18023-9524-4ceb-89af-40ea85c67ae3',
  first_name: 'Paciente',
  last_name: 'Demostración',
  birth_date: '2020-02-20',
  chart_number: 'DEMO-TEST',
  sex: 'X',
  document_type: null,
  document_number: null,
  locality: null,
}
const validForm = {
  occurred_at: '2025-02-21T10:30',
  clinical_note: ' Registro ficticio ',
  weight_kg: '10.25',
  height_cm: '',
  hb_g_dl: '',
}
describe('Formulario CRED', () => {
  it('preserva mediciones opcionales como null y fecha con zona horaria de Perú', () => {
    expect(parseEncounterForm(validForm, patient)).toMatchObject({
      occurred_at: '2025-02-21T10:30:00-05:00',
      clinical_note: 'Registro ficticio',
      weight_kg: 10.25,
      height_cm: null,
      hb_g_dl: null,
      type: 'CRED',
    })
  })
  it('rechaza atención anterior al nacimiento', () => {
    expect(() =>
      parseEncounterForm({ ...validForm, occurred_at: '2019-02-21T10:30' }, patient),
    ).toThrow('anterior al nacimiento')
  })
  it.each(['-1', '0', 'Infinity', 'NaN', 'peso'])('rechaza medición inválida %s', (value) => {
    expect(encounterFormSchema.safeParse({ ...validForm, weight_kg: value }).success).toBe(false)
  })
  it('convierte fechas UTC a fecha/hora local de Perú al reanudar', () => {
    expect(localEncounterDate('2025-02-21T15:30:00Z')).toBe('2025-02-21T10:30')
  })
  it('rechaza una fecha de calendario inexistente aunque Date la normalice', () => {
    expect(
      encounterFormSchema.safeParse({ ...validForm, occurred_at: '2025-02-31T10:30' }).success,
    ).toBe(false)
  })
})
describe('Confirmación HIS', () => {
  const suggestion: HisChoice = {
    catalog_item_id: '7aa9bbc0-20b0-4b88-874e-e5b29266ddcd',
    label: 'Ejemplo',
    code: 'DEMO-TEST',
    diagnosis_type_suggested: 'D',
    lab_suggested: 'PROPUESTA',
    verification_status: 'draft',
    source: 'Ficticio',
    selected: false,
    diagnosis_type: '',
    lab: '',
  }
  it('no transforma sugerencias en actividades realizadas', () => {
    expect(selectedHisLines([suggestion])).toEqual([])
  })
  it('no copia valores sugeridos sin confirmación y edición', () => {
    expect(selectedHisLines([{ ...suggestion, selected: true }])).toEqual([
      { catalog_item_id: suggestion.catalog_item_id, diagnosis_type: null, lab: null },
    ])
  })
  it('envía solo campos del contrato para selecciones explícitas', () => {
    expect(
      selectedHisLines([
        { ...suggestion, selected: true, diagnosis_type: 'R', lab: ' valor confirmado ' },
      ]),
    ).toEqual([
      { catalog_item_id: suggestion.catalog_item_id, diagnosis_type: 'R', lab: 'valor confirmado' },
    ])
  })
})
