@props([
    'value',
    'label' => null,
])

@php
    $displayDateTime = $value
        ? \Illuminate\Support\Carbon::parse($value)->timezone(config('app.timezone', 'Europe/London'))
        : null;
@endphp

<small {{ $attributes->class(['text-muted', 'd-block', 'text-nowrap']) }}>
    @if ($displayDateTime)
        <time datetime="{{ $displayDateTime->toIso8601String() }}">{{ $label ? $label . ' ' : '' }}{{ $displayDateTime->format('d M Y, h:i A T') }}</time>
    @else
        —
    @endif
</small>
