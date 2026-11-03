# Mailings Mockup

This is a no-build browser prototype of the planned self-hosted solution. It uses in-memory sample data and does not connect to MariaDB, Microsoft Graph, Office 365, or any external service.

## Run it

Open [`index.html`](index.html) in a browser. No installation or secrets are required.

## Included views

- Dashboard
- Companies
- Contacts
- Groups
- Campaigns
- Replies and follow-up Kanban board
- Advertising products
- Opportunities
- Settings

The company form, navigation, search fields, and mock actions are interactive. Refreshing the page resets the sample data.

## Backend mapping

| Mockup view | Planned backend |
|---|---|
| Companies and contacts | MariaDB Companies and Contacts tables |
| Groups | MariaDB Groups and Group Members tables |
| Campaigns | MariaDB Campaigns and Campaign Recipients tables |
| Replies | Microsoft Graph mail adapter plus Activities |
| Advertising products | MariaDB Advertising Products table |
| Opportunities | MariaDB Opportunities table |
| Settings | Server configuration and Microsoft Graph app registration |
