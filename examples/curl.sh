#!/usr/bin/env bash
# Cloudzaro Domain Reseller API – complete flow with curl (needs jq)
# Usage: CZ_KEY=cz_live_... DOMAIN=example-shop.de ./curl.sh
set -euo pipefail

API="https://www.cloudzaro.com/api/v1"
: "${CZ_KEY:?Set CZ_KEY to your API key (cz_live_...)}"
DOMAIN="${DOMAIN:-example-shop.de}"
AUTH=(-H "Authorization: Bearer $CZ_KEY" -H "Content-Type: application/json")

echo "1) Availability"
curl -s "$API/check?name=$DOMAIN" "${AUTH[@]}" | jq .

echo "2) Holder contact"
CONTACT_ID=$(curl -s -X POST "$API/contacts" "${AUTH[@]}" -d '{
  "type": "company", "organization": "Example GmbH",
  "street": "Hauptstr. 1", "postal_code": "10115", "city": "Berlin", "country": "DE",
  "email": "admin@example-shop.de", "phone": "+49 30 1234567"
}' | jq -r .id)
echo "contact_id=$CONTACT_ID"

echo "3) Register (charged to the saved card)"
ORDER_ID=$(curl -s -X POST "$API/domains" "${AUTH[@]}" \
  -d "{\"domain\":\"$DOMAIN\",\"contact_id\":$CONTACT_ID,\"auto_renew\":true}" | jq -r .id)
echo "order_id=$ORDER_ID"

echo "4) Wait for the registry"
while true; do
  STATUS=$(curl -s "$API/orders/$ORDER_ID" "${AUTH[@]}" | jq -r .status)
  echo "   status: $STATUS"
  [ "$STATUS" = "completed" ] && break
  [ "$STATUS" = "failed" ] && { echo "Registration failed (payment refunded)"; exit 1; }
  sleep 30
done

echo "5) DNS record + DNSSEC"
curl -s -X POST "$API/domains/$DOMAIN/dns" "${AUTH[@]}" -d '{"name":"@","type":"A","content":"203.0.113.10","ttl":3600}' | jq .id
curl -s -X PUT "$API/domains/$DOMAIN/dnssec" "${AUTH[@]}" -d '{"enabled":true}' | jq .
