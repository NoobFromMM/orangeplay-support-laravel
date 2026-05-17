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

## Pending Features
- F2 DB FAQ auto replies (smoke: `php artisan smoke:f2`)
- F3 Admin reply to Telegram
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
