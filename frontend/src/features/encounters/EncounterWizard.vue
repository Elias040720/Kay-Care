<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import {
  ArrowLeft,
  ArrowRight,
  Check,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Clock3,
  Search,
  ShieldCheck,
  UserRound,
} from '@lucide/vue'
import { z } from 'zod'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatePanel from '@/components/ui/StatePanel.vue'
import HisActivities from '@/features/his/HisActivities.vue'
import EncounterReview from './EncounterReview.vue'
import {
  parseEncounterForm,
  localEncounterDate,
  selectedHisLines,
  type EncounterForm,
  type HisChoice,
} from './validation'
import { api } from '@/lib/api'
import { normalizeError } from '@/lib/api/errors'
import { ageAt, fullName, initials, nowLocalInput } from '@/lib/format'
import { useToast } from '@/composables/useToast'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import type { EncounterWrite, HisLine, HisLineWrite, Patient } from '@/types'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const { notify } = useToast()
const step = ref(1)
const farthestStep = ref(1)
const stepNames = ['Paciente', 'Evaluación', 'Actividades HIS', 'Revisión']
const search = ref('')
const page = ref(1)
const selectedPatient = ref<Patient | null>(null)
const encounterId = ref('')
const initializing = ref(false)
const initializationError = ref<Error | null>(null)
const form = reactive<EncounterForm>({
  occurred_at: nowLocalInput(),
  clinical_note: '',
  weight_kg: '',
  height_cm: '',
  hb_g_dl: '',
})
const choices = ref<HisChoice[]>([])
const fieldErrors = ref<Record<string, string[]>>({})
const saveError = ref('')
const submitting = ref(false)

function evaluationSnapshot() {
  return JSON.stringify({ patient: selectedPatient.value?.id || '', form })
}
function activitiesSnapshot() {
  return JSON.stringify(
    choices.value
      .filter((choice) => choice.selected)
      .map((choice) => ({
        id: choice.catalog_item_id,
        diagnosis: choice.diagnosis_type,
        lab: choice.lab,
      }))
      .sort((a, b) => a.id.localeCompare(b.id)),
  )
}
const savedEvaluation = ref(evaluationSnapshot())
const savedActivities = ref(activitiesSnapshot())
const dirty = computed(
  () =>
    evaluationSnapshot() !== savedEvaluation.value ||
    activitiesSnapshot() !== savedActivities.value,
)
useUnsavedChanges(dirty)
const patientQuery = useQuery({
  queryKey: computed(() => ['patients', search.value, page.value]),
  queryFn: () => api.listPatients(search.value, page.value),
  enabled: computed(() => step.value === 1),
})
watch(search, () => {
  page.value = 1
})
const suggestionsQuery = useQuery({
  queryKey: computed(() => ['his-suggestions', encounterId.value]),
  queryFn: () => api.hisSuggestions(encounterId.value),
  enabled: computed(() => !!encounterId.value && step.value === 3),
})
const previewQuery = useQuery({
  queryKey: computed(() => ['his-preview', encounterId.value]),
  queryFn: () => api.hisPreview(encounterId.value),
  enabled: computed(() => !!encounterId.value && step.value === 4),
})

watch(suggestionsQuery.data, (suggestions) => {
  if (!suggestions) return
  const oldChoices = new Map(choices.value.map((choice) => [choice.catalog_item_id, choice]))
  const nextChoices: HisChoice[] = suggestions.map((suggestion) => ({
    ...suggestion,
    // Refresh catalog metadata while preserving only the nurse's explicit edits.
    selected: oldChoices.get(suggestion.catalog_item_id)?.selected ?? false,
    diagnosis_type: oldChoices.get(suggestion.catalog_item_id)?.diagnosis_type ?? '',
    lab: oldChoices.get(suggestion.catalog_item_id)?.lab ?? '',
  }))
  for (const choice of choices.value) {
    if (
      choice.selected &&
      !nextChoices.some((next) => next.catalog_item_id === choice.catalog_item_id)
    )
      nextChoices.push(choice)
  }
  choices.value = nextChoices
})

function choicesFromSaved(lines: HisLine[]): HisChoice[] {
  return lines.map((line) => ({
    ...line,
    selected: true,
    diagnosis_type: line.diagnosis_type || '',
    lab: line.lab || '',
    diagnosis_type_suggested: null,
    lab_suggested: null,
    source: 'Actividad guardada previamente en esta atención',
  }))
}

async function initialize() {
  initializing.value = true
  initializationError.value = null
  try {
    const existingId = typeof route.query.encounter === 'string' ? route.query.encounter : ''
    if (existingId) {
      const preview = await api.hisPreview(existingId)
      encounterId.value = preview.encounter.id
      selectedPatient.value = preview.patient
      Object.assign(form, {
        occurred_at: localEncounterDate(preview.encounter.occurred_at),
        clinical_note: preview.encounter.clinical_note || '',
        weight_kg: preview.encounter.weight_kg?.toString() || '',
        height_cm: preview.encounter.height_cm?.toString() || '',
        hb_g_dl: preview.encounter.hb_g_dl?.toString() || '',
      })
      choices.value = choicesFromSaved(preview.lines)
      step.value = 2
      farthestStep.value = 2
    } else if (typeof route.query.patient === 'string') {
      selectedPatient.value = await api.getPatient(route.query.patient)
      step.value = 2
      farthestStep.value = 2
    }
    savedEvaluation.value = evaluationSnapshot()
    savedActivities.value = activitiesSnapshot()
  } catch (error) {
    initializationError.value = normalizeError(error)
  } finally {
    initializing.value = false
  }
}
onMounted(initialize)

async function goToStep(value: number) {
  step.value = value
  farthestStep.value = Math.max(value, farthestStep.value)
  saveError.value = ''
  fieldErrors.value = {}
  await nextTick()
  document.getElementById('wizard-step-title')?.focus()
}
function choosePatient(patient: Patient) {
  selectedPatient.value = patient
}
function setMeasurement(field: 'weight_kg' | 'height_cm' | 'hb_g_dl', event: Event) {
  form[field] = (event.target as HTMLInputElement).value
}
function continuePatient() {
  if (!selectedPatient.value) {
    saveError.value = 'Selecciona un paciente para continuar.'
    return
  }
  void goToStep(2)
}
function showError(error: unknown) {
  if (error instanceof z.ZodError) {
    fieldErrors.value = {}
    for (const issue of error.issues) {
      const key = String(issue.path[0] || 'form')
      fieldErrors.value[key] = [...(fieldErrors.value[key] || []), issue.message]
    }
    saveError.value = 'Revisa los campos señalados antes de continuar.'
  } else {
    const normalized = normalizeError(error)
    fieldErrors.value = normalized.fields
    saveError.value = normalized.message
  }
  void nextTick(() => {
    ;(document.querySelector('[aria-invalid="true"]') as HTMLElement | null)?.focus()
  })
}
async function invalidateEncounter() {
  await Promise.all([
    queryClient.invalidateQueries({ queryKey: ['patient-encounters', selectedPatient.value?.id] }),
    queryClient.invalidateQueries({ queryKey: ['encounters'] }),
    queryClient.invalidateQueries({ queryKey: ['encounter', encounterId.value] }),
    queryClient.invalidateQueries({ queryKey: ['dashboard'] }),
    queryClient.invalidateQueries({ queryKey: ['his-preview', encounterId.value] }),
  ])
}
const saveEncounterMutation = useMutation({
  mutationFn: (input: EncounterWrite) =>
    encounterId.value ? api.updateEncounter(encounterId.value, input) : api.createEncounter(input),
})
const saveHisMutation = useMutation({
  mutationFn: (lines: HisLineWrite[]) => api.saveHisLines(encounterId.value, lines),
})
const busy = computed(
  () =>
    submitting.value || saveEncounterMutation.isPending.value || saveHisMutation.isPending.value,
)

async function saveEvaluation() {
  if (!selectedPatient.value || busy.value) return
  submitting.value = true
  saveError.value = ''
  fieldErrors.value = {}
  try {
    const input = parseEncounterForm(form, selectedPatient.value)
    const encounter = await saveEncounterMutation.mutateAsync(input)
    encounterId.value = encounter.id
    savedEvaluation.value = evaluationSnapshot()
    await invalidateEncounter()
    await queryClient.invalidateQueries({ queryKey: ['his-suggestions', encounter.id] })
    notify('Evaluación guardada. Revisa ahora las actividades HIS.')
    await goToStep(3)
  } catch (error) {
    showError(error)
  } finally {
    submitting.value = false
  }
}

async function saveActivities() {
  if (busy.value) return
  submitting.value = true
  saveError.value = ''
  fieldErrors.value = {}
  try {
    await saveHisMutation.mutateAsync(selectedHisLines(choices.value))
    savedActivities.value = activitiesSnapshot()
    await invalidateEncounter()
    notify('Actividades confirmadas guardadas.')
    await goToStep(4)
  } catch (error) {
    showError(error)
  } finally {
    submitting.value = false
  }
}

function finish() {
  void router.push({ name: 'encounter-detail', params: { id: encounterId.value } })
}
</script>

<template>
  <div class="encounter-page">
    <PageHeader
      eyebrow="UN REGISTRO A LA VEZ"
      :title="encounterId ? 'Continuar atención CRED' : 'Nueva atención CRED'"
      description="Un flujo claro, para que tu atención esté en quien más importa."
    >
      <RouterLink class="btn btn-ghost" to="/pacientes"
        ><ArrowLeft :size="16" />Volver a pacientes</RouterLink
      >
    </PageHeader>
    <StatePanel :loading="initializing" :error="initializationError" @retry="initialize">
      <div class="encounter-workspace">
        <nav class="wizard-progress" aria-label="Pasos de la atención">
          <ol>
            <li
              v-for="(name, index) in stepNames"
              :key="name"
              :class="{ active: step === index + 1, complete: step > index + 1 }"
            >
              <button
                type="button"
                :aria-current="step === index + 1 ? 'step' : undefined"
                :disabled="
                  index + 1 > farthestStep ||
                  busy ||
                  (index === 0 && !!encounterId) ||
                  index + 1 > step
                "
                @click="goToStep(index + 1)"
              >
                <span class="step-number"
                  ><Check v-if="step > index + 1" :size="16" /><span v-else>{{
                    String(index + 1).padStart(2, '0')
                  }}</span></span
                ><span>{{ name }}</span>
              </button>
            </li>
          </ol>
        </nav>
        <div v-if="selectedPatient && step > 1 && step < 4" class="selected-patient-bar">
          <span class="avatar">{{ initials(fullName(selectedPatient)) }}</span>
          <div>
            <strong>{{ fullName(selectedPatient) }}</strong
            ><span
              >HC {{ selectedPatient.chart_number }} <span aria-hidden="true">·</span>
              {{ ageAt(selectedPatient.birth_date, form.occurred_at) }}</span
            >
          </div>
          <span v-if="encounterId" class="saved-state"
            ><CheckCircle2 :size="15" />{{
              dirty ? 'Cambios sin guardar' : 'Borrador guardado'
            }}</span
          ><span v-else class="saved-state"
            ><ShieldCheck :size="15" />Información del paciente</span
          >
        </div>
        <Transition name="wizard-step" mode="out-in"
          ><section :key="step" :class="['wizard-content', { panel: step < 4 }]" :aria-busy="busy">
            <div class="wizard-section-heading">
              <p class="eyebrow">PASO {{ String(step).padStart(2, '0') }} DE 04</p>
              <h2 id="wizard-step-title" tabindex="-1">
                {{
                  [
                    '¿A quién atenderemos?',
                    'La evaluación, con claridad.',
                    'Registra lo que realizaste.',
                    'Revisa cada detalle.',
                  ][step - 1]
                }}
              </h2>
              <p>
                {{
                  [
                    'Busca por nombre, documento o número de historia clínica.',
                    'Registra las mediciones realizadas. Los campos clínicos son opcionales.',
                    'Las sugerencias te acompañan; tú confirmas las actividades.',
                    'La información guardada y los documentos de esta atención.',
                  ][step - 1]
                }}
              </p>
            </div>
            <template v-if="step === 1">
              <div class="patient-search">
                <Search :size="19" /><label class="sr-only" for="encounter-patient-search"
                  >Buscar paciente</label
                ><input
                  id="encounter-patient-search"
                  v-model="search"
                  type="search"
                  placeholder="Nombre, documento o historia clínica"
                  autocomplete="off"
                />
              </div>
              <StatePanel
                :loading="patientQuery.isLoading.value"
                :error="patientQuery.error.value"
                :empty="patientQuery.data.value?.data.length === 0"
                title="No encontramos pacientes"
                description="Prueba otra búsqueda o registra un paciente desde la sección Pacientes."
                @retry="patientQuery.refetch()"
              >
                <div class="patient-choices" role="radiogroup" aria-label="Paciente de la atención">
                  <label
                    v-for="patient in patientQuery.data.value?.data"
                    :key="patient.id"
                    :class="['patient-choice', { selected: selectedPatient?.id === patient.id }]"
                    ><input
                      type="radio"
                      name="encounter-patient"
                      :value="patient.id"
                      :checked="selectedPatient?.id === patient.id"
                      @change="choosePatient(patient)" /><span class="avatar">{{
                      initials(fullName(patient))
                    }}</span
                    ><span class="patient-choice-info"
                      ><strong>{{ fullName(patient) }}</strong
                      ><small
                        >HC {{ patient.chart_number }} · {{ ageAt(patient.birth_date) }}</small
                      ></span
                    ><CheckCircle2
                      v-if="selectedPatient?.id === patient.id"
                      :size="20"
                      class="selection-check"
                  /></label>
                </div>
                <div v-if="patientQuery.data.value" class="wizard-pagination">
                  <span
                    >{{ patientQuery.data.value.meta.total }} pacientes · Página {{ page }} de
                    {{ patientQuery.data.value.meta.last_page }}</span
                  >
                  <div>
                    <button
                      class="icon-btn"
                      aria-label="Página anterior de pacientes"
                      :disabled="page <= 1"
                      @click="page--"
                    >
                      <ChevronLeft :size="18" /></button
                    ><button
                      class="icon-btn"
                      aria-label="Página siguiente de pacientes"
                      :disabled="page >= patientQuery.data.value.meta.last_page"
                      @click="page++"
                    >
                      <ChevronRight :size="18" />
                    </button>
                  </div>
                </div>
              </StatePanel>
            </template>
            <form
              v-else-if="step === 2"
              id="evaluation-form"
              novalidate
              @submit.prevent="saveEvaluation"
            >
              <div class="form-grid evaluation-grid">
                <div class="field">
                  <label for="occurred_at"
                    >Fecha y hora de atención <span aria-hidden="true">*</span></label
                  ><input
                    id="occurred_at"
                    v-model="form.occurred_at"
                    type="datetime-local"
                    required
                    :max="nowLocalInput()"
                    :min="selectedPatient ? `${selectedPatient.birth_date}T00:00` : undefined"
                    :aria-invalid="!!fieldErrors.occurred_at"
                    :aria-describedby="
                      fieldErrors.occurred_at ? 'occurred_at-error' : 'timezone-note'
                    "
                    :disabled="busy"
                  /><small id="timezone-note" class="muted">Hora local de Perú · UTC−5</small>
                  <p v-if="fieldErrors.occurred_at" id="occurred_at-error" class="field-error">
                    {{ fieldErrors.occurred_at.join(' ') }}
                  </p>
                </div>
                <div class="field">
                  <label for="encounter_type">Tipo de atención</label
                  ><input
                    id="encounter_type"
                    value="CRED · Crecimiento y desarrollo"
                    readonly
                  /><small class="muted">El contrato actual admite atenciones CRED.</small>
                </div>
              </div>
              <div class="evaluation-divider">
                <h3>Mediciones</h3>
                <p>
                  Deja vacío aquello que no se midió. No se generan diagnósticos a partir de estos
                  valores.
                </p>
              </div>
              <div class="measurements-grid">
                <div class="field">
                  <label for="weight_kg">Peso <span class="unit">kg</span></label
                  ><input
                    id="weight_kg"
                    :value="form.weight_kg"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="any"
                    placeholder="Sin registrar"
                    :aria-invalid="!!fieldErrors.weight_kg"
                    :aria-describedby="fieldErrors.weight_kg ? 'weight_kg-error' : undefined"
                    :disabled="busy"
                    @input="setMeasurement('weight_kg', $event)"
                  />
                  <p v-if="fieldErrors.weight_kg" id="weight_kg-error" class="field-error">
                    {{ fieldErrors.weight_kg.join(' ') }}
                  </p>
                </div>
                <div class="field">
                  <label for="height_cm">Talla <span class="unit">cm</span></label
                  ><input
                    id="height_cm"
                    :value="form.height_cm"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="any"
                    placeholder="Sin registrar"
                    :aria-invalid="!!fieldErrors.height_cm"
                    :aria-describedby="fieldErrors.height_cm ? 'height_cm-error' : undefined"
                    :disabled="busy"
                    @input="setMeasurement('height_cm', $event)"
                  />
                  <p v-if="fieldErrors.height_cm" id="height_cm-error" class="field-error">
                    {{ fieldErrors.height_cm.join(' ') }}
                  </p>
                </div>
                <div class="field">
                  <label for="hb_g_dl">Hemoglobina <span class="unit">g/dL</span></label
                  ><input
                    id="hb_g_dl"
                    :value="form.hb_g_dl"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="any"
                    placeholder="Si corresponde"
                    :aria-invalid="!!fieldErrors.hb_g_dl"
                    :aria-describedby="fieldErrors.hb_g_dl ? 'hb_g_dl-error' : undefined"
                    :disabled="busy"
                    @input="setMeasurement('hb_g_dl', $event)"
                  />
                  <p v-if="fieldErrors.hb_g_dl" id="hb_g_dl-error" class="field-error">
                    {{ fieldErrors.hb_g_dl.join(' ') }}
                  </p>
                </div>
              </div>
              <div class="field clinical-note-field">
                <label for="clinical_note"
                  >Notas clínicas <span class="muted">(opcional)</span></label
                ><textarea
                  id="clinical_note"
                  v-model="form.clinical_note"
                  rows="5"
                  maxlength="10000"
                  placeholder="Registra los hallazgos, cuidados y orientaciones de esta atención…"
                  :aria-invalid="!!fieldErrors.clinical_note"
                  :aria-describedby="fieldErrors.clinical_note ? 'clinical_note-error' : undefined"
                  :disabled="busy"
                />
                <p v-if="fieldErrors.clinical_note" id="clinical_note-error" class="field-error">
                  {{ fieldErrors.clinical_note.join(' ') }}
                </p>
              </div>
              <p class="evaluation-help">
                <Clock3 :size="15" />Al continuar, la evaluación se guarda como borrador antes de
                consultar las sugerencias HIS.
              </p>
            </form>
            <template v-else-if="step === 3"
              ><StatePanel
                :loading="suggestionsQuery.isLoading.value"
                :error="suggestionsQuery.error.value"
                @retry="suggestionsQuery.refetch()"
                ><HisActivities v-model="choices" :busy="busy" /></StatePanel
            ></template>
            <template v-else
              ><StatePanel
                :loading="previewQuery.isLoading.value"
                :error="previewQuery.error.value"
                @retry="previewQuery.refetch()"
                ><EncounterReview
                  v-if="previewQuery.data.value"
                  :preview="previewQuery.data.value" /></StatePanel
            ></template>
            <div v-if="saveError" class="notice notice-warning save-error" role="alert">
              <strong>{{ saveError }}</strong>
              <ul v-if="Object.keys(fieldErrors).some((key) => key.startsWith('lines'))">
                <li v-for="(messages, field) in fieldErrors" :key="field">
                  {{ messages.join(' ') }}
                </li>
              </ul>
            </div>
            <div class="wizard-actions">
              <button
                v-if="step > 1 && step < 4"
                class="btn btn-secondary"
                :disabled="busy || (step === 2 && !!encounterId)"
                @click="goToStep(step - 1)"
              >
                <ArrowLeft :size="16" />Anterior</button
              ><RouterLink v-else-if="step === 1" to="/pacientes" class="btn btn-ghost"
                >Cancelar</RouterLink
              ><button v-else class="btn btn-secondary" @click="goToStep(3)">
                <ArrowLeft :size="16" />Revisar actividades
              </button>
              <button
                v-if="step === 1"
                class="btn btn-primary"
                :disabled="!selectedPatient || initializing"
                @click="continuePatient"
              >
                Continuar con la evaluación<ArrowRight :size="16" />
              </button>
              <button
                v-else-if="step === 2"
                type="submit"
                form="evaluation-form"
                class="btn btn-primary"
                :disabled="busy"
              >
                {{ busy ? 'Guardando evaluación…' : 'Guardar y revisar HIS'
                }}<ArrowRight v-if="!busy" :size="16" />
              </button>
              <button
                v-else-if="step === 3"
                class="btn btn-primary"
                :disabled="
                  busy || suggestionsQuery.isLoading.value || !!suggestionsQuery.error.value
                "
                @click="saveActivities"
              >
                {{ busy ? 'Guardando actividades…' : 'Guardar actividades y revisar'
                }}<ArrowRight v-if="!busy" :size="16" />
              </button>
              <button
                v-else
                class="btn btn-primary"
                :disabled="!previewQuery.data.value"
                @click="finish"
              >
                <CheckCircle2 :size="17" />Finalizar revisión
              </button>
            </div>
          </section></Transition
        >
        <p class="wizard-bottom-note">
          <UserRound :size="14" />Cada actividad HIS requiere tu confirmación. El registro no
          reemplaza el criterio clínico.
        </p>
      </div>
    </StatePanel>
  </div>
</template>

<style scoped>
.encounter-workspace {
  max-width: 1120px;
  margin: 0 auto;
}
.wizard-progress {
  margin-bottom: 28px;
}
.wizard-progress ol {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  list-style: none;
  padding: 0;
  margin: 0;
  position: relative;
}
.wizard-progress ol:before {
  content: '';
  position: absolute;
  left: 10%;
  right: 10%;
  height: 1px;
  background: #dce6e0;
  top: 20px;
}
.wizard-progress li {
  position: relative;
}
.wizard-progress button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  background: #f7faf9;
  border: 0;
  color: #526b5f;
  font-size: 13px;
  font-weight: 600;
  padding: 0 8px;
  cursor: pointer;
}
.wizard-progress button:disabled {
  cursor: default;
}
.step-number {
  height: 40px;
  width: 40px;
  border-radius: 50%;
  background: #fff;
  border: 1px solid #dce6e0;
  display: grid;
  place-items: center;
  font-size: 12px;
  flex-shrink: 0;
}
.wizard-progress .active button {
  color: #285e55;
}
.wizard-progress .active .step-number {
  background: #285e55;
  color: white;
  border-color: #285e55;
  box-shadow: 0 0 0 5px #e5efea;
}
.wizard-progress .complete .step-number {
  background: #e5efea;
  border-color: #e5efea;
  color: #285e55;
}
.selected-patient-bar {
  display: flex;
  align-items: center;
  gap: 13px;
  margin-bottom: 18px;
  background: #edf5f1;
  border: 1px solid #deebe3;
  border-radius: 14px;
  padding: 16px 20px;
}
.selected-patient-bar > div {
  display: grid;
  gap: 5px;
  flex: 1;
}
.selected-patient-bar strong {
  font-size: 14px;
}
.selected-patient-bar > div > span {
  font-size: 12px;
  color: #586f66;
}
.saved-state {
  font-size: 11px;
  display: flex;
  align-items: center;
  gap: 6px;
  color: #285e55;
}
.wizard-content.panel {
  padding: 32px;
}
.wizard-section-heading {
  margin-bottom: 26px;
}
.wizard-section-heading h2 {
  font-size: 25px;
  letter-spacing: -0.7px;
  font-weight: 600;
  margin: 9px 0;
}
.wizard-section-heading > p:last-child {
  font-size: 14px;
  color: #586f66;
  margin: 0;
  line-height: 1.6;
}
.patient-search {
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid #dce6e0;
  border-radius: 10px;
  padding: 0 14px;
  max-width: 100%;
  background: white;
}
.patient-search input {
  border: 0;
  box-shadow: none;
  outline: 0;
  padding: 13px 0;
  width: 100%;
  background: transparent;
  font-size: 14px;
}
.patient-search:focus-within {
  outline: 2px solid #285e55;
  outline-offset: 3px;
}
.patient-search svg {
  color: #586f66;
  flex-shrink: 0;
}
.patient-choices {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 22px;
}
.patient-choice {
  border: 1px solid #e5ece8;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 16px;
  cursor: pointer;
  background: #fff;
  min-width: 0;
}
.patient-choice.selected {
  border-color: #8daf9c;
  background: #f1f7f4;
}
.patient-choice:focus-within {
  outline: 2px solid #285e55;
  outline-offset: 3px;
}
.patient-choice input {
  width: 17px;
  height: 17px;
  accent-color: #285e55;
  flex-shrink: 0;
}
.patient-choice-info {
  min-width: 0;
}
.patient-choice-info strong {
  font-size: 13px;
  display: block;
  overflow-wrap: anywhere;
}
.patient-choice-info small {
  display: block;
  color: #586f66;
  font-size: 11px;
  margin-top: 5px;
}
.selection-check {
  color: #285e55;
  margin-left: auto;
  flex-shrink: 0;
}
.wizard-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 24px;
  color: #586f66;
  font-size: 12px;
}
.wizard-pagination > div {
  display: flex;
  gap: 6px;
}
.evaluation-grid {
  grid-template-columns: 1fr 1fr;
  gap: 22px;
}
.evaluation-grid small {
  font-size: 11px;
}
.evaluation-divider {
  margin: 30px 0 18px;
  padding-top: 25px;
  border-top: 1px solid #e5ece8;
}
.evaluation-divider h3 {
  font-size: 16px;
  margin: 0 0 8px;
}
.evaluation-divider p {
  color: #586f66;
  font-size: 12px;
  margin: 0;
  line-height: 1.6;
}
.measurements-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}
.unit {
  font-size: 11px;
  color: #586f66;
  margin-left: 5px;
}
.clinical-note-field {
  margin-top: 25px;
}
.clinical-note-field textarea {
  resize: vertical;
  min-height: 130px;
}
.evaluation-help {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 12px;
  color: #586f66;
  margin: 18px 0 0;
  line-height: 1.6;
}
.evaluation-help svg {
  flex-shrink: 0;
  margin-top: 2px;
}
.wizard-actions {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  margin-top: 30px;
  border-top: 1px solid #e5ece8;
  padding-top: 24px;
}
.wizard-actions .btn {
  font-size: 13px;
}
.wizard-bottom-note {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  color: #586f66;
  text-align: center;
  margin: 22px 0;
}
.save-error {
  margin-top: 20px;
  font-size: 13px;
}
.save-error ul {
  padding-left: 20px;
}
.wizard-step-enter-active,
.wizard-step-leave-active {
  transition:
    opacity 180ms,
    transform 180ms;
}
.wizard-step-enter-from,
.wizard-step-leave-to {
  opacity: 0;
  transform: translateY(7px);
}
@media (max-width: 760px) {
  .wizard-progress button {
    flex-direction: column;
    gap: 10px;
    font-size: 11px;
  }
  .wizard-progress ol:before {
    left: 12%;
    right: 12%;
  }
  .wizard-content.panel {
    padding: 24px;
  }
  .patient-choices {
    grid-template-columns: 1fr;
  }
  .measurements-grid {
    gap: 12px;
  }
  .saved-state {
    display: none;
  }
  .wizard-section-heading h2 {
    font-size: 23px;
  }
}
@media (max-width: 520px) {
  .wizard-content.panel {
    padding: 18px;
  }
  .wizard-progress {
    margin: 4px -8px 24px;
  }
  .wizard-progress button {
    padding: 0 2px;
    font-size: 10px;
  }
  .wizard-section-heading h2 {
    font-size: 21px;
  }
  .evaluation-grid,
  .measurements-grid {
    grid-template-columns: 1fr;
  }
  .selected-patient-bar {
    padding: 14px;
  }
  .wizard-actions {
    flex-wrap: wrap;
  }
  .wizard-actions > .btn {
    width: 100%;
    justify-content: center;
  }
  .wizard-actions > .btn:first-child {
    order: 2;
  }
  .wizard-bottom-note {
    align-items: flex-start;
    text-align: left;
    line-height: 1.6;
  }
  .wizard-bottom-note svg {
    flex-shrink: 0;
    margin-top: 2px;
  }
  .patient-choice {
    padding: 13px;
  }
  .wizard-pagination {
    font-size: 11px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .wizard-step-enter-active,
  .wizard-step-leave-active {
    transition: none;
  }
}
</style>
