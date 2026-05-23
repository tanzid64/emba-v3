<!-- lerd:begin -->
## Lerd — Laravel Local Dev Environment

This project runs on **lerd**, a Podman-based Laravel development environment. The `lerd` MCP server is available — use it to manage the environment without leaving the chat.

### Architecture

- PHP runs in Podman containers named `lerd-php<version>-fpm` (e.g. `lerd-php84-fpm`); each container includes composer and node/npm; the PHP version is resolved from `.lerd.yaml` → `.php-version` → `composer.json` `require.php` constraint (matched against installed versions) → global default
- Nginx routes `*.test` domains to the correct PHP-FPM container
- Services (MySQL, Redis, PostgreSQL, etc.) and custom services run as Podman containers via systemd quadlets
- Node.js versions are managed by fnm; per-project version is set via a `.node-version` file
- Framework workers (queue, schedule, reverb, horizon, messenger, vite, etc.) run as systemd user services named `lerd-<worker>-<sitename>`; commands are defined per-framework in YAML; Laravel Horizon is auto-detected from `composer.json` and replaces the queue toggle when installed; Laravel ships with a `vite` host worker that runs `npm run dev` on the host via fnm for HMR; workers and setup commands support optional `check` (`file` or `composer`) for conditional visibility; workers with `conflicts_with` auto-stop conflicting workers on start. Per-worker flags: `host: true` (run on host via fnm instead of in FPM container — HMR-sensitive Node tools), `per_worktree: true` (worker runs independently per worktree under `lerd-<worker>-<site>-<branch>`), `replaces_build: true` (worker provides asset manifest while running, so `lerd worktree add` skips the static `npm run build` step when this worker is opted in)
- Custom workers can be added per-project (`.lerd.yaml` `custom_workers`) or globally (`~/.config/lerd/frameworks/<name>.yaml`); use `worker_add` / `worker_remove` — both survive framework store updates
- Framework setup commands (one-off bootstrap steps like migrations, storage links) are defined in the framework YAML and shown in `lerd setup`; Laravel has built-in storage:link/migrate/db:seed; custom frameworks can define their own
- Service version placeholders (`{{mysql_version}}`, `{{postgres_version}}`, `{{redis_version}}`, `{{meilisearch_version}}`) are available in framework env vars and are resolved from the service image tag at `lerd env` time
- **Custom containers**: non-PHP sites (Node.js, Python, Go, etc.) can define a `Containerfile.lerd` and a `container:` section in `.lerd.yaml` with a port; lerd builds a per-project image, runs it as `lerd-custom-<sitename>`, and nginx reverse-proxies to it; the project directory is volume-mounted at its host path with `--workdir` set automatically — do NOT add `WORKDIR` or `COPY` to the Containerfile; workers exec into the custom container; services are accessible by name on the shared `lerd` Podman network; **hot-reload file watchers must use polling on macOS** (inotify does not fire across Podman Machine's virtiofs mount) — nodemon: `--legacy-watch`, Vite: `server.watch.usePolling: true`, webpack: `watchOptions: { poll: 1000 }`
- Git worktrees automatically get a `<branch>.<site>.test` subdomain (with deep `*.<branch>.<site>.test` wildcard cert + nginx `server_name` on secured sites); `vendor/`, `node_modules/`, and `.env` are populated from the main checkout. `.lerd.yaml` `env_overrides` declares templated env vars (placeholders `{{domain}}`, `{{scheme}}`, `{{site}}`, plus plain strings) layered on top of the default `APP_URL` rewrite — useful for multi-tenant apps with per-branch session cookies, signed-URL hosts, or tenant routing

### DNS modes

Lerd has two install-time DNS modes recorded in `~/.config/lerd/config.yaml`:
- **Managed (default)**: `dns.enabled: true`, `dns.tld: test`. Sites at `*.test` via lerd-dns + mkcert; `site_tls` works.
- **Disabled**: `dns.enabled: false`, `dns.tld: localhost`. Sites at `*.localhost` via RFC 6761; no mkcert CA, `site_tls` is unavailable.

Read `status()` for `dns.tld` and `dns.enabled` instead of assuming `.test`; do not propose `site_tls` when `dns.enabled` is false.

### Available MCP tools

| Tool | What it does |
|------|-------------|
| `sites` | List all registered sites with framework and worker status — call this first |
| `runtime_versions` | List installed PHP and Node.js versions with defaults |
| `php_list` | List installed PHP versions, marking the global default |
| `php_ext` | Manage custom PHP extensions — `action`: `list` / `add` / `remove`; `add` and `remove` rebuild FPM image and restart container; `add` verifies the extension loaded and accepts `apk_deps` for extra Alpine build packages |
| `artisan` | Run `php artisan` inside the PHP-FPM container (Laravel only) |
| `console` | Run the framework's console command (e.g. `php bin/console` for Symfony) — non-Laravel frameworks with a `console` field |
| `composer` | Run `composer` inside the PHP-FPM container |
| `vendor_bins` | List composer-installed binaries available in the project's `vendor/bin` directory |
| `vendor_run` | Run a binary from `vendor/bin` (pest, phpunit, pint, phpstan, rector, …) inside the PHP-FPM container |
| `node` | Install or uninstall a Node.js version via fnm — `action`: `install` / `uninstall` (e.g. `"20"`, `"lts"`) |
| `env_setup` | Configure `.env` for lerd: detects services, starts them, creates DB, generates APP_KEY (leaves `DB_CONNECTION=sqlite` alone — call `db_set` first); `APP_URL` follows `.lerd.yaml app_url` → `sites.yaml app_url` → default chain |
| `db_set` | Pick the database for a Laravel project: `sqlite` / `mysql` / `postgres`; persists to `.lerd.yaml`, rewrites `DB_` keys in `.env`, starts the service, creates the database |
| `env_check` | Compare all `.env` files against `.env.example` — returns structured JSON with per-key sync status |
| `site_link` | Register a directory as a lerd site — **non-PHP projects** must have a Containerfile (default name `Containerfile.lerd`; set `container.containerfile` for a different path, e.g. `Dockerfile`) + `.lerd.yaml` with `container: {port: N}` written first, otherwise the site registers as PHP (wrong) |
| `site_unlink` | Unregister a site and remove its nginx vhost (all domains) |
| `site_domain` | Add or remove a site domain (without TLD) — `action`: `add` / `remove`; cannot remove last |
| `park` | Register a parent directory — auto-registers all PHP projects as sites |
| `unpark` | Remove a parked directory and unlink all its sites |
| `site_tls` | Enable or disable HTTPS for a site (mkcert) — `action`: `enable` / `disable`; updates APP_URL automatically |
| `xdebug` | Manage Xdebug for a PHP version (port 9003) — `action`: `on` / `off` / `status`; optional `mode` on `on` (default `debug`; also `coverage`, `develop`, `profile`, `trace`, `gcstats`, or comma combos) |
| `dumps_recent` / `dumps_status` / `dumps_clear` / `dumps_toggle` | Inspect / clear / toggle the lerd dump bridge that captures `dump()` / `dd()` calls. Off by default; enable with `dumps_toggle({enable: true})` |
| `service_control` | Start, stop, pin, or unpin a built-in or custom service — `action`: `start` / `stop` / `pin` / `unpin` |
| `service_add` | Register a new custom OCI service (MongoDB, RabbitMQ, …); supports `depends_on` for service dependencies |
| `service_preset_list` | List bundled service presets (phpmyadmin, pgadmin, mongo, mongo-express, selenium, stripe-mock, …) with versions and install state |
| `service_preset_install` | Install a bundled preset by name (`version` for multi-version families); becomes a normal custom service |
| `service_remove` | Stop and deregister a custom service |
| `service_expose` | Add or remove an extra published port on a built-in service (persisted) |
| `service_env` | Return the recommended `.env` connection variables for a service |
| `db_export` | Export a database to a SQL dump file — auto-detects service and database; accepts optional `service` override |
| `db_import` | Import a SQL dump file into the project database — auto-detects service and database; starts the service if needed |
| `db_create` | Create a database and `_testing` variant — auto-detects service and name; starts the service if needed |
| `queue` | Start or stop the queue worker for a site — `action`: `start` / `stop` (any framework with a queue worker) |
| `horizon` | Start or stop Laravel Horizon for a site — `action`: `start` / `stop` (use instead of `queue` when laravel/horizon is installed) |
| `reverb` | Start or stop the Reverb WebSocket server for a site — `action`: `start` / `stop` |
| `schedule` | Start or stop the task scheduler for a site — `action`: `start` / `stop` |
| `worker` | Start or stop any named framework worker (e.g. messenger, pulse, vite) — `action`: `start` / `stop`; pass `branch` to target a per-worktree unit |
| `worker_list` | List all workers for a site's framework with running status, host/per_worktree/replaces_build flags; pass `branch` for per-worktree unit state |
| `worker_add` | Add a custom worker to a project or global framework overlay |
| `worker_remove` | Remove a custom worker; stops it if running |
| `worktree` | Manage git worktrees — `action`: `list` / `add` / `remove` / `db_isolate` / `db_share`; secured sites get auto wildcard cert + `server_name` for `*.<branch>.<site>.test` |
| `project_new` | Scaffold a new PHP project (runs the framework's create command); follow with `site_link` + `env_setup` |
| `framework_list` | List all framework definitions with their workers and setup commands |
| `framework_add` | Add or update a framework definition; use `name: "laravel"` to add custom workers or setup commands to Laravel |
| `framework_remove` | Remove a user-defined framework; for laravel removes only custom worker and setup additions |
| `site_php` | Change PHP version for a site — writes `.php-version`, updates registry, regenerates nginx vhost; pass `branch` to pin per-worktree (writes inside the worktree, persists to its `.lerd.yaml`) |
| `site_node` | Change Node.js version for a site — writes `.node-version`, installs via fnm if needed; pass `branch` to pin per-worktree |
| `workers_mode` | Show or set the macOS worker runtime mode (exec / container); no-op on Linux |
| `bug_report` | Generate a diagnostic report for GitHub issues — anonymises site names / domains / parked paths by default; returns the file path |
| `site_control` | Pause, unpause, restart, or rebuild a site — `action`: `pause` / `unpause` / `restart` / `rebuild` (pause replaces vhost with landing page; rebuild only for custom containers) |
| `site_runtime` | Switch between shared PHP-FPM and per-site FrankenPHP runtime (supports worker mode) |
| `stripe` | Start or stop a Stripe webhook listener for a site — `action`: `start` / `stop` |
| `logs` | Fetch container logs — defaults to current site's FPM; optionally specify nginx, service name, PHP version, or site name |
| `status` | Health snapshot of DNS, nginx, PHP-FPM containers, and the file watcher |
| `doctor` | Full diagnostic as structured JSON: podman, systemd, DNS, ports, PHP images, config, updates |
| `which` | Show resolved PHP version, Node version, document root, and nginx config for the current site |
| `check` | Validate `.lerd.yaml` as structured JSON — PHP version, services, framework references with per-field ok/warn/fail |

### Key conventions

- `path` argument is optional on most tools — defaults to the directory the AI assistant was opened in (cwd), then `LERD_SITE_PATH` if set; you can almost always omit it
- `artisan` is Laravel-only; `console` is the equivalent for non-Laravel frameworks — both take `path` (absolute project root) and `args` (array)
- `vendor_run` is the right way to invoke project tooling like pest, phpunit, pint, phpstan, rector — call `vendor_bins` first to discover what's installed, then `vendor_run(bin: "<name>", args: [...])`; prefer it over `composer(args: ["exec", ...])`
- On a **fresh Laravel clone** (DB_CONNECTION=sqlite in `.env`), call `db_set(database: "mysql"|"postgres"|"sqlite")` before `env_setup` to pick a database deliberately. `env_setup` on its own won't switch the database away from sqlite.
- **Domain conflicts on link**: when `lerd link` (or the parked-directory watcher) tries to register a `.lerd.yaml` domain that another site already owns, the conflicting domain is filtered out and a `[WARN] domain "X" already used by site "Y" — skipped` line is printed. The site still gets registered with surviving domains, falling back to `<dirname>.<tld>` if everything was filtered. `.lerd.yaml` is not modified on disk so the conflict is visible in the UI and self-heals on the next link if the owning site is removed. The `site_link` and `site_domain(action: "add", ...)` MCP tools, by contrast, hard-error on conflicts so you can react explicitly — read the error message for the owning site name.
- **Custom APP_URL**: `env_setup` writes `<scheme>://<primary-domain>` by default. Override by setting `app_url` in `.lerd.yaml` (committed) or in the per-machine `sites.yaml` site entry. No MCP tool sets it — edit the YAML and re-run `env_setup`.
- `tinker` must use `--execute=<code>` for non-interactive use
- Built-in service hosts follow the pattern `lerd-<name>` (e.g. `lerd-mysql`, `lerd-redis`)
- Default DB credentials: username `root`, password `lerd`
- `service_control(action: "stop", ...)` marks the service paused — `lerd start` skips it until explicitly started again
- `queue(action: "start", ...)` requires Redis to be running when `QUEUE_CONNECTION=redis`; call `service_control(action: "start", name: "redis")` first
- If `sites` returns `has_horizon: true` for a site, use `horizon` instead of `queue` — Horizon manages queues and they are mutually exclusive
- Use `worker_list` first to discover what workers are available for a site before calling `worker(action: "start", ...)`
- `worker_add` saves custom workers to `.lerd.yaml` by default (project-level, committed to git); use `global: true` to save to the user framework overlay (`~/.config/lerd/frameworks/`) for all projects of that framework; does not auto-start — call `worker(action: "start", ...)` afterwards
- `worker_remove` stops a running worker before removing it from config; use `global: true` to target the framework overlay
- Workers with `conflicts_with` automatically stop conflicting workers when started (e.g. a custom queue processor that conflicts with the default queue worker); conflicted workers are hidden from the UI while the conflicting worker runs
- Worker unit names follow the pattern `lerd-<worker>-<site>` (e.g. `lerd-messenger-myapp`, `lerd-horizon-myapp`)
- `site_php` / `site_node` change the PHP/Node version for a site; the FPM container for the new PHP version must be running after calling `site_php`
- `site_control(action: "pause")` / `site_control(action: "unpause")` free up resources for sites not in active use without unlinking them; paused state persists across restarts
- **Custom container sites** (Node.js, Python, Go, etc.) — mandatory sequence: **(1)** write a Containerfile in the project root (default name `Containerfile.lerd`; any name works if you set `container.containerfile`); **(2)** write `.lerd.yaml` with `container: {port: <N>}` (plus optional `domains`, `services`, `secured`) — there is no MCP tool for this; write the file directly or ask the user to run `lerd init`; **(3)** configure the project's `.env` (or equivalent config) with service connection strings BEFORE linking — use `lerd-mysql`, `lerd-redis`, `lerd-postgres` as hostnames and start needed services with `service_control(action: "start", ...)`; **(4)** call `site_link` — the container starts immediately, so the env must already be correct. **Never call `site_link` before steps 1–3**: without `container:` config the site registers as PHP-FPM (wrong); if that happened, `site_unlink` first, write the files, then link again. Workers in `custom_workers` exec into the container. `site_control(action: "restart", ...)` restarts without rebuilding. When `container` is set, `php_version` and `framework` are ignored.
- `service_control(action: "pin", ...)` keeps a service always running regardless of which sites are active; use for shared services like MySQL or Redis
- `service_add` supports `depends_on` (array of service names): starting a dependency auto-starts the dependent service; stopping a dependency cascade-stops the dependent first; starting the dependent ensures dependencies start first
- Prefer `service_preset_install` over hand-rolling `service_add` for anything in the bundled catalogue (`phpmyadmin`, `pgadmin`, `mongo`, `mongo-express`, `selenium`, `stripe-mock`, `mysql`, `mariadb`, …) — presets ship sane defaults, dependency wiring, dashboards, and rendered config files; call `service_preset_list` first to see what's available; multi-version families take a `version` argument; presets whose dependency is another custom service (e.g. `mongo-express` on `mongo`) require the dep installed first
- `project_new` requires an absolute `path` and runs the framework's `create` command; follow it with `site_link` + `env_setup` to register and configure the new project
- `framework_add` accepts `workers` (map) and `setup` (array) — both support an optional `check` field (`{file}` or `{composer}`) to conditionally show based on project deps; for Laravel, custom setup commands replace built-in storage:link/migrate/db:seed
- Framework env vars support service version placeholders: `{{mysql_version}}`, `{{postgres_version}}`, `{{redis_version}}`, `{{meilisearch_version}}` — resolved from the running service image tag
- `php_ext(action: "add", ...)` / `php_ext(action: "remove", ...)` rebuild the FPM image and restart the container — may take a minute; `version` defaults to the project or global PHP version
- `db_import` / `db_export` / `db_create` auto-detect service and database via: `service` arg → framework definition detect rules → `DB_CONNECTION` / `DB_TYPE` / `TYPEORM_CONNECTION` / `DATABASE_URL` / `DB_PORT`; pass `service` explicitly for projects with no env config
- `db_create` always creates both `<name>` and `<name>_testing` databases; safe to call if they already exist; starts the service automatically if not running
- `park` auto-registers all PHP subdirectories as sites in one call; `unpark` removes them all — project files are NOT deleted

<!-- lerd:end -->
