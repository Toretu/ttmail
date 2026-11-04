# Project Plan

## Goal

Create a maintainable, low-cost self-hosted customer and campaign management solution for a volunteer organization. The system must support a few hundred recipients per campaign, controlled email sending, reply follow-up, and advertising sales.

## Status definitions

- Not started
- In progress
- Blocked
- Ready for testing
- Complete
- Deferred

## Phases

| Phase | Status | Dependencies | Acceptance summary |
|---|---|---|---|
| Foundation and environment | In progress | None | Server, database, mail, permissions, and architecture are documented |
| Database schema and API foundation | In progress (schema, API, Microsoft 365 sign-in and roles done) | Foundation | MariaDB schema, plain PHP API conventions, migrations, and authentication are ready |
| Microsoft Graph mail integration | Not started | Foundation | Approved Office 365 mailboxes can send and retrieve campaign mail |
| Companies and contacts | In progress | Database and API foundation | Companies can have multiple contacts and searchable history |
| Groups | Done (groups, filters, members) | Companies and contacts | Groups can be created, reviewed, and reused |
| Campaigns | In progress (products and sales done; recipients, message, preview and test send done; sending with approval, pause and retry done; replies and follow-up next) | Groups | Campaign recipients and campaign statistics are available |
| Controlled email sending | Not started | Campaigns, Microsoft Graph mail integration | Approved campaigns send in controlled batches |
| Replies and follow-ups | Not started | Email sending | Replies become actionable follow-up records |
| Advertising products | Done (per-campaign products, capacity, sales summary) | Foundation | Page products, prices, and inventory are configurable |
| Opportunities and payments | Not started | Campaigns, products | Quotes, sales, invoices, and payments are tracked |
| Reports and dashboards | Not started | Campaigns, opportunities | Users can see campaign progress and value |
| Security and deployment | Not started | All MVP features | Production deployment is repeatable and access is restricted |
| User acceptance testing | Not started | Testable MVP | Volunteers approve the workflows |
| Production launch | Not started | UAT complete | Live solution is documented and supported |

## Work rules

Every task must update affected documentation, configuration notes, security notes, tests, and this plan before it is marked complete.

## MVP scope

The MVP includes:

1. Companies and contacts
2. Groups
3. Campaigns and recipients
4. Controlled Outlook email sending
5. Reply and follow-up stages
6. Advertising products and prices
7. Opportunities and payment status
8. Basic campaign dashboard
9. Self-hosted setup and operational documentation

## Cost comparison

| Area | Self-hosted server and MySQL | Power Apps route |
|---|---|---|
| Recurring app license | Usually no additional per-user Power Apps license | May require Power Apps or premium licensing |
| Database | Existing MySQL server | SharePoint, Dataverse, or licensed connector |
| Development | More custom development | Faster low-code development |
| Maintenance | Application, server, database, backups, security | Power Platform configuration and licensing |
| Email | Existing mail server or Microsoft 365 integration | Outlook/Power Automate licensing and connector limits |
| Main risk | Security and maintenance burden | Licensing and platform dependency |

The self-hosted choice reduces licensing exposure but does not make the system free: development time, PHP framework maintenance, server administration, backups, monitoring, Microsoft Graph app registration, credential rotation, and email deliverability remain real costs.

## Risks

| Risk | Mitigation |
|---|---|
| Incorrect recipients receive email | Review, approval, deduplication, unsubscribe checks, and batches |
| Microsoft 365 licensing does not include required rights | Verify licenses before implementation; avoid premium connectors |
| Reply matching is imperfect | Provide manual correction and an unknown-sender queue |
| Volunteers lack technical administration skills | Maintain a step-by-step setup and operations guide |
| Customer data is exposed | Use Entra ID, least privilege, separate environments, and test data |
