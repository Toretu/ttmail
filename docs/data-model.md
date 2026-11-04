# Data Model

## Companies

Company ID, company name, organization number, billing address, city, country, website, category, tags, status, owner, notes, created by, created date, modified by, modified date.

## Contacts

Contact ID, company, first name, last name, job title, email, phone, consent status, unsubscribe status, unsubscribe date, owner, notes, audit fields.

## Groups

Group ID, name, description, active status, filter definition, owner, audit fields.

## Sending state

Migrations 006 and 007: campaigns hold `mailbox_id`, `subject`, `body`, `send_state` (draft/sending/paused/done), `test_sent_at`, `approved_by`, `approved_at`, `pause_reason`. `campaign_recipients` gain status `sending`, `claimed_at`, `sent_at`, `error`. `contact_tokens` holds per-contact unsubscribe tokens.

## Group members

Implemented (migrations 001 and 005): `contact_groups`, `contact_group_members` (source manual/filter) and `campaign_recipients` (campaign, contact, email snapshot, status pending/sent/failed/skipped; one row per contact per campaign). Deleting a contact removes its recipient rows; this will be restricted once mail has been sent.

Group, company, contact, membership source, date added, active status.

## Campaigns

Implemented (migration 003): `campaigns` (name, description, status draft/active/closed). Planned additions: publication, start/closing dates, owner, target group, email template, financial target.

## Campaign recipients

Campaign, company, contact, recipient email, selected date, sent date, delivery status, reply status, current stage, last contact date, next follow-up date, owner, failure reason, unsubscribe check result.

## Advertising products and sales

Implemented (migration 003). Products belong to one campaign, so each campaign has its own price list.

- `campaign_products`: campaign, name (unique per campaign), description, price, `quantity_available` (NULL = unlimited), sort order.
- `product_sales`: product, company, quantity, `unit_price` (snapshot of the price when sold), status `reserved` / `sold` / `cancelled`, notes.
- Reserved and sold quantities both count against `quantity_available`; cancelled ones do not. The product row is locked while a sale is saved, so two users cannot oversell the last item.
- Summary per product: capacity, reserved, sold, remaining (NULL = unlimited), sold value and reserved value, plus campaign totals.

## Opportunities

Campaign, company, contact, product, quantity, quoted price, agreed price, sales stage, expected value, invoice status, payment status, document link, owner, notes. The sales stage and follow-up handling is still planned; `product_sales` covers reserved/sold only.

## Activities

Company, contact, campaign, opportunity, activity type, activity date, user, notes, next action date.

## Stage history

Record, old stage, new stage, changed by, changed date, reason or note.

## Configuration

Use for non-secret values such as organization name, default batch size, currency, and reminder interval. Do not store secrets here.

## Mailboxes

Mailbox ID, display name, Microsoft 365 email address, Graph sender identity, active status, project usage, reply folder configuration, and audit fields. Do not store client secrets, access tokens, or refresh tokens.

## Relationships

- One company has many contacts.
- One group has many group members.
- One campaign has many recipients.
- One campaign has many opportunities.
- One contact can participate in many campaigns.
- One opportunity can have many activities and stage-history entries.
- One or more approved mailboxes can be used by campaigns.
