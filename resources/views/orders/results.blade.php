@extends('layouts.lab', ['title' => 'Resultados - '.$order['patient_name']])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Resultados</p>
                <h1>{{ $order['patient_name'] }}</h1>
                <p>{{ $order['category_title'] }}</p>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('orders.show', $order['id']) }}">Orden</a>
                <button class="button primary" type="submit" form="results-form">Guardar</button>
            </div>
        </header>

        <form id="results-form" class="workbench" method="POST" action="{{ route('orders.results.save', $order['id']) }}">
            @csrf
            <section class="panel">
                <div class="section-heading">
                    <div><p class="eyebrow">Captura</p><h2>Campos editables</h2></div>
                    <button class="button" type="button" data-add-row>Agregar campo</button>
                </div>
                <div class="results-table editable-results" data-results-table>
                    <div class="table-head">Analisis</div><div class="table-head">Resultado</div><div class="table-head">Unidades</div><div class="table-head">V.N.</div><div class="table-head action-head">Accion</div>
                    @php $rows = max(count($order['tests']), 8); @endphp
                    @for ($index = 0; $index < $rows; $index++)
                        @php $test = $order['tests'][$index] ?? ['name' => '', 'unit' => '', 'reference' => '']; @endphp
                        <div class="result-cell"><input name="tests[{{ $index }}][name]" value="{{ $test['name'] }}" placeholder="Analisis"></div>
                        <div class="result-cell"><input name="results[{{ $index }}]" value="{{ $order['results'][$index] ?? '' }}" placeholder="Resultado"></div>
                        <div class="result-cell"><input name="tests[{{ $index }}][unit]" value="{{ $test['unit'] }}" placeholder="Unidad"></div>
                        <div class="result-cell"><input name="tests[{{ $index }}][reference]" value="{{ $test['reference'] }}" placeholder="Valor normal"></div>
                        <div class="result-cell row-action"><button class="icon-button danger" type="button" data-remove-row>Eliminar</button></div>
                    @endfor
                </div>
                <div class="actions">
                    <select name="status">
                        <option value="pending_results" @selected($order['status'] === 'pending_results')>Guardar borrador</option>
                        <option value="ready" @selected($order['status'] === 'ready')>Resultado listo</option>
                    </select>
                    <button class="button" type="button" data-add-row>Agregar campo</button>
                    <button class="button primary" type="submit">Guardar resultados</button>
                </div>
            </section>
        </form>
        <template id="result-row-template">
            <div class="result-cell"><input data-name-template="tests[__INDEX__][name]" placeholder="Analisis"></div>
            <div class="result-cell"><input data-name-template="results[__INDEX__]" placeholder="Resultado"></div>
            <div class="result-cell"><input data-name-template="tests[__INDEX__][unit]" placeholder="Unidad"></div>
            <div class="result-cell"><input data-name-template="tests[__INDEX__][reference]" placeholder="Valor normal"></div>
            <div class="result-cell row-action"><button class="icon-button danger" type="button" data-remove-row>Eliminar</button></div>
        </template>
    </main>
    @include('orders.results-script')
@endsection
