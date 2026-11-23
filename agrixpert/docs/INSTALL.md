# AgriXpert Plugin — Installation Guide

## Requirements

- WordPress 6.4 or later
- PHP 8.0 or later
- MySQL/MariaDB (whatever your WordPress install already uses)
- An SMTP-capable mail setup if you want registration/notification emails to actually deliver (WordPress's default `wp_mail()` often gets filtered as spam by receiving mail servers without one — see "Configure email" below)

## Before you install: read this

This is version **1.0.0-rc1** — a release candidate. The code has been through PHP lint and a manual static security/functional audit (see `docs/TESTING.md` for exactly what that covered and found), but **it has not yet been click-tested on a live, running WordPress site**, because the sandboxed environment it was built in could not fetch WordPress core or wp-cli to stand one up. Install this on a staging site first, run through `docs/TESTING.md`'s checklist yourself, and only then put it on a production domain.

## 1. Get the plugin files

If you're installing from this repository, you need the `agrixpert/` folder (the one containing `agrixpert.php` at its root) packaged as a ZIP with that folder at the top level:

```
agrixpert.zip
└── agrixpert/
    ├── agrixpert.php
    ├── uninstall.php
    └── ... (includes/, assets/, docs/)
```

A build script that produces exactly this ZIP is at `tools/build-zip.sh` in the repo root — see `docs/MAINTENANCE.md` for how to run it.

## 2. Install via the WordPress admin dashboard

1. Go to **Plugins → Add New Plugin → Upload Plugin**.
2. Choose `agrixpert.zip` and click **Install Now**.
3. Click **Activate Plugin**.

On activation, the plugin automatically:
- Creates all 22 database tables.
- Registers the `AgriXpert Staff` and `AgriXpert Admin` roles, and grants your own WordPress administrator account full AgriXpert access (you will never be locked out of it).
- Seeds your WordPress admin email into the AgriXpert admins list.
- Seeds a handful of default site-content values (home page headline, contact email) so the public pages aren't empty on first load.

No manual database setup, SQL import, or configuration file editing is required for this step.

## 3. Add the public-facing pages

The plugin provides shortcodes, not pre-built pages — this lets you place them wherever fits your site's existing structure and theme. Create WordPress pages (or add blocks to existing ones) with:

| Shortcode | What it shows |
|---|---|
| `[agrixpert_farmer_form]` | Farmer registration form |
| `[agrixpert_buyer_form]` | Buyer registration form |
| `[agrixpert_supplier_form]` | Supplier registration form |
| `[agrixpert_cooperative_form]` | Cooperative registration form |
| `[agrixpert_contact_form]` | General contact/enquiry form |
| `[agrixpert_marketplace]` | Marketplace listings + post-a-listing form |
| `[agrixpert_content key="hero_heading"]` | A single managed site-content value (see Site Content admin screen for available keys) |
| `[agrixpert_insights]` | The Insights/articles grid |

The consent checkbox on every registration form links to `/privacy-policy`, `/terms-of-service`, and `/cookie-policy` — make sure pages exist at those slugs (or edit the links in `includes/class-form-engine.php` if your site uses different URLs).

## 4. Configure email (recommended before going live)

Go to **AgriXpert → Settings** in the WordPress admin menu:

- Enter your SMTP host, port, username, and password, and upload a logo for branded emails. These are stored in `wp_options` by default.
- **For a production deployment**, the hardened alternative is to define SMTP credentials as constants in `wp-config.php` instead, so they never live in the database:
  ```php
  define( 'AGRIXPERT_SMTP_HOST', 'smtp.yourprovider.com' );
  define( 'AGRIXPERT_SMTP_PORT', 587 );
  define( 'AGRIXPERT_SMTP_USER', 'you@yourdomain.com' );
  define( 'AGRIXPERT_SMTP_PASS', 'your-smtp-password' );
  ```
  A constant, if defined, always takes priority over the Settings-screen value.

Without SMTP configured, the plugin still functions — WordPress's default mail sending is used as a fallback — but deliverability to external inboxes is not guaranteed.

## 5. Grant staff access

To let someone other than a full WordPress administrator verify registrations or manage the CRM/marketplace without giving them full admin rights, assign them the **AgriXpert Staff** role (Users → your colleague → Role). Give **AgriXpert Admin** only to people who should also manage site content and settings.

## 6. Verify the install

Walk through `docs/TESTING.md`'s manual checklist on your staging site before announcing it live. At minimum: submit each registration form, approve/reject one from the admin verification queue, post a marketplace listing, and confirm notification emails arrive.

## Updating to a later version

Upload the new ZIP the same way (WordPress will prompt to replace the existing plugin) and reactivate if prompted. The plugin checks and upgrades its database schema automatically on the very next page load after an update — there is no separate "run migrations" step, and no existing data is touched by an upgrade.

## Uninstalling

**Deactivating** the plugin (Plugins screen) is always safe and never deletes anything — all your data and settings remain, ready for the plugin to be reactivated later.

**Deleting** the plugin (after deactivating, via the Plugins screen's "Delete" link) gives you the option to also delete its data. If you confirm that, every AgriXpert table, option, and the two custom roles are permanently removed. This cannot be undone — export anything you need first.
