# AGENTS.md — OrangePlay Support Development Rules

## Role
You are building the OrangePlay customer support dashboard.

The user will guide features one by one. Do not infer or build future features unless explicitly requested.

## Core Principle
Stability first. Work in very small feature slices.

One task = one issue or one feature.

If a previously working feature breaks, stop the new work and fix or roll back the regression first.

## Architecture Direction
The preferred rebuild direction is a single Laravel-based system without n8n.

Laravel should own:
- channel webhooks
- message normalization
- database writes
- FAQ/business logic
- dashboard UI
- outbound channel replies

Do not introduce n8n, Next.js, Prisma, or workflow JSON unless the user explicitly requests it.

## Feature Process
For every feature:
1. Clarify the smallest possible scope.
2. Implement only that scope.
3. Run automated tests.
4. Ask user to manually test the real behavior.
5. If manual test passes, add or update a smoke/lock test.
6. Commit only after automated tests and manual pass.
7. Do not push unless the user explicitly says to push.

## Locked Feature Rule
A feature becomes locked only after:
- real manual test passes
- a smoke test exists
- regression gates pass
- commit is created

After a feature is locked, every future feature must re-run locked feature tests before commit.

## Required Gates Before Commit
Always run the relevant gates before commit:

```bash
php artisan test
php artisan smoke:locked
```

Run the feature-specific smoke command too, for example:

```bash
php artisan smoke:f1
php artisan smoke:f2
```

If frontend assets exist:

```bash
npm run build
```

Do not commit if any required gate fails.

## Permission/Command Rules
Do not ask for confirmation for safe read-only commands:
- pwd
- ls
- cat
- sed -n
- grep
- rg
- find
- git status
- git diff
- git log
- php artisan route:list
- php artisan test

Ask or stop before destructive/high-risk commands:
- rm -rf
- git reset --hard
- git push
- database drop/truncate/wipe
- destructive migrations
- deleting files
- rotating secrets
- modifying production credentials

## Security Rules
Never print secrets.
Never commit .env.
Never hardcode API tokens.
Never expose Telegram/Viber/Messenger tokens to the browser.
Private channel files must be fetched server-side or proxied safely.

## Burmese Text Rule
Preserve Burmese text carefully.
Do not copy corrupted Burmese from terminal output.
Use exact text from user-provided prompt or source files when Burmese copy matters.

## Conversation-First Data Rule
Messages are the source of truth.
Timeline ordering must be:

```text
created_at ASC, id ASC
```

Each channel should normalize inbound data to a common internal shape.

## Channel Adapter Rule
Channel-specific differences must stay inside channel services.
Business logic should receive normalized input.

Normalized message shape:
- platform
- platform_user_id
- display_name
- username
- message_type
- text
- file_id
- raw_payload
- metadata

## Reporting Format
Final reports must include:
- files changed
- behavior implemented/fixed
- tests run and results
- manual test needed/result
- commit hash if committed
- pushed? yes/no
- skipped/deferred risks
