@props([
    'position' => 'top-right',
])

@php
    $positionClasses = match ($position) {
        'top-left' => 'top-4 left-4',
        'bottom-right' => 'bottom-4 right-4',
        'bottom-left' => 'bottom-4 left-4',
        default => 'top-4 right-4',
    };
@endphp

@php
    $flash = session('toast');
@endphp

<div
    x-data="emba_toaster()"
    x-init="init(@js($flash))"
    class="fixed {{ $positionClasses }} z-[9999] flex flex-col gap-3 max-w-sm w-full pointer-events-none"
    aria-live="polite"
>
    <template x-for="t in items" :key="t.id">
        <div
            x-show="t.visible"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 sm:translate-x-2 sm:translate-y-0"
            x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-4"
            class="pointer-events-auto rounded-xl shadow-lg ring-1 ring-black/5 bg-white overflow-hidden"
        >
            <div class="flex items-start gap-3 p-4">
                <span
                    class="shrink-0 w-9 h-9 rounded-lg inline-flex items-center justify-center text-white"
                    :class="iconBg(t.variant)"
                >
                    <template x-if="t.variant === 'success'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </template>
                    <template x-if="t.variant === 'danger' || t.variant === 'error'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </template>
                    <template x-if="t.variant === 'warning'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    </template>
                    <template x-if="t.variant === 'info'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25 12 12m0 0 .75.75M12 12l-.75.75M12 12l.75-.75m9 .75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </template>
                </span>

                <div class="flex-1 min-w-0 pt-0.5">
                    <p x-show="t.title" x-text="t.title" class="font-inter font-semibold text-sm text-gray-900"></p>
                    <p x-text="t.message" class="text-sm text-gray-600 leading-snug"></p>
                </div>

                <button
                    type="button"
                    @click="dismiss(t.id)"
                    class="shrink-0 -mr-1 -mt-1 p-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                    aria-label="Dismiss"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div
                class="h-1 transition-[width] ease-linear"
                :class="iconBg(t.variant)"
                :style="`width: ${t.progress}%`"
            ></div>
        </div>
    </template>
</div>

@once
    <script>
        window.toast = window.toast || function (payload, variant = 'info') {
            const detail = typeof payload === 'string'
                ? { message: payload, variant }
                : payload;
            window.dispatchEvent(new CustomEvent('toast', { detail }));
        };

        // Bridge Livewire 'toast' events to a single window CustomEvent.
        // Registering only once prevents stacking duplicate handlers on
        // every Livewire re-render or page transition.
        if (!window._embaLwToastBridge) {
            window._embaLwToastBridge = true;
            document.addEventListener('livewire:init', () => {
                window.Livewire.on('toast', (payload) => {
                    const data = Array.isArray(payload) ? payload[0] : payload;
                    window.dispatchEvent(new CustomEvent('toast', { detail: data || {} }));
                });
            });
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('emba_toaster', () => ({
                items: [],
                nextId: 1,
                lastSignature: null,
                lastSignatureAt: 0,

                init(flash = null) {
                    if (this._bound) return;
                    this._bound = true;

                    window.addEventListener('toast', (e) => this.add(e.detail || {}));

                    if (flash) this.add(flash);
                },

                normalize(payload) {
                    return {
                        variant: payload.variant || payload.type || 'info',
                        message: payload.message ?? payload.text ?? '',
                        title:   payload.title || null,
                        duration: payload.duration ?? 4000,
                    };
                },

                add(payload) {
                    const t = this.normalize(payload || {});
                    if (!t.message) return;

                    // Drop a duplicate (same message+variant) fired within 500ms —
                    // defends against repeat events from multiple listeners.
                    const signature = `${t.variant}::${t.message}`;
                    const now = Date.now();
                    if (signature === this.lastSignature && now - this.lastSignatureAt < 500) {
                        return;
                    }
                    this.lastSignature = signature;
                    this.lastSignatureAt = now;

                    const id = this.nextId++;
                    const entry = {
                        id,
                        variant: t.variant,
                        title: t.title,
                        message: t.message,
                        visible: true,
                        progress: 100,
                        duration: t.duration,
                        timer: null,
                        ticker: null,
                    };

                    this.items.push(entry);

                    if (t.duration > 0) {
                        const start = Date.now();
                        entry.ticker = setInterval(() => {
                            const elapsed = Date.now() - start;
                            entry.progress = Math.max(0, 100 - (elapsed / t.duration) * 100);
                        }, 60);
                        entry.timer = setTimeout(() => this.dismiss(id), t.duration);
                    }
                },

                dismiss(id) {
                    const t = this.items.find((i) => i.id === id);
                    if (!t) return;
                    if (t.timer) clearTimeout(t.timer);
                    if (t.ticker) clearInterval(t.ticker);
                    t.visible = false;
                    setTimeout(() => {
                        this.items = this.items.filter((i) => i.id !== id);
                    }, 200);
                },

                iconBg(variant) {
                    switch (variant) {
                        case 'success': return 'bg-emerald-500';
                        case 'danger':
                        case 'error':   return 'bg-red-500';
                        case 'warning': return 'bg-amber-500';
                        default:        return 'bg-indigo-500';
                    }
                },
            }));
        });
    </script>
@endonce
