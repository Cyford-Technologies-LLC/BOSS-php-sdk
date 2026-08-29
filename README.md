# BOSS PHP SDK

Composer package wrapping the ZeroAI BOSS v2 API for any PHP application — a
custom site integration, a future Shopify/Magento extension, or the
WordPress plugin once it's generalized. Companion to the
[JS SDK](https://github.com/Cyford-Technologies-LLC/BOSS-js-sdk).

## Install

Public repo — `composer install` just works, no credential needed:

```json
{
  "repositories": [
    {"type": "vcs", "url": "https://github.com/Cyford-Technologies-LLC/BOSS-php-sdk"}
  ],
  "require": {
    "zeroai/boss-php-sdk": "^0.1"
  }
}
```

```
composer require zeroai/boss-php-sdk
```

Composer resolves `^0.1` against this repo's git tags. A new tagged release
here is picked up by any consumer's next `composer update` — nothing to
re-copy or re-integrate.

Public deliberately, not by default: the package has no secrets in it
(`client_id`/`client_secret` are supplied at runtime by whoever installs
it, generated per-company through BOSS's own admin UI), and access to the
actual API is already gated server-side by that credential, not by
possession of this source. Seeing the HMAC signing algorithm doesn't let
anyone forge a request without the secret key - same reasoning every major
platform's public SDK (Stripe, Twilio, AWS) already relies on.

## Status (2026-08-29)

Built and tested (31 assertions across `tests/smoke.php` and
`tests/smoke_import.php`): `Client` (auth, idempotency, retries, typed
exceptions), `WebhookHandler`, `DbConnectionImporter` (onboarding import),
and the Tier 1 resources - `leads()`, `customers()`, `contacts()`,
`visitors()`, `errors()`. First real installer: Cyford Technologies LLC.

Not built yet: Sales/Bookings/Payments/Marketing/AI resources (the
underlying v2 routes exist for some of these already - see the main repo's
`www/dev-clients/ENDPOINTS.md` for the full catalog and what's wrapped vs.
not), bulk-import API route (single-record create only today).

## Quick start

```php
$boss = new \ZeroAI\Boss\Sdk\Client([
    'client_id' => 'YOUR_CLIENT_ID',
    'client_secret' => 'YOUR_CLIENT_SECRET',
]);

$lead = $boss->leads()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
$customer = $boss->customers()->create(['name' => 'Acme Inc', 'email' => 'billing@acme.com']);
$boss->visitors()->trackEvent('page_view', ['page_url' => 'https://example.com/pricing']);
```

## Config

- `client_id` / `client_secret` — the signed-client credential (or use
  `bearer_token` for server-to-server calls instead)
- `environment` — `production` (default, resolves to `https://zeroaiboss.com/api/v2`)
  or `sandbox` (requires `base_url` — there's no single fixed sandbox host)
- `base_url` override — for on-prem/self-hosted edge cases
- `company_id` — default scoping merged into every request for multi-company
  accounts (client-side convenience only - see the note in `src/Client.php`
  about signed-client credentials not being company-scoped server-side yet)
- `timeout_ms`, `retry_policy` (`max_attempts`, `base_delay_ms`)
- `webhook_secret` — verifies inbound webhook signatures, never sent on
  outbound requests
- `logger` — accepts a PSR-3 logger
- `http_client` — swap in your own transport (see `src/Http/HttpClientInterface.php`)
  instead of the built-in curl client
- `debug` — verbose request/response logging; credentials are always
  redacted even in debug output

## Auth

- Bearer token for server-to-server calls (matches the `api_tokens` model)
- HMAC request signing for the signed-client flow (matches `api_v2_clients`)
  — the SDK computes the signature, callers never touch raw secrets directly

Note: `Leads`/`Customers` split routes across both auth types - see each
class's docblock for exactly which methods need which. This is real,
current API behavior, not a simplification.

## Resources (built)

- `leads()` — create, list, get, update, delete, `convertToCustomer()`
- `customers()` — create, list, get, update, delete (one-way from leads
  only — a customer can never convert back to a lead)
- `contacts()` — create, list, get, update
- `visitors()` — trackVisitor, trackEvent, identify, bindToLead
- `errors()` — report, list, get
- `->call($method, $path, $query, $body)` — escape hatch for any route not
  yet wrapped

## Onboarding import

`ZeroAI\Boss\Sdk\Import\DbConnectionImporter` — pass your own already-live
PDO connection (never transmits credentials), it introspects columns,
samples rows with sensitive columns (passwords, card numbers, SSNs) auto-
redacted, and executes an already-approved column mapping in batches. See
its class docblock for the full contract. Manual + AI-assisted mapping UI
is separate, not-yet-built work.

## Callbacks

- Inbound webhooks: `WebhookHandler` — verifies signature, parses payload,
  dispatches to named event listeners instead of making callers parse raw JSON

## Error handling

- Typed exceptions: `AuthException`, `RateLimitException`,
  `ValidationException`, `ApiException` (generic, carries the API's own
  error code/message/request_id)
- `RateLimitException` carries `retryAfterSeconds()`

## Idempotency

- Automatic `Idempotency-Key` on every write, with an override via
  `$options['idempotency_key']` on `call()`

## Testing

```
composer install
php tests/smoke.php
php tests/smoke_import.php
```

`Http\MockHttpClient` lets you unit-test your own integration without
hitting real BOSS infrastructure.

## Distribution

This repo *is* the distribution mechanism (git VCS + tags, public repo) -
see Install above. No private Packagist/Satis server or per-company
credential needed.

## Versioning / compatibility

- SDK follows its own semver via git tags, independent of the API's version
- Breaking changes bump the major/minor tag; deprecated fields/methods stay
  supported for a defined window with a deprecation warning before removal
