# Connecting to Office 365 (Microsoft Graph)

The application sends and reads mail through Microsoft Graph using an **app registration** in Microsoft Entra ID (the "other side"). It signs in as the application itself (client credentials), so no user password or interactive login is involved. This guide covers the Microsoft setup and how to put the values on the server. Approved mailboxes: `programm@paradisskolekorps.no` and `salg@paradisskolekorps.no` (see [decisions](decisions.md)).

You need an account that is **Global Administrator** (or Application Administrator plus Exchange Administrator) in your Microsoft 365 tenant. There is no extra licence cost for an app registration. Shared mailboxes work and do not need a licence.

## 1. Register the application
1. Open [entra.microsoft.com](https://entra.microsoft.com) → **Identity → Applications → App registrations → New registration**.
2. Name: `Mailings`. Supported account types: **Accounts in this organizational directory only (single tenant)**. Leave the redirect URI empty. Click **Register**.
3. On the **Overview** page copy:
   - **Directory (tenant) ID** → `GRAPH_TENANT_ID`
   - **Application (client) ID** → `GRAPH_CLIENT_ID`

## 2. Add Graph permissions
1. **API permissions → Add a permission → Microsoft Graph → Application permissions**.
2. Add:
   - `Mail.Send` – send campaign mail from the approved mailboxes.
   - `Mail.ReadWrite` – read replies and mark/move them. If replies should only be read, use `Mail.Read` instead.
3. Click **Grant admin consent for <your organisation>**. Both rows must show a green check under *Status*.

Do not add other permissions. Do not use *Delegated* permissions.

## 3. Create a client secret
1. **Certificates & secrets → Client secrets → New client secret**. Pick an expiry (12 months is a good balance; the maximum is 24).
2. Copy the secret **Value** (not the Secret ID) immediately; it is shown only once. This is `GRAPH_CLIENT_SECRET`.
3. Add a calendar reminder a few weeks before the expiry date. When it expires, sending stops until you create a new secret and update the server.

## 3b. Allow people to sign in (redirect URI)
The same app registration is used for "Sign in with Microsoft".
1. **Authentication → Add a platform → Web**. Redirect URI: `<APP_URL>/auth/callback` (locally `http://127.0.0.1:8081/auth/callback`; in production your https address, for example `https://mailings.example.org/auth/callback`). Do not enable implicit grant or hybrid flows.
2. The default delegated permission `User.Read` (added automatically) is enough; `openid`, `profile` and `email` are requested at sign-in.
3. In `.env` set `AUTH_BOOTSTRAP_ADMIN` to your own sign-in address (your Microsoft 365 user principal name, e.g. `you@paradisskolekorps.no`). The first time that address signs in with no admin present it becomes the administrator. Then add the other people under **Settings → Users**.
4. `APP_URL` must exactly match the address people use, including `https://`, or Microsoft will reject the redirect.

Anyone from outside your organisation cannot sign in because the app is single tenant, and anyone inside it still needs to be added under Users.

## 4. Restrict the app to the approved mailboxes
Application permissions normally allow access to **every mailbox in the organisation**. Restrict them in Exchange Online so the app can only touch the approved mailboxes. Connect with Exchange Online PowerShell (`Connect-ExchangeOnline`) and run:

```powershell
# 1. A mail-enabled security group containing only the approved mailboxes
New-DistributionGroup -Name "Mailings allowed mailboxes" -Alias mailings-allowed -Type Security `
  -PrimarySmtpAddress mailings-allowed@paradisskolekorps.no
Add-DistributionGroupMember -Identity mailings-allowed -Member programm@paradisskolekorps.no
Add-DistributionGroupMember -Identity mailings-allowed -Member salg@paradisskolekorps.no

# 2. Limit the app to that group
New-ApplicationAccessPolicy -AppId <client id> -PolicyScopeGroupId mailings-allowed@paradisskolekorps.no `
  -AccessRight RestrictAccess -Description "Mailings app: approved mailboxes only"

# 3. Verify: expect Granted for the approved mailboxes and Denied for any other
Test-ApplicationAccessPolicy -Identity programm@paradisskolekorps.no -AppId <client id>
Test-ApplicationAccessPolicy -Identity someone.else@paradisskolekorps.no -AppId <client id>
```

Notes:
- The policy can take up to about an hour to take effect.
- To approve another mailbox later, add it to the group **and** add it under Settings → Mailboxes in the application.
- Microsoft is moving towards *RBAC for Applications* in Exchange Online as the replacement for Application Access Policies. Application Access Policies still work; check Microsoft's current documentation if `New-ApplicationAccessPolicy` is unavailable in your tenant.

## 5. Put the values on the server
Never commit these values and never put them in the database, a web page, or an email.

- **Local Docker:** edit `.env` (created by `bin/init-env.sh`):
  ```
  GRAPH_TENANT_ID=<tenant id>
  GRAPH_CLIENT_ID=<client id>
  GRAPH_CLIENT_SECRET=<secret value>
  ```
  Then run `docker compose up -d` so the web container reloads the file.
- **Production:** set the same three variables in the server's protected environment or a config file outside the web root that only the web server user can read (for example mode `0600`). See [deployment](deployment.md) and [configuration](configuration.md).

## 6. Test
Open **Settings** (`/settings.html`) and press **Test connection**. It checks:
1. Sign-in to Microsoft succeeds.
2. `Mail.Send` and `Mail.Read`/`Mail.ReadWrite` are present in the granted permissions.
3. Each active mailbox can be read.

The test sends no email.

## Troubleshooting
| Message | Meaning and fix |
|---|---|
| `AADSTS7000215` Invalid client secret | Wrong value, or you copied the Secret ID instead of the Value. Create a new secret. |
| `AADSTS7000222` Client secret expired | Create a new secret and update `.env`. |
| `AADSTS700016` Application not found | Wrong `GRAPH_CLIENT_ID` or `GRAPH_TENANT_ID`. |
| `AADSTS90002` / `AADSTS900021` Tenant not valid | Wrong `GRAPH_TENANT_ID`. |
| Permission Mail.Send "missing" | Permission not added, or admin consent not granted (step 2). |
| `ErrorAccessDenied` on a mailbox | The mailbox is not in the allowed group, the access policy has not propagated yet, or admin consent is missing. |
| `ErrorInvalidUser` / `MailboxNotFound` | The address is misspelled or the mailbox does not exist. |
| `AADSTS50011` redirect URI mismatch | The redirect URI in Entra does not exactly match `<APP_URL>/auth/callback` (step 3b). |
| "is not authorised to use this application" | The person is not in Settings → Users, is disabled, or signed in with a different address than the one listed (use the user principal name). |
| `Could not reach Microsoft` | The server cannot reach `login.microsoftonline.com` / `graph.microsoft.com` over HTTPS (firewall or DNS). |

## Rotating the secret
Create a new secret in Entra (keep the old one until the new one works), update the server value, restart, run **Test connection**, then delete the old secret in Entra.

## Not yet implemented
Sending and reply retrieval are separate phases; this step only configures and verifies the connection. A test-email feature will be added together with the sending feature and login.
