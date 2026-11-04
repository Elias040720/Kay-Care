import { z } from 'zod'
import type {
  ApiAdapter,
  DocumentKind,
  Encounter,
  EncounterWrite,
  HisLine,
  HisLineWrite,
  HisPreview,
  Patient,
  PatientWrite,
  User,
} from '@/types'
import { AppError } from '@/lib/api/errors'
import { encounterWriteSchema, hisLineWriteSchema, patientWriteSchema } from '@/lib/api/schemas'
import { createFixtures, DEMO_SUGGESTIONS, DEMO_USER } from './fixtures'
import { createDemoPdf } from './pdf'

function parseInput<T>(schema: z.ZodType<T>, input: unknown): T {
  const parsed = schema.safeParse(input)
  if (parsed.success) return parsed.data
  const fields: Record<string, string[]> = {}
  for (const issue of parsed.error.issues) {
    const name = issue.path.join('.')
    fields[name] = [...(fields[name] ?? []), 'Revisa el valor de este campo.']
  }
  throw new AppError('Revisa los campos indicados antes de continuar.', 422, fields)
}

const copy = <T>(value: T): T => structuredClone(value)
const normalizeSearch = (value: string) =>
  value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('es-PE')

/** Session-memory demonstration only. No browser persistence or production authentication. */
export class MockApiAdapter implements ApiAdapter {
  private user: User | null = null
  private patients: Patient[]
  private encounters: Encounter[]
  private readonly lines = new Map<string, HisLine[]>()

  constructor(private readonly delayMs = 160) {
    const fixtures = createFixtures()
    this.patients = fixtures.patients
    this.encounters = fixtures.encounters
  }

  private async wait() {
    if (this.delayMs > 0) await new Promise<void>((resolve) => setTimeout(resolve, this.delayMs))
  }

  private async guard() {
    await this.wait()
    if (!this.user) throw new AppError('Ingresa al modo demo para continuar.', 401)
  }

  private findPatient(id: string): Patient {
    const patient = this.patients.find((entry) => entry.id === id)
    if (!patient) throw new AppError('No encontramos este paciente. Actualiza el listado.', 404)
    return patient
  }

  private findEncounter(id: string): Encounter {
    const encounter = this.encounters.find((entry) => entry.id === id)
    if (!encounter)
      throw new AppError('No encontramos esta atención. Vuelve a la ficha del paciente.', 404)
    return encounter
  }

  private validatePatient(input: PatientWrite, exceptId?: string) {
    if (
      this.patients.some(
        (patient) =>
          patient.id !== exceptId &&
          normalizeSearch(patient.chart_number) === normalizeSearch(input.chart_number),
      )
    ) {
      throw new AppError('Este número de historia clínica ya está registrado.', 422, {
        chart_number: ['Usa un número de HC distinto.'],
      })
    }
    if (
      input.document_number &&
      this.patients.some(
        (patient) =>
          patient.id !== exceptId &&
          patient.document_type === input.document_type &&
          patient.document_number === input.document_number,
      )
    ) {
      throw new AppError('Este documento ya está registrado.', 422, {
        document_number: ['Revisa el documento o busca al paciente existente.'],
      })
    }
    if (input.birth_date > new Date(Date.now() - 5 * 3_600_000).toISOString().slice(0, 10)) {
      throw new AppError('La fecha de nacimiento no puede estar en el futuro.', 422, {
        birth_date: ['Revisa la fecha de nacimiento.'],
      })
    }
  }

  private validateEncounter(input: EncounterWrite) {
    const patient = this.findPatient(input.patient_id)
    const peruDate = new Date(new Date(input.occurred_at).getTime() - 5 * 3_600_000)
      .toISOString()
      .slice(0, 10)
    if (peruDate < patient.birth_date) {
      throw new AppError('La atención no puede ser anterior al nacimiento.', 422, {
        occurred_at: ['Revisa la fecha de atención.'],
      })
    }
  }

  async health(): Promise<{ status: 'ok'; version: 'v1' }> {
    await this.wait()
    return { status: 'ok', version: 'v1' }
  }

  async login(_input: { email: string; password: string }) {
    await this.wait()
    this.user = copy(DEMO_USER)
    return copy(this.user)
  }

  async me() {
    await this.guard()
    return copy(this.user!)
  }

  async logout() {
    await this.wait()
    this.user = null
    const fixtures = createFixtures()
    this.patients = fixtures.patients
    this.encounters = fixtures.encounters
    this.lines.clear()
  }

  async listPatients(search = '', page = 1) {
    await this.guard()
    const term = normalizeSearch(search.trim())
    const results = this.patients.filter((patient) =>
      normalizeSearch(
        `${patient.first_name} ${patient.last_name} ${patient.chart_number} ${patient.document_number ?? ''}`,
      ).includes(term),
    )
    const perPage = 8
    const lastPage = Math.max(1, Math.ceil(results.length / perPage))
    const currentPage = Math.min(
      lastPage,
      Math.max(1, Number.isFinite(page) ? Math.floor(page) : 1),
    )
    return copy({
      data: results.slice((currentPage - 1) * perPage, currentPage * perPage),
      meta: {
        current_page: currentPage,
        last_page: lastPage,
        per_page: perPage,
        total: results.length,
      },
    })
  }

  async createPatient(input: PatientWrite) {
    await this.guard()
    const data = parseInput(patientWriteSchema, input)
    this.validatePatient(data)
    const patient: Patient = { ...data, id: crypto.randomUUID() }
    this.patients.unshift(patient)
    return copy(patient)
  }

  async getPatient(id: string) {
    await this.guard()
    return copy(this.findPatient(id))
  }

  async updatePatient(id: string, input: Partial<PatientWrite>) {
    await this.guard()
    const patient = this.findPatient(id)
    const data = parseInput(patientWriteSchema, { ...patient, ...input })
    this.validatePatient(data, id)
    Object.assign(patient, data)
    return copy(patient)
  }

  async patientEncounters(id: string) {
    await this.guard()
    this.findPatient(id)
    return copy(
      this.encounters
        .filter((encounter) => encounter.patient_id === id)
        .sort((a, b) => Date.parse(b.occurred_at) - Date.parse(a.occurred_at)),
    )
  }

  async createEncounter(input: EncounterWrite) {
    await this.guard()
    const data = parseInput(encounterWriteSchema, input)
    this.validateEncounter(data)
    const encounter: Encounter = { ...data, id: crypto.randomUUID(), status: 'draft' }
    this.encounters.unshift(encounter)
    return copy(encounter)
  }

  async getEncounter(id: string) {
    await this.guard()
    return copy(this.findEncounter(id))
  }

  async updateEncounter(id: string, input: Partial<EncounterWrite>) {
    await this.guard()
    const encounter = this.findEncounter(id)
    const data = parseInput(encounterWriteSchema, { ...encounter, ...input })
    this.validateEncounter(data)
    Object.assign(encounter, data)
    return copy(encounter)
  }

  async hisSuggestions(id: string) {
    await this.guard()
    this.findEncounter(id)
    return copy(DEMO_SUGGESTIONS)
  }

  async saveHisLines(id: string, input: HisLineWrite[]) {
    await this.guard()
    this.findEncounter(id)
    const data = parseInput(z.array(hisLineWriteSchema), input)
    const seen = new Set<string>()
    const lines = data.map((line): HisLine => {
      const suggestion = DEMO_SUGGESTIONS.find(
        (item) => item.catalog_item_id === line.catalog_item_id,
      )
      if (!suggestion || seen.has(line.catalog_item_id)) {
        throw new AppError(
          'Revisa las actividades seleccionadas. No se permiten códigos desconocidos o duplicados.',
          422,
          { lines: ['Vuelve a revisar las actividades.'] },
        )
      }
      seen.add(line.catalog_item_id)
      return {
        ...line,
        id: crypto.randomUUID(),
        label: suggestion.label,
        code: suggestion.code,
        verification_status: suggestion.verification_status,
      }
    })
    this.lines.set(id, lines)
    return copy(lines)
  }

  async hisPreview(id: string): Promise<HisPreview> {
    await this.guard()
    const encounter = this.findEncounter(id)
    const patient = this.findPatient(encounter.patient_id)
    const lines = this.lines.get(id) ?? []
    const warnings = [
      'DEMO: catálogo ficticio sin validación oficial. No usar para reportes MINSA.',
    ]
    if (!lines.length) warnings.push('No hay actividades HIS confirmadas para esta atención.')
    if (!patient.document_number)
      warnings.push('El paciente no tiene documento de identidad registrado.')
    if (encounter.weight_kg === null) warnings.push('El peso no está registrado.')
    if (encounter.height_cm === null) warnings.push('La talla no está registrada.')
    if (!encounter.clinical_note?.trim()) warnings.push('La atención no tiene notas clínicas.')
    if (lines.some((line) => line.diagnosis_type === null))
      warnings.push(
        'Hay actividades sin tipo de diagnóstico. Revísalas antes de utilizar el registro.',
      )
    return copy({ patient, encounter, lines, warnings, validated: false })
  }

  async downloadDocument(id: string, kind: DocumentKind) {
    return createDemoPdf(await this.hisPreview(id), kind)
  }
}
