# CV2P-75 — Nomination Application Record

Organisation nominations now open an editable record with Summary and Nomination
cards and a shared **Save changes** action. The organisation breadcrumb, legal
name, trading name and generated `NOM-` reference come from the saved records.

The record supports internal client search or an external nominee, configured
nomination subclasses, status (including Appealing), priority, critical date,
eligible counsellors and migration agent, sponsorship, position title, six-digit
ANZSCO code, annual AUD salary, lodgement/reference and outcome dates. Google
Drive opens the saved folder URL; Actions includes a link to configure that URL.
Folder URLs must use HTTPS on `drive.google.com`.

Approved, Refused and Withdrawn outcomes require an outcome date. Refused shows
the appeal fields; a final outcome requires its date, and final approval also
requires expiry. Original outcome remains Refused after an appeal. Not reviewable
cannot have an appeal deadline. Moving away from refusal clears appeal data;
expiry is retained only for original or final approval.

Sponsorship defaults at creation to the accessible, active, unexpired SBS for
the same organisation when the configured subclass requires SBS. An SBS with a
future approval date, an expired SBS, or a labour agreement is not an automatic
SBS default. The dropdown also permits explicit selection of the organisation's
other accessible sponsorships. A missing active SBS raises a warning and does
not block saving. Changing subclass in the form updates the default sponsorship.

Visa links use the shared Linked Items dialog. It suggests active visas under
the nominee and supports exact application ID lookup. One nomination can link
to one visa, and one visa can link to one nomination. The server checks both
directions, legacy links and links hidden from the viewer. Linking copies and
locks nominee/subclass; unlinking clears both fields. Linked visa updates keep
the stored nomination identity in sync. Batch replacements remove the previous
link before adding the new one, within one transaction.

Default nomination tabs are Notes, Tasks, LMT, RFI, Payments and Agreements.
Application Type configuration controls visibility, including disabling all
tabs. Notes use the existing note editor; LMT uses persisted activity records.
Other application categories retain their previous default tab set.

## Validation

Apply the additive migration with `php artisan migrate`. Focused coverage is in
`tests/Feature/NominationRecordTest.php`, alongside the existing creation,
linking, configuration and organisation tests. Browser validation used a separate
SQLite fixture database to exercise saved appeal details, client search,
internal/external switching, linking/locking/unlinking, subclass-driven SBS
defaults and desktop/tablet layouts without changing development records.
