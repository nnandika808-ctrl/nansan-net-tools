# Deployment and rollback

Production: `mikrotik.nansannet.cloud`. Staging: `staging.mikrotik.nansannet.cloud`. Production is not authorized for deployment. No DNS changes have been made. Hosting remains Ubuntu/aaPanel/Nginx/PHP-FPM, MySQL-compatible database, Redis and Cloudflare Tunnel; the Laravel app is not deployed to Sites/Workers.

## Discovery

The owner supplied SSH access to the private Ubuntu host. Read-only discovery found Ubuntu 22.04, PHP 8.4.27, MariaDB 10.11.10, 2 GB RAM and approximately 12 GB free root disk. Nginx/Redis/Tunnel were not established by PATH/systemd checks. aaPanel-specific binary/socket paths require further inspection. No public IP is assumed. The requested 4–8 GB memory target is above the observed host RAM; capacity remains unbenchmarked.

## Required staging preparation

1. Confirm aaPanel Nginx path, PHP 8.4 FPM socket/extensions, database socket auth, Redis and supervisor/systemd support.
2. Create an isolated app user, release directory, database/user, storage and secrets for staging. Never reuse production `.env`, session cookie, storage or Redis prefix/database.
3. Transfer a source whitelist and built assets. Exclude `.env`, `.runtime`, `.cache`, browser state, logs, test DBs and user uploads. Use `composer install --no-dev --prefer-dist --no-interaction` and `composer check-platform-reqs` with the selected PHP CLI.
4. Set APP_ENV=staging, APP_DEBUG=false, proper URL, key, MySQL and Redis settings, real Turnstile credentials/hostnames and a distinct cookie. Keep SITE_INDEXABLE=false.
5. Migrate and seed catalog/content. Create Admin by `php artisan nansan:admin <username>` with hidden password prompt. Seed never creates an Admin or public credentials.
6. Point Nginx root to `public/`, deny hidden files/PHP uploads, set FPM socket and bind internal staging origin to loopback. Validate configuration before reload.
7. Configure a distinct Redis queue worker, scheduler cron, private backup disk/retention, heartbeat monitoring and log rotation.
8. Configure Cloudflare Tunnel to the correct local origin and protect staging with Cloudflare Access. Do not open database/Redis ports. Tunnel credentials remain server secrets. DNS/Tunnel account access must be provided through a secure authorized mechanism.
9. Run public/auth/admin/media/CSRF smoke tests and MySQL/Redis/queue/scheduler checks, then backup+isolated restore test and performance/security checks.

## Production gate

Complete generator verification/lab criteria, close critical gaps, review legal drafts, validate Turnstile/TLS/proxy/CSP, verify backup restore and off-host storage, ensure no test identities/data, and record staging acceptance. Only then request explicit production deployment approval.

## Release and rollback

Use a dated release and atomic current symlink. Before migration, verify a fresh database/storage backup and its hashes; document schema compatibility. Build/cache configuration/routes/views, migrate with force only for the intended environment, switch release, restart the matching worker, and run health/smoke tests. If health fails, switch to the prior release and restart its workers. Do not automatically roll back destructive migrations or overwrite a live database; restoration requires the reviewed backup and an explicit operational decision. Current prepared documents are not a claim that this procedure has been executed.
