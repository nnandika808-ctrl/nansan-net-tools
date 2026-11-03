# NANSAN NET Tools

Laravel 13 + React/TypeScript + Inertia MikroTik workspace. Free, username-based identity and private user/admin areas. **Development build; generators are not published and production is not ready.** See [actual status](docs/IMPLEMENTATION-STATUS.md) and [parity evidence](docs/GENERATOR-PARITY-MATRIX.md).

## Local setup

Requirements: PHP 8.4+ with Laravel/SQLite/MySQL/GD extensions, Composer 2, Node 22.12+, npm. On this Windows workspace PHP is available at `.runtime/tools/php/php.exe`; `scripts/bootstrap-tools.ps1` downloads checksum-verified official PHP/Composer for local use.

```sh
composer install
npm ci
node scripts/setup-local.mjs
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Local setup creates `.env` only if absent and uses SQLite/file cache for development. `.env.example` contains MySQL/Redis/secure production defaults with no credentials. Never deploy the local environment. Create a real Admin via `php artisan nansan:admin your_username` (hidden password prompt); there is no seeded Admin/password.

```sh
php artisan test
npm test
npm run typecheck
npm run lint
npm run build
npm run test:e2e
```

E2E uses a separate `.runtime/e2e.sqlite` database/server. Browser authentication state, local DBs, `.env`, uploads and logs are ignored by Git. Start reference automation with powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-reference-browser.ps1. It enforces isolated memory-only browser state and disables automatic snapshots; the persistent bridge blocks inspection/screenshots on visible authentication forms. Complete Premium login manually. Stop only the owned audit session with scripts/stop-reference-browser.ps1. Never export authentication state.

Read [PRD](docs/PRD.md), [architecture](docs/ARCHITECTURE.md), [database](docs/DATABASE.md), [security](docs/SECURITY.md), [testing](docs/TESTING.md), [deployment/rollback](docs/DEPLOYMENT.md), and the [reference audit](docs/reference/BUANANET-AUDIT.md). No automatic router execution is implemented. Production deployment requires explicit owner approval.

Private staging: http://127.0.0.1:8091/login while the SSH forward is running. Admin username: nansan. Its random password is protected by Windows DPAPI in ignored runtime storage. Run powershell -NoProfile -ExecutionPolicy Bypass -File scripts/staging-admin-access.ps1 -CopyPassword to open login and copy the password without printing it. See deployment documentation for external-service blockers. Draft review: https://github.com/nnandika808-ctrl/nansan-net-tools/pull/1.

Static Routing G01 now has an Admin draft at /admin/tools/static-routing/preview. All 47 choices were audited (188 app/ROS/matcher combinations); 92 parameter snapshots cover 46 applications, while both Shopify sources returned 404. Rebuild data only with the owner-authorized workflow: node scripts/collect-static-routing-data.mjs, node scripts/prepare-static-routing-data.mjs, then php scripts/index-static-routing.php. Raw source files are ignored. G01 remains unpublished; see docs/reference/G01-STATIC-ROUTING.md.
