@props(['paginate' => null])

<div class="flex flex-col gap-0 overflow-hidden rounded-xl border border-zinc-200 bg-white text-xs!">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            @isset($columns)
                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-50">
                        {{ $columns }}
                    </tr>
                </thead>
            @endisset
            <tbody class="divide-y divide-zinc-100">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if ($paginate && $paginate->hasPages())
        <div class="border-t border-zinc-200">
            {{ $paginate->links('ui.pagination') }}
        </div>
    @endif
</div>
