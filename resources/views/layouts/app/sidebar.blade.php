<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @php
                $sidebarBatches = \App\Models\Batch::orderByDesc('admission_year')->orderByDesc('id')->get(['id', 'name', 'code', 'status', 'admission_year']);
                $sidebarCurrentBatch = \App\Support\CurrentBatch::get();

                $batchStatusDot = fn (?\App\Enum\BatchStatusEnum $status) => match ($status) {
                    \App\Enum\BatchStatusEnum::OPEN => 'bg-green-500',
                    \App\Enum\BatchStatusEnum::DRAFT => 'bg-yellow-500',
                    \App\Enum\BatchStatusEnum::CLOSED => 'bg-zinc-400',
                    default => 'bg-zinc-300',
                };
                $batchStatusLabel = fn (?\App\Enum\BatchStatusEnum $status) => $status ? ucfirst($status->value) : '—';
            @endphp

            <div
                x-data="{ open: false }"
                @click.outside="open = false"
                @keydown.escape.window="open = false"
                class="relative px-3 pt-3 pb-2"
            >
                <p class="mb-1.5 text-[10px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                    {{ __('Active batch') }}
                </p>

                <button
                    type="button"
                    @click="open = !open"
                    :aria-expanded="open"
                    aria-haspopup="listbox"
                    @disabled($sidebarBatches->isEmpty())
                    class="flex w-full items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm font-semibold text-zinc-800 shadow-xs transition focus:outline-none focus:border-brand disabled:opacity-50 disabled:cursor-not-allowed dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-100"
                >
                    @if ($sidebarCurrentBatch)
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="shrink-0 inline-block size-2 rounded-full {{ $batchStatusDot($sidebarCurrentBatch->status) }}"></span>
                            <span class="truncate">{{ $sidebarCurrentBatch->name }}</span>
                        </span>
                    @else
                        <span class="text-zinc-400">{{ __('No batch') }}</span>
                    @endif
                    <x-lucide-chevrons-up-down class="size-4 text-zinc-400 shrink-0" />
                </button>

                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-1"
                    class="absolute z-50 mt-1.5 w-[calc(100%-1.5rem)] rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                    role="listbox"
                >
                    @forelse ($sidebarBatches as $sidebarBatch)
                        @php $isCurrent = $sidebarCurrentBatch?->id === $sidebarBatch->id; @endphp
                        <form method="POST" action="{{ route('admin.current-batch.set') }}" class="block">
                            @csrf
                            <input type="hidden" name="batch_id" value="{{ $sidebarBatch->id }}">
                            <input type="hidden" name="_return_to" value="{{ url()->current() }}">
                            <button
                                type="submit"
                                role="option"
                                :aria-selected="{{ $isCurrent ? 'true' : 'false' }}"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/50 {{ $isCurrent ? 'bg-zinc-50 dark:bg-zinc-700/40' : '' }} first:rounded-t-lg last:rounded-b-lg"
                            >
                                <span class="shrink-0 inline-block size-2 rounded-full {{ $batchStatusDot($sidebarBatch->status) }}" title="{{ $batchStatusLabel($sidebarBatch->status) }}"></span>

                                <span class="flex-1 min-w-0">
                                    <span class="block truncate font-semibold text-zinc-800 dark:text-zinc-100">{{ $sidebarBatch->name }}</span>
                                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $sidebarBatch->code }} · {{ $batchStatusLabel($sidebarBatch->status) }}</span>
                                </span>

                                @if ($isCurrent)
                                    <x-lucide-check class="size-4 shrink-0 text-brand" />
                                @endif
                            </button>
                        </form>
                    @empty
                        <p class="px-3 py-3 text-center text-xs text-zinc-400">{{ __('No batches available.') }}</p>
                    @endforelse
                </div>
            </div>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Admission')" class="grid">
                    <flux:sidebar.item icon="rectangle-stack" :href="route('admin.batches.index')" :current="request()->routeIs('admin.batches.*')" wire:navigate>
                        {{ __('Admission Batch') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('admin.applicants.index')" :current="request()->routeIs('admin.applicants.*')" wire:navigate>
                        {{ __('All Applicants') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-check" :href="route('admin.confirmed-applicants.index')" :current="request()->routeIs('admin.confirmed-applicants.*')" wire:navigate>
                        {{ __('Confirmed Applications') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Help')" class="grid">
                    <flux:sidebar.item icon="book-open-text" :href="route('admin.docs')" :current="request()->routeIs('admin.docs')" wire:navigate>
                        {{ __('Documentation') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <x-ui.toast />
        @endpersist

        @fluxScripts
    </body>
</html>
