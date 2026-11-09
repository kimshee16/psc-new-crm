# PSC-96: Organisations list

Open **Organisations** in the sidebar. The list shows Organisation ID, trading
name, legal name, SBS held, Renew and an action icon to open the record. Existing
legal names remain stored in `organisations.name`; IDs retain `ORG-000001` format.

Trading name A–Z is the default sort, with missing trading names last. Each data
column can be sorted in either direction; Organisation IDs sort numerically.
Equal values use Organisation ID as a stable tie-breaker. ID and name column
searches, SBS held All/Y/N and Renew All/Flag/No flag filters work together. The
existing general search also accepts ABNs. Text searches are case insensitive
and treat `%`, `_` and `!` literally. Full public Organisation IDs are accepted.

SBS held is Y when the organisation has any SBS application whose status is
**Active**, otherwise N. Active can be selected in an SBS application's Summary.
Other application categories retain their existing status options. Indicators
summarise all SBS records on an accessible organisation; application details
continue to follow their existing assignment rules.

Renew is flagged if any Active SBS expires from today through the inclusive
calendar-month threshold: four months for Standard and two months for Accredited.
Month-end thresholds clamp to the last day of the target month. Expired records,
missing expiry dates and other sponsor types do not flag. Status and sponsor type
matching ignores case and surrounding spaces. Supported sponsor labels are
Standard, Standard Business Sponsor, Standard Business Sponsorship, Accredited,
Accredited Sponsor, Accredited Business Sponsor and Accredited Business Sponsorship.

Pagination offers 10 (default), 20 and 50 records and retains applied filters and
sort order. Applying filters, changing sort or changing page size starts at page 1.
**Download CSV** submits the current filters and exports all matching pages in
the selected order. The UTF-8 CSV includes the five data columns, quotes special
characters and neutralises spreadsheet formulas in user-entered names.

The list and export retain the existing organisation visibility boundary:
organisations created by the user or with an application assigned to the user.
Both endpoints require an authenticated, active account.

Validation: `php artisan test --compact --filter=Organisations` and
`npm run build`. No database migration is added by PSC-96.
