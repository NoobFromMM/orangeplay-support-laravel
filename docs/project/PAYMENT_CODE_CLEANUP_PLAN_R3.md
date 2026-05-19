# R3 Payment Code Cleanup Plan

Date: 2026-05-19
Status: REPORT ONLY — no code changed

---

## 1. Current Simplified Runtime

After R2 (`bd072da`), the Telegram webhook supports only:

| Input | Behavior |
|-------|----------|
| FAQ-matching text | bot auto-reply + status=resolved |
| Unknown text | status=Needs Reply |
| Image/file | saved with preview + status=Needs Reply |
| Admin reply | unchanged (in_chat) |

No payment Worker call, no payment_case creation, no email ask/attach, no duplicate detection.

---

## 2. Payment Code Inventory to Remove

### Services — DELETE (5 files)
- `app/Services/Payments/PaymentCheckClient.php`
- `app/Services/Payments/PaymentCaseService.php`
- `app/Services/Payments/PaymentScreenshotService.php`
- `app/Services/Payments/PaymentCaseResolutionService.php`
- `app/Services/Payments/DuplicatePaymentDetector.php`
- `app/Services/Customers/CustomerEmailCaptureService.php`

### Models — DELETE (2 files)
- `app/Models/PaymentCase.php`
- `app/Models/CustomerEmail.php`

### Controllers — DELETE (1 file)
- `app/Http/Controllers/PaymentCaseController.php`

### Smoke Commands — DELETE (7 files)
- `app/Console/Commands/SmokePaymentFoundation.php`
- `app/Console/Commands/SmokePaymentScreenshot.php`
- `app/Console/Commands/SmokePaymentWebhook.php`
- `app/Console/Commands/SmokePaymentEmailAttach.php`
- `app/Console/Commands/SmokePaymentResolution.php`
- `app/Console/Commands/SmokePaymentDuplicate.php`
- `app/Console/Commands/SmokeCustomerEmails.php`

### Migrations — DELETE (2 files)
- `database/migrations/2026_05_17_000006_create_payment_cases_table.php`
- `database/migrations/2026_05_19_000001_create_customer_emails_table.php`

### Seeder — DELETE (1 file)
- `app/Console/Commands/SeedDashboardPreview.php`

### Plan Docs — DELETE (4 files)
- `docs/project/PAYMENT_SCREENSHOT_PLAN_F6.md`
- `docs/project/PAYMENT_CASE_RESOLUTION_PLAN_F6_P6.md`
- `docs/project/PAYMENT_POLICY_REVISION_PLAN.md`
- `docs/project/PAYMENT_FEATURE_REMOVAL_PLAN.md`

### Env references — EDIT .env
- Remove: `PAYMENT_CHECK_WORKER_URL`, `AGENT_TOKEN`, `GEMINI_KEY`

### Files to EDIT (keep, remove payment sections) — 7 files
- `routes/web.php` — remove `PaymentCaseController` import + approve/reject routes (3 lines)
- `app/Http/Controllers/DashboardController.php` — remove `PaymentCase` import + `$paymentCases` query (3 lines)
- `resources/views/dashboard/conversation.blade.php` — remove `.payment-card` CSS + `$isPaymentReview` block + approve/reject forms (~80 lines)
- `app/Models/Customer.php` — remove `customerEmails()` relation (4 lines)
- `docs/project/CURRENT_STATE.md` — remove payment features from lists
- `docs/qa/FEATURE_LOCKS.md` — remove payment features + deprecated section
- `docs/qa/SMOKE_TESTS.md` — remove payment smoke documentation
- `database/seeders/FaqSeeder.php` — optionally rename `payment_account` entry (keep FAQ, may rename category)

**Total: ~23 DELETE + 7 EDIT = 30 files affected.**

---

## 3. What to Keep

| Category | Files to keep |
|----------|---------------|
| Image preview | `TelegramFileController.php`, `GET /telegram/file/{fileId}` route |
| Webhook events | `WebhookEvent.php`, `webhook_events` table, `smoke:webhook-events` |
| FAQ Admin | `FaqEntryController.php`, FAQ routes, FAQ views, `smoke:faq-admin` |
| Telegram image smoke | `SmokeTelegramImage.php`, `smoke:telegram-image` |
| Image + admin reply smoke | `SmokeImageAdminReply.php`, `smoke:image-admin-reply` |
| Conversation timeline | Blade for text/image/system messages (keep) |
| General models | `Customer.php` (minus customerEmails relation), `Conversation.php`, `Message.php` |
| General services | `ConversationService.php`, `FaqMatcher.php`, `TelegramBotService.php`, `TelegramUpdateNormalizer.php` |
| Locked smokes | `SmokeF1.php`, `SmokeF2.php`, `SmokeF3.php` |
| Historical docs | Archive `docs/project/` payment plans as reference, not deleted unless user explicitly wants clean docs |

---

## 4. Migration/Database Strategy

**Recommendation: Option A — Leave old tables for now.**

Reasoning:
- Local DB already has `payment_cases` and `customer_emails` data. Dropping tables via migration would require `migrate:fresh` or a custom down migration, which could affect other tables.
- Old `payment_review_card` and `payment_status_update` messages exist in the `messages` table. If Blade renders these as generic system messages after UI cleanup, no errors occur.
- Removing migration files but not dropping tables means the DB still has the tables but no code references them. This is safe for local dev.
- If a fresh setup is needed, a new `migrate:fresh` without the deleted migration files simply won't create the payment tables.

**Alternative: Add a cleanup migration to drop the tables.** Only if the user wants them physically removed from the local DB. Not recommended in R3 — defer to R4.

---

## 5. Dashboard Old Data Strategy

Old payment messages (`payment_review_card`, `payment_status_update`, `payment_duplicate_notice`) still exist in the `messages` table. After UI cleanup:

**Recommended approach:**
1. Remove the `$isPaymentReview` Blade block entirely (payment card rendering).
2. Add a small fallback: if `message_type` is one of the removed payment types, render as a generic system card with the original text.
3. Example fallback:
   ```
   Payment screenshot detected  (from payment_review_card)
   Payment review approved       (from payment_status_update)
   ```
   This preserves timeline readability without payment-specific UI.

**Do NOT allow approve/reject buttons to appear.** Removal of the `$paymentCases` variable from the controller ensures no case lookups work in the view.

---

## 6. Implementation Phases

| Phase | Task | Risk |
|-------|------|------|
| **R3A** | Remove routes, controller, smoke commands from filesystem | Low — no runtime impact (already inactive) |
| **R3B** | Replace payment UI with generic system fallback; remove `$paymentCases` from controller | Low — Blade-only |
| **R3C** | Remove payment models, services, migrations, env references | Medium — composer autoload may need `dump-autoload` |
| **R3D** | Remove seeder + payment plan docs | Low |
| **R3E** | Update CURRENT_STATE.md, FEATURE_LOCKS.md, SMOKE_TESTS.md | Low |
| **R3F** | Run `php artisan test` + `smoke:locked` + manual dashboard check | Low |

**Recommended: R3A+R3B together** (small, safe, clean visible impact). Then R3C+R3D+R3E together. R3F as final gate.

---

## 7. Tests Required After Cleanup

```bash
php artisan test
php artisan smoke:f1
php artisan smoke:f2
php artisan smoke:f3
php artisan smoke:webhook-events
php artisan smoke:telegram-image
php artisan smoke:image-admin-reply
php artisan smoke:faq-admin
php artisan smoke:locked
```

Manual checks:
- Open `/dashboard` — no crash, no payment references
- Open customer conversation with old payment messages — generic system cards rendered
- Open `/dashboard/faqs` — still works

---

## 8. Risks

| Risk | Mitigation |
|------|------------|
| Old DB payment messages cause Blade errors | Generic system fallback renders safely |
| Route references broken | Remove only payment routes; keep all others |
| Migrations rollback issues | Leave tables in DB; delete migration files only |
| Composer autoload stale class refs | Run `composer dump-autoload` after deletions |
| Accidental image preview code removal | Keep `TelegramFileController` and image proxy route |
| `customer_emails` may be needed later | Keep DB table; delete model/service only. Re-add if future needs arise |
| FAQ `payment_account` entry | Keep or rename category; leave keywords intact for Burmese payment FAQ |
| Stale env vars | Remove from `.env.example`; local `.env` clean by user |

---

## 9. Recommendation

**Next task: R3A + R3B — Remove payment routes/controller/smokes + replace payment UI with generic fallback.**

This is the smallest safe step with visible impact:
- Delete ~18 files (routes, controller, 7 smokes, 5 services, seed command)
- Edit ~3 files (web.php, DashboardController, conversation blade)
- Keep DB tables, models, migrations for R3C
- Run all gates and manual checks

---

## Tests
- `php artisan test`: 10 passed, 21 assertions
- `php artisan smoke:locked`: PASS (7 features)
