# F4 Architecture Alignment Audit

Date: 2026-05-17

---

## 1. Current MVP Summary

### What exists now

**Routes (5):**
- `GET /` — welcome page
- `GET /dashboard` — customer list
- `GET /customers/{platform}/{platformUserId}` — conversation timeline + reply form
- `POST /customers/{platform}/{platformUserId}/reply` — admin reply
- `POST /webhooks/telegram` — Telegram webhook receiver

**Models (4 app + 1 stock):**
- `Customer` — platform + platform_user_id unique, display_name, username
- `Conversation` — customer_id FK, status, last_message_at (datetime cast)
- `Message` — customer_id + conversation_id FK, platform, direction, sender_type, message_type, text, raw_payload (json), metadata (json)
- `FaqEntry` — intent_code unique, keywords (json), answer_text, priority, is_active
- `User` — stock Laravel (unused)

**Services (5):**
- `TelegramUpdateNormalizer` — raw Telegram payload to normalized shape
- `TelegramBotService` — sends text via Bot API, reads token from env
- `ConversationService` — findOrCreate customer/conversation, save inbound/outbound/admin messages, setStatus
- `GreetingMatcher` — hardcoded greeting check (legacy, unused in controller since F2)
- `FaqMatcher` — DB-backed keyword matching with priority ordering

**Controllers (2):**
- `TelegramWebhookController` — full inbound flow: normalize, save, FAQ match, reply or set Needs Reply
- `DashboardController` — index, showConversation, sendReply (Telegram only)

**Commands (4):**
- `smoke:f1` — F1 greeting flow smoke test
- `smoke:f2` — F2 FAQ auto reply smoke test (10 cases)
- `smoke:f3` — F3 admin reply smoke test (success + failure)
- `smoke:locked` — runs F1+F2+F3 sequentially

**Database tables (5 app + 7 stock):**
- customers, conversations, messages, faq_entries (+ users, sessions, cache, jobs)

**Views (2):**
- dashboard/index.blade.php — customer table
- dashboard/conversation.blade.php — conversation timeline + reply form

**Locked features:** F1, F2, F3

---

## 2. Alignment with Target Architecture

| Target Component | Current State | Gap | Priority |
|-----------------|---------------|-----|----------|
| Channel Adapter Pattern | Partially — Telegram-specific normalizer/service exist but no interface | Missing abstraction layer | Do now |
| support_customers | Exists as `customers` table | Naming not aligned; no channel identity concept | Later |
| support_channel_identities | Missing | Single customer can only have one platform+id pair, no multi-channel yet | Later |
| support_conversations | Exists as `conversations` table | Works fine for single channel; status filter limited | Later |
| support_messages | Exists as `messages` table | Raw payload stores full webhook, but no separate event log | Later |
| webhook_events | Missing | No raw event backup before normalization; if normalizer fails, event lost | Should do now |
| support_agents | Missing | No agent/auth model; dashboard has no login | Later |
| internal notes | Missing | No admin notes on conversations | Later |
| queues/jobs | Exists (stock jobs table) | Not used; sendMessage is synchronous, no retry on failure | Later |
| outgoing message service | Partially — TelegramBotService | No outbox/retry pattern; send failures are fire-and-forget | Later |
| React dashboard | Missing | Current Blade MVP; no API layer for future React | Later |
| WebSocket/realtime | Missing | Dashboard requires manual refresh | Later |
| payment_cases | Missing | Not yet built | Later |
| admin_notes | Missing | Not yet built | Later |

---

## 3. Current Risks

### High risk

**A. No raw webhook event backup**
Telegram sends a webhook, the normalizer processes it, and the raw payload is stored in `messages.raw_payload`. But if normalizer throws an exception before reaching the message save step, the event is lost. There is no separate `webhook_events` table that captures the raw payload first, before normalization. Every other channel we add will have this same vulnerability.

**B. Channel logic coupling**
`TelegramWebhookController` knows about both Telegram normalization AND FAQ matching AND message saving. Adding Viber would require a parallel controller or a switch/case. The channel-specific differences (how to extract user ID, display name, text) are not behind an interface. Risk: controller gets bloated with per-channel if/else.

**C. No queue or retry on message sending**
`TelegramBotService::sendMessage` is called synchronously in the webhook handler and dashboard reply. If Telegram API is down or returns 429, the send fails silently (returns false, controller shows error). No job is queued for retry. Risk: lost admin replies and bot replies under load or API issues.

**D. Dashboard has no auth**
Anyone who knows the URL can read all customer conversations and send replies. Risk: data exposure, spam, abuse. This is acceptable for MVP manual testing but is a blocker for production.

### Medium risk

**E. Table naming divergence from target**
Current tables are `customers`, `conversations`, `messages` (no `support_` prefix). Target architecture expects `support_` prefix. Renaming later is not hard but requires migration planning.

**F. ConversationService status filter rigidity**
`findOrCreateConversation` filters by `['new', 'open', 'resolved', 'in_chat']`. Adding a new status requires remembering to update this array. Risk: forgotten status causes duplicate conversations.

**G. Blade MVP limitations**
Current dashboard is server-rendered Blade with inline CSS. Works for MVP. Cannot scale to rich realtime inbox, search, or agent multi-tasking. Adding more features to Blade will make React migration harder.

### Low risk

**H. Missing message delivery status**
Messages have no `delivered_at` or `delivery_status` field. We don't know if Telegram actually received the bot reply or admin reply. Currently we assume success if HTTP 200.

---

## 4. Recommended Next Refactor Sequence

### R1: Add webhook_events raw logging table

**Goal:** Ensure every incoming webhook is stored raw before any processing. Decouples receipt from normalization.

**Files likely affected:**
- `database/migrations/` — new migration
- `app/Models/WebhookEvent.php` — new model
- `app/Http/Controllers/Webhooks/TelegramWebhookController.php` — save raw event first
- `app/Services/Support/WebhookEventService.php` — new service (optional)
- `app/Console/Commands/SmokeF1.php` — may need slight update

**Smoke tests to protect:** F1, F2, F3
**Manual test needed:** Send `hi` via Telegram, verify event row created
**Risk level:** Low — add-only, no breaking change to existing tables

### R2: Introduce ChannelInterface + refactor Telegram behind it

**Goal:** Create a `ChannelInterface` with methods like `normalize(array $raw): array`, `sendMessage(string $chatId, string $text): bool`. Make `TelegramChannelService` implement it. Update controller to depend on interface.

**Files likely affected:**
- `app/Contracts/ChannelInterface.php` — new
- `app/Services/Channels/TelegramChannelService.php` — wraps TelegramNormalizer + TelegramBotService
- `app/Http/Controllers/Webhooks/TelegramWebhookController.php` — use interface
- `app/Services/Support/ConversationService.php` — may accept channel param

**Smoke tests to protect:** F1, F2, F3
**Manual test needed:** Send `hi`, admin reply, verify same behavior
**Risk level:** Medium — refactors existing working code

### R3: Evolve customers to support channel identities

**Goal:** Add `channel_identities` table so one customer can have multiple platform identities (Telegram + Viber + Messenger). Customers table becomes the person, channel_identities becomes the per-platform link.

**Files likely affected:**
- `database/migrations/` — new table
- `app/Models/ChannelIdentity.php` — new
- `app/Models/Customer.php` — add relation
- `app/Services/Support/ConversationService.php` — update lookups
- Dashboard views

**Smoke tests to protect:** F1, F2, F3
**Risk level:** Medium — schema change, backward compat needed

### R4: Add SupportAgent model + basic auth

**Goal:** Add agents table, middleware to protect dashboard. Simple login (Laravel default or token-based).

**Files likely affected:**
- `database/migrations/` — agents table or extend users
- `app/Models/SupportAgent.php`
- `app/Http/Middleware/` — dashboard auth
- Routes

**Risk level:** Medium — changes dashboard access patterns

### R5: React dashboard later

**Goal:** Replace Blade dashboard with React + API endpoints. Backend exposes JSON API for conversations, messages, reply. Frontend polls or uses WebSocket.

**Prerequisite:** R2 (channel interface), R3 (channel identities), R4 (auth) should be done first.

**Risk level:** High — large scope, requires frontend tooling

---

## 5. Recommendation: What to Do Next

**Recommended next task: R1 — Add webhook_events raw logging table**

**Why:**

1. **Safest first step.** R1 is add-only. No existing code behavior changes. No renaming. No breaking smoke tests.

2. **Reduces data loss risk.** Every future channel (Viber, Messenger) will benefit from a single raw event log before normalization. Without it, debugging webhook issues requires guessing from normalized messages.

3. **Small scope.** One migration, one model, one line in the webhook controller. Less than 30 minutes to implement and test.

4. **Does not block anything.** R2-R5 can proceed in any order after R1. R1 does not create coupling or lock us into any architecture choice.

5. **Matches AGENTS.md pattern.** One task = one small feature. Manual test. Smoke test. Lock. Commit.

**Alternative skipped: Starting React dashboard (R5)**
Too large, too early. Backend foundation (channels, identities, auth) is not stable enough. Building React on an unstable API means rewriting the React layer later.

**Alternative skipped: Adding Viber/Messenger (F6/F7)**
Should wait until channel interface (R2) exists. Without the interface, adding Viber duplicates Telegram controller logic.

---

## 6. Locked Gate Reminder

Every future task must run these BEFORE commit:

```bash
php artisan test
php artisan smoke:f1
php artisan smoke:f2
php artisan smoke:f3
php artisan smoke:locked
```

If a new feature is added:
- Create a feature-specific smoke command (e.g., `smoke:f4`)
- Add it to `smoke:locked` AFTER manual pass
- Manual pass before locking
