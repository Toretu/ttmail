# Editing and deleting companies and contacts

UI: `http://127.0.0.1:8081/records.html` (tabs for Companies and Contacts; search, filter, add, edit, delete). Also linked from the mockup's Companies and Contacts screens.

| Method | Path | Notes |
|---|---|---|
| PUT | `/api/companies/{id}`, `/api/contacts/{id}` | Partial update of any editable field |
| DELETE | `/api/companies/{id}` | Body `{"confirm_name": "<exact company name>"}`. Deletes the company and its contacts (and their group memberships). Refused with 409 if the company has registered sales; set the status to `inactive` instead. |
| DELETE | `/api/contacts/{id}` | Deletes the contact and its group memberships |

Deletion is permanent. The company dialog requires typing the exact company name.
