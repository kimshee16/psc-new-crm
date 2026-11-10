# CV2P-47 event registration database repair

Michelle's November 10 report shows MySQL error 1054: `leads.event_discovery_channel` is missing when the event registration form inserts its lead. The event referral table already exists. The screenshot does not establish why the column was omitted.

Deploy the updated files, then run this additive repair from the affected server's application directory:

```sh
php artisan migrate --force --path=database/migrations/2026_11_10_000002_ensure_event_discovery_channel_on_leads.php
```

This adds only the missing nullable column. Existing clients, leads, referral options and recorded answers are preserved. It does nothing when the column already exists. Existing leads without a recorded answer remain blank; the migration does not invent historical answers.

If the original event-referral migration is still pending, its updated guards also allow it to finish when the referral table already exists. Apply the project's remaining migrations through the normal deployment process.

Verify with a test registration on an active event: select a discovery channel, submit, confirm the thank-you page, then check that the linked lead has Source `Event`, the correct event and the selected discovery channel. A failed insert is inside a transaction, so a registration that failed with this error did not save its client changes or lead.

Do not use the `/run-migration` route for this repair: it runs `migrate:refresh` and rebuilds the database. The repair above requires no reset or reseeding.
