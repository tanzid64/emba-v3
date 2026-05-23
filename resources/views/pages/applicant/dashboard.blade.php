<x-layouts::applicant.guest>

    <section class="min-h-[calc(100vh-128px)] py-14 px-4" style="background:#f4f4f8;">
        <div class="max-w-5xl mx-auto">

            <div class="mb-8">
                <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#8b072b;">Applicant Portal</p>
                <h1 class="font-inter font-bold text-2xl sm:text-3xl text-gray-900">Application Dashboard</h1>
                <p class="text-gray-500 text-sm mt-1">{{ auth('applicant')->user()->email }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                <div class="bg-white rounded-2xl shadow-md p-7 flex flex-col gap-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-white" style="background:#2F1B72;">
                        <x-lucide-file-text class="size-5" />
                    </div>
                    <h3 class="font-inter font-bold text-gray-800">Application Status</h3>
                    <p class="text-sm text-gray-500">Your application has not been started yet.</p>
                    <span class="inline-block text-xs font-bold uppercase tracking-wide px-3 py-1 rounded-full w-fit" style="background:#f4f4f8; color:#8b072b;">Not Started</span>
                </div>

                <div class="bg-white rounded-2xl shadow-md p-7 flex flex-col gap-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-white" style="background:#58b325;">
                        <x-lucide-mail-check class="size-5" />
                    </div>
                    <h3 class="font-inter font-bold text-gray-800">Email Verified</h3>
                    <p class="text-sm text-gray-500">Your email address has been verified successfully.</p>
                    <span class="inline-block text-xs font-bold uppercase tracking-wide px-3 py-1 rounded-full w-fit" style="background:#f0fde8; color:#3a7e14;">Verified</span>
                </div>

                <div class="bg-white rounded-2xl shadow-md p-7 flex flex-col gap-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-white" style="background:#A27126;">
                        <x-lucide-info class="size-5" />
                    </div>
                    <h3 class="font-inter font-bold text-gray-800">Admission Circular</h3>
                    <p class="text-sm text-gray-500">FBS EMBA 8th Batch applications are now open.</p>
                    <a href="#" class="text-xs font-bold hover:underline" style="color:#2F1B72;">View Circular &rarr;</a>
                </div>

            </div>

            <div class="mt-6 bg-white rounded-2xl shadow-md p-7 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="font-inter font-bold text-gray-800 mb-1">Ready to begin your application?</h3>
                    <p class="text-sm text-gray-500">Complete your profile and submit your application documents.</p>
                </div>
                <a href="#" class="shrink-0 inline-flex items-center gap-2 px-6 py-3 rounded-lg font-bold text-white text-sm transition-opacity hover:opacity-90 shadow-md" style="background:#8b072b;">
                    Start Application <x-lucide-arrow-right class="size-4" />
                </a>
            </div>

            <div class="mt-6 text-right">
                <form method="POST" action="{{ route('applicant.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-400 hover:text-gray-600 transition-colors">
                        <x-lucide-log-out class="size-4" /> Log out
                    </button>
                </form>
            </div>

        </div>
    </section>

</x-layouts::applicant.guest>
