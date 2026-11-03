# Showcase redesign — 2026-11-04

Reference: owner-provided `f:/download hasil/NANSAN NET Tools UI Showcase.png`. The implementation follows its bright white/cyan/blue/violet palette, N brand, flowing background, router illustration, five featured cards, two-column generator, workspace greeting and admin charts. This is a responsive implementation, not a claim of pixel-identical output at every viewport. Actual statistics, empty states, warnings and publication gates remain truthful. Existing generator algorithms and source datasets were not changed.

## Preview

Local Laravel preview: http://127.0.0.1:8000. Run `Buka Desain NANSAN.cmd` to start it and open login. The local owner username is `nansan_preview`; its randomly generated password is stored with Windows DPAPI under ignored `.runtime`, and the launcher copies it for the owner. Initial creation uses Artisan and does not promote registered accounts or overwrite existing administrators. The preview uses a workspace-local SQLite database. Browser QA uses a separate disposable database at port 8001.

The old Ubuntu staging forward at port 8091 last returned HTTP 502. Its expected ownership marker, current release target and PHP socket were missing during a read-only check. No remote files were overwritten or production deployment attempted. Historical deployment reports below remain historical evidence, not current availability guarantees.

## Assets

Selected original illustration: `public/images/router-hero.webp`, generated with the built-in imagegen tool, transparent background, optimized from its generated PNG using PHP GD. The PNG master stays in ignored runtime storage. Full generation prompt:

> Create a polished 3D website hero illustration, landscape 3:2 composition, transparent background. A glossy pearl-white compact wireless router with rounded rectangular body, two tall white antennae with a subtle blue inner light, viewed in three-quarter front perspective. The front of the device says exactly 'MikroTik' in dark navy. Cyan luminous bottom rim, soft blue and violet reflections, layered glowing concentric elliptical rings underneath the router. Floating translucent rounded glass tiles around and behind it: a white code brackets symbol, a white stacked layers symbol, a white three-node networking symbol, and a white wifi symbol. Color palette electric royal blue, cyan, lilac and white. Elegant friendly premium SaaS technology product render matching a bright glassmorphism networking website. Router and antennae completely inside frame with generous breathing space. No page UI, no website screenshot, no headings, no additional words, no watermarks. Single high-resolution isolated illustration, soft ambient shadow and luminous edges; true transparent alpha outside the object and its glows.

Logo and ribbons are code-native SVG components. Self-hosted Inter variable font: `resources/fonts/InterVariable.woff2`, from the official [Inter repository](https://github.com/rsms/inter); SIL OFL license retained in `public/fonts/Inter-LICENSE.txt`. Vite bundles the font locally.

## Verification

- PHP: 77 tests passed, 725 assertions.
- Frontend: 11 tests passed, including selected output-step copying and initial light appearance.
- Chromium E2E: 16 passed, zero skipped/flaky/unexpected.
- TypeScript, ESLint and production build passed.
- Node privacy/parser: 7 passed.
- Pint passes for changed controllers and the new feature test. Repository-wide Pint still flags pre-existing formatting in VersionController, DatabaseSeeder and SimpleQueueTreeTest; those unrelated files were preserved.
- Visual inspection: homepage desktop/mobile, generator, dashboard and admin screenshots. All four routes checked at 360/390/768/1440 pixels: no page errors or horizontal overflow.
- Two new backend tests verify real dashboard counts and seven-day admin activity; database metadata cannot report unpublished engines as active. Added this coverage to the MySQL CI suite as well.

Screenshots and browser test reports remain ignored under `.runtime/showcase-qa` and `.runtime/e2e-results.json`. No authentication snapshots, credentials or runtime databases are included in source.
