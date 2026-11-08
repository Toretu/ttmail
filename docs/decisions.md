# Architecture Decisions

## ADR-001: Use the existing server and MySQL for production

**Status:** Accepted

**Decision:** Build the production application as a self-hosted web application using the existing server and MySQL database.

**Reason:** The organization already owns server/database capacity and wants to reduce recurring Power Apps, premium connector, and Dataverse licensing exposure.

**Consequences:** The project must provide application development, server patching, backups, monitoring, authentication, permissions, and security maintenance. Microsoft 365 may still be used for email or identity.

## ADR-002: Use plain PHP 8.3 initially

**Status:** Accepted

**Decision:** Implement the first backend in plain PHP 8.3 rather than adding a framework.

**Reason:** The existing server supports PHP and the initial application is small enough to keep dependencies and deployment simple.

**Consequences:** The code must enforce clear boundaries, input validation, prepared database statements, CSRF protection, authentication, authorization, error handling, and testable services without relying on framework defaults. Reconsider a framework if the application complexity grows.

## ADR-003: Use controlled campaign batches

**Status:** Accepted

**Decision:** Require review, test email, approval, and controlled batches before sending campaign email.

**Reason:** This reduces accidental sends, duplicate sends, and compliance errors.

## ADR-004: Use Microsoft Graph for approved mailboxes

**Status:** Accepted

**Decision:** Isolate email sending and reply retrieval behind a Microsoft Graph mail adapter restricted to `programm@paradisskolekorps.no` and `salg@paradisskolekorps.no`.

**Reason:** The organization wants to use Office 365 mailboxes, and Graph provides a supported server-to-mailbox integration without exposing mailbox passwords to the application or users.

## ADR-005: Separate secrets from configuration

**Status:** Accepted

**Decision:** Store Graph client credentials outside the source tree using the server's protected secret mechanism or Azure Key Vault when appropriate.

**Reason:** Secrets must not be stored in the database, source code, browser, or documentation. Graph application access must be restricted to approved mailboxes.

## ADR-006: Documentation is part of completion

**Status:** Accepted

**Decision:** A task cannot be marked complete until affected setup, configuration, security, testing, and project-plan documentation is updated.

**Reason:** Volunteers must be able to operate and maintain the solution after implementation.

## ADR-007: Reply follow-up stages are a subset until opportunities exist

**Status:** Proposed – needs project owner approval

**Decision:** The Replies pane uses seven follow-up stages (`new`, `interested`, `needs_call`, `quote_sent`, `won`, `declined`, `done`) for mail that has come in. The fifteen sales stages required by the project instructions (Not contacted … Do not contact) describe the *recipient/opportunity* lifecycle; the mail-related ones are already represented by recipient status (`pending`/`sent`/`failed`/`skipped`/`unknown`) and unsubscribe status (`Do not contact`). The remaining sales stages will be introduced with the Opportunities and payments phase as the opportunity stage, and the reply stages will map onto them (`interested` → Interested, `needs_call` → Needs information / Follow-up required, `quote_sent` → Proposal sent, `won` → Confirmed, `declined` → Not interested).

**Reason:** Replies needed a small, obvious set of stages for volunteers. Using the full list on replies would mix mail status with sales status.

**Consequences:** Until the owner approves this ADR (or asks for the full list on replies), the reply stages are a known deviation from the instructions. Changing them later requires a migration of `replies.stage` and `reply_stage_history`.

## ADR-008: Records with history are deactivated, never deleted

**Status:** Accepted

**Decision:** A contact that has unsubscribed, or that has been sent campaign mail, cannot be deleted (nor can its company). Users set the contact to withdrawn or the company to inactive / do not contact instead. Plain contacts with no history can still be deleted.

**Reason:** The unsubscribe record and the send history must survive a re-import of the same spreadsheet; otherwise an address that asked not to be contacted could be mailed again, and "who was sent what" would be lost.

**Consequences:** Deletion for data-protection requests must be handled as a documented manual procedure (anonymise the contact fields, keep the suppression) rather than the Delete button. This is listed as an open item in the project plan.

## ADR-009: Personal details links use their own expiring tokens

**Status:** Accepted

**Decision:** The `{{details_link}}` page is opened with a token per campaign and contact (`campaign_links`, default validity `REPLY_LINK_DAYS` = 90 days). The unsubscribe token (`contact_tokens`) only unsubscribes and never opens the details page. Preview and test mail contain inactive sample tokens only.

**Reason:** The details page shows and accepts organisation number, billing address and phone. A forwarded unsubscribe link must not expose that, and the exposure window should be limited.

## ADR-010: Products and prices are administrator settings

**Status:** Accepted

**Decision:** Creating and changing campaign products (name, price, capacity) requires the admin role. Registering, changing and cancelling sales against products remains editor work.

**Reason:** The security documentation and the critical test list already assigned products and prices to administrators; the code allowed editors. Prices are a financial setting, not day-to-day campaign work.
