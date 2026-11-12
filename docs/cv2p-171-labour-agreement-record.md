# Labour Agreement application record (CV2P-171)

Selecting Labour Agreement in an organisation's Add sponsorship modal creates a LAB-prefixed record and opens the same editable two-card workflow as CV2P-77. The heading, read-only ID, sponsor type, nominations messages, save notifications and application-list description identify it as Labour Agreement. TRN is absent from the form and rejected on creation and both update routes.

Summary, staff selection, notes, four sponsorship milestones, lodgement, outcome and conditional appeal fields follow the SBS workflow. The creator defaults to Counsellor 1 and the first SBS workflow provides the migration agent. Saving requires an eligible Counsellor 1 and Migration Agent. First lodgement sets Pending Decision / Awaiting decision; later saves preserve decisions. Agreements activities update the service-agreement milestone when sent or digitally signed.

Each organisation can have one Active Labour Agreement independently of its Active SBS. Conflict checks lock the organisation and include records assigned to other staff. Approval, appeal dates and required expiry dates use the same validation as SBS, without requiring TRN. The scheduled `sbs:expire` command and authenticated CRM requests expire both sponsorship types inclusively.

Default tabs are Tasks, RFI, Nominations, Payments and Agreements, using the existing shared SBS Application Types configuration. Explicit tab settings, including Quotes, remain respected. Linked nominations are read-only, paginated and scoped to the sponsorship, organisation and viewing staff member. Labour Agreements do not satisfy nomination subclasses that explicitly require an active SBS.

Migration `2026_11_12_000001_backfill_labour_agreement_outcomes` carries legacy Labour Agreement approval dates into blank outcome fields without replacing existing decisions, expiry dates or references. It is safe to repeat and retains decisions on rollback.

Deploy with `php artisan migrate` and `npm run build`; keep the Laravel scheduler running. `LabourAgreementRecordTest` covers the record, creation, validation, workflow transitions, active conflicts, expiry, access, nominations, agreement events and migration. Existing SBS and organisation tests cover shared integration.

Validation on November 12, 2026: the focused sponsorship, organisation and tab suites passed (36 tests), the production build passed, and Pint passed for the changed PHP files. The full suite passed 257 tests and reported two existing `RecordNotesTest` HTTP 409 conflicts. Both conflicts also reproduced with the original Application model and SbsRecord service restored; they concern shared Client notes in Study applications, separate from Labour Agreement notes.
