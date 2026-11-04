@extends('documents.demo.layout')

@section('title', 'Nota clínica estructurada')

@section('content')
    <h1>Nota clínica estructurada — Control CRED <span class="badge">DEMO</span></h1>
    <p class="muted">Documento para revisión. No firmado. No interpreta automáticamente los datos.</p>

    @include('documents.demo._patient')

    <h2>Antropometría y laboratorio (valores registrados)</h2>
    <table class="kv">
        <tr><th>Peso</th><td>{{ $encounter->weight_kg !== null ? \App\Support\Num::format($encounter->weight_kg).' kg' : 'No registrado' }}</td></tr>
        <tr><th>Talla</th><td>{{ $encounter->height_cm !== null ? \App\Support\Num::format($encounter->height_cm).' cm' : 'No registrada' }}</td></tr>
        <tr><th>Hemoglobina</th><td>{{ $encounter->hb_g_dl !== null ? \App\Support\Num::format($encounter->hb_g_dl).' g/dL' : 'No registrada' }}</td></tr>
    </table>

    <h2>Nota clínica</h2>
    <div class="note">{{ $encounter->clinical_note ?? 'Sin nota clínica registrada.' }}</div>

    <h2>Actividades confirmadas</h2>
    @if ($lines->isEmpty())
        <p>No hay actividades confirmadas.</p>
    @else
        <table>
            <tr><th style="width:18%">Código</th><th>Actividad</th><th style="width:10%">Tipo</th><th style="width:10%">LAB</th></tr>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line->item->code }}</td>
                    <td>{{ $line->item->label }}</td>
                    <td>{{ $line->diagnosis_type ?? '—' }}</td>
                    <td>{{ $line->lab ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="warn">
        <ul>
            <li>Los códigos mostrados pertenecen a un catálogo DEMO no verificado y no son códigos oficiales.</li>
            <li>Este documento no contiene firma ni sello y no tiene validez legal ni clínica.</li>
        </ul>
    </div>

    <table style="margin-top:12mm;">
        <tr>
            <td style="height:18mm; width:50%;">Revisión del profesional (no firmado digitalmente)</td>
            <td>Observaciones de revisión</td>
        </tr>
    </table>
@endsection
