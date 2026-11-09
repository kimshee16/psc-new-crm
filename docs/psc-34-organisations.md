# PSC-34: Organisation records

The page layout, sponsorship labels and collated tabs have since been updated
for [CV2P-72](cv2p-72-organisation-record.md). That document describes the current
organisation task controls, Notes and Agreements; the original data relationships
and access boundaries below still apply.

Open **Organisations** in the sidebar to search by legal/trading name, ABN or
Organisation ID, or create a record. Organisation references use `ORG-000001` and
are independent of Client IDs. Existing `organisations.name` values are retained
as legal names. Trading name, ABN and address are optional; ABNs accept 11 digits
with optional spaces. Contact persons can be added and edited independently.

**Add SBS** and **Create nomination** create organisation-owned application
records. Their IDs link to the existing application pages. Edit the additional
fields through **Edit details**, and use the existing Summary for status and
counsellor/migration agent assignments. New applications start as Not Started and
are assigned to their creator.

Nominees may have a name without a Client ID, or link to an existing public Client
ID. The nominee relationship is separate from application ownership. When the
entered name is empty, the linked client's current name is displayed. Visa links
reuse the existing Link/Unlink items mechanism, including reciprocal and legacy
links. Multiple linked visas are displayed with their current statuses.

Tasks, payments, RFI and quotes are stored in `application_activities`, attached
to individual SBS/nomination applications. Add records in the application tabs;
the organisation tabs collate them with source application links, counts and
pagination. Payments and quotes record an amount and currency; these forms do not
process financial transactions. Application-type tab configuration still applies
to the application pages; organisation rollups include existing records even if
their source tabs are subsequently disabled.

Access follows the existing assignment boundary: organisation creators and staff
assigned to an organisation's applications can open and edit its details and
contacts. Application tables, visa links, activity counts and rollups only expose
applications assigned to the current user. Organisation ownership alone does not
grant access to other staff's applications.

The implementation uses the requirements supplied with PSC-34. The linked Jira
tickets CV2P-74, CV2P-76 and CV2P-77 were inaccessible, so no additional workflows
from those tickets are assumed.

Deployment: run `php artisan migrate` and `npm run build`.
Validation: `php artisan test --compact`; feature coverage is in
`tests/Feature/OrganisationsTest.php` and `tests/Feature/ApplicationTabsTest.php`.
