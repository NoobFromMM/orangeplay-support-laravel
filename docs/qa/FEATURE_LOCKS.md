# Feature Locks

## Locked Active Features (9)

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

### F8 — Human Takeover Bot Pause
**Status**: LOCKED

**Purpose**: When an admin replies from the dashboard, the bot pauses auto-replies so the human can handle the conversation. Bot resumes on manual Resolve, pauses again on Reopen.

**Behavior**:
- FAQ match → bot auto reply + resolved only when bot_paused is false
- FAQ miss → Needs Reply
- Image/file inbound → Needs Reply
- Admin reply → does not auto-resolve, enables bot_paused
- While bot_paused is true, inbound FAQ-matching text must not trigger bot auto reply
- Manual Resolve → resolved + clears bot_paused
- Manual Reopen → Needs Reply + enables bot_paused

**Smoke**: `php artisan smoke:human-takeover`

---

### F9 — Support Case Workflow
**Status**: LOCKED

**Smoke**: `php artisan smoke:case-create`

**Behavior**:
- Conversation-level Create Case available per conversation
- Source message selected from recent inbound customer text/image/file messages
- Latest inbound defaults as source when available
- Support case linked to customer, conversation, and source message
- Active open/in_progress cases appear in Active Cases summary on conversation page
- Case created/resolved/rejected events appear in conversation timeline as cards
- Cases index and detail pages render
- Raw source metadata not exposed in UI
- Case resolve/reject can send and save a customer-facing update

**Protected invariants**:
- Case create/resolve/reject does NOT change conversation.status
- Case create/resolve/reject does NOT change conversation.bot_paused
- Bot pause controlled only by admin chat actions (reply, resolve, reopen)
- Conversation status controls chat queue only; support case status controls back-office case lifecycle

---

## Payment Runtime — Removed (R2+R3)
All payment features (F6 P1-P6B, old F8 duplicate detection, P10B) have been fully removed from the codebase.

## Pending Features
- Dashboard auth before production
- Viber channel support
- Facebook Messenger channel support
- FAQ import / AI dataset builder

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
