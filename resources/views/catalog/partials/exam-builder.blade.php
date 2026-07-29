@php $embedded = $embedded ?? false; @endphp

<section class="{{ $embedded ? 'exam-builder-inline' : 'panel exam-builder-panel' }}">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Plantilla nueva</p>
            <h2>Crear examen personalizado</h2>
            <p>Define los campos que apareceran al emitir resultados.</p>
        </div>
        <button class="button" type="button" data-add-exam-row>Agregar campo</button>
    </div>

    <form id="exam-template-form" method="POST" action="{{ route('catalog.exam') }}">
        @csrf
        <div class="exam-builder-head">
            <div class="field">
                <label for="exam_name">Nombre del examen</label>
                <input id="exam_name" name="name" placeholder="Ej. Perfil tiroideo" required>
            </div>
            <div class="field">
                <label for="exam_title">Titulo en hoja</label>
                <input id="exam_title" name="title" placeholder="Ej. Perfil tiroideo">
            </div>
            <div class="field">
                <label for="exam_price">Precio base</label>
                <input id="exam_price" name="price" type="number" min="0" step="0.01" value="0">
            </div>
            <div class="filter-actions">
                <button class="button primary" type="submit">Guardar plantilla</button>
            </div>
        </div>

        <div class="result-grid builder-grid" data-exam-rows>
            <div class="result-head">Analisis</div>
            <div class="result-head">Resultado</div>
            <div class="result-head">Unidades</div>
            <div class="result-head">V.N.</div>
            <div class="result-head">Accion</div>

            @for ($index = 0; $index < 4; $index++)
                <div class="result-cell"><input name="tests[{{ $index }}][name]" placeholder="Analisis"></div>
                <div class="result-cell"><input disabled placeholder="Se llena al emitir"></div>
                <div class="result-cell"><input name="tests[{{ $index }}][unit]" placeholder="Unidad"></div>
                <div class="result-cell"><input name="tests[{{ $index }}][reference]" placeholder="Valor normal"></div>
                <div class="result-cell"><button class="button compact-button" type="button" data-remove-exam-row>Quitar</button></div>
            @endfor
        </div>
    </form>

    <template id="exam-row-template">
        <div class="result-cell"><input data-name-template="tests[__INDEX__][name]" placeholder="Analisis"></div>
        <div class="result-cell"><input disabled placeholder="Se llena al emitir"></div>
        <div class="result-cell"><input data-name-template="tests[__INDEX__][unit]" placeholder="Unidad"></div>
        <div class="result-cell"><input data-name-template="tests[__INDEX__][reference]" placeholder="Valor normal"></div>
        <div class="result-cell"><button class="button compact-button" type="button" data-remove-exam-row>Quitar</button></div>
    </template>
</section>

<script>
    (() => {
        const grid = document.querySelector('[data-exam-rows]');
        const addButton = document.querySelector('[data-add-exam-row]');
        const template = document.getElementById('exam-row-template');

        if (! grid || ! addButton || ! template) {
            return;
        }

        const nextIndex = () => grid.querySelectorAll('input[name^="tests["]').length / 3;

        const reindex = () => {
            Array.from(grid.children).slice(5).forEach((node, position) => {
                node.querySelectorAll('input').forEach((input) => {
                    const current = input.name || input.dataset.nameTemplate || '';
                    if (current.includes('tests[')) {
                        input.name = current.replace(/tests\[\d+|tests\[__INDEX__/g, 'tests[' + Math.floor(position / 5));
                    }
                });
            });
        };

        addButton.addEventListener('click', () => {
            const fragment = template.content.cloneNode(true);
            const index = nextIndex();
            fragment.querySelectorAll('[data-name-template]').forEach((input) => {
                input.name = input.dataset.nameTemplate.replace('__INDEX__', index);
                input.removeAttribute('data-name-template');
            });
            grid.appendChild(fragment);
        });

        grid.addEventListener('click', (event) => {
            if (! event.target.matches('[data-remove-exam-row]')) {
                return;
            }

            const cells = Array.from(grid.children);
            const cellIndex = cells.indexOf(event.target.closest('.result-cell'));
            const rowStart = cellIndex - ((cellIndex - 5) % 5);
            cells.slice(rowStart, rowStart + 5).forEach((cell) => cell.remove());
            reindex();
        });
    })();
</script>
