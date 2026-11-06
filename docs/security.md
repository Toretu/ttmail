# Security and Privacy

## Access roles

- Administrators: configuration, Microsoft 365 connection, mailboxes, users, products and prices
- Editors (campaign managers and volunteers doing customer work): companies, contacts, groups, campaigns, recipients, sending approval, sales, answers and replies
- Viewers: read-only

Use least-privilege application, database, server, and Microsoft Graph permissions.

## Sign-in and roles (implemented)

- People sign in with their Microsoft 365 account (OpenID Connect authorization code flow with PKCE, using the same Entra app registration as the Graph connection). No passwords are stored by the application.
- Only users in the `users` table who are active can sign in. A Microsoft account alone is not enough. The first administrator is created when `AUTH_BOOTSTRAP_ADMIN` signs in and no admin exists.
- Roles: **viewer** (read), **editor** (also create, edit, import, delete, approve sending, register sales), **admin** (also settings, mailboxes, users, products and prices – ADR-010). Enforced on every request handled by PHP, **deny by default**: only `/api/health`, `/api/auth/config`, `/auth/*`, the public `/unsubscribe` and `/reply` pages and `/` need no session. Static HTML and JS are served by the web server and contain no data.
- Role and active state are re-read on every request, so disabling a user takes effect immediately. Admins cannot change their own role or disable themselves.
- Sessions use an HttpOnly, SameSite=Lax cookie (Secure when `APP_URL` is https), are regenerated at sign-in, and expire after 8 hours without activity.
- State-changing requests carrying an `Origin` header from another site are refused (CSRF defence in addition to SameSite). The check compares `Origin` with the `Host` header, so a reverse proxy must pass the original host through (see [deployment.md](deployment.md)).
- The ID token is checked for audience, issuer, tenant, nonce and expiry. It is received directly from Microsoft over TLS, so its signature is not separately verified (OpenID Connect Core 3.1.3.7).
- The development login (`AUTH_DEV_LOGIN=1`) works only when `APP_ENV=development`. Never enable it in production.
- Not yet implemented: audit log of who changed what (beyond approvals and reply stage history), and sign-in rate limiting beyond what Microsoft provides.

## Public links in mail

- The unsubscribe link carries a random per-contact token that can only unsubscribe that contact (GET shows a confirmation, POST unsubscribes).
- The details link (`{{details_link}}`) carries its own random token per campaign and contact that expires after `REPLY_LINK_DAYS` days (default 90). It shows company and contact details and accepts proposed changes that an editor must apply. The unsubscribe token never opens this page (ADR-009).
- Preview and test mail contain only inactive sample tokens; personal tokens are created at the moment a mail is actually sent.

## Email safety

- Require campaign approval before sending.
- Send controlled batches.
- Send a test message first.
- Exclude unsubscribed contacts.
- Prevent duplicate sends.
- Include unsubscribe instructions.
- Record failures rather than marking them successful.
- Restrict Microsoft Graph application access to approved mailboxes.

Implemented: test mail required before approval, explicit approval by typing the recipient count (recorded with user and time), one message per contact, a personal unsubscribe link in every mail, unsubscribed contacts re-checked at send time, claim-before-send so nothing is sent twice, interrupted sends kept as "unknown" and never requeued automatically, locked message and recipients while sending, automatic pause on Microsoft errors, and a per-minute rate limit. See [sending.md](sending.md).

## Data protection

- Use test data outside Production. No production addresses are seeded by migrations; mailboxes are added in Settings.
- Do not put secrets in lists or documentation.
- Restrict billing and payment data.
- Retain audit fields and stage history.
- Contacts that unsubscribed or were sent mail cannot be deleted (ADR-008), so the suppression survives re-imports. A data-subject deletion request is handled by anonymising the contact's fields while keeping the row; the procedure must be documented before launch.
- Review access when volunteers leave.

## Incident handling

If customer data or a secret is exposed:

1. Stop the affected job or revoke the affected connection.
2. Revoke or rotate the exposed credential.
3. Record what happened without copying the secret.
4. Notify the administrator responsible for the tenant.
5. Assess affected data and users.
6. Document corrective action.
