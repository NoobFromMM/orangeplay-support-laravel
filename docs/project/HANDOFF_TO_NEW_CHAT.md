# Handoff to New Chat

Date: 2026-05-19

## What This Project Is

A **standalone Laravel support dashboard MVP** for OrangePlay customer support. No n8n. No external orchestration. Laravel owns webhooks, database, FAQ matching, and dashboard UI.

## Active Locked Features (7)

| # | Feature | Smoke Command |
|---|---------|---------------|
| F1 | Telegram Greeting Flow | `smoke:f1` |
| F2 | DB FAQ Auto Reply | `smoke:f2` |
| F3 | Admin Reply from Dashboard | `smoke:f3` |
| W1/W2 | Telegram Webhook Raw Logging | `smoke:webhook-events` |
| F5 | Telegram Image Receive + Dashboard Preview | `smoke:telegram-image` |
| F5A | Image + Admin Reply Regression | `smoke:image-admin-reply` |
| F7 | FAQ Admin Data Input Management | `smoke:faq-admin` |

**`php artisan smoke:locked` runs all 7.**

## Current Runtime Behavior

| Input | Action |
|-------|--------|
| FAQ-matching text | bot auto-reply + status=resolved |
| Unknown text | status=Needs Reply |
| Image/file | saved with preview + status=Needs Reply |
| Admin reply | reaches Telegram + status=in_chat |

No payment OCR. No Cloudflare Worker. No payment_case creation. No email ask/attach.

## Payment Runtime — Deprecated

Payment runtime was removed in commit `bd072da` (R2). Payment services, models, tables, UI, and smoke commands still exist on disk but are INACTIVE. A cleanup plan (`docs/project/PAYMENT_CODE_CLEANUP_PLAN_R3.md`) exists but has not been implemented.

## Directory Map

```
app/
  Http/Controllers/
    Webhooks/TelegramWebhookController.php   ← main webhook entry
    DashboardController.php                  ← dashboard views
    TelegramFileController.php               ← image proxy
    FaqEntryController.php                   ← FAQ CRUD
    PaymentCaseController.php               ← INACTIVE (payment)
  Models/
    Customer.php, Conversation.php, Message.php  ← core
    PaymentCase.php, CustomerEmail.php       ← INACTIVE
  Services/
    Support/
      FaqMatcher.php, GreetingMatcher.php    ← active
      ConversationService.php                ← active
    Telegram/
      TelegramUpdateNormalizer.php           ← active
      TelegramBotService.php                 ← active
    Payments/                                ← all INACTIVE
      PaymentCheckClient.php
      PaymentCaseService.php
      PaymentScreenshotService.php
      PaymentCaseResolutionService.php
      DuplicatePaymentDetector.php
  Console/Commands/
    SmokeF1-F3.php, SmokeFaqAdmin.php, etc.  ← active smokes
    SmokePayment*.php                        ← INACTIVE
database/migrations/
  2026_05_17_000001_create_customers_table.php
  2026_05_17_000002_create_conversations_table.php
  2026_05_17_000003_create_messages_table.php
  2026_05_17_000004_create_faq_entries_table.php
  2026_05_17_000005_create_webhook_events_table.php
  2026_05_17_000006_create_payment_cases_table.php      ← INACTIVE
  2026_05_19_000001_create_customer_emails_table.php     ← INACTIVE
resources/views/dashboard/
  index.blade.php                    ← customer list
  conversation.blade.php             ← conversation timeline
  faqs/index|create|edit             ← FAQ manager
routes/web.php                       ← all routes
```

## Important Rules (from AGENTS.md)

- One task = one feature or one bug
- Keep changes small and isolated
- Do not print secrets; do not commit `.env`
- Do not add React/Livewire/Queue/Redis/WebSocket unless explicitly asked
- Do not add Viber/Messenger unless explicitly asked
- Do not reintroduce payment OCR/Cases/Worker
- Run `php artisan test` and `php artisan smoke:locked` before every commit

## Next Recommended Steps

1. **R3A+R3B** — Delete payment files (routes/controller/smokes/services) and remove payment UI from conversation blade. (See `docs/project/PAYMENT_CODE_CLEANUP_PLAN_R3.md`)
2. **R3C+R3D+R3E** — Remove payment models/migrations/docs, update docs
3. Dashboard auth before any production use
4. Viber channel support (new channel adapter)
5. FAQ import / AI dataset builder

## Quickstart

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=FaqSeeder
php artisan seed:dashboard-preview   # optional preview data
php artisan serve
ngrok http 8000
# Set Telegram webhook to {ngrok_url}/webhooks/telegram
```
