# AgriXpert Plugin — Architecture

This document describes how the AgriXpert WordPress plugin is put together. It is written for a developer who needs to maintain, extend, or audit the code — not for an end user of the site.

## 1. Context: this is a rewrite, not a port

AgriXpert originally shipped as a Next.js application on Vercel, backed by Supabase (Postgres + Row Level Security + Auth + Vercel Cron). This plugin reimplements the same functional scope natively in PHP/MySQL for WordPress. It is a different language, a different runtime, and a different database engine, so several things were deliberately re-architected rather than translated line-for-line. Those decisions are recorded below because they are the parts most likely to surprise someone used to the original system.

The original Next.js/Supabase application continues to run unaffected at its existing Vercel URL; this plugin is an independent, parallel build and does not read from or write to that system.

## 2. Plugin bootstrap

`agrixpert.php` is the single entry point WordPress loads. It:

- Defines constants (`AGRIXPERT_VERSION`, `AGRIXPERT_PLUGIN_DIR`, `AGRIXPERT_PLUGIN_URL`, `AGRIXPERT_DB_VERSION`).
- Requires every include file in dependency order — schema and roles first, then data-access classes, then the form engine and anything that registers shortcodes or hooks against those classes.
- Defers admin-only files (everything under `includes/admin/`) to a `plugins_loaded` callback at priority 20, so they never load on the front end.
- Registers activation and deactivation hooks.
- Runs a `agrixpert_check_db_version()` check on every `plugins_loaded`, so a schema change shipped in a plugin update is applied automatically the next time any page loads, without requiring the admin to manually deactivate/reactivate.

## 3. Directory layout

```
agrixpert/
├── agrixpert.php                 Plugin bootstrap
├── uninstall.php                 Destructive cleanup (see §7)
├── assets/css/forms.css          Theme-agnostic styling for public forms
├── docs/                         This documentation set
└── includes/
    ├── class-schema.php          All 22 table definitions (single source of truth)
    ├── class-roles.php           Custom roles + capability constants
    ├── class-activation.php      First-run setup (tables, roles, seed admin, default content)
    ├── class-form-engine.php     Generic public-form renderer/handler (see §5)
    ├── class-registration-forms.php   The 5 form configs registered with the engine above
    ├── class-settings.php        Admin Settings screen (SMTP, logo)
    ├── class-mailer.php          wp_mail wrapper + SMTP credential resolution
    ├── class-email-templates.php Branded HTML email templates
    ├── class-notifications.php   Hooks data-writing actions to the mailer
    ├── data/                     One data-access class per domain area (CRM, marketplace,
    │                             actors, verification, registrations, content) — every
    │                             query against user input goes through $wpdb->prepare()
    ├── admin/                    WP admin screens (menu, list tables, CRM, marketplace,
    │                             follow-ups, site content/insights) — loaded only in wp-admin
    └── public/                   Shortcode renderers for the marketplace and site content
```

## 4. Data model

All 22 tables are defined in `AgriXpert_Schema` and created/upgraded via `dbDelta()`, WordPress's standard idempotent schema-management function — calling it again with an unchanged definition is a no-op, and calling it with a changed definition alters only what changed. This is what makes plugin updates safe: a new version can add a column or table without touching existing data.

**Primary keys are UUIDs (`CHAR(36)`), not WordPress's usual `AUTO_INCREMENT BIGINT`.** This was a deliberate compatibility choice: the original Supabase tables use UUID primary keys, and keeping the same key type means a future data migration from the live Supabase database can import rows (and the foreign keys between them) without renumbering anything. Supabase's `auth.users(id)` references map to `BIGINT UNSIGNED` against `wp_users.ID`, since WordPress users are never UUIDs.

## 5. Public forms: one engine, five configs

The original site has five structurally identical public forms (farmer, buyer, supplier, cooperative, and a general contact/enquiry form): a handful of fields, an optional checkbox group, a required consent checkbox, one table insert. Rather than hand-write five near-identical handlers, `AgriXpert_Form_Engine` is a single generic engine driven by a config array per form (defined in `class-registration-forms.php`). Each form becomes a shortcode (`[agrixpert_farmer_form]`, `[agrixpert_buyer_form]`, `[agrixpert_supplier_form]`, `[agrixpert_cooperative_form]`, `[agrixpert_contact_form]`).

Submission follows WordPress's native Post/Redirect/Get pattern through `admin-post.php`: nonce-verified POST → server-side validation and sanitization per field type → insert → redirect back to the referring page with a query flag the shortcode reads to show a success or error message. This works with JavaScript disabled, which the original client-side SPA form did not — a genuine functional improvement, not just a port.

**Abuse defense** is built into the engine itself (so every form gets it for free): a CSS-hidden honeypot field, a minimum-fill-time check (a submission faster than 2 seconds after the form rendered is rejected as scripted), and a per-IP rate limit of 5 submissions per form per 10 minutes, implemented with WordPress's transients API rather than a custom table.

## 6. Authorization: capabilities replace Row Level Security

**This is the single biggest architectural substitution in the whole rewrite, and the one most worth a careful read.**

Postgres RLS enforced authorization at the database layer: a query could only ever see or modify rows a policy explicitly allowed, regardless of what the application code did or forgot to do. MySQL/WordPress has no equivalent database-level mechanism. Authorization here is enforced entirely in PHP, at the top of every admin screen handler and every data-writing function, via WordPress's capability system:

| Capability | Meaning |
|---|---|
| `agx_manage_all` | Full admin: site content, settings, staff-level access to everything |
| `agx_verify_entities` | Approve/reject farmer, buyer, supplier, cooperative registrations |
| `agx_manage_crm` | CRM pipeline, notes, tasks, follow-ups |
| `agx_moderate_marketplace` | Marketplace listings and bookings |

Two custom roles bundle these: `agrixpert_staff` (verify + CRM + marketplace, not full admin) and `agrixpert_admin` (all four). The WordPress `administrator` role is always granted all four on activation, so the person installing the plugin is never locked out of it.

Because there is no database backstop, **every new admin screen or data-writing function must call `current_user_can()` (or `check_admin_referer()` for CSRF, which is a separate concern) before touching data.** The Phase 8 security audit swept every existing handler for this and found the coverage sound, with two real bugs fixed (see `docs/TESTING.md`). Any future code added to this plugin should be held to the same standard, since nothing else will catch a missed check.

**Actor identity is always derived server-side, never trusted from client input.** `AgriXpert_Actors` resolves the logged-in WordPress user to their farmer/buyer/supplier/cooperative record via a `user_id` foreign key, claimed automatically on first login. Every marketplace write (expressing interest, booking equipment or storage) derives "who is acting" from this resolution, not from a hidden form field — the server-side analogue of what RLS enforced in Postgres via "you can only act as your own row."

## 7. Lifecycle: activation, deactivation, uninstall

- **Activation** (`class-activation.php`): runs `dbDelta()` to create/upgrade all tables, registers the custom roles and capabilities, seeds the installing admin's email into the `admins` table so there is always at least one full admin, and seeds a handful of default site-content values (hero heading, contact email, etc.) only if they don't already exist.
- **Deactivation**: intentionally does nothing destructive. Tables, options, and roles are all left in place, so deactivating and reactivating the plugin — or updating it — never loses data. `dbDelta()` re-running on every version check is additive/idempotent by design.
- **Uninstall** (`uninstall.php`): WordPress only runs this file when the plugin is deleted from the Plugins screen with the "also delete its data" confirmation checked — never on a plain deactivation. It is the one deliberately destructive path: it drops all 22 tables, deletes the plugin's options, clears the scheduled cron hook, and removes the two custom roles.

## 8. Notifications

Six WordPress actions connect every data-writing feature to the notification layer without those features needing to know anything about email: `agrixpert_submission_saved`, `agrixpert_verification_decided`, `agrixpert_listing_created`, `agrixpert_listing_interest_created`, `agrixpert_equipment_booking_created`, `agrixpert_storage_booking_created`. `AgriXpert_Notifications` hooks all six and sends branded HTML emails via `AgriXpert_Mailer`, which wraps `wp_mail()` and resolves SMTP credentials from either `wp_options` (set via the admin Settings screen) or `wp-config.php` constants (`AGRIXPERT_SMTP_HOST`, etc.) if defined, with the constant taking priority — the hardened option for a production deployment where secrets shouldn't live in the database.

A daily digest of overdue CRM follow-ups runs on native WP-Cron (`agrixpert_followup_digest_cron`, scheduled for 07:00 on `init` if not already scheduled, cleared on deactivation), calling `AgriXpert_CRM::get_due_followup_tasks()` directly in-process. The original Supabase version needed a shared-secret RPC for this because an external Vercel Cron job had no Supabase session and had to bypass RLS to read the data; WordPress's own cron runs inside a normal WordPress request with full `$wpdb` access, so that complexity doesn't exist here.

## 9. What is intentionally not in this plugin

- **No REST API layer** beyond what the public shortcode forms need (they use `admin-post.php`, not `wp-json`). If a future requirement needs programmatic access (a mobile app, a third-party integration), that would be a new, deliberately-scoped addition — not something to bolt onto the existing admin-post handlers.
- **No file upload handling.** The Insights feature takes a plain image URL field; the branding logo goes through WordPress's own built-in media-library uploader. There is no custom upload-and-validate code anywhere in the plugin.
- **No automated test suite.** Verification so far has been PHP lint on every commit plus a manual static security/functional audit (see `docs/TESTING.md`). This is a real gap for a production system of this scope and is flagged, not hidden.
