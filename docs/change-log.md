# Change Log

All meaningful implementation, configuration, and documentation changes must be recorded here.

## 2026-11-03

- Created the initial project documentation baseline.
- Added Copilot development instructions.
- Added architecture, data model, setup, configuration, security, deployment, testing, operations, troubleshooting, decisions, and project-plan documents.
- Started the foundation phase.
- Added the foundation checklist.
- Recorded `programm@paradisskolekorps.no` and `salg@paradisskolekorps.no` as the initial approved campaign mailboxes.
- Recorded the decision to use one mailbox with project-specific campaign configuration before considering separate mailboxes.
- Added an interactive no-build mockup in `mockup/` covering the planned customer, campaign, follow-up, product, and opportunity views.
- Changed the planned production architecture to a self-hosted web application with the existing server and MySQL database.
- Added the mail-adapter requirement so the application can use the existing mail server or Microsoft 365 without embedding provider-specific logic.
- Confirmed Linux and PHP as the initial server platform.
- Selected Microsoft Graph for connecting the application to one or more approved Office 365 mailboxes.
- Confirmed PHP 8.3, MariaDB 10.6, plain PHP, and Graph reply access.
- Recorded `programm@paradisskolekorps.no` and `salg@paradisskolekorps.no` as the initial approved mailboxes.
- Added local Docker dev stack (php:8.3-apache + MariaDB 10.6, web on 127.0.0.1:8081), migrations, and a plain-PHP JSON API for companies and contacts (no authentication yet; local use only).
- Added company import from CSV/XLSX with preview and commit modes; see `docs/import.md`.
- Added contact import (`POST /api/contacts/import`), a browser import screen (`public/import.html`), and an import link in the mockup.
- Added campaigns, per-campaign products (price, limited or unlimited quantity), sales with reserved/sold/cancelled status, sold/remaining summary, and `public/campaigns.html`; see `docs/campaign-products.md`.
- Removed leftover SharePoint wording from the mockup; its New campaign and Add product buttons now open the live page.
- Live pages (campaigns, import) now have a navigation bar with a link back to the mockup; the campaigns page remembers the selected campaign across refresh (URL hash + localStorage).
- The mockup is now also served by the app at `/app/` (same origin as the API). Its Campaigns screen lists the real campaigns from the database (with sold/reserved totals); the old sample rows are labelled demo data. Live pages link back to `/app/`.
- Added campaign deletion with typed-name confirmation (API and campaigns page).
- Added company and contact delete endpoints (company needs typed-name confirmation and is blocked when it has sales) and `public/records.html` for searching, adding, editing and deleting both; see `docs/records.md`.
- Added Office 365 settings: `GRAPH_*` variables, Graph client, `GET /api/settings/graph`, `POST /api/settings/graph/test`, mailbox management API, `public/settings.html`, and the step-by-step Microsoft setup guide `docs/office365-setup.md`.
- Added Microsoft 365 sign-in (OIDC + PKCE), a users table with viewer/editor/admin roles enforced on every API call, same-origin checks, a login page, a signed-in user bar on all pages, user management in Settings, and a local development login. `/api/health` is the only public data endpoint. Smoke test now signs in and covers roles.
- Config now reads the project `.env` file itself when real environment variables are not set, so `php bin/migrate.php` works on a plain server without Docker.
- `/` now redirects to the campaigns page (which sends signed-out users to the login page).
- Fixed post-login redirect regex (invalid delimiter on some PHP builds); default landing page is /campaigns.html. Documented required PHP extensions (curl).
- Added contact groups (page + API), campaign recipients with preview and automatic exclusion of unsubscribed/inactive contacts (migration 005, docs/groups-and-recipients.md). Nothing is sent yet.
- Added campaign message (sender mailbox, subject, body with placeholders), preview, test send via Graph sendMail, and a public per-contact unsubscribe page (migration 006, docs/campaign-message.md). JSON bodies may now be an empty object. Nothing is sent to recipients yet.
- Added approval and background sending: test mail required, typed recipient count, worker bin/send-queue.php (cron, 10 mails/min), claim-before-send, re-check of unsubscribes, automatic pause on Microsoft errors, pause/resume/retry, dry-run mode for development, campaign delete blocked once mail is sent (migration 007, docs/sending.md).

## 2026-11-04 (details link)

- Added `{{details_link}}`: a personal public page where recipients correct their details, pick campaign products and leave a message.
- Added migration 008 (`campaign_responses`), `ReplyController`, `ResponseController` and the "Answers from recipients" panel. Answers are reviewed by an editor before master data changes.
- Smoke test extended to 124 checks.
- Added `{{products}}` placeholder: the available products with prices are listed in the mail.
