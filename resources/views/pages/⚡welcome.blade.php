<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('FBS EMBA Admission — University of Dhaka')]
#[Layout('layouts.applicant.guest')]
class extends Component {
}; ?>

<div>

    {{-- HERO --}}
    <section class="hero-bg relative min-h-[520px] md:min-h-[580px] flex items-center">
        <div class="absolute inset-0 bg-black/55"></div>
        <div class="relative max-w-7xl mx-auto px-6 sm:px-8 py-20 w-full">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-widest mb-4" style="color:#A27126;">Faculty of Business Studies, University of Dhaka</p>
                <h1 class="font-inter font-bold text-white leading-tight mb-4 text-3xl sm:text-4xl md:text-5xl">
                    The MBA you need as your next career step.
                </h1>
                <h2 class="text-lg sm:text-xl text-gray-300 mb-10 font-medium">
                    The business degree that Bangladesh trusts.
                </h2>
                <div class="flex flex-wrap gap-4">
                    <a href="{{ route('applicant.register') }}" class="inline-flex items-center gap-2 px-7 py-3 rounded font-bold text-white text-sm transition-opacity hover:opacity-90 shadow-lg" style="background:#2F1B72;">
                        Apply Now <x-lucide-arrow-right class="size-3.5" />
                    </a>
                    <a href="#" target="_blank" class="inline-flex items-center gap-2 px-7 py-3 rounded font-bold text-white text-sm transition-opacity hover:opacity-90 shadow-lg" style="background:#da3129;">
                        View Circular <x-lucide-arrow-right class="size-3.5" />
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
                    <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color:#8b072b;">About the Program</p>
                    <h2 class="font-inter font-bold text-3xl sm:text-4xl text-gray-900 leading-tight mb-6">
                        Who the FBS EMBA is really for
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-6 text-base sm:text-lg">
                        The FBS EMBA at the Faculty of Business Studies, University of Dhaka, is <strong>NOT</strong> just for executives.
                        It's a flexible, evening-time MBA designed for fresh graduates and early-career professionals who want to:
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3">
                            <span class="mt-1 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-white" style="background:#58b325;">
                                <x-lucide-check class="size-3" />
                            </span>
                            <span class="text-gray-700">Strengthen employability and gain practical business skills</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-white" style="background:#58b325;">
                                <x-lucide-check class="size-3" />
                            </span>
                            <span class="text-gray-700">Switch from technical or general backgrounds into business roles</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-white" style="background:#58b325;">
                                <x-lucide-check class="size-3" />
                            </span>
                            <span class="text-gray-700">Earn the credibility of Dhaka University's most respected business degree</span>
                        </li>
                    </ul>
                </div>
                <div class="rounded-2xl overflow-hidden shadow-2xl">
                    <img src="{{ asset('assets/images/FBS-Banner.jpg') }}" alt="FBS Campus" class="w-full h-80 lg:h-96 object-cover">
                </div>
            </div>
        </div>
    </section>

    {{-- SECTION 2 — Find your starting point --}}
    <section class="py-20" style="background:#f4f4f8;">
        <div class="max-w-7xl mx-auto px-6 sm:px-8">
            <div class="text-center max-w-3xl mx-auto mb-12">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color:#8b072b;">Diversity of Backgrounds</p>
                <h2 class="font-inter font-bold text-3xl sm:text-4xl text-gray-900 leading-tight mb-5">
                    Find your starting point —<br class="hidden sm:block"> We have a place for every field of undergraduate studies!
                </h2>
                <p class="text-gray-600 leading-relaxed">
                    At FBS EMBA, we take pride in our diversity of student backgrounds. From Engineers to Liberal Arts &amp; Social Science
                    Graduates to Medical graduates who want to lead large institutions, we attract future leaders from every field.
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
                    <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color:#8b072b;">Our Strengths</p>
                    <h2 class="font-inter font-bold text-3xl sm:text-4xl text-gray-900 leading-tight mb-5">
                        Why do the FBS EMBA?
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        From Engineers to Liberal Arts &amp; Social Science Graduates to Medical graduates who want to lead large institutions,
                        we attract future leaders from every field.
                    </p>
                </div>
                <div class="lg:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-5">
                    @php
                        $reasons = [
                            ['icon' => 'graduation-cap', 'label' => 'Dhaka University Prestige'],
                            ['icon' => 'clock',          'label' => 'Evening Flexibility'],
                            ['icon' => 'briefcase',      'label' => 'Career Transition Focus'],
                            ['icon' => 'heart',          'label' => 'Strong Alumni Network'],
                            ['icon' => 'layers',         'label' => 'Case-Based, Practical Learning'],
                        ];
                    @endphp
                    @foreach ($reasons as $r)
                        <div class="flex flex-col items-center text-center p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300" style="background:#fafafa;">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4 text-white shadow-md" style="background:#8b072b;">
                                @svg('lucide-' . $r['icon'], 'size-6')
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
                        ['title' => 'Class Timings', 'body' => 'Evening &amp; weekend sessions designed for working professionals.'],
                        ['title' => 'Format',        'body' => 'Regular MBA curriculum, real-world cases, and applied decision-making.'],
                        ['title' => 'Faculty',       'body' => 'Senior academics &amp; industry practitioners with deep expertise.'],
                        ['title' => 'Peer Mix',      'body' => 'Engineers, managers, entrepreneurs, and fresh graduates.'],
                    ];
                @endphp
                @foreach ($glance as $g)
                    <div class="rounded-2xl p-7 text-center text-white flex flex-col items-center hover:brightness-110 transition-all duration-300 shadow-lg" style="background:#8b072b;">
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
            <div class="rounded-2xl px-8 py-12 flex flex-col sm:flex-row items-center justify-between gap-8 shadow-xl" style="background:#2F1B72;">
                <div>
                    <h3 class="font-inter font-bold text-2xl sm:text-3xl text-white mb-2">
                        Ready for the next step in your career?
                    </h3>
                    <p class="text-white/70 text-base">Applications for FBS EMBA (8th Batch) are now open.</p>
                </div>
                <a href="{{ route('applicant.register') }}" class="shrink-0 inline-flex items-center gap-2 px-8 py-4 rounded-xl font-bold text-white text-base transition-opacity hover:opacity-90 shadow-lg" style="background:#8b072b;">
                    Apply Now <x-lucide-arrow-right class="size-4" />
                </a>
            </div>
        </div>
    </section>

</div>
