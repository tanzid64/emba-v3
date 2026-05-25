@props(['paginate' => null])

<div class="flex flex-col gap-0 overflow-hidden rounded-xl border border-zinc-200 bg-white text-xs!">
    @isset($toolbar)
        <div class="border-b border-zinc-200 px-4 py-3">
            {{ $toolbar }}
        </div>
    @endisset

    <div class="overflow-x-auto">
        <table class="w-full text-sm [&_thead_th]:border-e [&_thead_th]:border-zinc-200 [&_thead_th:last-child]:border-e-0 [&_tbody_td]:border-e [&_tbody_td]:border-zinc-200 [&_tbody_td:last-child]:border-e-0">
            @isset($columns)
                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-50">
                        {{ $columns }}
                    </tr>
                </thead>
            @endisset
            <tbody class="divide-y divide-zinc-200">
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
