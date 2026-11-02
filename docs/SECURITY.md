# Security

Passwords use Laravel hashing. Registration accepts username/password and prohibits role input. First Admin is created through an interactive hidden Artisan password prompt. No email-reset fiction or OAuth is provided. Recovery requires a one-time random code; username alone never grants access. Login, registration, recovery, generation and common writes are rate limited.

Sessions regenerate on login/register and invalidate on logout. Production defaults enable secure, HTTP-only, SameSite cookies and encrypted session payloads. Device-session revocation requires current password and uses a keyed opaque session identifier; raw session IDs are not sent to React. Password changes revoke other database sessions and rotate remember tokens. Suspension invalidates sessions and persistent auth. Staging and production require separate APP_KEY and cookie/cache namespaces.

Laravel web middleware protects CSRF. ORM/query-builder parameters prevent SQL injection. React escapes text; CMS Markdown strips raw HTML and unsafe links. Uploads accept only size/dimension-limited JPEG/PNG/WebP, decode/reencode as WebP and reside outside the public executable tree. Media reads enforce public status or ownership/Admin permission. SVG/PHP uploads are rejected. No arbitrary PHP/shell execution endpoint exists.

Turnstile fails closed when enabled without credentials. Successful validation requires the expected hostname and `register` action. Local/testing may disable it explicitly; deployment must configure real keys and hostnames. Never deploy the local `.env`.

Audit metadata has an explicit low-sensitivity allowlist; passwords/tokens are dropped. Never export browser cookies/storage state or capture login forms. Browser profile is isolated and memory-only. User login was manual. Reference observation artifacts containing output stay outside tracked source; repository evidence uses form metadata and functional parameter observations, not exclusive datasets.

Security headers cover nosniff, referrer policy, frame denial, permissions restrictions and private no-store/noindex responses. Deployed CSP/TLS/proxy behavior, suspicious-login notification, credential rotation, penetration testing and external backup encryption/retention need staging verification before production. Current checks are regression tests, not a complete security audit.

Schema drafts cannot alter active generator schemas, overwrite audited versions, claim passing evidence, or install code/handlers. Analytics counts successful public page requests, excludes prefetch, retains aggregate rows for 90 days, and collects no visitor identifiers. Server security/session logs remain a separate, disclosed category. Review the final privacy draft with these features before launch.

The reference-browser launcher sets snapshot mode none, disables code generation/WebMCP, redacts token URL parameters and uses an isolated profile. The bridge refuses explicit snapshots/form-fill operations and blocks DOM inspection/screenshots on visible authentication forms. Its guard reads visibility only, never field values. Helper unit tests and real headless integration passed without entering credentials.
