# Signed Remote Order URLs

## Overview
When third-party applications (like QuizWitz) create remote order URLs with query parameters
such as `card` (card alias) or `name` (requester name), these parameters must be signed to
prevent tampering. This ensures that only authorized integrators can create valid order URLs
that charge specific cards or assign orders to specific users.

## Token Structure

The order token is split into two parts separated by a dash (`-`):

```
{public_token}-{secret}
```

- **Public token** (`order_token`): Used in URLs and for event lookup. Visible to end users.
- **Secret** (`order_token_secret`): Used for signing parameters. Only shared with integrators.

### Example
Full token (shown to admins/integrators): `ABC123def456GHI789jkl012MNO345pq-rstUVW789xyz012ABC345def678GHI901`

- Public part: `ABC123def456GHI789jkl012MNO345pq` (in the URL)
- Secret part: `rstUVW789xyz012ABC345def678GHI901` (for signing)

## Signature Algorithm

### Signable Parameters
Only these query parameters are included in the signature:
- `card` — Card alias for payment
- `name` — Requester name

### How to Calculate the Signature

1. **Collect** only the signable parameters that are present (skip empty/null values)
2. **Sort** parameters alphabetically by key name
3. **URL-encode** both keys and values using RFC 3986 percent-encoding (`rawurlencode` in PHP)
4. **Build** a query string: `key1=value1&key2=value2`
5. **Compute** HMAC-SHA256 using the secret as the key
6. **Encode** the result as lowercase hexadecimal (64 characters)

> **Important:** Values must be URL-encoded (RFC 3986) before building the message string.
> This prevents ambiguity when values contain special characters like `&` or `=`.
> For simple alphanumeric values, URL encoding has no effect.

### Examples

#### Card only
```
Secret: "mysecret"
Parameters: card=player1
Message to sign: "card=player1"
Signature: HMAC-SHA256("mysecret", "card=player1") → hex string
```

#### Card and name
```
Secret: "mysecret"
Parameters: card=player1, name=Alice
Message to sign: "card=player1&name=Alice"  (alphabetically sorted)
Signature: HMAC-SHA256("mysecret", "card=player1&name=Alice") → hex string
```

#### Name only
```
Secret: "mysecret"
Parameters: name=Bob
Message to sign: "name=Bob"
Signature: HMAC-SHA256("mysecret", "name=Bob") → hex string
```

### Signature Generation Examples in Various Languages

#### PHP
```php
$secret = 'your-secret-here';
$params = ['card' => 'player1', 'name' => 'Alice'];
ksort($params);
$parts = [];
foreach ($params as $k => $v) {
    $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
}
$message = implode('&', $parts); // "card=player1&name=Alice"
$signature = hash_hmac('sha256', $message, $secret);
```

#### Python
```python
import hmac, hashlib, urllib.parse

secret = 'your-secret-here'
params = {'card': 'player1', 'name': 'Alice'}
sorted_params = sorted(params.items())
message = '&'.join(f'{urllib.parse.quote(k, safe="")}={urllib.parse.quote(v, safe="")}' for k, v in sorted_params)
signature = hmac.new(secret.encode(), message.encode(), hashlib.sha256).hexdigest()
```

#### JavaScript / Node.js
```javascript
const crypto = require('crypto');

const secret = 'your-secret-here';
const params = { card: 'player1', name: 'Alice' };
const sorted = Object.keys(params).sort();
const message = sorted.map(k => `${encodeURIComponent(k)}=${encodeURIComponent(params[k])}`).join('&');
const signature = crypto.createHmac('sha256', secret).update(message).digest('hex');
```

## URL Format

### Without signed parameters (unchanged)
```
https://drinks.catlab.eu/order/{public_token}
```

### With signed parameters
```
https://drinks.catlab.eu/order/{public_token}?card=player1&name=Alice&signature={hex_signature}
```

## Validation Flow

### Page Load (`GET /order/{token}?...`)
1. Server looks up event by `order_token` (public part)
2. If event has `order_token_secret` AND signable params are present:
   - Extract `card` and `name` from query params
   - Validate `signature` query param using HMAC-SHA256
   - Reject with 403 if signature is missing or invalid
3. If event has no secret (legacy): allow params without signature
4. Render Vue app with validated parameters passed as JS variables

### API Calls (`GET/POST /api/v1/public/*`)
1. Middleware checks `X-Event-Token` header for event lookup
2. If event has `order_token_secret` AND `X-Card-Token` or `X-Order-Name` headers are present:
   - Build params map from `X-Card-Token` → `card`, `X-Order-Name` → `name`
   - Validate `X-Signature` header
   - Reject with 403 if signature is missing or invalid
3. If event has no secret (legacy): allow headers without signature

### HTTP Headers Used by the Client App
| Header | Purpose |
|--------|---------|
| `X-Event-Token` | Public order token for event lookup |
| `X-Card-Token` | Card alias for payment (corresponds to `card` query param) |
| `X-Order-Name` | Requester name (corresponds to `name` query param) |
| `X-Signature` | HMAC-SHA256 signature of the signed parameters |

## Database Changes

### Migration: `2026_03_06_070000_add_order_token_secret_to_events`
- Adds `order_token_secret` column (varchar 32, nullable) to `events` table
- Generates secrets for all existing events

## Backward Compatibility

- **Legacy events** (without `order_token_secret`): No signature validation is performed.
  Query parameters like `card` work as before without any signature.
- **New events**: Always have both `order_token` and `order_token_secret`. When signed
  parameters are present, signature validation is enforced.
- **Existing integrations**: If an integration previously used unsigned `?card=` URLs,
  it will continue to work with legacy events. For new events, the integrator must
  use the full token (with secret) to generate signatures.

## Key Files

| File | Purpose |
|------|---------|
| `app/Services/OrderTokenSignatureService.php` | Signing and verification logic |
| `app/Http/Controllers/OrderController.php` | Web page signature validation |
| `app/Http/Middleware/PublicEventApiAuthentication.php` | API signature validation |
| `app/Models/Event.php` | `getFullOrderToken()`, `getOrderTokenSecret()` methods |
| `resources/views/order/index.blade.php` | Passes signature data to Vue app |
| `resources/clients/js/bootstrap.js` | Sets signature headers for API calls |
| `tests/Unit/OrderTokenSignatureTest.php` | Unit tests for signing logic |
| `tests/Feature/SignedOrderUrlTest.php` | Feature tests for full flow |
