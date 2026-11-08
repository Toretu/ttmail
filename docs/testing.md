# Testing

## Required test categories

Every feature must include:

- Normal behavior
- Invalid input
- Duplicate data
- Permission boundaries
- Failure handling
- User acceptance

## Critical tests

| Test | Expected result | Covered by |
|---|---|---|
| Contact marked Do not contact | Contact is excluded from all sends | smoke: recipients preview / worker skips unsubscribed |
| Duplicate contact in group | Only one recipient record is created | smoke: no duplicate member / no duplicate recipient |
| Campaign without approval | Sending is blocked | smoke: approve needs test, worker sends nothing while paused |
| Duplicate campaign send | Existing successful recipient is not sent again | smoke: no duplicate sends |
| Interrupted send | Recipient becomes "unknown" and is not requeued by retry | smoke: interrupted send marked unknown |
| Invalid email address | Recipient is rejected with a visible reason | smoke: contact invalid email |
| Failed email | Recipient is marked failed, not sent | worker (manual, needs Graph) |
| Unknown reply sender | Reply is counted, not stored | smoke: unknown sender ignored |
| Editor edits product price | Operation is denied (admin only) | smoke: editor cannot change a price |
| Missing mailbox configuration | Flow reports an actionable error naming the keys | smoke: missing Microsoft 365 configuration is reported |
| Follow-up due today | Record appears in the due-today queue | smoke: follow-up due filter |
| Deleting a contact with history | Refused; unsubscribe and send history kept | smoke: cannot delete unsubscribed contact / contact that was sent mail |
| Unsubscribe token on details page | Refused | smoke: unsubscribe token does not open details page |
| Expired details link | 410 | smoke: expired reply link refused |

## User acceptance

At least one campaign manager and one volunteer must complete the smoke test in [setup-guide.md](setup-guide.md) using test data before production launch.

## API smoke test
`bash tests/smoke.sh` (stack running and migrated; needs `APP_ENV=development`, `AUTH_DEV_LOGIN=1` and `MAIL_DRY_RUN=1` in `.env`). It signs in with the development login and covers authentication (401 without a session, deny by default), roles (viewer read-only; editor cannot manage users, mailboxes or products), cross-origin refusal, companies, contacts, delete guards, campaigns, products, capacity and sales, groups, recipients, message, preview, unsubscribe, approval, the worker in dry run, unknown outcome, the details link (including expiry), answers and replies. 170 checks. Host PHP is optional: without it the script uses PHP inside the web container.

## Continuous integration
`.github/workflows/ci.yml` runs `php -l` on every PHP file and then the full smoke test against the Docker stack on each push and pull request.
