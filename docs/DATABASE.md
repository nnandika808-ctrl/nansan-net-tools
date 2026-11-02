# Database

Schema is authoritative in `database/migrations/`. All tables are created through migrations. Test factory data is restricted to test databases; default seed creates no users or passwords.

| Area | Tables | Constraints / lifecycle |
|---|---|---|
| Identity | users, sessions, recovery_codes, password_recovery_requests, user_preferences | Unique lowercase username; role/status indexes; hashed passwords/codes; sessions FK; no required email |
| Activity | user_activity_logs, audit_logs | Nullable actor FK; event/date/subject indexes; allowlisted audit metadata; 30/90-day retention |
| Catalog | tool_categories, tools, tool_tags, tool_tag_relations, tool_synonyms | Unique slugs/IDs; category FK; composite tag/synonym uniqueness; searchable synonyms |
| Engine | generator_versions, generator_form_definitions, generator_templates | Tool/version uniqueness; version FK; JSON schema/defaults; no executable admin code |
| Workspace | user_presets, script_histories, favorites | Owner/tool FKs; owner/date indexes; unique favorites; output SHA-256; owner policy |
| Documentation | documentation_categories, documentation_articles | Author/category/tool FKs; unique slug; publication/status indexes |
| Notifications | announcements, announcement_reads | Scheduled publication; unique user/announcement read records |
| Feedback/media | feedback_reports, feedback_attachments, media_files | Private attachments; MIME/path/size metadata; public flag; private disk |
| Content/settings | cms_pages, site_settings, seo_metadata | Unique page slugs, setting keys and metadata paths; legal review marker |
| Operations | backup_records, deletion_requests | Backup hash/restore evidence; one deletion request/user; due-date index |

Tools have many synonyms and versions. Users have many owned presets, histories, favorites, feedback and sessions. Feedback attaches media through a pivot. CMS publication and article schedules filter public queries. Presets/history become writable only for published generator versions.

Secret-bearing arbitrary payload fields are rejected by generator validation. Default history storage never requests router passwords or tokens. Recovery codes contain 128 random bits, are stored as SHA-256 hashes and consumed atomically under a transaction. Encrypted session payloads and filesystem/database permission restrictions protect transient recovery-code display.

Deletion is password-verified, deactivates the account, rotates persistent auth state and waits 30 full days. Cancellation requires password and a not-yet-due request. Purge rechecks/locks eligibility, deletes owned private content/media and user data, and leaves only nonpersonal operational audit entries under retention. An Admin cannot request self-deletion through the user route. Backup retention after erasure remains a production policy/restore requirement for owner review.

MySQL/MariaDB migration, index and concurrency checks are still required on staging; SQLite tests alone cannot prove MySQL locking semantics.

English CMS fields are nullable additions: title_en/body_en on articles, pages and announcements; excerpt_en on articles; description_en on tools. English seeding fills only missing translations and preserves owner edits. Both Markdown bodies are sanitized through the same renderer.
