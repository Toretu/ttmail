# Importing companies and contacts (CSV / Excel)

Endpoint: `POST /api/companies/import?mode=preview|commit`, multipart field `file`.

- Formats: `.csv` (UTF-8 or Windows-1252; `,` `;` or tab, auto-detected) and `.xlsx` (first sheet). Max 2 MB, 2000 data rows.
- Always run `mode=preview` first; `mode=commit` inserts only rows marked `new`, in one transaction.
- Template: [templates/companies-import.csv](templates/companies-import.csv).

## Columns
Only `name` is required. Header names are case/space/punctuation-insensitive; Norwegian aliases work.

| Field | Accepted headers |
|---|---|
| name | name, company, navn, firmanavn, firma, selskap, bedrift |
| org_number | orgnr, organisasjonsnummer |
| billing_address | address, adresse, fakturaadresse |
| postal_code | postnr, postnummer, zip |
| city | city, by, sted, poststed |
| country | country, land (2-letter code, default NO) |
| website | website, nettside |
| category | category, kategori, bransje |
| status | status (`active`, `inactive`, `do_not_contact`) |
| notes | notes, notater, merknad |

Unknown columns are ignored and listed in `ignored_columns`.

## Duplicates and invalid rows
A row is a duplicate if the org number, or name + city, already exists in the database or earlier in the file. Duplicates and invalid rows are skipped and reported with their file row number, so re-importing is safe.

## Importing contacts
`POST /api/contacts/import?mode=preview|commit`, same contract, limits and preview/commit flow. Template: [templates/contacts-import.csv](templates/contacts-import.csv).

| Field | Accepted headers |
|---|---|
| company | company, firma, firmanavn, selskap, bedrift (exact name match) |
| org_number | orgnr, organisasjonsnummer (preferred; use it when names are ambiguous) |
| first_name / last_name | firstname, fornavn / lastname, etternavn |
| name | name, kontaktperson, navn (split into first and last name when those columns are missing) |
| job_title | title, stilling, tittel |
| email | email, epost, mail (required) |
| phone | phone, telefon, tlf, mobil |
| consent_status | consent, samtykke (`unknown`, `granted`, `withdrawn`) |
| notes | notes, notater |

Each contact must match exactly one existing company, so import companies first. A row is a duplicate if the email already exists in the database or earlier in the file. Rows with no matching company, several matching companies, or an invalid email are reported as invalid.

## Import screen
Open `http://127.0.0.1:8081/import.html` (served from `public/import.html`). Choose companies or contacts, pick a file, preview, then import. The mockup's Companies page links to it.

## Not yet supported
Norwegian status values (e.g. `aktiv`), and updating existing records (import only adds).
