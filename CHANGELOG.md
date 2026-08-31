# Changelog

All notable changes to the BOSS PHP SDK. Format loosely follows Keep a Changelog.
Compatibility matrix: SDK version → minimum BOSS API version.

## [Unreleased]

### Added
- `customers()` resource. The leads v2 API (both `/crm/leads` and
  `/leads/{id}`) was expanded to accept and filter on `type` instead of
  BOSS building a separate customer API - see the leads/customers handler
  commit on 2026-08-29. `Customers::create()`/`list()` force `type=customer`
  regardless of caller input; `get()`/`update()`/`delete()` require
  `bearer_token` config, same restriction as `Leads`.
- `Leads::convertToCustomer()`.

### Changed
- `POST /crm/leads` now requires `type` explicitly - no server-side default
  on that route as of 2026-08-29 (an external/API caller must say what it
  means; only the internal `/leads` route still defaults to `lead`).
  `Leads::create()` now forces `type=lead` itself, same as
  `Customers::create()` forces `customer`, so this is invisible to an SDK
  consumer - only a direct `call('POST', '/crm/leads', ...)` caller needs
  to pass it.

### Removed
- `Customers::convertToLead()`. Conversion is one-way only - "a customer is
  a customer forever" (user direction, 2026-08-29). The server rejects a
  customer->lead transition with 422 `customer_conversion_not_allowed`, so
  this method would only ever fail.

### Fixed
- The "confirmed hard gap" below no longer applies - kept for history.
- `Leads::create()` and `Customers::create()` now return the created record
  directly with both object and array access, so documented examples like
  `$lead->id` expose the inserted record ID instead of reading as missing.

## [0.1.0] - 2026-08-29 (unreleased, not yet tagged/published)

Initial scaffold. Minimum API: v2 (current).

### Added
- `Client` - config resolution, signed-client HMAC + bearer auth, automatic
  `Idempotency-Key` on writes, retry with backoff on transport errors and
  429s, typed exception mapping, debug logging with credential redaction,
  and the `call()` escape hatch for any route not yet wrapped.
- Resources: `leads()`, `contacts()`, `visitors()`, `errors()`.
- `WebhookHandler` for verifying and dispatching inbound BOSS webhook
  deliveries.
- `MockHttpClient` for consumers to unit-test their own integration without
  hitting real BOSS infrastructure.
- `Import\DbConnectionImporter` - onboarding-import connector tier 1 (project
  43 decision #27): takes a business's own already-live PDO connection
  in-process, introspects columns, samples rows with sensitive columns
  (passwords, card numbers, SSNs, etc.) auto-redacted, and executes an
  already-approved column mapping in batches with per-row failure tracking.
  Field-mapping UI (manual, then AI-assisted) is separate work (tasks
  515/516) built on top of this.

### Known gaps (tracked on BOSS project 43)
- **No `customers()` resource.** As of this release there is no v2 API path
  that can create, read, update, or delete a `type='customer'` leads row —
  `/crm/leads` never writes `type` on create, and `/leads/{id}` hard-filters
  to `type='lead' OR type IS NULL`. Needs a server-side fix before this SDK
  can expose customers as their own concept.
- **`Leads::get()`/`update()`/`delete()` require `bearer_token` config** —
  they call `/leads/{id}`, which only accepts bearer/session auth, not a
  signed-client credential. `create()`/`list()` use `/crm/leads` instead,
  which accepts either.
- **No bulk-import route.** `Leads::bulkImport()` is intentionally not
  implemented rather than pointing at a route that doesn't exist yet.
