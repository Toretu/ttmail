# Editing and deleting companies and contacts

UI: `http://127.0.0.1:8081/records.html` (tabs for Companies and Contacts; search, filter, add, edit, delete). Also linked from the mockup's Companies and Contacts screens.

| Method | Path | Notes |
|---|---|---|
| PUT | `/api/companies/{id}`, `/api/contacts/{id}` | Partial update of any editable field |
| DELETE | `/api/companies/{id}` | Body `{"confirm_name": "<exact company name>"}`. Deletes the company and its contacts (and their group memberships). Refused with 409 if the company has registered sales, or if any of its contacts has unsubscribed or been sent campaign mail; set the status to `inactive` or `do_not_contact` instead. |
| DELETE | `/api/contacts/{id}` | Deletes the contact and its group memberships. Refused with 409 if the contact has unsubscribed or been sent campaign mail (ADR-008); mark it `withdrawn` instead. |

Deletion is permanent. The company dialog requires typing the exact company name. Records with history are kept so that an address that asked not to be contacted is never mailed again after a re-import; see [security.md](security.md) for data-subject deletion requests.
