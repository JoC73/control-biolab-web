@extends('layouts.lab', ['title' => ($savedResult ?? null) ? 'Editar resultado - '.$category['name'] : 'Nuevo resultado - '.$category['name']])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Nueva plantilla</p>
                <h1>{{ $category['title'] }}</h1>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('lab.index') }}">Menu</a>
                @if ($savedResult ?? null)
                    <a class="button" href="{{ route('lab.results.show', $savedResult['id']) }}">Cancelar</a>
                    <button class="button primary" type="submit" form="result-form" formaction="{{ route('lab.results.update', $savedResult['id']) }}">Actualizar</button>
                @else
                    <button class="button primary" type="submit" form="result-form" formaction="{{ route('lab.results.save', $category['slug']) }}">Guardar</button>
                @endif
            </div>
        </header>

        <form id="result-form" class="workbench" method="POST" action="{{ route('lab.results.preview', $category['slug']) }}">
            @csrf
            @php
                $leftSectionSlugs = ['orina', 'heces'];
                $sectionAddSlugs = ['hematologia', 'orina', 'heces'];
            @endphp

            <section class="panel form-panel">
                <div class="field span-2">
                    <label for="patient_name">Nombre del paciente</label>
                    <input id="patient_name" name="patient_name" value="{{ old('patient_name', $savedResult['patient_name'] ?? '') }}" required>
                    @error('patient_name')<small>{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="age">Edad</label>
                    <input id="age" name="age" value="{{ old('age', $savedResult['age'] ?? '') }}" placeholder="Ej. 35 anos">
                </div>
                <div class="field">
                    <label for="date">Fecha</label>
                    <input id="date" name="date" type="date" value="{{ old('date', $savedResult['date'] ?? now()->toDateString()) }}" required>
                </div>
                <div class="field span-2">
                    <label for="referred_by">Refiere</label>
                    <select id="referred_by" name="referred_by">
                        @foreach ($referrers as $referrer)
                            <option value="{{ $referrer }}" @selected(old('referred_by', $savedResult['referred_by'] ?? 'DRA. ESTRADA') === $referrer)>{{ $referrer }}</option>
                        @endforeach
                    </select>
                </div>
            </section>

            <section class="panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Resultados</p>
                        <h2>{{ $category['title'] }}</h2>
                    </div>
                    <button class="button" type="button" data-add-row>Agregar campo</button>
                </div>

                <div class="results-table editable-results" data-results-table>
                    <div class="table-head">Analisis</div>
                    <div class="table-head">Resultado</div>
                    <div class="table-head">Unidades</div>
                    <div class="table-head">V.N.</div>
                    <div class="table-head action-head">Accion</div>

                    @php
                        $baseTests = ($savedResult ?? null) ? $savedResult['tests'] : $category['tests'];
                        $baseResults = ($savedResult ?? null) ? ($savedResult['results'] ?? []) : [];
                        $rows = max(count($baseTests), 8);
                    @endphp

                    @for ($index = 0; $index < $rows; $index++)
                        @php
                            $test = $baseTests[$index] ?? ['name' => '', 'unit' => '', 'reference' => ''];
                            $resultValue = old('results.'.$index, $baseResults[$index] ?? '');
                            $isSection = filled($test['name'] ?? null) && blank($test['unit'] ?? null) && blank($test['reference'] ?? null) && blank($resultValue);
                        @endphp
                        @if ($isSection)
                            <div class="result-cell result-section-label @if(in_array(($category['slug'] ?? ''), $leftSectionSlugs, true)) result-section-label-left @endif @if(in_array(($category['slug'] ?? ''), $sectionAddSlugs, true)) result-section-with-action @endif" data-section-row>
                                <input type="hidden" name="tests[{{ $index }}][name]" value="{{ $test['name'] }}">
                                <span>{{ $test['name'] }}</span>
                                @if(in_array(($category['slug'] ?? ''), $sectionAddSlugs, true))
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
                            <div class="result-cell"><input name="tests[{{ $index }}][name]" value="{{ old('tests.'.$index.'.name', $test['name']) }}" placeholder="Analisis"></div>
                            <div class="result-cell"><input name="results[{{ $index }}]" value="{{ $resultValue }}" placeholder="Resultado"></div>
                            <div class="result-cell"><input name="tests[{{ $index }}][unit]" value="{{ old('tests.'.$index.'.unit', $test['unit']) }}" placeholder="Unidad"></div>
                            <div class="result-cell"><input name="tests[{{ $index }}][reference]" value="{{ old('tests.'.$index.'.reference', $test['reference']) }}" placeholder="Valor normal"></div>
                            <div class="result-cell row-action"><button class="icon-button danger" type="button" data-remove-row title="Eliminar fila">Eliminar</button></div>
                        @endif
                    @endfor
                </div>

                <div class="actions">
                    <button class="button" type="button" data-add-row>Agregar campo</button>
                    <button class="button" type="submit">Vista previa</button>
                    @if ($savedResult ?? null)
                        <button class="button primary" type="submit" formaction="{{ route('lab.results.update', $savedResult['id']) }}">Actualizar</button>
                    @else
                        <button class="button primary" type="submit" formaction="{{ route('lab.results.save', $category['slug']) }}">Guardar</button>
                    @endif
                </div>
            </section>
        </form>

        <template id="result-row-template">
            <div class="result-cell"><input data-name-template="tests[__INDEX__][name]" placeholder="Analisis"></div>
            <div class="result-cell"><input data-name-template="results[__INDEX__]" placeholder="Resultado"></div>
            <div class="result-cell"><input data-name-template="tests[__INDEX__][unit]" placeholder="Unidad"></div>
            <div class="result-cell"><input data-name-template="tests[__INDEX__][reference]" placeholder="Valor normal"></div>
            <div class="result-cell row-action"><button class="icon-button danger" type="button" data-remove-row title="Eliminar fila">Eliminar</button></div>
        </template>
    </main>

    <script>
        (() => {
            const table = document.querySelector('[data-results-table]');
            const template = document.getElementById('result-row-template');
            const columns = 5;

            const rowCount = () => Math.max(0, (table.children.length - columns) / columns);

            const renameRows = () => {
                const cells = Array.from(table.querySelectorAll('.result-cell'));

                for (let row = 0; row < rowCount(); row++) {
                    const rowCells = cells.slice(row * columns, row * columns + columns);
                    rowCells.forEach((cell) => {
                        cell.querySelectorAll('input').forEach((input) => {
                            const current = input.name || input.dataset.nameTemplate || '';
                            input.name = current.replace(/\[\d+\]/, `[${row}]`).replace('__INDEX__', row);
                        });
                    });
                }
            };

            const buildRow = (index) => {
                const fragment = template.content.cloneNode(true);

                fragment.querySelectorAll('input').forEach((input) => {
                    input.name = input.dataset.nameTemplate.replace('__INDEX__', index);
                    input.removeAttribute('data-name-template');
                });

                return fragment;
            };

            const focusRow = (index) => {
                table.querySelector('input[name="tests[' + index + '][name]"]')?.focus();
            };

            const isEmptyDataRow = (cells, row) => {
                const rowCells = cells.slice(row * columns, row * columns + columns);

                if (rowCells[0]?.hasAttribute('data-section-row')) {
                    return false;
                }

                return rowCells.length === columns && rowCells.every((cell) => {
                    const input = cell.querySelector('input');

                    return !input || input.value.trim() === '';
                });
            };

            const addRow = () => {
                const index = rowCount();
                const fragment = buildRow(index);
                table.appendChild(fragment);
                focusRow(index);
            };

            const addRowAfterSection = (button) => {
                const cells = Array.from(table.querySelectorAll('.result-cell'));
                const sectionRow = Math.floor(cells.indexOf(button.closest('.result-cell')) / columns);
                let insertRow = sectionRow + 1;

                while (
                    insertRow < rowCount()
                    && !cells[insertRow * columns]?.hasAttribute('data-section-row')
                    && !isEmptyDataRow(cells, insertRow)
                ) {
                    insertRow++;
                }

                const fragment = buildRow(insertRow);
                const referenceCell = cells[insertRow * columns] ?? null;

                if (referenceCell) {
                    table.insertBefore(fragment, referenceCell);
                } else {
                    table.appendChild(fragment);
                }

                renameRows();
                focusRow(insertRow);
            };

            const removeRow = (button) => {
                if (rowCount() <= 1) {
                    return;
                }

                if (!confirm('Deseas eliminar esta fila de analisis?')) {
                    return;
                }

                const cells = Array.from(table.querySelectorAll('.result-cell'));
                const start = cells.indexOf(button.closest('.result-cell')) - 4;

                cells.slice(start, start + columns).forEach((cell) => cell.remove());
                renameRows();
            };

            document.querySelectorAll('[data-add-row]').forEach((button) => {
                button.addEventListener('click', addRow);
            });

            table.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-row]');

                if (button) {
                    removeRow(button);
                    return;
                }

                const addAfterSectionButton = event.target.closest('[data-add-after-section]');

                if (addAfterSectionButton) {
                    addRowAfterSection(addAfterSectionButton);
                }
            });
        })();
    </script>
@endsection
