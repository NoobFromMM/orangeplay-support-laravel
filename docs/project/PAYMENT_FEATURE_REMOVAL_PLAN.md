# Payment Feature Removal Plan

## 1. New simplified support flow

Final target flow:

- text message:
  - save raw webhook
  - save inbound message
  - FAQ match → bot reply + Resolved
  - no FAQ match → Needs Reply

- image/file message:
  - save raw webhook
  - save inbound image/file metadata
  - keep image preview/proxy only if it remains useful for support review
  - no OCR
  - no payment check
  - no payment bot reply
  - status = Needs Reply

- admin reply:
  - unchanged
  - dashboard reply still works the same way

This removes chat-based payment handling entirely and leaves the bot focused on support information only.

## 2. Payment features to remove

Inventory of payment-specific code and docs that should be removed or archived:

- `payment_cases` table and related migrations
- `payment_cases` model
- `PaymentCheckClient`
- `PaymentCaseService`
- `PaymentScreenshotService`
- `PaymentCaseResolutionService`
- `DuplicatePaymentDetector`
- payment smoke commands
- `PaymentCaseController` and any payment routes
- payment review card Blade UI
- payment duplicate notice handling
- payment check metadata writing
- payment-related environment variable docs:
  - `PAYMENT_CHECK_WORKER_URL`
  - `PAYMENT_CHECK_WORKER_SECRET`
  - `AGENT_TOKEN`
  - `GEMINI_KEY`
  - any payment worker fallback variables
- payment docs, plans, lock notes, and payment-specific smoke expectations

If the repo still contains payment-specific timeline or badge rendering, that should also be removed once the payment tables and services are gone.

## 3. What to keep

Keep:
- Telegram webhook raw logging
- DB FAQ auto reply
- FAQ Admin
- image preview/proxy if it still helps support review
- image → Needs Reply behavior
- admin reply from dashboard
- dashboard status badges
- smoke tests for the non-payment locked features

Decision on customer email storage:
- recommend removing payment-specific email attach logic entirely
- keep `customer_emails` only if the team later decides it is a general support profile feature
- for this removal plan, the safer default is to remove it unless a non-payment use case is already planned

The bot should become a clean support information bot, not a payment workflow bot.

## 4. Migration / database strategy

Because the project already has committed migrations, use a safe cleanup strategy rather than rewriting history unless the team explicitly wants a repo reset.

Recommended options:

### Option A: additive cleanup migration
- create a new migration that drops payment-related tables if they exist
- drop `payment_cases`
- drop `customer_emails` if it is payment-only
- remove foreign keys or references before dropping
- leave old migrations untouched

### Option B: clean rewrite for an unreleased local-only repo
- remove obsolete payment migrations and reset the database with `migrate:fresh`
- this is only safe if the project is still fully local and not shared as a deployed migration history

Recommended approach for safety:
- prefer Option A for a shared or semi-shared repo
- prefer Option B only if the team confirms the app is not relying on deployed migration history

## 5. Test / smoke strategy

Payment-specific smoke commands should be removed from the locked baseline or archived as deprecated:

- `smoke:payment-foundation`
- `smoke:payment-screenshot`
- `smoke:payment-webhook`
- `smoke:payment-email-attach`
- `smoke:payment-resolution`
- `smoke:payment-duplicate`
- any `smoke:customer-emails` or payment-policy smoke if present

Update the locked smoke coverage so it reflects the simplified support bot baseline only.

Likely remaining locked baseline after removal:
- F1 greeting
- F2 FAQ auto reply
- F3 admin reply
- W1/W2 webhook logging
- F5 image receive + preview
- F5A image + admin reply regression
- FAQ Admin

Update `smoke:locked` expectations accordingly and remove payment-related smoke references from docs.

## 6. Implementation sequence

### R1 — removal plan docs only
- define the simplified support flow
- inventory payment features to remove
- decide what stays

### R2 — stop payment logic in webhook
- remove payment OCR/check invocation from the Telegram image path
- make image messages always fall back to Needs Reply
- keep image preview if useful
- remove payment smoke references from locked coverage

### R3 — remove payment dashboard UI and services
- remove payment review cards
- remove approve/reject payment routes and controllers
- remove payment duplicate notice handling
- remove payment email attach behavior

### R4 — remove payment tables / models / migration strategy
- drop payment-related tables safely
- remove unused payment models and support services
- clean up any remaining references

### R5 — manual Telegram evidence
- verify text FAQ reply still works
- verify images still save and stay Needs Reply
- verify admin reply still works

### R6 — lock the simplified baseline
- update lock docs
- update smoke coverage
- confirm the simplified bot is stable

## 7. Risks

Main risks:
- losing a fallback payment path if the team later decides chat payments should return
- stale payment docs or smoke commands confusing future work
- database cleanup leaving behind foreign key or view references
- unused env vars and helper classes staying in the codebase too long
- old payment messages already in the DB still rendering in dashboard views
- dashboard code may still reference payment card fields after the services are removed

Mitigation:
- remove the runtime path first, then clean up services and UI
- keep non-payment support flows stable while the payment code is removed
- use additive cleanup migration if there is any uncertainty about deployment history
- remove payment references from smoke docs and lock docs at the same time

## 8. Recommendation

Recommended next implementation task:

**R2 — stop payment logic in the Telegram webhook and make image messages always fall back to Needs Reply, while leaving table deletion and dashboard cleanup for later.**

Why:
- it immediately matches the new product direction
- it removes the user-visible payment behavior first
- it reduces the chance that a customer sees payment-specific replies after the policy change
- it lets the team clean up services, UI, and tables in later smaller steps

If the team wants the cleanest possible finish, follow with R3 and R4 in separate small tasks.

## Locked gate reminder
Before any future commit in this repo, run:

```bash
php artisan test
php artisan smoke:locked
```

Then update the locked smoke coverage to reflect the simplified support bot baseline only.
