# AgriXpert Plugin — Maintenance Guide

## Building a release ZIP

From the repository root:

```bash
tools/build-zip.sh
```

This reads the version straight from `agrixpert.php`'s header, copies the plugin into a clean temporary staging directory (so no stray local files leak in), and produces `dist/agrixpert-<version>.zip` with the correct internal layout for WordPress's "Upload Plugin" flow (a single top-level `agrixpert/` folder). Requires `zip` and `unzip` on the machine running it.

## Releasing a new version

1. Make your code changes.
2. Bump the version in **two places that must always match**:
   - The `Version:` line in `agrixpert.php`'s header comment.
   - The `AGRIXPERT_VERSION` constant a few lines below it.
3. If the change adds, removes, or alters a database table or column, update `AgriXpert_Schema` and bump `AGRIXPERT_DB_VERSION` in `agrixpert.php`. The plugin checks this on every `plugins_loaded` and re-runs `dbDelta()` automatically when it's changed — you do not need to write a separate migration script, and existing data is never touched by this (dbDelta is additive-only: it can add new tables/columns but never drops or truncates existing ones).
4. Run `php -l` across the whole `agrixpert/` directory before committing — catches syntax errors for free with no dependencies.
5. Commit, push, and run `tools/build-zip.sh` to produce the release ZIP.
6. **Test the upgrade path**, not just a fresh install: install the previous version, activate it, let it accumulate some data, then upload and activate the new ZIP over it. Confirm existing data survived and any new tables/columns appeared. This has not yet been exercised even once for this plugin (see `docs/TESTING.md`) — it is one of the first things to verify in live testing.

## Versioning scheme

This plugin does not follow WordPress.org's plugin directory conventions (it isn't distributed through there) but does use semantic-ish versioning: `MAJOR.MINOR.PATCH[-suffix]`. The current `1.0.0-rc1` tag means "feature-complete first release, pending live functional testing" — see `docs/TESTING.md`. Once live testing passes, the next tag should drop to a plain `1.0.0`.

## Where things live

See `docs/ARCHITECTURE.md` for the full directory layout and design rationale. The short version for a maintainer:

- New database table or column → `includes/class-schema.php`, then bump `AGRIXPERT_DB_VERSION`.
- New capability check needed somewhere → constants are in `includes/class-roles.php`; call `current_user_can()` with one of them at the top of the new code path. There is no other enforcement layer (see `docs/ARCHITECTURE.md` §7) — a missed check here is a real security hole, not a style issue.
- New public form → add a config block to `includes/class-registration-forms.php` and call `AgriXpert_Form_Engine::register_form()`; you get CSRF protection, sanitization, and the spam/abuse defenses (honeypot, timestamp floor, rate limit) for free without writing any of that yourself.
- New admin screen → follow the pattern in `includes/admin/`: register with `add_submenu_page()` under the `agrixpert` top-level slug, gate the whole render function on `current_user_can()`, and use `check_admin_referer()`/`wp_nonce_field()` on anything that writes data.
- New notification trigger → fire a new `do_action()` at the point the event happens, then hook it in `includes/class-notifications.php` — keeps the triggering code decoupled from email concerns, matching the existing pattern.

## Keeping docs in sync

If a change affects installation steps, admin workflows, or public-facing behavior, update the relevant file in `docs/` in the same commit — `INSTALL.md`, `ADMIN-GUIDE.md`, `USER-GUIDE.md`, `ARCHITECTURE.md`. `docs/TESTING.md` should be updated with real results the first time this plugin is actually live-tested, replacing its current "not done" status for each item.
