# Payment Policy Revision Plan

## 1. New policy
Final intended policy:

- `transaction_id` is the duplicate key
- provider is metadata only, not part of duplicate matching
- each customer can have multiple emails
- email can be captured at any time, even before a payment screenshot
- one customer can have only one open payment case at a time
- a screenshot without `transaction_id` is incomplete and must not create a payment case
- after payment screenshot, the bot should still ask which email or account should be extended

This plan keeps the current payment lifecycle intact while tightening the rules around duplicate handling, email capture, and open-case blocking.

## 2. Current behavior vs desired behavior

### Current behavior
- email is mainly attached when a payment case is already open in `needs_email` or `pending_review`
- payment cases can still be created again when the `transaction_id` differs
- there is no dedicated `customer_emails` table yet
- a screenshot with missing `transaction_id` may still be treated too leniently in some paths
- duplicate handling is transaction-based, but it does not yet enforce a single open case per customer

### Desired behavior
- any valid email text from the customer should be stored immediately
- payment case flow should always ask which email/account to extend after a payment screenshot
- only one open payment case should exist per customer at a time
- a new screenshot while an open case exists should not create a second case
- missing `transaction_id` should be treated as incomplete and should not create a case
- provider must remain visible as metadata, but not as the duplicate key

## 3. Data model

### Recommended new table
`customer_emails`

Fields:
- `id`
- `customer_id`
- `email`
- `source` (`telegram_text`, `dashboard`, `import`)
- `first_seen_at`
- `last_seen_at`
- `is_primary` nullable or default `false`
- `metadata` json nullable
- timestamps

Constraints:
- unique index on `customer_id + email`
- normalize email to lowercase before storage or comparison

### Payment case support
- keep `payment_cases.customer_email` as the selected email for that case
- do not remove the existing column
- a future `selected_customer_email_id` can be added later if needed

No destructive migration is part of this plan.

## 4. Open payment case definition

Open payment case statuses:
- `needs_email`
- `pending_review`

Terminal statuses:
- `approved`
- `rejected`

Rule:
- if a customer already has an open case and sends another payment screenshot, do not create a new `payment_case`
- save the image message
- log a system message `payment_open_case_exists`
- reply:

```text
လက်ရှိ စစ်ဆေးနေတဲ့ ငွေလွှဲ Case ရှိနေပါသေးတယ်ရှင့်။ အရင် Case ပြီးမှ Screenshot အသစ်ကို စစ်ဆေးပေးပါမယ်။
```

- if the open case is still `needs_email`, optionally add:

```text
သက်တမ်းတိုးမယ့် Orange Play account Email ကို ပို့ပေးပါရှင့်။
```

This keeps the customer on one active payment thread at a time.

## 5. Missing transaction_id policy

If the Worker says `is_payment=true` but `transaction_id` is missing or invalid:

- do not create a `payment_case`
- save the payment check metadata for debugging
- create a system message `payment_incomplete`
- reply:

```text
Payment Screenshot ထဲမှာ ငွေလွှဲအချက်အလက် မပြည့်စုံပါရှင့်။ Transaction ID ပါတဲ့ Screenshot အသစ်ကို ပြန်ပို့ပေးပါရှင့်။
```

- conversation status should remain `Needs Reply` or a similar incomplete state

This prevents weak OCR results from creating half-valid payment records.

## 6. Email capture policy

For any inbound text message that is a valid email:

- always upsert into `customer_emails`
- if there is an open payment case:
  - set `payment_cases.customer_email` if it is still empty
  - if the case is `needs_email`, move it to `pending_review`
  - if the case is already `pending_review`, keep it there
  - send the existing `payment_email_received` confirmation once per attached case
- if there is no open payment case:
  - store the email only
  - do not create a payment confirmation message
  - avoid extra noise unless the product intentionally wants a general email acknowledgement later

Multiple emails:
- store each unique email separately
- never overwrite the customer’s email history
- `payment_cases.customer_email` is only the selected email for that one case

## 7. Processing priority

### Text messages
Recommended order:
1. save inbound message
2. capture valid email into `customer_emails`
3. if an open payment case exists, attach the email to that case
4. otherwise try FAQ match
5. otherwise leave as `Needs Reply`

### Image messages
Recommended order:
1. save image message
2. run `payment_check`
3. if non-payment, keep current image behavior
4. if payment but `transaction_id` is missing, reply with the incomplete-payment message
5. if the customer already has an open payment case, block new case creation and reply that an earlier case is still open
6. if `transaction_id` duplicates an existing case, keep the existing duplicate handling
7. otherwise create the new case and still ask which email/account should be extended

Priority note:
- open-case blocking should happen before creating a new payment case
- duplicate-by-`transaction_id` should still catch old terminal cases and historical cases

## 8. Smoke tests

Recommended smoke command:

```bash
php artisan smoke:payment-policy
```

Suggested test cases:

- email before screenshot
  - email is saved to `customer_emails`
  - no payment confirmation is sent if there is no open case

- screenshot after known email
  - payment case is created
  - bot still asks which email/account to extend
  - email history exists

- open case blocks new screenshot
  - existing `needs_email` or `pending_review` case
  - new payment screenshot with different `transaction_id`
  - no new case
  - bot says an open case already exists

- missing `transaction_id`
  - no payment case
  - incomplete screenshot reply is sent

- email after `needs_email`
  - email is saved to `customer_emails`
  - case gets the email
  - status moves to `pending_review`

- email after `pending_review`
  - email is saved and attached
  - status stays `pending_review`

- approved case then new screenshot
  - if no open case exists and `transaction_id` is not a duplicate, a new case can be created

- duplicate transaction approved/rejected
  - existing duplicate behavior still works

## 9. Implementation sequence

### P10A
Plan only.

### P10B
Add `customer_emails` table/model and email capture service only, with smoke coverage.

Likely files:
- `database/migrations/...customer_emails...`
- `app/Models/CustomerEmail.php`
- small email capture service or helper
- smoke command

### P10C
Add open payment case policy service and missing `transaction_id` handling.

Likely files:
- payment screenshot flow
- helper service for open-case detection
- bot reply path

### P10D
Wire the policy into the Telegram webhook path and add `smoke:payment-policy`.

### P10E
Manual Telegram evidence.

### P10F
Lock after manual confirmation.

## 10. Risks

Main risks:
- multiple emails can cause confusion about which account should be extended
- users may expect a bot reply even when no open payment case exists
- blocking a second screenshot may frustrate customers who sent a real second payment
- OCR or extraction can miss `transaction_id`
- the one-open-case rule may collide with legitimate back-to-back payments
- older open cases in the database may need cleanup or migration-aware handling

Mitigation:
- keep the selected email per case separate from email history
- be clear about the bot reply when a case is already open
- preserve raw payment metadata for debugging
- avoid destructive migration changes
- handle legacy data conservatively

## 11. Recommendation
Recommended next implementation task:

**P10B — customer_emails table/model plus email capture service only, with no payment policy behavior changes yet.**

Why:
- it is the smallest safe step toward the new policy
- it gives us the email history foundation before changing screenshot behavior
- it lowers risk before we enforce the one-open-case rule

If the team wants the behavior fix immediately, split the work:
1. build `customer_emails`
2. then add open-case blocking and missing `transaction_id` handling

## Locked Gate Reminder
Before any future commit in this repo, run:

```bash
php artisan test
php artisan smoke:locked
```

Do not lock new payment policy behavior until manual Telegram verification is complete.
