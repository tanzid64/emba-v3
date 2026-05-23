<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FBS EMBA Admission — University of Dhaka</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600&display=swap"
        rel="stylesheet">
    <script src="https://kit.fontawesome.com/93e59bacec.js" crossorigin="anonymous"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
        }

        .font-inter {
            font-family: 'Inter', sans-serif;
        }

        .hero-bg {
            background: url('/assets/images/FBS-Banner.jpg') center center / cover no-repeat;
        }

        #mobile-menu {
            display: none;
        }

        #mobile-menu.open {
            display: block;
        }

        #sub-mobile-menu {
            display: none;
        }

        #sub-mobile-menu.open {
            display: block;
        }
    </style>
</head>

<body class="bg-white text-gray-700 antialiased">

    {{-- ===================== TOP HEADER ===================== --}}
    <header class="fixed top-0 inset-x-0 z-50 bg-white shadow-md" style="height:80px;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-full flex items-center justify-between">

            {{-- Logo + Title --}}
            <a href="/" class="flex items-center gap-3 shrink-0">
                <img src="{{ asset('assets/logo/logo.jpg') }}" alt="University of Dhaka" class="h-16 w-auto">
                <div class="hidden sm:block leading-tight">
                    <p class="font-inter font-bold text-lg text-black">Executive MBA Program</p>
                    <p class="font-inter text-sm font-semibold" style="color:#A27126;">Faculty of Business Studies,
                        University of Dhaka</p>
                </div>
            </a>

            {{-- Desktop right --}}
            <div class="hidden md:flex items-center gap-3">
                <a href="#"
                    class="px-5 py-2 rounded text-sm font-bold text-white transition-opacity hover:opacity-90"
                    style="background:#2F1B72;">
                    Apply Now
                </a>
                <a href="#"
                    class="px-5 py-2 rounded text-sm font-semibold border-2 transition-colors hover:bg-gray-50"
                    style="border-color:#2F1B72; color:#2F1B72;">
                    Student Login
                </a>
                <a href="#"
                    class="px-5 py-2 rounded text-sm font-semibold border-2 transition-colors hover:bg-gray-50"
                    style="border-color:#2F1B72; color:#2F1B72;">
                    Admin Login
                </a>
            </div>

            {{-- Mobile hamburger --}}
            <button id="header-toggle" class="md:hidden p-2 rounded focus:outline-none" style="color:#2F1B72;"
                aria-label="Toggle menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path id="icon-open" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    <path id="icon-close" class="hidden" stroke-linecap="round" stroke-linejoin="round"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Mobile dropdown --}}
        <div id="mobile-menu" class="md:hidden bg-white border-t border-gray-100 shadow-lg px-4 py-3 space-y-2">
            <a href="#" class="block text-center py-2 rounded font-bold text-white text-sm"
                style="background:#2F1B72;">Apply Now</a>
            <a href="#" class="block text-center py-2 rounded font-semibold text-sm border-2"
                style="border-color:#2F1B72; color:#2F1B72;">Student Login</a>
            <a href="#" class="block text-center py-2 rounded font-semibold text-sm border-2"
                style="border-color:#2F1B72; color:#2F1B72;">Admin Login</a>
        </div>
    </header>

    {{-- ===================== SUB NAV ===================== --}}
    <nav class="fixed inset-x-0 z-40 shadow-sm" style="top:80px; background:#2F1B72;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between">
                {{-- Desktop links --}}
                <ul class="hidden md:flex items-center">
                    <li><a href="/"
                            class="block px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors">Home</a>
                    </li>
                    <li><a href="#"
                            class="block px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors">Eligibility</a>
                    </li>
                    <li><a href="#"
                            class="block px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors">Instruction
                            &amp; Guideline</a></li>
                    <li><a href="#"
                            class="block px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors">Make
                            Payment</a></li>
                    <li><a href="#"
                            class="block px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors">App
                            ID Recovery</a></li>
                </ul>
                <ul class="hidden md:flex items-center">
                    <li><a href="#"
                            class="flex items-center gap-1 px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors"><i
                                class="fa-solid fa-right-to-bracket text-xs"></i> Apply</a></li>
                    <li><a href="#"
                            class="flex items-center gap-1 px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors"><i
                                class="fa-solid fa-user text-xs"></i> Student</a></li>
                    <li><a href="#"
                            class="flex items-center gap-1 px-4 py-3 text-sm font-bold text-white hover:bg-white/10 transition-colors"><i
                                class="fa-solid fa-lock text-xs"></i> Admin</a></li>
                </ul>

                {{-- Mobile sub-nav hamburger --}}
                <div class="md:hidden flex items-center justify-between w-full py-1">
                    <span class="text-white text-sm font-bold">Navigation</span>
                    <button id="sub-toggle" class="p-2 text-white" aria-label="Toggle sub menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Mobile sub-nav dropdown --}}
            <div id="sub-mobile-menu" class="md:hidden pb-2 space-y-1">
                <a href="/"
                    class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Home</a>
                <a href="#"
                    class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Eligibility</a>
                <a href="#"
                    class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Instruction &amp;
                    Guideline</a>
                <a href="#" class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Make
                    Payment</a>
                <a href="#" class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">App
                    ID Recovery</a>
                <a href="#"
                    class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Apply</a>
                <a href="#"
                    class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Student Login</a>
                <a href="#" class="block px-3 py-2 text-sm font-bold text-white hover:bg-white/10 rounded">Admin
                    Login</a>
            </div>
        </div>
    </nav>

    {{-- ===================== MAIN ===================== --}}
    <main class="pt-[128px]">

        {{-- HERO --}}
        <section class="hero-bg relative min-h-[520px] md:min-h-[580px] flex items-center">
            <div class="absolute inset-0 bg-black/55"></div>
            <div class="relative max-w-7xl mx-auto px-6 sm:px-8 py-20 w-full">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-widest mb-4" style="color:#A27126;">Faculty of
                        Business Studies, University of Dhaka</p>
                    <h1 class="font-inter font-bold text-white leading-tight mb-4 text-3xl sm:text-4xl md:text-5xl">
                        The MBA you need as your next career step.
                    </h1>
                    <h2 class="text-lg sm:text-xl text-gray-300 mb-10 font-medium">
                        The business degree that Bangladesh trusts.
                    </h2>
                    <div class="flex flex-wrap gap-4">
                        <a href="#"
                            class="inline-flex items-center gap-2 px-7 py-3 rounded font-bold text-white text-sm transition-opacity hover:opacity-90 shadow-lg"
                            style="background:#2F1B72;">
                            Apply Now <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                        <a href="#" target="_blank"
                            class="inline-flex items-center gap-2 px-7 py-3 rounded font-bold text-white text-sm transition-opacity hover:opacity-90 shadow-lg"
                            style="background:#da3129;">
                            View Circular <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- SECTION 1 — Who the FBS EMBA is for --}}
        <section class="bg-white py-20">
            <div class="max-w-7xl mx-auto px-6 sm:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-14 items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color:#8b072b;">About the
                            Program</p>
                        <h2 class="font-inter font-bold text-3xl sm:text-4xl text-gray-900 leading-tight mb-6">
                            Who the FBS EMBA is really for
                        </h2>
                        <p class="text-gray-600 leading-relaxed mb-6 text-base sm:text-lg">
                            The FBS EMBA at the Faculty of Business Studies, University of Dhaka, is
                            <strong>NOT</strong> just for executives.
                            It's a flexible, evening-time MBA designed for fresh graduates and early-career
                            professionals who want to:
                        </p>
                        <ul class="space-y-4">
                            <li class="flex items-start gap-3">
                                <span
                                    class="mt-1 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-white text-xs"
                                    style="background:#58b325;">
                                    <i class="fa-solid fa-check" style="font-size:9px;"></i>
                                </span>
                                <span class="text-gray-700">Strengthen employability and gain practical business
                                    skills</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span
                                    class="mt-1 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-white text-xs"
                                    style="background:#58b325;">
                                    <i class="fa-solid fa-check" style="font-size:9px;"></i>
                                </span>
                                <span class="text-gray-700">Switch from technical or general backgrounds into business
                                    roles</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span
                                    class="mt-1 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-white text-xs"
                                    style="background:#58b325;">
                                    <i class="fa-solid fa-check" style="font-size:9px;"></i>
                                </span>
                                <span class="text-gray-700">Earn the credibility of Dhaka University's most respected
                                    business degree</span>
                            </li>
                        </ul>
                    </div>
                    <div class="rounded-2xl overflow-hidden shadow-2xl">
                        <img src="{{ asset('assets/images/FBS-Banner.jpg') }}" alt="FBS Campus"
                            class="w-full h-80 lg:h-96 object-cover">
                    </div>
                </div>
            </div>
        </section>

        {{-- SECTION 2 — Find your starting point --}}
        <section class="py-20" style="background:#f4f4f8;">
            <div class="max-w-7xl mx-auto px-6 sm:px-8">
                <div class="text-center max-w-3xl mx-auto mb-12">
                    <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color:#8b072b;">Diversity of
                        Backgrounds</p>
                    <h2 class="font-inter font-bold text-3xl sm:text-4xl text-gray-900 leading-tight mb-5">
                        Find your starting point —<br class="hidden sm:block"> We have a place for every field of
                        undergraduate studies!
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        At FBS EMBA, we take pride in our diversity of student backgrounds. From Engineers to Liberal
                        Arts &amp; Social Science
                        Graduates to Medical graduates who want to lead large institutions, we attract future leaders
                        from every field.
                    </p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="rounded-2xl shadow-md overflow-hidden">
                        <img src="{{ asset('assets/images/2nd.png') }}" alt="" class="w-full h-auto block">
                    </div>
                    <div class="rounded-2xl shadow-md overflow-hidden">
                        <img src="{{ asset('assets/images/3.png') }}" alt="" class="w-full h-auto block">
                    </div>
                    <div class="rounded-2xl shadow-md overflow-hidden">
                        <img src="{{ asset('assets/images/4.png') }}" alt="" class="w-full h-auto block">
                    </div>
                    <div class="rounded-2xl shadow-md overflow-hidden">
                        <img src="{{ asset('assets/images/1st.jpg') }}" alt="" class="w-full h-auto block">
                    </div>
                </div>
            </div>
        </section>

        {{-- SECTION 3 — Why FBS EMBA --}}
        <section class="bg-white py-20">
            <div class="max-w-7xl mx-auto px-6 sm:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-14 items-start">
                    <div class="lg:col-span-1">
                        <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color:#8b072b;">Our
                            Strengths</p>
                        <h2 class="font-inter font-bold text-3xl sm:text-4xl text-gray-900 leading-tight mb-5">
                            Why do the FBS EMBA?
                        </h2>
                        <p class="text-gray-600 leading-relaxed">
                            From Engineers to Liberal Arts &amp; Social Science Graduates to Medical graduates who want
                            to lead large institutions,
                            we attract future leaders from every field.
                        </p>
                    </div>
                    <div class="lg:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-5">
                        @php
                            $reasons = [
                                ['icon' => 'fa-graduation-cap', 'label' => 'Dhaka University Prestige'],
                                ['icon' => 'fa-clock', 'label' => 'Evening Flexibility'],
                                ['icon' => 'fa-suitcase', 'label' => 'Career Transition Focus'],
                                ['icon' => 'fa-heart', 'label' => 'Strong Alumni Network'],
                                ['icon' => 'fa-layer-group', 'label' => 'Case-Based, Practical Learning'],
                            ];
                        @endphp
                        @foreach ($reasons as $r)
                            <div class="flex flex-col items-center text-center p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300"
                                style="background:#fafafa;">
                                <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4 text-white text-2xl shadow-md"
                                    style="background:#8b072b;">
                                    <i class="fa-solid {{ $r['icon'] }}"></i>
                                </div>
                                <h5 class="font-semibold text-gray-800 text-sm leading-snug">{{ $r['label'] }}</h5>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- SECTION 4 — Program at a glance --}}
        <section class="py-20" style="background:#0c1d6b;">
            <div class="max-w-7xl mx-auto px-6 sm:px-8">
                <div class="text-center mb-12">
                    <p class="text-xs font-bold uppercase tracking-widest mb-3 text-white/60">Quick Overview</p>
                    <h2 class="font-inter font-bold text-3xl sm:text-4xl text-white leading-tight">
                        Program at a glance
                    </h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    @php
                        $glance = [
                            [
                                'title' => 'Class Timings',
                                'body' => 'Evening &amp; weekend sessions designed for working professionals.',
                            ],
                            [
                                'title' => 'Format',
                                'body' => 'Regular MBA curriculum, real-world cases, and applied decision-making.',
                            ],
                            [
                                'title' => 'Faculty',
                                'body' => 'Senior academics &amp; industry practitioners with deep expertise.',
                            ],
                            [
                                'title' => 'Peer Mix',
                                'body' => 'Engineers, managers, entrepreneurs, and fresh graduates.',
                            ],
                        ];
                    @endphp
                    @foreach ($glance as $g)
                        <div class="rounded-2xl p-7 text-center text-white flex flex-col items-center hover:brightness-110 transition-all duration-300 shadow-lg"
                            style="background:#8b072b;">
                            <h4 class="font-inter font-bold text-xl tracking-wide mb-3">{{ $g['title'] }}</h4>
                            <p class="text-white/80 text-sm leading-relaxed">{!! $g['body'] !!}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- CTA --}}
        <section class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-6 sm:px-8">
                <div class="rounded-2xl px-8 py-12 flex flex-col sm:flex-row items-center justify-between gap-8 shadow-xl"
                    style="background:#2F1B72;">
                    <div>
                        <h3 class="font-inter font-bold text-2xl sm:text-3xl text-white mb-2">
                            Ready for the next step in your career?
                        </h3>
                        <p class="text-white/70 text-base">Applications for FBS EMBA (8th Batch) are now open.</p>
                    </div>
                    <a href="#"
                        class="shrink-0 inline-flex items-center gap-2 px-8 py-4 rounded-xl font-bold text-white text-base transition-opacity hover:opacity-90 shadow-lg"
                        style="background:#8b072b;">
                        Apply Now <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </section>

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
            <div class="text-sm" style="color:#ff0000;">
                <p class="font-bold mb-1">Helpline</p>
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
        const mobileMenu = document.getElementById('mobile-menu');
        const iconOpen = document.getElementById('icon-open');
        const iconClose = document.getElementById('icon-close');

        headerToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('open');
            iconOpen.classList.toggle('hidden');
            iconClose.classList.toggle('hidden');
        });

        document.getElementById('sub-toggle').addEventListener('click', () => {
            document.getElementById('sub-mobile-menu').classList.toggle('open');
        });
    </script>

</body>

</html>
