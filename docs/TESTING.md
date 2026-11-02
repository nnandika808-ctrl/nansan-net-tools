# Testing

Run `php artisan test`, `npm test`, `npm run typecheck`, `npm run lint`, `npm run build`, and `npm run test:e2e`. Windows bootstrap provides `.runtime/tools/php/php.exe`; use that path if PHP is not on PATH. Vite native resolution and Chrome execution need ordinary child-process permission on Windows.

Backend tests isolate SQLite in memory. E2E starts a separate local server at 127.0.0.1:8001 with `.runtime/e2e.sqlite`, never the development or server database. `tests/Support/bootstrap-e2e.php` refuses other database names/drivers/environments. Test-only identity credentials are confined to this isolated database. Production seeding creates no test user.

Latest completed checks: 26 platform/comparison/default tests passed (129 assertions); 3 PCC regression tests passed separately (14 assertions); 5 React component tests passed; 8 E2E tests passed, covering public pages/search, registration/recovery display, favorites, feedback, account password rejection, CMS publication, CSRF and responsive widths 360/390/768/1440. TypeScript, ESLint and production build passed before the latest PCC/backend additions. Re-run the full combined suite after further changes. Npm audit reported zero vulnerabilities after the reviewed minimatch override; lint/build/E2E must remain compatible with it.

Reference fixtures live under `tests/Fixtures/GeneratorReference/`. Five G03 cases contain observed ordered parameter structures; other cases retain metadata/blocked status. No full reference output or game/.rsc dataset is bundled. Partial parameter projection matches are reported separately from exact and full semantic equivalence. See reference/OUTPUT-COMPARISON.md for rates and limits. Unverified/blocked cases never count as passing.

Still required: exhaustive form/validation combinations, complete generator regression, permitted full-text fixtures if exact comparison is required, real RouterOS lab import/traffic/failover checks, MySQL locking/foreign-key integration, Redis cache/worker/scheduler, backup restore, deployed Turnstile/CSP/TLS, accessibility review and load benchmark. Do not run router configuration tests against production devices.
