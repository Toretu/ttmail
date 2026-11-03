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

MariaDB 10.6 stores companies, contacts, groups, campaigns, recipients, products, opportunities, activities, follow-up stages, configuration, and audit history.

### Microsoft Graph mail adapter

Sends campaign messages through Microsoft Graph from the approved mailboxes `programm@paradisskolekorps.no` and `salg@paradisskolekorps.no`. The adapter must support test messages, controlled batches, delivery errors, reply-to configuration, reply retrieval, and unsubscribe handling.

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
