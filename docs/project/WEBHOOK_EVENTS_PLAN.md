# Webhook Events Plan

Date: 2026-05-17

This is a report-only implementation plan for adding `webhook_events` safely in a future task. It does not change code, migrations, routes, services, or behavior.

## 1. Purpose

`webhook_events` is needed so inbound channel traffic is captured before normalization or business logic runs.

Why it matters:
- raw payload backup protects against message loss
- failed normalization does not erase the original event
- retries and debugging become possible with a stable event record
- future Viber and Messenger support can reuse the same raw event path
- image and payment screenshot issues can be inspected from the original payload

## 2. Proposed Table

Proposed table: `webhook_events`

Suggested fields:
- `id`
- `channel` (`telegram`, `viber`, `messenger`)
- `event_type` nullable
- `external_event_id` nullable
- `external_user_id` nullable
- `payload` json
- `status` (`received`, `processing`, `processed`, `failed`)
- `processed_at` nullable
- `failed_at` nullable
- `error_message` nullable
- `attempts` integer default `0`
- `created_at`
- `updated_at`

Suggested indexes:
- `channel` + `external_event_id`
- `status`
- `created_at`

Design notes:
- keep the raw payload intact
- treat the table as an immutable event log first
- use `status` to track processing lifecycle
- keep payload size in mind for future image-heavy channels

## 3. Current Telegram Integration Impact

Current `TelegramWebhookController` can later be updated in a small safe sequence:

1. receive the request
2. store the raw payload in `webhook_events`
3. continue existing synchronous processing
4. preserve current F1, F2, and F3 behavior
5. later move processing into a queued job only when needed

Important:
- the first implementation should stay synchronous
- behavior should remain unchanged while the raw event record is added
- locked Telegram flows must continue to pass exactly as they do now

## 4. Future Queue Path

Later, if the project needs async processing, the flow can become:

- `ProcessWebhookEvent` job receives a stored event
- a channel adapter parses the event
- `ConversationService` stores messages and updates conversation state
- an outbound service sends replies
- failed jobs retry through the queue system

Recommendation:
- do **not** add queue processing in the first `webhook_events` implementation unless the task specifically requires it
- keep the first pass synchronous so the MVP stays simple
- add queueing only after the raw event store is stable and proven

## 5. Safe Phased Implementation

### W1 — Add `webhook_events` migration and model only
- Files likely affected:
  - `database/migrations/...create_webhook_events_table.php`
  - `app/Models/WebhookEvent.php`
- Tests to run:
  - `php artisan test`
  - `php artisan smoke:locked`
- Manual test needed:
  - none yet
- Risk level:
  - low

### W2 — Log Telegram raw payload before existing processing
- Files likely affected:
  - `app/Http/Controllers/Webhooks/TelegramWebhookController.php`
  - possibly a small support service for event storage
- Tests to run:
  - `php artisan test`
  - `php artisan smoke:locked`
- Manual test needed:
  - send `hi` through Telegram and confirm existing reply still arrives
- Risk level:
  - low to medium

### W3 — Add smoke coverage for raw event storage
- Files likely affected:
  - `app/Console/Commands/Smoke...`
  - `docs/qa/SMOKE_TESTS.md` if the project keeps smoke notes there
- Tests to run:
  - `php artisan test`
  - new smoke command
  - `php artisan smoke:locked`
- Manual test needed:
  - confirm a real Telegram message still follows the locked path
- Risk level:
  - medium

### W4 — Capture failed status and error details
- Files likely affected:
  - webhook event handling code
  - possibly job or service logic later
- Tests to run:
  - `php artisan test`
  - `php artisan smoke:locked`
- Manual test needed:
  - verify a failed event records failure metadata without breaking the inbox
- Risk level:
  - medium

### W5 — Add queue processing later
- Files likely affected:
  - job classes
  - queue configuration
  - event processing services
- Tests to run:
  - `php artisan test`
  - `php artisan smoke:locked`
  - any queue-specific smoke
- Manual test needed:
  - real Telegram test plus failure/retry confirmation
- Risk level:
  - medium to high

### W6 — Reuse the same table for Viber and Messenger later
- Files likely affected:
  - channel adapters
  - channel-specific webhook controllers or handlers
  - event parsing services
- Tests to run:
  - `php artisan test`
  - `php artisan smoke:locked`
  - channel-specific smoke tests
- Manual test needed:
  - real channel verification per platform
- Risk level:
  - medium

## 6. Smoke Tests Needed

Suggested command:

```bash
php artisan smoke:webhook-events
```

Suggested assertions:
- Telegram `hi` webhook creates a `webhook_events` row
- payload is saved
- event status becomes `processed`
- F1 reply still sends
- unknown text still ends in `Needs Reply`
- `php artisan smoke:locked` still passes

## 7. Risks

Key risks:
- duplicate processing if the same webhook arrives twice
- idempotency should use `external_event_id` when available
- payloads may grow large, especially for images and file messages
- raw payloads can contain sensitive fields, so access and retention need care
- table growth will be fast if every webhook is stored forever
- queue retries can cause duplicate replies if idempotency is weak
- migration naming should stay consistent with the support-domain direction

Mitigations:
- store the raw event first
- make processing idempotent
- keep status transitions simple
- avoid queueing in the first pass unless needed
- do not change locked behavior while introducing the table

## 8. Recommendation

Recommended next implementation task:

**W1 + W2 together, only if kept small:**

> Add `webhook_events` raw logging for Telegram, synchronous only.

Why this is the safest next step:
- low risk
- no queue yet
- preserves existing business logic
- protects future debugging
- gives Viber, Messenger, image, and payment screenshot work a safe starting point

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
