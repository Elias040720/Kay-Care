<script setup lang="ts">
import { computed } from 'vue'
import { AlertTriangle, CheckCircle2, ListChecks } from '@lucide/vue'
import type { HisChoice } from '@/features/encounters/validation'

const choices = defineModel<HisChoice[]>({ required: true })
defineProps<{ busy?: boolean }>()
const selectedCount = computed(() => choices.value.filter((choice) => choice.selected).length)
const hasDrafts = computed(() =>
  choices.value.some((choice) => choice.verification_status !== 'verified'),
)
</script>

<template>
  <div class="his-activities">
    <div class="notice notice-info">
      <ListChecks :size="20" />
      <div>
        <strong>Confirma únicamente lo que realizaste.</strong>
        <p>
          Las sugerencias no acreditan actividades ni controles anteriores. Revisa cada código, tipo
          de diagnóstico y valor LAB antes de guardar.
        </p>
      </div>
    </div>
    <div v-if="hasDrafts" class="notice notice-warning" role="note">
      <AlertTriangle :size="20" />
      <div>
        <strong>Catálogo pendiente de validación</strong>
        <p>
          Las actividades señaladas como «Sin verificar» requieren revisión. Los códigos DEMO son
          ficticios y no son códigos oficiales del MINSA.
        </p>
      </div>
    </div>
    <div class="his-list-heading">
      <h3>Actividades sugeridas</h3>
      <span class="badge">{{ selectedCount }} confirmadas</span>
    </div>
    <div v-if="!choices.length" class="empty-his">
      <ListChecks :size="26" /><strong>No hay sugerencias disponibles</strong>
      <p>Puedes continuar sin actividades HIS. No se agregarán códigos automáticamente.</p>
    </div>
    <article
      v-for="(choice, index) in choices"
      :key="choice.catalog_item_id"
      :class="['his-activity', { 'is-selected': choice.selected }]"
    >
      <div class="his-activity-top">
        <label class="activity-confirm" :for="`activity-${index}`">
          <input
            :id="`activity-${index}`"
            v-model="choice.selected"
            type="checkbox"
            :disabled="busy"
          />
          <span
            ><strong>{{ choice.label }}</strong
            ><span class="activity-code">{{ choice.code }}</span></span
          >
        </label>
        <span
          :class="[
            'badge',
            choice.verification_status === 'verified' ? 'badge-success' : 'badge-warning',
          ]"
          ><CheckCircle2 v-if="choice.verification_status === 'verified'" :size="13" />{{
            choice.verification_status === 'verified' ? 'Verificado' : 'Sin verificar'
          }}</span
        >
      </div>
      <p class="activity-source">Fuente: {{ choice.source || 'No informada' }}</p>
      <p class="activity-suggestion">
        Propuesta del catálogo: tipo {{ choice.diagnosis_type_suggested || 'no indicado' }} · LAB
        {{ choice.lab_suggested || 'no indicado' }}
      </p>
      <Transition name="his-expand"
        ><div v-if="choice.selected" class="his-values">
          <div class="field">
            <label :for="`diagnosis-${index}`">Tipo de diagnóstico</label
            ><select :id="`diagnosis-${index}`" v-model="choice.diagnosis_type" :disabled="busy">
              <option value="">Sin indicar</option>
              <option value="P">P · Presuntivo</option>
              <option value="D">D · Definitivo</option>
              <option value="R">R · Repetido</option>
            </select>
          </div>
          <div class="field">
            <label :for="`lab-${index}`"
              >Valor LAB <span class="muted">(si corresponde)</span></label
            ><input
              :id="`lab-${index}`"
              v-model="choice.lab"
              type="text"
              maxlength="100"
              autocomplete="off"
              placeholder="Ingresa un valor validado"
              :disabled="busy"
            />
          </div>
          <p class="lab-guidance">
            El valor propuesto no se completa automáticamente. Confirma su pertinencia con el
            catálogo validado.
          </p>
        </div></Transition
      >
    </article>
  </div>
</template>

<style scoped>
.his-activities {
  display: grid;
  gap: 16px;
}
.his-activities .notice {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.his-activities .notice svg {
  flex-shrink: 0;
  margin-top: 2px;
}
.his-activities .notice p {
  margin: 4px 0 0;
  line-height: 1.6;
  font-size: 13px;
}
.his-list-heading,
.his-activity-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.his-list-heading {
  margin: 10px 0 0;
}
.his-list-heading h3 {
  font-size: 16px;
}
.his-activity {
  padding: 20px;
  border: 1px solid #e5ece8;
  border-radius: 14px;
  background: #fff;
  transition:
    border-color 180ms,
    background 180ms;
}
.his-activity.is-selected {
  border-color: #8daf9c;
  background: #fbfdfc;
}
.activity-confirm {
  display: flex;
  gap: 13px;
  align-items: center;
  cursor: pointer;
}
.activity-confirm input {
  width: 19px;
  height: 19px;
  accent-color: #285e55;
  flex-shrink: 0;
}
.activity-confirm strong {
  font-size: 15px;
  display: block;
}
.activity-code {
  font-family: ui-monospace, SFMono-Regular, monospace;
  font-size: 12px;
  display: inline-block;
  color: #285e55;
  margin-top: 5px;
  background: #edf5f1;
  padding: 3px 7px;
  border-radius: 5px;
}
.activity-source,
.activity-suggestion {
  font-size: 12px;
  color: #586f66;
  margin: 10px 0 0 32px;
  overflow-wrap: anywhere;
}
.activity-suggestion {
  margin-top: 6px;
}
.his-values {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 20px;
  padding-top: 18px;
  border-top: 1px solid #e5ece8;
}
.lab-guidance {
  grid-column: 1/-1;
  margin: 0;
  font-size: 12px;
  color: #586f66;
  line-height: 1.6;
}
.empty-his {
  display: grid;
  justify-items: center;
  gap: 10px;
  text-align: center;
  color: #586f66;
  padding: 36px 16px;
  background: #f7faf9;
  border-radius: 14px;
}
.empty-his p {
  max-width: 430px;
  font-size: 14px;
  margin: 0;
}
.his-expand-enter-active,
.his-expand-leave-active {
  transition:
    opacity 180ms,
    transform 180ms;
}
.his-expand-enter-from,
.his-expand-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}
@media (max-width: 640px) {
  .his-activity {
    padding: 16px;
  }
  .his-activity-top {
    align-items: flex-start;
    flex-wrap: wrap;
  }
  .his-values {
    grid-template-columns: 1fr;
  }
  .his-activity-top > .badge {
    margin-left: 32px;
  }
  .activity-source,
  .activity-suggestion {
    margin-left: 0;
  }
}
@media (prefers-reduced-motion: reduce) {
  .his-expand-enter-active,
  .his-expand-leave-active {
    transition: none;
  }
}
</style>
