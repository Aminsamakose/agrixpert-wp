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

## 3. The public-facing pages

Activation automatically creates every page the site needs — About, What We Do, Solutions, Value Chains (plus the 5 crop and 6 solution sub-pages), For Farmers, For Buyers, For Cooperatives, Partnerships, Contact, Marketplace, My Account (Portal), and the three legal pages — already filled with the real content migrated from the live site (see `docs/CONTENT-MIGRATION.md`), not placeholders. This only happens once per slug: if a page already exists there (including one you've since edited), activation leaves it alone.

If you want to place any of this content somewhere else, or build additional pages, the same shortcodes the seeded pages use are available anywhere:

| Shortcode | What it shows |
|---|---|
| `[agrixpert_farmer_form]` | Farmer registration form |
| `[agrixpert_buyer_form]` | Buyer registration form |
| `[agrixpert_supplier_form]` | Supplier registration form |
| `[agrixpert_cooperative_form]` | Cooperative registration form |
| `[agrixpert_contact_form]` | General contact/enquiry form |
| `[agrixpert_marketplace]` | Marketplace listings + post-a-listing form |
| `[agrixpert_portal]` | Self-service "My Account" area for a logged-in registered farmer/buyer/supplier/cooperative: verification status, editable profile details, cooperative member roster, and booking reviews |
| `[agrixpert_content key="home.hero.title"]` | A single managed site-content value (see Site Content admin screen for available keys) |
| `[agrixpert_insights]` | The Insights/articles grid |
| `[agrixpert_value_chains]` | Grid of all 5 value-chain pages (Maize, Soybeans, Rice, Groundnuts, Vegetables) |
| `[agrixpert_value_chain slug="maize"]` | Full detail page for one value chain |
| `[agrixpert_solutions]` | Grid of all 6 solution pages |
| `[agrixpert_solution slug="production-coordination"]` | Full detail page for one solution |

The consent checkbox on every registration form links to `/privacy-policy`, `/terms-of-service`, and `/cookie-policy` — make sure pages exist at those slugs (or edit the links in `includes/class-form-engine.php` if your site uses different URLs).

## 3b. Replace the placeholder photos with your own

AgriXpert &rsaquo; **Images** (admin menu) lists every photo used on the value-chain and solution pages (currently stock Unsplash placeholders). Click **Choose from Media Library** on any one, upload or pick your own photo, and save — that image updates immediately everywhere that slot is used, no code change or redeployment needed. **Reset to Default** on a card puts the placeholder back.

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
