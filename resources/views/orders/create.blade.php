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
                <div class="status-message wide error-message">{{ $errors->first() }}</div>
            @endif

            <section class="panel form-panel">
                <div class="field span-2">
                    <label for="patient_name">Paciente</label>
                    <input id="patient_name" name="patient_name" value="{{ old('patient_name') }}" required autofocus>
                </div>
                <div class="field">
                    <label for="age">Edad</label>
                    <input id="age" name="age" value="{{ old('age') }}">
                </div>
                <div class="field">
                    <label for="phone">WhatsApp</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" placeholder="502...">
                </div>
                <div class="field">
                    <label for="date">Fecha</label>
                    <input id="date" name="date" type="date" value="{{ old('date', now()->toDateString()) }}" required>
                </div>
                <input type="hidden" name="category_slug" value="{{ $selectedCategory }}" data-primary-exam>
                <div class="field span-2">
                    <label for="referrer">Referencia medica</label>
                    <input id="referrer" name="referrer" list="referrers" value="{{ old('referrer') }}" placeholder="Medico o institucion">
                    <datalist id="referrers">
                        @foreach ($referrers as $referrer)
                            <option value="{{ $referrer }}"></option>
                        @endforeach
                    </datalist>
                </div>
            </section>

            <section class="panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Examenes</p>
                        <h2>Selecciona uno o varios examenes</h2>
                    </div>
                    <span class="soft-badge" data-exam-count>{{ count($selectedExamSlugs) }} seleccionado{{ count($selectedExamSlugs) === 1 ? '' : 's' }}</span>
                </div>
                <div class="catalog-grid compact-exam-picker">
                    @foreach ($categories as $category)
                        @php $examPrice = (float) ($prices[$category['slug']] ?? 0); @endphp
                        <label class="catalog-card selectable-card">
                            <input type="checkbox" name="exam_slugs[]" value="{{ $category['slug'] }}" data-exam-option data-price="{{ $examPrice }}" @checked(in_array($category['slug'], $selectedExamSlugs, true))>
                            <input type="hidden" name="exam_prices[{{ $category['slug'] }}]" value="{{ number_format($examPrice, 2, '.', '') }}">
                            <strong>{{ $category['name'] }}</strong>
                            <span>Q {{ number_format($examPrice, 2) }}</span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="panel form-panel">
                <div class="field">
                    <label for="price">Subtotal</label>
                    <input id="price" name="price" type="number" step="0.01" min="0" value="{{ $initialPrice > 0 ? number_format($initialPrice, 2, '.', '') : '' }}" placeholder="0.00" readonly data-price>
                </div>
                <div class="field">
                    <label for="discount">Descuento</label>
                    <input id="discount" name="discount" type="number" step="0.01" min="0" value="{{ $initialDiscountValue !== null ? $initialDiscountValue : '' }}" placeholder="0.00" data-discount data-money-input>
                </div>
                <div class="field">
                    <label for="paid_amount">Pago inicial</label>
                    <input id="paid_amount" name="paid_amount" type="number" step="0.01" min="0" value="{{ $initialPaidValue !== null ? $initialPaidValue : '' }}" placeholder="0.00" data-paid data-money-input>
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
                </div>
                <div class="field">
                    <label>Saldo pendiente</label>
                    <input value="{{ number_format($initialBalance, 2, '.', '') }}" readonly data-balance>
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
            const price = document.querySelector('[data-price]');
            const discount = document.querySelector('[data-discount]');
            const paid = document.querySelector('[data-paid]');
            const total = document.querySelector('[data-total]');
            const balance = document.querySelector('[data-balance]');
            if (!exams.length || !price || !discount || !paid || !total || !balance) return;

            const numericValue = (field) => Number.parseFloat(String(field.value || '').replace(',', '.')) || 0;
            const update = () => {
                const selected = exams.filter((exam) => exam.checked);
                const subtotal = selected.reduce((sum, exam) => sum + (Number.parseFloat(exam.dataset.price || '0') || 0), 0);
                price.value = subtotal.toFixed(2);
                if (primaryExam) primaryExam.value = selected[0]?.value || '';
                if (examCount) examCount.textContent = `${selected.length} seleccionado${selected.length === 1 ? '' : 's'}`;

                const netTotal = Math.max(0, subtotal - numericValue(discount));
                const paidAmount = Math.max(0, numericValue(paid));

                total.value = netTotal.toFixed(2);
                balance.value = Math.max(0, netTotal - paidAmount).toFixed(2);
            };
            const scheduleUpdate = () => {
                update();
                requestAnimationFrame(update);
                setTimeout(update, 50);
                setTimeout(update, 250);
            };
            exams.forEach((exam) => exam.addEventListener('change', scheduleUpdate));
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
        })();
    </script>
@endsection
