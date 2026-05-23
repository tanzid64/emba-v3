<x-layouts::auth :title="__('Applicant Login')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your application')" :description="__('Enter your email and password to access your application portal')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('applicant.login.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                <flux:link class="absolute top-0 text-sm end-0" :href="route('applicant.password.request')" wire:navigate>
                    {{ __('Forgot your password?') }}
                </flux:link>
            </div>

            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full">
                {{ __('Log in') }}
            </flux:button>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __("Don't have an account?") }}</span>
            <flux:link :href="route('applicant.register')" wire:navigate>{{ __('Apply now') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
