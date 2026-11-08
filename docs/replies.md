# Replies and follow-up

Replies to campaign mail are read from the approved mailboxes through Microsoft Graph and shown on the **Replies** page, where an editor sets a stage, a follow-up date and a note.

## How it works

- `bin/fetch-replies.php` reads the **inbox** of each active mailbox (Settings → Mailboxes) and is run by cron. **Check mailboxes now** on the page does the same on demand, limited to 2 pages (100 messages) per mailbox so the request finishes; cron fetches up to 10 pages per run.
- Only mail from an address that is a **contact** in the system is stored. Everything else is counted ("unknown senders ignored") and discarded, so the rest of the inbox is not copied into the database.
- A reply is linked to the campaign that contact was last sent before the reply arrived.
- The first run looks 30 days back; later runs continue from the newest mail seen. A message is stored once (unique per mailbox and Graph message id).
- Mail is only read. Nothing is moved, marked or deleted in the mailbox. Reply from Outlook as usual.
- Body text is stored as plain text (max 20 000 characters) and always displayed as text.

## Stages

New → Interested → Needs a call → Quote sent → Won / Declined / Done. Every change is logged with who changed it and when. "Follow-up due" lists replies with a follow-up date today or earlier that are not Won, Declined or Done. These are a deliberate subset of the project's sales stages; see ADR-007 in [decisions.md](decisions.md) (awaiting owner approval).

## Server setup

1. The Entra app needs the application permission `Mail.Read` (or `Mail.ReadWrite`) with admin consent (see [office365-setup.md](office365-setup.md)). Settings → Test connection shows it.
2. Run `php bin/migrate.php` (migration 009).
3. Add to cron, as the same user as the send queue:

```
*/5 * * * * cd /srv/mailings && /usr/bin/php bin/fetch-replies.php >> /var/log/mailings-replies.log 2>&1
```

If you restricted the app to the two mailboxes with an Application Access Policy, it covers read access as well.

## API

| Endpoint | Purpose |
|---|---|
| `GET /api/replies?stage=&campaign_id=&due=1` | List, with counts per stage and the last fetch result per mailbox |
| `GET /api/replies/{id}` | One reply with body and stage history |
| `PUT /api/replies/{id}` | `stage`, `next_followup` (YYYY-MM-DD), `note` (editor) |
| `POST /api/replies/sync` | Fetch now (editor) |

## Limits

- Replies from an address that is not a contact (for example someone answering from a private address) are not shown. Add that address to the contact, or the reply is picked up on the next fetch only if it is still in the 30-day window and newer than the last mail seen, so add contacts early.
- Automatic replies (out-of-office) from contacts are stored like any other mail.
- Not tested against live Microsoft 365 yet; local tests inject sample messages.
