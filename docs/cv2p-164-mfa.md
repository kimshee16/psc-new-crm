# CRM two-step verification

Each staff member needs an individual CRM account and a private email inbox for invitations and password recovery.

- Apply the additive users migration and install Composer dependencies before enabling `CRM_REQUIRE_MFA=true`. Refresh the configuration cache after enabling it.
- On the next sign-in, staff enter their existing CRM password, add the setup key to a time-based authenticator app and confirm a six-digit code. Subsequent sign-ins require an authenticator code after the password.
- The verification screen is valid for five minutes. Existing sessions must sign in again when MFA is enabled. MFA sign-ins use the configured Laravel session lifetime and omit persistent sign-in cookies.
- For a forgotten password, verify the staff member’s identity and use **Send Invite** in Configuration → Users. Setting a new password keeps their enrolled authenticator and invalidates earlier MFA sessions.
- For a lost authenticator, verify the staff member’s identity through an independent channel. An authorised operator can run `php artisan crm:reset-mfa staff@example.com` from the deployed checkout, then send a fresh invitation to the verified private inbox. The reset rotates the password and remember token, clears the authenticator and invalidates pending verifications. The staff member sets a new password and enrolls a replacement authenticator.

Before Production, confirm who approves recovery requests, validate private inboxes and invitation delivery, and agree on the rollout window with PSC.
