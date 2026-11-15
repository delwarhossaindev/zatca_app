@props(['title', 'arabic' => null, 'icon' => 'receipt', 'back' => null, 'backLabel' => null])

<div {{ $attributes->class('z-page-head no-print') }}>
    @if ($back)
        <a href="{{ $back }}" class="z-back d-inline-flex align-items-center gap-1 mb-2"><i class="bi bi-arrow-left"></i> {{ $backLabel }}</a>
    @endif
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <span class="z-head-icon"><i class="bi bi-{{ $icon }}"></i></span>
            <div>
                <h1 class="h3">{{ $title }}</h1>
                @if ($arabic)<div class="ar text-start">{{ $arabic }}</div>@endif
            </div>
        </div>
        @if ($slot->isNotEmpty())
            <div class="d-flex flex-wrap align-items-center gap-2">{{ $slot }}</div>
        @endif
    </div>
    <div class="z-ornament"><i class="bi bi-asterisk small"></i></div>
</div>
