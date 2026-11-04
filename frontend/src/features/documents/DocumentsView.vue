<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { Search, FileText, ArrowUpRight, ShieldCheck, Files } from '@lucide/vue'
import { api } from '@/lib/api'
import { isDemonstrationData as isDemo } from '@/app/environment'
import { useDebouncedRef } from '@/composables/useDebouncedRef'
import { fullName, dateLabel } from '@/lib/format'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatePanel from '@/components/ui/StatePanel.vue'
const search = ref('')
const debounced = useDebouncedRef(search)
const selected = ref('')
const page = ref(1)
watch(debounced, () => {
  page.value = 1
})
const patients = useQuery({
  queryKey: ['patients', debounced, page],
  queryFn: () => api.listPatients(debounced.value, page.value),
})
const history = useQuery({
  queryKey: ['patient-encounters', selected],
  queryFn: () => api.patientEncounters(selected.value),
  enabled: computed(() => !!selected.value),
})
</script>
<template>
  <div>
    <PageHeader
      eyebrow="INFORMACIÓN PARA DAR CONTINUIDAD"
      title="Documentación"
      description="Encuentra una atención para revisar el registro HIS, el resumen clínico y sus documentos."
    />
    <div v-if="isDemo" class="notice notice-warning">
      <ShieldCheck :size="17" /><span
        >Los documentos de demostración están marcados como DEMO y no tienen validez oficial. No son
        historias clínicas oficiales.</span
      >
    </div>
    <div class="documents-grid">
      <section class="panel">
        <div class="section-heading">
          <div>
            <p class="eyebrow">01 · ENCUENTRA A TU PACIENTE</p>
            <h2>Buscar en el registro</h2>
          </div>
          <Search :size="20" class="muted" />
        </div>
        <div class="field">
          <label for="document-search">Nombre, DNI o historia clínica</label
          ><input
            id="document-search"
            v-model="search"
            type="search"
            placeholder="Escribe para buscar…"
          />
        </div>
        <StatePanel
          :loading="patients.isPending.value"
          :error="patients.error.value"
          :empty="!patients.data.value?.data.length"
          title="Sin coincidencias"
          description="Intenta con otro nombre o número de historia clínica."
          @retry="patients.refetch()"
          ><div class="document-patients">
            <button
              v-for="patient in patients.data.value?.data"
              :key="patient.id"
              :class="['document-patient', { selected: selected === patient.id }]"
              :aria-pressed="selected === patient.id"
              @click="selected = patient.id"
            >
              <span
                ><strong>{{ fullName(patient) }}</strong
                ><small>HC {{ patient.chart_number }}</small></span
              ><ArrowUpRight :size="15" />
            </button>
          </div>
          <div
            v-if="patients.data.value && patients.data.value.meta.last_page > 1"
            class="document-pagination"
          >
            <button class="btn btn-ghost" :disabled="page <= 1" @click="page--">Anterior</button
            ><span>{{ page }} / {{ patients.data.value.meta.last_page }}</span
            ><button
              class="btn btn-ghost"
              :disabled="page >= patients.data.value.meta.last_page"
              @click="page++"
            >
              Siguiente
            </button>
          </div></StatePanel
        >
      </section>
      <section class="panel">
        <div class="section-heading">
          <div>
            <p class="eyebrow">02 · SELECCIONA UNA ATENCIÓN</p>
            <h2>Registros y documentos</h2>
          </div>
          <Files :size="20" class="muted" />
        </div>
        <StatePanel
          v-if="!selected"
          empty
          title="Cada documento pertenece a una atención"
          description="Selecciona un paciente para consultar sus registros disponibles."
        /><StatePanel
          v-else
          :loading="history.isPending.value"
          :error="history.error.value"
          :empty="!history.data.value?.length"
          title="Aún no hay atenciones"
          description="Registra una atención CRED para revisar su documentación."
          @retry="history.refetch()"
          ><RouterLink
            v-for="encounter in history.data.value"
            :key="encounter.id"
            :to="`/atenciones/${encounter.id}`"
            class="document-encounter"
            ><span class="avatar"><FileText :size="19" /></span
            ><span
              ><strong>Atención CRED</strong
              ><small>{{ dateLabel(encounter.occurred_at, true) }}</small
              ><small>Vista previa HIS · Resumen clínico · PDFs</small></span
            ><ArrowUpRight :size="18" /></RouterLink
        ></StatePanel>
        <p class="documents-note">
          La disponibilidad y validación de los documentos dependen de los datos guardados y del
          servicio clínico.
        </p>
      </section>
    </div>
  </div>
</template>
<style scoped>
.documents-grid {
  display: grid;
  grid-template-columns: 370px 1fr;
  gap: 23px;
  align-items: start;
}
.document-patients {
  margin-top: 22px;
}
.document-patient {
  display: flex;
  justify-content: space-between;
  align-items: center;
  text-align: left;
  gap: 12px;
  width: 100%;
  border: 1px solid transparent;
  border-bottom-color: #edf1ed;
  background: #fff;
  padding: 14px 10px;
  border-radius: 8px;
  color: #48624e;
}
.document-patient.selected {
  background: #edf5ee;
  border-color: #cedfce;
}
.document-patient:hover {
  background: #f5f9f3;
}
.document-patient strong {
  font-size: 11px;
  font-weight: 600;
  display: block;
}
.document-patient small {
  font-size: 11px;
  display: block;
  color: #526b5f;
  margin-top: 5px;
}
.document-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11px;
  margin-top: 15px;
}
.document-pagination .btn {
  font-size: 11px;
  padding: 8px;
}
.document-encounter {
  display: flex;
  gap: 14px;
  align-items: center;
  padding: 22px 0;
  border-top: 1px solid #e8eee7;
}
.document-encounter > span:nth-child(2) {
  flex: 1;
}
.document-encounter strong {
  font-size: 13px;
  display: block;
}
.document-encounter small {
  display: block;
  margin-top: 7px;
  font-size: 11px;
  color: #526b5f;
}
.document-encounter > svg {
  color: #526b5f;
}
.documents-note {
  font-size: 11px;
  color: #526b5f;
  line-height: 1.8;
  margin-top: 20px;
  padding-top: 18px;
  border-top: 1px solid #e8eee7;
}
@media (max-width: 1100px) {
  .documents-grid {
    grid-template-columns: 1fr 1.3fr;
  }
}
@media (max-width: 760px) {
  .documents-grid {
    grid-template-columns: 1fr;
  }
}
</style>
