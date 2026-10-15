# 07 — Build Plan

Sized in working weeks for **one backend developer**, assuming Phase 0 answers land early. React portals and the Shopify theme run in parallel after Phase 1 (see workstreams in 01). Estimates are rough, so re-baseline after Phase 0.

## Phase 0 — Decisions and feasibility (1 week, starts now)

| Task | Output |
|---|---|
| Resolve D1–D3, D8, D10 and open decisions 1–6 from 01 | Signed-off decision list |
| Spike: Shopify store audit (plan, apps, checkout paths, validation app options) | Written verdict on mandatory selection |
| Spike: Stripe charge ownership, Connect eligibility, payout control (06 §3.1) | Go / no-go for the payout design |
| Create Shopify dev store + custom app, Stripe test account | Credentials in the secret store |
| Agree the API contract in 05 with the React team | OpenAPI draft, mock server |

**Exit:** Stripe spike answered, and the donation base, hold period and fee-bearing rule are written down. D1–D3 are already final (schools withdraw, admin sets the rate, Stripe Connect).

## Phase 1 — Foundation (2 weeks)

- Laravel project, Docker/Sail, CI (Pint, Larastan, Pest), env config, Horizon, Sentry.
- Migrations for identity, schools, rates, content, notifications (04 §2).
- Auth module: register, OTP, login, forgot/reset, logout, `me`, change password, settings toggle.
- Roles, policies, `EnsureRole`, `EnsureSchoolApproved`. Admin seeder and artisan command.
- School profile create, read, update, avatar. Soft-delete rules.
- Admin school management: list, details, approve, reject, deactivate, reactivate. Audit log.
- Fundraising rate versions: list and create with sum-to-100 validation.
- Public API: `/storefront/schools`, `/schools/{id}`, `/donation-settings`, `/content/{slug}`, with caching, CORS and throttling.
- Notification infrastructure (database + mail) and the first types.

**Exit:** the school can sign up → verify → create a profile → be approved in the admin, and then shows up in the public list. The Shopify theme team can build against the public API. Feature tests cover every flow in 03 §3.1–3.2.

## Phase 2 — Orders and donations (2–3 weeks)

- `ShopifyClient`, HMAC middleware, `webhook_inbox`, `ProcessShopifyWebhook` job.
- Order and item upsert, status mapping (D4), quarantine path.
- Donation calculator (pure class, heavy unit tests), allocation + ledger credit in one transaction.
- Refund, cancel and chargeback adjustments.
- Admin Orders list/detail, `assign-school`. School donations list, dashboard cards and revenue chart, CSV export.
- Admin dashboard endpoints.
- Hold-release scheduler.
- Daily Shopify reconciliation command.

**Exit:** a test purchase on the dev store with a selected school shows in the school's Fund collection as Pending and in the admin Orders with the right amount. Replaying the webhook changes nothing. A multi-item cart yields one allocation. Acceptance tests 1–7 from the backend plan pass.

## Phase 3 — Stripe Connect, transfers and withdrawals (3–4 weeks)

- Balance service (ledger-derived), `GET /admin/payouts/balance`.
- Connect onboarding: `POST /school/stripe/onboarding-link`, `GET /school/stripe/status`, `account.updated` handler.
- Transfer engine (06 §3.2): nightly `donations:transfer-payable`, `stripe_transfers` + FIFO `transfer_allocations`, `ExecuteTransfer` job, `transfer_to_connect` ledger entries.
- Admin override `POST /admin/payouts`, `GET /admin/payouts/balance`.
- School wallet and withdrawals: `GET /school/wallet` (fresh Stripe balance), `POST /school/withdrawals` with idempotency and one in-flight limit, `payout.paid` / `payout.failed` handlers.
- Admin payout log and CSV export. School transfer and withdrawal history. Notifications.
- Negative-balance handling after refunds.
- Status sync job and Stripe reconciliation (wallet bucket vs live connected-account balance).

**Exit:** an onboarded test school receives a transfer and allocations flip to Completed (including partial coverage). It withdraws to a test bank account, and `payout.paid` and `payout.failed` both update status correctly. Double-clicking Withdraw is harmless. A concurrent override and nightly transfer cannot overdraw. Acceptance tests 8–11 pass.

## Phase 4 — Support, content, polish (1–1.5 weeks)

- Tickets: create (multipart), list, details, reply, close, attachments (signed URLs), notifications.
- Admin content editing (About, Terms, Privacy), HTML sanitising.
- Notification centre endpoints (read, read-all, dismiss).
- Delete account, email-change OTP.
- API docs published (Scramble), Postman collection.

**Exit:** every screen in 03 §2 has a working endpoint.

## Phase 5 — Hardening and release (1.5 weeks)

- End-to-end tests against the Shopify dev store and Stripe test mode, including the failure matrix in 06 §4.
- Load check on `/storefront/*` (these sit on every product page). Confirm caching and rate limits.
- Security pass: dependency audit, authorization tests that try cross-school access, upload validation, log redaction.
- Backups and restore drill, monitoring and alerts (queue depth, webhook failures, reconciliation mismatches, 5xx).
- Runbooks: webhook outage, payout failure, quarantined order, rate rollback.
- Production cutover: register production webhooks, rotate secrets, smoke test with a real small-value order.

**Exit:** the checklist in §4 below is green.

## Parallel workstreams (not backend-only)

| WS | Starts | Notes |
|---|---|---|
| React admin and school portals | After the Phase 0 contract | Build against a mock server first, switch to staging at the end of Phase 1 |
| Shopify theme (selector, disclosure, cart attribute) | After Phase 1's public API | Follow `shopify_grow_school_selector_plan.md`. Test cart drawer, mobile, express checkout bypass |
| Shopify mandatory-selection enforcement | Phase 0 audit, finishes before release | Possibly blocked on a plan upgrade or a third-party app |

## 3. Testing strategy

| Layer | What | Tools |
|---|---|---|
| Unit | Donation calculator, rounding, ledger math, OTP rules, status mappers | Pest, data providers |
| Feature | Every endpoint: auth, validation, authorization (school A cannot read school B), pagination | Pest + `RefreshDatabase` |
| Integration | Webhook HMAC (valid and invalid), duplicate delivery, out-of-order events, Stripe calls with fakes | `Http::fake()`, recorded fixtures from the dev store |
| Concurrency | Parallel payouts, parallel webhook delivery of the same order | DB-level tests with real transactions |
| E2E | Real dev-store purchase → school sees it → admin pays out | Manual script + checklist before each release |
| Contract | OpenAPI stays in sync with 05 | Scramble diff in CI |

Minimum bar: money paths (calculator, allocation, payout, refunds) at 100% branch coverage.

## 4. Release checklist

- [ ] Schools withdraw themselves, admin sets the rate, Stripe Connect used (D1–D3).
- [ ] Wallet screen balance matches a live Stripe lookup.
- [ ] Replayed webhook creates no duplicate allocation (test + staging proof).
- [ ] Quarantine path tested for: no school, invalid school, deactivated school.
- [ ] Ledger entries are append-only (DB guard tested).
- [ ] Daily reconciliation green for a week on staging.
- [ ] Double-click Withdraw and concurrent transfer/override tests pass.
- [ ] Schools cannot see other schools' data or customer PII.
- [ ] CORS locked to the real store and portal domains.
- [ ] Webhook secrets rotated for production.
- [ ] Mandatory selection: enforcement tested on express checkout, or the limitation is documented and accepted.
- [ ] Runbooks written, alerts routed to a person.

## 5. Top risks

| Risk | Impact | Mitigation |
|---|---|---|
| Shopify-created Stripe charges or platform balance aren't usable for Connect transfers | Payout design fails. Stripe Connect is mandated, so there is no fallback | Phase 0 spike is a hard gate. Escalate to the client immediately if it fails |
| Connect account type can't create API payouts | School can't withdraw on demand | Check in the spike. Wallet shows balance and schedule with Withdraw disabled |
| Mandatory school selection can't be enforced on Grow | Orders without a school | Quarantine + `assign-school`, enforcement app, or Plus |
| Donation base or rate rule changes after launch | Wrong money | Rate versions and base snapshot on every allocation. Calculator isolated and tested |
| Missed or duplicated webhooks | Missing or double donations | Inbox dedupe, unique allocation per order, daily reconciliation |
| Refunds after payout | Negative school balance | Ledger supports negatives, policy decided up front (01 §3 item 5) |
| Legal treatment of charitable funds | Compliance | Confirm in Phase 0 before payouts go live |
| Wallet, Withdraw and Payout-account screens not in Figma | UI work blocked | Brief design now (03 §2.2b) |
| Scope creep from "User" tickets and social login | Delay | D6 and D7 explicitly parked until confirmed |

## 6. Suggested first commits

1. `laravel new` + CI + Pint/Larastan/Pest + Docker.
2. Migrations: `users`, `otp_codes`, `schools`, `fundraising_rate_versions`, `content_pages`, `app_notifications`.
3. `AuthController` + OTP service + tests.
4. `SchoolProfile` actions + admin approve/reject + `/storefront/schools`.
