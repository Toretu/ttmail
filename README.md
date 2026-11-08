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
- [Project plan](docs/project-plan.md) and [change log](docs/change-log.md)
- [Architecture](docs/architecture.md), [data model](docs/data-model.md), [decisions](docs/decisions.md)
- [Setup guide](docs/setup-guide.md), [Microsoft 365 setup](docs/office365-setup.md), [configuration](docs/configuration.md), [deployment](docs/deployment.md), [operations](docs/operations.md), [troubleshooting](docs/troubleshooting.md)
- [Security](docs/security.md) and [testing](docs/testing.md)
- Features: [records](docs/records.md), [import](docs/import.md), [groups and recipients](docs/groups-and-recipients.md), [campaign products](docs/campaign-products.md), [campaign message](docs/campaign-message.md), [sending](docs/sending.md), [replies](docs/replies.md)

## Development quick start

```
bash bin/init-env.sh            # creates .env with generated database passwords
docker compose up -d --build    # web on http://127.0.0.1:8081
docker compose exec -T web php bin/migrate.php
bash tests/smoke.sh             # needs MAIL_DRY_RUN=1 in .env; works without host PHP
```

CI (`.github/workflows/ci.yml`) runs the same lint, stack, migrations and smoke test on every push.

## Signing in
Open `http://127.0.0.1:8081/login.html`. Locally, with `APP_ENV=development` and `AUTH_DEV_LOGIN=1`, use the development login. With Microsoft 365, follow `docs/office365-setup.md` (step 3b) and set `AUTH_BOOTSTRAP_ADMIN`. Manage users under Settings → Users and the approved mailboxes under Settings → Mailboxes. The mockup is served at `/app/` by the Docker setup only.
