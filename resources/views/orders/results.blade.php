@extends('layouts.lab', ['title' => 'Resultados - '.$order['patient_name']])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Resultados</p>
                <h1>{{ $order['patient_name'] }}</h1>
                <p>{{ count($examItems) }} examen{{ count($examItems) === 1 ? '' : 'es' }} en esta orden</p>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('orders.show', $order['id']) }}">Orden</a>
                <button class="button primary" type="submit" form="results-form">Guardar</button>
            </div>
        </header>

        <form id="results-form" class="workbench" method="POST" action="{{ route('orders.results.save', $order['id']) }}">
            @csrf
            <input type="hidden" name="exam_index" value="{{ $selectedExamIndex }}">
            @php
                $leftSectionSlugs = ['orina', 'heces'];
                $sectionAddSlugs = ['hematologia', 'orina', 'heces'];
            @endphp
            <section class="panel">
                <div class="section-heading">
                    <div><p class="eyebrow">Orden</p><h2>Examenes solicitados</h2></div>
                </div>
                <div class="history-list compact-list">
                    @foreach ($examItems as $index => $item)
                        <article class="history-row">
                            <a href="{{ route('orders.results', ['id' => $order['id'], 'exam' => $index]) }}">
                                <strong>{{ $item['category_name'] }}</strong>
                                <span>Q {{ number_format((float) $item['price'], 2) }}</span>
                                <em>{{ ($item['status'] ?? 'pending') === 'ready' ? 'Listo' : 'Pendiente' }}</em>
                            </a>
                            <div class="row-tools">
                                <a class="button {{ $index === $selectedExamIndex ? 'primary' : '' }}" href="{{ route('orders.results', ['id' => $order['id'], 'exam' => $index]) }}">Editar</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
            <section class="panel">
                <div class="section-heading">
                    <div><p class="eyebrow">Captura</p><h2>{{ $selectedExam['category_title'] }}</h2></div>
                    <button class="button" type="button" data-add-row>Agregar campo</button>
                </div>
                <div class="results-table editable-results" data-results-table>
                    <div class="table-head">Analisis</div><div class="table-head">Resultado</div><div class="table-head">Unidades</div><div class="table-head">V.N.</div><div class="table-head action-head">Accion</div>
                    @php $rows = max(count($selectedExam['tests']), 8); @endphp
                    @for ($index = 0; $index < $rows; $index++)
                        @php $test = $selectedExam['tests'][$index] ?? ['name' => '', 'unit' => '', 'reference' => '']; @endphp
                        @php
                            $resultValue = $selectedExam['results'][$index] ?? '';
                            $isSection = filled($test['name'] ?? null) && blank($test['unit'] ?? null) && blank($test['reference'] ?? null) && blank($resultValue);
                        @endphp
                        @if ($isSection)
                            <div class="result-cell result-section-label @if(in_array(($selectedExam['category_slug'] ?? ''), $leftSectionSlugs, true)) result-section-label-left @endif @if(in_array(($selectedExam['category_slug'] ?? ''), $sectionAddSlugs, true)) result-section-with-action @endif" data-section-row>
                                <input type="hidden" name="tests[{{ $index }}][name]" value="{{ $test['name'] }}">
                                <span>{{ $test['name'] }}</span>
                                @if(($selectedExam['category_slug'] ?? '') === 'hematologia')
                                    <button class="section-add-button" type="button" data-add-before-section>Agregar antes</button>
                                @endif
                                @if(in_array(($selectedExam['category_slug'] ?? ''), $sectionAddSlugs, true))
                                    <button class="section-add-button" type="button" data-add-after-section>Agregar aqui</button>
                                @endif
                            </div>
                            <div class="result-cell result-section-placeholder">
                                <input type="hidden" name="results[{{ $index }}]" value="">
                            </div>
                            <div class="result-cell result-section-placeholder">
                                <input type="hidden" name="tests[{{ $index }}][unit]" value="">
                            </div>
                            <div class="result-cell result-section-placeholder">
                                <input type="hidden" name="tests[{{ $index }}][reference]" value="">
                            </div>
                            <div class="result-cell row-action result-section-placeholder">
                                <span class="locked-label">Etiqueta fija</span>
                            </div>
                        @else
                            <div class="result-cell"><input name="tests[{{ $index }}][name]" value="{{ $test['name'] }}" placeholder="Analisis"></div>
                            <div class="result-cell"><input name="results[{{ $index }}]" value="{{ $resultValue }}" placeholder="Resultado"></div>
                            <div class="result-cell"><input name="tests[{{ $index }}][unit]" value="{{ $test['unit'] }}" placeholder="Unidad"></div>
                            <div class="result-cell"><input name="tests[{{ $index }}][reference]" value="{{ $test['reference'] }}" placeholder="Valor normal"></div>
                            <div class="result-cell row-action"><button class="icon-button danger" type="button" data-remove-row>Eliminar</button></div>
                        @endif
                    @endfor
                </div>
                <div class="actions">
                    <select name="status">
                        <option value="pending_results" @selected(($selectedExam['status'] ?? 'pending') !== 'ready')>Guardar borrador</option>
                        <option value="ready" @selected(($selectedExam['status'] ?? 'pending') === 'ready')>Resultado listo</option>
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
