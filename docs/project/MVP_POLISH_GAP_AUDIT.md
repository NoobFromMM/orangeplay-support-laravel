# MVP Polish Gap Audit

## 1. Current locked baseline
The current locked MVP has 13 locked features:

- F1 Telegram Greeting Flow
- F2 DB FAQ Auto Reply
- F3 Admin Reply from Dashboard to Telegram
- W1/W2 Telegram Webhook Raw Logging
- F5 Telegram Image Receive + Dashboard Preview
- F5A Image + Admin Reply Regression Flow
- F6 P1 Payment Case Foundation
- F6 P2 Payment Screenshot Processing Service
- F6 P3 Payment Webhook Integration
- F6 P3D Payment Review Card UI + Conversation DESC Order
- F6 P4 Ask Email After Payment Screenshot
- F6 P5 Payment Email Attachment Flow
- F6 P6A Payment Case Resolution Service
- F6 P6B Dashboard Payment Approve/Reject Actions
- F7 FAQ Admin Data Input Management
- F8 Duplicate Payment Detection

The baseline is stable enough to pause feature growth and review polish, readability, and edge-case clarity before F9 Private Knowledge Bot.

## 2. Dashboard UX polish gaps
Likely gaps that still deserve attention:

- dashboard list columns may still feel dense on smaller screens
- conversation timeline copy can be clearer around system events and payment events
- timezone display may need to be explicit in more places
- message card labels can be more consistent between inbound, outbound, system, and payment entries
- payment cards can be easier to scan when there are multiple statuses in one conversation
- duplicate notices may need more visual separation from normal support messages
- mobile width may still be tight in places with long Burmese text
- empty states can be more helpful, especially when no matching FAQ or payment case exists
- navigation consistency can be tightened so dashboard, FAQ admin, and conversation views feel like one product

## 3. Payment flow polish gaps
The payment lifecycle works, but a few polish and edge-case gaps remain:

- duplicate transaction handling is functional, but the customer-facing wording may still need refinement
- multiple `needs_email` cases can be confusing if a customer sends more than one screenshot before replying
- customer email behavior could be clearer when the email already exists on file
- status wording such as “Waiting for customer email response” may need one final wording pass
- approve/reject copy can be more explicit that review approval is not subscription extension yet
- reject reason or admin note support is still not part of the locked flow
- customer notification after approve/reject still needs a final product decision
- subscription extension is intentionally not implemented yet, so approval still means review approval only

## 4. FAQ Admin polish gaps
FAQ admin is usable, but a few usability improvements are still open:

- create/edit UX can still be simplified for faster data entry
- keyword preview could be easier to scan on the list page
- search and filtering are not yet obvious for larger FAQ sets
- category filtering could help once the FAQ list grows
- import/export remains a useful future tool for content management
- active/inactive clarity is good, but could be even more visible in list rows
- testing a FAQ before activation would reduce mistakes
- CSV import from the old Q&A workflow would help content migration

## 5. Bot reply text gaps
The Burmese reply copy is stable, but still worth a polish pass:

- ensure wording stays consistent across greeting, FAQ, image, payment, and duplicate flows
- keep responses clear and short enough for mobile
- avoid over-promising action the backend does not yet perform
- payment approved wording should not imply subscription extension
- duplicate payment wording should be calm and firm, not repetitive
- image non-payment wording should remain neutral if it is ever surfaced more explicitly

## 6. Operational gaps
The MVP is functional, but these operational pieces are still missing:

- no auth/login yet
- no rate limiting yet
- no queue retry yet
- no webhook signature validation yet
- no channel adapter abstraction yet
- no Viber support yet
- no Messenger support yet
- no backup/import tooling for support data

These are not all urgent before F9, but auth/login should happen before production exposure.

## 7. Recommended polish tasks

### P9A Conversation UI final polish
- Why: makes the main dashboard feel more coherent before adding AI knowledge features
- Risk: low
- Suggested agent: opencode
- Estimated scope: UI only
- Manual test needed: yes

### P9B Payment status wording polish
- Why: reduces confusion in the most sensitive support flow
- Risk: low
- Suggested agent: opencode
- Estimated scope: UI only
- Manual test needed: yes

### P9C FAQ search/filter
- Why: helps admins manage FAQ content as the list grows
- Risk: medium
- Suggested agent: opencode
- Estimated scope: UI only or very small backend support
- Manual test needed: yes

### P9D FAQ CSV import plan
- Why: useful for moving old Q&A content into the new dashboard workflow
- Risk: medium
- Suggested agent: Codex
- Estimated scope: docs only
- Manual test needed: no

### P9E Payment reject reason note
- Why: improves support context without changing the core payment lifecycle
- Risk: medium
- Suggested agent: opencode
- Estimated scope: backend small
- Manual test needed: yes

### P9F Customer notification after approve/reject plan
- Why: clarifies support outcomes for the customer
- Risk: medium
- Suggested agent: Codex
- Estimated scope: docs only
- Manual test needed: no

### P9G Dashboard list filters
- Why: helps support agents scan larger queues more quickly
- Risk: low
- Suggested agent: opencode
- Estimated scope: UI only
- Manual test needed: yes

### P9H Auth before production plan
- Why: needed before public exposure of the dashboard
- Risk: medium
- Suggested agent: Codex
- Estimated scope: docs only
- Manual test needed: no

## 8. Recommendation
Recommended next implementation task: **P9B Payment status wording polish**

Why this one:
- it is small
- it is low risk
- it improves the most sensitive user-facing flow
- it does not block F9 later
- it keeps the payment UX from feeling ambiguous before adding a knowledge bot

If the team wants the dashboard itself to feel calmer first, the close second choice is P9A Conversation UI final polish.
