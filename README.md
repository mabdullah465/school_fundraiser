# School Fundraiser: Backend (Laravel)

API-only Laravel app. **Current scope: only what Shopify talks to**, running on dummy data. There is no sign up, login or portal API yet. Those come later (see [docs/](docs/README.md)).

| What | Endpoint | Caller |
|---|---|---|
| Approved schools (search, paginated) | `GET /api/v1/storefront/schools?search=&page=&per_page=` | Shopify theme (browser) |
| One school, re-validation | `GET /api/v1/storefront/schools/{id}` | Shopify theme |
| Donation rate and disclaimer | `GET /api/v1/storefront/donation-settings` | Shopify theme |
| Order / refund / fulfilment events | `POST /api/v1/webhooks/shopify` (HMAC verified) | Shopify |

Plans: [shopify_grow_school_selector_plan.md](shopify_grow_school_selector_plan.md) (theme side), [backend_school_donation_plan.md](backend_school_donation_plan.md) (this app).

## Run it

Needs PHP 8.3+, Composer, and MySQL (or Postgres).

```bash
composer install
cp .env.example .env && php artisan key:generate   # skip the cp if .env exists
# create an empty database called school_fundraiser, set DB_USERNAME / DB_PASSWORD in .env
php artisan migrate:fresh --seed
php artisan serve                                   # http://localhost:8000
```

`QUEUE_CONNECTION=sync` in `.env.example` processes webhooks inside the request, so you don't need a worker locally. In production use `database` or `redis` and run `php artisan queue:work --queue=webhooks,default`.

### Dummy data (`php artisan migrate:fresh --seed`)

- 31 schools. Ids are fixed (`01JB0000000000000000000001` is Lincoln High School), so the theme can hard-code a test id. 24 are approved and 7 are not, to prove the filter works: Figma pending/rejected rows, plus one deactivated school.
- Rate history `donation-v1` 50%, `v2` 40%, `v3` **30% active** (the Figma "Fundraising Percentage" table).
- 6 demo orders pushed through the real ingestion code: single item, a 3-item order (still one allocation), a discounted order, and 2 that are **quarantined** (no school, unapproved school).

```bash
php artisan donations:report      # allocations, ledger balance per school, quarantined orders
```

## Test the Shopify side

### 1. Without a Shopify store (signed fake webhooks)

```bash
php artisan shopify:simulate orders/paid --school=01JB0000000000000000000002 --items=3
php artisan shopify:simulate orders/paid --school=none            # quarantined, nobody credited
php artisan shopify:simulate orders/paid --order=5000000000001    # run twice: replay creates nothing new
php artisan shopify:simulate orders/cancelled --order=5000000000001
php artisan donations:report
```

The command signs the body with `SHOPIFY_WEBHOOK_SECRET` exactly as Shopify does, so it exercises the HMAC check, the inbox, the queue job and the ledger. It POSTs to `APP_URL`, so the app must be running.

### 2. Theme against the public API

```bash
curl "http://localhost:8000/api/v1/storefront/schools?search=lin"
curl  http://localhost:8000/api/v1/storefront/schools/01JB0000000000000000000001
curl  http://localhost:8000/api/v1/storefront/donation-settings
```

Add the theme/store origin to `CORS_ALLOWED_ORIGINS` in `.env`. The theme saves the chosen id as the cart attribute `school_id`:

```js
fetch(window.Shopify.routes.root + 'cart/update.js', {
  method: 'POST', headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ attributes: { school_id: school.id, school_name: school.name } })
});
```

### 3. A real Shopify dev store

1. Create a **custom app** in the store admin. Grant `read_orders` (and `read_all_orders` for old orders). Copy the Admin API token and the **API secret key**.
2. Put them in `.env`: `SHOPIFY_STORE_DOMAIN`, `SHOPIFY_ADMIN_TOKEN`, `SHOPIFY_WEBHOOK_SECRET`.
3. Expose your machine over HTTPS (Shopify only delivers to HTTPS): `ngrok http 8000` or `cloudflared tunnel --url http://localhost:8000`.
4. Register the webhooks (not yet run against a live store, so check Shopify admin → Settings → Notifications → Webhooks after):
   ```bash
   php artisan shopify:register-webhooks --url=https://your-tunnel.example
   ```
5. Place a test order with the `school_id` cart attribute set, then run `php artisan donations:report`.

## How a paid order becomes a donation

```text
POST /webhooks/shopify
  -> VerifyShopifyHmac            raw body, hash_equals, 401 on mismatch
  -> webhook_inbox                unique (provider, event_id): duplicates are no-ops
  -> ProcessShopifyWebhook (job)  retries with backoff, failed events are retried on redelivery
  -> IngestShopifyOrder           upsert order + items (safe in any order)
  -> AllocateDonation             only when paid; one allocation per order (unique index)
       school = note_attributes[school_id], must exist and be approved
       base   = sum of (price x qty - discounts) over eligible items, no shipping / tax
       amount = round half up (base x active school_bps / 10000)
  -> donation_ledger_entries      append-only credit; refunds / cancels are new negative rows
```

Paid order with no valid approved school (or no active rate): **quarantined**, never credited, logged. The client never supplies the percentage. It is read from the server at payment time and snapshotted on the allocation.

## Tests

```bash
vendor/bin/phpunit
```

63 tests: money maths, donation calculator, public API (visibility, search, pagination, CORS, caching), and the webhook pipeline (bad/missing signature, replay, out-of-order, quarantine, refunds, cancellation, ledger immutability, retry after failure). Tests use in-memory SQLite. If your PHP has `pdo_sqlite` disabled, run `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`.

## Layout

```text
app/
  Domain/Donations/     DonationCalculator, AllocateDonation, AdjustDonationForRefund, CancelDonation
  Domain/Orders/        IngestShopifyOrder
  Integrations/Shopify/ Handlers/ (one per topic), Support/ (payload parsing), ShopifyClient, Fixtures/
  Http/Controllers/     Api/V1/Storefront/*, Webhooks/ShopifyWebhookController
  Http/Middleware/      VerifyShopifyHmac
  Jobs/                 ProcessShopifyWebhook
  Models/ Enums/ Support/Money.php
config/shopify.php      topic -> handler map, attribute name, credentials
config/donations.php    hold days, excluded products, calculation basis
routes/api.php          mounted at /api/v1
```

Add a webhook topic: write a handler implementing `ShopifyWebhookHandler` and add it to `config/shopify.php`.

## Not built yet (on purpose)

Auth and sign up, school and admin portal APIs, notifications, support tickets, Stripe Connect transfers and withdrawals. The tables and rules they need are designed in [docs/04-data-model.md](docs/04-data-model.md) and [docs/05-api-reference.md](docs/05-api-reference.md). The schema already holds what the Shopify side needs to hand over to them (`schools.user_id` is a nullable column waiting for auth, and allocations carry the `payable_at` that the transfer job will use).
