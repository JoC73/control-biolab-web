<script>
    (() => {
        const table = document.querySelector('[data-results-table]');
        const template = document.getElementById('result-row-template');
        const columns = 5;
        const rowCount = () => Math.max(0, (table.children.length - columns) / columns);
        const renameRows = () => {
            const cells = Array.from(table.querySelectorAll('.result-cell'));
            for (let row = 0; row < rowCount(); row++) {
                cells.slice(row * columns, row * columns + columns).forEach((cell) => {
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

            while (insertRow < rowCount() && !cells[insertRow * columns]?.hasAttribute('data-section-row')) {
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
        document.querySelectorAll('[data-add-row]').forEach((button) => button.addEventListener('click', addRow));
        table.addEventListener('click', (event) => {
            const addAfterSectionButton = event.target.closest('[data-add-after-section]');
            if (addAfterSectionButton) {
                addRowAfterSection(addAfterSectionButton);
                return;
            }

            const button = event.target.closest('[data-remove-row]');
            if (!button || rowCount() <= 1 || !confirm('Deseas eliminar esta fila?')) return;
            const cells = Array.from(table.querySelectorAll('.result-cell'));
            const start = cells.indexOf(button.closest('.result-cell')) - 4;
            cells.slice(start, start + columns).forEach((cell) => cell.remove());
            renameRows();
        });
    })();
</script>
