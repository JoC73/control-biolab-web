@extends('layouts.lab', ['title' => 'Registrar cobro'])

@section('body')
    @php
        $selectedExamSlugs = collect(old('exam_slugs', [old('category_slug', $categories[0]['slug'] ?? '')]))->filter()->values()->all();
        $selectedCategory = $selectedExamSlugs[0] ?? ($categories[0]['slug'] ?? '');
        $initialPrice = collect($selectedExamSlugs)->sum(fn ($slug) => (float) ($prices[$slug] ?? 0));
        $initialDiscountValue = old('discount');
        $initialPaidValue = old('paid_amount');
        $initialDiscount = (float) ($initialDiscountValue ?? 0);
        $initialPaid = (float) ($initialPaidValue ?? 0);
        $initialTotal = max(0, round($initialPrice - $initialDiscount, 2));
        $initialBalance = max(0, round($initialTotal - $initialPaid, 2));
        $initialReferrer = old('referrer');
    @endphp

    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Recepcion / Caja</p>
                <h1>Registrar cobro de examen</h1>
                <p>Registra al paciente, el examen solicitado y el pago inicial o total.</p>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('orders.index') }}">Ordenes</a>
                <button class="button primary" type="submit" form="order-form">Guardar cobro</button>
            </div>
        </header>

        <form id="order-form" class="workbench" method="POST" action="{{ route('orders.store') }}">
            @csrf
            @if ($errors->any())
                <div class="status-message wide error-message guidance-message">
                    <strong>Revisa estos datos antes de guardar</strong>
                    <span>No se perdio tu informacion. Corrige lo indicado y vuelve a guardar el cobro.</span>
                    <ul class="form-error-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="help-strip" aria-label="Guia rapida para registrar cobros">
                <article>
                    <strong>1. Paciente</strong>
                    <span>Ingresa nombre, fecha y referencia medica.</span>
                </article>
                <article>
                    <strong>2. Examenes</strong>
                    <span>Selecciona uno o varios examenes solicitados.</span>
                </article>
                <article>
                    <strong>3. Pago</strong>
                    <span>El sistema calcula total y saldo pendiente.</span>
                </article>
            </section>

            <section class="panel form-panel">
                <div class="field span-2">
                    <label for="patient_name">Paciente</label>
                    <input id="patient_name" name="patient_name" value="{{ old('patient_name') }}" required autofocus>
                    <p class="field-hint">Escribe el nombre completo como debe salir en el resultado.</p>
                </div>
                <div class="field">
                    <label for="age">Edad</label>
                    <input id="age" name="age" value="{{ old('age') }}">
                    <p class="field-hint">Puede quedar vacia si no la tienes.</p>
                </div>
                <div class="field">
                    <label for="phone">WhatsApp</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" placeholder="502...">
                    <p class="field-hint">Usalo para enviar el PDF cuando este listo.</p>
                </div>
                <div class="field">
                    <label for="date">Fecha</label>
                    <input id="date" name="date" type="date" value="{{ old('date', now()->toDateString()) }}" required>
                </div>
                <input type="hidden" name="category_slug" value="{{ $selectedCategory }}" data-primary-exam>
                <div class="field span-2">
                    <label>Referencia medica</label>
                    <input type="hidden" name="referrer" value="{{ $initialReferrer }}" data-referrer-value>
                    <div class="referrer-selected {{ $initialReferrer ? 'has-value' : '' }}" data-referrer-selected>
                        <div>
                            <strong data-referrer-selected-name>{{ $initialReferrer ?: 'Sin referencia seleccionada' }}</strong>
                            <span data-referrer-selected-type>{{ $initialReferrer ? (str_contains($initialReferrer, 'CENTRO') || str_contains($initialReferrer, 'FARMACIA') ? 'Institucion' : 'Medico') : 'Selecciona una opcion' }}</span>
                        </div>
                        <div class="referrer-actions">
                            <button class="button compact-button" type="button" data-referrer-search-toggle>Buscar</button>
                            <button class="button compact-button" type="button" data-referrer-change @disabled(! $initialReferrer)>Cambiar</button>
                        </div>
                    </div>
                    <p class="field-hint">Toca una referencia de la lista. Usa Buscar solo cuando necesites filtrar.</p>
                    <input class="referrer-search-input" type="search" placeholder="Buscar por nombre" autocomplete="off" data-referrer-search hidden>
                    <div class="referrer-picker" data-referrer-picker aria-label="Referencias medicas disponibles">
                        @foreach ($referrers as $referrer)
                            <button type="button" data-referrer-option="{{ $referrer }}">
                                <strong>{{ $referrer }}</strong>
                                <span>{{ str_contains($referrer, 'CENTRO') || str_contains($referrer, 'FARMACIA') ? 'Institucion' : 'Medico' }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Examenes</p>
                        <h2>Selecciona uno o varios examenes</h2>
                    </div>
                    <div class="exam-summary-pills">
                        <span class="soft-badge" data-exam-count>{{ count($selectedExamSlugs) }} seleccionado{{ count($selectedExamSlugs) === 1 ? '' : 's' }}</span>
                        <span class="soft-badge">Subtotal Q <strong data-exam-subtotal>{{ number_format($initialPrice, 2) }}</strong></span>
                    </div>
                </div>
                <div class="exam-picker-toolbar">
                    <div class="field">
                        <label for="exam_search">Buscar examen</label>
                        <input id="exam_search" type="search" placeholder="Nombre del examen" data-exam-search>
                    </div>
                    <div class="selected-exams" data-selected-exams></div>
                </div>
                <div class="exam-picker-grid">
                    @foreach ($categories as $category)
                        @php $examPrice = (float) ($prices[$category['slug']] ?? 0); @endphp
                        <label class="exam-option-card">
                            <input class="exam-checkbox" type="checkbox" name="exam_slugs[]" value="{{ $category['slug'] }}" data-exam-option data-exam-price="{{ $examPrice }}" data-exam-label="{{ $category['name'] }}" @checked(in_array($category['slug'], $selectedExamSlugs, true))>
                            <input type="hidden" name="exam_prices[{{ $category['slug'] }}]" value="{{ number_format($examPrice, 2, '.', '') }}">
                            <span class="exam-option-main">
                                <strong>{{ $category['name'] }}</strong>
                            </span>
                            <span class="exam-price">Q {{ number_format($examPrice, 2) }}</span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="panel form-panel">
                <div class="field">
                    <label for="price">Subtotal</label>
                    <input id="price" name="price" type="number" step="0.01" min="0" value="{{ $initialPrice > 0 ? number_format($initialPrice, 2, '.', '') : '' }}" placeholder="0.00" readonly data-subtotal>
                </div>
                <div class="field">
                    <label for="discount">Descuento</label>
                    <input id="discount" name="discount" type="number" step="0.01" min="0" value="{{ $initialDiscountValue !== null ? $initialDiscountValue : '' }}" placeholder="0.00" data-discount data-money-input>
                    <p class="field-hint">Si no hay descuento, dejalo vacio o en cero.</p>
                </div>
                <div class="field">
                    <label for="paid_amount">Pago inicial</label>
                    <input id="paid_amount" name="paid_amount" type="number" step="0.01" min="0" value="{{ $initialPaidValue !== null ? $initialPaidValue : '' }}" placeholder="0.00" data-paid data-money-input>
                    <p class="field-hint">No puede ser mayor al total neto.</p>
                </div>
                <div class="field">
                    <label for="payment_method">Forma de pago</label>
                    <select id="payment_method" name="payment_method">
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="tarjeta">Tarjeta</option>
                    </select>
                </div>
                <div class="field">
                    <label for="payment_timing">Cobro</label>
                    <select id="payment_timing" name="payment_timing">
                        <option value="before">Antes del resultado</option>
                        <option value="after">Despues del resultado</option>
                    </select>
                </div>
                <div class="field">
                    <label>Total neto</label>
                    <input value="{{ number_format($initialTotal, 2, '.', '') }}" readonly data-total>
                    <p class="field-hint">Subtotal menos descuento.</p>
                </div>
                <div class="field">
                    <label>Saldo pendiente</label>
                    <input value="{{ number_format($initialBalance, 2, '.', '') }}" readonly data-balance>
                    <p class="field-hint">Lo que falta por cobrar despues del pago inicial.</p>
                </div>
            </section>

            <div class="actions">
                <a class="button" href="{{ route('orders.index') }}">Cancelar</a>
                <button class="button primary" type="submit">Guardar cobro</button>
            </div>
        </form>
    </main>

    <script>
        (() => {
            const exams = Array.from(document.querySelectorAll('[data-exam-option]'));
            const primaryExam = document.querySelector('[data-primary-exam]');
            const examCount = document.querySelector('[data-exam-count]');
            const examSubtotal = document.querySelector('[data-exam-subtotal]');
            const selectedExams = document.querySelector('[data-selected-exams]');
            const examSearch = document.querySelector('[data-exam-search]');
            const referrerValue = document.querySelector('[data-referrer-value]');
            const referrerSearch = document.querySelector('[data-referrer-search]');
            const referrerSelected = document.querySelector('[data-referrer-selected]');
            const referrerSelectedName = document.querySelector('[data-referrer-selected-name]');
            const referrerSelectedType = document.querySelector('[data-referrer-selected-type]');
            const referrerSearchToggle = document.querySelector('[data-referrer-search-toggle]');
            const referrerChange = document.querySelector('[data-referrer-change]');
            const referrerOptions = Array.from(document.querySelectorAll('[data-referrer-option]'));
            const price = document.querySelector('[data-subtotal]');
            const discount = document.querySelector('[data-discount]');
            const paid = document.querySelector('[data-paid]');
            const total = document.querySelector('[data-total]');
            const balance = document.querySelector('[data-balance]');
            if (!exams.length || !price || !discount || !paid || !total || !balance) return;

            const numericValue = (field) => Number.parseFloat(String(field.value || '').replace(',', '.')) || 0;
            const update = () => {
                const selected = exams.filter((exam) => exam.checked);
                const subtotal = selected.reduce((sum, exam) => sum + (Number.parseFloat(exam.dataset.examPrice || '0') || 0), 0);
                price.value = subtotal.toFixed(2);
                if (primaryExam) primaryExam.value = selected[0]?.value || '';
                if (examCount) examCount.textContent = `${selected.length} seleccionado${selected.length === 1 ? '' : 's'}`;
                if (examSubtotal) examSubtotal.textContent = subtotal.toFixed(2);
                if (selectedExams) {
                    selectedExams.replaceChildren();
                    if (selected.length) {
                        selected.forEach((exam) => {
                            const item = document.createElement('span');
                            item.textContent = exam.dataset.examLabel || exam.value;
                            selectedExams.appendChild(item);
                        });
                    } else {
                        const empty = document.createElement('em');
                        empty.textContent = 'Sin examenes seleccionados';
                        selectedExams.appendChild(empty);
                    }
                }

                const netTotal = Math.max(0, subtotal - numericValue(discount));
                const paidAmount = Math.max(0, numericValue(paid));

                total.value = netTotal.toFixed(2);
                balance.value = Math.max(0, netTotal - paidAmount).toFixed(2);
            };
            const filterExams = () => {
                const term = String(examSearch?.value || '').trim().toLowerCase();
                exams.forEach((exam) => {
                    const card = exam.closest('.exam-option-card');
                    const label = String(exam.dataset.examLabel || '').toLowerCase();
                    if (card) card.hidden = term !== '' && ! label.includes(term);
                });
            };
            const scheduleUpdate = () => {
                update();
                requestAnimationFrame(update);
                setTimeout(update, 50);
                setTimeout(update, 250);
            };
            exams.forEach((exam) => exam.addEventListener('change', scheduleUpdate));
            examSearch?.addEventListener('input', filterExams);
            const referrerType = (value) => {
                const normalized = String(value || '').toUpperCase();
                return normalized.includes('CENTRO') || normalized.includes('FARMACIA') ? 'Institucion' : 'Medico';
            };
            const renderReferrer = () => {
                const value = String(referrerValue?.value || '').trim();
                if (referrerSelectedName) referrerSelectedName.textContent = value || 'Sin referencia seleccionada';
                if (referrerSelectedType) referrerSelectedType.textContent = value ? referrerType(value) : 'Selecciona una opcion';
                referrerSelected?.classList.toggle('has-value', value !== '');
                if (referrerChange) referrerChange.disabled = value === '';
            };
            const filterReferrers = () => {
                const term = String(referrerSearch?.value || '').trim().toLowerCase();
                referrerOptions.forEach((option) => {
                    const label = String(option.dataset.referrerOption || '').toLowerCase();
                    option.hidden = term !== '' && ! label.includes(term);
                });
            };
            const showReferrerSearch = (focus = false) => {
                if (! referrerSearch) return;
                referrerSearch.hidden = false;
                filterReferrers();
                if (focus) referrerSearch.focus();
            };
            referrerOptions.forEach((option) => {
                option.addEventListener('click', () => {
                    if (! referrerValue) return;
                    referrerValue.value = option.dataset.referrerOption || '';
                    if (referrerSearch) {
                        referrerSearch.value = '';
                        referrerSearch.hidden = true;
                        referrerSearch.blur();
                    }
                    filterReferrers();
                    renderReferrer();
                });
            });
            referrerSearchToggle?.addEventListener('click', () => showReferrerSearch(true));
            referrerChange?.addEventListener('click', () => {
                if (! referrerValue) return;
                referrerValue.value = '';
                renderReferrer();
                showReferrerSearch(false);
            });
            referrerSearch?.addEventListener('input', filterReferrers);
            [discount, paid].forEach((field) => {
                field.addEventListener('focus', () => field.select());
                field.addEventListener('input', scheduleUpdate);
                field.addEventListener('change', scheduleUpdate);
                field.addEventListener('keyup', scheduleUpdate);
            });
            document.getElementById('order-form')?.addEventListener('submit', (event) => {
                scheduleUpdate();
                if (event.currentTarget.dataset.submitting === '1') {
                    event.preventDefault();
                    return;
                }
                event.currentTarget.dataset.submitting = '1';
                event.currentTarget.querySelectorAll('button[type="submit"]').forEach((button) => {
                    button.disabled = true;
                });
            });
            window.addEventListener('load', scheduleUpdate);
            window.addEventListener('pageshow', scheduleUpdate);
            document.addEventListener('visibilitychange', scheduleUpdate);
            scheduleUpdate();
            filterReferrers();
            renderReferrer();
        })();
    </script>
@endsection
