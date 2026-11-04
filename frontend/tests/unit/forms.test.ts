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
  it('exige número cuando se selecciona un tipo de documento', () => {
    const result = patientSchema.safeParse({ ...patient, document_type: 'CNV' })
    expect(result.success).toBe(false)
    if (!result.success)
      expect(result.error.issues.some((issue) => issue.path[0] === 'document_number')).toBe(true)
  })
  it.each(['DNI', 'CE', 'PASAPORTE', 'CNV', 'OTRO'])(
    'reconoce el tipo permitido %s sin aceptar tipos libres',
    (documentType) => {
      expect(
        patientSchema.safeParse({
          ...patient,
          document_type: documentType,
          document_number: documentType === 'DNI' ? '00000000' : 'DEMO',
        }).success,
      ).toBe(true)
      expect(
        patientSchema.safeParse({ ...patient, document_type: 'LIBRE', document_number: 'DEMO' })
          .success,
      ).toBe(false)
    },
  )
  it('aplica el límite de historia clínica, apellidos y localidad del servidor', () => {
    expect(
      patientSchema.safeParse({
        ...patient,
        chart_number: 'A'.repeat(30),
        last_name: 'Á'.repeat(150),
        locality: 'L'.repeat(150),
      }).success,
    ).toBe(true)
    expect(patientSchema.safeParse({ ...patient, chart_number: 'A'.repeat(31) }).success).toBe(
      false,
    )
    expect(patientSchema.safeParse({ ...patient, last_name: 'Á'.repeat(151) }).success).toBe(false)
    expect(patientSchema.safeParse({ ...patient, locality: 'L'.repeat(151) }).success).toBe(false)
  })
  it('admite documentos entre 4 y 20 caracteres alfanuméricos', () => {
    for (const documentNumber of ['DEMO', 'D'.repeat(20)]) {
      expect(
        patientSchema.safeParse({
          ...patient,
          document_type: 'OTRO',
          document_number: documentNumber,
        }).success,
      ).toBe(true)
    }
    for (const documentNumber of ['DEM', 'D'.repeat(21), 'DEMO/01']) {
      expect(
        patientSchema.safeParse({
          ...patient,
          document_type: 'OTRO',
          document_number: documentNumber,
        }).success,
      ).toBe(false)
    }
  })
  it('normaliza identificadores y espacios de la misma manera que Laravel', () => {
    const parsed = patientSchema.parse({
      ...patient,
      chart_number: ' demo - 01/2 ',
      first_name: '  Persona   Ficticia ',
      document_type: ' cnv ',
      document_number: ' de-mo 01 ',
    })
    expect(parsed).toMatchObject({
      chart_number: 'DEMO-01/2',
      first_name: 'Persona Ficticia',
      document_type: 'CNV',
      document_number: 'DEMO01',
    })
  })
  it('no permite caracteres de historia clínica o nombres rechazados por el servidor', () => {
    expect(patientSchema.safeParse({ ...patient, chart_number: 'DEMO_01' }).success).toBe(false)
    expect(patientSchema.safeParse({ ...patient, first_name: 'Paciente 1' }).success).toBe(false)
    expect(
      patientSchema.safeParse({ ...patient, first_name: 'María-José', last_name: "O'Prueba" })
        .success,
    ).toBe(true)
  })
  it('admite desde el 1 de enero de 1900 y rechaza fechas anteriores', () => {
    expect(patientSchema.safeParse({ ...patient, birth_date: '1900-01-01' }).success).toBe(true)
    expect(patientSchema.safeParse({ ...patient, birth_date: '1899-12-31' }).success).toBe(false)
  })
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
