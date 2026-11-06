# Architecture

## Overview

The production solution will use the existing server and MySQL database:

```text
Users
  -> HTTPS web application
       -> Application API
            -> MySQL
            -> Scheduled campaign and reminder jobs
            -> Microsoft Graph mail adapter
                 -> One or more approved Microsoft 365 mailboxes
```

## Components

### Web application

Provides dashboards, customer management, groups, campaigns, replies, follow-ups, products, opportunities, and administration.

The mockup in `mockup/` is a no-build visual prototype. It is not connected to production data.

The initial implementation should use plain PHP 8.3 on Linux. Avoid a framework dependency for the first version unless a concrete requirement justifies it. Use a small application structure with strict separation between HTTP handlers, domain services, database access, mail integration, and scheduled jobs.

### Application API

Validates input, enforces permissions, applies unsubscribe and duplicate checks, records audit history, and exposes data to the web application.

### MariaDB

MariaDB 10.6 is the system of record. Implemented today: companies, contacts, groups and members, mailboxes, users, campaigns, recipients, products and sales, campaign messages and sending state, unsubscribe and details-link tokens, recipient answers, replies with stage history and mailbox sync state (see [data-model.md](data-model.md)). Planned: opportunities, activities, configuration table and a general audit log.

### Microsoft Graph mail adapter

Sends campaign messages through Microsoft Graph from the approved mailboxes `programm@paradisskolekorps.no` and `salg@paradisskolekorps.no`, which an administrator registers under Settings → Mailboxes. The adapter (`src/Graph`, `src/Mail`) supports test messages, controlled batches with claim-before-send, delivery errors and automatic pausing, reply retrieval, and unsubscribe handling. Reply-to configuration is not implemented (replies go to the sending mailbox).

The application must select an approved mailbox per project or campaign. Mailbox configuration must be stored as data and must not contain client secrets or access tokens.

Use Microsoft Entra ID OAuth 2.0 application authentication for server-side jobs. Restrict application mail permissions to the approved mailboxes using Exchange Online application access controls or the current Microsoft-supported equivalent. Request only the Graph permissions required for sending and reading campaign replies.

### Entra ID

May be used for authentication if the server application is registered with the tenant. Otherwise use the application's secure account system with MFA and appropriate password controls.

## Hosting and operations

- Use separate Development, Test, and Production deployments.
- Use HTTPS everywhere.
- Keep MySQL private to the application server or private network.
- Run scheduled jobs with a service account, not a personal account.
- Maintain encrypted backups and test restoration.
- Apply operating system, runtime, framework, and database security updates.
- Log administrative actions without logging passwords, tokens, or full email contents unnecessarily.

## Design constraints

- The application must be deployable on the existing Linux/PHP server.
- MySQL is the system of record.
- No Power Apps, Dataverse, or premium Power Platform connector is required for production.
- The web application must not expose MySQL directly to browsers.
- Microsoft Graph client credentials must be stored outside source code and protected by the server's secret/configuration mechanism.
