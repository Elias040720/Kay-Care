<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import {
  ArrowLeft,
  Pencil,
  ClipboardPlus,
  CalendarDays,
  MapPin,
  FileHeart,
  ArrowUpRight,
} from '@lucide/vue'
import { api } from '@/lib/api'
import { fullName, initials, ageAt, dateLabel } from '@/lib/format'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatePanel from '@/components/ui/StatePanel.vue'
import AppDrawer from '@/components/ui/AppDrawer.vue'
import PatientForm from './PatientForm.vue'
const route = useRoute()
const id = computed(() => String(route.params.id))
const patient = useQuery({ queryKey: ['patient', id], queryFn: () => api.getPatient(id.value) })
const encounters = useQuery({
  queryKey: ['patient-encounters', id],
  queryFn: () => api.patientEncounters(id.value),
})
const sorted = computed(() =>
  [...(encounters.data.value || [])].sort(
    (a, b) => Date.parse(b.occurred_at) - Date.parse(a.occurred_at),
  ),
)
const editing = ref(false)
const form = ref<InstanceType<typeof PatientForm>>()
function close() {
  if (form.value?.canClose() !== false) editing.value = false
}
</script>
<template>
  <div>
    <RouterLink to="/pacientes" class="back-link"
      ><ArrowLeft :size="15" />Todos los pacientes</RouterLink
    >
    <StatePanel
      :loading="patient.isPending.value"
      :error="patient.error.value"
      @retry="patient.refetch()"
      ><template v-if="patient.data.value">
        <PageHeader
          eyebrow="FICHA DEL PACIENTE"
          :title="fullName(patient.data.value)"
          :description="`Historia clínica ${patient.data.value.chart_number}`"
          ><button class="btn btn-secondary" @click="editing = true">
            <Pencil :size="15" />Editar datos</button
          ><RouterLink
            :to="{ name: 'encounter-new', query: { patient: id } }"
            class="btn btn-primary"
            ><ClipboardPlus :size="17" />Nueva atención</RouterLink
          ></PageHeader
        >
        <div class="patient-detail-grid">
          <section class="panel patient-summary">
            <span class="avatar patient-avatar">{{ initials(fullName(patient.data.value)) }}</span>
            <h2>{{ patient.data.value.first_name }}</h2>
            <p class="muted">{{ patient.data.value.last_name }}</p>
            <span class="badge badge-neutral">{{ ageAt(patient.data.value.birth_date) }}</span>
            <dl>
              <div>
                <dt><CalendarDays :size="15" />Nacimiento</dt>
                <dd>{{ dateLabel(patient.data.value.birth_date) }}</dd>
              </div>
              <div>
                <dt>Sexo registrado</dt>
                <dd>
                  {{
                    { F: 'Femenino', M: 'Masculino', X: 'No especificado' }[patient.data.value.sex]
                  }}
                </dd>
              </div>
              <div>
                <dt>Documento</dt>
                <dd>
                  {{
                    patient.data.value.document_number
                      ? `${patient.data.value.document_type} ${patient.data.value.document_number}`
                      : 'Sin documento registrado'
                  }}
                </dd>
              </div>
              <div>
                <dt><MapPin :size="15" />Localidad</dt>
                <dd>{{ patient.data.value.locality || 'Sin registrar' }}</dd>
              </div>
            </dl>
            <p class="patient-reminder">
              <FileHeart :size="17" />Una historia que continúa<br />con cada atención.
            </p>
          </section>
          <section class="panel history-panel">
            <div class="section-heading">
              <div>
                <p class="eyebrow">CONTINUIDAD DEL CUIDADO</p>
                <h2>Historial de atenciones</h2>
                <p>Evaluaciones y actividades registradas para este paciente.</p>
              </div>
              <span class="count-pill">{{ sorted.length }}</span>
            </div>
            <StatePanel
              :loading="encounters.isPending.value"
              :error="encounters.error.value"
              :empty="!sorted.length"
              title="Su historia comienza contigo"
              description="Registra la primera atención CRED de este paciente."
              @retry="encounters.refetch()"
              ><div class="encounter-timeline">
                <RouterLink
                  v-for="encounter in sorted"
                  :key="encounter.id"
                  :to="`/atenciones/${encounter.id}`"
                  class="history-item"
                  ><span class="history-symbol"><FileHeart :size="20" /></span>
                  <div>
                    <div class="history-item-heading">
                      <h3>Control de crecimiento y desarrollo</h3>
                      <span
                        :class="[
                          'badge',
                          encounter.status === 'reviewed' ? 'badge-success' : 'badge-warning',
                        ]"
                        >{{ encounter.status === 'reviewed' ? 'Revisada' : 'Borrador' }}</span
                      >
                    </div>
                    <p>{{ dateLabel(encounter.occurred_at, true) }}</p>
                    <div class="history-measurements">
                      <span
                        >Peso
                        <strong>{{
                          encounter.weight_kg !== null ? `${encounter.weight_kg} kg` : '—'
                        }}</strong></span
                      ><span
                        >Talla
                        <strong>{{
                          encounter.height_cm !== null ? `${encounter.height_cm} cm` : '—'
                        }}</strong></span
                      ><span
                        >Edad
                        <strong>{{
                          ageAt(patient.data.value!.birth_date, encounter.occurred_at)
                        }}</strong></span
                      >
                    </div>
                  </div>
                  <ArrowUpRight :size="17"
                /></RouterLink></div
            ></StatePanel>
          </section>
        </div>
        <AppDrawer :open="editing" title="Editar datos del paciente" @close="close"
          ><PatientForm
            v-if="editing"
            ref="form"
            :patient="patient.data.value"
            @cancel="close"
            @saved="editing = false"
        /></AppDrawer> </template
    ></StatePanel>
  </div>
</template>
<style scoped>
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: #526b5f;
  font-size: 11px;
  margin-bottom: 24px;
}
.patient-detail-grid {
  display: grid;
  grid-template-columns: 265px 1fr;
  gap: 22px;
  align-items: start;
}
.patient-summary {
  text-align: center;
}
.patient-avatar {
  width: 68px;
  height: 68px;
  font-size: 21px;
  border-radius: 21px;
  margin: 6px 0 17px;
}
.patient-summary h2 {
  font-size: 22px;
}
.patient-summary > p.muted {
  font-size: 12px;
  margin-top: 5px;
}
.patient-summary > .badge {
  margin-top: 14px;
}
.patient-summary dl {
  margin: 25px 0 0;
  text-align: left;
  border-top: 1px solid #e5ece8;
  padding-top: 7px;
}
.patient-summary dl > div {
  padding: 13px 0;
}
.patient-summary dt {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 11px;
  color: #526b5f;
}
.patient-summary dd {
  font-size: 12px;
  margin: 7px 0 0;
  font-weight: 500;
  overflow-wrap: anywhere;
}
.patient-reminder {
  border-top: 1px solid #e5ece8;
  padding-top: 20px;
  margin-top: 10px;
  font-size: 11px;
  line-height: 1.7;
  color: #526b5f;
  display: flex;
  justify-content: center;
  gap: 10px;
  align-items: center;
}
.count-pill {
  padding: 6px 10px;
  border-radius: 7px;
  background: #eff5f0;
  font-size: 11px;
  color: #526b5f;
}
.history-item {
  display: grid;
  grid-template-columns: 40px 1fr 17px;
  gap: 13px;
  padding: 24px 0;
  border-top: 1px solid #e9eeea;
  align-items: start;
}
.history-item:hover h3 {
  color: #526b5f;
}
.history-symbol {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #edf5ef;
  color: #526b5f;
  border-radius: 12px;
}
.history-item-heading {
  display: flex;
  align-items: center;
  gap: 12px;
  justify-content: space-between;
  flex-wrap: wrap;
}
.history-item h3 {
  font-size: 13px;
  line-height: 1.6;
}
.history-item p {
  font-size: 11px;
  margin-top: 7px;
  color: #526b5f;
}
.history-item > svg {
  margin-top: 7px;
  color: #526b5f;
}
.history-measurements {
  display: flex;
  flex-wrap: wrap;
  gap: 18px;
  margin-top: 16px;
}
.history-measurements span {
  font-size: 11px;
  color: #526b5f;
}
.history-measurements strong {
  font-weight: 500;
  color: #4e6958;
  margin-left: 5px;
}
@media (max-width: 1100px) {
  .patient-detail-grid {
    grid-template-columns: 230px 1fr;
  }
  .history-item {
    grid-template-columns: 1fr 17px;
  }
  .history-symbol {
    display: none;
  }
}
@media (max-width: 700px) {
  .patient-detail-grid {
    grid-template-columns: 1fr;
  }
  .patient-summary dl {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 15px;
  }
  .patient-reminder {
    display: none;
  }
  .history-item-heading {
    align-items: flex-start;
  }
  .patient-summary {
    padding: 22px;
  }
}
</style>
