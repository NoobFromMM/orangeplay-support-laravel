# Legacy Feature Gap Audit

Date: 2026-05-19

This is a report-only gap audit for the OrangePlay Support MVP. It compares the current Laravel-first system against older support workflow needs and the remaining business gaps. It does not change code, migrations, routes, or behavior.

## 1. Current Completed Features

Locked and working:
- F1 Telegram greeting flow
- F2 DB FAQ auto reply
- F3 admin reply from dashboard
- W1/W2 raw webhook logging
- F5 Telegram image receive and preview
- F5A image + admin reply regression
- F6 P1 payment case foundation
- F6 P2 payment screenshot processing service
- F6 P3 payment webhook integration
- F6 P5 payment email attachment flow
- F6 P6A payment case resolution service
- F6 P6B dashboard payment approve/reject actions

Operationally, the MVP now covers:
- greeting
- FAQ matching
- admin reply
- raw event logging
- image preview
- payment screenshot detection
- email request after payment
- email attachment to payment case
- approve/reject review actions

## 2. FAQ / Auto-Reply Content Management Gap

Current behavior:
- FAQ entries are seeded by `FaqSeeder`
- `DatabaseSeeder` does not seed the FAQ automatically
- `faq_entries` already supports `intent_code`, `category`, `keywords`, `answer_text`, `priority`, and `is_active`
- `FaqMatcher` reads active entries ordered by priority and matches keyword substrings

What is missing:
- no dashboard UI for FAQ admin input
- no create/edit/delete workflow for FAQ answers
- no CSV or Google Sheet style import path
- no content management flow for support staff

Compared with a sheet-based or manual content workflow, the current MVP is static unless the database is edited directly.

Suggested next small feature:
- F7 FAQ Admin CRUD

Or, if spreadsheet-style updates matter more:
- F7 FAQ Import from CSV / Sheet-style data

Suggested minimal fields:
- `intent_code`
- `category`
- `keywords`
- `answer_text`
- `priority`
- `is_active`

## 3. Duplicate Payment Screenshot / Transaction Gap

Current behavior:
- `payment_cases.transaction_id` exists
- `payment_cases` has an index on `transaction_id`
- no unique constraint is documented on `transaction_id`
- locked payment flows create cases and review cards, but duplicate handling is not a clearly locked behavior

What is missing:
- no explicit duplicate transaction enforcement
- no clear customer-facing message for repeated or reused screenshots
- no visible safe behavior for same `transaction_id` submitted again
- no explicit policy for duplicate screenshots from the same customer or a different customer

Recommended safe next feature:
- F8 Duplicate Payment Detection

Suggested behavior:
- if `transaction_id` matches an existing approved case, reply that the screenshot was already reviewed or used
- if `transaction_id` matches `pending_review` or `needs_email`, reply that it is already received and waiting review
- do not create a duplicate `payment_case`
- log the duplicate attempt as a timeline or system message

## 4. Payment Case Lifecycle Gaps

Current lifecycle now includes:
- `needs_email`
- `pending_review`
- `approved`
- `rejected`
- `reviewed_at`
- `reviewed_by`
- payment status update timeline messages

Still missing or not yet fully locked as business logic:
- explicit admin reject reason or note
- a clear customer notification policy for approve/reject
- subscription extension integration
- duplicate transaction protection
- payment amount/package validation

The review layer is present, but it is still separate from actual subscription fulfillment.

## 5. Customer Support Workflow Gaps

Still missing:
- login/auth
- roles
- assigned agent model
- internal notes
- tags
- search
- customer profile view
- unread/new message indicators

Before Viber or Messenger, the most useful workflow additions are:
- auth
- search/filtering
- internal notes
- agent assignment or ownership

## 6. Dashboard UX Gaps

Current dashboard is workable, but still MVP-shaped.

Gaps to watch:
- newest-first timeline controls
- payment card readability on narrow screens
- dashboard list filtering
- stronger status badge clarity
- better empty states
- payment case visibility and review affordances

The dashboard is still Blade-first and should remain light until the next structural phase.

## 7. Channel Gaps

Missing channel work:
- Viber
- Facebook Messenger
- channel adapter refactor
- channel identity model

These should stay deferred until the core gaps above are addressed, especially auth and content management.

## 8. Old Workflow Parity

No old n8n workflow export or legacy workflow file was present in this repo, so a full one-to-one workflow comparison is not possible from the local files.

Based on the project memory and current docs, likely parity items from the older workflow were:
- FAQ source and content updates
- payment duplicate detection
- email follow-up after payment screenshot
- payment review
- non-payment image handling
- admin reply
- raw payload logging
- bot confirmations

The current Laravel MVP now covers most of the execution path, but not all of the content and safety controls that a mature support workflow usually needs.

## 9. Prioritized Next Tasks

### 1. FAQ Admin / Data Input Management
- Why: support content changes often, and the current FAQ set is database-seeded rather than operator-managed
- Risk: low to medium
- First small task: add a minimal FAQ admin CRUD surface or import flow for `faq_entries`
- Suggested agent: opencode

### 2. Duplicate Payment Detection
- Why: payment reuse and repeated screenshots are a real risk after payment handling is live
- Risk: medium
- First small task: add idempotent transaction detection for `payment_cases.transaction_id`
- Suggested agent: Codex

### 3. Dashboard Login / Auth
- Why: production exposure should not rely on an open dashboard
- Risk: medium
- First small task: protect the dashboard route group with login
- Suggested agent: Codex

### 4. Payment Approve / Reject Customer Notification Review
- Why: the review action should be explicit and understandable to customers
- Risk: medium
- First small task: define and verify the customer notification behavior for approval/rejection
- Suggested agent: Codex

### 5. Dashboard Search / Filters
- Why: support staff need to find conversations and payment cases quickly
- Risk: low to medium
- First small task: add conversation and payment-case filtering to the dashboard list
- Suggested agent: opencode

## 10. Recommendation

Recommended next implementation task:
- **F7 FAQ Admin / Data Input Management**

Why:
- support content changes are a core business need
- it addresses a major old-workflow gap
- it is lower risk than jumping straight into channel expansion
- it gives the team a way to maintain FAQ answers without direct database edits

If payment abuse or duplicate transactions are the more urgent operational concern, then `F8 Duplicate Payment Detection` should move ahead of FAQ CRUD. Based on the current docs, FAQ management looks like the cleaner next step before Viber/Messenger.

## 11. Smoke / Test Recommendation

- Keep `php artisan smoke:locked` mandatory
- Add manual evidence notes for any new channel or payment workflow
- Avoid broad refactors until the next missing workflow gap is closed

## Locked Gate Reminder

Before any future commit in this repo, run:

```bash
php artisan test
php artisan smoke:locked
```

Do not lock a new feature until manual verification is complete.
