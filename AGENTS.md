# User communication preferences

- In every session, automatically include a concise, copy-ready daily accomplishment update after completing requested ticket work, including follow-up fixes and test data.
- Provide one line per ticket, using its ticket ID when known and a concrete past-tense description of what was delivered.
- Put the accomplishment lines clearly at the end of the final response, separate from explanation and validation details. Never wait for the user to request them.
- Report only work actually completed in the available conversation or verified records. Do not infer completed work or ticket IDs from screenshots alone.

# Jira worklog workflow

- After completing development work, check the relevant Jira tickets and record missing worklogs using the daily accomplishment text. This is the user's standing request for future development work.
- The user's total working time is 4 hours per workday: allocate 4h for one ticket, 2h each for two tickets, or 1h/1h/2h for three tickets, assigning 2h to the heaviest task based on the available evidence.
- Use the actual work date, including the dates supplied in a daily tracker when backfilling. A ticket worked on across multiple dates can have a separate entry for each date.
- Check existing worklogs before adding entries, preserve existing logs, and avoid duplicate entries. Account for existing time before allocating the day's 4h.
- When a daily tracker is supplied, use the accomplishment details immediately to the left of its status column as the worklog description. Do not invent missing or clipped text.
- Format each worklog note as a date and hours line (for example, "November 2, 2026 - 2 hours spent"), followed by a bullet containing the accomplishment.
- Start the project's daily work at 10:00 AM Asia/Manila by default, unless the user supplies another start time. Use an explicit +08:00 offset when recording Jira timestamps; account for existing entries before scheduling additional time.
- Example for a single ticket day: started `2026-11-09T10:00:00.000+0800`, time spent `4h`, note `November 9, 2026 - 4 hours spent`, a blank line, then `- CV2P-76: <concrete completed accomplishment>`.
- The daily total for this project is 4 HOURS, including development, fixes, test data and validation; do not log 4 hours per ticket or add duplicate hours for follow-ups. If existing records conflict or the allocation cannot be established, clarify before adding more time.

# Daily accomplishment spreadsheet workflow

- After completing and validating requested ticket development, automatically update the user's existing Google Sheet through Spreadsheets GPT Workspace, then record the corresponding Jira worklog through Atlassian. These updates are authorized by the user's standing workflow; no additional routine confirmation is needed.
- Tracker: https://docs.google.com/spreadsheets/d/15Fmi4c_pEsJaFzDmVVJY1mt3QfYOWKP5DMTjzUtgRJ0/edit#gid=0 ; worksheet `Daily Accomplishments` (verify its current title and structure before writing).
- Match the existing columns: A Date, B Jira Ticket, C Phase, D Task, E Actual Work Done / Output, F Status, G Hours Logged, H Comments.
- Use the actual work date in Asia/Manila with `MM/DD/YYYY` display, the verified ticket ID/title and phase, concise past-tense accomplishments with `- ` bullets in column E, `Done` only for completed work, and numeric hours in G. Keep Comments blank unless there is a useful requested comment. Never invent completed work or ticket IDs from screenshots.
- Read current entries first. Add or update the day's entry without overwriting unrelated content; preserve the user's one-row-per-workday style, line breaks, blank weekly separators, formatting and green Done status. Multiple tickets for a day belong in that day's row with corresponding task/output lines. Do not duplicate a date/ticket/accomplishment or increase the 4-hour daily total on a follow-up.
- Use the exact accomplishment text in column E (immediately left of Status) as the Jira worklog bullet text, including ticket IDs where written. Format the worklog with the full date and allocated hours on the first line, a blank line, then the accomplishment bullet(s), matching the user's screenshot.
- Check the relevant ticket's existing worklogs and the user's other project worklogs for the date before adding time. Preserve existing entries and apply the daily allocation described above.
- Verify the spreadsheet values and saved Jira worklog after writing. If a connector lacks permission, report the specific access issue and preserve the finished accomplishment text for retry; do not claim it was recorded.
