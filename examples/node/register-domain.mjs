// Cloudzaro Domain Reseller API – register a domain with Node.js 18+ (built-in fetch)
// Usage: CZ_KEY=cz_live_... node register-domain.mjs example-shop.de

const API = 'https://www.cloudzaro.com/api/v1';

async function cz(method, path, body) {
  const res = await fetch(API + path, {
    method,
    headers: { Authorization: `Bearer ${process.env.CZ_KEY}`, 'Content-Type': 'application/json', Accept: 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await res.json();
  if (!res.ok) throw new Error(`${data.error?.code}: ${data.error?.message}`);
  return data;
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const domain = process.argv[2] ?? 'example-shop.de';

// 1) Availability
const check = await cz('GET', `/check?name=${encodeURIComponent(domain)}`);
if (check.results[0].status !== 'available') throw new Error(`${domain} is not available`);

// 2) Holder contact (your end customer)
const contact = await cz('POST', '/contacts', {
  type: 'company', organization: 'Example GmbH',
  street: 'Hauptstr. 1', postal_code: '10115', city: 'Berlin', country: 'DE',
  email: 'admin@example-shop.de', phone: '+49 30 1234567',
});

// 3) Register – charged immediately to the saved card
let order = await cz('POST', '/domains', { domain, contact_id: contact.id, auto_renew: true });

// 4) Wait for the registry
while (['paid', 'processing'].includes(order.status)) {
  await sleep(30_000);
  order = await cz('GET', `/orders/${order.id}`);
  console.log('status:', order.status);
}
if (order.status !== 'completed') throw new Error('Registration failed – payment refunded');

// 5) DNS record and DNSSEC
await cz('POST', `/domains/${domain}/dns`, { name: 'www', type: 'CNAME', content: `${domain}.`, ttl: 3600 });
await cz('PUT', `/domains/${domain}/dnssec`, { enabled: true });
console.log(`${domain} is ready`);
