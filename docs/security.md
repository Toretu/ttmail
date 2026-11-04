# Security and Privacy

## Access roles

- Administrators: configuration, security, products, prices, deployment
- Campaign managers: campaigns, groups, sending approval, opportunities
- Volunteers: assigned customer and follow-up work
- Read-only users: reports and approved records

Use Entra ID security groups and least-privilege application, database, server, and Microsoft Graph permissions.

## Sign-in and roles (implemented)

- People sign in with their Microsoft 365 account (OpenID Connect authorization code flow with PKCE, using the same Entra app registration as the Graph connection). No passwords are stored by the application.
- Only users in the `users` table who are active can sign in. A Microsoft account alone is not enough. The first administrator is created when `AUTH_BOOTSTRAP_ADMIN` signs in and no admin exists.
- Roles: **viewer** (read), **editor** (also create, edit, import, delete), **admin** (also settings, mailboxes, users). Enforced on every `/api` call; `/api/health` and `/api/auth/config` are public.
- Role and active state are re-read on every request, so disabling a user takes effect immediately. Admins cannot change their own role or disable themselves.
- Sessions use an HttpOnly, SameSite=Lax cookie (Secure when `APP_URL` is https), are regenerated at sign-in, and expire after 8 hours without activity.
- State-changing requests carrying an `Origin` header from another site are refused (CSRF defence in addition to SameSite).
- The ID token is checked for audience, issuer, tenant, nonce and expiry. It is received directly from Microsoft over TLS, so its signature is not separately verified (OpenID Connect Core 3.1.3.7).
- The development login (`AUTH_DEV_LOGIN=1`) works only when `APP_ENV=development`. Never enable it in production.
- Not yet implemented: audit log of who changed what, and sign-in rate limiting beyond what Microsoft provides.

Planned role names in the original design (campaign managers, volunteers) are mapped onto the three roles above for now.

## Email safety

- Require campaign approval before sending.
- Send controlled batches.
- Send a test message first.
- Exclude unsubscribed contacts.
- Prevent duplicate sends.
- Include unsubscribe instructions.
- Record failures rather than marking them successful.
- Restrict Microsoft Graph application access to approved mailboxes.

Implemented: test mail required before approval, explicit approval by typing the recipient count (recorded with user and time), one message per contact, a personal unsubscribe link in every mail, unsubscribed contacts re-checked at send time, claim-before-send so nothing is sent twice, locked message and recipients while sending, automatic pause on Microsoft errors, and a per-minute rate limit. See [sending.md](sending.md).

## Data protection

- Use test data outside Production.
- Do not put secrets in lists or documentation.
- Restrict billing and payment data.
- Retain audit fields and stage history.
- Document deletion and retention rules before launch.
- Review access when volunteers leave.

## Incident handling

If customer data or a secret is exposed:

1. Stop the affected job or revoke the affected connection.
2. Revoke or rotate the exposed credential.
3. Record what happened without copying the secret.
4. Notify the administrator responsible for the tenant.
5. Assess affected data and users.
6. Document corrective action.
