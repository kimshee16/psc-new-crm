# CV2P-72: Organisation record update

The organisation page follows Michelle's supplied mockup: legal-name heading,
trading-name breadcrumb, page-level Edit details, side-by-side organisation
details and contact table, Sponsorship and Nominations tables, and collated tabs.
Contacts can be added using Add new and edited by selecting their names.

Sponsorship includes standard/accredited sponsors and Labour Agreements in the
existing SBS application category. The creation form suggests these sponsor
types while preserving existing values. Display references use SBS-, LAB-, NOM-
and PSC- prefixes with at least four digits; underlying IDs and routes are
unchanged. Home Affairs reference remains on the application detail page.
Status badges reflect saved statuses without changing application workflow rules.

Tabs are Notes, Tasks, RFI, Payments, Agreements and Quotes. The user confirmed
Agreements should be added while existing Quotes remain available separately.
Agreements are non-financial activity records (title, reference, status and due
date), added from an SBS/nomination application's Agreements tab. Application
type settings control whether that tab is enabled. Existing explicit settings
are preserved; enable Agreements there if needed.

Notes collate the existing application notes, including legacy text, with author,
date and source links. Add/edit notes on the source application. Tasks can be
added, edited, assigned to active staff, marked Pending/In progress/Done, and
deleted from the organisation page. Each task belongs to a visible SBS or
nomination. Source links are fixed after creation. Existing tasks without an
assignee remain visible as Unassigned, and legacy statuses remain visible until
changed. Adding tasks respects the source application's configured Tasks tab.

Organisation and application assignment boundaries remain in place for all
rollups and mutations; organisation ownership does not grant access to hidden
applications or their notes/tasks.

Deployment: run `php artisan migrate` for the nullable task assignee field and
`npm run build` for the updated styles.

Verification: organisation feature tests, including
`OrganisationRecordUpdateTest`, cover persistence, validation, draft recovery,
cross-organisation/assignment restrictions, note escaping, Agreements/Quotes
separation and Labour Agreement display. Browser verification was unavailable
because the browser access host was unavailable in this session.
