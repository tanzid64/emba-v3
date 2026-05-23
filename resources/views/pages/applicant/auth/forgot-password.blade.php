<x-layouts::applicant.guest>

    <section class="min-h-[calc(100vh-128px)] flex items-center justify-center py-12 px-4" style="background:#f4f4f8;">
        <div class="w-full max-w-md">

            <div class="text-center mb-8">
                <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color:#8b072b;">Password Recovery</p>
                <h1 class="font-inter font-bold text-2xl sm:text-3xl text-gray-900">Forgot your password?</h1>
                <p class="text-gray-500 text-sm mt-2">Enter your email to receive a reset link</p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8">

                @if (session('status'))
                    <div class="mb-6 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-4 py-3">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('applicant.password.email') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">Email address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            placeholder="email@example.com"
                            class="w-full px-4 py-2.5 rounded-lg border text-sm text-gray-800 focus:outline-none focus:ring-2 transition {{ $errors->has('email') ? 'border-red-400 focus:ring-red-200' : 'border-gray-300 focus:ring-indigo-200 focus:border-indigo-400' }}"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 rounded-lg font-bold text-white text-sm transition-opacity hover:opacity-90 shadow-md"
                        style="background:#2F1B72;"
                    >
                        Send reset link
                    </button>
                </form>

                <p class="text-center text-sm text-gray-500 mt-6">
                    Remember your password?
                    <a href="{{ route('applicant.login') }}" class="font-semibold hover:underline" style="color:#2F1B72;">Log in</a>
                </p>
            </div>
        </div>
    </section>

</x-layouts::applicant.guest>
