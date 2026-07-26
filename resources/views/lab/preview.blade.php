@extends('layouts.lab', ['title' => 'Vista previa - '.$category['name']])

@section('body')
    <main class="print-shell">
        @if (session('status'))
            <div class="status-message">{{ session('status') }}</div>
        @endif

        <div class="screen-actions">
            @if ($savedResult ?? null)
                <a class="button" href="{{ route('lab.history') }}">Historial</a>
                <a class="button" href="{{ route('lab.results.edit', $savedResult['id']) }}">Editar</a>
                <a class="button" href="{{ route('lab.results.saved-pdf', $savedResult['id']) }}">Descargar PDF</a>
                <form method="POST" action="{{ route('lab.results.destroy', $savedResult['id']) }}" onsubmit="return confirm('Deseas eliminar este resultado guardado?')">
                    @csrf
                    @method('DELETE')
                    <button class="button danger-button" type="submit">Eliminar</button>
                </form>
            @else
                <button class="button" type="button" onclick="history.back()">Editar</button>
                <form method="POST" action="{{ route('lab.results.save', $category['slug']) }}">
                    @csrf
                    @include('lab.partials.result-fields')
                    <button class="button primary" type="submit">Guardar</button>
                </form>
                <form method="POST" action="{{ route('lab.results.pdf', $category['slug']) }}">
                    @csrf
                    @include('lab.partials.result-fields')
                    <button class="button" type="submit">Descargar PDF</button>
                </form>
            @endif
            <button class="button primary" onclick="window.print()">Imprimir</button>
        </div>

        <section class="result-page">
            @include('lab.partials.report-header')

            <dl class="patient-grid">
                <dt>Nombre:</dt>
                <dd>{{ $result['patient_name'] }}</dd>
                <dt>Fecha:</dt>
                <dd>{{ \Illuminate\Support\Carbon::parse($result['date'])->format('d/m/Y') }}</dd>
                <dt>Edad:</dt>
                <dd>{{ $result['age'] ?? '' }}</dd>
                <dt>Refiere:</dt>
                <dd>{{ $result['referred_by'] ?? '' }}</dd>
            </dl>

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

            @include('lab.partials.report-footer')
        </section>
    </main>
@endsection
