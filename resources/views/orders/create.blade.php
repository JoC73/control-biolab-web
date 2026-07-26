@extends('layouts.lab', ['title' => 'Nueva orden'])

@section('body')
    @php
        $selectedCategory = old('category_slug', $categories[0]['slug'] ?? '');
        $initialPrice = (float) old('price', $prices[$selectedCategory] ?? $prices[$categories[0]['slug']] ?? 0);
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
                <p class="eyebrow">Recepcion</p>
                <h1>Nueva orden</h1>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('orders.index') }}">Ordenes</a>
                <button class="button primary" type="submit" form="order-form">Guardar orden</button>
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
                <div class="field">
                    <label for="category_slug">Examen</label>
                    <select id="category_slug" name="category_slug" required data-price-source>
                        @foreach ($categories as $category)
                            <option value="{{ $category['slug'] }}" data-price="{{ $prices[$category['slug']] ?? 0 }}" @selected($selectedCategory === $category['slug'])>{{ $category['name'] }}</option>
                        @endforeach
                    </select>
                </div>
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

            <section class="panel form-panel">
                <div class="field">
                    <label for="price">Precio</label>
                    <input id="price" name="price" type="number" step="0.01" min="0" value="{{ $initialPrice > 0 ? number_format($initialPrice, 2, '.', '') : '' }}" placeholder="0.00" required data-price data-money-input>
                </div>
                <div class="field">
                    <label for="discount">Descuento</label>
                    <input id="discount" name="discount" type="number" step="0.01" min="0" value="{{ $initialDiscountValue !== null ? $initialDiscountValue : '' }}" placeholder="0.00" data-discount data-money-input>
                </div>
                <div class="field">
                    <label for="paid_amount">Pago inicial</label>
                    <input id="paid_amount" name="paid_amount" type="number" step="0.01" min="0" max="{{ number_format($initialTotal, 2, '.', '') }}" value="{{ $initialPaidValue !== null ? $initialPaidValue : '' }}" placeholder="0.00" data-paid data-money-input>
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
                <button class="button primary" type="submit">Guardar orden</button>
            </div>
        </form>
    </main>

    <script>
        (() => {
            const exam = document.querySelector('[data-price-source]');
            const price = document.querySelector('[data-price]');
            const discount = document.querySelector('[data-discount]');
            const paid = document.querySelector('[data-paid]');
            const total = document.querySelector('[data-total]');
            const balance = document.querySelector('[data-balance]');
            if (!exam || !price || !discount || !paid || !total || !balance) return;

            const numericValue = (field) => Number.parseFloat(String(field.value || '').replace(',', '.')) || 0;
            const update = () => {
                const netTotal = Math.max(0, numericValue(price) - numericValue(discount));
                const paidAmount = Math.max(0, numericValue(paid));

                total.value = netTotal.toFixed(2);
                balance.value = Math.max(0, netTotal - paidAmount).toFixed(2);
                paid.max = netTotal.toFixed(2);
            };
            const scheduleUpdate = () => {
                update();
                requestAnimationFrame(update);
                setTimeout(update, 50);
                setTimeout(update, 250);
            };
            exam.addEventListener('change', () => {
                price.value = exam.selectedOptions[0].dataset.price || 0;
                scheduleUpdate();
            });
            [price, discount, paid].forEach((field) => {
                field.addEventListener('focus', () => field.select());
                field.addEventListener('input', scheduleUpdate);
                field.addEventListener('change', scheduleUpdate);
                field.addEventListener('keyup', scheduleUpdate);
            });
            window.addEventListener('load', scheduleUpdate);
            window.addEventListener('pageshow', scheduleUpdate);
            document.addEventListener('visibilitychange', scheduleUpdate);
            scheduleUpdate();
        })();
    </script>
@endsection
