<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Technical Documentation')]
#[Layout('layouts.app')]
class extends Component {
    /** @return array<int, array{id: string, label: string, icon: string}> */
    public function sections(): array
    {
        return [
            ['id' => 'overview', 'label' => __('Overview'), 'icon' => 'info'],
            ['id' => 'stack', 'label' => __('Tech stack'), 'icon' => 'layers'],
            ['id' => 'architecture', 'label' => __('Architecture'), 'icon' => 'workflow'],
            ['id' => 'domain', 'label' => __('Domain model'), 'icon' => 'database'],
            ['id' => 'auth', 'label' => __('Authentication'), 'icon' => 'shield-check'],
            ['id' => 'payments', 'label' => __('Payments'), 'icon' => 'credit-card'],
            ['id' => 'frontend', 'label' => __('Frontend'), 'icon' => 'palette'],
            ['id' => 'tooling', 'label' => __('Tooling & testing'), 'icon' => 'wrench'],
            ['id' => 'commands', 'label' => __('Common commands'), 'icon' => 'terminal'],
        ];
    }
}; ?>

@php
    $sectionHeading = 'flex items-center gap-2 text-base font-bold text-zinc-900 dark:text-zinc-100 mb-3';
    $kbd = 'inline-flex items-center rounded-md border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 text-[11px] font-mono font-semibold text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200';
    $code = 'rounded-md border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 text-[12px] font-mono text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200';
    $card = 'rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900';
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('Technical Documentation') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Architecture, tech stack, and conventions used in :app.', ['app' => config('app.name')]) }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-ui.badge size="sm" color="zinc">{{ __('Laravel') }} {{ app()->version() }}</x-ui.badge>
            <x-ui.badge size="sm" color="zinc">PHP {{ PHP_VERSION }}</x-ui.badge>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">

        {{-- Sticky TOC --}}
        <aside class="col-span-12 lg:col-span-3">
            <nav class="sticky top-6 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="px-2 pt-1 pb-2 text-[10px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                    {{ __('On this page') }}
                </p>
                <ul class="space-y-0.5">
                    @foreach ($this->sections() as $section)
                        <li>
                            <a
                                href="#{{ $section['id'] }}"
                                class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                            >
                                <x-dynamic-component :component="'lucide-' . $section['icon']" class="size-4 text-zinc-400" />
                                <span>{{ $section['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </aside>

        <div class="col-span-12 space-y-6 lg:col-span-9">

            {{-- Overview --}}
            <section id="overview" class="{{ $card }} scroll-mt-6">
                <h2 class="{{ $sectionHeading }}">
                    <x-lucide-info class="size-5 text-brand" />
                    {{ __('Overview') }}
                </h2>
                <div class="space-y-3 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                    <p>
                        {{ config('app.name') }} {{ __('is the third generation of the EMBA admission platform — a Laravel-based system that handles the full lifecycle of an admission cycle: batches, applicants, applications, payments, and result publication.') }}
                    </p>
                    <p>
                        {{ __('The application is split into two surfaces: a public/applicant surface (registration, profile, application form, payment) and an admin surface (batch management, settings, applicants review, results).') }}
                    </p>
                </div>
            </section>

            {{-- Tech stack --}}
            <section id="stack" class="{{ $card }} scroll-mt-6">
                <h2 class="{{ $sectionHeading }}">
                    <x-lucide-layers class="size-5 text-brand" />
                    {{ __('Tech stack') }}
                </h2>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {{-- Backend --}}
                    <div class="rounded-lg border border-zinc-100 p-4 dark:border-zinc-800">
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Backend') }}</p>
                        <ul class="space-y-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <li><span class="font-semibold">PHP</span> 8.4</li>
                            <li><span class="font-semibold">Laravel</span> 13</li>
                            <li><span class="font-semibold">Laravel Fortify</span> 1 <span class="text-xs text-zinc-500">— auth backend</span></li>
                            <li><span class="font-semibold">Laravel Tinker</span> 3</li>
                            <li><span class="font-semibold">Laravel Chisel</span> — page-based Livewire routing</li>
                            <li><span class="font-semibold">MySQL</span> {{ __('(primary)') }} / SQLite {{ __('(testing)') }}</li>
                        </ul>
                    </div>

                    {{-- Frontend --}}
                    <div class="rounded-lg border border-zinc-100 p-4 dark:border-zinc-800">
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Frontend') }}</p>
                        <ul class="space-y-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <li><span class="font-semibold">Livewire</span> 4 <span class="text-xs text-zinc-500">— single-file components</span></li>
                            <li><span class="font-semibold">Flux UI</span> 2 (free)</li>
                            <li><span class="font-semibold">Alpine.js</span> {{ __('— bundled with Livewire') }}</li>
                            <li><span class="font-semibold">Tailwind CSS</span> 4</li>
                            <li><span class="font-semibold">Vite</span> 8</li>
                            <li><span class="font-semibold">Lucide icons</span> <span class="text-xs text-zinc-500">— via blade-lucide-icons</span></li>
                        </ul>
                    </div>

                    {{-- Dev tooling --}}
                    <div class="rounded-lg border border-zinc-100 p-4 dark:border-zinc-800">
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Tooling') }}</p>
                        <ul class="space-y-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <li><span class="font-semibold">Pest</span> 4 + PHPUnit 12</li>
                            <li><span class="font-semibold">Laravel Pint</span> {{ __('— code formatter') }}</li>
                            <li><span class="font-semibold">Laravel Pail</span> {{ __('— log tail') }}</li>
                            <li><span class="font-semibold">Laravel Boost</span> {{ __('— MCP server') }}</li>
                            <li><span class="font-semibold">Laravel Sail</span> {{ __('— optional Docker dev') }}</li>
                        </ul>
                    </div>

                    {{-- Integrations --}}
                    <div class="rounded-lg border border-zinc-100 p-4 dark:border-zinc-800">
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Integrations') }}</p>
                        <ul class="space-y-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <li><span class="font-semibold">bKash</span> {{ __('— payment gateway (tokenised)') }}</li>
                            <li><span class="font-semibold">@laravel/passkeys</span> {{ __('— WebAuthn support') }}</li>
                            <li><span class="font-semibold">2FA</span> {{ __('— Fortify TOTP') }}</li>
                        </ul>
                    </div>
                </div>
            </section>

            {{-- Architecture --}}
            <section id="architecture" class="{{ $card }} scroll-mt-6">
                <h2 class="{{ $sectionHeading }}">
                    <x-lucide-workflow class="size-5 text-brand" />
                    {{ __('Architecture') }}
                </h2>

                <div class="space-y-4 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                    <p>
                        {{ __('Pages live as single-file Livewire components under') }}
                        <span class="{{ $code }}">resources/views/pages/</span>.
                        {{ __('Each file colocates its PHP class, state, and Blade markup. Routes are defined explicitly in') }}
                        <span class="{{ $code }}">routes/web.php</span>
                        {{ __('using') }}
                        <span class="{{ $code }}">Route::livewire(...)</span>.
                    </p>

                    <div class="rounded-lg border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Directory layout') }}</p>
                        <pre class="overflow-x-auto text-[12px] font-mono leading-relaxed text-zinc-700 dark:text-zinc-300"><code>app/
├── Actions/Fortify/      {{ __('# Fortify customisations (CreateNewUser, …)') }}
├── Casts/                {{ __('# Custom Eloquent casts') }}
├── Concerns/             {{ __('# Reusable trait-style validation rules') }}
├── Enum/ + Enums/        {{ __('# BatchStatus, PaymentStatus, Gender, …') }}
├── Http/                 {{ __('# Controllers, middleware, requests') }}
├── Livewire/Actions/     {{ __('# Cross-component action classes') }}
├── Models/               {{ __('# Eloquent models') }}
├── Notifications/        {{ __('# Mail / database notifications') }}
├── Providers/            {{ __('# Service providers, FortifyServiceProvider') }}
└── Support/              {{ __('# CurrentBatch, Toast, etc.') }}

resources/views/
├── components/ui/        {{ __('# Reusable Blade UI primitives (button, table, badge…)') }}
├── layouts/app/          {{ __('# App + sidebar layouts') }}
└── pages/                {{ __('# Livewire SFC pages — filename prefixed with ⚡') }}
    ├── admin/
    ├── applicant/
    ├── auth/
    └── settings/</code></pre>
                    </div>

                    <p>
                        {{ __('Cross-cutting state — the currently active admission batch — is held in a tiny support class') }}
                        <span class="{{ $code }}">App\Support\CurrentBatch</span>
                        {{ __('and persisted in session. The sidebar batch switcher writes to it via the') }}
                        <span class="{{ $code }}">admin.current-batch.set</span>
                        {{ __('route.') }}
                    </p>
                </div>
            </section>

            {{-- Domain model --}}
            <section id="domain" class="{{ $card }} scroll-mt-6">
                <h2 class="{{ $sectionHeading }}">
                    <x-lucide-database class="size-5 text-brand" />
                    {{ __('Domain model') }}
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-zinc-200 text-left dark:border-zinc-700">
                            <tr>
                                <th class="px-3 py-2 font-semibold text-zinc-700 dark:text-zinc-200">{{ __('Model') }}</th>
                                <th class="px-3 py-2 font-semibold text-zinc-700 dark:text-zinc-200">{{ __('Purpose') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">Batch</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('An admission cycle (year, code, status: draft/open/closed).') }}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">AdmissionSetting</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Per-batch schedule and fees — intake window, exam/viva dates, fee structure.') }}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">Applicant</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('A registered prospective student. Separate guard from admin users.') }}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">ApplicantProfile</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Personal/contact details. Locked once an application is submitted.') }}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">Application</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Applicant → Batch submission with status workflow.') }}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">EducationHistory / ExpHistory</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Education and work-experience entries on an applicant profile.') }}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">Address</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Polymorphic address with district/upazila lookup tables.') }}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">Payment</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Polymorphic payment record (actor-based owner) for application / enrollment / admission fees.') }}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">BkashToken</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Cached bKash gateway access/refresh tokens.') }}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">User</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Admin/staff account — Fortify-backed.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Auth --}}
            <section id="auth" class="{{ $card }} scroll-mt-6">
                <h2 class="{{ $sectionHeading }}">
                    <x-lucide-shield-check class="size-5 text-brand" />
                    {{ __('Authentication') }}
                </h2>
                <div class="space-y-3 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                    <p>
                        {{ __('Auth is powered by Laravel Fortify. Admin auth routes are scoped under') }}
                        <span class="{{ $code }}">/admin</span>{{ __('. The system supports:') }}
                    </p>
                    <ul class="ml-5 list-disc space-y-1.5">
                        <li>{{ __('Email + password login with Fortify') }}</li>
                        <li>{{ __('Two-factor authentication (TOTP, recovery codes, QR enrolment)') }}</li>
                        <li>{{ __('Passkeys / WebAuthn via @laravel/passkeys') }}</li>
                        <li>{{ __('Password reset & email verification flows') }}</li>
                        <li>{{ __('Separate applicant guard with its own password reset table') }}</li>
                    </ul>
                </div>
            </section>

            {{-- Payments --}}
            <section id="payments" class="{{ $card }} scroll-mt-6">
                <h2 class="{{ $sectionHeading }}">
                    <x-lucide-credit-card class="size-5 text-brand" />
                    {{ __('Payments') }}
                </h2>
                <div class="space-y-3 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                    <p>
                        {{ __('Payments are stored in a single polymorphic') }}
                        <span class="{{ $code }}">payments</span>
                        {{ __('table keyed by an actor enum (applicant / admin) and method enum (bKash, manual, …).') }}
                        {{ __('Status transitions are driven by') }}
                        <span class="{{ $code }}">PaymentStatusEnum</span>.
                    </p>
                    <p>
                        {{ __('bKash access/refresh tokens are persisted in the') }}
                        <span class="{{ $code }}">bkash_tokens</span>
                        {{ __('table so token refreshes are shared across workers and survive deploys.') }}
                    </p>
                </div>
            </section>

            {{-- Frontend --}}
            <section id="frontend" class="{{ $card }} scroll-mt-6">
                <h2 class="{{ $sectionHeading }}">
                    <x-lucide-palette class="size-5 text-brand" />
                    {{ __('Frontend') }}
                </h2>
                <div class="space-y-3 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                    <p>
                        {{ __('All UI is server-rendered Blade + Livewire 4. Interactivity is added with') }}
                        <span class="{{ $code }}">wire:*</span>
                        {{ __('directives and small Alpine.js islands. Flux UI provides the component primitives (') }}<span class="{{ $code }}">flux:sidebar</span>, <span class="{{ $code }}">flux:dropdown</span>, <span class="{{ $code }}">flux:menu</span>{{ __(', etc.).') }}
                    </p>
                    <p>
                        {{ __('Project-specific UI primitives live under') }}
                        <span class="{{ $code }}">resources/views/components/ui/</span>
                        ({{ __(':list).', ['list' => 'button, badge, table, toast']) }}
                        {{ __('Always check that directory before creating a new component.') }}
                    </p>
                    <p>
                        {{ __('Tailwind v4 is used via') }}
                        <span class="{{ $code }}">@tailwindcss/vite</span>;
                        {{ __('the design token') }}
                        <span class="{{ $code }}">brand</span>
                        {{ __('is the primary accent across the admin and applicant surfaces.') }}
                    </p>
                </div>
            </section>

            {{-- Tooling & testing --}}
            <section id="tooling" class="{{ $card }} scroll-mt-6">
                <h2 class="{{ $sectionHeading }}">
                    <x-lucide-wrench class="size-5 text-brand" />
                    {{ __('Tooling & testing') }}
                </h2>
                <div class="space-y-3 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                    <ul class="ml-5 list-disc space-y-1.5">
                        <li>
                            <span class="font-semibold">{{ __('Tests') }}:</span>
                            {{ __('Pest 4 — feature tests under') }}
                            <span class="{{ $code }}">tests/Feature</span>,
                            {{ __('unit tests under') }}
                            <span class="{{ $code }}">tests/Unit</span>.
                            {{ __('Every change must be covered by a test.') }}
                        </li>
                        <li>
                            <span class="font-semibold">{{ __('Formatting') }}:</span>
                            {{ __('Run') }}
                            <span class="{{ $code }}">vendor/bin/pint --dirty --format agent</span>
                            {{ __('before committing PHP changes.') }}
                        </li>
                        <li>
                            <span class="font-semibold">{{ __('Logs') }}:</span>
                            <span class="{{ $code }}">php artisan pail</span>
                            {{ __('streams application logs in the dev shell.') }}
                        </li>
                        <li>
                            <span class="font-semibold">{{ __('Boost MCP') }}:</span>
                            {{ __('database schema, query, doc search, browser logs — exposed to AI tooling.') }}
                        </li>
                    </ul>
                </div>
            </section>

            {{-- Commands --}}
            <section id="commands" class="{{ $card }} scroll-mt-6">
                <h2 class="{{ $sectionHeading }}">
                    <x-lucide-terminal class="size-5 text-brand" />
                    {{ __('Common commands') }}
                </h2>
                <div class="space-y-3 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                    <div class="overflow-hidden rounded-lg border border-zinc-100 dark:border-zinc-800">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                <tr>
                                    <td class="w-2/5 px-3 py-2"><code class="{{ $code }}">composer run dev</code></td>
                                    <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Run server + queue + pail + vite concurrently.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2"><code class="{{ $code }}">npm run dev</code></td>
                                    <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Vite dev server only.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2"><code class="{{ $code }}">npm run build</code></td>
                                    <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Production asset build.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2"><code class="{{ $code }}">php artisan test --compact</code></td>
                                    <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Run the Pest test suite.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2"><code class="{{ $code }}">php artisan migrate</code></td>
                                    <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Apply pending migrations.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2"><code class="{{ $code }}">php artisan route:list --except-vendor</code></td>
                                    <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Inspect application routes.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2"><code class="{{ $code }}">vendor/bin/pint --dirty</code></td>
                                    <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ __('Format changed PHP files.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
