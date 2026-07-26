<input type="hidden" name="patient_name" value="{{ $result['patient_name'] }}">
<input type="hidden" name="age" value="{{ $result['age'] ?? '' }}">
<input type="hidden" name="referred_by" value="{{ $result['referred_by'] ?? '' }}">
<input type="hidden" name="date" value="{{ $result['date'] }}">

@foreach ($tests as $index => $test)
    <input type="hidden" name="tests[{{ $index }}][name]" value="{{ $test['name'] }}">
    <input type="hidden" name="tests[{{ $index }}][unit]" value="{{ $test['unit'] }}">
    <input type="hidden" name="tests[{{ $index }}][reference]" value="{{ $test['reference'] }}">
    <input type="hidden" name="results[{{ $index }}]" value="{{ $result['results'][$index] ?? '' }}">
@endforeach
