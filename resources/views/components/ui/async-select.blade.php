@props([
    'optionsProperty' => 'options',
    'searchProperty' => null,
    'options' => [],
    'placeholder' => 'Select an option...',
    'searchPlaceholder' => 'Search...',
    'noResultsText' => 'No results found.',
    'clearable' => false,
    'disabled' => false,
])

<div
    x-data="{
        open: false,
        search: '',
        loading: false,
        selected: null,
        internalOptions: {{ Js::from($options) }},

        get displayedOptions() {
            return this.internalOptions;
        },

        init() {
            this.$watch(
                () => this.$wire['{{ $optionsProperty }}'],
                (fresh) => { if (Array.isArray(fresh)) this.internalOptions = fresh; }
            );

            this.$nextTick(() => {
                const val = this.$refs.hidden?.value;
                if (val !== '' && val !== null && val !== undefined) {
                    this.selected = this.internalOptions.find(o => String(o.value) === String(val)) ?? null;
                }
            });
        },

        openDropdown() {
            if ({{ $disabled ? 'true' : 'false' }}) return;
            this.open = true;
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        closeDropdown() {
            this.open = false;
            this.search = '';
            @if ($searchProperty)
            this.$wire.set('{{ $searchProperty }}', '');
            @endif
        },

        toggle() {
            this.open ? this.closeDropdown() : this.openDropdown();
        },

        selectOption(option) {
            this.selected = option;
            const hidden = this.$refs.hidden;
            hidden.value = option.value;
            hidden.dispatchEvent(new Event('input'));
            this.closeDropdown();
        },

        clearSelection() {
            this.selected = null;
            const hidden = this.$refs.hidden;
            hidden.value = '';
            hidden.dispatchEvent(new Event('input'));
        },

        async onSearch(value) {
            this.search = value;
            @if ($searchProperty)
            this.loading = true;
            await this.$wire.set('{{ $searchProperty }}', value);
            this.loading = false;
            @endif
        },
    }"
    x-on:click.outside="closeDropdown()"
    x-on:keydown.escape.window="open && closeDropdown()"
    class="relative"
>
    {{-- Wire binding target --}}
    <input type="hidden" x-ref="hidden" {{ $attributes->whereStartsWith('wire') }} />

    {{-- Trigger --}}
    <button
        type="button"
        x-on:click="toggle()"
        :disabled="{{ $disabled ? 'true' : 'false' }}"
        :aria-expanded="open"
        aria-haspopup="listbox"
        class="flex w-full items-center justify-between rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm shadow-xs transition focus:outline-none focus:border-zinc-400 disabled:cursor-not-allowed disabled:opacity-50 :border-white/20 :bg-zinc-800"
    >
        <span
            x-text="selected ? selected.label : '{{ $placeholder }}'"
            :class="selected ? 'text-zinc-800 :text-zinc-100' : 'text-zinc-400 :text-zinc-500'"
        ></span>

        <span class="flex shrink-0 items-center gap-1 ms-2">
            @if ($clearable)
                <span
                    x-show="selected"
                    x-cloak
                    x-on:click.stop="clearSelection()"
                    class="rounded p-0.5 text-zinc-400 transition hover:text-zinc-600 :hover:text-zinc-300"
                >
                    <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </span>
            @endif
            <svg
                class="size-4 text-zinc-400 transition-transform duration-150"
                :class="{ 'rotate-180': open }"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke-width="1.5" stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </span>
    </button>

    {{-- Dropdown --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-[0.98] translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-[0.98] translate-y-1"
        class="absolute z-50 mt-1.5 w-full min-w-48 rounded-lg border border-zinc-200 bg-white shadow-lg :border-zinc-700 :bg-zinc-800"
    >
        @if ($searchProperty)
            <div class="border-b border-zinc-100 p-2 :border-zinc-700">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-2.5 text-zinc-400">
                        <svg x-show="!loading" class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <svg x-show="loading" x-cloak class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 0s6.627 0 8 8z" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        x-ref="searchInput"
                        x-model="search"
                        x-on:input.debounce.300ms="onSearch($event.target.value)"
                        placeholder="{{ $searchPlaceholder }}"
                        class="block w-full rounded-md py-1.5 ps-8 pe-3 text-sm text-zinc-800 placeholder-zinc-400 focus:outline-none :text-zinc-100 :placeholder-zinc-500"
                    />
                </div>
            </div>
        @endif

        <ul class="max-h-56 overflow-y-auto py-1" role="listbox">
            <template x-for="option in displayedOptions" :key="option.value">
                <li
                    role="option"
                    :aria-selected="selected && String(selected.value) === String(option.value)"
                    x-on:click="selectOption(option)"
                    :class="selected && String(selected.value) === String(option.value) ? 'bg-zinc-50 :bg-zinc-700/50' : ''"
                    class="flex cursor-pointer select-none items-center justify-between px-3 py-2 text-sm text-zinc-700 hover:bg-zinc-50 :text-zinc-200 :hover:bg-zinc-700/50"
                >
                    <span x-text="option.label"></span>
                    <svg
                        x-show="selected && String(selected.value) === String(option.value)"
                        class="size-4 shrink-0 text-zinc-900 :text-white"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </li>
            </template>

            <li x-show="displayedOptions.length === 0" class="px-3 py-4 text-center text-sm text-zinc-400">
                {{ $noResultsText }}
            </li>
        </ul>
    </div>
</div>
