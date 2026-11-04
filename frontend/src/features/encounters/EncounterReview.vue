<script setup lang="ts">
import { ref } from 'vue'
import {
  AlertTriangle,
  CheckCircle2,
  Download,
  FileText,
  FileSpreadsheet,
  ShieldCheck,
} from '@lucide/vue'
import { api } from '@/lib/api'
import { isDemonstrationData as isDemo } from '@/app/environment'
import { normalizeError } from '@/lib/api/errors'
import { useToast } from '@/composables/useToast'
import { ageAt, dateLabel, fullName } from '@/lib/format'
import type { DocumentKind, HisPreview } from '@/types'
import { localEncounterDate } from './validation'

defineProps<{ preview: HisPreview }>()
const downloading = ref<DocumentKind | null>(null)
const downloadError = ref('')
const { notify } = useToast()
async function download(id: string, kind: DocumentKind) {
  downloading.value = kind
  downloadError.value = ''
  try {
    const blob = await api.downloadDocument(id, kind)
    const href = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = href
    anchor.download = `kay-care-${isDemo ? 'DEMO-' : ''}${kind}-${id}.pdf`
    anchor.click()
    window.setTimeout(() => URL.revokeObjectURL(href), 1000)
    notify(
      isDemo
        ? 'Descargaste un PDF de demostración, sin validez clínica oficial.'
        : 'Documento descargado.',
    )
  } catch (error) {
    downloadError.value = normalizeError(error).message
  } finally {
    downloading.value = null
  }
}
</script>

<template>
  <div class="review-grid">
    <section class="review-summary panel">
      <div class="section-heading">
        <div>
          <p class="eyebrow">REGISTRO DE ATENCIÓN</p>
          <h2>Resumen clínico</h2>
        </div>
        <span class="badge">CRED</span>
      </div>
      <div class="review-patient">
        <strong>{{ fullName(preview.patient) }}</strong
        ><span
          >HC {{ preview.patient.chart_number }} ·
          {{
            ageAt(preview.patient.birth_date, localEncounterDate(preview.encounter.occurred_at))
          }}</span
        >
      </div>
      <dl class="clinical-values">
        <div>
          <dt>Fecha y hora</dt>
          <dd>{{ dateLabel(preview.encounter.occurred_at, true) }}</dd>
        </div>
        <div>
          <dt>Zona horaria</dt>
          <dd>Perú · UTC−5</dd>
        </div>
        <div>
          <dt>Peso</dt>
          <dd>
            {{
              preview.encounter.weight_kg === null
                ? 'No registrado'
                : `${preview.encounter.weight_kg} kg`
            }}
          </dd>
        </div>
        <div>
          <dt>Talla</dt>
          <dd>
            {{
              preview.encounter.height_cm === null
                ? 'No registrada'
                : `${preview.encounter.height_cm} cm`
            }}
          </dd>
        </div>
        <div>
          <dt>Hemoglobina</dt>
          <dd>
            {{
              preview.encounter.hb_g_dl === null
                ? 'No registrada'
                : `${preview.encounter.hb_g_dl} g/dL`
            }}
          </dd>
        </div>
        <div>
          <dt>Estado</dt>
          <dd>{{ preview.encounter.status === 'reviewed' ? 'Revisada' : 'Borrador' }}</dd>
        </div>
      </dl>
      <div class="clinical-note">
        <h3>Notas clínicas</h3>
        <p>{{ preview.encounter.clinical_note || 'Sin notas clínicas registradas.' }}</p>
      </div>
    </section>
    <section class="review-documents panel">
      <p class="eyebrow">DOCUMENTACIÓN</p>
      <h2>Todo, en su lugar.</h2>
      <p class="muted document-description">
        Descarga los documentos disponibles para esta atención.
      </p>
      <div v-if="isDemo" class="notice notice-warning">
        <strong>Documentos DEMO</strong>
        <p>
          Estos PDF son ejemplos ficticios. No constituyen una historia clínica ni un registro HIS
          oficial válido.
        </p>
      </div>
      <button
        class="document-download"
        :disabled="!!downloading"
        @click="download(preview.encounter.id, 'clinical-note')"
      >
        <span class="document-icon"><FileText :size="22" /></span
        ><span
          ><strong>Resumen clínico</strong
          ><small>PDF {{ isDemo ? '· Demostración' : '· Documento de la atención' }}</small></span
        ><Download :size="18" />
      </button>
      <button
        class="document-download"
        :disabled="!!downloading"
        @click="download(preview.encounter.id, 'his')"
      >
        <span class="document-icon"><FileSpreadsheet :size="22" /></span
        ><span
          ><strong>Registro HIS</strong
          ><small>PDF {{ isDemo ? '· Demostración' : '· Vista de registro' }}</small></span
        ><Download :size="18" />
      </button>
      <p v-if="downloading" role="status" class="muted download-status">Preparando descarga…</p>
      <p v-if="downloadError" role="alert" class="field-error">{{ downloadError }}</p>
      <div class="document-footnote">
        <ShieldCheck :size="16" /><span
          >La validación del contenido corresponde al personal responsable.</span
        >
      </div>
    </section>
    <section class="review-his panel">
      <div class="section-heading">
        <div>
          <p class="eyebrow">SOLO ACTIVIDADES CONFIRMADAS</p>
          <h2>Vista previa HIS</h2>
        </div>
        <span :class="['badge', preview.validated ? 'badge-success' : 'badge-warning']"
          ><CheckCircle2 v-if="preview.validated" :size="14" />{{
            preview.validated ? 'Consistencia estructural comprobada' : 'Pendiente de validación'
          }}</span
        >
      </div>
      <div
        v-if="preview.warnings.length"
        class="notice notice-warning preview-warnings"
        role="note"
      >
        <AlertTriangle :size="20" />
        <div>
          <strong>Antes de utilizar este registro</strong>
          <ul>
            <li v-for="warning in preview.warnings" :key="warning">{{ warning }}</li>
          </ul>
        </div>
      </div>
      <div
        v-if="preview.lines.length"
        class="table-wrap"
        tabindex="0"
        role="region"
        aria-label="Actividades HIS confirmadas, tabla desplazable"
      >
        <table class="data-table">
          <caption class="sr-only">
            Actividades HIS confirmadas para esta atención
          </caption>
          <thead>
            <tr>
              <th scope="col">Actividad</th>
              <th scope="col">Código</th>
              <th scope="col">Tipo</th>
              <th scope="col">LAB</th>
              <th scope="col">Verificación</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in preview.lines" :key="line.id">
              <td>{{ line.label }}</td>
              <td class="his-code">{{ line.code }}</td>
              <td>{{ line.diagnosis_type || '—' }}</td>
              <td>{{ line.lab || '—' }}</td>
              <td>
                <span
                  :class="[
                    'badge',
                    line.verification_status === 'verified' ? 'badge-success' : 'badge-warning',
                  ]"
                  >{{
                    line.verification_status === 'verified' ? 'Verificado' : 'Sin verificar'
                  }}</span
                >
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="no-lines">
        <FileSpreadsheet :size="28" />
        <p>No se confirmaron actividades HIS para esta atención.</p>
      </div>
      <p class="review-disclaimer">
        La comprobación estructural revisa la consistencia del registro. No certifica su validez
        clínica ni normativa. El catálogo actual es demostrativo y sus códigos no son oficiales.
      </p>
    </section>
  </div>
</template>

<style scoped>
.review-grid > section {
  min-width: 0;
}
.review-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.8fr);
  gap: 22px;
}
.review-summary,
.review-documents,
.review-his {
  padding: 26px;
}
.review-summary h2,
.review-documents h2,
.review-his h2 {
  font-size: 20px;
  margin: 5px 0;
}
.review-patient {
  display: grid;
  gap: 6px;
  padding: 20px 0;
  border-bottom: 1px solid #e5ece8;
}
.review-patient strong {
  font-size: 20px;
}
.review-patient span {
  color: #586f66;
  font-size: 13px;
}
.clinical-values {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 22px 16px;
  margin: 24px 0;
}
.clinical-values dt {
  font-size: 12px;
  color: #586f66;
  margin-bottom: 6px;
}
.clinical-values dd {
  font-size: 14px;
  font-weight: 600;
  margin: 0;
}
.clinical-note {
  border-top: 1px solid #e5ece8;
  padding-top: 20px;
}
.clinical-note h3 {
  font-size: 13px;
  color: #586f66;
  margin: 0 0 10px;
}
.clinical-note p {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  font-size: 14px;
  line-height: 1.7;
  margin: 0;
}
.review-his {
  grid-column: 1/-1;
}
.document-description {
  font-size: 14px;
  line-height: 1.6;
  margin: 12px 0 18px;
}
.review-documents .notice {
  font-size: 12px;
  margin-bottom: 16px;
}
.review-documents .notice p {
  margin: 5px 0 0;
  line-height: 1.6;
}
.document-download {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 18px 0;
  border-bottom: 1px solid #e5ece8;
  text-align: left;
  background: transparent;
  color: #19312d;
  cursor: pointer;
}
.document-download:hover {
  color: #285e55;
}
.document-download:disabled {
  opacity: 0.55;
  cursor: wait;
}
.document-download > span:nth-child(2) {
  flex: 1;
}
.document-download strong {
  display: block;
  font-size: 14px;
}
.document-download small {
  display: block;
  font-size: 11px;
  color: #586f66;
  margin-top: 5px;
}
.document-icon {
  width: 42px;
  height: 46px;
  border-radius: 10px;
  background: #eff6f2;
  display: grid;
  place-items: center;
  color: #285e55;
  flex-shrink: 0;
}
.document-footnote {
  display: flex;
  gap: 8px;
  font-size: 11px;
  color: #586f66;
  margin-top: 22px;
  line-height: 1.6;
}
.document-footnote svg {
  flex-shrink: 0;
  margin-top: 1px;
}
.download-status {
  font-size: 12px;
  margin-top: 12px;
}
.preview-warnings {
  display: flex;
  gap: 12px;
  margin: 20px 0;
}
.preview-warnings svg {
  flex-shrink: 0;
}
.preview-warnings ul {
  margin: 8px 0 0;
  padding-left: 17px;
  font-size: 13px;
  line-height: 1.7;
}
.his-code {
  font-family: ui-monospace, SFMono-Regular, monospace;
  font-size: 12px;
}
.review-disclaimer {
  font-size: 12px;
  color: #586f66;
  line-height: 1.6;
  margin: 20px 0 0;
}
.no-lines {
  display: grid;
  justify-items: center;
  gap: 10px;
  text-align: center;
  background: #f7faf9;
  border-radius: 12px;
  padding: 28px 16px;
  margin-top: 18px;
  color: #586f66;
  font-size: 14px;
}
.no-lines p {
  margin: 0;
}
@media (max-width: 900px) {
  .review-grid {
    grid-template-columns: 1fr;
  }
  .review-his {
    grid-column: auto;
  }
  .review-documents {
    order: 3;
  }
}
@media (max-width: 480px) {
  .review-summary,
  .review-documents,
  .review-his {
    padding: 18px;
  }
  .clinical-values {
    grid-template-columns: 1fr;
  }
  .section-heading {
    align-items: flex-start;
    flex-wrap: wrap;
  }
  .review-patient strong {
    font-size: 18px;
  }
}
</style>
