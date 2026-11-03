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
