<x-layouts::app :title="__('Application Dashboard')">
    <div class="flex flex-col gap-6 p-6">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 :text-zinc-100">{{ __('Welcome back') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ auth('applicant')->user()->email }}</p>
        </div>
    </div>
</x-layouts::app>
