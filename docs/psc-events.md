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

The requirement remains WIP. The following are unresolved, not implemented rules:
custom event-ID formats, external registration URLs, per-event timezones,
automatic registration opening/closing dates, capacity or duplicate-registration
policies, and any additional fields or registration workflow.
