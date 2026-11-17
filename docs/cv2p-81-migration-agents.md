# CV2P-81: Migration Agents configuration

Configuration → Migration Agents follows the ticket's reference fields: first and last name, signature, registration number and optional email. Signature drawing is replaced with PNG/JPG/WebP upload, up to 2 MB, with a preview. Registration numbers remain strings, retaining leading zeros. Administrators can add, edit and remove unused agents; all signature files are private and served through an administrator-only endpoint.

An optional CRM user link makes the professional details available as `User::migrationAgentDetails`, `Application::migration_agent_details` and `ApplicationWorkflow::migration_agent_details`. Existing workflow and Application `migration_agent_id` values continue to reference Users. Configuring an agent does not create a login, change a user's role or grant case access. The existing assignments and user records remain intact; no registration data or signatures are invented or seeded.

Editing without an upload keeps the current signature. Successful replacement deletes the old image, and failed uploads/saves retain the previous record and clean up new files. Removing an unused agent deletes its signature. A profile linked to a User assigned as a migration agent on any workflow or Application cannot be removed or relinked, preserving the details needed by existing records. Its professional details and signature remain editable.

Deployment: commit and push the completed work to `origin/main`, then follow `docs/deployment/git-staging.md` to fast-forward staging and build assets there. Apply `php artisan migrate --path=database/migrations/2026_11_17_000001_create_migration_agents_table.php --force` and refresh route/view caches. The migration only creates the new profile table; it does not change existing user, workflow or Application assignments.

Validation: `php artisan test --compact --filter='MigrationAgentConfigurationTest|UserConfigurationTest|ApplicationSummaryTest|ApplicationCreationTest'`, `php vendor/bin/pint --test`, and `npm run build`. Browser checks cover the configuration list, upload preview, saved signature, edits and responsive layout.
