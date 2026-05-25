@if ($paginator->hasPages())
    @php
        $linkBase = 'inline-flex items-center justify-center min-w-9 h-9 px-3 text-xs font-semibold rounded-lg border border-zinc-200 bg-white text-zinc-700 hover:border-brand/40 hover:text-brand transition-colors';
        $disabledLink = 'inline-flex items-center justify-center min-w-9 h-9 px-3 text-xs font-semibold rounded-lg border border-zinc-200 bg-zinc-50 text-zinc-400 cursor-not-allowed';
        $activeLink = 'inline-flex items-center justify-center min-w-9 h-9 px-3 text-xs font-semibold rounded-lg border border-brand bg-brand text-white shadow-xs';
        $separator = 'inline-flex items-center justify-center min-w-9 h-9 px-2 text-xs text-zinc-400';
    @endphp

    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">

        <p class="text-xs text-zinc-500">
            {!! __('Showing') !!}
            @if ($paginator->firstItem())
                <span class="font-semibold text-zinc-700">{{ $paginator->firstItem() }}</span>
                {!! __('to') !!}
                <span class="font-semibold text-zinc-700">{{ $paginator->lastItem() }}</span>
            @else
                <span class="font-semibold text-zinc-700">{{ $paginator->count() }}</span>
            @endif
            {!! __('of') !!}
            <span class="font-semibold text-zinc-700">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </p>

        <div class="flex items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="{{ $disabledLink }}" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                    @svg('lucide-chevron-left', 'size-4')
                </span>
            @else
                <button type="button" wire:click="previousPage" rel="prev" class="{{ $linkBase }}" aria-label="{{ __('pagination.previous') }}">
                    @svg('lucide-chevron-left', 'size-4')
                </button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $separator }}">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="{{ $activeLink }}" aria-current="page">{{ $page }}</span>
                        @else
                            <button type="button" wire:click="gotoPage({{ $page }})" class="{{ $linkBase }}">
                                {{ $page }}
                            </button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage" rel="next" class="{{ $linkBase }}" aria-label="{{ __('pagination.next') }}">
                    @svg('lucide-chevron-right', 'size-4')
                </button>
            @else
                <span class="{{ $disabledLink }}" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                    @svg('lucide-chevron-right', 'size-4')
                </span>
            @endif
        </div>
    </nav>
@endif
