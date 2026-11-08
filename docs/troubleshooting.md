# Troubleshooting

## Emails are not sent

Check, in this order:

1. The campaign's Sending panel says **Sending** (not Draft or Paused). If Paused, the red reason tells you what to fix; press Resume afterwards.
2. The cron entry for `bin/send-queue.php` exists and runs as a user that can read `.env` (see [sending.md](sending.md)). Without it nothing is delivered.
3. Settings → Test connection is green and `Mail.Send` is granted. `MAIL_DRY_RUN` must be `0` (it is ignored outside development anyway).
4. The recipient is not `skipped` (unsubscribed, inactive or do-not-contact company, invalid address) – the problems table on the panel shows the reason.
5. The recipient is not already `sent`: a contact is never sent the same campaign twice.
6. Entries marked **unknown** were interrupted mid-send. Look in the mailbox's Sent Items; if the mail is not there, add the contact to the campaign again. They are never requeued automatically.
7. The server log (`error_log`, the cron log) shows the Microsoft error text when a message is refused.

## Replies are not matched

Check:

1. The reply arrived in the **inbox** of an active mailbox listed under Settings → Mailboxes.
2. The sender address is a contact in the system (otherwise the reply is counted as "unknown sender" and not stored).
3. `bin/fetch-replies.php` runs from cron, or press **Check mailboxes now** (this fetches at most 2 pages per mailbox; cron fetches more).
4. The reply is newer than the last mail seen for that mailbox (the Replies page shows the last fetch time and error per mailbox).

## "Cross-origin request refused" on every save

The application compares the browser's `Origin` with the `Host` header. A reverse proxy must pass the original host through; see [deployment.md](deployment.md).

## "This contact has unsubscribed or has been sent campaign mail, so it cannot be deleted"

Intended (ADR-008). Mark the contact withdrawn or set the company to inactive / do not contact. The row is kept so the address is never mailed again after a re-import.

## "Link expired" on the details page

Details links are valid `REPLY_LINK_DAYS` days (default 90) after sending. The recipient should reply to the email instead; an editor records the answer.

## App cannot load data / "Sign in required"

Check:

1. The user exists under Settings → Users, is active, and signs in with that exact Microsoft 365 address.
2. The database user can read and write the required tables (`/api/health` returns `ok`).
3. `.env` (or the server environment) contains valid values; the Settings page shows which `GRAPH_*` values are set.
4. `php bin/migrate.php` reports "Database is up to date".

## Migration failed half-way

See "If a migration fails half-way" in [deployment.md](deployment.md).

## Settings → Test connection reports missing configuration

Do not put the secret anywhere except `.env` or the server's protected environment. Set the missing `GRAPH_*` value named in the message, restart PHP if it reads real environment variables, and test again.
