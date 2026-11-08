# Deployment

## Deployment path

Deploy changes from Development to Test and then Production using the selected PHP deployment process and versioned MariaDB migrations.

## Pre-deployment checklist

- Project plan and change log are current.
- Documentation is updated.
- Tests pass in Development.
- No secrets are present in source, build artifacts, or exported files.
- Server configuration values and secret references are identified.
- Migration steps are documented.
- Rollback or disablement steps are documented.
- A production backup or export is available.

## Post-deployment checklist

- Deployment completes successfully.
- MariaDB migrations complete successfully.
- Server configuration values have correct values.
- Scheduled jobs are enabled in the intended order.
- Application health check passes.
- Security groups have correct access.
- Smoke test passes.
- Deployment version and date are recorded.

## Rollback

Disable affected jobs, restore the previous application version, restore configuration or database migration state if necessary, and run the smoke test. Record the incident and rollback in the change log.

## PHP requirements on the server
PHP 8.3 with the `curl`, `pdo_mysql`, `mbstring` and `zip` extensions (Debian/Ubuntu: `sudo apt install php8.3-curl php8.3-mysql php8.3-mbstring php8.3-zip`, then restart php-fpm). The `curl` extension is required for Microsoft sign-in and Graph. Check with `php -m` (and phpinfo for the FPM version, which can differ from the command line).

The prototype in `mockup/` is only served by the Docker setup at `/app/`. It is not part of a production deployment unless you add an alias for it.

## Scheduled sending
Add the cron entry from [sending.md](sending.md) so approved campaigns are actually sent, and the one from [replies.md](replies.md) to fetch replies. After deploying, run `php bin/migrate.php` (migration 010 adds the unknown status and details-link tokens).

## Upgrading an existing installation
`git pull` followed by `php bin/migrate.php`, as before. Between the two commands the details page answers 500 (new code, old schema), so run them back to back. Migration 010 is safe on live data: it keeps details links already sent (valid 90 days after sending), converts old "Outcome unknown" failures to `unknown`, and leaves existing mailboxes untouched (the removed seed migration stays recorded in `schema_migrations`). After upgrading, give the **admin** role to anyone who maintains products and prices; editors can no longer change them (ADR-010). `REPLY_LINK_DAYS` is optional.

## Mailboxes
Migrations do not create mailboxes. After the first deployment an administrator adds the approved mailboxes under Settings → Mailboxes (and restricts the Entra app to them, see [office365-setup.md](office365-setup.md)).

## Behind a reverse proxy or TLS terminator
The cross-origin check compares the browser's `Origin` with the `Host` header the application sees. If a proxy sits in front of PHP, pass the original host through (Apache: `ProxyPreserveHost On`; nginx: `proxy_set_header Host $host;`). Otherwise every POST/PUT/DELETE from the browser is refused with "Cross-origin request refused". Also set `APP_URL` to the public https address so the session cookie is marked Secure.

## If a migration fails half-way
MariaDB does not roll back DDL. If `php bin/migrate.php` reports `Migration NNN failed`, the statements before the failing one are applied but the file is not recorded. Fix the cause, undo the applied statements of that file by hand (or restore the backup you took before deploying), then run the migrator again. Never insert the file name into `schema_migrations` by hand unless every statement in it has been applied.
