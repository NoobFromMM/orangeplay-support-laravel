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

## Pending Features
- F3 Admin Reply — pending manual test (smoke: `php artisan smoke:f3`)
- F4 Image preview
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
