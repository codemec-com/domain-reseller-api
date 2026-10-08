<?php
/**
 * Cloudzaro Domain Reseller API – register a domain with PHP (ext-curl, PHP 7.4+)
 * Usage: CZ_KEY=cz_live_... php register-domain.php example-shop.de
 */

const API = 'https://www.cloudzaro.com/api/v1';

function cz(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init(API . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . getenv('CZ_KEY'), 'Content-Type: application/json', 'Accept: application/json'],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $data = json_decode((string)curl_exec($ch), true) ?: [];
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400) {
        throw new RuntimeException(($data['error']['code'] ?? 'error') . ': ' . ($data['error']['message'] ?? 'HTTP ' . $status));
    }
    return $data;
}

$domain = $argv[1] ?? 'example-shop.de';

// 1) Availability
$check = cz('GET', '/check?name=' . urlencode($domain));
if ($check['results'][0]['status'] !== 'available') {
    exit("$domain is not available\n");
}

// 2) Holder contact (your end customer)
$contact = cz('POST', '/contacts', [
    'type' => 'person', 'first_name' => 'Erika', 'last_name' => 'Mustermann',
    'street' => 'Hauptstr. 1', 'postal_code' => '10115', 'city' => 'Berlin', 'country' => 'DE',
    'email' => 'erika@example.com', 'phone' => '+49 170 1234567',
]);

// 3) Register – charged immediately to the card saved in your Cloudzaro account
$order = cz('POST', '/domains', ['domain' => $domain, 'contact_id' => $contact['id'], 'auto_renew' => true]);
echo "Order {$order['id']}: {$order['status']}\n";

// 4) Wait until the registry confirms (usually a few minutes)
do {
    sleep(30);
    $order = cz('GET', '/orders/' . $order['id']);
    echo "  status: {$order['status']}\n";
} while (in_array($order['status'], ['paid', 'processing'], true));

if ($order['status'] !== 'completed') {
    exit("Registration failed – the payment has been refunded.\n");
}

// 5) DNS record and DNSSEC
cz('POST', "/domains/$domain/dns", ['name' => '@', 'type' => 'A', 'content' => '203.0.113.10', 'ttl' => 3600]);
cz('PUT', "/domains/$domain/dnssec", ['enabled' => true]);
echo "$domain is ready.\n";
