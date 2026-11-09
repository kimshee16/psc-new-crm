# CV2P-76 — Add Sponsorship

The Organisation's **Add sponsorship** action opens a modal with read-only legal
and trading names, matching the Jira reference. Sponsorship type is required and
offers exactly **SBS** or **Labour Agreement**. Cancel, Close and Escape dismiss
the dialog without creating a record; opening it again resets the choice.

Create persists the sponsorship against the visible organisation, assigns the
creator as primary counsellor, and opens the saved application. Defaults remain
Not Started and Medium priority, with blank optional details. SBS stores
`Standard Business Sponsor` and receives an `SBS-` reference; Labour Agreement
stores `Labour Agreement` and receives a `LAB-` reference. Both use the existing
SBS sponsorship category so organisation tables, activities and nomination
sponsorship selection continue to work. Record headings distinguish the types.

The server rejects missing, arbitrary and Accredited Business Sponsor choices
on creation. It resolves organisation and ownership itself. Validation failures
reopen the modal, retain the selected type and show errors. Submission disables
Create while navigating. Existing create URLs open the same modal.

The full SBS record requirements belong to CV2P-77. CV2P-171 currently has no
Labour Agreement record specification; this ticket creates and opens that record
using the existing sponsorship details editor, summary and configured tabs.

Validation: `php artisan test --filter=SponsorshipCreationTest`, followed by the
organisation, nomination and shared application regression tests. Tests cover
both branches, defaults, references, persistence, invalid choices, validation
recovery, authentication and organisation access.

Completed validation: 224 tests passed with 3,740 assertions, including four new
creation tests with 76 assertions. PHP syntax checks and `git diff --check` passed.
On this Windows sandbox, PHPUnit's bootstrap readability check fails even though
PHP can load Composer. The same suite ran successfully using a temporary local
configuration with the redundant bootstrap attribute omitted and test/source
paths resolved to the project. Browser visual validation could not run because
the in-app browser failed to attach on both attempts.
