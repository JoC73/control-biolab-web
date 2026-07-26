<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 12px;
            margin: 20px 28px 132px;
        }

        .report-header {
            display: table;
            width: 100%;
            border-top: 4px solid #4b5563;
            border-bottom: 3px double #0f4c81;
            padding: 8px 0 6px;
        }

        .report-brand,
        .report-business,
        .report-side-space {
            display: table-cell;
            vertical-align: middle;
        }

        .report-brand {
            width: 126px;
            text-align: center;
            font-weight: bold;
            border-right: 1px dashed #b7c0ca;
        }

        .report-logo {
            width: 64px;
            height: 48px;
            margin: 0 auto 3px;
        }

        .report-brand strong,
        .report-brand span,
        .report-brand small {
            display: block;
        }

        .report-brand span {
            color: #0f4c81;
            font-size: 19px;
            letter-spacing: 5px;
        }

        .report-business {
            text-align: center;
            color: #0f416d;
            font-weight: bold;
        }

        .report-side-space {
            width: 126px;
            border-left: 1px dashed #b7c0ca;
        }

        h1 {
            margin: 0 0 4px;
            color: #0f416d;
            font-size: 17px;
            text-transform: uppercase;
        }

        h3 {
            margin: 4px 0 0;
            color: #0f416d;
            font-size: 14px;
        }

        p {
            margin: 2px 0;
        }

        .patient-grid {
            width: 100%;
            margin: 20px 0 18px;
            border-collapse: collapse;
        }

        .patient-grid th {
            width: 75px;
            text-align: left;
            font-weight: bold;
        }

        .patient-grid td {
            border-bottom: 1px solid #9ca3af;
            padding: 5px 8px;
        }

        h2 {
            margin: 18px 0;
            text-align: center;
            text-transform: uppercase;
            font-size: 16px;
        }

        .print-table {
            width: 100%;
            border-collapse: collapse;
        }

        .print-table th,
        .print-table td {
            padding: 8px 7px;
            border-bottom: 1px solid #d1d5db;
            text-align: left;
        }

        .print-table th {
            text-transform: uppercase;
            font-size: 11px;
        }

        .report-footer {
            position: fixed;
            right: 28px;
            bottom: 14px;
            left: 28px;
            border-top: 2px solid #111827;
            padding-top: 16px;
            text-align: center;
            color: #0f416d;
            font-weight: bold;
            line-height: 1.12;
        }

        .footer-seal {
            text-align: center;
            margin-bottom: 7px;
        }

        .mini-stamp,
        .signature-img,
        .signature-text {
            display: inline-block;
            vertical-align: middle;
        }

        .mini-stamp {
            width: 118px;
            min-height: 54px;
            border: 1px solid #7b9ec0;
            color: #0f4c81;
            font-size: 7px;
            line-height: 1.05;
            text-align: center;
        }

        .mini-logo {
            width: 30px;
            height: 22px;
            margin-top: 3px;
        }

        .mini-stamp strong {
            display: block;
            font-size: 12px;
            letter-spacing: 3px;
        }

        .signature-img {
            width: 116px;
            height: auto;
            margin: 0 0 0 28px;
        }
    </style>
</head>
<body>
    <header class="report-header">
        <div class="report-brand">
            @if (!empty($logoDataUri))
                <img class="report-logo" src="{{ $logoDataUri }}" alt="BIOLAB">
            @endif
            LABORATORIO
            <span>BIOLAB</span>
            TEL: {{ $business['phone'] }}
        </div>
        <div class="report-business">
            <h1>{{ $business['name'] }}</h1>
            <p>{{ $business['address'] }}</p>
            <p>TELEFONOS: {{ $business['phone'] }}</p>
            <h3>{{ $business['director'] }}</h3>
            <p>{{ $business['credential'] }}</p>
        </div>
        <div class="report-side-space"></div>
    </header>

    <table class="patient-grid">
        <tr>
            <th>Nombre:</th>
            <td>{{ $result['patient_name'] }}</td>
            <th>Fecha:</th>
            <td>{{ \Illuminate\Support\Carbon::parse($result['date'])->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <th>Edad:</th>
            <td>{{ $result['age'] ?? '' }}</td>
            <th>Refiere:</th>
            <td>{{ $result['referred_by'] ?? '' }}</td>
        </tr>
    </table>

    <h2>{{ $category['title'] }}</h2>

    <table class="print-table">
        <thead>
            <tr>
                <th>Analisis</th>
                <th>Resultado</th>
                <th>Unidades</th>
                <th>V.N.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tests as $index => $test)
                <tr>
                    <td>{{ $test['name'] }}</td>
                    <td>{{ $result['results'][$index] ?? '' }}</td>
                    <td>{{ $test['unit'] }}</td>
                    <td>{{ $test['reference'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Sin resultados registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <footer class="report-footer">
        <div class="footer-seal">
            <div class="mini-stamp">
                @if (!empty($logoDataUri))
                    <img class="mini-logo" src="{{ $logoDataUri }}" alt="BIOLAB">
                @endif
                <span>LABORATORIO<br>CLINICO<br>BIOLOGICO</span>
                <strong>BIOLAB</strong>
                <small>TEL: {{ $business['phone'] }}</small>
            </div>
            @if (!empty($signatureDataUri))
                <img class="signature-img" src="{{ $signatureDataUri }}" alt="Firma responsable">
            @endif
        </div>
        <p>Horarios: Lunes a Viernes 7:00 a 19:00 horas Sabados 7:00 a 17:00 horas</p>
        <strong>DOMINGOS SOLO EMERGENCIAS</strong>
    </footer>
</body>
</html>
