<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import { ArrowLeft, PencilLine } from '@lucide/vue'
import { api } from '@/lib/api'
import { dateLabel } from '@/lib/format'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatePanel from '@/components/ui/StatePanel.vue'
import EncounterReview from './EncounterReview.vue'
const route = useRoute()
const id = computed(() => String(route.params.id))
const previewQuery = useQuery({
  queryKey: computed(() => ['his-preview', id.value]),
  queryFn: () => api.hisPreview(id.value),
})
</script>
<template>
  <div class="encounter-detail-page">
    <PageHeader
      eyebrow="HISTORIA DE ATENCIÓN"
      title="Un cuidado bien registrado."
      :description="
        previewQuery.data.value
          ? `Atención CRED · ${dateLabel(previewQuery.data.value.encounter.occurred_at, true)}`
          : 'Resumen clínico, actividades HIS y documentos.'
      "
    >
      <RouterLink
        v-if="previewQuery.data.value"
        class="btn btn-secondary"
        :to="`/pacientes/${previewQuery.data.value.patient.id}`"
        ><ArrowLeft :size="16" />Ficha del paciente</RouterLink
      >
      <RouterLink class="btn btn-primary" :to="{ name: 'encounter-new', query: { encounter: id } }"
        ><PencilLine :size="16" />Editar atención</RouterLink
      >
    </PageHeader>
    <StatePanel
      :loading="previewQuery.isLoading.value"
      :error="previewQuery.error.value"
      @retry="previewQuery.refetch()"
      ><EncounterReview v-if="previewQuery.data.value" :preview="previewQuery.data.value"
    /></StatePanel>
  </div>
</template>
