<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import {
  ArrowUpRight,
  ArrowRight,
  ClipboardPlus,
  UserPlus,
  UsersRound,
  ClipboardCheck,
  FileClock,
  CalendarDays,
  Heart,
  Check,
  BookOpen,
  Plus,
} from '@lucide/vue'
import { api, isDemo } from '@/lib/api'
import type { Encounter, Patient } from '@/types'
import { fullName, initials, dateLabel } from '@/lib/format'
import { useAuthStore } from '@/features/auth/store'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatePanel from '@/components/ui/StatePanel.vue'
const auth = useAuthStore()
const summary = useQuery({
  queryKey: ['dashboard'],
  queryFn: async () => {
    const patients = await api.listPatients('', 1)
    const group = patients.data.slice(0, 8)
    const results = await Promise.allSettled(
      group.map((patient) => api.patientEncounters(patient.id)),
    )
    const activity: { patient: Patient; encounter: Encounter }[] = []
    results.forEach((result, index) => {
      if (result.status === 'fulfilled')
        result.value.forEach((encounter) => activity.push({ patient: group[index]!, encounter }))
    })
    return {
      patients,
      group,
      incomplete: results.some((r) => r.status === 'rejected'),
      activity: activity.sort(
        (a, b) => Date.parse(b.encounter.occurred_at) - Date.parse(a.encounter.occurred_at),
      ),
    }
  },
})
const drafts = computed(
  () =>
    summary.data.value?.activity.filter((item) => item.encounter.status === 'draft').length || 0,
)
const date = new Intl.DateTimeFormat('es-PE', {
  timeZone: 'America/Lima',
  weekday: 'long',
  day: 'numeric',
  month: 'long',
}).format(new Date())
const greeting = computed(() => {
  const hour = Number(
    new Intl.DateTimeFormat('en-US', {
      timeZone: 'America/Lima',
      hour: '2-digit',
      hourCycle: 'h23',
    }).format(new Date()),
  )
  return hour < 12 ? 'Buenos días' : hour < 19 ? 'Buenas tardes' : 'Buenas noches'
})
</script>
<template>
  <div>
    <PageHeader
      :eyebrow="date.toUpperCase()"
      :title="`${greeting}, ${isDemo ? 'enfermera' : auth.user?.name.split(' ')[0] || 'enfermera'}.`"
      description="Un nuevo momento para acompañar, registrar y cuidar."
      ><RouterLink to="/atenciones/nueva" class="btn btn-primary"
        ><Plus :size="17" />Nueva atención</RouterLink
      ></PageHeader
    >
    <section class="welcome-banner">
      <div class="welcome-copy">
        <span class="welcome-tag"><span class="status-dot" />TU ESPACIO DE CUIDADO</span>
        <h2>Lo importante está<br />en buenas manos.</h2>
        <p>
          Nos encargamos de simplificar el registro,<br class="desktop-break" />
          para que tú te enfoques en cada paciente.
        </p>
        <RouterLink to="/atenciones/nueva" class="banner-link"
          >Comenzar una atención <ArrowRight :size="16"
        /></RouterLink>
      </div>
      <div class="care-illustration" aria-hidden="true">
        <span class="orbit orbit-one" /><span class="orbit orbit-two" /><span
          class="orbit orbit-three"
        />
        <div class="illustration-note">
          <div class="tiny-note-header"><span /><i /><b>+</b></div>
          <div class="note-heart"><Heart :size="32" :stroke-width="1.5" /></div>
          <i class="note-line" /><i class="note-line short" />
          <div class="note-check"><Check :size="11" /><span>Un cuidado que continúa</span></div>
        </div>
        <div class="floating-plus">+</div>
        <div class="floating-heart"><Heart :size="19" :stroke-width="1.5" /></div>
        <span class="orbit-dot" />
      </div>
    </section>
    <StatePanel
      :loading="summary.isPending.value"
      :error="summary.error.value"
      @retry="summary.refetch()"
      ><template v-if="summary.data.value">
        <div v-if="summary.data.value.incomplete" class="notice notice-warning" role="alert">
          Algunas atenciones no pudieron consultarse. Los conteos son parciales.
          <button class="text-link" @click="summary.refetch()">Reintentar</button>
        </div>
        <div class="dashboard-stats">
          <RouterLink to="/pacientes" class="stat"
            ><div class="stat-top"><span>Pacientes registrados</span><UsersRound :size="19" /></div>
            <div class="stat-value">
              {{ summary.data.value.patients.meta.total.toString().padStart(2, '0')
              }}<ArrowUpRight :size="18" />
            </div>
            <small>{{
              isDemo ? 'Personas ficticias en tu espacio demo' : 'Total disponible en el registro'
            }}</small></RouterLink
          >
          <div class="stat">
            <div class="stat-top">
              <span>Atenciones consultadas</span><ClipboardCheck :size="19" />
            </div>
            <div class="stat-value">
              {{ summary.data.value.activity.length.toString().padStart(2, '0') }}
            </div>
            <small>De {{ summary.data.value.group.length }} pacientes de la primera página</small>
          </div>
          <div class="stat">
            <div class="stat-top"><span>Borradores por revisar</span><FileClock :size="19" /></div>
            <div class="stat-value">
              {{ drafts.toString().padStart(2, '0')
              }}<span class="stat-caption">En las atenciones consultadas</span>
            </div>
            <small>Continúa desde la ficha del paciente</small>
          </div>
        </div>
        <div class="dashboard-body">
          <section class="panel activity-panel">
            <div class="section-heading">
              <div>
                <p class="eyebrow">CONTINUIDAD, EN CADA VISITA</p>
                <h2>Atenciones recientes</h2>
              </div>
              <RouterLink to="/pacientes" class="subtle-link"
                >Ver pacientes <ArrowUpRight :size="14"
              /></RouterLink>
            </div>
            <p class="activity-context">Últimos registros de los pacientes consultados.</p>
            <StatePanel
              :empty="!summary.data.value.activity.length"
              title="Todo listo para la primera atención"
              description="Cuando registres una evaluación, podrás continuarla desde aquí."
              ><div class="activity-list">
                <RouterLink
                  v-for="(item, index) in summary.data.value.activity.slice(0, 4)"
                  :key="item.encounter.id"
                  :to="`/atenciones/${item.encounter.id}`"
                  class="activity-item"
                  ><span
                    :class="[
                      'avatar',
                      index % 3 === 1 ? 'lavender' : index % 3 === 2 ? 'peach' : '',
                    ]"
                    >{{ initials(fullName(item.patient)) }}</span
                  >
                  <div class="activity-person">
                    <strong>{{ fullName(item.patient) }}</strong
                    ><span>CRED · {{ dateLabel(item.encounter.occurred_at) }}</span>
                  </div>
                  <span
                    :class="[
                      'badge',
                      item.encounter.status === 'reviewed' ? 'badge-success' : 'badge-warning',
                    ]"
                    >{{ item.encounter.status === 'reviewed' ? 'Revisada' : 'Borrador' }}</span
                  ><ArrowUpRight :size="16"
                /></RouterLink></div></StatePanel
            ><RouterLink to="/atenciones/nueva" class="activity-bottom"
              ><ClipboardPlus :size="16" />Registrar una nueva atención<ArrowRight :size="15"
            /></RouterLink>
          </section>
          <aside class="quick-panel">
            <p class="eyebrow">A UN PASO DE DISTANCIA</p>
            <h2>¿Por dónde empezamos?</h2>
            <RouterLink :to="{ name: 'patients', query: { new: '1' } }" class="quick-action"
              ><span class="quick-icon"><UserPlus :size="19" /></span
              ><span
                ><strong>Registrar paciente</strong
                ><small>El inicio de una nueva historia</small></span
              ><ArrowUpRight :size="16" /></RouterLink
            ><RouterLink to="/documentos" class="quick-action"
              ><span class="quick-icon"><BookOpen :size="19" /></span
              ><span
                ><strong>Revisar documentación</strong
                ><small>HIS y resumen de atención</small></span
              ><ArrowUpRight :size="16"
            /></RouterLink>
            <div class="care-reminder">
              <CalendarDays :size="20" />
              <div>
                <strong>Cada actividad cuenta.</strong>
                <p>En el asistente HIS, confirma solo las actividades que realmente realizaste.</p>
              </div>
            </div>
          </aside>
        </div>
      </template></StatePanel
    >
    <div class="dashboard-bottom">
      <span class="small-heart"><Heart :size="14" /></span>
      <p>Detrás de cada registro, hay una persona. <strong>Gracias por cuidar.</strong></p>
      <span v-if="isDemo" class="badge badge-demo">DATOS DE DEMOSTRACIÓN</span>
    </div>
  </div>
</template>
<style scoped>
.welcome-banner {
  position: relative;
  overflow: hidden;
  min-height: 243px;
  background: #e7f0e8;
  border: 1px solid #dce8dc;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 26px;
}
.welcome-copy {
  position: relative;
  z-index: 2;
  padding: 30px 36px;
}
.welcome-tag {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 11px;
  letter-spacing: 1.6px;
  color: #526b5f;
  font-weight: 650;
}
.welcome-copy h2 {
  font-size: 30px;
  line-height: 1.2;
  letter-spacing: -1px;
  font-weight: 550;
  margin: 16px 0 12px;
  color: #31563e;
}
.welcome-copy p {
  font-size: 11px;
  line-height: 1.85;
  color: #526b5f;
}
.banner-link {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  font-size: 11px;
  color: #375e44;
  font-weight: 650;
  margin-top: 18px;
}
.care-illustration {
  position: absolute;
  width: 370px;
  height: 280px;
  right: 20px;
  top: -12px;
}
.orbit {
  position: absolute;
  border: 1px solid #bfd4bd80;
  display: block;
  border-radius: 50%;
  inset: 0 40px;
}
.orbit-two {
  inset: 25px 65px;
}
.orbit-three {
  inset: 50px 90px;
}
.illustration-note {
  position: absolute;
  width: 146px;
  height: 180px;
  top: 62px;
  left: 103px;
  transform: rotate(-9deg);
  border-radius: 15px;
  background: #ffffffdb;
  border: 1px solid #fff;
  box-shadow: 6px 12px 20px #718b7120;
  padding: 15px;
}
.tiny-note-header {
  display: flex;
  align-items: center;
  gap: 5px;
}
.tiny-note-header > span {
  width: 17px;
  height: 17px;
  border-radius: 5px;
  background: #e2ece0;
}
.tiny-note-header > i {
  height: 3px;
  width: 35px;
  background: #dce9d8;
}
.tiny-note-header > b {
  font-size: 13px;
  color: #526b5f;
  font-weight: 400;
  margin-left: auto;
}
.note-heart {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 11px auto;
  width: 58px;
  height: 58px;
  border-radius: 50%;
  color: #526b5f;
  background: #edf4e8;
}
.note-line {
  display: block;
  margin: 7px auto;
  width: 75px;
  height: 4px;
  background: #dfe9d9;
  border-radius: 5px;
}
.note-line.short {
  width: 53px;
}
.note-check {
  display: flex;
  gap: 5px;
  align-items: center;
  justify-content: center;
  margin-top: 16px;
  font-size: 6px;
  color: #526b5f;
}
.note-check svg {
  background: #e4efdd;
  border-radius: 50%;
}
.floating-plus {
  position: absolute;
  top: 50px;
  right: 73px;
  background: #c3d9c1;
  color: white;
  border: 1px solid #d9e8d4;
  width: 44px;
  height: 44px;
  border-radius: 13px;
  display: grid;
  place-items: center;
  font-size: 30px;
  font-weight: 300;
  transform: rotate(12deg);
}
.floating-heart {
  position: absolute;
  bottom: 31px;
  left: 63px;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #f8fbf4;
  display: grid;
  place-items: center;
  box-shadow: 0 5px 15px #5c7b5110;
  color: #526b5f;
  transform: rotate(12deg);
}
.orbit-dot {
  position: absolute;
  top: 95px;
  left: 63px;
  width: 9px;
  height: 9px;
  background: #a4bf97;
  border: 2px solid #f3f7ec;
  border-radius: 50%;
}
.dashboard-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 17px;
  margin-bottom: 30px;
}
.stat {
  padding: 21px 23px;
  background: #fff;
  border: 1px solid #e5ece8;
  border-radius: 14px;
}
.stat-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  color: #526b5f;
  font-size: 11px;
}
.stat-top svg {
  color: #526b5f;
}
.stat-value {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 33px;
  font-weight: 550;
  letter-spacing: -1px;
  margin: 10px 0 8px;
  color: #34553c;
}
.stat-value > svg {
  color: #526b5f;
}
.stat small {
  font-size: 11px;
  color: #526b5f;
}
.stat-caption {
  max-width: 95px;
  font-size: 11px;
  letter-spacing: 0;
  line-height: 1.7;
  font-weight: 400;
  color: #526b5f;
  text-align: right;
}
.dashboard-body {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(240px, 1fr);
  gap: 25px;
}
.activity-panel {
  padding: 25px 24px 0;
  overflow: hidden;
}
.activity-panel .section-heading {
  margin-bottom: 7px;
}
.activity-panel .section-heading h2 {
  font-size: 19px;
}
.subtle-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: #526b5f;
  white-space: nowrap;
}
.activity-context {
  font-size: 11px;
  color: #526b5f;
  margin-bottom: 18px;
}
.activity-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 0;
  border-top: 1px solid #edf2ed;
}
.activity-item:hover strong {
  color: #526b5f;
}
.activity-person {
  flex: 1;
  min-width: 0;
}
.activity-person strong {
  font-size: 11px;
  font-weight: 600;
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.activity-person > span {
  display: block;
  margin-top: 6px;
  font-size: 11px;
  color: #526b5f;
}
.activity-item > .badge {
  font-size: 11px;
  letter-spacing: 0.1px;
}
.activity-item > svg {
  color: #526b5f;
}
.activity-bottom {
  display: flex;
  gap: 7px;
  align-items: center;
  justify-content: center;
  border-top: 1px solid #e7eee6;
  background: #fafcf8;
  margin: 5px -24px 0;
  padding: 17px;
  font-size: 11px;
  color: #526b5f;
}
.activity-bottom svg:last-child {
  margin-left: auto;
}
.activity-bottom svg:first-child {
  margin-left: auto;
}
.quick-panel {
  padding: 5px 0;
}
.quick-panel h2 {
  font-size: 19px;
  margin-bottom: 19px;
}
.quick-action {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #fff;
  border: 1px solid #e5ece8;
  border-radius: 13px;
  padding: 19px 17px;
  margin-bottom: 12px;
}
.quick-action:hover {
  background: #f4f8f1;
  border-color: #cfdcc9;
}
.quick-icon {
  width: 38px;
  height: 38px;
  border-radius: 11px;
  background: #f0f4ea;
  display: grid;
  place-items: center;
  color: #526b5f;
}
.quick-action > span:nth-child(2) {
  flex: 1;
}
.quick-action strong {
  font-size: 11px;
  font-weight: 550;
  display: block;
}
.quick-action small {
  font-size: 11px;
  color: #526b5f;
  display: block;
  margin-top: 5px;
}
.quick-action > svg {
  color: #526b5f;
}
.care-reminder {
  display: flex;
  align-items: flex-start;
  gap: 11px;
  padding: 19px 4px;
  color: #526b5f;
}
.care-reminder > svg {
  margin-top: 3px;
}
.care-reminder strong {
  font-size: 11px;
  color: #526b5f;
  font-weight: 500;
}
.care-reminder p {
  font-size: 11px;
  line-height: 1.8;
  margin-top: 5px;
  color: #526b5f;
  max-width: 240px;
}
.dashboard-bottom {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: center;
  padding: 28px 0 0;
  color: #526b5f;
  font-size: 11px;
}
.dashboard-bottom p strong {
  font-weight: 500;
  color: #526b5f;
}
.dashboard-bottom > .badge {
  margin-left: auto;
  font-size: 11px;
}
.small-heart {
  display: flex;
  color: #526b5f;
}
@media (min-width: 1440px) {
  .welcome-banner {
    min-height: 260px;
  }
  .care-illustration {
    right: 80px;
  }
  .welcome-copy {
    padding: 35px 42px;
  }
  .welcome-copy h2 {
    font-size: 34px;
  }
  .activity-item {
    padding: 19px 0;
  }
  .stat {
    padding: 25px;
  }
}
@media (max-width: 1100px) {
  .welcome-copy {
    padding: 28px;
  }
  .care-illustration {
    right: -30px;
  }
  .dashboard-body {
    grid-template-columns: 1fr;
  }
  .quick-panel {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }
  .quick-panel > .eyebrow,
  .quick-panel > h2,
  .quick-panel > .care-reminder {
    grid-column: 1/-1;
  }
  .quick-panel > h2 {
    margin-top: -12px;
    margin-bottom: 3px;
  }
  .quick-action {
    margin: 0;
  }
  .care-reminder {
    padding: 5px;
  }
  .care-reminder p {
    max-width: 100%;
  }
  .stat {
    padding: 18px;
  }
  .stat-top {
    font-size: 11px;
  }
}
@media (max-width: 600px) {
  .welcome-copy {
    padding: 24px;
  }
  .welcome-copy h2 {
    font-size: 26px;
  }
  .care-illustration {
    display: none;
  }
  .welcome-copy p {
    max-width: 220px;
  }
  .dashboard-stats {
    gap: 10px;
    grid-template-columns: 1fr 1fr;
  }
  .stat:last-child {
    grid-column: 1/-1;
  }
  .stat:last-child .stat-value {
    margin-bottom: 2px;
  }
  .stat-value {
    font-size: 29px;
  }
  .stat-caption {
    max-width: none;
  }
  .stat-top svg {
    width: 17px;
  }
  .dashboard-bottom {
    flex-wrap: wrap;
    text-align: center;
  }
  .dashboard-bottom > .badge {
    margin: 0;
  }
  .activity-panel {
    padding: 20px 18px 0;
  }
  .activity-bottom {
    margin-inline: -18px;
  }
  .activity-item {
    gap: 9px;
  }
  .activity-item > .badge {
    font-size: 11px;
    padding: 4px 6px;
  }
  .activity-item > .avatar {
    width: 34px;
    height: 34px;
    font-size: 11px;
    border-radius: 10px;
  }
  .activity-item > svg {
    display: none;
  }
  .quick-panel {
    grid-template-columns: 1fr;
  }
  .activity-person strong {
    font-size: 11px;
  }
  .activity-panel .section-heading h2 {
    font-size: 18px;
  }
  .activity-panel .eyebrow {
    font-size: 11px;
  }
  .subtle-link {
    font-size: 11px;
  }
}
</style>
