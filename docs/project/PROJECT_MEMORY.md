# Project Memory

## Why The Laravel Rebuild Exists
- The project is moving toward a single Laravel source of truth.
- The goal is to own webhooks, normalization, database writes, support logic, and dashboard behavior inside Laravel.
- This avoids splitting support behavior across multiple orchestration systems.

## Lessons Learned
- Small slices are safer than broad refactors.
- Locked features must stay intact while new work lands.
- Telegram behavior is the current proof point for the MVP.
- Raw payload retention matters for debugging and future channel support.
- Worker provider can be inconsistent (kbzpay vs wavepay for same screenshot), so duplicate matching uses transaction_id first, not provider.
- Manual Telegram evidence must inspect the latest event only; stale data misleads debugging.
- Always verify php artisan serve/ngrok are running when testing webhooks.
- Http::fake chaining in Laravel causes cross-command interference; decouple smoke tests from Http::fake where possible.
- `env()` caches values; use constructor injection for test-friendliness over env-dependent logic.

## Working Rules
- One task = one feature or one bug.
- Do not expand scope mid-task.
- Keep services small and controllers readable.
- Preserve Burmese text exactly.
- No n8n: Laravel remains the single source of truth.

## Manual-First Rule
- Smoke tests protect regressions.
- Manual Telegram or dashboard confirmation is required before a feature becomes locked.
- Do not treat synthetic tests as the final proof for user-facing behavior.

## No Secrets Rule
- Never print secrets.
- Never commit `.env`.
- Never hardcode API tokens.
- Never expose channel credentials in browser-facing code.
