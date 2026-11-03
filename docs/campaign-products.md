# Campaign products and sales

Each campaign has its own products (e.g. Full page, Half page, Middle page, Last page, Back cover) with a price and a number available. Leave the number empty for unlimited.

UI: `http://127.0.0.1:8081/campaigns.html` (create a campaign, add products, register sales, see the summary).

## API

| Method | Path | Purpose |
|---|---|---|
| GET/POST | `/api/campaigns` | List / create (`name`, `description`, `status`) |
| GET/PUT | `/api/campaigns/{id}` | Read / update |
| GET/POST | `/api/campaigns/{id}/products` | List / add (`name`, `price`, `quantity_available`, `description`, `sort_order`) |
| PUT | `/api/products/{id}` | Change price, name or capacity |
| GET/POST | `/api/campaigns/{id}/sales` | List (filter `status`, `product_id`, `company_id`) / register (`product_id`, `company_id`, `quantity`, `status`, `unit_price`, `notes`) |
| PUT | `/api/sales/{id}` | Change `status`, `quantity`, `unit_price`, `notes` |
| GET | `/api/campaigns/{id}/summary` | Per product: capacity, reserved, sold, remaining, values; plus totals |

## Rules
- Prices are non-negative with at most 2 decimals (`1500,50` is accepted).
- `quantity_available` empty/null = unlimited. It cannot be set below what is already reserved or sold (409).
- Reserved and sold count against the capacity; cancelled does not. Exceeding it returns 409 ("Only N left" / "sold out").
- A sale stores the price at the time (`unit_price`, defaults to the product price), so later price changes do not change history.
- The product must belong to the campaign in the URL.

## Deleting a campaign
`DELETE /api/campaigns/{id}` with body `{"confirm_name": "<exact campaign name>"}`. A missing or non-matching name returns 422 and nothing is deleted. On success the campaign, its products and all its sales are removed in one transaction (companies are untouched) and the counts are returned. This cannot be undone. In the UI the Delete button on the campaigns page stays disabled until the name is typed exactly.
