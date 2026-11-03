# Customer and Campaign Management Solution

You are developing and maintaining a low-cost self-hosted web solution for a volunteer organization.

## Business purpose

The solution manages companies, contacts, groups, sponsorship campaigns, advertising products, replies, follow-ups, sales opportunities, invoices, and payments.

Advertising products initially include:

- Full page
- Half page
- Middle page
- Last page

Prices, inventory, and campaign-specific prices must be configurable without changing application code.

## Technology constraints

Use these technologies by default:

- Existing server for the web application and scheduled jobs
- MySQL as the system of record
- HTTPS web application and server-side API
- A Microsoft Graph mail adapter targeting one or more approved Microsoft 365 mailboxes
- Microsoft Entra ID if approved for application sign-in
- Protected server configuration for non-secret settings and secret references

Do not introduce Power Apps, SharePoint Lists, Dataverse, Azure SQL, premium connectors, external email services, or Azure-hosted components unless there is a documented requirement and the project owner approves the additional cost and complexity. A browser mockup may be used for UX validation but is not production.

## Core entities

Support:

- Companies
- Contacts
- Groups
- Group members
- Campaigns
- Campaign recipients
- Advertising products
- Opportunities
- Activities
- Follow-ups
- Email templates
- Stage history
- Configuration

A company can have multiple contacts.

## Campaign behavior

A campaign must support:

- Selecting a group
- Generating campaign recipients
- Removing duplicate contacts
- Excluding unsubscribed contacts
- Reviewing recipients before sending
- Sending a test email
- Requiring explicit approval
- Sending controlled batches
- Recording sent, failed, and skipped recipients
- Tracking replies
- Tracking follow-ups
- Tracking advertising sales and campaign value

Never send campaign email automatically without an explicit approval step.

## Sales stages

Use these stages unless the project owner approves a change:

- Not contacted
- Email scheduled
- Email sent
- Replied
- Interested
- Needs information
- Proposal sent
- Follow-up required
- Negotiation
- Confirmed
- Invoice sent
- Paid
- Not interested
- No response
- Do not contact

## Privacy and communication rules

- Never send to a contact marked `Do not contact`.
- Every campaign email must contain an unsubscribe method.
- Record unsubscribe status and date.
- Prevent duplicate campaign sends.
- Do not expose customer data unnecessarily.
- Use test data in development and testing.
- Do not use real customer data in development unless explicitly approved.

## Secrets and configuration

Never store passwords, API keys, tokens, certificates, client secrets, or connection passwords in source code, browser code, MySQL, documentation, screenshots, or commits.

Use a server-side mail adapter and protected server configuration. The browser must never receive database or mail credentials.

Use environment variables for non-secret configuration such as:

- Application URL
- Configured mailbox identifiers and addresses
- Batch size
- Environment name
- Organization name
- Currency
- Reminder interval

If a real secret is required, use the server's protected secret store or Azure Key Vault with managed identity where appropriate. Document only the secret name, location, owner, permissions, and rotation process. Never document the secret value.

## Error handling

Errors must be explicit and actionable. Do not silently ignore errors, mark failed emails as successful, return success when required configuration is missing, or continue a campaign when recipient validation has failed.

Show users what failed and how to correct it. Record technical details in the appropriate administrative log without exposing secrets or sensitive data.

## Documentation requirement

Documentation is part of every implementation task.

Before making a change:

1. Read the relevant documentation.
2. Read `docs/project-plan.md`.
3. Identify affected requirements and acceptance criteria.
4. Identify configuration, security, migration, and testing impacts.

After making a change:

1. Update relevant documentation in the same task.
2. Update the project plan status.
3. Update `docs/change-log.md`.
4. Add or update setup instructions.
5. Add or update configuration instructions.
6. Add or update security and permissions information.
7. Add or update tests and acceptance criteria.
8. Record important design decisions in `docs/decisions.md`.

Never mark a task complete while its required documentation is outdated.

## Required documentation

Maintain:

- `docs/architecture.md`
- `docs/data-model.md`
- `docs/security.md`
- `docs/setup-guide.md`
- `docs/configuration.md`
- `docs/deployment.md`
- `docs/testing.md`
- `docs/operations.md`
- `docs/troubleshooting.md`
- `docs/decisions.md`
- `docs/change-log.md`
- `docs/project-plan.md`

## Testing rules

For every feature, include normal behavior, invalid input, duplicate-data, permission, failure-handling, and user acceptance tests.

Before declaring a change complete, run the smallest relevant test set and report the result.

## Change discipline

Make precise, minimal changes. Preserve existing behavior unless the requirement explicitly changes it. Search for existing components before adding new ones. Do not make unrelated changes.

If requirements are ambiguous and the decision affects cost, privacy, permissions, user workflow, or data structure, stop and request clarification before implementing.

## Completion report

For each completed task, report:

1. What changed
2. Components affected
3. Documentation updated
4. Configuration required
5. Security or permission impact
6. Tests performed
7. Known limitations
8. Next project-plan task
