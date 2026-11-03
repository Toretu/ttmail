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
