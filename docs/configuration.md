# Configuration and Secrets

## Non-secret configuration

Store these as server environment variables, a protected configuration store, or administrator-controlled configuration values:

- Organization name
- Approved mailbox addresses
- Default email batch size
- Default currency
- Follow-up reminder interval
- Application environment name
- Application URL
- MySQL host and database name
- Microsoft Graph tenant ID
- Microsoft Graph client ID
- Microsoft Graph client secret reference
- Job schedule

Initial configuration values:

| Setting | Initial value | Status |
|---|---|---|
| Approved mailboxes | `programm@paradisskolekorps.no`, `salg@paradisskolekorps.no` | Pending Graph mail configuration |
| Application server | Existing server | Confirm runtime and deployment method |
| Database | MariaDB 10.6 | Confirm database and migration permissions |
| PHP runtime | PHP 8.3 | Confirm required extensions |
| Mail adapter | Microsoft Graph | Pending Entra app registration |
| Approved mailboxes | `programm@paradisskolekorps.no`, `salg@paradisskolekorps.no` | Pending Graph permissions and mailbox scope |
| Default batch size | To be decided after licensing and sending-policy review | Pending |
| Currency | To be confirmed | Pending |

## Microsoft Graph mail configuration

The application must support a Microsoft Graph mail adapter rather than embedding mailbox logic throughout the code. The adapter must provide:

- Send test message
- Send approved batch
- Report per-recipient failures
- Configure sender and reply-to
- Retrieve or receive replies where supported
- Handle connection errors explicitly
- Read replies only from approved mailbox folders

Do not send directly from the database or browser.

## Entra application registration

Create a dedicated app registration for the server application. The setup guide must record:

- Tenant ID
- Client ID
- Required Graph permissions
- Admin-consent status
- Approved mailbox scope
- Credential owner and expiry/rotation date

Record only secret references and expiry information in project documentation. Never record the client secret value.

## Secrets

Never store passwords, tokens, API keys, certificates, or client secrets in source code, browser code, MySQL, documentation, screenshots, or commits.

If the mail server or Microsoft 365 integration requires a secret:

1. Store it in the server's protected secret store or Azure Key Vault.
2. Use a restricted service account or managed identity where available.
3. Grant least-privilege access.
4. Document the secret name, owner, permissions, and rotation schedule.
5. Never document the secret value.
6. Test rotation before production use.

## Configuration changes

Every configuration change must record:

- Date
- Environment
- Changed value or setting, excluding secrets
- Reason
- Person making the change
- Validation performed
- Rollback method

## Microsoft Graph environment variables
| Variable | Purpose | Secret |
|---|---|---|
| `GRAPH_TENANT_ID` | Entra directory (tenant) ID | No |
| `GRAPH_CLIENT_ID` | App registration (client) ID | No |
| `GRAPH_CLIENT_SECRET` | Client secret value | **Yes** – `.env` / protected server environment only |

The Settings page (`/settings.html`) shows whether each value is set (never the secret), lets administrators manage the approved mailboxes stored in the `mailboxes` table, and runs a read-only connection test. See [office365-setup.md](office365-setup.md).

## Sign-in variables
| Variable | Purpose |
|---|---|
| `APP_URL` | Public address of the app, used for the sign-in redirect (`<APP_URL>/auth/callback`) and to decide whether cookies are Secure |
| `AUTH_BOOTSTRAP_ADMIN` | Sign-in address that becomes the first administrator when no admin exists |
| `AUTH_DEV_LOGIN` | `1` enables a local development login, only honoured when `APP_ENV=development`. Must be `0` or unset in production. |

Users and roles are managed in Settings → Users. See [security.md](security.md).

## Sending variables
| Variable | Purpose |
|---|---|
| `MAIL_RATE_PER_MINUTE` | Messages per minute the worker sends (1–30, default 10). Exchange Online allows about 30 per minute per mailbox. |
| `MAIL_DRY_RUN` | `1` logs mail instead of sending, only honoured when `APP_ENV=development`. Keep `0` in production. |
| `REPLY_LINK_DAYS` | Days a personal details link (`{{details_link}}`) stays valid after sending (1–365, default 90). |

The worker `bin/send-queue.php` must be run every minute from cron. See [sending.md](sending.md).

## Mailboxes
The approved mailboxes are **data**, not configuration: an administrator adds them under Settings → Mailboxes after the first deployment. No migration seeds addresses, so development databases hold test addresses only.
