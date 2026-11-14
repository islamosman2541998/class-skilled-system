# Website link — changes needed in the website project

Spec §10. This system's side is built (Stage 1, Task 10). The website project
(`D:\laragon\www\class-skilled`) was **not** edited, by decision of 2026-11-14. These are the exact changes
to make there, kept small as the spec requires: one send action, one `synced_at` column, two endpoints.

## 1. Settings on the website

Add two values to the website's `.env` (or its settings screen):

```
SYSTEM_URL=https://system.class-skilled.com
SYSTEM_KEY=<the key shown once in System → Settings → Website link → Generate new key>
```

The same key works both ways: the website sends it to the system, and the system sends it back when it
fetches missed requests.

## 2. Column on `quote_requests`

```php
Schema::table('quote_requests', function (Blueprint $table) {
    $table->timestamp('synced_at')->nullable()->index();
});
```

## 3. Send each new request after the reply

In the place where the website creates a `QuoteRequest` (the "request a quote" form), after it is saved:

```php
dispatch(fn () => app(\App\Actions\SendQuoteToSystem::class)->handle($quote->id))->afterResponse();
```

`SendQuoteToSystem::handle(int $id)`:

- Loads the `QuoteRequest` with its `service` and its `attachments` media.
- `POST {SYSTEM_URL}/api/v1/website/leads` as `multipart/form-data`, header `X-Api-Key: {SYSTEM_KEY}`,
  timeout 15 seconds, with the fields below and each attachment as `files[]`.
- On HTTP 200 or 201: `forceFill(['synced_at' => now()])->saveQuietly()`.
- On any other result or a connection error: do nothing (the request stays unsynced and is fetched later).
  Never throw to the visitor.

| Field sent | From `QuoteRequest` |
|---|---|
| `external_id` (required) | `number` (for example `QR-0042`) |
| `name` (required) | `name` |
| `phone` (required) | `phone` |
| `email` | `email` |
| `service` | `service->name` (Arabic or English; the system matches it to its services list by name) |
| `facility_type` | `facility_type` |
| `project_state` | `project_state` |
| `emirate` | `emirate` (matched by name) |
| `area_text` | `area_text` |
| `area_m2` | `area_m2` |
| `start_when` | `start_when` |
| `description` | `description` |
| `preferred_contact` | `preferred_contact` |
| `locale` | `locale` (`ar` or `en`) |

Responses from the system: `201 {id, number}` created, `200 {id, number}` already received (safe to
mark synced), `401` wrong key, `422` validation (field errors in `errors`), `429` too many requests.
Sending the same `external_id` again never creates a second lead.

## 4. Endpoints the system calls ("fetch requests" button)

Both are guarded by the same key: compare `hash('sha256', $request->header('X-Api-Key'))` with
`hash('sha256', config('services.system.key'))` using `hash_equals`; answer `401` otherwise.

`GET /api/system/quote-requests/unsynced`

```json
{ "data": [ { "external_id": "QR-0042", "name": "…", "phone": "…", "...": "…",
              "files": ["https://class-skilled.com/…/signed-file-url"] } ] }
```

- At most 50 requests with `synced_at` null, oldest first.
- `files` are temporary signed HTTPS URLs on the website's own host (the system refuses any other host).

`POST /api/system/quote-requests/synced` with `{ "external_ids": ["QR-0042", "…"] }`

- Sets `synced_at = now()` on those requests. Answer `{ "ok": true }`.

## 5. Nothing else changes

The website's own request list and admin screens stay as they are; follow-up happens in the system.
