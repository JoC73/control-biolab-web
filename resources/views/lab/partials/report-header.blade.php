<header class="report-header">
    <div class="report-brand">
        @include('components.biolab-logo', ['class' => 'report-logo'])
        <strong>LABORATORIO</strong>
        <span>BIOLAB</span>
        <small>TEL: {{ $business['phone'] }}</small>
    </div>
    <div class="report-business">
        <h1>{{ $business['name'] }}</h1>
        <p>{{ $business['address'] }}</p>
        <p>TELEFONOS: {{ $business['phone'] }}</p>
        <h3>{{ $business['director'] }}</h3>
        <p>{{ $business['credential'] }}</p>
    </div>
    <div class="report-side-space"></div>
</header>
