@extends('documents.demo.layout')

@section('title', 'Vista estructurada de registro HIS')

@section('content')
    <h1>Vista estructurada de registro HIS <span class="badge">DEMO</span></h1>
    <p class="muted">Vista de revisión. No es el formato HIS oficial del MINSA.</p>

    @include('documents.demo._patient')

    <h2>Mediciones registradas</h2>
    <table>
        <tr><th>Peso (kg)</th><th>Talla (cm)</th><th>Hemoglobina (g/dL)</th></tr>
        <tr>
            <td>{{ $encounter->weight_kg !== null ? \App\Support\Num::format($encounter->weight_kg) : '—' }}</td>
            <td>{{ $encounter->height_cm !== null ? \App\Support\Num::format($encounter->height_cm) : '—' }}</td>
            <td>{{ $encounter->hb_g_dl !== null ? \App\Support\Num::format($encounter->hb_g_dl) : '—' }}</td>
        </tr>
    </table>
    <p class="muted">Las mediciones se muestran tal como fueron registradas; el sistema no genera diagnósticos a partir de ellas.</p>

    <h2>Actividades confirmadas por el profesional</h2>
    @if ($lines->isEmpty())
        <p>No hay actividades confirmadas.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width:6%">#</th>
                    <th style="width:21%">Código</th>
                    <th>Actividad</th>
                    <th style="width:10%">Tipo (P/D/R)</th>
                    <th style="width:10%">LAB</th>
                    <th style="width:14%">Verificación</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $i => $line)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $line->item->code }}</td>
                        <td>{{ $line->item->label }}</td>
                        <td>{{ $line->diagnosis_type ?? '—' }}</td>
                        <td>{{ $line->lab ?? '—' }}</td>
                        <td>{{ $line->item->effectiveVerification()->value === 'verified' ? 'Verificado' : 'NO verificado' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Validación estructural</h2>
    <p>
        Resultado: <strong>{{ $validation->isValid() ? 'Consistente con el catálogo (no es validación oficial)' : 'Con observaciones bloqueantes' }}</strong>
    </p>
    @if (count($validation->messages()) > 0)
        <div class="warn">
            <ul>
                @foreach ($validation->messages() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
