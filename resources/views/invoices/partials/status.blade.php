@php
    $class = match ($status) {
        'REPORTED', 'CLEARED' => 'bg-success',
        'NOT_REPORTED', 'NOT_CLEARED', 'ERROR' => 'bg-danger',
        'pending' => 'bg-secondary',
        default => 'bg-warning text-dark',
    };
@endphp
<span class="badge {{ $class }}">{{ str_replace('_', ' ', $status) }}</span>
