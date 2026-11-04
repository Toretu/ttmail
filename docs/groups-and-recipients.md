# Groups and campaign recipients

## Groups (page: Groups)
- Create, rename, deactivate and delete groups. Delete requires typing the group name; contacts are not affected.
- Add members **by filter** (company category, city, or everyone) with a preview first, or **individually** by searching for a contact.
- Unsubscribed contacts may stay in a group. They are skipped when a campaign picks recipients.

## Recipients (page: Campaigns, Recipients panel)
1. Pick one or more groups, or "all contacts". Optionally narrow by company category and city.
2. **Preview** shows how many match, how many will be added, how many are already in the campaign, and how many are excluded and why.
3. **Add recipients** stores them as `pending`. Nothing is sent. Pending recipients can be removed; sent ones cannot.

Never added: unsubscribed or withdrawn contacts, companies that are inactive or do-not-contact, invalid email addresses. A closed campaign accepts no new recipients. The same contact cannot be added twice to one campaign.

## API
| Method and path | Purpose |
|---|---|
| `GET/POST /api/groups`, `GET/PUT/DELETE /api/groups/{id}` | Manage groups (delete needs `confirm_name`) |
| `GET /api/groups/{id}/members` | List members |
| `POST /api/groups/{id}/members[?mode=preview]` | Body `{"contact_ids":[..]}` or `{"category","city","all"}` |
| `DELETE /api/groups/{id}/members/{contact_id}` | Remove a member |
| `GET /api/campaigns/{id}/recipients` | List with counts per status |
| `POST /api/campaigns/{id}/recipients[?mode=preview]` | Body `{"all":true}` or `{"group_ids":[..]}`, optional `category`, `city` |
| `DELETE /api/campaigns/{id}/recipients/{contact_id}` | Remove a pending recipient |

Editors can change data; viewers can read.
