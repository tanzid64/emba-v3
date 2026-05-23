<x-layouts::applicant.app :title="__('Dashboard')">

    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#8b072b;">Applicant Portal</p>
        <h1 class="font-inter font-bold text-2xl text-gray-900">Application Dashboard</h1>
        <p class="text-gray-400 text-sm mt-1">Welcome back, {{ auth('applicant')->user()->email }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col gap-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white" style="background:#2F1B72;">
                <x-lucide-file-text class="size-5" />
            </div>
            <h3 class="font-inter font-bold text-gray-800">Application Status</h3>
            <p class="text-sm text-gray-500">Your application has not been started yet.</p>
            <span class="inline-block text-xs font-bold uppercase tracking-wide px-3 py-1 rounded-full w-fit" style="background:#f4f4f8; color:#8b072b;">Not Started</span>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col gap-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white" style="background:#58b325;">
                <x-lucide-mail-check class="size-5" />
            </div>
            <h3 class="font-inter font-bold text-gray-800">Email Verified</h3>
            @php $verifiedAt = auth('applicant')->user()->email_verified_at; @endphp
            @if ($verifiedAt)
                <p class="text-sm text-gray-500">{{ $verifiedAt['formatted'] }}</p>
                <span class="inline-block text-xs font-bold uppercase tracking-wide px-3 py-1 rounded-full w-fit" style="background:#f0fde8; color:#3a7e14;">Verified</span>
            @else
                <p class="text-sm text-gray-500">Your email has not been verified yet.</p>
                <span class="inline-block text-xs font-bold uppercase tracking-wide px-3 py-1 rounded-full w-fit" style="background:#fef3c7; color:#92400e;">Pending</span>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col gap-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white" style="background:#A27126;">
                <x-lucide-info class="size-5" />
            </div>
            <h3 class="font-inter font-bold text-gray-800">Admission Circular</h3>
            <p class="text-sm text-gray-500">FBS EMBA 8th Batch applications are now open.</p>
            <a href="#" class="text-xs font-bold hover:underline" style="color:#2F1B72;">View Circular &rarr;</a>
        </div>

    </div>

    <div class="mt-5 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h3 class="font-inter font-bold text-gray-800 mb-1">Ready to begin your application?</h3>
            <p class="text-sm text-gray-500">Complete your profile and submit your application documents.</p>
        </div>
        <a href="#" class="shrink-0 inline-flex items-center gap-2 px-6 py-2.5 rounded-lg font-bold text-white text-sm transition-opacity hover:opacity-90" style="background:#8b072b;">
            Start Application <x-lucide-arrow-right class="size-4" />
        </a>
    </div>

</x-layouts::applicant.app>
