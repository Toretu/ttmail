# Campaign message, preview and test send

## Where
Campaigns page → **Message** panel (above Recipients).

## What you set
- **Send from**: one of the active mailboxes (Settings → mailboxes).
- **Subject** and **Text**. Plain text; line breaks are kept. The text is HTML-escaped, so nothing you or a contact types can inject markup.
- **Placeholders**: `{{first_name}}`, `{{last_name}}`, `{{full_name}}`, `{{company}}`, `{{campaign}}`. Unknown placeholders are rejected when saving.

## Unsubscribe line
A Norwegian and English footer with a personal unsubscribe link is added to every message automatically and cannot be removed. The sender name in the footer is the mailbox's display name (change it in Settings if it should read e.g. "Paradisskolekorps").

The link opens `/unsubscribe?t=<token>` and needs no login. Opening the link only shows a confirmation page; the contact is unsubscribed when they press the button (so mail scanners that follow links cannot unsubscribe people by accident). The contact is then marked `withdrawn` and is excluded from all future recipient selections. Tokens are random, per contact, and only allow unsubscribing that contact.

Microsoft Graph does not allow setting the `List-Unsubscribe` header, so mail clients will not show their own "unsubscribe" button; the link in the footer is what people use.

## Preview
Shows subject, sender and the rendered message for the first recipient (or sample data when there are none). Nothing is sent.

## Test send
Sends one message through Microsoft Graph from the chosen mailbox to the address you enter (default: your own), with the subject prefixed `[TEST]`. The unsubscribe link in a test mail is deliberately inactive. Requires the Microsoft 365 connection to be working (Settings → Test connection) and the `Mail.Send` permission. Every test send is written to the server log.

## Locking
Once any recipient has been sent mail, the message can no longer be changed.

## API
| Method and path | Purpose |
|---|---|
| `GET/PUT /api/campaigns/{id}/message` | Read or save `mailbox_id`, `subject`, `body` |
| `POST /api/campaigns/{id}/message/preview` | Optional body `{"contact_id":N}` |
| `POST /api/campaigns/{id}/message/test` | Body `{"to":"address"}` |
| `GET/POST /unsubscribe` | Public unsubscribe page |
