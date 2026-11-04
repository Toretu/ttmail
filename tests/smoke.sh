#!/usr/bin/env bash
# API smoke test. Requires the docker stack to be running and migrated.
set -uo pipefail
BASE="${BASE:-http://127.0.0.1:8081}"
BODY=$(mktemp)
JAR=$(mktemp)
VIEWER_JAR=$(mktemp)
trap 'rm -f "$BODY" "$JAR" "$VIEWER_JAR"' EXIT
fail=0

check() { # name expected_status method path [json]
  local name="$1" expected="$2" method="$3" path="$4" body="${5:-}"
  local extra=("${@:6}")
  local args=(-s -o "$BODY" -w '%{http_code}' -b "${COOKIES:-$JAR}" -X "$method" "$BASE$path")
  [ -n "$body" ] && args+=(-H 'Content-Type: application/json' -d "$body")
  [ ${#extra[@]} -gt 0 ] && args+=("${extra[@]}")
  local got; got=$(curl "${args[@]}")
  if [ "$got" = "$expected" ]; then echo "PASS $name"; else echo "FAIL $name (expected $expected, got $got): $(cat "$BODY")"; fail=1; fi
}
json_id() { php -r 'echo json_decode(file_get_contents($argv[1]), true)["data"]["id"];' "$BODY"; }

suffix=$(date +%s%N)
check "health (public)" 200 GET /api/health
check "unauthenticated is refused" 401 GET /api/companies
# Requires APP_ENV=development and AUTH_DEV_LOGIN=1 in .env
[ "$(curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -X POST "$BASE/auth/dev-login")" = 200 ] && echo "PASS dev login" || { echo "FAIL dev login"; exit 1; }
check "me" 200 GET /api/me
me_id=$(json_id)
check "cross-origin write refused" 403 POST /api/companies '{"name":"x"}' -H 'Origin: https://evil.example'
check "create viewer" 201 POST /api/users "{\"email\":\"viewer-$suffix@example.com\",\"role\":\"viewer\"}"
curl -s -o /dev/null -c "$VIEWER_JAR" -X POST -H 'Content-Type: application/json' "$BASE/auth/dev-login" -d "{\"email\":\"viewer-$suffix@example.com\"}"
COOKIES="$VIEWER_JAR" check "viewer can read" 200 GET /api/companies
COOKIES="$VIEWER_JAR" check "viewer cannot write" 403 POST /api/companies '{"name":"x"}'
COOKIES="$VIEWER_JAR" check "viewer cannot open settings" 403 GET /api/settings/graph
check "cannot change own role" 409 PUT "/api/users/$me_id" '{"role":"viewer"}'
check "health" 200 GET /api/health
check "unknown route" 404 GET /api/nope
check "wrong method" 405 DELETE /api/companies
check "company missing name" 422 POST /api/companies '{"city":"Oslo"}'
check "company bad website" 422 POST /api/companies '{"name":"X","website":"javascript:alert(1)"}'
check "invalid json" 400 POST /api/companies '{bad'
check "create company" 201 POST /api/companies "{\"name\":\"Smoke AS $suffix\",\"city\":\"Oslo\"}"
company_id=$(json_id)
check "contact invalid email" 422 POST /api/contacts "{\"company_id\":$company_id,\"first_name\":\"A\",\"last_name\":\"B\",\"email\":\"nope\"}"
check "contact unknown company" 422 POST /api/contacts '{"company_id":999999999,"first_name":"A","last_name":"B","email":"orphan@example.com"}'
check "create contact" 201 POST /api/contacts "{\"company_id\":$company_id,\"first_name\":\"Kari\",\"last_name\":\"Berg\",\"email\":\"Kari-$suffix@Example.com\"}"
contact_id=$(json_id)
check "duplicate email" 409 POST /api/contacts "{\"company_id\":$company_id,\"first_name\":\"K\",\"last_name\":\"B\",\"email\":\"kari-$suffix@example.com\"}"
check "update company" 200 PUT "/api/companies/$company_id" '{"status":"inactive"}'
check "search companies" 200 GET "/api/companies?q=Smoke"
check "unsubscribe" 200 POST "/api/contacts/$contact_id/unsubscribe"
grep -q '"consent_status":"withdrawn"' "$BODY" && echo "PASS unsubscribe recorded" || { echo "FAIL unsubscribe not recorded"; fail=1; }
check "get missing contact" 404 GET /api/contacts/999999999
check "create campaign" 201 POST /api/campaigns "{\"name\":\"Smoke campaign $suffix\"}"
campaign_id=$(json_id)
check "product bad price" 422 POST "/api/campaigns/$campaign_id/products" '{"name":"X","price":"-1"}'
check "limited product" 201 POST "/api/campaigns/$campaign_id/products" '{"name":"Back cover","price":"5000","quantity_available":1}'
limited_id=$(json_id)
check "unlimited product" 201 POST "/api/campaigns/$campaign_id/products" '{"name":"Half page","price":"1500,50"}'
unlimited_id=$(json_id)
check "duplicate product" 409 POST "/api/campaigns/$campaign_id/products" '{"name":"Half page","price":"1"}'
check "sell last item" 201 POST "/api/campaigns/$campaign_id/sales" "{\"product_id\":$limited_id,\"company_id\":$company_id,\"status\":\"sold\"}"
sale_id=$(json_id)
check "sold out" 409 POST "/api/campaigns/$campaign_id/sales" "{\"product_id\":$limited_id,\"company_id\":$company_id}"
check "unlimited sale" 201 POST "/api/campaigns/$campaign_id/sales" "{\"product_id\":$unlimited_id,\"company_id\":$company_id,\"quantity\":50}"
check "capacity below sold" 409 PUT "/api/products/$limited_id" '{"quantity_available":0}'
check "summary" 200 GET "/api/campaigns/$campaign_id/summary"
grep -q '"remaining":0' "$BODY" && grep -q '"remaining":null' "$BODY" && echo "PASS summary remaining" || { echo "FAIL summary remaining"; fail=1; }
check "cancel frees item" 200 PUT "/api/sales/$sale_id" '{"status":"cancelled"}'
check "sell again" 201 POST "/api/campaigns/$campaign_id/sales" "{\"product_id\":$limited_id,\"company_id\":$company_id}"

# Groups and recipients
check "create active company" 201 POST /api/companies "{\"name\":\"Active AS $suffix\",\"city\":\"Bergen\",\"category\":\"smoke-$suffix\"}"
active_company=$(json_id)
check "good contact" 201 POST /api/contacts "{\"company_id\":$active_company,\"first_name\":\"Ola\",\"last_name\":\"Nord\",\"email\":\"ola-$suffix@example.com\"}"
good_contact=$(json_id)
check "second contact" 201 POST /api/contacts "{\"company_id\":$active_company,\"first_name\":\"Eva\",\"last_name\":\"Sor\",\"email\":\"eva-$suffix@example.com\"}"
unsub_contact=$(json_id)
check "unsubscribe second" 200 POST "/api/contacts/$unsub_contact/unsubscribe"
check "create group" 201 POST /api/groups "{\"name\":\"Smoke group $suffix\"}"
group_id=$(json_id)
check "duplicate group" 409 POST /api/groups "{\"name\":\"Smoke group $suffix\"}"
check "add members by filter" 200 POST "/api/groups/$group_id/members" "{\"category\":\"smoke-$suffix\"}"
grep -q '"added":2' "$BODY" && echo "PASS group filter added 2" || { echo "FAIL group filter: $(cat "$BODY")"; fail=1; }
check "add members again" 200 POST "/api/groups/$group_id/members" "{\"contact_ids\":[$good_contact]}"
grep -q '"added":0' "$BODY" && echo "PASS no duplicate member" || { echo "FAIL duplicate member"; fail=1; }
check "list members" 200 GET "/api/groups/$group_id/members"
check "recipients need a selection" 422 POST "/api/campaigns/$campaign_id/recipients" '{"all":false}'
check "recipients preview" 200 POST "/api/campaigns/$campaign_id/recipients?mode=preview" "{\"group_ids\":[$group_id]}"
grep -q '"to_add":1' "$BODY" && grep -q '"unsubscribed":1' "$BODY" && echo "PASS preview excludes unsubscribed" || { echo "FAIL preview: $(cat "$BODY")"; fail=1; }
check "recipients commit" 200 POST "/api/campaigns/$campaign_id/recipients" "{\"group_ids\":[$group_id]}"
grep -q '"added":1' "$BODY" && echo "PASS recipient added" || { echo "FAIL commit: $(cat "$BODY")"; fail=1; }
check "recipients commit again" 200 POST "/api/campaigns/$campaign_id/recipients" "{\"group_ids\":[$group_id]}"
grep -q '"added":0' "$BODY" && grep -q '"already_in_campaign":1' "$BODY" && echo "PASS no duplicate recipient" || { echo "FAIL duplicate: $(cat "$BODY")"; fail=1; }
check "list recipients" 200 GET "/api/campaigns/$campaign_id/recipients"
grep -q '"total":1' "$BODY" && echo "PASS recipient total" || { echo "FAIL total"; fail=1; }
check "remove recipient" 200 DELETE "/api/campaigns/$campaign_id/recipients/$good_contact"
check "remove recipient twice" 404 DELETE "/api/campaigns/$campaign_id/recipients/$good_contact"
check "remove member" 200 DELETE "/api/groups/$group_id/members/$good_contact"
check "delete group wrong name" 422 DELETE "/api/groups/$group_id" '{"confirm_name":"x"}'
check "delete group" 200 DELETE "/api/groups/$group_id" "{\"confirm_name\":\"Smoke group $suffix\"}"
exit $fail
