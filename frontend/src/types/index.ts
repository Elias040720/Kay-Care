export interface User {
  id: string
  name: string
  email: string
  role: 'nurse' | 'admin'
}

export interface Patient {
  id: string
  chart_number: string
  first_name: string
  last_name: string
  birth_date: string
  sex: 'F' | 'M' | 'X'
  document_type: string | null
  document_number: string | null
  locality: string | null
}

export type PatientWrite = Omit<Patient, 'id'>

export interface Encounter {
  id: string
  patient_id: string
  type: 'CRED'
  occurred_at: string
  clinical_note: string | null
  weight_kg: number | null
  height_cm: number | null
  hb_g_dl: number | null
  status: 'draft' | 'reviewed'
}

export type EncounterWrite = Omit<Encounter, 'id' | 'status'>
export type DiagnosisType = 'P' | 'D' | 'R'
export type VerificationStatus = 'draft' | 'verified'

export interface HisSuggestion {
  catalog_item_id: string
  label: string
  code: string
  diagnosis_type_suggested: DiagnosisType | null
  lab_suggested: string | null
  verification_status: VerificationStatus
  source: string
}

export interface HisLineWrite {
  catalog_item_id: string
  diagnosis_type: DiagnosisType | null
  lab: string | null
}

export interface HisLine extends HisLineWrite {
  id: string
  label: string
  code: string
  verification_status: VerificationStatus
}

export interface HisPreview {
  patient: Patient
  encounter: Encounter
  lines: HisLine[]
  warnings: string[]
  validated: boolean
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginatedPatients {
  data: Patient[]
  meta: PaginationMeta
}

export type DocumentKind = 'his' | 'clinical-note'

/** UI-facing contract. Adapters own transport, validation, and demo behavior. */
export interface ApiAdapter {
  health(): Promise<{ status: 'ok'; version: 'v1' }>
  login(input: { email: string; password: string }): Promise<User>
  me(): Promise<User>
  logout(): Promise<void>
  listPatients(search?: string, page?: number): Promise<PaginatedPatients>
  createPatient(input: PatientWrite): Promise<Patient>
  getPatient(id: string): Promise<Patient>
  updatePatient(id: string, input: Partial<PatientWrite>): Promise<Patient>
  patientEncounters(id: string): Promise<Encounter[]>
  createEncounter(input: EncounterWrite): Promise<Encounter>
  getEncounter(id: string): Promise<Encounter>
  updateEncounter(id: string, input: Partial<EncounterWrite>): Promise<Encounter>
  hisSuggestions(id: string): Promise<HisSuggestion[]>
  saveHisLines(id: string, lines: HisLineWrite[]): Promise<HisLine[]>
  hisPreview(id: string): Promise<HisPreview>
  downloadDocument(id: string, kind: DocumentKind): Promise<Blob>
}
