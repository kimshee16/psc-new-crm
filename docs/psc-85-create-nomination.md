# PSC-85: Create nomination from an Organisation

The supplied latest [CV2P-74 revision](cv2p-74-create-nomination.md) confirms this
flow and removes the earlier SBS eligibility rules. Its CV2P-170 subclass
catalogue remains an external dependency pending the exact options.

**Create nomination** in an Organisation's Nominations table opens the modal from
the supplied reference. The organisation's legal and trading names are plain text.
Nominee and Visa subclass start blank and are required. Cancel, Close and Escape
discard the draft and restore focus to the launch button.

Search existing clients by name (including middle name and multiple name parts)
or exact public Client ID, then select a result with the mouse or arrow keys and
Enter. Search returns up to 20 names and public IDs; refine the search for more
specific results. It uses the same global discovery scope as Client Search and
requires access to the source Organisation. The external checkbox switches to
mandatory free-text full name entry and clears the client selection.

Creation validates the nominee and subclass server-side and creates an
organisation-owned Nomination, assigned to the creator, with Not Started status
and Medium priority. Internal nominees use `nominee_client_id`, independently of
application ownership. External names use `nominee_name` without a client link.
The subclass uses the existing `nomination_pathway` field so it appears in the
application Summary and list. Position title is no longer required at creation.
No SBS presence, status, expiry, or subclass-dependent gate is applied.

CV2P-170 was inaccessible in this session. Until its options are provided, the
modal and server validation share the existing
`applications.creation_options.visa_subclass` catalogue. Set the optional
`applications.nomination_subclasses` configuration array to the confirmed
nomination-specific options once available. This does not change other visa
application choices. No immigration eligibility rules are inferred from this list.

The old Nomination creation URL redirects back to the Organisation and opens this
modal. SBS creation and application detail editing retain their existing pages.
Validation errors reopen the modal with its draft; closing it clears the draft.

Verification: `php artisan test --compact`, `npm run build`, and targeted Pint
checks. `NominationCreationTest` covers validation, configuration, internal and
external nominees, no-SBS creation, public ID resolution, search, draft recovery,
and access control. Browser access was unavailable for screenshot verification.
An isolated jsdom check of the rendered Blade markup passed 35 interaction checks,
including mouse/keyboard selection, mandatory fields, mode changes, cancellation,
escaped search results, lookup failure feedback and restored validation drafts.
