# AGENTS.md — OrangePlay Laravel MVP Rules

## Project Rules
- Laravel MVP only.
- Telegram first.
- Blade dashboard for now.
- One task = one feature or one bug.
- Keep changes small and isolated.

## Locked Features
- F1 Telegram Greeting Flow
- F2 DB FAQ Auto Reply
- F3 Admin Reply from Dashboard to Telegram

## Required Gates
Before any commit, run:

```bash
php artisan test
php artisan smoke:f1
php artisan smoke:f2
php artisan smoke:f3
php artisan smoke:locked
```

Run the feature-specific smoke test too when relevant.
Do not commit if any required gate fails.

## No-Go Rules
- Do not add React unless explicitly requested.
- Do not add n8n, Prisma, Viber, Messenger, Redis, Queue, or WebSocket unless explicitly requested.
- Do not change locked behavior.
- Do not change migrations for documentation tasks.
- Do not print secrets.
- Do not commit `.env`.
- Do not push unless the user explicitly asks.
- Do not refactor architecture during a small task.

## Manual-First Rule
- User-facing behavior is not locked by smoke tests alone.
- Wait for real manual confirmation before calling a feature locked.
- Update lock coverage only after manual pass.

## Report Format
Final reports must include:
- summary
- files changed
- behavior
- tests
- commit hash
- pushed? yes/no
- manual test needed/result
- risks deferred
