# NANSAN NET Tools

Laravel 13 + React/TypeScript + Inertia MikroTik workspace. Free, username-based identity and private user/admin areas. **Development build; generators are not published and production is not ready.** See [actual status](docs/IMPLEMENTATION-STATUS.md) and [parity evidence](docs/GENERATOR-PARITY-MATRIX.md).

## Hosting di aaPanel

Ikuti [panduan aaPanel](docs/AAPANEL-HOSTING.md). CI menghasilkan artifact **nansan-hosting-[commit]** berisi ZIP dengan dependency produksi dan frontend siap pakai, checksum, serta hasil instalasi ZIP dari keadaan bersih. Ambil dari run **Checks yang sukses** di GitHub Actions; tidak perlu Node di hosting jika memakai paket ini. Konfigurasi server/secret tetap harus diisi dan diuji. `php artisan nansan:hosting-check --production --runtime` memeriksa prasyarat tanpa mencetak secret. Deployment produksi tetap memerlukan persetujuan pemilik.

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

Current local design preview: http://127.0.0.1:8000 via `Buka Desain NANSAN.cmd` (Windows preview owner uses an ignored DPAPI-protected credential). Old private staging at port 8091 currently returns 502 and its expected ownership marker/current target are missing; do not bypass that guard or treat historical smoke results as current health. Current draft review: https://github.com/nnandika808-ctrl/nansan-net-tools/pull/2.

Static Routing G01 now has an Admin draft at /admin/tools/static-routing/preview. All 47 choices were audited (188 app/ROS/matcher combinations); 92 parameter snapshots cover 46 applications, while both Shopify sources returned 404. Rebuild data only with the owner-authorized workflow: node scripts/collect-static-routing-data.mjs, node scripts/prepare-static-routing-data.mjs, then php scripts/index-static-routing.php. Raw source files are ignored. G01 remains unpublished; see docs/reference/G01-STATIC-ROUTING.md.

All five modules now have Admin-only draft previews: static-routing, queue-qos, pcc, pcc-starlink and static-routing-games at /admin/tools/{slug}/preview. Queue QoS shares the pinned Static Routing artifacts. Routing Games has 106 source-attributed port snapshots; the two malformed source entries are visibly FAILED and blocked. No generator is publicly published.
