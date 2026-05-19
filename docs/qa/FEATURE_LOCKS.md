# Feature Locks

## Locked Active Features (7)

### F1 — Telegram Greeting Flow
**Status**: LOCKED

**Manual evidence**:
- Real Telegram user Myat Thaw Maung sent `hi` and `hello`
- Bot replied with exact greeting text
- Dashboard DB records created (customer, conversation, messages)
- Conversation status = Resolved

**Smoke**: `php artisan smoke:f1`

---

### F2 — DB FAQ Auto Reply
**Status**: LOCKED

**Manual evidence**:
- Real Telegram user sent `hi`, `တစ်လဘယ်လောက်လဲ`, `သက်တမ်းတိုးချင်လို့`, `မင်ဘာဝင်ချင်တယ်`, `kpay နံပါတ်ပေးပါ`, `xyzzy123blah`
- Greeting matched and replied correctly
- Pricing matched without payment account number
- Payment account matched with correct account info
- Unknown text produced no bot reply, status = Needs Reply
- Dashboard labels correct

**Smoke**: `php artisan smoke:f2`

---

### F3 — Admin Reply from Dashboard
**Status**: LOCKED

**Manual evidence**:
- Admin sent reply from dashboard conversation page
- Telegram user received the admin message in real time
- Timeline showed admin outbound message with sender_type=admin
- Conversation status changed to In Chat / in_chat
- Dashboard status badges updated correctly

**Smoke**: `php artisan smoke:f3`

---

### W1/W2 — Telegram Webhook Raw Logging
**Status**: LOCKED

**Manual evidence**:
- Real Telegram user sent `hi`
- Webhook event row created with channel=telegram, status=processed
- Full payload preserved including message.text=hi
- Existing F1/F2/F3 behavior intact
- No errors, error_message=null

**Smoke**: `php artisan smoke:webhook-events`

---

### F5 — Telegram Image Receive + Dashboard Preview
**Status**: LOCKED

**Manual evidence**:
- Real Telegram user sent a photo
- Webhook event logged with status=processed
- Image message saved with message_type=image, metadata includes telegram_file_id and dimensions
- Dashboard shows image preview via proxy URL, no token exposed
- Proxy route serves image bytes (HTTP 200)
- No bot auto-reply for image messages
- Conversation status = Needs Reply

**Smoke**: `php artisan smoke:telegram-image`

---

### F5A — Image + Admin Reply Regression Flow
**Status**: LOCKED

**Manual evidence**:
- Real Telegram user sent a photo, admin replied `ပုံရရှိပါပြီရှင့်။` from dashboard
- Image saved with metadata, no bot auto-reply, status Needs Reply after image
- Admin reply saved with source=dashboard, status changed to in_chat
- Dashboard shows image preview and admin reply text
- Telegram delivery of admin reply confirmed by user
- No token exposed

**Smoke**: `php artisan smoke:image-admin-reply`

---

### F6 P1 — Payment Case Foundation
**Status**: LOCKED

**Evidence**:
- payment_cases table and model created
- PaymentCheckClient returns safe failure when URL missing, normalizes worker response
- PaymentCaseService creates cases with provider, transaction_id, worker_response
- Handles null transaction_id, rejects is_payment=false
- Smoke covers all 5 test cases
- No webhook behavior changed, no production Worker calls

**Smoke**: `php artisan smoke:payment-foundation`

---

### F6 P2 — Payment Screenshot Processing Service
**Status**: LOCKED

**Evidence**:
- PaymentScreenshotService processes image messages with worker results
- is_payment=true creates payment case + payment_review_card timeline message
- is_payment=false returns null, nothing created
- Null transaction_id accepted
- Non-image messages rejected with InvalidArgumentException
- Smoke covers all 4 scenarios
- Telegram webhook NOT connected yet, Worker NOT called

**Smoke**: `php artisan smoke:payment-screenshot`

---

### F6 P3 — Payment Webhook Integration
**Status**: LOCKED

**Manual evidence**:
- Real Telegram payment screenshot sent
- payment_check.ok=true, is_payment=true
- provider=ayapay, transaction_id=259430808614
- payment_case created (id=97) with pending_review status
- payment_review_card timeline message created (id=1901)
- No bot auto-reply on payment detection
- Worker called via multipart contract with AGENT_TOKEN + GEMINI_KEY

**Smoke**: `php artisan smoke:payment-webhook`

---

### F6 P5 — Payment Email Attachment Flow
**Status**: LOCKED

**Manual evidence**:
- payment screenshot sent + bot asked for email
- customer replied with email: `customer@orangeplay.com`
- payment_case id=232 updated: customer_email=customer@orangeplay.com, status=pending_review
- bot confirmation sent with metadata.event=payment_email_received
- metadata.payment_case_id=232 linked correctly
- only 1 confirmation sent (no duplicate)
- dashboard shows email on payment card, no secrets

**Smoke**: `php artisan smoke:payment-email-attach`

---

### F6 P6A — Payment Case Resolution Service
**Status**: LOCKED

**Evidence**:
- PaymentCaseResolutionService with approve/reject methods
- Only pending_review cases can be resolved (guards on needs_email/approved/rejected)
- Creates payment_status_update timeline message with reviewer metadata
- Smoke covers: approve, reject, block needs_email, duplicate approve, reject approved
- Service only — no routes or dashboard UI yet

**Smoke**: `php artisan smoke:payment-resolution`

---

### F7 — FAQ Admin Data Input Management
**Status**: LOCKED

**Manual evidence**:
- Created FAQ via dashboard with Burmese keyword `စမ်းမေးခွန်း`
- Bot replied with exact FAQ answer when keyword matched (active)
- FAQ deactivated; same keyword no longer triggered auto reply
- Dashboard shows inactive badge, FAQ list renders correctly
- No secrets exposed

**Smoke**: `php artisan smoke:faq-admin`

---

## Removed/Deprecated Payment Runtime (R2)
Payment runtime path stopped in Telegram webhook. Services/tables/UI preserved but not active.

- ~~F6 P1 — Payment Case Foundation~~
- ~~F6 P2 — Payment Screenshot Processing Service~~
- ~~F6 P3 — Payment Webhook Integration~~
- ~~F6 P3D — Payment Review Card UI + DESC Order~~
- ~~F6 P4 — Ask Email After Payment Screenshot~~
- ~~F6 P5 — Payment Email Attachment Flow~~
- ~~F6 P6A — Payment Case Resolution Service~~
- ~~F6 P6B — Dashboard Payment Approve/Reject Actions~~
- ~~F8 — Duplicate Payment Detection~~
- ~~P10B — Customer Emails Foundation~~

## Pending Features
- F4 Architecture Alignment Audit
- F5 Payment screenshot
- F6 Viber channel
- F7 Facebook Messenger channel

## Lock Rule
A feature is locked only after:
1. User manually tests the real flow and confirms pass.
2. A smoke test exists for the manual behavior.
3. All locked feature tests pass.
4. Commit is created.

## Regression Rule
Before committing any future feature, run:

```bash
php artisan test
php artisan smoke:locked
```

Also run the feature-specific smoke command.

If any locked feature fails, stop and fix/rollback before continuing.
