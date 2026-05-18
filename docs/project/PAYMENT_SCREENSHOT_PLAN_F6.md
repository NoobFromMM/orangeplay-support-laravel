# F6 Payment Screenshot Plan

Date: 2026-05-18

This is a report-only implementation plan for the payment screenshot flow. It does not change code, migrations, routes, services, or locked behavior.

## 1. Purpose

F6 covers the case where a Telegram customer sends a payment screenshot.

Laravel should:
- save the image message
- call the existing Cloudflare Worker `payment_check` service server-side
- create a payment case when payment is detected
- add a payment review card or timeline entry
- ask the customer for email if it is missing
- keep admin review available

The Worker is useful here because it already proved it can detect payment screenshots, return `is_payment=true`, detect providers such as KBZ Pay and AYA Pay, and detect transaction IDs. The earlier failures were caused by orchestration issues, not by the Worker itself.

## 2. Worker Contract

Laravel should treat the Worker as an OCR and payment classification service only.

Suggested environment variables:
- `PAYMENT_CHECK_WORKER_URL` — Cloudflare Worker endpoint
- `AGENT_TOKEN` — auth token (primary, used for Bearer header)
- `PAYMENT_CHECK_WORKER_SECRET` — fallback auth token if AGENT_TOKEN not set

Auth header style:
```
Authorization: Bearer <token>
```

Token resolution order:
1. `AGENT_TOKEN` env (preferred)
2. `PAYMENT_CHECK_WORKER_SECRET` env (fallback)
3. No auth header if neither is set

Suggested request shape:
- server-side only
- send image bytes, or `telegram_file_id` plus proxy bytes depending on current app capability
- include metadata:
  - `platform`
  - `platform_user_id`
  - `message_id`
  - `telegram_file_id`

Expected response fields:
- `ok`
- `is_payment`
- `provider`
- `app`
- `transaction_id` nullable
- `amount` nullable
- `confidence` nullable
- `reason` nullable
- `error` nullable

Important:
- the Worker is not the source of truth
- Laravel owns `payment_cases`, messages, and bot replies
- the Worker should not expose business decisions directly to the dashboard

## 3. Proposed DB Design

Proposed new table:
- `payment_cases`

Suggested fields:
- `id`
- `customer_id`
- `conversation_id`
- `message_id` nullable, or `image_message_id`
- `provider` nullable
- `transaction_id` nullable
- `amount` nullable
- `currency` nullable
- `status` (`pending_review`, `needs_email`, `approved`, `rejected`, `unclear`)
- `customer_email` nullable
- `worker_response` json nullable
- `reviewed_by` nullable
- `reviewed_at` nullable
- `created_at`
- `updated_at`

Timeline usage:
- use the `messages` table for payment review card or system timeline entries
- `message_type` can be `payment_review_card` or `system`
- `sender_type` can be `system`
- `metadata.payment_case_id` should point to the case
- `metadata.provider` and `metadata.transaction_id` can summarize the OCR result

If the current message schema is too simple for the future UI, the implementation phase can propose a minimal extension, but this plan does not implement that.

## 4. Flow Design

### A. Customer sends image
- `webhook_events` row is saved first
- image message is saved
- conversation status starts as `needs_reply`

### B. Payment check
- Laravel downloads the image bytes through Telegram file API or existing proxy logic
- Laravel calls the Worker server-side
- if the Worker returns `is_payment=true`:
  - create `payment_cases`
  - create a payment review card timeline message
  - if customer email is missing:
    - send a bot reply asking for email
    - save the bot reply message
    - set payment case status to `needs_email`
  - otherwise set status to `pending_review`
- if the Worker returns `is_payment=false`:
  - keep the image as a normal `Needs Reply` case
  - do not send a bot reply
- if the Worker errors:
  - keep the image as `Needs Reply`
  - store the error safely
  - do not lose the message

## 5. Edge Cases

Plan for these cases:
- `transaction_id` missing but `is_payment=true`
- duplicate screenshot or duplicate `transaction_id`
- same image resent
- Worker timeout
- Worker false negative
- non-payment image
- email arrives after a payment case is already open
- multiple open payment cases
- customer sends a payment screenshot before asking for payment info

Suggested handling direction:
- prefer idempotency by `transaction_id` when available
- fall back to image hash or event identity if needed later
- keep the first implementation simple and deterministic

## 6. Recommended Implementation Phases

### P1 — DB + model + Worker client service, no webhook behavior change
- Goal: prepare schema and service boundary without changing customer-facing behavior
- Files likely affected:
  - `database/migrations/...create_payment_cases_table.php`
  - `app/Models/PaymentCase.php`
  - `app/Services/Payments/PaymentCheckClient.php` or similar
- Tests:
  - `php artisan test`
  - `php artisan smoke:locked`
- Manual test:
  - none yet
- Risk level:
  - low

### P2 — Smoke test Worker fake response, create payment case from image
- Goal: prove the payment case path can be exercised with a fake Worker response
- Files likely affected:
  - smoke command
  - payment client fake/stub support
- Tests:
  - `php artisan test`
  - new payment smoke
  - `php artisan smoke:locked`
- Manual test:
  - confirm the new smoke mirrors the real scenario closely enough
- Risk level:
  - low to medium

### P3 — Integrate Telegram image webhook to payment check synchronously
- Goal: call the Worker when an image arrives, while keeping the flow synchronous
- Files likely affected:
  - Telegram webhook handling
  - image storage helpers
  - payment case service
- Tests:
  - `php artisan test`
  - payment smoke
  - `php artisan smoke:locked`
- Manual test:
  - real Telegram payment screenshot
- Risk level:
  - medium

### P4 — Bot asks email when payment case needs email
- Goal: collect missing email after a payment screenshot is detected
- Files likely affected:
  - Telegram bot reply path
  - payment case follow-up logic
- Tests:
  - `php artisan test`
  - payment-email smoke
  - `php artisan smoke:locked`
- Manual test:
  - send image, then confirm email prompt appears
- Risk level:
  - medium

### P5 — Email follow-up attaches to open payment case
- Goal: let an email reply resolve the open payment case
- Files likely affected:
  - message handling
  - payment case lookup/service
- Tests:
  - `php artisan test`
  - payment-email smoke
  - `php artisan smoke:locked`
- Manual test:
  - confirm email attaches to the open case
- Risk level:
  - medium

### P6 — Dashboard payment card UI and review actions
- Goal: show the payment case in the dashboard and support review actions
- Files likely affected:
  - dashboard views
  - dashboard controller
  - payment case read/update paths
- Tests:
  - `php artisan test`
  - `php artisan smoke:locked`
- Manual test:
  - admin reviews a payment case in the dashboard
- Risk level:
  - medium

### P7 — Later queue/retry
- Goal: move payment OCR and follow-up into queued jobs once the synchronous path is stable
- Files likely affected:
  - jobs
  - queue config
  - retry logic
- Tests:
  - `php artisan test`
  - `php artisan smoke:locked`
  - queue-specific smoke
- Manual test:
  - real payment screenshot plus retry behavior
- Risk level:
  - medium to high

## 7. Smoke Tests

Suggested commands:
- `php artisan smoke:payment-worker`
- `php artisan smoke:payment-screenshot`
- later `php artisan smoke:payment-email`

Suggested assertions:
- `is_payment=true` plus `transaction_id` creates a `payment_case`
- `is_payment=true` with no `transaction_id` still creates a case, but leaves it pending review or unclear
- `is_payment=false` creates no payment case and leaves the image as `Needs Reply`
- Worker error does not lose the message
- email after an open payment case attaches to the case

## 8. Security

Security rules for the future implementation:
- Worker secret lives in `.env` only
- do not expose Worker URL or secret in frontend code
- do not print image bytes or secrets
- validate file type and size before sending to the Worker
- avoid arbitrary URL fetches
- rate limiting can come later
- audit `worker_response` carefully before showing it in the UI

## 9. Recommendation

Recommended next implementation task:

**P1 — Add `payment_cases` migration/model and a `PaymentCheckClient` service interface with fakeable tests, but do not connect the webhook yet.**

Why this is the safest next step:
- it isolates schema and client concerns
- it does not change customer-facing behavior
- it is easier to smoke test
- it protects the locked Telegram flows
- it prepares the path for a later synchronous image flow without bringing back n8n

## Locked Gate Reminder

Before any future commit in this repo, run:

```bash
php artisan test
php artisan smoke:f1
php artisan smoke:f2
php artisan smoke:f3
php artisan smoke:locked
```

Do not lock a new feature until manual verification is complete.
