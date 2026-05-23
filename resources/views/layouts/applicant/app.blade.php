<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'FBS EMBA' }} — Applicant Portal</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Open Sans', sans-serif; }
        .font-inter { font-family: 'Inter', sans-serif; }
    </style>
</head>

<body class="bg-gray-50 text-gray-700 antialiased" x-data="{ sidebarOpen: false }">

    {{-- ===================== HEADER ===================== --}}
    <header class="fixed top-0 inset-x-0 z-50 bg-white border-b border-gray-200" style="height:64px;">
        <div class="h-full flex items-center justify-between px-4 sm:px-6">

            {{-- Logo + hamburger --}}
            <div class="flex items-center gap-3">
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors"
                    aria-label="Toggle sidebar"
                >
                    <x-lucide-menu class="size-5" />
                </button>
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 shrink-0">
                    <img src="{{ asset('assets/logo/logo.jpg') }}" alt="University of Dhaka" class="h-10 w-auto">
                    <div class="hidden sm:block leading-tight">
                        <p class="font-inter font-bold text-sm text-gray-900">FBS EMBA</p>
                        <p class="font-inter text-xs font-medium text-gray-400">Applicant Portal</p>
                    </div>
                </a>
            </div>

            {{-- Right side --}}
            <div class="flex items-center gap-3">
                <div class="text-right hidden sm:block">
                    <p class="text-xs font-semibold text-gray-800">{{ auth('applicant')->user()->email }}</p>
                    <p class="text-xs text-gray-400">Applicant</p>
                </div>
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0" style="background:#2F1B72;">
                    {{ strtoupper(substr(auth('applicant')->user()->email, 0, 1)) }}
                </div>
            </div>
        </div>
    </header>

    {{-- ===================== SIDEBAR BACKDROP (mobile) ===================== --}}
    <div
        x-show="sidebarOpen"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="sidebarOpen = false"
        class="fixed inset-0 z-30 bg-black/40 lg:hidden"
        x-cloak
    ></div>

    {{-- ===================== SIDEBAR ===================== --}}
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed top-0 left-0 z-40 h-full w-64 bg-white border-r border-gray-200 flex flex-col transition-transform duration-200 ease-in-out lg:translate-x-0 lg:top-[64px] lg:h-[calc(100vh-64px)]"
    >
        {{-- Mobile: logo inside sidebar --}}
        <div class="lg:hidden flex items-center gap-2.5 px-5 py-4 border-b border-gray-100">
            <img src="{{ asset('assets/logo/logo.jpg') }}" alt="University of Dhaka" class="h-10 w-auto">
            <div class="leading-tight">
                <p class="font-inter font-bold text-sm text-gray-900">FBS EMBA</p>
                <p class="font-inter text-xs font-medium text-gray-400">Applicant Portal</p>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            <p class="px-3 mb-2 text-xs font-bold uppercase tracking-widest text-gray-400">Menu</p>

            <a href="#"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors
                    {{ request()->routeIs('applicant.dashboard') ? 'text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}"
                style="{{ request()->routeIs('applicant.dashboard') ? 'background:#2F1B72;' : '' }}"
            >
                <x-lucide-layout-dashboard class="size-4 shrink-0" />
                Dashboard
            </a>

            <a href="#"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
            >
                <x-lucide-clipboard-list class="size-4 shrink-0" />
                Application Flow
            </a>

            <a href="#"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
            >
                <x-lucide-user-circle class="size-4 shrink-0" />
                Profile
            </a>
        </nav>

        {{-- Bottom: logout --}}
        <div class="px-3 py-4 border-t border-gray-100">
            <form method="POST" action="{{ route('applicant.logout') }}">
                @csrf
                <button type="submit"
                    class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-500 hover:bg-red-50 hover:text-red-600 transition-colors"
                >
                    <x-lucide-log-out class="size-4 shrink-0" />
                    Log out
                </button>
            </form>
        </div>
    </aside>

    {{-- ===================== CONTENT ===================== --}}
    <main class="lg:pl-64 pt-[64px] min-h-screen">
        <div class="p-6 sm:p-8">
            {{ $slot }}
        </div>
    </main>

</body>
</html>
