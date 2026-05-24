<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'FBS EMBA Admission' }} — University of Dhaka</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Open Sans', sans-serif; }
        .font-inter { font-family: 'Inter', sans-serif; }
        .hero-bg { background: url('/assets/images/FBS-Banner.jpg') center center / cover no-repeat; }
        #mobile-menu { display: none; }
        #mobile-menu.open { display: block; }
        #sub-mobile-menu { display: none; }
        #sub-mobile-menu.open { display: block; }
    </style>
</head>

<body class="bg-white text-gray-700 antialiased">

    {{-- ===================== TOP HEADER ===================== --}}
    <header class="fixed top-0 inset-x-0 z-50 bg-white shadow-md" style="height:80px;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-full flex items-center justify-between">

            <a href="/" class="flex items-center gap-3 shrink-0">
                <img src="{{ asset('assets/logo/logo.jpg') }}" alt="University of Dhaka" class="h-16 w-auto">
                <div class="hidden sm:block leading-tight">
                    <p class="font-inter font-bold text-lg text-black">Executive MBA Program</p>
                    <p class="font-inter text-sm font-semibold" style="color:#A27126;">Faculty of Business Studies, University of Dhaka</p>
                </div>
            </a>

            <div class="hidden md:flex items-center gap-3">
                @auth('applicant')
                    <a href="{{ route('applicant.dashboard') }}"
                        class="px-5 py-2 rounded text-sm font-bold text-white transition-opacity hover:opacity-90"
                        style="background:#2F1B72;">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('applicant.register') }}"
                        class="px-5 py-2 rounded text-sm font-bold text-white transition-opacity hover:opacity-90"
                        style="background:#2F1B72;">
                        Apply Now
                    </a>
                    <a href="{{ route('applicant.login') }}"
                        class="px-5 py-2 rounded text-sm font-semibold border-2 transition-colors hover:bg-gray-50"
                        style="border-color:#2F1B72; color:#2F1B72;">
                        Student Login
                    </a>
                @endauth
                <a href="#"
                    class="px-5 py-2 rounded text-sm font-semibold border-2 transition-colors hover:bg-gray-50"
                    style="border-color:#2F1B72; color:#2F1B72;">
                    Admin Login
                </a>
            </div>

            <button id="header-toggle" class="md:hidden p-2 rounded focus:outline-none" style="color:#2F1B72;" aria-label="Toggle menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path id="icon-open" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    <path id="icon-close" class="hidden" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div id="mobile-menu" class="md:hidden bg-white border-t border-gray-100 shadow-lg px-4 py-3 space-y-2">
            @auth('applicant')
                <a href="{{ route('applicant.dashboard') }}" class="block text-center py-2 rounded font-bold text-white text-sm"
                    style="background:#2F1B72;">Dashboard</a>
            @else
                <a href="{{ route('applicant.register') }}" class="block text-center py-2 rounded font-bold text-white text-sm"
                    style="background:#2F1B72;">Apply Now</a>
                <a href="{{ route('applicant.login') }}" class="block text-center py-2 rounded font-semibold text-sm border-2"
                    style="border-color:#2F1B72; color:#2F1B72;">Student Login</a>
            @endauth
            <a href="#" class="block text-center py-2 rounded font-semibold text-sm border-2"
                style="border-color:#2F1B72; color:#2F1B72;">Admin Login</a>
        </div>
    </header>

    {{-- ===================== SUB NAV ===================== --}}
    <nav class="fixed inset-x-0 z-40 shadow-sm" style="top:80px; background:#2F1B72;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between">
                <ul class="hidden md:flex items-center">
                    <li><a href="/" class="block px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors">Home</a></li>
                    <li><a href="#" class="block px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors">Eligibility</a></li>
                    <li><a href="#" class="block px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors">Instruction &amp; Guideline</a></li>
                    <li><a href="#" class="block px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors">Make Payment</a></li>
                    <li><a href="#" class="block px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors">App ID Recovery</a></li>
                </ul>
                <ul class="hidden md:flex items-center">
                    <li><a href="{{ route('applicant.register') }}" class="flex items-center gap-1 px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors"><x-lucide-log-in class="size-3.5" /> Apply</a></li>
                    <li><a href="{{ route('applicant.login') }}" class="flex items-center gap-1 px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors"><x-lucide-user class="size-3.5" /> Student</a></li>
                    <li><a href="#" class="flex items-center gap-1 px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors"><x-lucide-lock class="size-3.5" /> Admin</a></li>
                </ul>

                <div class="md:hidden flex items-center justify-between w-full py-1">
                    <span class="text-white text-sm font-bold">Navigation</span>
                    <button id="sub-toggle" class="p-2 text-white" aria-label="Toggle sub menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <div id="sub-mobile-menu" class="md:hidden pb-2 space-y-1">
                <a href="/" class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Home</a>
                <a href="#" class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Eligibility</a>
                <a href="#" class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Instruction &amp; Guideline</a>
                <a href="#" class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Make Payment</a>
                <a href="#" class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">App ID Recovery</a>
                <a href="{{ route('applicant.register') }}" class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Apply</a>
                <a href="{{ route('applicant.login') }}" class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Student Login</a>
                <a href="#" class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Admin Login</a>
            </div>
        </div>
    </nav>

    {{-- ===================== CONTENT ===================== --}}
    <main class="pt-[128px]">
        {{ $slot }}
    </main>

    {{-- ===================== FOOTER ===================== --}}
    <footer class="bg-white border-t border-gray-200 py-8">
        <div class="max-w-7xl mx-auto px-6 sm:px-8 flex flex-col sm:flex-row justify-between gap-6">
            <div>
                <p class="font-bold text-sm mb-1" style="color:#2F1B72;">
                    &copy; {{ date('Y') }} Faculty of Business Studies, University of Dhaka. All Rights Reserved.
                </p>
                <p class="text-xs text-gray-400">Executive MBA Program</p>
            </div>
            <div class="text-sm">
                <p class="font-bold mb-1" style="color:#ff0000;">Helpline</p>
                <p class="text-gray-600 text-xs leading-relaxed">
                    bKash Payment Related: <strong>16247</strong><br>
                    Telephone: 58613295, 9661920-73/4360<br>
                    Mobile: +8801850211315, +8801820974731, +8801916931369<br>
                    <span class="text-gray-400">(10 AM – 05 PM, working days only)</span>
                </p>
            </div>
        </div>
    </footer>

    <script>
        const headerToggle = document.getElementById('header-toggle');
        const mobileMenu   = document.getElementById('mobile-menu');
        const iconOpen     = document.getElementById('icon-open');
        const iconClose    = document.getElementById('icon-close');

        headerToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('open');
            iconOpen.classList.toggle('hidden');
            iconClose.classList.toggle('hidden');
        });

        document.getElementById('sub-toggle').addEventListener('click', () => {
            document.getElementById('sub-mobile-menu').classList.toggle('open');
        });
    </script>

    <x-ui.toast />

</body>
</html>
