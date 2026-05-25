@props([
    'variant' => 'outline',
    'size' => 'md',
    'icon' => null,
    'href' => null,
])

@php
    $variants = [
        'primary' => 'bg-brand text-white hover:bg-brand-dark shadow-xs focus-visible:ring-2 focus-visible:ring-brand/30',
        'secondary' => 'bg-brand-secondary text-white hover:bg-brand-secondary-dark shadow-xs focus-visible:ring-2 focus-visible:ring-brand-secondary/30',
        'outline' => 'border border-zinc-200 bg-white text-zinc-700 hover:border-brand/40 hover:text-brand shadow-xs focus-visible:ring-2 focus-visible:ring-brand/20',
        'ghost' => 'text-zinc-600 hover:bg-brand-soft hover:text-brand focus-visible:ring-2 focus-visible:ring-brand/20',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 shadow-xs focus-visible:ring-2 focus-visible:ring-red-600/30',
    ];

    // Sizes + child-svg sizing as a single static string per size.
    // Tailwind's scanner needs to see the full class literally, so we cannot
    // concatenate "[&>svg]:" with a dynamic suffix at render time.
    $sizes = [
        'sm' => 'h-8 gap-1.5 rounded-lg px-3 text-xs [&>svg]:size-3.5 [&>svg]:shrink-0',
        'md' => 'h-9 gap-2 rounded-lg px-4 text-sm [&>svg]:size-4 [&>svg]:shrink-0',
        'lg' => 'h-10 gap-2 rounded-lg px-5 text-sm [&>svg]:size-4 [&>svg]:shrink-0',
    ];

    $iconClass = [
        'sm' => 'size-3.5 shrink-0',
        'md' => 'size-4 shrink-0',
        'lg' => 'size-4 shrink-0',
    ][$size] ?? 'size-4 shrink-0';

    $tag = $href ? 'a' : 'button';
    $base = 'inline-flex items-center justify-center font-medium transition-colors focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="{{ $attributes->get('type', 'button') }}" @endif
    {{ $attributes->except(['type'])->class([$base, $variants[$variant] ?? $variants['outline'], $sizes[$size] ?? $sizes['md']]) }}>
    @if ($icon)
        @svg('lucide-'.$icon, $iconClass)
    @endif

    {{ $slot }}
</{{ $tag }}>
