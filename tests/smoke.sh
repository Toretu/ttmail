#!/usr/bin/env bash
# API smoke test. Requires the docker stack to be running and migrated.
set -uo pipefail
BASE="${BASE:-http://127.0.0.1:8081}"
BODY=$(mktemp)
JAR=$(mktemp)
VIEWER_JAR=$(mktemp)
EDITOR_JAR=$(mktemp)
trap 'rm -f "$BODY" "$JAR" "$VIEWER_JAR" "$EDITOR_JAR"' EXIT
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
# JSON helpers run PHP on the host when available, otherwise inside the web container (reads the response from stdin).
if command -v php >/dev/null 2>&1; then PHP=(php); else PHP=(docker compose exec -T web php); fi
json_id() { "${PHP[@]}" -r 'echo json_decode(stream_get_contents(STDIN), true)["data"]["id"];' < "$BODY"; }
json_first_id() { "${PHP[@]}" -r 'echo json_decode(stream_get_contents(STDIN), true)["data"][0]["id"];' < "$BODY"; }

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
check "create editor (boolean is_active)" 201 POST /api/users "{\"email\":\"editor-$suffix@example.com\",\"role\":\"editor\",\"is_active\":true}"
curl -s -o /dev/null -c "$EDITOR_JAR" -X POST -H 'Content-Type: application/json' "$BASE/auth/dev-login" -d "{\"email\":\"editor-$suffix@example.com\"}"
COOKIES="$VIEWER_JAR" check "viewer can read" 200 GET /api/companies
COOKIES="$VIEWER_JAR" check "viewer cannot write" 403 POST /api/companies '{"name":"x"}'
COOKIES="$VIEWER_JAR" check "viewer cannot open settings" 403 GET /api/settings/graph
COOKIES="$EDITOR_JAR" check "editor can write" 201 POST /api/companies "{\"name\":\"Editor AS $suffix\"}"
COOKIES="$EDITOR_JAR" check "editor cannot manage users" 403 GET /api/users
check "unknown non-api route with session" 404 GET /nope
COOKIES=/dev/null check "unauthenticated non-api route is refused" 401 GET /nope
check "graph status" 200 GET /api/settings/graph
if grep -q '"ready":false' "$BODY"; then
  check "missing Microsoft 365 configuration is reported" 422 POST /api/settings/graph/test '{}'
  grep -q '"missing"' "$BODY" && echo "PASS missing configuration names the keys" || { echo "FAIL missing config: $(cat "$BODY")"; fail=1; }
fi
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
COOKIES="$EDITOR_JAR" check "editor cannot add products or prices" 403 POST "/api/campaigns/$campaign_id/products" '{"name":"X","price":"1"}'
check "limited product" 201 POST "/api/campaigns/$campaign_id/products" '{"name":"Back cover","price":"5000","quantity_available":1}'
limited_id=$(json_id)
COOKIES="$EDITOR_JAR" check "editor cannot change a price" 403 PUT "/api/products/$limited_id" '{"price":"1"}'
COOKIES="$EDITOR_JAR" check "editor can read products" 200 GET "/api/campaigns/$campaign_id/products"
check "unlimited product" 201 POST "/api/campaigns/$campaign_id/products" '{"name":"Half page","price":"1500,50"}'
unlimited_id=$(json_id)
check "duplicate product" 409 POST "/api/campaigns/$campaign_id/products" '{"name":"Half page","price":"1"}'
check "sell last item" 201 POST "/api/campaigns/$campaign_id/sales" "{\"product_id\":$limited_id,\"company_id\":$company_id,\"status\":\"sold\"}"
sale_id=$(json_id)
COOKIES="$EDITOR_JAR" check "editor can register a sale" 201 POST "/api/campaigns/$campaign_id/sales" "{\"product_id\":$unlimited_id,\"company_id\":$company_id,\"quantity\":1}"
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

# Message, preview, unsubscribe
check "list mailboxes" 200 GET /api/mailboxes
if ! grep -q '"id":' "$BODY"; then
  check "create test mailbox" 201 POST /api/mailboxes '{"display_name":"Smoke test","email":"smoke-mailbox@example.com"}'
  check "list mailboxes again" 200 GET /api/mailboxes
fi
mailbox_id=$(json_first_id)
COOKIES="$EDITOR_JAR" check "editor cannot add mailboxes" 403 POST /api/mailboxes '{"display_name":"x","email":"x@example.com"}'
check "preview needs message" 422 POST "/api/campaigns/$campaign_id/message/preview"
check "unknown placeholder" 422 PUT "/api/campaigns/$campaign_id/message" "{\"mailbox_id\":$mailbox_id,\"subject\":\"Hi {{nope}}\",\"body\":\"x\"}"
check "unknown mailbox" 422 PUT "/api/campaigns/$campaign_id/message" '{"mailbox_id":999999,"subject":"s","body":"b"}'
check "save message" 200 PUT "/api/campaigns/$campaign_id/message" "{\"mailbox_id\":$mailbox_id,\"subject\":\"Hei {{first_name}}\",\"body\":\"Hei {{ first_name }} i {{company}} <b>x</b>\\nKatalog {{details_link}}\"}"
check "preview sample" 200 POST "/api/campaigns/$campaign_id/message/preview"
grep -q 'Hei Kari' "$BODY" && grep -q '&lt;b&gt;' "$BODY" && echo "PASS preview escapes html" || { echo "FAIL preview: $(cat "$BODY")"; fail=1; }
check "preview contact" 200 POST "/api/campaigns/$campaign_id/message/preview" "{\"contact_id\":$good_contact}"
grep -q 'Hei Ola' "$BODY" && grep -q "unsubscribe?t=00000000000000000000000000000000" "$BODY" && grep -q "reply?t=00000000000000000000000000000000" "$BODY" && echo "PASS preview personalised with inactive links" || { echo "FAIL preview links: $(cat "$BODY")"; fail=1; }
unsub_url=$(docker compose exec -T web php -r 'require "src/bootstrap.php"; echo Mailings\Mail\Unsubscribe::tokenFor((int) $argv[1]);' -- "$good_contact")
[ -n "$unsub_url" ] && echo "PASS unsubscribe token issued" || { echo "FAIL no unsubscribe token"; fail=1; }
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/reply?t=$unsub_url&c=$campaign_id"); [ "$code" = 404 ] && echo "PASS unsubscribe token does not open details page" || { echo "FAIL unsubscribe token opened details page $code"; fail=1; }
code=$(curl -s -o "$BODY" -w '%{http_code}' "$BASE/unsubscribe?t=$unsub_url"); [ "$code" = 200 ] && grep -q "<form" "$BODY" && echo "PASS unsubscribe page (no login)" || { echo "FAIL unsubscribe page $code"; fail=1; }
check "contact still subscribed after GET" 200 GET "/api/contacts/$good_contact"
grep -q '"unsubscribed_at":null' "$BODY" && echo "PASS GET does not unsubscribe" || { echo "FAIL GET unsubscribed"; fail=1; }
code=$(curl -s -o "$BODY" -w '%{http_code}' -X POST -d "t=$unsub_url" "$BASE/unsubscribe"); [ "$code" = 200 ] && echo "PASS unsubscribe confirm" || { echo "FAIL confirm $code"; fail=1; }
check "contact unsubscribed" 200 GET "/api/contacts/$good_contact"
grep -q '"consent_status":"withdrawn"' "$BODY" && echo "PASS unsubscribe recorded via link" || { echo "FAIL not recorded"; fail=1; }
check "cannot delete unsubscribed contact" 409 DELETE "/api/contacts/$good_contact"
check "cannot delete company with unsubscribed contact" 409 DELETE "/api/companies/$active_company" "{\"confirm_name\":\"Active AS $suffix\"}"
check "deletable contact" 201 POST /api/contacts "{\"company_id\":$active_company,\"first_name\":\"Tmp\",\"last_name\":\"Gone\",\"email\":\"tmp-$suffix@example.com\"}"
check "delete plain contact" 200 DELETE "/api/contacts/$(json_id)"
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/unsubscribe?t=bad"); [ "$code" = 404 ] && echo "PASS bad token 404" || { echo "FAIL bad token $code"; fail=1; }
check "test mail bad address" 422 POST "/api/campaigns/$campaign_id/message/test" '{"to":"nope"}'
COOKIES="$VIEWER_JAR" check "viewer cannot send test" 403 POST "/api/campaigns/$campaign_id/message/test" '{"to":"a@example.com"}'

# Approval and sending (needs MAIL_DRY_RUN=1 in .env; the worker runs inside the web container)
WORKER=${WORKER:-docker compose exec -T web php bin/send-queue.php}
check "send campaign" 201 POST /api/campaigns "{\"name\":\"Send campaign $suffix\"}"
send_id=$(json_id)
check "approve without message" 409 POST "/api/campaigns/$send_id/send/approve" '{"confirm_count":1}'
check "send message" 200 PUT "/api/campaigns/$send_id/message" "{\"mailbox_id\":$mailbox_id,\"subject\":\"Hei\",\"body\":\"Hei {{first_name}}\"}"
check "send company" 201 POST /api/companies "{\"name\":\"Send AS $suffix\",\"category\":\"send-$suffix\"}"
send_company=$(json_id)
for n in 1 2 3; do
  check "send contact $n" 201 POST /api/contacts "{\"company_id\":$send_company,\"first_name\":\"S$n\",\"last_name\":\"T\",\"email\":\"send$n-$suffix@example.com\"}"
  eval "send_contact_$n=$(json_id)"
done
check "add send recipients" 200 POST "/api/campaigns/$send_id/recipients" "{\"all\":true,\"category\":\"send-$suffix\"}"
grep -q '"added":3' "$BODY" && echo "PASS 3 recipients" || { echo "FAIL recipients: $(cat "$BODY")"; fail=1; }
check "approve needs test" 409 POST "/api/campaigns/$send_id/send/approve" '{"confirm_count":3}'
check "send test" 200 POST "/api/campaigns/$send_id/message/test" '{"to":"me@example.com"}'
check "approve wrong count" 422 POST "/api/campaigns/$send_id/send/approve" '{"confirm_count":2}'
COOKIES="$VIEWER_JAR" check "viewer cannot approve" 403 POST "/api/campaigns/$send_id/send/approve" '{"confirm_count":3}'
check "approve" 200 POST "/api/campaigns/$send_id/send/approve" '{"confirm_count":3}'
check "approve twice" 409 POST "/api/campaigns/$send_id/send/approve" '{"confirm_count":3}'
check "message locked" 409 PUT "/api/campaigns/$send_id/message" '{"subject":"changed"}'
check "recipients locked while sending" 409 POST "/api/campaigns/$send_id/recipients" '{"all":true}'
check "pause" 200 POST "/api/campaigns/$send_id/send/pause"
$WORKER >/dev/null 2>&1
check "status while paused" 200 GET "/api/campaigns/$send_id/send"
grep -q '"sent":0' "$BODY" && grep -q '"pending":3' "$BODY" && echo "PASS paused sends nothing" || { echo "FAIL paused: $(cat "$BODY")"; fail=1; }
check "unsubscribe one before sending" 200 POST "/api/contacts/$send_contact_3/unsubscribe"
check "resume" 200 POST "/api/campaigns/$send_id/send/resume"
$WORKER >/dev/null 2>&1
check "status after worker" 200 GET "/api/campaigns/$send_id/send"
grep -q '"sent":2' "$BODY" && grep -q '"skipped":1' "$BODY" && grep -q '"state":"done"' "$BODY" && echo "PASS sent 2, skipped unsubscribed, done" || { echo "FAIL after worker: $(cat "$BODY")"; fail=1; }
$WORKER >/dev/null 2>&1
check "status after second run" 200 GET "/api/campaigns/$send_id/send"
grep -q '"sent":2' "$BODY" && echo "PASS no duplicate sends" || { echo "FAIL duplicate: $(cat "$BODY")"; fail=1; }
check "cannot delete mailed campaign" 409 DELETE "/api/campaigns/$send_id" "{\"confirm_name\":\"Send campaign $suffix\"}"
check "cannot delete contact that was sent mail" 409 DELETE "/api/contacts/$send_contact_1"
check "cannot delete company whose contacts were sent mail" 409 DELETE "/api/companies/$send_company" "{\"confirm_name\":\"Send AS $suffix\"}"

# Interrupted send: the recipient becomes "unknown" and is never queued again automatically
docker compose exec -T web php -r 'require "src/bootstrap.php"; Mailings\Database::connect()->prepare("UPDATE campaign_recipients SET status = \"sending\", claimed_at = NOW() - INTERVAL 20 MINUTE WHERE campaign_id = ? AND contact_id = ?")->execute([(int) $argv[1], (int) $argv[2]]);' -- "$send_id" "$send_contact_2"
$WORKER >/dev/null 2>&1
check "status after interrupted send" 200 GET "/api/campaigns/$send_id/send"
grep -q '"unknown":1' "$BODY" && grep -q '"sent":1' "$BODY" && echo "PASS interrupted send marked unknown" || { echo "FAIL unknown status: $(cat "$BODY")"; fail=1; }
check "retry does not requeue unknown" 200 POST "/api/campaigns/$send_id/send/retry-failed"
grep -q '"requeued":0' "$BODY" && grep -q '"unknown":1' "$BODY" && echo "PASS unknown outcome not requeued" || { echo "FAIL unknown requeued: $(cat "$BODY")"; fail=1; }

# Public details page (personal link) and review
check "reply product" 201 POST "/api/campaigns/$send_id/products" '{"name":"Half page","price":"1500"}'
reply_product=$(json_id)
tok=$(docker compose exec -T web php -r 'require "src/bootstrap.php"; echo Mailings\Mail\DetailsLink::tokenFor((int) $argv[1], (int) $argv[2]);' -- "$send_id" "$send_contact_1")
code=$(curl -s -o "$BODY" -w '%{http_code}' "$BASE/reply?t=$tok&c=$send_id"); [ "$code" = 200 ] && grep -q "Half page" "$BODY" && echo "PASS reply page shows products" || { echo "FAIL reply page $code"; fail=1; }
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/reply?t=$tok&c=999999"); [ "$code" = 404 ] && echo "PASS reply wrong campaign 404" || { echo "FAIL reply wrong campaign $code"; fail=1; }
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/reply?t=bad&c=$send_id"); [ "$code" = 404 ] && echo "PASS reply bad token 404" || { echo "FAIL reply bad token $code"; fail=1; }
docker compose exec -T web php -r 'require "src/bootstrap.php"; Mailings\Database::connect()->prepare("UPDATE campaign_links SET expires_at = NOW() - INTERVAL 1 DAY WHERE token = ?")->execute([$argv[1]]);' -- "$tok"
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/reply?t=$tok&c=$send_id"); [ "$code" = 410 ] && echo "PASS expired reply link refused" || { echo "FAIL expired link $code"; fail=1; }
docker compose exec -T web php -r 'require "src/bootstrap.php"; Mailings\Database::connect()->prepare("UPDATE campaign_links SET expires_at = NOW() + INTERVAL 30 DAY WHERE token = ?")->execute([$argv[1]]);' -- "$tok"
code=$(curl -s -o "$BODY" -w '%{http_code}' -X POST --data-urlencode "t=$tok" -d "c=$send_id" --data-urlencode "company_city=Oslo $suffix" -d "contact_first_name=S1" -d "contact_last_name=T" -d "products[]=$reply_product" -d "products[]=999999" --data-urlencode "comment=<b>hi</b>" "$BASE/reply")
[ "$code" = 200 ] && grep -q "Thank you" "$BODY" && echo "PASS reply submitted" || { echo "FAIL reply submit $code"; fail=1; }
check "list responses" 200 GET "/api/campaigns/$send_id/responses"
grep -q "Oslo $suffix" "$BODY" && grep -q "Half page" "$BODY" && echo "PASS response listed" || { echo "FAIL responses: $(cat "$BODY")"; fail=1; }
check "company unchanged before apply" 200 GET "/api/companies/$send_company"
grep -q "Oslo $suffix" "$BODY" && { echo "FAIL city changed before apply"; fail=1; } || echo "PASS master data untouched"
check "list responses again" 200 GET "/api/campaigns/$send_id/responses"
resp_id=$(json_first_id)
COOKIES="$VIEWER_JAR" check "viewer cannot apply" 403 POST "/api/campaigns/$send_id/responses/$resp_id/apply" '{}'
check "apply response" 200 POST "/api/campaigns/$send_id/responses/$resp_id/apply" '{}'
check "company after apply" 200 GET "/api/companies/$send_company"
grep -q "Oslo $suffix" "$BODY" && echo "PASS corrections applied" || { echo "FAIL apply: $(cat "$BODY")"; fail=1; }
code=$(curl -s -o "$BODY" -w '%{http_code}' -X POST --data-urlencode "t=$tok" -d "c=$send_id" -d "contact_first_name=" "$BASE/reply"); grep -q "must not be empty\|first_name" "$BODY" && echo "PASS reply validation" || { echo "FAIL reply validation $code"; fail=1; }
check "products placeholder saved" 200 PUT "/api/campaigns/$campaign_id/message" "{\"mailbox_id\":$mailbox_id,\"subject\":\"S\",\"body\":\"Available:\\n{{products}}\"}"
check "product for mail" 201 POST "/api/campaigns/$campaign_id/products" '{"name":"Mail <b>page</b>","price":"2500"}'
check "products preview" 200 POST "/api/campaigns/$campaign_id/message/preview" '{}'
grep -q "<li>Mail &lt;b&gt;page&lt;/b&gt; – kr 2 500</li>" "$BODY" && echo "PASS products listed and escaped" || { echo "FAIL products in mail: $(cat "$BODY")"; fail=1; }
code=$(curl -s -o "$BODY" -w '%{http_code}' "$BASE/reply?t=00000000000000000000000000000000&c=$send_id"); [ "$code" = 200 ] && grep -q "Nothing you enter is saved" "$BODY" && echo "PASS test link opens preview" || { echo "FAIL test link $code"; fail=1; }
code=$(curl -s -o "$BODY" -w '%{http_code}' -X POST -d "t=00000000000000000000000000000000" -d "c=$send_id" -d "comment=x" "$BASE/reply"); grep -q "nothing was saved" "$BODY" && echo "PASS test link saves nothing" || { echo "FAIL test post $code"; fail=1; }

# Replies (messages are injected as Graph resources; the live fetch needs Microsoft)
docker compose exec -T web php -r 'require "src/bootstrap.php";
$msgs = [
 ["id"=>"smoke-$argv[2]-1","subject"=>"Re: Hei","from"=>["emailAddress"=>["address"=>strtoupper($argv[1])]],"receivedDateTime"=>gmdate("Y-m-d\\TH:i:s\\Z"),"bodyPreview"=>"Interested","body"=>["content"=>"Interested <script>x</script>"]],
 ["id"=>"smoke-$argv[2]-2","subject"=>"Spam","from"=>["emailAddress"=>["address"=>"stranger-$argv[2]@example.org"]],"receivedDateTime"=>gmdate("Y-m-d\\TH:i:s\\Z"),"bodyPreview"=>"x","body"=>["content"=>"x"]],
];
$mb = (int) Mailings\Database::connect()->query("SELECT id FROM mailboxes LIMIT 1")->fetchColumn();
$a = (new Mailings\Mail\Replies())->store($mb, "me@example.com", $msgs);
$b = (new Mailings\Mail\Replies())->store($mb, "me@example.com", $msgs);
echo $a["stored"], $a["unknown"], $b["stored"];' -- "send1-$suffix@example.com" "$suffix" > "$BODY"
[ "$(cat "$BODY")" = "110" ] && echo "PASS reply stored once, unknown sender ignored" || { echo "FAIL reply store: $(cat "$BODY")"; fail=1; }
check "list replies" 200 GET "/api/replies?campaign_id=$send_id"
grep -q "Re: Hei" "$BODY" && grep -q '"new":' "$BODY" && echo "PASS reply listed and matched to campaign" || { echo "FAIL reply list: $(cat "$BODY")"; fail=1; }
reply_id=$(json_first_id)
check "get reply" 200 GET "/api/replies/$reply_id"
check "reply bad stage" 422 PUT "/api/replies/$reply_id" '{"stage":"nope"}'
check "reply bad date" 422 PUT "/api/replies/$reply_id" '{"next_followup":"2026-13-45"}'
COOKIES="$VIEWER_JAR" check "viewer cannot change stage" 403 PUT "/api/replies/$reply_id" '{"stage":"interested"}'
check "set stage" 200 PUT "/api/replies/$reply_id" '{"stage":"needs_call","next_followup":"2020-01-01","note":"Call Monday"}'
grep -q '"stage":"needs_call"' "$BODY" && grep -q '"new_stage":"needs_call"' "$BODY" && echo "PASS stage saved with history" || { echo "FAIL stage: $(cat "$BODY")"; fail=1; }
check "due filter" 200 GET "/api/replies?due=1"
grep -q "Re: Hei" "$BODY" && echo "PASS follow-up due filter" || { echo "FAIL due: $(cat "$BODY")"; fail=1; }
check "sync in dry run" 200 POST /api/replies/sync '{}'
exit $fail
