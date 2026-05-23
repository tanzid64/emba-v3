<x-layouts::applicant.guest>

    <section class="min-h-[calc(100vh-128px)] flex items-center justify-center py-12 px-4" style="background:#f4f4f8;">
        <div class="w-full max-w-md">

            <div class="text-center mb-8">
                <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color:#8b072b;">Account Verification</p>
                <h1 class="font-inter font-bold text-2xl sm:text-3xl text-gray-900">Verify your email</h1>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8 text-center">

                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-5 text-white" style="background:#2F1B72;">
                    <x-lucide-mail class="size-7" />
                </div>

                <p class="text-gray-600 text-sm leading-relaxed mb-4">
                    Please verify your email address by clicking the link we just emailed to you.
                </p>

                @if (session('status') === 'verification-link-sent')
                    <div class="mb-5 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-4 py-3">
                        A new verification link has been sent to your email address.
                    </div>
                @endif

                <div class="space-y-3 mt-6">
                    <form method="POST" action="{{ route('applicant.verification.send') }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full py-3 rounded-lg font-bold text-white text-sm transition-opacity hover:opacity-90 shadow-md"
                            style="background:#2F1B72;"
                        >
                            Resend verification email
                        </button>
                    </form>

                    <form method="POST" action="{{ route('applicant.logout') }}">
                        @csrf
                        <button type="submit" class="w-full py-2 text-sm font-semibold text-gray-500 hover:text-gray-700 transition-colors">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

</x-layouts::applicant.guest>
