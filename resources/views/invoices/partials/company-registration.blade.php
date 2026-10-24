@php
    $registeredOffice = trim((string) config('company.registered_office'));
    $companyNumber = trim((string) config('company.number'));
@endphp

@if ($registeredOffice !== '')
    <div class="muted"><strong>Registered office:</strong> {{ $registeredOffice }}</div>
@endif
@if ($companyNumber !== '')
    <div class="muted"><strong>Company number:</strong> {{ $companyNumber }}</div>
@endif
