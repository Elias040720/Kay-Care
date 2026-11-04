<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { Save, ShieldCheck } from '@lucide/vue'
import { api } from '@/lib/api'
import { isDemonstrationData as isDemo } from '@/app/environment'
import { normalizeError } from '@/lib/api/errors'
import type { Patient, PatientWrite } from '@/types'
import { patientSchema } from '@/lib/validation/patient'
import { todayInPeru } from '@/lib/format'
import { useToast } from '@/composables/useToast'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
const props = defineProps<{ patient?: Patient | null }>()
const emit = defineEmits<{ saved: [patient: Patient]; cancel: [] }>()
const queryClient = useQueryClient()
const { notify } = useToast()
const blank = (): PatientWrite => ({
  chart_number: '',
  first_name: '',
  last_name: '',
  birth_date: '',
  sex: 'X',
  document_type: null,
  document_number: null,
  locality: null,
})
const form = reactive<PatientWrite>(blank())
const initial = ref('')
const errors = ref<Record<string, string>>({})
const message = ref('')
const submitting = ref(false)
const formElement = ref<HTMLFormElement>()
watch(
  () => props.patient,
  (patient) => {
    const { id: _id, ...values } = patient || { ...blank(), id: '' }
    Object.assign(form, values)
    initial.value = JSON.stringify(form)
    errors.value = {}
    message.value = ''
  },
  { immediate: true },
)
const dirty = computed(() => initial.value !== JSON.stringify(form))
const { confirmDiscard } = useUnsavedChanges(dirty)
const mutation = useMutation({
  mutationFn: (values: PatientWrite) =>
    props.patient ? api.updatePatient(props.patient.id, values) : api.createPatient(values),
})
async function save() {
  if (submitting.value) return
  errors.value = {}
  message.value = ''
  const parsed = patientSchema.safeParse(form)
  if (!parsed.success) {
    for (const issue of parsed.error.issues) errors.value[String(issue.path[0])] = issue.message
    await nextTick()
    formElement.value?.querySelector<HTMLElement>('[aria-invalid=true]')?.focus()
    return
  }
  submitting.value = true
  try {
    const saved = await mutation.mutateAsync(parsed.data)
    initial.value = JSON.stringify(form)
    await Promise.all(
      ['patients', 'patient', 'dashboard', 'patient-encounters'].map((key) =>
        queryClient.invalidateQueries({ queryKey: [key] }),
      ),
    )
    notify(
      props.patient
        ? 'Los datos del paciente se actualizaron.'
        : 'Paciente registrado. Todo listo para su atención.',
    )
    emit('saved', saved)
  } catch (e) {
    const error = normalizeError(e)
    message.value = error.message
    for (const [field, values] of Object.entries(error.fields))
      errors.value[field] = values.join(' ')
    await nextTick()
    formElement.value?.querySelector<HTMLElement>('[aria-invalid=true]')?.focus()
  } finally {
    submitting.value = false
  }
}
function canClose() {
  return !submitting.value && confirmDiscard()
}
defineExpose({ canClose })
</script>
<template>
  <form ref="formElement" novalidate @submit.prevent="save">
    <p class="muted" style="font-size: 12px; line-height: 1.8; margin-bottom: 24px">
      Los campos con * son obligatorios. Puedes registrar a un paciente sin documento de identidad.
    </p>
    <div v-if="isDemo" class="notice notice-info">
      <ShieldCheck :size="16" /><span
        >Estás en modo demo. Utiliza únicamente información ficticia.</span
      >
    </div>
    <div v-if="message" class="notice notice-error" role="alert">{{ message }}</div>
    <fieldset :disabled="submitting" class="form-grid" style="border: 0; padding: 0; margin: 0">
      <div class="field span-2">
        <label for="patient-chart">N.º de historia clínica *</label
        ><input
          id="patient-chart"
          v-model="form.chart_number"
          :aria-invalid="!!errors.chart_number"
          aria-describedby="error-chart"
          placeholder="Ej. DEMO-HC-020"
          maxlength="30"
          autocomplete="off"
        /><span id="error-chart" class="field-error">{{ errors.chart_number }}</span>
      </div>
      <div class="field">
        <label for="patient-first">Nombres *</label
        ><input
          id="patient-first"
          v-model="form.first_name"
          :aria-invalid="!!errors.first_name"
          aria-describedby="error-first"
          maxlength="100"
          autocomplete="off"
        /><span id="error-first" class="field-error">{{ errors.first_name }}</span>
      </div>
      <div class="field">
        <label for="patient-last">Apellidos *</label
        ><input
          id="patient-last"
          v-model="form.last_name"
          :aria-invalid="!!errors.last_name"
          aria-describedby="error-last"
          maxlength="150"
          autocomplete="off"
        /><span id="error-last" class="field-error">{{ errors.last_name }}</span>
      </div>
      <div class="field">
        <label for="patient-birth">Fecha de nacimiento *</label
        ><input
          id="patient-birth"
          v-model="form.birth_date"
          type="date"
          min="1900-01-01"
          :max="todayInPeru()"
          :aria-invalid="!!errors.birth_date"
          aria-describedby="error-birth"
        /><span id="error-birth" class="field-error">{{ errors.birth_date }}</span>
      </div>
      <div class="field">
        <label for="patient-sex">Sexo registrado *</label
        ><select
          id="patient-sex"
          v-model="form.sex"
          :aria-invalid="!!errors.sex"
          aria-describedby="error-sex"
        >
          <option value="X">X · No especificado</option>
          <option value="F">F · Femenino</option>
          <option value="M">M · Masculino</option></select
        ><span id="error-sex" class="field-error">{{ errors.sex }}</span>
      </div>
      <div class="field">
        <label for="patient-document-type">Tipo de documento <span>Opcional</span></label
        ><select
          id="patient-document-type"
          v-model="form.document_type"
          :aria-invalid="!!errors.document_type"
          aria-describedby="error-document-type"
        >
          <option :value="null">Sin documento</option>
          <option value="DNI">DNI</option>
          <option value="CE">Carné de extranjería</option>
          <option value="PASAPORTE">Pasaporte</option>
          <option value="CNV">Certificado de nacido vivo</option>
          <option value="OTRO">Otro</option></select
        ><span id="error-document-type" class="field-error">{{ errors.document_type }}</span>
      </div>
      <div class="field">
        <label for="patient-document">N.º de documento <span>Opcional</span></label
        ><input
          id="patient-document"
          v-model="form.document_number"
          :aria-invalid="!!errors.document_number"
          aria-describedby="error-document"
          minlength="4"
          maxlength="20"
          autocomplete="off"
        /><span id="error-document" class="field-error">{{ errors.document_number }}</span>
      </div>
      <div class="field span-2">
        <label for="patient-locality">Localidad <span>Opcional</span></label
        ><input
          id="patient-locality"
          v-model="form.locality"
          :aria-invalid="!!errors.locality"
          aria-describedby="error-locality"
          maxlength="150"
          autocomplete="off"
        /><span id="error-locality" class="field-error">{{ errors.locality }}</span>
      </div>
    </fieldset>
    <div class="form-actions">
      <button
        type="button"
        class="btn btn-secondary"
        :disabled="submitting"
        @click="emit('cancel')"
      >
        Cancelar</button
      ><button type="submit" class="btn btn-primary" :disabled="submitting">
        <Save :size="16" />{{ submitting ? 'Guardando…' : 'Guardar paciente' }}
      </button>
    </div>
  </form>
</template>
