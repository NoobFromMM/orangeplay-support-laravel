# Feature Locks

## Locked Features

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

## Pending Features
- F4 Architecture Alignment Audit
- F5 Image preview
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
