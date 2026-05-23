@props([
    'color' => 'zinc',
    'variant' => 'solid',
])

@php
    $colors = [
        'green' => 'bg-green-100 text-green-700 ',
        'yellow' => 'bg-yellow-100 text-yellow-700 ',
        'red' => 'bg-red-100 text-red-700 ',
        'blue' => 'bg-blue-100 text-blue-700 ',
        'zinc' => 'bg-zinc-100 text-zinc-600 ',
    ];

    $outline = [
        'green' => 'border border-green-300 text-green-700 ',
        'yellow' => 'border border-yellow-300 text-yellow-700 ',
        'red' => 'border border-red-300 text-red-700 ',
        'blue' => 'border border-blue-300 text-blue-700 ',
        'zinc' => 'border border-zinc-300 text-zinc-600 ',
    ];

    $colorClass = $variant === 'outline' ? $outline[$color] ?? $outline['zinc'] : $colors[$color] ?? $colors['zinc'];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium', $colorClass]) }}>
    {{ $slot }}
</span>
