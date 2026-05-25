<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Project-Specific Rules (ALWAYS APPLY)

### Models & database fields

Before referencing a database field in queries, blade templates, or PHP code:

1. **Open the relevant model and read its `$casts`, `$guarded`, `$appends`, and accessor methods.** Do not assume a column returns a primitive — this project uses custom casts.
2. **Datetime fields use `App\Casts\DateFormatCast`**, which returns an array `['original' => ..., 'humanize' => ..., 'formatted' => ...]`. Never echo a casted timestamp directly with `{{ $model->created_at }}` — it will crash with `htmlspecialchars(): Argument #1 must be of type string, array given`. Use `$model->created_at['formatted']` / `['humanize']`, or parse `['original']` with Carbon if you need a custom format.
3. **Check for accessor methods (`getXAttribute`) and `$appends`** before computing values yourself — e.g., `ApplicantProfile` exposes `photo_url` and `photo_path`; `Application` exposes `is_applied`.
4. **Check enum casts** before treating a field as a string. Many columns (`status`, `payment_status`, `gender`, `blood_group`, `religion`, `marital_status`) cast to backed enums and expose a `->label()` method.
5. **Use `database-schema` (Laravel Boost MCP tool) to confirm column existence and types** before writing migrations, queries, or factories.

### UI components

Before writing any markup for a UI element:

1. **Check `resources/views/components/ui/` first** — the project already has `x-ui.table`, `x-ui.button`, `x-ui.input`, `x-ui.badge`, `x-ui.modal`, `x-ui.toast`, `x-ui.async-select`, and `resources/views/ui/pagination.blade.php`. Reuse them; don't hand-roll a `<table>`, `<button>`, or `<input>`.
2. **Check Flux components next** (`<flux:sidebar>`, `<flux:dropdown>`, `<flux:menu>`, `<flux:profile>`, etc.) — the project uses livewire/flux v2.
3. **Check `resources/views/components/`** for higher-level building blocks (`x-app-logo`, `x-desktop-user-menu`, `x-auth-header`).
4. **Mirror sibling pages for layout patterns** — `resources/views/pages/admin/⚡batches.blade.php`, `⚡applicants.blade.php`, `⚡quick-settings.blade.php` show the page header → toolbar → table card pattern in use. Match it instead of inventing a new one.
5. **If an existing component is close but not quite right, extend it** (e.g., adding a named slot) rather than duplicating it inline.

### Icons

1. **Always use Lucide icons** via the `mallardduck/blade-lucide-icons` package. The `lucide` prefix is configured project-wide.
   - Anonymous component form: `<x-lucide-search class="size-4" />`, `<x-lucide-arrow-right />`.
   - SVG helper inside PHP: `@svg('lucide-search', 'size-4')`.
   - Dynamic name: `<x-dynamic-component :component="'lucide-' . $iconName" />`.
2. **When passing an icon to a component that has an `icon` prop, use the prop — do not nest the icon manually.**
   - Correct: `<x-ui.button icon="plus">Add</x-ui.button>`, `<x-ui.input icon="search" ... />`, `<flux:sidebar.item icon="users" ...>`.
   - Wrong: `<x-ui.button><x-lucide-plus class="size-4" />Add</x-ui.button>` — the `icon` prop already handles sizing, spacing, and color inheritance for its size variant.
3. **Confirm the icon name exists in `vendor/mallardduck/blade-lucide-icons/resources/svg/`** before using it. Lucide uses kebab-case (`graduation-cap`, `arrow-left-from-line`, `chevrons-up-down`). Do not invent names — if unsure, `ls` that folder or check [lucide.dev](https://lucide.dev/icons).
4. **For an icon-only badge** (the colored square in section headers), wrap the icon in a span with explicit `bg-*` and `text-white`: `<span class="inline-flex items-center justify-center size-9 rounded-lg bg-brand text-white shrink-0"><x-lucide-wallet class="size-4" /></span>`. Lucide icons render with `stroke="currentColor"`, so the parent's text color drives the stroke.
5. **Never put bare icon `<span>` blocks outside a card** when the page body is `bg-zinc-100` and the badge is `bg-zinc-700` — contrast is fine, but if you want consistency with sibling sections that *are* in cards, either wrap the header in a `{{ $card }}` div or move it into the `x-ui.table` `toolbar` slot.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest
- Do not write test cases without approval.
- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

## Database Field
- While you using a datetime field always check Model if that casted as DateFormatCast
- Always check for model method or attributes

</laravel-boost-guidelines>
