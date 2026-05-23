<x-layouts::applicant.guest>

    @if (!$hasActiveBatch)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center" style="background:rgba(15,10,40,0.82); backdrop-filter:blur(4px);">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-8 text-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-5 text-white" style="background:#8b072b;">
                    <x-lucide-history class="size-7" />
                </div>
                <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color:#8b072b;">Admission Closed</p>
                <h2 class="font-inter font-bold text-2xl text-gray-900 mb-3">No Active Admission</h2>
                <p class="text-gray-600 text-sm leading-relaxed mb-6">
                    Applications for the FBS Executive MBA program are not currently open.
                    Please check back later or follow our official channels for announcements about the next batch.
                </p>
                <div class="rounded-xl px-5 py-4 text-sm text-left space-y-1" style="background:#f4f4f8;">
                    <p class="font-semibold text-gray-700">Stay informed:</p>
                    <p class="text-gray-500">Telephone: 58613295, 9661920-73/4360</p>
                    <p class="text-gray-500">Mobile: +8801850211315, +8801820974731</p>
                    <p class="text-gray-400 text-xs mt-1">(10 AM – 05 PM, working days only)</p>
                </div>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 mt-6 px-6 py-2.5 rounded-lg font-bold text-white text-sm transition-opacity hover:opacity-90" style="background:#2F1B72;">
                    <x-lucide-home class="size-3.5" /> Back to Home
                </a>
            </div>
        </div>
    @endif

    <section class="min-h-[calc(100vh-128px)] flex items-center justify-center py-12 px-4" style="background:#f4f4f8;">
        <div class="w-full max-w-md">

            <div class="text-center mb-8">
                <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color:#8b072b;">New Application</p>
                <h1 class="font-inter font-bold text-2xl sm:text-3xl text-gray-900">Create your account</h1>
                <p class="text-gray-500 text-sm mt-2">Start your FBS EMBA application today</p>
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

                <form method="POST" action="{{ route('applicant.register.store') }}" class="space-y-5">
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
                            autocomplete="email"
                            placeholder="email@example.com"
                            class="w-full px-4 py-2.5 rounded-lg border text-sm text-gray-800 focus:outline-none focus:ring-2 transition {{ $errors->has('email') ? 'border-red-400 focus:ring-red-200' : 'border-gray-300 focus:ring-indigo-200 focus:border-indigo-400' }}"
                        >
                    </div>

                    <div>
                        <label for="phone_number" class="block text-sm font-semibold text-gray-700 mb-1">Phone number</label>
                        <input
                            id="phone_number"
                            name="phone_number"
                            type="tel"
                            value="{{ old('phone_number') }}"
                            required
                            autocomplete="tel"
                            placeholder="+880 1XXX-XXXXXX"
                            class="w-full px-4 py-2.5 rounded-lg border text-sm text-gray-800 focus:outline-none focus:ring-2 transition {{ $errors->has('phone_number') ? 'border-red-400 focus:ring-red-200' : 'border-gray-300 focus:ring-indigo-200 focus:border-indigo-400' }}"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="w-full px-4 py-2.5 rounded-lg border text-sm text-gray-800 focus:outline-none focus:ring-2 transition {{ $errors->has('password') ? 'border-red-400 focus:ring-red-200' : 'border-gray-300 focus:ring-indigo-200 focus:border-indigo-400' }}"
                        >
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-1">Confirm password</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="w-full px-4 py-2.5 rounded-lg border text-sm text-gray-800 focus:outline-none focus:ring-2 transition border-gray-300 focus:ring-indigo-200 focus:border-indigo-400"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 rounded-lg font-bold text-white text-sm transition-opacity hover:opacity-90 shadow-md"
                        style="background:#8b072b;"
                    >
                        Create account
                    </button>
                </form>

                <p class="text-center text-sm text-gray-500 mt-6">
                    Already have an account?
                    <a href="{{ route('applicant.login') }}" class="font-semibold hover:underline" style="color:#2F1B72;">Log in</a>
                </p>
            </div>
        </div>
    </section>

</x-layouts::applicant.guest>
