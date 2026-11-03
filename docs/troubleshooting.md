# Troubleshooting

## Emails are not sent

Check:

1. Campaign is approved.
2. Recipient is not unsubscribed.
3. Recipient has not already been sent the campaign.
4. Shared mailbox connection is valid.
5. Flow is enabled.
6. Recipient has a valid email address.
7. The flow run history contains no error.

## Replies are not matched

Check:

1. Reply arrived in the configured mailbox or folder.
2. Sender email matches a known contact.
3. Campaign identifier is present where matching requires it.
4. The unknown-sender queue has been reviewed.

Manual matching must be available when automatic matching fails.

## App cannot load data

Check:

1. User belongs to the correct Entra ID group.
2. The database user can read and write the required tables.
3. Connection references are connected.
4. Environment variables contain valid non-secret values.
5. Lists and columns have not been renamed unexpectedly.

## Flow reports missing configuration

Do not add a secret to a list or flow action. Correct the relevant environment variable or secure connection, then rerun the smoke test.
