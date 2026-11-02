# Testing

Run php artisan test, npm test, npm run typecheck, npm run lint, npm run build and npm run test:e2e. Windows PHP is .runtime/tools/php/php.exe; use npm.cmd if PowerShell script policy blocks npm.ps1.

Latest backend: **45 tests, 219 assertions passed**. Frontend: **6 component/form tests passed**; TypeScript, ESLint and production build passed. Browser suite: 12 passed, including SEO/FAQ, validated templates and English content. GitHub CI run 41514788703 passed PHP 8.4, frontend checks, isolated MySQL/Redis integration, Chromium E2E and dependency audits. New changes require their own CI result.

Backend tests use isolated SQLite; CI additionally runs PlatformTest on its disposable MySQL service. Browser tests reset only .runtime/e2e.sqlite at 127.0.0.1:8001 after verifying it is a regular workspace file, not a symlink. Test identities are never seeded into a deployed database. Backend HTTP tests disable Vite; browser tests build/load real assets. Vitest excludes the Laravel HMR plugin, rather than bypassing its CI protection.

G03: five ordered parameter projections and five CHR syntax/NAT-count cases passed. G04: seven rendered parameter/DHCP projections and six CHR syntax/simulated-event cases passed. Reports: docs/lab/. Neither set proves full traffic distribution, reachability or failover. Ten-WAN G04 was observed, not lab-tested. Full exact fixtures: zero; no exact-match percentage. Blocked cases never count as passing.

Private staging smoke verified five public routes, four authenticated routes, login and Starlink preview with a temporary Admin removed afterward. Doctor verified MySQL, Redis/cache, a unique actual worker job and scheduler heartbeat. Encrypted MariaDB backup was restored to a newly created random database under a distinct restricted role, checked, then dropped; source DB was not overwritten. SQLite restore also has automated coverage. Twelve sequential catalog requests through SSH are only a latency sample, not a load benchmark.

Remaining: exhaustive generator combinations, real packet-flow/DHCP/failover tests, deployed Turnstile/TLS/Cloudflare Access, accessibility review, meaningful concurrent-load testing and off-host disaster recovery. Never target a production router.
