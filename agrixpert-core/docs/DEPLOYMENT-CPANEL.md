# Deploying agrixpert-core on cPanel

This covers the two things generic WordPress install instructions don't: getting the ZIP onto a cPanel-hosted WordPress site specifically, and the staging-then-production sequence the cutover plan calls for. For what the plugin does on activation and the shortcode reference, see `INSTALL.md` — this document is about the hosting mechanics only.

## Before you start

Confirm with your host (or cPanel's own info pages, under **General Information**) that the account meets what's in `INSTALL.md`'s Requirements section: PHP 8.0+, WordPress 6.4+. Most current cPanel/WHM hosting defaults to PHP 8.1 or later, but shared hosts sometimes leave an account pinned to an older version from years ago — check **MultiPHP Manager** in cPanel and set the domain to 8.0 or higher if it isn't already.

## Option A: a staging subdomain first (recommended, matches the cutover plan)

This is the path the cutover plan assumes for Stage 1.

1. **Create the subdomain.** In cPanel, go to **Domains → Create A New Domain**, enter something like `staging.agrixpert.samakose.com`, and let cPanel create its own document root (e.g. `/public_html/staging.agrixpert`). Do not point it at the live site's document root.
2. **Install WordPress there.** If the host provides Softaculous or WP Toolkit (cPanel → **Softaculous Apps Installer** or **WordPress Toolkit**), use it to install a fresh WordPress onto the new subdomain in a couple of clicks. If neither is available, download WordPress core from wordpress.org, extract it into the subdomain's document root via **File Manager**, and run the five-minute install at the subdomain's URL.
3. **Create a database.** If you didn't use an installer that does this for you: cPanel → **MySQL Database Wizard** → create a database, a user, a strong password, and grant that user all privileges on the database. Note the database name, username, and password (cPanel usually prefixes both with your cPanel account name, e.g. `cpaneluser_agx`).
4. **Upload and activate agrixpert-core.** In the staging site's wp-admin: **Plugins → Add New Plugin → Upload Plugin**, choose `agrixpert-core-2.2.0.zip`, **Install Now**, then **Activate**.
5. **Run Stage 1 of the cutover plan** against this staging site — the checklist in `agrixpert-core-cutover-plan.md` (registration → auto-login → Portal, the anti-takeover check, the verification-gating toggle, CSV export restricted to Admin). Do not proceed past this until every item passes.

## Option B: direct to production (only once Stage 1 has already passed on staging elsewhere, or you accept the risk)

1. **Back up first, always.** cPanel → **Backup** (or **JetBackup**/**Softaculous Backups** if your host provides one) → take a full account backup, or at minimum a database backup (`phpMyAdmin → Export`) and a files backup of `wp-content/plugins/`. This is non-negotiable before touching a live site's plugin set, regardless of how low-risk the change looks.
2. **Pick a low-traffic window** and let anyone reviewing registrations know there may be a brief interruption, per the cutover plan.
3. **Upload the ZIP.** wp-admin → **Plugins → Add New Plugin → Upload Plugin** → `agrixpert-core-2.2.0.zip` → **Install Now**.
4. **Deactivate `agrixpert`, activate `agrixpert-core`.** Both plugins read and write the same 24 database tables (the schema has been unchanged since Phase 2), so this is a like-for-like swap, not a migration — no data moves, nothing is converted.
5. **Confirm immediately:** the AgriXpert admin menu loads, the verification queues show existing data, and the front-end registration/marketplace pages render. The abbreviated version of the Stage 1 checklist.
6. **Leave `agrixpert/` in place, deactivated, for at least one full review cycle** so rollback is a one-click reactivation rather than a redeploy or restore. Rollback plan, if anything goes wrong: deactivate `agrixpert-core`, reactivate `agrixpert` — the full detail is in `agrixpert-core-cutover-plan.md`.

## cPanel-specific things that occasionally trip up a WordPress plugin install

- **Upload size limits.** If the ZIP upload fails with a size or timeout error, cPanel → **MultiPHP INI Editor** → select the domain → raise `upload_max_filesize` and `post_max_size` (the plugin ZIP is well under 1 MB, so this is unlikely to bite, but shared hosts sometimes set these unusually low).
- **File ownership after File Manager uploads.** If you ever upload the plugin folder via **File Manager** instead of the wp-admin uploader (e.g. to unblock a failed ZIP upload), make sure the extracted files are owned by the same user WordPress's PHP process runs as — cPanel's File Manager usually gets this right automatically, but if the plugin doesn't appear in the Plugins list after extraction, this is the first thing to check.
- **SSL.** Use cPanel → **SSL/TLS Status** (or **AutoSSL**) to confirm the subdomain/domain has a valid certificate before going live — registration forms collecting phone numbers and emails should not run over plain HTTP.
- **WP-CLI, for the Supabase migration command.** `wp agrixpert migrate-supabase` (see `agrixpert-core-supabase-migration.md` in the project docs) needs WP-CLI. Many cPanel hosts have it preinstalled and reachable over SSH; check **Terminal** in cPanel or ask your host. If WP-CLI isn't available on the hosting account, that migration step needs to run from a machine that has shell + WP-CLI access to the same WordPress install (or the database credentials), not necessarily the cPanel account's own Terminal.

## Updating later

Same as `INSTALL.md` describes: upload the new ZIP through wp-admin (it will prompt to replace the existing plugin) and reactivate if prompted. No cPanel-specific step is needed for routine updates once the plugin is already installed.
