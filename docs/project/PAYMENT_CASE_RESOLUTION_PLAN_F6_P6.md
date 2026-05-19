# F6 P6 Payment Case Resolution Plan

Date: 2026-05-19

This is a report-only implementation plan for payment case resolution. It does not change code, migrations, routes, services, or locked behavior.

## 1. Current Flow Summary

Current locked flow:
- customer sends a payment screenshot
- webhook event is logged
- image message is saved
- payment case is created when `is_payment=true`
- payment review card is shown in the timeline
- bot asks for email if the customer email is missing
- email reply attaches to the open payment case
- status becomes `pending_review`

At this stage, the payment case is ready for human review but not yet resolved by the dashboard.

## 2. P6 Goal

P6 adds admin resolution for `pending_review` payment cases.

The dashboard admin should be able to:
- approve a payment case
- reject a payment case
- save reviewer metadata
- notify the customer via Telegram
- save outbound bot/system/admin messages
- preserve an audit trail in the message timeline

This step is only about the review decision. It should not extend subscriptions yet.

## 3. Scope Boundaries

P6 should include:
- approve/reject buttons on the payment card
- POST endpoints for the actions
- status transitions
- Telegram notification messages
- DB timeline entries for the decision
- smoke tests for approve/reject behavior

P6 should not include yet:
- actual OrangePlay subscription extension
- package duration selection
- user account lookup
- payment reconciliation automation
- multi-agent auth/roles unless already present
- queues/retries

## 4. Status Transitions

Recommended safe state machine:
- `needs_email` → `pending_review`
- `pending_review` → `approved`
- `pending_review` → `rejected`
- `approved` and `rejected` are terminal for now

Behavior for edge transitions:
- if a case is `needs_email` and admin clicks approve, block safely and keep the status unchanged
- if a case is already approved or rejected, keep the state unchanged and return a safe idempotent response
- if `customer_email` is missing, approval should not proceed yet because the notification path is incomplete

Recommended review rule:
- only cases in `pending_review` should expose Approve / Reject actions
- other states should show status only

## 5. Dashboard UI Design

Plan for the payment review card:
- if status is `pending_review`, show Approve and Reject buttons
- if status is `needs_email`, show a `Waiting for Email` badge and no approve action
- if status is `approved`, show an `Approved` badge plus reviewer and `reviewed_at`
- if status is `rejected`, show a `Rejected` badge plus reviewer and `reviewed_at`

Button placement:
- inside the payment review card
- compact, clear, and secondary in visual weight
- use POST forms with CSRF protection

The card should remain a read-first review surface, not a large workflow panel.

## 6. Routes / Controllers

Recommended minimal route style:
- `POST /payments/{paymentCase}/approve`
- `POST /payments/{paymentCase}/reject`

This matches the current app style of short web routes and keeps the action endpoints obvious.

Controller options:
- use `DashboardController` methods first if the scope stays tiny
- use a dedicated `PaymentCaseController` only if it keeps the code cleaner without broadening the task

Smallest safe recommendation:
- keep the action handlers close to the existing dashboard flow unless there is a clear need to split them out

## 7. Notification Text

Approve reply:

```text
✅ Payment အတည်ပြုပြီးပါပြီရှင့်။ Admin Team မှ သက်တမ်းတိုးလုပ်ဆောင်ပေးနေပါတယ်။
```

Reject reply:

```text
ငွေလွှဲ Screenshot ကို အတည်မပြုနိုင်သေးပါရှင့်။ မှန်ကန်တဲ့ Screenshot ကို ပြန်ပို့ပေးပါရှင့်။
```

Clarification:
- these messages do not extend the subscription yet
- they only communicate the review decision
- the customer still needs the later subscription extension flow

## 8. Message Timeline

On approve/reject, the timeline should record the decision clearly.

Recommended entries:
- system/admin timeline message with:
  - `message_type = payment_status_update`
  - `sender_type = system` or `admin`
  - `direction = system`
  - `metadata.payment_case_id`
  - `metadata.action`
  - `metadata.old_status`
  - `metadata.new_status`

Outbound customer notification should also be saved as a message with:
- `metadata.event = payment_approved` or `payment_rejected`
- `metadata.payment_case_id`

This keeps the audit trail visible in the existing conversation timeline.

## 9. Smoke Tests

Suggested smoke command:

```bash
php artisan smoke:payment-resolution
```

Suggested test cases:

### A. Approve `pending_review`
- status becomes `approved`
- `reviewed_at` is set
- reviewer metadata is set
- bot notification is saved or faked
- timeline status update is saved

### B. Reject `pending_review`
- status becomes `rejected`
- bot notification is saved or faked
- timeline status update is saved

### C. Approve `needs_email`
- action is blocked safely
- status remains unchanged
- no approval message is sent

### D. Duplicate approve
- no duplicate bot message
- status stays stable
- response is idempotent or safely rejected

### E. Locked flows
- `php artisan smoke:payment-webhook` still passes
- `php artisan smoke:payment-email-attach` still passes

## 10. Risks

Key risks:
- approving before real subscription extension exists
- customer misunderstanding that approved means the subscription is already extended
- duplicate notifications on repeated clicks
- no auth/roles yet
- manual admin action without login protection
- later audit requirements may want stronger reviewer attribution

Mitigations:
- keep P6 limited to the review decision only
- make approve/reject idempotent
- show status clearly in the card
- defer subscription extension to a later phase
- do not add broader workflow automation here

## 11. Recommended Implementation Sequence

Keep the rollout narrow:

- P6A — add a resolution service plus smoke coverage only, no UI yet
- P6B — add dashboard POST actions and buttons
- P6C — manual test approve/reject
- P6D — lock the flow
- P7 — subscription extension integration later
- P8 — auth/roles later

## 12. Recommendation

Recommended next implementation task:

**P6A — PaymentCaseResolutionService plus `smoke:payment-resolution`, with no UI first.**

Why this is the safest next step:
- isolates decision logic before UI wiring
- avoids changing customer-facing behavior too early
- keeps the approval/rejection state machine easy to test
- protects the locked payment screenshot and email flows

## Locked Gate Reminder

Before any future commit in this repo, run:

```bash
php artisan test
php artisan smoke:locked
```

Do not lock a new feature until manual verification is complete.
