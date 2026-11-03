# Customer and Campaign Management

This project defines a low-cost self-hosted web solution for managing customers, contacts, sponsorship campaigns, advertising products, replies, and follow-ups.

The initial target is a few hundred recipients per campaign.

## Recommended production platform

- Existing server
- MariaDB 10.6 database
- PHP web application and API
- Microsoft Graph mail adapter for one or more approved Office 365 mailboxes
- Encrypted HTTPS access
- Server-side scheduled jobs for campaign batches and reminders

See [docs/project-plan.md](docs/project-plan.md) for the implementation plan and [docs/setup-guide.md](docs/setup-guide.md) for installation.

Power Apps and SharePoint are not required for the production application. The interactive [mockup](mockup/) remains a browser prototype for validating the workflow.

## Cost direction

The self-hosted route can reduce recurring Power Apps, premium connector, and Dataverse licensing costs. It transfers more responsibility to the project for development, hosting, security updates, backups, monitoring, Microsoft Graph configuration, email deliverability, and support.

See [docs/architecture.md](docs/architecture.md) and [docs/decisions.md](docs/decisions.md) for the trade-offs.

## Mockup

An interactive, no-build browser mockup is available in [mockup/](mockup/). Open [mockup/index.html](mockup/index.html) to explore the planned workflow without connecting to Microsoft 365.

## Documentation

- [Copilot instructions](copilot-instructions.md)
- [Architecture](docs/architecture.md)
- [Data model](docs/data-model.md)
- [Security](docs/security.md)
- [Configuration](docs/configuration.md)
- [Deployment](docs/deployment.md)
- [Testing](docs/testing.md)
- [Operations](docs/operations.md)
- [Troubleshooting](docs/troubleshooting.md)
- [Decisions](docs/decisions.md)
- [Change log](docs/change-log.md)

## Signing in
Open `http://127.0.0.1:8081/login.html`. Locally, with `APP_ENV=development` and `AUTH_DEV_LOGIN=1`, use the development login. With Microsoft 365, follow `docs/office365-setup.md` (step 3b) and set `AUTH_BOOTSTRAP_ADMIN`. Manage users under Settings → Users. The mockup is served at `/app/`.
