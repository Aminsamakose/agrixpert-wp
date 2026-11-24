# Verification-status bug: diagnostic and one-time data fix

## What was wrong

`includes/admin/class-admin-menu.php`'s "Verify" / "Reject" / "Reset to
Pending" actions (row actions and bulk actions, in the verification
queue screens) were passing their short action verb straight into
`AgriXpert_Verification::decide()`, which writes it directly into the
`verification_status` column. That column, and the
`agrixpert_verification_decided` notification hook, expect exactly
`'verified'` / `'rejected'` / `'pending'` — but the short verbs are
`'verify'` / `'reject'` / `'reset'`. "Reset" happened to already map to
`'pending'` in the old code, so resetting a registration worked
correctly; verifying or rejecting one did not.

**Effect:** clicking "Verify" or "Reject" wrote the literal string
`'verify'` or `'reject'` into `verification_status` instead of
`'verified'`/`'rejected'`, and — because the notification email only
fires on an exact `'verified'`/`'rejected'` match — the registrant was
never emailed that their registration had been decided.

This has now been fixed in the code (`class-admin-menu.php`'s new
`map_decision_to_status()`, applied on both the single-row and
bulk-decide paths). Plugin version bumped to 1.0.1.

## What you need to check and fix in the database (one-time, manual)

The code fix only changes behavior going forward. Any registration you
already clicked "Verify" or "Reject" on before this fix was deployed may
still have the wrong string sitting in its `verification_status`
column. Run these against your live WordPress database (phpMyAdmin,
Adminer, or `wp db query` via WP-CLI if you have it — replace `wp_` with
your actual table prefix if it differs):

### 1. Check first — see if this actually affected any rows

```sql
SELECT 'farmers' AS t, verification_status, COUNT(*) FROM wp_agx_farmers GROUP BY verification_status
UNION ALL
SELECT 'buyers', verification_status, COUNT(*) FROM wp_agx_buyers GROUP BY verification_status
UNION ALL
SELECT 'suppliers', verification_status, COUNT(*) FROM wp_agx_suppliers GROUP BY verification_status
UNION ALL
SELECT 'cooperatives', verification_status, COUNT(*) FROM wp_agx_cooperatives GROUP BY verification_status;
```

Look at the output. You expect to see only `pending`, `verified`, and
`rejected` as values. **If you see `verify` or `reject` anywhere in that
list, those are the affected rows** — registrations that were actually
decided but never correctly recorded or notified.

### 2. If step 1 found affected rows — fix them

Run one at a time, and read the "rows affected" count each `UPDATE`
reports before moving to the next:

```sql
UPDATE wp_agx_farmers      SET verification_status = 'verified' WHERE verification_status = 'verify';
UPDATE wp_agx_buyers       SET verification_status = 'verified' WHERE verification_status = 'verify';
UPDATE wp_agx_suppliers    SET verification_status = 'verified' WHERE verification_status = 'verify';
UPDATE wp_agx_cooperatives SET verification_status = 'verified' WHERE verification_status = 'verify';

UPDATE wp_agx_farmers      SET verification_status = 'rejected' WHERE verification_status = 'reject';
UPDATE wp_agx_buyers       SET verification_status = 'rejected' WHERE verification_status = 'reject';
UPDATE wp_agx_suppliers    SET verification_status = 'rejected' WHERE verification_status = 'reject';
UPDATE wp_agx_cooperatives SET verification_status = 'rejected' WHERE verification_status = 'reject';
```

### 3. Notification emails that were missed

The data fix above corrects the stored status, but it does **not**
retroactively send the "you've been verified/rejected" email that
should have gone out at the time (the `agrixpert_verification_decided`
hook only fires at the moment `decide()` runs, not from a later raw
`UPDATE`). If you want those registrants notified now:

- Easiest: after running the SQL fix above, re-open each affected
  registration in the verification queue and click "Reset to Pending,"
  then click "Verify" (or "Reject") again through the normal admin UI.
  That re-runs the fixed code path and sends the email correctly, at
  the cost of a slightly later verified/rejected timestamp.
- Only worth doing for registrations where the registrant genuinely
  needs that email — if it has been a long time and they already know
  their status some other way, this step may not be necessary. Your
  call.

### Before you touch the database

Back up the four tables (or the whole database) before running the
`UPDATE` statements, as ordinary precaution — this is a straightforward
fix but it is still a direct write to production data outside the
plugin's own code.

## Where this is also fixed

The `agrixpert-core` rebuild (not yet deployed) has the same fix
already in place, plus the same mapping reused in its new REST API's
decide route. No separate action needed there once you cut over —
just be aware the data-level check/fix above applies to whichever
database `agrixpert-core` points at too, since it's the same tables.
