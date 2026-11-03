# Foundation Checklist

**Status:** In progress  
**Started:** 2026-11-03  
**Target:** Development foundation for the Mailings solution

## Confirmed decisions

- Application deployment environment: not created yet
- Approved project mailboxes: `programm@paradisskolekorps.no`, `salg@paradisskolekorps.no`
- PHP runtime: 8.3
- Database: MariaDB 10.6
- Application style: plain PHP
- Initial approach: separate projects and campaigns inside one solution
- Initial mailbox approach: one shared mailbox, with project-specific campaign records, templates, reply folders, and sender/reply-to configuration
- Server runtime, database version, and mail capabilities: to be verified

## Administrator actions

- [ ] Confirm the existing server runtime, database version, and available capacity.
- [x] Confirm PHP 8.3.
- [ ] Confirm required PHP extensions.
- [ ] Create Development, Test, and Production server deployments if the server supports them; otherwise document the separation strategy.
- [ ] Create the MariaDB 10.6 database and restricted application database user.
- [ ] Confirm access to `programm@paradisskolekorps.no` and `salg@paradisskolekorps.no`.
- [ ] Create the Entra ID app registration for Microsoft Graph.
- [ ] Restrict Graph application access to `programm@paradisskolekorps.no` and `salg@paradisskolekorps.no`.
- [ ] Create Entra ID groups for administrators, campaign managers, volunteers, and read-only users.
- [ ] Confirm who may send from the shared mailbox.
- [ ] Confirm the organization's data retention and unsubscribe policy.

## Build actions

- [ ] Create MariaDB migrations from `docs/data-model.md`.
- [ ] Add database indexes to columns used for search, filtering, and lookups.
- [ ] Create the server-side application configuration.
- [ ] Add the Microsoft Graph mail adapter and protected connection configuration.
- [ ] Build the first Companies and Contacts screens.
- [ ] Run the foundation smoke test.

## Project-specific mailbox behavior

The first release should use one shared mailbox. Each project or campaign must have:

- A project/campaign record
- A selected email template
- A project identifier
- A reply folder or matching rule where useful
- A configurable reply-to address if the tenant supports it

Create separate shared mailboxes only when there is a documented need for separate permissions, branding, retention, or sending identity. This avoids unnecessary licensing and administration.

## Exit criteria

The foundation phase is complete when:

- Server runtime and database configuration are documented.
- The deployment method and MySQL database are identified.
- The shared mailbox is available.
- Security groups and access owners are documented.
- Non-secret configuration is defined.
- The initial MySQL schema is ready for implementation.
- The foundation smoke test passes.
