# AgriXpert Plugin — Administrator Guide

This covers day-to-day use of the AgriXpert admin dashboard inside WordPress. For installation steps, see `docs/INSTALL.md`; for the underlying design, see `docs/ARCHITECTURE.md`.

## Who can do what

| Role | Can do |
|---|---|
| WordPress **Administrator** | Everything below, automatically, from the moment the plugin is activated |
| **AgriXpert Admin** | Everything below |
| **AgriXpert Staff** | Verify registrations, manage the CRM, moderate the marketplace — but not Site Content, Insights, or Settings |

Assign these under **Users** in the normal WordPress way.

## The AgriXpert menu

A top-level **AgriXpert** menu appears in the WordPress admin sidebar with the following screens:

### Registration queues: Farmers / Buyers / Suppliers / Cooperatives / Enquiries

One screen per entity type, each a searchable, filterable, sortable list (WordPress's standard list-table UI). From here you can:

- **Search and filter** by status (pending / verified / rejected) and free-text search.
- **Verify or reject** a single submission, or select several with the checkboxes and apply a bulk action.
- **Export to CSV** — the button above the table exports the currently filtered list.
- **Reset** a decision back to pending if you need to revisit it.

A verification decision fires a notification email to the submitter automatically (see `docs/ARCHITECTURE.md` §9) — there is nothing further to send manually.

### CRM

The pipeline view for tracking relationships with registered farmers/buyers/suppliers/cooperatives after they've been verified: stage tracking, notes, and tasks with due dates.

### Follow-ups

A dedicated view of CRM tasks that are due or overdue. The same list is also emailed as a daily digest at 07:00 server time to staff with CRM access, so this screen is for checking on demand rather than the only way to see what's due.

### Marketplace

Moderation for marketplace listings (produce, equipment-for-hire, storage-for-hire) and their bookings/expressions of interest — approve, reject, or remove listings that violate your site's rules.

### Site Content

Edit the text values that populate the public-facing pages (home page headline, contact email and phone, and similar fields) without touching code or a theme file. Available keys are listed on this screen; reference one in a page with `[agrixpert_content key="the_key_name"]`.

### Insights

A lightweight CMS for articles/insights shown via `[agrixpert_insights]` on the public site — title, body, category, and an image URL (there is no file-upload field here; paste a URL to an already-hosted image, for example one from the WordPress Media Library).

### Staff

Admin-only. Add someone by email and choose **Staff** or **Admin**. If a WordPress user account with that email already exists, the matching AgriXpert role is granted immediately. **If it doesn't exist yet, create their WordPress user account first** — adding them here before their WordPress account exists records the intended role but does not grant it automatically once they do sign up (there is no equivalent of the registration-linking behavior that farmers/buyers/suppliers/cooperatives get on first login). This screen is also where a record of every staff/admin email is kept, independent of WordPress's own Users list.

### Settings

SMTP configuration and the branded-email logo. See `docs/INSTALL.md` §4 for the full walkthrough, including the `wp-config.php` constant override for production deployments.

## Routine tasks

- **New registration comes in** → appears as "pending" in its entity queue → review → Verify or Reject → submitter is emailed automatically.
- **Staff member needs access** → create their WordPress account first if they don't have one, then add them on the Staff screen (above) rather than assigning the role directly under Users — the Staff screen is also where a record of who has access is kept.
- **A listing needs removing** → Marketplace screen → moderate it there, not by editing the database directly.
- **Site copy needs a quick change** → Site Content screen, not a theme edit.
- **A farmer/buyer/supplier/cooperative asks about their own status, wants to update their details, or a cooperative wants to add/remove a member** → point them to the page on your site carrying the `[agrixpert_portal]` shortcode (see `docs/USER-GUIDE.md`) rather than handling it for them — they can do all of this themselves once logged in.

## Things to know about how this differs from a database with built-in access rules

There is no database-level safety net equivalent to what Supabase's Row Level Security provided in the original system (see `docs/ARCHITECTURE.md` §7 for the full explanation). In practice this means: only grant the AgriXpert Admin role to people who should have full access, and treat it with the same care as a WordPress Administrator account.
