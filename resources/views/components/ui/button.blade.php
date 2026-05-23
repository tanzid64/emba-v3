@props([
    'variant' => 'outline',
    'size' => 'md',
    'icon' => null,
    'href' => null,
])

@php
    $variants = [
        'primary' => 'bg-zinc-900 text-white hover:bg-zinc-700 :bg-zinc-200 shadow-xs',
        'outline' => 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 :bg-zinc-700 shadow-xs',
        'ghost' => 'text-zinc-600 hover:bg-zinc-100 :bg-zinc-700/60',
    ];

    $sizes = [
        'sm' => 'h-8 gap-1.5 rounded-lg px-3 text-xs',
        'md' => 'h-9 gap-2 rounded-lg px-4 text-sm',
        'lg' => 'h-10 gap-2 rounded-lg px-5 text-sm',
    ];

    $iconPath = $icon ? $icons[$icon] ?? null : null;
    $tag = $href ? 'a' : 'button';
    $base =
        'inline-flex items-center justify-center font-medium transition-colors focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="{{ $attributes->get('type', 'button') }}" @endif
    {{ $attributes->except(['type'])->class([$base, $variants[$variant] ?? $variants['outline'], $sizes[$size] ?? $sizes['md']]) }}>
    @if ($icon)
        <x-icon name="{{ 'lucide.' . $icon }}" />
    @endif

    @if ($slot->isNotEmpty())
        <span>{{ $slot }}</span>
    @endif
    </{{ $tag }}>
