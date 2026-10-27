# PSC Events — requirements WIP

The confirmed scope supports administrator configuration of event names, dates/times,
locations, active status, and generated registration-form links. Event IDs are
automatically assigned and remain stable when details change.

- Configuration is available to Admin and Super Admin users under **PSC Events**.
- New events start inactive in the configuration form. Activating an event makes
  its existing `/events/{id}/register` form available.
- Dates/times use the application timezone (currently UTC).
- Registration creates a lead with source `Event`, the event ID, and a snapshot
  of the event name. Renaming or deactivating an event preserves existing history.
- Lead event selections use IDs to distinguish events with the same name.
- Deactivate events to close registration; configuration does not delete events.
- Last Name and Date of Birth are optional on event registration, as are Middle Name
  and Highest education level. Supplied names and dates still undergo validation.
- Complete last-name/DOB submissions retain first-name, last-name and DOB client matching.
  If either optional identity field is absent, linking requires exactly one client matching
  first name and email plus any supplied last name or DOB. Otherwise a new client is created.
  Omitted optional identity fields do not erase an existing client's recorded details.

## Event discovery channel

- Admin and Super Admin users manage referral options under **Configurations > Event Referral**.
  Options have a name, sort order, and active status. The initial active options, in order,
  are WhatsApp, Instagram, Facebook, Website, Word of Mouth, and Celebgram Campaign.
  The defaults are added once by migration without overwriting existing options.
- Event registration requires **How did you hear about our event?**. Only active
  configured options are accepted, including when a previously loaded form is submitted.
- The selected label is saved on the lead as **Event discovery channel**, displayed
  read-only when Source is `Event`. Changing Source hides or shows the field without
  discarding the original answer. Older leads display `Not recorded`.
- Renaming or deactivating an option does not change answers already recorded on leads.
- With no active options, the public form shows a temporary-unavailability message
  and disables registration until an administrator activates an option.

The requirement remains WIP. The following are unresolved, not implemented rules:
custom event-ID formats, external registration URLs, per-event timezones,
automatic registration opening/closing dates, capacity or duplicate-registration
policies, and any additional fields or registration workflow.
