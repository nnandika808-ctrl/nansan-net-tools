# Testing

Run php artisan test, npm test, npm run typecheck, npm run lint, npm run build and npm run test:e2e. Windows PHP is .runtime/tools/php/php.exe; use npm.cmd if PowerShell script policy blocks npm.ps1.

Latest backend: **50 tests, 251 assertions passed**. Frontend: **6 component/form tests passed**; TypeScript, ESLint and production build passed. Browser suite: 13 passed, including SEO/FAQ, validated templates and English content. GitHub source CI [41531922788](https://github.com/nnandika808-ctrl/nansan-net-tools/actions/runs/41531922788) at 830475a passed PHP 8.4, frontend/privacy checks, isolated MySQL/Redis integration, Chromium E2E and dependency audits.

Backend tests use isolated SQLite; CI additionally runs PlatformTest, TemplateTest, VersionTest, AnalyticsTest and SeoTest on its disposable MySQL service. Browser tests reset only .runtime/e2e.sqlite at 127.0.0.1:8001 after verifying it is a regular workspace file, not a symlink. Test identities are never seeded into a deployed database. Backend HTTP tests disable Vite; browser tests build/load real assets. Vitest excludes the Laravel HMR plugin, rather than bypassing its CI protection.

G03: five ordered parameter projections and five CHR syntax/NAT-count cases passed. G04: seven rendered parameter/DHCP projections and six CHR syntax/simulated-event cases passed. Reports: docs/lab/. Neither set proves full traffic distribution, reachability or failover. Ten-WAN G04 was observed, not lab-tested. Full exact fixtures: zero; no exact-match percentage. Blocked cases never count as passing.

Private staging smoke verified five public routes, six authenticated routes, login and Starlink preview with a temporary Admin removed afterward. Doctor verified MySQL, Redis/cache, a unique actual worker job and scheduler heartbeat. Encrypted MariaDB backup was restored to a newly created random database under a distinct restricted role, checked, then dropped; source DB was not overwritten. SQLite restore also has automated coverage. Twelve sequential catalog requests through SSH are only a latency sample, not a load benchmark.

Remaining: exhaustive generator combinations, broader physical-network packet-flow/DHCP/failover tests, deployed Turnstile/TLS/Cloudflare Access, accessibility review, meaningful concurrent-load testing and off-host disaster recovery. Never target a production router.

Packet/protocol validation: see lab/PACKET-RESULTS.md. Five PCC packet cases forwarded 2,000 baseline UDP flows, plus 100 during a ROS7 link disable, corroborated by PCAP. Three ROS7 Starlink cases passed real synthetic DHCP/UDP/NAT/renew/unbind and monitoring where enabled. Both ROS6 gateway-only renewal cases failed: client gateway changed, route gateway did not. They remain failed. The supplementary ROS7 renewal-while-monitor-Down observation did not flag reactivation by its route query. No physical ISP or full reference-equivalence claim.

Browser privacy: three Node helper tests passed. The actual headless MCP integration confirmed snapshots disabled, auth-page DOM inspection/screenshots blocked and ordinary-page inspection allowed; no credentials were entered. Use scripts/start-reference-browser.ps1 and stop-reference-browser.ps1.

Owner-supplied ROS6 comparison: initial DHCP clients asserted zero before each script. Both the supplied sample and own draft passed import and 400/400 UDP/NAT forwarding; ordered parameter/limited DHCP projections matched. Both gateway-only renewal assertions FAILED in the same way. PCAP readback corroborates 800 total baseline payloads. See lab/OWNER-SUPPLIED-STARLINK-6.49.19.json. No application/generator code changed; this observation does not add eligible redistributable full-text fixtures.
