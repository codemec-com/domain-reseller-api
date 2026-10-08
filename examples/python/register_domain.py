"""Cloudzaro Domain Reseller API – register a domain with Python 3.8+ (standard library only).

Usage: CZ_KEY=cz_live_... python register_domain.py example-shop.de
"""
import json
import os
import sys
import time
import urllib.error
import urllib.request

API = "https://www.cloudzaro.com/api/v1"


def cz(method, path, body=None):
    req = urllib.request.Request(
        API + path,
        method=method,
        data=json.dumps(body).encode() if body is not None else None,
        headers={
            "Authorization": "Bearer " + os.environ["CZ_KEY"],
            "Content-Type": "application/json",
            "Accept": "application/json",
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=60) as res:
            return json.load(res)
    except urllib.error.HTTPError as e:
        err = json.load(e).get("error", {})
        raise RuntimeError(f"{err.get('code')}: {err.get('message')}") from None


domain = sys.argv[1] if len(sys.argv) > 1 else "example-shop.de"

# 1) Availability
if cz("GET", f"/check?name={domain}")["results"][0]["status"] != "available":
    sys.exit(f"{domain} is not available")

# 2) Holder contact (your end customer)
contact = cz("POST", "/contacts", {
    "type": "person", "first_name": "Erika", "last_name": "Mustermann",
    "street": "Hauptstr. 1", "postal_code": "10115", "city": "Berlin", "country": "DE",
    "email": "erika@example.com", "phone": "+49 170 1234567",
})

# 3) Register – charged immediately to the saved card
order = cz("POST", "/domains", {"domain": domain, "contact_id": contact["id"], "auto_renew": True})

# 4) Wait for the registry
while order["status"] in ("paid", "processing"):
    time.sleep(30)
    order = cz("GET", f"/orders/{order['id']}")
    print("status:", order["status"])
if order["status"] != "completed":
    sys.exit("Registration failed – the payment has been refunded")

# 5) DNS records and DNSSEC
cz("POST", f"/domains/{domain}/dns", {"name": "@", "type": "MX", "content": "mail.example.com.", "priority": 10, "ttl": 3600})
cz("PUT", f"/domains/{domain}/dnssec", {"enabled": True})
print(f"{domain} is ready")
