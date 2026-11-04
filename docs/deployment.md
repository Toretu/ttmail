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
Add the cron entry from [sending.md](sending.md) so approved campaigns are actually sent. After deploying, run `php bin/migrate.php` (migration 007 adds the sending state).
