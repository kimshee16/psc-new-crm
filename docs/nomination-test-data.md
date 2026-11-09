# Nomination dropdown UAT data — CV2P-74 / CV2P-170

Run against the local development database after the nomination catalogue migration:

```powershell
php artisan db:seed --class=NominationTestDataSeeder
```

Use the existing **admin@psccrm.test** account. The seeder does not create users
or change passwords. It runs only in local/testing environments, inserts missing
fixtures, and preserves edits when rerun. It does not run the general database
seeder or reset existing data. Public Client IDs below are reserved for these
fixtures; a conflict stops and rolls back the seed.

Search Organisations for **[UAT Nomination]**. Each organisation has legal and
trading names and two saved nominations: one internal and one external.

| Organisation | Sponsorship setup | Example subclass | Internal client | External nominee |
| --- | --- | --- | --- | --- |
| [UAT Nomination] No SBS Pty Ltd | No sponsorship record | 482 | 910074 — Ana Maria Santos | Morgan Lee |
| [UAT Nomination] Active SBS Pty Ltd | Active; expiry one year after initial seeding | 186 | 910170 — Daniel Min Park | Taylor Quinn |
| [UAT Nomination] Expired SBS Pty Ltd | Expired; expiry one year before initial seeding | 494 | 910074 — Ana Maria Santos | Casey Brooks |
| [UAT Nomination] SBS Not Started Pty Ltd | Not Started; no approval/expiry date | 491 | 910170 — Daniel Min Park | Riley Jordan |

The two clients use synthetic `example.test` email addresses and blank telephone
numbers. There are 4 organisations, 3 SBS records, and 8 saved nominations. All
are owned/assigned to PSC Admin. All four subclasses can be tested on any of the
four organisations; the example subclass is just the saved sample.

## Manual checks

1. Open any sample organisation and choose **Create nomination**. Visa subclass
   must start blank and be required. On the initial catalogue the only choices
   are 482, 186, 494 and 491, each with its configured full visa name.
2. Search for `Ana`, `Santos`, or `910074`, select the client, and choose a
   subclass. Create should save and open the nomination record.
3. Reopen the modal, select **External**, enter `Morgan Lee UAT`, choose a
   subclass, and create. The saved nominee should be text, without a linked client.
4. Repeat with 482 or 494 on the **No SBS**, **Expired SBS**, and **SBS Not Started**
   organisations. Creation must succeed: SBS-required metadata does not gate creation.
5. Leave the subclass blank: submission must be blocked. Choose a subclass then
   cancel; reopening must start blank again.
6. In **Configuration → Nomination Subclasses**, confirm the initial rows 482,
   186, 494 and 491. Initial SBS-required flags are Y for 482/494 only.
7. Add **407 — Training Visa** temporarily. Verify it appears in the creation
   dropdown. Edit its SBS-required flag and save; verify the configuration changed.
8. Remove 407 and confirm it disappears from new selections. To check historical
   retention, create a 407 nomination before removing it: its saved subclass must
   remain visible afterwards.

The seeder restores missing default rows but keeps existing catalogue edits and
extra rows. Remove the temporary 407 option manually when finished. It leaves
workflow configuration unchanged; names use current Visa workflow options or the
shared visa defaults. The initial four-row expectations assume the catalogue has
not already been customised.
