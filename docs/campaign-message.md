# Campaign message, preview and test send

## Where
Campaigns page → **Message** panel (above Recipients).

## What you set
- **Send from**: one of the active mailboxes (Settings → mailboxes).
- **Subject** and **Text**. Plain text; line breaks are kept. The text is HTML-escaped, so nothing you or a contact types can inject markup.
- **Placeholders**: `{{first_name}}`, `{{last_name}}`, `{{full_name}}`, `{{company}}`, `{{campaign}}`, `{{products}}`, `{{details_link}}` (the last two are described below). Unknown placeholders are rejected when saving.

## Unsubscribe line
A Norwegian and English footer with a personal unsubscribe link is added to every message automatically and cannot be removed. The sender name in the footer is the mailbox's display name (change it in Settings if it should read e.g. "Paradisskolekorps").

The link opens `/unsubscribe?t=<token>` and needs no login. Opening the link only shows a confirmation page; the contact is unsubscribed when they press the button (so mail scanners that follow links cannot unsubscribe people by accident). The contact is then marked `withdrawn` and is excluded from all future recipient selections. Tokens are random, per contact, and only allow unsubscribing that contact; they never open the details page.

Microsoft Graph does not allow setting the `List-Unsubscribe` header, so mail clients will not show their own "unsubscribe" button; the link in the footer is what people use.

## Preview
Shows subject, sender and the rendered message for the first recipient (or sample data when there are none). Nothing is sent, nothing is stored: the unsubscribe and details links in a preview are inactive sample links even when a real contact is shown.

## Test send
Sends one message through Microsoft Graph from the chosen mailbox to the address you enter (default: your own), with the subject prefixed `[TEST]`. The unsubscribe and details links in a test mail are deliberately inactive (the details link opens a sample page that saves nothing). Requires the Microsoft 365 connection to be working (Settings → Test connection) and the `Mail.Send` permission. Every test send is written to the server log.

## Locking
Once any recipient has been sent mail, the message can no longer be changed.

## API
| Method and path | Purpose |
|---|---|
| `GET/PUT /api/campaigns/{id}/message` | Read or save `mailbox_id`, `subject`, `body` |
| `POST /api/campaigns/{id}/message/preview` | Optional body `{"contact_id":N}` |
| `POST /api/campaigns/{id}/message/test` | Body `{"to":"address"}` |
| `GET/POST /unsubscribe` | Public unsubscribe page |

## Personal details link

Put `{{details_link}}` in the message text. It becomes a link, unique per contact and campaign (its own token in `campaign_links`, separate from the unsubscribe token), to a public page `/reply` where the recipient can:

- correct company details (org. number, billing address, postal code, city, website) and their own (name, role, phone);
- tick the campaign's products they are interested in (not a binding order);
- leave a message.

Rules:

- The link works only for contacts that were actually sent this campaign, not for unsubscribed contacts, and shows nothing for closed campaigns. In preview and test mail the link opens the page with sample data and nothing is saved.
- The link **expires** `REPLY_LINK_DAYS` days (default 90) after the mail was sent; an expired link shows "Link expired, please reply to the email" (HTTP 410).
- Answers are stored in `campaign_responses` (one per contact and campaign; a new answer replaces the old). **Master data is never changed by the public page.** Editors review them in the campaign's "Answers from recipients" panel and press *Apply corrections* or *Mark handled*.
- Only the whitelisted fields can be changed. The email address cannot; the recipient writes it in the message.
- Anyone who gets the mail (for example by forwarding) can use the link while it is valid, so it can only propose changes. The unsubscribe link does not open this page.

| Endpoint | Purpose |
|---|---|
| `GET/POST /reply?t=TOKEN&c=CAMPAIGN` | Public page (no login) |
| `GET /api/campaigns/{id}/responses` | List answers with old → new values |
| `POST /api/campaigns/{id}/responses/{rid}/apply` / `dismiss` | Editor actions |

## Product list in the mail

`{{products}}` inserts a bullet list of the campaign's products with prices (and the description, if any), for example "Half page – kr 1 500". Products that are sold out are left out; sold and reserved quantities both count as taken. The list is built when each mail is sent, so later mails reflect sales made in the meantime. Use it together with `{{details_link}}` so recipients can choose.

**Reserve chosen products:** next to an answer with chosen products, this button (after a confirm) registers one reserved sale per product for that company, skips any the company already has, respects stock limits, and marks the answer handled. The sales then show in the campaign summary.
