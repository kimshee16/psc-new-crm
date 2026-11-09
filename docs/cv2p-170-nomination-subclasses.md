# CV2P-170: Nomination subclass catalogue

Requirements read directly from CV2P-74 and CV2P-170 on 6 November 2026.

- Default catalogue, in ticket order: 482, 186, 494, 491.
- SBS required: true for 482 and 494; false for 186 and 491.
- Full visa names follow `ApplicationWorkflow.summary_options.visa_subclass`
  for Visa workflows, then the shared visa creation options for unmatched codes.
- Configuration > Nomination Subclasses provides administrator-only add, edit
  and remove controls. Options must match a configured numeric visa subclass;
  duplicate subclasses are rejected.
- The creation dropdown remains mandatory and initially blank. Display and
  server validation share the catalogue. Unrelated general visa options,
  removed choices and an empty catalogue cannot bypass validation.
- Nomination summary choices use the same catalogue and retain an existing
  record's saved pathway after catalogue removal or visa-name changes.
- Existing nominations retain their saved pathway text. Catalogue editing does
  not rewrite historical records.
- SBS-required metadata is available for subsequent nomination-record work.
  CV2P-74 explicitly removes sponsorship eligibility checks during creation.

Deployment: run the additive migration
`2026_11_06_000002_create_nomination_subclasses_table.php` before serving the update.

Validation: `NominationCreationTest` and `NominationSubclassConfigurationTest`
cover creation, the exact default catalogue, rendered dropdown options and blank
selection, workflow-name lookup, add/edit/remove behaviour, retained historical
values, access control, invalid input, and sponsorship-independent creation.
