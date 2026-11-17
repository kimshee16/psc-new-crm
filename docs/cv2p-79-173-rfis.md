# CV2P-79 and CV2P-173: RFIs

Application records now share RFI data with the global RFIs page. Each enabled RFI tab supports creation, editing, private request-letter and proof uploads, notes popups, a confirmation before closing, and an active count that excludes Closed records. Administrators can delete RFIs they can access.

The RFI tab badge highlights the outstanding count in teal and labels it for screen readers. Every non-Closed status and legacy null status is outstanding. The shared outstanding query also drives the global list's default filter. The badge is available on other application tabs, independent of RFI pagination, and is recalculated after creating, closing, reopening or deleting records.

The global page includes RFIs assigned directly to the user and RFIs on applications where the user is the primary counsellor, secondary counsellor or migration agent. These conditions do not produce duplicate rows. Closed RFIs are hidden on each fresh page visit; the Display Closed RFIs switch applies only to the current URL. Users can filter request types, statuses, officers and date ranges, search application IDs, sort, paginate and download the filtered CSV.

Uploads are stored on the private local disk and served through authenticated RFI access checks. Supported files are PDF, JPG, PNG and Word documents, up to 10 MB each. Existing files are retained on edits unless replacements are supplied. Failed saves remove newly uploaded files, and replacements remove the superseded files after saving.

Apply `php artisan migrate --path=database/migrations/2026_11_16_000001_extend_application_rfis.php --force` when deploying. The migration extends existing application activity rows rather than deleting or replacing earlier RFIs. Legacy titles, references and deadlines are retained; unknown received dates and documents stay blank and must be completed when editing. The old generic activity endpoint cannot create incomplete RFIs.

Validation:

- `php artisan test --compact --filter='ApplicationRfisTest|RfisPageTest|ApplicationTabsTest|OrganisationsTest'`
- `npm run build`
- Browser checks cover the close confirmation, notes popup, conditional Other field, document upload/view, filtering, active counts and CSV download.

The full regression suite also has two existing `RecordNotesTest` conflict failures unrelated to RFIs.
