# Laravel Support Dashboard Skill

Use this skill for Laravel backend/dashboard implementation.

## Rules
Laravel is the source of truth.
No n8n.
No Prisma.
No Next.js unless explicitly requested.

## Use
- Controllers for HTTP/webhooks
- Services for business logic
- Jobs for async sending/downloading
- Eloquent models for DB
- Blade/Livewire for dashboard
- Queues for slow channel/API work

## Conversation Data
Messages are the source of truth.
Timeline order:

```text
created_at ASC, id ASC
```

## Suggested Models
- Customer
- Conversation
- Message
- PaymentCase
- SupportCase
- AdminNote

## Label Logic
Keep label logic in one service/class.
Early labels:
- Resolved
- Needs Reply
- In Chat
- Payment

Test every label rule.
