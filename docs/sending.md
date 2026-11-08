# Sending a campaign

Sending is a deliberate, controlled process. Nothing is sent when you add recipients or save a message.

## Steps (Campaigns page)
1. **Message**: choose sender, subject, text. **Save**.
2. **Recipients**: pick groups or filters, **Preview**, **Add recipients**.
3. **Send test** to yourself and check the mail in your inbox. Saving any change to the message clears the test, so the test always matches what will be sent.
4. **Sending** panel: type the exact number of recipients and press **Approve and start sending**, then confirm the dialog. Only editors and admins can approve. The approver's address and time are recorded.

## What happens next
A background worker (`bin/send-queue.php`, started every minute by cron) sends about **10 mails per minute** (`MAIL_RATE_PER_MINUTE`, 1–30), one message per contact, spaced evenly. 300 recipients take about 30 minutes. The Sending panel updates itself every 10 seconds. You can close the page.

- Each recipient is **claimed before sending** and never sent twice. If the worker is killed mid-send, that recipient is marked **outcome unknown** ("check Sent Items") rather than being resent. Unknown entries are never queued again automatically; if Sent Items shows the mail was not sent, add the contact to the campaign again by hand.
- Before each mail the contact is **checked again**: someone who unsubscribed after approval is skipped, as are companies that became inactive or do-not-contact.
- The message and recipient list are **locked** from approval until the campaign is done. After it is done you may add new recipients; they must be approved again with their own count.
- A campaign with sent mail cannot be deleted. Set it to closed to keep the history. Likewise a contact that was sent mail (or unsubscribed) cannot be deleted; set the company inactive or the contact withdrawn instead.
- A run stops claiming new recipients after about 55 seconds so it is always finished before the next cron tick, even when Microsoft answers slowly.

## Automatic pausing
The campaign pauses (waiting recipients stay waiting, nothing is lost) when:
- Microsoft rejects the sign-in or permission (401/403), throttles (429), is unavailable (5xx), or cannot be reached;
- three messages in a row fail;
- the sender mailbox is deactivated or the campaign is closed.

The reason is shown in red on the panel. Fix the cause, then press **Resume**. Pause by hand with **Pause** at any time.

A single message refused for its own reason (for example an address that does not exist) is marked **failed** with Microsoft's reason, and sending continues. **Queue failed again** puts failed recipients back in the queue; entries marked **unknown** are left alone (see above).

## Cron setup (server)
Run as the same user as PHP/the web app can read `.env` (it needs database and Graph access), once a minute:
```
* * * * * cd /srv/mailings && /usr/bin/php bin/send-queue.php >> /var/log/mailings-send.log 2>&1
```
Install with `sudo crontab -u www-data -e` (or the user that owns the app). Overlapping runs are safe: a database lock lets only one worker send at a time. Without this cron entry, approved campaigns stay in "Sending" and nothing is delivered.

## Development: dry run
With `APP_ENV=development` and `MAIL_DRY_RUN=1`, mail is only logged ("DRY RUN: would send…") and a red banner shows on the panel. It is ignored outside development, so production can never silently skip sending. Run the worker by hand with `docker compose exec -T web php bin/send-queue.php`.

## API
| Method and path | Purpose |
|---|---|
| `GET /api/campaigns/{id}/send` | State, counts, failures and skips |
| `POST …/send/approve` | Body `{"confirm_count": N}`; needs a current test mail |
| `POST …/send/pause`, `…/send/resume` | Stop and continue |
| `POST …/send/retry-failed` | Queue failed recipients again (not those with unknown outcome) |

Recipient statuses: `pending`, `sending` (claimed), `sent`, `failed`, `skipped` (excluded at send time), `unknown` (interrupted; outcome not known).

## Before the first real campaign
- Settings → Test connection is green and `Mail.Send` is granted (docs/office365-setup.md).
- SPF, DKIM and DMARC are set up for the domain (already done).
- Start with a small group of people who know you, check delivery and spam placement, then send the large campaign.
