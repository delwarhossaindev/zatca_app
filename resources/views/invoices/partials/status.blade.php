@php
    [$class, $icon] = match ($status) {
        'REPORTED', 'CLEARED' => ['ok', 'bi-patch-check-fill'],
        'NOT_REPORTED', 'NOT_CLEARED', 'ERROR' => ['bad', 'bi-x-octagon-fill'],
        'pending' => ['wait', 'bi-hourglass-split'],
        default => ['warn', 'bi-exclamation-triangle-fill'],
    };
@endphp
<span class="z-status {{ $class }}"><i class="bi {{ $icon }}"></i> {{ str_replace('_', ' ', $status) }}</span>
