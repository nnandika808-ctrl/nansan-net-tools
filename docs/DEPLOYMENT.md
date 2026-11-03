# Deployment and rollback

Current aaPanel installation path: [AAPANEL-HOSTING.md](AAPANEL-HOSTING.md), with CI-built no-dev ZIP, checksum and fresh extracted-archive smoke. The private staging description below is historical: the latest read-only audit found its ownership marker/current release missing and HTTP 502. Do not run a redeploy over that directory or treat the old verification as current health.

Production mikrotik.nansannet.cloud is **not approved/deployed**. The staging hostname staging.mikrotik.nansannet.cloud is not configured in DNS/Tunnel. Current staging is private through encrypted SSH forwarding at http://127.0.0.1:8091. Existing Apache port 80, production sites and routers were not changed.

## Actual private staging

Ubuntu 22.04 LXC, PHP 8.4.27, MariaDB 10.11.10, 2 GB RAM. Base: /www/wwwroot/nansan-tools-staging, with a root-owned ownership marker, dated releases and atomic current symlink. A separate nansan-staging system user owns application runtime data. Composer and private Nginx/Redis dependencies were installed only under that project directory. Fileinfo was compiled from checksum-verified official PHP source and loaded only through the staging PHP scan directory; global aaPanel PHP configuration was preserved.

Nginx listens on 127.0.0.1:8091, Redis on 127.0.0.1:6381. Dedicated PHP-FPM socket, database/user, APP_KEY, session cookie, cache/queue namespace, worker and minute systemd scheduler are isolated from existing apps. Required GD/fileinfo/PDO/sodium/zip extensions are checked by Composer. CLI process functions/signals needed by Composer/queues are enabled per staging command, not globally. Publication remains locked in code. Turnstile is enabled and fails closed until real keys are configured. Internal HTTP cookies are secure=false only inside this SSH-only preview; HTTPS deployment requires secure=true.

The owner Admin nansan was created through hidden Artisan prompts with a random password. Its local credential is encrypted with Windows DPAPI in ignored .runtime/staging-owner.credential.xml. To open login and copy the password without printing it, run:

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts/staging-admin-access.ps1 -CopyPassword

The file can only be decrypted by the current Windows user on this machine. Keep the SSH forward running. Generate and safely retain recovery codes from the Account page after login. No public default password or seeded Admin exists.

On Windows, double-click `Buka NANSAN.cmd` in the project folder. It invokes the same owner-access helper, checks the login URL before copying the Admin password, and opens a visible browser window. If the forward is missing, it prompts for the Ubuntu root SSH password with hidden input and starts a detached, hidden Node connection pinned to the authorized server fingerprint. The SSH password travels through stdin only; it is not saved in arguments, environment or files. Runtime connection logs contain service/HTTP status, not passwords. Closing the launcher leaves the forward running; an SSH disconnect closes its listener so the next launch can reconnect. This is private access from this Windows computer, not a phone/public URL. Cold-start recovery and HTTP 200 at `/login` were verified on 2026-11-03; application generator logic was unchanged.

## Reproducible release

scripts/deploy-staging.mjs pins the observed SSH fingerprint, uploads a source whitelist and verified Composer, then runs deployment/provision-staging.py. Archive excludes .env, browser state, databases, uploads, vendor and node_modules. The server generates/retains secrets, installs locked dependencies, checks platform requirements, migrates/seeds, switches current, caches Laravel metadata and restarts only project-owned FPM/web/worker services. enable --now alone is insufficient for an existing worker, so restart is explicit.

Validate /up, public/auth/admin routes, CSP/CSRF, uploads and nansan:doctor --queue. scripts/verify-staging.mjs creates a guarded temporary test identity, performs real HTTP operations and removes it afterward. The successful report includes a unique worker probe, fresh scheduler heartbeat and verified encrypted backup restore. A dozen sequential SSH requests are latency samples, not load/capacity proof.

## Backup and rollback

Snapshots contain database and private media, authenticated sodium encryption and stored SHA-256 verification. Same-host private backup storage and a separate encryption key are configured. The nansan_restore account has privileges only on randomly named nansan_restore_* scratch databases; it cannot access the source application database. Restore verification creates a fresh scratch DB, imports, checks tables/counts/media hashes, then drops only that newly created DB. SQLite scratch restore also has test coverage. Off-host storage and independent protected key escrow remain unconfigured.

Before migration on a future production release, verify a fresh backup and schema compatibility. Roll back application code by switching the owned current symlink to the preceding compatible release and restarting matching services. Never automatically roll back destructive migrations or overwrite a live database. Restoration of a live database needs a reviewed operational decision.

## Remaining production gate

Configure Cloudflare account/Tunnel/Access and staging DNS through an authorized secure connection; real Turnstile keys/hostnames; TLS/proxy/secure-cookie verification; off-host storage/key escrow; concurrent capacity testing; legal review and generator packet-flow/failover/dataset acceptance. Close critical gaps and obtain explicit production approval before any production deployment or DNS/router change.
