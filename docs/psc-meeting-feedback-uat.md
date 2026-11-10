# PSC CRM meeting feedback adjustments

## Implemented

### CV2P-168 — Configuration usability

- Retained and tightened the existing side-panel navigation, with a labelled navigation landmark, current-section indication and keyboard focus styling.
- Applied consistent, compact cell spacing and vertical alignment across Configuration tables.
- Added matching row hover and keyboard-focus highlighting.
- Kept Save/Remove controls on one aligned row; wide tables scroll horizontally.
- Changed Highest Education Level ordering to unique, consecutive positions. Moving/inserting an option shifts the others; deleting an option closes the gap. Blank new positions append to the end.
- Added a migration that preserves existing display order, resolves duplicate positions, and creates a database uniqueness constraint.
- Updated the seeder to use the same ordering service so reseeding does not violate the constraint.

### CV2P-64 — Applications list and consistent navigation

- Application IDs now link directly to their records in the current tab.
- Client names link to the Client profile using the public Client ID, not the internal database key. Organisation-owned applications link to their organisation.
- Removed the redundant Applications Actions column and corrected the empty-table column span.
- Labelled both Critical Date bounds as inclusive and clarified that filters work together and each application has one Critical Date.
- Retained and tested the existing AND filtering, inclusive date boundaries, single-date model, export filtering and record assignment scope.
- Added Applications row hover feedback.
- Applied the same navigation convention to Clients: linked IDs/names and removed the open-only Actions column.
- Replaced Leads' open button with a linked Lead ID, preserving the existing Client link and duplicate indicator.

### CV2P-158, CV2P-165, CV2P-167 — Shared and inline notes

- Introduced one reusable sticky-note component for Clients, Leads and Applications, preserving the yellow/green/blue card styling.
- Client-linked Leads and Applications now read and save the same Client notes, including internal nomination nominees. Notes written from any linked record appear on the Client and its other linked records after refresh. Existing Client note-management permissions still control shared-note edits; source Lead/Application access is checked first. Records without a linked Client keep their own notes. Earlier record-specific notes remain available read-only under Earlier Lead/Application Notes without being copied or overwritten.
- Leads and Applications provide a Notes shortcut in the record header and expand Add Note automatically when the record has no notes. Application Notes remain visible when another configured tab is selected, without duplicating the section on the Notes tab.
- Added independent Add, Edit and Remove actions, with multiline text and newest-created notes first/leftmost.
- Author and creation time come from the authenticated user and server. Editing preserves the original author/time and displays the latest editor/time separately.
- Client Notes can be maintained without acquiring the whole-record edit lock. Notes also save independently of Lead and Application forms.
- Retained the existing Client note-management rules, Lead visibility rules and Application assignment rules for all note mutations.
- Preserved legacy text/JSON notes and paragraph breaks. Missing historical attribution is shown as unrecorded rather than invented.
- Kept the original Lead enquiry visible separately from Notes.
- Prevented full-record saves from overwriting independently saved notes.
- Added transactional conflict detection to reject stale note saves, input/storage limits, output escaping and delete confirmation.
- Kept drafts after failed requests and preserved drafts in other note cards when one card is saved. Added an unsaved-note navigation warning.
- Notes remain in the existing `notes` columns; no note-data migration is needed. Current limits are 50 notes per record, 10,000 characters per new/edited note, and 60,000 encoded bytes per record.

## Deferred by request

Authentication/security (CV2P-164) and user access/permissions configuration (CV2P-166) were left unchanged. No MFA, account or role-matrix changes were retained. Jira statuses were not changed. The broader Phase 2 programme and production approval processes were not implemented from meeting status notes.

## Verification and local rollout

- Full automated suite: 141 tests passed, 2,164 assertions, including existing manual Lead creation and allocation tests.
- Frontend production build, Blade compilation and PHP formatting checks passed.
- Added regression coverage for shared note lifecycle, author attribution, multiline text, legacy ordering, XSS escaping, denied access, stale writes, invalid input, independent record saves, and education-position shifting.
- Live browser visual testing was unavailable in this session.
- The local MySQL instance was initially stopped. A brief start allowed migration-status inspection (all earlier migrations applied), but subsequent connections failed and its log reported InnoDB consistency errors. The new migration was **not applied** to the local MySQL database; no database recovery was attempted.

After the database is healthy, back up the education-level configuration and run:

```sh
php artisan migrate --path=database/migrations/2026_10_28_000001_make_education_level_order_unique.php
npm run build
```

For UAT: check compact tables and keyboard/hover states in each Configuration section; move an education option into an occupied position; verify combined Application filters and record links; add multiline notes in all three modules; edit Client Notes outside Edit Mode; and use two tabs to confirm stale note changes are rejected while drafts remain available.
