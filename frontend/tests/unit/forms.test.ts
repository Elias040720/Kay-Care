import { describe, expect, it } from 'vitest'
import { patientSchema } from '@/lib/validation/patient'
import { ageAt, dateLabel, toPeruISO } from '@/lib/format'
const patient = {
  chart_number: 'DEMO-TEST',
  first_name: 'Persona',
  last_name: 'Ficticia',
  birth_date: '2024-01-01',
  sex: 'X',
  document_type: null,
  document_number: null,
  locality: null,
}
describe('Validaciones del registro', () => {
  it('permite registrar sin documento y normaliza campos opcionales', () => {
    expect(patientSchema.parse({ ...patient, locality: '  ' }).locality).toBeNull()
  })
  it('rechaza fechas imposibles y futuras', () => {
    expect(patientSchema.safeParse({ ...patient, birth_date: '2024-02-31' }).success).toBe(false)
    expect(patientSchema.safeParse({ ...patient, birth_date: '2099-01-01' }).success).toBe(false)
  })
  it('exige tipo si hay número y valida longitud DNI sin exigir documento', () => {
    expect(patientSchema.safeParse({ ...patient, document_number: 'DEMO' }).success).toBe(false)
    expect(
      patientSchema.safeParse({ ...patient, document_type: 'DNI', document_number: 'DEMO' })
        .success,
    ).toBe(false)
    expect(patientSchema.safeParse(patient).success).toBe(true)
  })
  it('rechaza nombres vacíos tras quitar espacios', () =>
    expect(patientSchema.safeParse({ ...patient, first_name: '  ' }).success).toBe(false))
})
describe('Edad de referencia, sin inferencias clínicas', () => {
  it('respeta día exacto, meses y cumpleaños', () => {
    expect(ageAt('2024-01-15', '2024-01-15')).toBe('0 días')
    expect(ageAt('2024-01-15', '2024-02-14')).toBe('30 días')
    expect(ageAt('2024-01-15', '2024-02-15')).toBe('1 mes')
    expect(ageAt('2024-01-15', '2025-01-15')).toBe('1 año')
  })
  it('no convierte fecha posterior al encuentro en edad negativa', () =>
    expect(ageAt('2025-01-01', '2024-01-01')).toBe('Fecha por revisar'))
  it('mantiene zona Perú al enviar atención y mostrar fecha', () => {
    expect(ageAt('2024-01-02', '2025-01-02T01:00:00Z')).toBe('11 meses')
    expect(toPeruISO('2025-06-01T09:30')).toBe('2025-06-01T09:30:00-05:00')
    expect(dateLabel('2025-06-02T01:00:00Z')).toContain('1 jun')
  })
})
