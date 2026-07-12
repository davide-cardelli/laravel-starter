# AGENTS.md

Machine-readable guide for AI agents (and humans) working in this repository.
Everything below is enforced by tooling — treat it as binding, not advisory.

## Stack

- **Backend**: Laravel 13, PHP 8.3/8.4, Laravel Fortify (auth + 2FA), spatie/laravel-permission 8.
- **Frontend**: Inertia 3, Vue 3.5 + TypeScript, Vite 8 (Rolldown), Tailwind 4, reka-ui, Laravel Wayfinder (type-safe routes).
- **Data**: PostgreSQL via Laravel Sail (Docker).
- **Quality**: PHPStan level 9 (larastan), Deptrac, Laravel Pint, ESLint, Prettier, Pest 4.

## Architecture: Action-Based, enforced by Deptrac

Controllers are **thin**. All business logic lives in single-purpose **Actions**
(`app/Actions/**`). Role/permission names are **backed enums**
(`App\Enums\Role`, `App\Enums\Permission`) — never magic strings.

Layer dependencies are enforced by `deptrac.yaml`. A layer may only depend on
the layers listed:

| Layer         | Directory                   | May depend on                                  |
| ------------- | --------------------------- | ---------------------------------------------- |
| `Enums`       | `app/Enums`                 | nothing (leaf: domain primitives)              |
| `Models`      | `app/Models`                | Enums                                           |
| `Policies`    | `app/Policies`              | Models, Enums                                   |
| `Actions`     | `app/Actions`               | Models, Enums                                   |
| `Requests`    | `app/Http/Requests`         | Models, Enums                                   |
| `Controllers` | `app/Http/Controllers`      | Actions, Models, Requests, Policies, Enums      |
| `Jobs`        | `app/Jobs`                  | Actions, Models                                 |
| `Middleware`  | `app/Http/Middleware`       | Models, Enums                                   |

Rules of thumb:

- New business logic → a new Action, not controller code.
- New role/permission → add a case to `App\Enums\Role` / `App\Enums\Permission`
  and wire it in `RolePermissionSeeder` (the single source of truth for seeding).
- Authorization → `UserPolicy` + `#[Authorize]` attributes on controller methods.
- Do not loosen `deptrac.yaml`. Adding a new leaf layer to model a legitimate
  dependency is fine; relaxing an existing rule is not.

## Type safety

- **PHP**: PHPStan **level 9** (`composer analyse`). No new baseline entries, no
  `@phpstan-ignore`. Every file declares `strict_types=1` (enforced by an arch
  test).
- **Frontend**: `vue-tsc` strict type-check (`npm run type-check`). Route helpers
  come from Wayfinder (`@/routes`, `@/actions`) — do not hardcode URLs.

## Testing (Pest 4)

- Suites: `tests/Unit`, `tests/Feature`, `tests/Browser`.
- **Browser tests** use Pest 4 browser testing (Playwright), not Dusk. They drive
  a real headless browser against an in-process server and run **inside Sail**.
- Architecture tests (`tests/Unit/ArchTest.php`) pin conventions: strict types,
  no debug helpers (`dd`/`dump`/`ray`) in `app/`, layer boundaries, string-backed
  enums.
- Coverage must stay **≥ 80%** (`composer test:coverage`, non-browser suites).

## Quality gates — run before every commit

Tests run **only inside Sail** (ports 80/8080 are taken locally; the app port is
`8090`). If containers are down: `APP_PORT=8090 vendor/bin/sail up -d`.

```bash
vendor/bin/sail composer test          # Unit + Feature + Browser (Playwright required)
composer test:coverage                 # coverage ≥ 80 (run with a coverage driver in Sail)
composer analyse                       # PHPStan level 9
composer deptrac                       # architecture — zero violations
composer format:test                   # Pint
npm run check                          # vue-tsc + ESLint + Prettier
npm run build && npm run build:ssr     # client + SSR bundles
```

**Wayfinder caveat**: `resources/js/routes` and `resources/js/actions` are
generated from the **database schema** and are gitignored. Regenerate them from
a **migrated** database before type-checking or building, or route types degrade
to `string` and `npm run check` fails:

```bash
vendor/bin/sail artisan migrate
vendor/bin/sail artisan wayfinder:generate --with-form
```

`composer setup` bootstraps everything (env, key, migrate+seed, npm install,
Playwright browsers, Wayfinder, build, git hooks). Git hooks live in `.githooks`
(`core.hooksPath`): pre-commit checks formatting of staged files; pre-push runs
the full gate.

## Laravel Boost (optional dev tooling)

`laravel/boost` (dev dependency) provides an MCP server and a Laravel docs API
for AI agents. To enable it for your editor, run the interactive installer:

```bash
php artisan boost:install
```

Boost regenerates its own artifacts (`.mcp.json`, `boost.json`, and generated
guideline files); this hand-maintained `AGENTS.md` is the project's source of
truth and is committed intentionally.
