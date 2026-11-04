<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import {
  Search,
  UserPlus,
  ArrowUpRight,
  ChevronLeft,
  ChevronRight,
  UsersRound,
  X,
} from '@lucide/vue'
import { api } from '@/lib/api'
import { ageAt, fullName, initials } from '@/lib/format'
import { useDebouncedRef } from '@/composables/useDebouncedRef'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatePanel from '@/components/ui/StatePanel.vue'
import AppDrawer from '@/components/ui/AppDrawer.vue'
import PatientForm from './PatientForm.vue'
const route = useRoute()
const search = ref('')
const debounced = useDebouncedRef(search)
const page = ref(1)
const drawerOpen = ref(route.query.new === '1')
const patientForm = ref<InstanceType<typeof PatientForm>>()
watch(debounced, () => {
  page.value = 1
})
const patients = useQuery({
  queryKey: ['patients', debounced, page],
  queryFn: () => api.listPatients(debounced.value, page.value),
})
function close() {
  if (patientForm.value?.canClose() !== false) drawerOpen.value = false
}
</script>
<template>
  <div>
    <PageHeader
      eyebrow="CADA PERSONA, UNA HISTORIA"
      title="Pacientes"
      description="Encuentra sus datos, conoce su historia y continúa su cuidado."
      ><button data-drawer-trigger class="btn btn-primary" @click="drawerOpen = true">
        <UserPlus :size="17" />Registrar paciente
      </button></PageHeader
    >
    <section class="panel patients-panel">
      <div class="patients-toolbar">
        <div class="list-count">
          <UsersRound :size="19" /><strong>Todos los pacientes</strong
          ><span v-if="patients.data.value" class="count-pill">{{
            patients.data.value.meta.total
          }}</span>
        </div>
        <div class="patient-search">
          <Search :size="17" /><label for="patient-search" class="sr-only"
            >Buscar por nombre, DNI o historia clínica</label
          ><input
            id="patient-search"
            v-model="search"
            placeholder="Nombre, DNI o N.º de HC"
            type="search"
            autocomplete="off"
          /><button
            v-if="search"
            class="icon-btn"
            aria-label="Limpiar búsqueda"
            @click="search = ''"
          >
            <X :size="15" />
          </button>
        </div>
      </div>
      <StatePanel
        :loading="patients.isPending.value"
        :error="patients.error.value"
        :empty="!patients.data.value?.data.length"
        :title="search ? 'No encontramos coincidencias' : 'El primer registro comienza aquí'"
        :description="
          search
            ? 'Prueba con otro nombre, documento o número de historia clínica.'
            : 'Registra un paciente para empezar a acompañar su cuidado.'
        "
        @retry="patients.refetch()"
      >
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Paciente</th>
                <th>Historia clínica</th>
                <th>Edad actual</th>
                <th>Localidad</th>
                <th><span class="sr-only">Acciones</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(patient, index) in patients.data.value?.data" :key="patient.id">
                <td>
                  <RouterLink :to="`/pacientes/${patient.id}`" class="patient-cell"
                    ><span
                      :class="[
                        'avatar',
                        index % 3 === 1 ? 'lavender' : index % 3 === 2 ? 'peach' : '',
                      ]"
                      >{{ initials(fullName(patient)) }}</span
                    ><span
                      ><strong>{{ fullName(patient) }}</strong
                      ><small>{{
                        patient.document_number
                          ? `${patient.document_type || 'Documento'} ${patient.document_number}`
                          : 'Sin documento registrado'
                      }}</small></span
                    ></RouterLink
                  >
                </td>
                <td>
                  <span class="chart-number">{{ patient.chart_number }}</span>
                </td>
                <td>{{ ageAt(patient.birth_date) }}</td>
                <td class="muted">{{ patient.locality || 'Sin registrar' }}</td>
                <td>
                  <RouterLink
                    :to="`/pacientes/${patient.id}`"
                    class="icon-btn"
                    :aria-label="`Ver ficha de ${fullName(patient)}`"
                    ><ArrowUpRight :size="18"
                  /></RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </StatePanel>
      <div v-if="patients.data.value && patients.data.value.meta.total > 0" class="pagination">
        <span
          >{{ patients.data.value.meta.total }}
          {{ patients.data.value.meta.total === 1 ? 'paciente' : 'pacientes' }} · Página
          {{ patients.data.value.meta.current_page }} de
          {{ patients.data.value.meta.last_page }}</span
        >
        <div>
          <button
            class="icon-btn"
            aria-label="Página anterior"
            :disabled="page <= 1 || patients.isFetching.value"
            @click="page--"
          >
            <ChevronLeft :size="18" /></button
          ><span class="page-number">{{ page }}</span
          ><button
            class="icon-btn"
            aria-label="Página siguiente"
            :disabled="page >= patients.data.value.meta.last_page || patients.isFetching.value"
            @click="page++"
          >
            <ChevronRight :size="18" />
          </button>
        </div>
      </div>
    </section>
    <p class="list-footnote">
      La edad se muestra a la fecha actual. En cada atención se calcula según su fecha de registro.
    </p>
    <AppDrawer :open="drawerOpen" title="Registrar paciente" @close="close"
      ><PatientForm v-if="drawerOpen" ref="patientForm" @cancel="close" @saved="drawerOpen = false"
    /></AppDrawer>
  </div>
</template>
<style scoped>
.patients-panel {
  padding: 0;
  overflow: hidden;
}
.patients-toolbar {
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
}
.list-count {
  display: flex;
  align-items: center;
  gap: 9px;
}
.list-count > svg {
  color: #526b5f;
}
.list-count strong {
  font-size: 13px;
}
.count-pill {
  font-size: 11px;
  background: #edf3ee;
  color: #526b5f;
  border-radius: 6px;
  padding: 3px 7px;
}
.patient-search {
  display: flex;
  align-items: center;
  gap: 9px;
  border: 1px solid #e1e9e3;
  background: #fbfcfb;
  border-radius: 9px;
  padding: 0 11px;
  min-width: 290px;
  color: #526b5f;
}
.patient-search input {
  border: 0;
  background: transparent;
  padding: 11px 0;
  outline-offset: 3px;
  min-width: 0;
  width: 100%;
  font-size: 11px;
  color: #19312d;
}
.chart-number {
  font-variant-numeric: tabular-nums;
  font-size: 11px;
  color: #526b5f;
}
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 17px 24px;
  border-top: 1px solid #e5ece8;
  font-size: 11px;
  color: #526b5f;
}
.pagination > div {
  display: flex;
  align-items: center;
  gap: 6px;
}
.page-number {
  background: #edf4ef;
  color: #345e47;
  border-radius: 6px;
  padding: 7px 12px;
  font-weight: 600;
}
.list-footnote {
  font-size: 11px;
  color: #526b5f;
  margin: 17px 4px;
  line-height: 1.7;
}
@media (max-width: 800px) {
  .patients-toolbar {
    flex-wrap: wrap;
    padding: 20px;
  }
  .patient-search {
    flex: 1;
  }
}
@media (max-width: 480px) {
  .patient-search {
    min-width: 100%;
  }
  .pagination {
    padding: 14px;
    font-size: 11px;
    gap: 5px;
  }
  .pagination > div {
    gap: 0;
  }
  .data-table td {
    padding: 14px;
  }
  .data-table th {
    padding: 12px 14px;
  }
}
</style>
