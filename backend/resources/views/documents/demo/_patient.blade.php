<h2>Paciente</h2>
<table class="kv">
    <tr><th>N.º de historia clínica</th><td>{{ $patient->chart_number }}</td></tr>
    <tr><th>Apellidos y nombres</th><td>{{ $patient->last_name }}, {{ $patient->first_name }}</td></tr>
    <tr><th>Documento</th><td>{{ $patient->document_type ? $patient->document_type.' '.$patient->document_number : 'No registrado' }}</td></tr>
    <tr><th>Fecha de nacimiento</th><td>{{ $patient->birth_date->format('d/m/Y') }}</td></tr>
    <tr><th>Sexo registrado</th><td>{{ $patient->sex->value }}</td></tr>
    <tr><th>Localidad</th><td>{{ $patient->locality ?? '—' }}</td></tr>
</table>

<h2>Atención</h2>
<table class="kv">
    <tr><th>Tipo</th><td>{{ $encounter->type }}</td></tr>
    <tr><th>Fecha y hora</th><td>{{ $occurredLocal }} — {{ $occurredAt }}</td></tr>
    <tr><th>Edad en la atención</th><td>{{ $age->label() }} ({{ $age->days }} días; {{ $age->months }} meses cumplidos)</td></tr>
    <tr><th>Estado</th><td>{{ $encounter->status->value === 'reviewed' ? 'Revisada' : 'Borrador (no revisada)' }}</td></tr>
    <tr><th>Profesional responsable</th><td>{{ $encounter->responsible?->name ?? '—' }}</td></tr>
</table>
