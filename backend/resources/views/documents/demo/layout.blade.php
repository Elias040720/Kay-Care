<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $watermark }} — Kay Care DEMO — @yield('title') — {{ $recordId }}</title>
    <style>
        @page { size: A4 portrait; margin: 22mm 14mm 20mm 14mm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9.5pt; color: #1f2933; }
        .watermark {
            position: fixed; top: 38%; left: -12%; width: 124%;
            text-align: center; transform: rotate(-32deg);
            font-size: 30pt; font-weight: bold; color: #c62828; opacity: 0.16;
            z-index: -1;
        }
        header.page-header {
            position: fixed; top: -16mm; left: 0; right: 0; height: 12mm;
            border-bottom: 1px solid #c62828; font-size: 8pt; color: #c62828;
        }
        footer.page-footer {
            position: fixed; bottom: -14mm; left: 0; right: 0; height: 10mm;
            border-top: 1px solid #9aa5b1; font-size: 7.5pt; color: #52606d;
        }
        h1 { font-size: 14pt; margin: 0 0 2mm 0; }
        h2 { font-size: 11pt; margin: 5mm 0 2mm 0; border-bottom: 1px solid #cbd2d9; padding-bottom: 1mm; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
        th, td { border: 1px solid #9aa5b1; padding: 1.6mm 2mm; text-align: left; vertical-align: top; }
        th { background: #e4e7eb; font-size: 8.5pt; }
        table.kv th { width: 30%; }
        .banner { border: 2px solid #c62828; color: #c62828; padding: 2mm; font-weight: bold; text-align: center; margin-bottom: 3mm; }
        .warn { background: #fff8e1; border: 1px solid #f9a825; padding: 2mm; margin-bottom: 3mm; }
        .warn li { margin-bottom: 1mm; }
        .muted { color: #52606d; font-size: 8pt; }
        .badge { font-size: 7.5pt; padding: 0.4mm 1.2mm; border: 1px solid #c62828; color: #c62828; }
        .note { white-space: pre-wrap; border: 1px solid #9aa5b1; padding: 2.5mm; min-height: 30mm; }
    </style>
</head>
<body>
    <div class="watermark">{{ $watermark }}</div>

    <header class="page-header">
        <table style="border:none; margin:0;">
            <tr>
                <td style="border:none; padding:0;">KAY CARE — {{ $watermark }}</td>
                <td style="border:none; padding:0; text-align:right;">Registro {{ $recordId }}</td>
            </tr>
        </table>
    </header>

    <footer class="page-footer">
        Documento demostrativo generado automáticamente a partir de la base de datos de Kay Care el {{ $generatedAt }}
        por {{ $generatedBy }}. No es un formato oficial aprobado, no está firmado y no reemplaza la historia clínica
        ni el registro HIS oficial. Códigos DEMO no verificados.
    </footer>

    <div class="banner">{{ $watermark }}</div>

    @yield('content')
</body>
</html>
