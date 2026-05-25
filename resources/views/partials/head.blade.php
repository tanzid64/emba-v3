<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts


@vite(['resources/css/app.css', 'resources/js/app.js'])

<script>
    // Default first-time visitors to light mode. Once set, the user's choice
    // from the Appearance settings page (light / dark / system) takes over.
    if (!window.localStorage.getItem('flux.appearance.bootstrapped')) {
        window.localStorage.setItem('flux.appearance.bootstrapped', '1');
        if (!window.localStorage.getItem('flux.appearance')) {
            window.localStorage.setItem('flux.appearance', 'light');
        }
    }
</script>
@fluxAppearance
