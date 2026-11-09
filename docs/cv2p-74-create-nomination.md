# CV2P-74: Create Nomination Application

Michelle's supplied latest revision removes the former SBS eligibility rules.
The existing PSC-85 nomination modal already implements the updated flow:

- Launch Create nomination from an organisation's Nominations section.
- Show the legal and trading names as plain text, without hyperlinks.
- Require an existing client selected by name/public Client ID, or a full name
  entered with the External checkbox selected.
- Require Visa subclass, initially blank.
- Provide Create and Cancel; cancelling discards the draft.
- Create the organisation-owned Nomination without an SBS status, presence or
  expiry requirement, and without subclass-dependent sponsorship rules.

No new UI or database change was needed for these confirmed requirements.
Additional regression coverage exercises internal and external nominees across
the former 482/186/494/DAMA pathways with absent, Not Started, Active-but-expired
and Expired sponsorships. These test values do not define the production catalogue.

The live CV2P-74 and CV2P-170 descriptions were read on 6 November 2026.
`NominationCreation::subclasses()` now controls modal display and server-side
validation using the database-backed CV2P-170 catalogue, seeded with 482, 186,
494 and 491. Full visa labels are resolved from Visa workflow subclass choices,
falling back to the shared workflow creation options for unmatched codes.
The catalogue does not fall back to the full general visa list, even when empty.
Configuration > Nomination Subclasses lets administrators add, edit and remove
options and maintain the SBS-required flag (initially Y for 482 and 494 only).
The flag is metadata; it does not gate creation under the current CV2P-74 rules.
The nomination summary uses the same catalogue while retaining saved retired
values on existing records. See `docs/cv2p-170-nomination-subclasses.md`.

Verification: `NominationCreationTest` covers mandatory input, blank defaults,
client/external entry, configuration, source-organisation access, draft recovery
and sponsorship-independent creation. The existing rendered-markup DOM checks
exercise opening, cancellation, search, selection and input mode changes.
