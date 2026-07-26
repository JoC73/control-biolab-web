@extends('layouts.lab', ['title' => 'Nueva orden'])

@section('body')
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
                            <option value="{{ $category['slug'] }}" data-price="{{ $prices[$category['slug']] ?? 0 }}">{{ $category['name'] }}</option>
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
                    <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $prices[$categories[0]['slug']] ?? 0) }}" required data-price>
                </div>
                <div class="field">
                    <label for="discount">Descuento</label>
                    <input id="discount" name="discount" type="number" step="0.01" min="0" value="{{ old('discount', 0) }}" data-discount>
                </div>
                <div class="field">
                    <label for="paid_amount">Pago inicial</label>
                    <input id="paid_amount" name="paid_amount" type="number" step="0.01" min="0" value="{{ old('paid_amount', 0) }}" data-paid>
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
                    <input value="0.00" readonly data-total>
                </div>
                <div class="field">
                    <label>Saldo pendiente</label>
                    <input value="0.00" readonly data-balance>
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
            const update = () => {
                const netTotal = Math.max(0, (parseFloat(price.value) || 0) - (parseFloat(discount.value) || 0));
                const paidAmount = Math.max(0, parseFloat(paid.value) || 0);

                total.value = netTotal.toFixed(2);
                balance.value = Math.max(0, netTotal - paidAmount).toFixed(2);
                paid.max = netTotal.toFixed(2);
            };
            exam.addEventListener('change', () => {
                price.value = exam.selectedOptions[0].dataset.price || 0;
                update();
            });
            price.addEventListener('input', update);
            discount.addEventListener('input', update);
            paid.addEventListener('input', update);
            update();
        })();
    </script>
@endsection
