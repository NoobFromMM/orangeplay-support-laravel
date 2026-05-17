# F4 Architecture Alignment Audit

Date: 2026-05-17

This audit is report-only. It compares the current Laravel MVP to the longer-term target architecture without proposing code changes.

## Current MVP Summary

The repository is a small Laravel-first Telegram MVP with a Blade dashboard.

Routes in place:
- `POST /webhooks/telegram` for inbound Telegram updates
- `GET /dashboard` for the customer list
- `GET /customers/{platform}/{platformUserId}` for a conversation timeline
- `POST /customers/{platform}/{platformUserId}/reply` for admin replies
- `GET /` for the default welcome page

Core application pieces:
- `TelegramWebhookController` receives Telegram payloads, normalizes them, stores messages, runs FAQ matching, and decides whether to auto-reply or mark a conversation as needing human help
- `DashboardController` lists customers, renders timelines, and sends admin replies back to Telegram
- `TelegramUpdateNormalizer` converts Telegram webhook payloads into a common internal message shape
- `ConversationService` creates customers and conversations, stores inbound/outbound/admin messages, and updates status
- `FaqMatcher` handles DB-backed keyword matching
- `TelegramBotService` sends outbound Telegram text messages directly

Current persistence model:
- `customers` stores platform, platform_user_id, display_name, and username
- `conversations` stores customer_id, status, and last_message_at
- `messages` stores conversation_id, customer_id, platform, direction, sender_type, message_type, text, raw_payload, and metadata
- `faq_entries` stores the FAQ knowledge base

Locked behavior already proven by smoke tests:
- F1 Telegram Greeting Flow
- F2 DB FAQ Auto Reply
- F3 Admin Reply from Dashboard to Telegram

## Target Architecture Gap

The target architecture is a broader omnichannel support backend with a future React dashboard. The current MVP covers only a narrow slice of that target.

Key gaps:
- No `webhook_events` table or equivalent raw event log
- No explicit channel adapter boundary
- No `support_customers` / `support_identities` split
- No `support_conversations` / `support_messages` naming alignment
- No queue-backed outbound job flow
- No dedicated API layer for a future React dashboard
- No Viber or Facebook Messenger channel support
- No agent/auth layer for the dashboard
- No shared message/event envelope beyond the Telegram-specific normalizer

## Gap By Target Area

### Laravel backend
Current state:
- Laravel is already the source of truth
- Business logic, persistence, and dashboard rendering all live in the app

Gap:
- The backend is still organized around a single Telegram path instead of a channel-neutral support domain

### Future React dashboard
Current state:
- The dashboard is Blade-based and server rendered

Gap:
- There is no API-first boundary yet for a later React inbox
- The current UI is tightly coupled to controller/view rendering

### Channel adapters
Current state:
- Telegram has a normalizer and a bot sender, but they are not wrapped behind a common adapter abstraction

Gap:
- Adding Viber or Messenger would likely duplicate controller and service logic unless the adapter boundary is introduced later

### `webhook_events`
Current state:
- Raw payload is stored inside `messages.raw_payload` after processing

Gap:
- There is no pre-normalization raw event archive
- A normalizer failure can still lose the original inbound event

### `support_customers` / `support_identities` / `support_conversations` / `support_messages`
Current state:
- The app uses `customers`, `conversations`, and `messages`
- A customer is currently tied to a single platform + platform_user_id record

Gap:
- The current schema does not separate person-level customer data from channel identities
- The naming is not yet aligned with the target support domain
- Multi-channel identity linking is not modeled yet

### Queue / jobs
Current state:
- Laravel ships with a jobs table, but the app sends Telegram messages synchronously

Gap:
- Outbound sending has no queued retry path
- Message send failures are handled immediately at request time

### Future Viber / Messenger
Current state:
- Telegram is the only live channel

Gap:
- There is no shared channel contract or shared normalization layer ready for additional channels
- New channels would have to fit into Telegram-shaped code unless the boundary is introduced first

## Risks

High risk:
- Raw webhook loss if normalization or downstream handling fails before the payload is safely recorded
- Controller coupling to Telegram-specific processing, FAQ logic, and outbound delivery
- Synchronous outbound delivery can fail under API outages or throttling without retry
- Dashboard access is open by default, which is acceptable for MVP testing but not for production use

Medium risk:
- Schema naming and domain naming will drift further from the target if new features keep landing in the current `customers / conversations / messages` shape
- Conversation status handling is currently central but still string-based, so new states can be missed by helper queries
- A Blade-only dashboard is fine for MVP work, but it raises the eventual React migration cost if too much logic accumulates in the view layer

Low risk:
- `GreetingMatcher` still exists as legacy support code, so there is some concept overlap with the DB FAQ matcher
- The app currently assumes Telegram send success based on HTTP success, not on a deeper delivery lifecycle

## Recommended Safe Refactor Sequence

1. Add a `webhook_events` raw archive first
   - This is the smallest architectural hardening step
   - It protects inbound data before normalization or business logic runs

2. Introduce a channel adapter boundary
   - This keeps Telegram behavior intact while making later channels less invasive
   - It should isolate normalization and outbound transport concerns

3. Split customer identity from channel identity
   - This is the point where a person can be linked to multiple channels
   - It sets up the later support-domain naming cleanly

4. Add a queued outbound path
   - Telegram sends can then be retried and observed more reliably
   - It reduces request-time coupling to third-party availability

5. Add an authenticated dashboard/API boundary
   - This is the step that makes a future React inbox realistic
   - It also improves the production security posture

6. Add Viber and Messenger adapters after the boundary exists
   - This avoids cloning Telegram controller logic
   - It keeps the omnichannel expansion manageable

## Next Single Task Recommendation

Recommended next task:
- Add `webhook_events` as a raw inbound archive before normalization

Why this is the safest next step:
- It is additive
- It does not change locked Telegram behavior
- It protects the most fragile part of the current flow
- It creates a stable base for every future channel

## Locked Gate Reminder

Before any future commit in this repo, run:

```bash
php artisan test
php artisan smoke:f1
php artisan smoke:f2
php artisan smoke:f3
php artisan smoke:locked
```

Do not lock a new feature until the manual pass is complete and the smoke coverage has been updated.
