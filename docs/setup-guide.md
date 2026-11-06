# Setup Guide

## Prerequisites

- Existing server with a supported web runtime
- Linux server with a supported PHP version
- Composer and required PHP extensions if the selected framework requires them
- MariaDB 10.6 database and administrative access
- Domain name and HTTPS certificate
- Microsoft 365 mailboxes `programm@paradisskolekorps.no` and `salg@paradisskolekorps.no`
- Permission to create an Entra ID app registration and grant administrator consent
- Backup storage
- Administrator and volunteer user model
- Optional Entra ID application registration for Microsoft 365 sign-in

Microsoft 365 is optional for the application itself. It may still be used for email, identity, calendars, or document storage.

## Installation order

1. Create Development, Test, and Production deployments.
2. Create a private MariaDB 10.6 database and database user.
3. Run versioned database migrations.
4. Configure the PHP application and install production dependencies.
5. Configure HTTPS and secure headers.
6. Register the application in Microsoft Entra ID.
7. Grant only the required Microsoft Graph permissions.
8. Restrict Graph application access to the approved mailboxes.
9. Configure the Microsoft Graph mail adapter for the approved mailboxes. Follow [office365-setup.md](office365-setup.md) and verify with Settings → Test connection (`/settings.html`).
10. Add the approved mailboxes under Settings → Mailboxes (migrations do not seed them).
11. Configure scheduled jobs for campaign batches and reply fetching ([sending.md](sending.md), [replies.md](replies.md)).
12. Set `AUTH_BOOTSTRAP_ADMIN`, sign in once as that address, then add users and roles under Settings → Users.
13. Configure encrypted backups and retention.
14. Deploy the web application.
15. Run the smoke test.

## Shared mailbox

The initial approved campaign mailboxes are `programm@paradisskolekorps.no` and `salg@paradisskolekorps.no`. The application will use Microsoft Graph to send and read replies. Additional mailboxes may be configured per project or campaign only after approval. Grant the Graph application access only to approved mailboxes, not the entire tenant. Configure reply folders or matching rules if automatic reply matching is implemented.

Do not create a separate mailbox per project unless the need, permissions, and licensing impact are documented.

## PHP and Microsoft Graph checklist

- [ ] Confirm PHP 8.3 and required extensions.
- [ ] Install Composer dependencies without development packages in Production.
- [ ] Create an Entra app registration.
- [ ] Grant and document only required Microsoft Graph permissions.
- [ ] Restrict the app registration to approved mailboxes.
- [ ] Store the client secret or certificate outside the source tree.
- [ ] Configure a rotation owner and expiry reminder.
- [ ] Test sending from the default mailbox.
- [ ] Test sending and reply retrieval for `programm@paradisskolekorps.no`.
- [ ] Test sending and reply retrieval for `salg@paradisskolekorps.no`.
- [ ] Test a blocked or unapproved mailbox.

## Self-hosted server checklist

- [ ] Firewall exposes only HTTPS and required administration ports.
- [ ] MariaDB is not publicly exposed.
- [ ] TLS certificate is valid and renews automatically.
- [ ] Application runs as a restricted service account.
- [ ] Secrets are outside the source tree.
- [ ] Backups are encrypted and restoration has been tested.
- [ ] Security updates and monitoring are assigned to an owner.

## Smoke test

1. Create a test company.
2. Create a test contact.
3. Add the contact to a test group.
4. Create a test campaign.
5. Generate recipients.
6. Confirm that duplicate and unsubscribed contacts are excluded.
7. Send a test email.
8. Approve and send one test batch.
9. Confirm the recipient status is updated.
10. Reply from the test mailbox.
11. Confirm the reply appears in the follow-up queue.
12. Move the opportunity through the stages.
13. Create a test advertising sale.
14. Confirm that campaign totals update.

## Completion

Record the setup date, environment, solution version, installer, configuration changes, and smoke-test result in the deployment record.
