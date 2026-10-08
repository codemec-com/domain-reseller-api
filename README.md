# Domain Reseller API – register domains via REST (Cloudzaro)

[![OpenAPI 3.0](https://img.shields.io/badge/OpenAPI-3.0-6BA539?logo=openapiinitiative&logoColor=white)](openapi.json)
[![Docs](https://img.shields.io/badge/docs-Swagger-85EA2D?logo=swagger&logoColor=black)](https://www.cloudzaro.com/api/docs)
[![Hosted in Germany](https://img.shields.io/badge/hosted%20in-Germany-000000)](https://www.cloudzaro.com)
[![License: MIT](https://img.shields.io/badge/examples-MIT-blue.svg)](LICENSE)

**Simple REST/JSON domain API for agencies, web designers and hosting resellers.**
Check availability, register and transfer domains, manage holder contacts, DNS records and DNSSEC for your customers – with one API key, billed per order by card. DNS hosting and DNSSEC are included for free, servers are located in Germany (GDPR compliant).

- 🌍 Hundreds of domain endings: `.de`, `.com`, `.eu`, `.net`, `.org`, `.at`, `.ch`, `.io`, … – [live price list (JSON)](https://www.cloudzaro.com/domains/prices.json)
- ⚡ Register or transfer a domain with a single call
- 🧾 Holder contacts (person or company) created and updated via API
- 🛠 DNS records: `A`, `AAAA`, `CNAME`, `MX`, `TXT`, `SRV`, `CAA`, `NS`
- 🔐 DNSSEC on/off with one call – the key is published at the registry automatically
- 🔁 Automatic renewal by card, or let a domain expire
- 📄 OpenAPI 3 specification and interactive [Swagger documentation](https://www.cloudzaro.com/api/docs)

> This repository contains the OpenAPI specification and ready-to-run examples (curl, PHP, Node.js, Python).
> The API itself is operated by [Cloudzaro](https://www.cloudzaro.com/domain-reseller-api).

---

## Quick start

1. [Create a free Cloudzaro account](https://www.cloudzaro.com/register) and save a card (e.g. with your first domain order).
2. Dashboard → **Domain API** → *Create key*. The key (`cz_live_…`) is shown once.
3. Call the API:

```bash
export CZ_KEY="cz_live_..."

# Is the domain available?
curl -s "https://www.cloudzaro.com/api/v1/check?name=example-shop&tlds=de,com" \
  -H "Authorization: Bearer $CZ_KEY"
```

```json
{
  "results": [
    { "domain": "example-shop.de",  "status": "available", "on_request": false },
    { "domain": "example-shop.com", "status": "taken",     "on_request": false }
  ],
  "prices": "https://www.cloudzaro.com/domains/prices.json"
}
```

### Register a domain

```bash
# 1. Holder contact (your end customer)
curl -s -X POST https://www.cloudzaro.com/api/v1/contacts \
  -H "Authorization: Bearer $CZ_KEY" -H "Content-Type: application/json" \
  -d '{"type":"company","organization":"Example GmbH","street":"Hauptstr. 1","postal_code":"10115","city":"Berlin","country":"DE","email":"admin@example-shop.de","phone":"+49 30 1234567"}'

# 2. Order (charged immediately to the saved card)
curl -s -X POST https://www.cloudzaro.com/api/v1/domains \
  -H "Authorization: Bearer $CZ_KEY" -H "Content-Type: application/json" \
  -d '{"domain":"example-shop.de","contact_id":12,"auto_renew":true}'

# 3. Poll the order until status = completed (usually a few minutes)
curl -s https://www.cloudzaro.com/api/v1/orders/815 -H "Authorization: Bearer $CZ_KEY"

# 4. DNS record + DNSSEC
curl -s -X POST https://www.cloudzaro.com/api/v1/domains/example-shop.de/dns \
  -H "Authorization: Bearer $CZ_KEY" -H "Content-Type: application/json" \
  -d '{"name":"@","type":"A","content":"203.0.113.10","ttl":3600}'
curl -s -X PUT https://www.cloudzaro.com/api/v1/domains/example-shop.de/dnssec \
  -H "Authorization: Bearer $CZ_KEY" -H "Content-Type: application/json" -d '{"enabled":true}'
```

Complete scripts: [`examples/`](examples) – [curl](examples/curl.sh) · [PHP](examples/php/register-domain.php) · [Node.js](examples/node/register-domain.mjs) · [Python](examples/python/register_domain.py)

---

## Endpoints

Base URL: `https://www.cloudzaro.com/api/v1`

| Method | Path | Description |
|---|---|---|
| `GET` | `/check?name=&tlds=` | Availability at the registry (max. 10 endings per call) |
| `GET` `POST` | `/contacts` | List / create holder contacts |
| `GET` `PUT` | `/contacts/{id}` | Get / update a contact |
| `GET` `POST` | `/domains` | List your domains / register a domain |
| `POST` | `/transfers` | Transfer a domain (auth code) |
| `GET` | `/orders/{id}` | Order status (`processing` → `completed` or `failed`) |
| `GET` `PATCH` | `/domains/{domain}` | Details / change `auto_renew` or holder |
| `GET` | `/domains/{domain}/authcode` | Auth code for a transfer away |
| `PUT` | `/domains/{domain}/dnssec` | DNSSEC on/off `{"enabled": true}` |
| `GET` `POST` | `/domains/{domain}/dns` | List / add DNS records |
| `PUT` `DELETE` | `/domains/{domain}/dns/{record_id}` | Update / delete a record |

Full reference: [openapi.json](openapi.json) · [Swagger UI](https://www.cloudzaro.com/api/docs)

## Authentication & limits

- `Authorization: Bearer cz_live_…` – keys are created and revoked in the dashboard.
- 120 requests per minute per key.
- After 10 invalid keys an IP address is locked for 15 minutes.

## Orders, billing and prices

- Registrations and transfers run **asynchronously**: `POST /domains` returns `202` with an order; poll `GET /orders/{id}`.
- Orders are **charged immediately to the card saved in your account**. Without a card the API answers `402 no_card`; if the bank declines or requires confirmation, `402 payment_failed`.
- **Renewals** are charged automatically before expiry (with retries; if the bank asks for confirmation you receive an e-mail). Set `auto_renew: false` to let a domain expire.
- If a registration fails, the payment is refunded automatically.
- **Prices:** regular prices, no base fee, no minimum volume – always current in the [price list JSON](https://www.cloudzaro.com/domains/prices.json) (incl. 19 % VAT, without guarantee).
- Endings that require proof of eligibility (`on_request: true`, e.g. `.us`, `.ca`) and holder changes that cost a registry fee are available on request via [support](https://www.cloudzaro.com/support).

## Errors

All errors use the same format and a meaningful HTTP status:

```json
{ "error": { "code": "invalid_domain", "message": "Unknown domain or ending not offered." } }
```

| Status | Typical codes |
|---|---|
| 401 | `unauthorized` |
| 402 | `no_card`, `payment_failed` |
| 404 | `not_found` |
| 409 | `owner_change_on_request`, `contract_ended` |
| 422 | `invalid_domain`, `invalid_contact`, `on_request`, `invalid_record`, … |
| 429 | `rate_limited`, `locked` |
| 502 | `registry_error`, `dns_unavailable` |

## FAQ

**Do I need a reseller contract with a registry?** No. You order through Cloudzaro; your end customer is registered as the domain holder.

**May I resell the domains?** Yes, at your own prices. You enter correct holder data and are the first point of contact for your customer.

**Are DNS and DNSSEC included?** Yes, every domain runs free of charge on the Cloudzaro nameservers (`alpha.ns.cloudzaro.com`, `beta.ns.cloudzaro.com`) in Germany.

**Is there a sandbox?** Not yet – use a cheap ending for your first test order.

---

## Deutsch – Domain-API für Reseller

Einfache REST-API für Agenturen, Webdesigner und Hoster: Verfügbarkeit prüfen, Domains registrieren und transferieren, Inhaber, DNS-Einträge und DNSSEC verwalten. Abrechnung pro Bestellung über die hinterlegte Karte, DNS-Hosting und DNSSEC kostenlos, Server in Deutschland (DSGVO-konform). Aktuelle Preise: [prices.json](https://www.cloudzaro.com/domains/prices.json) · Infos: [cloudzaro.com/domain-reseller-api](https://www.cloudzaro.com/domain-reseller-api) · Doku: [Swagger](https://www.cloudzaro.com/api/docs)

## Links

- Product page: https://www.cloudzaro.com/domain-reseller-api
- API documentation: https://www.cloudzaro.com/api/docs
- OpenAPI spec (live): https://www.cloudzaro.com/api/openapi.json
- Prices: https://www.cloudzaro.com/domains/prices.json
- Support: https://www.cloudzaro.com/support

The examples in this repository are MIT licensed. Use of the API is subject to the [Cloudzaro terms](https://www.cloudzaro.com/terms).
