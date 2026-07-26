<footer class="report-footer">
    <div class="footer-seal">
        <div class="mini-stamp">
            @include('components.biolab-logo', ['class' => 'mini-logo'])
            <span>LABORATORIO<br>CLINICO<br>BIOLOGICO</span>
            <strong>BIOLAB</strong>
            <small>TEL: {{ $business['phone'] }}</small>
        </div>
        <img class="signature-img" src="{{ asset('assets/firma-biolab.png') }}" alt="Firma responsable">
    </div>
    <p>Horarios: Lunes a Viernes 7:00 a 19:00 horas Sabados 7:00 a 17:00 horas</p>
    <strong>DOMINGOS SOLO EMERGENCIAS</strong>
</footer>
