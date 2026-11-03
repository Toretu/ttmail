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

| Test | Expected result |
|---|---|
| Contact marked Do not contact | Contact is excluded from all sends |
| Duplicate contact in group | Only one recipient record is created |
| Campaign without approval | Sending is blocked |
| Duplicate campaign send | Existing successful recipient is not sent again |
| Invalid email address | Recipient is rejected with a visible reason |
| Failed email | Recipient is marked failed, not sent |
| Unknown reply sender | Reply appears for manual matching |
| Volunteer edits product price | Operation is denied |
| Missing mailbox configuration | Flow reports an actionable error |
| Follow-up due today | Record appears in the due-today queue |

## User acceptance

At least one campaign manager and one volunteer must complete the smoke test in [setup-guide.md](setup-guide.md) using test data before production launch.

## API smoke test
`bash tests/smoke.sh` (stack running and migrated; needs `APP_ENV=development` and `AUTH_DEV_LOGIN=1` in `.env`). It signs in with the development login and covers authentication (401 without a session), roles (viewer read-only, no settings), cross-origin refusal, companies, contacts, campaigns, products, capacity and sales.
