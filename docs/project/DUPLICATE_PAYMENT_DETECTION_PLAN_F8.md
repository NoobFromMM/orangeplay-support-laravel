# F8 Duplicate Payment Detection Plan

## 1. Purpose
F8 adds safe duplicate handling for payment screenshots so the system does not create multiple `payment_cases` for the same real-world payment.

This matters because:
- the same customer may resend a screenshot
- the same transaction may be seen again after approval, rejection, or while still under review
- payment screenshots can be noisy, slow, or re-sent during support follow-up
- duplicate handling protects support review clarity before later subscription automation

The goal is not to change payment approval behavior. The goal is to prevent duplicate records, preserve audit history, and give the customer a clear response when the system has already seen the same payment.

## 2. Current payment lifecycle
Current flow is:

1. Telegram image arrives
2. Laravel stores the webhook event and the image message
3. The payment screenshot worker check runs
4. Worker output derives `is_payment`, `provider`, `transaction_id`, and related fields
5. If payment is detected, Laravel creates a `payment_case`
6. If email is missing, Laravel asks for email and sets the case to `needs_email`
7. When email arrives, the case moves to `pending_review`
8. Dashboard review card shows the payment case
9. Admin can approve or reject the case
10. Status changes are recorded with `payment_status_update`

The current lifecycle is good for the first pass, but it still needs a duplicate guard before creating a new case.

## 3. Duplicate definition
A duplicate should mean “we already have a known payment case or payment attempt for this payment evidence.”

Duplicate scenarios to cover:
- same `transaction_id` from the same customer
- same `transaction_id` from a different customer
- same provider plus `transaction_id`
- missing `transaction_id` but same Telegram `file_unique_id`
- different `transaction_id` but the same screenshot is resent
- a transaction already approved or rejected is sent again
- a transaction still in `needs_email` or `pending_review` is sent again

The safest approach is to treat the existing payment case as the source of truth and attach the resend to that case instead of creating a second one.

## 4. Status-specific behavior

### A. Existing `needs_email`
Do not create a new `payment_case`.

Bot reply:
`ဒီငွေလွှဲ Screenshot ကို လက်ခံထားပြီးပါပြီရှင့်။ သက်တမ်းတိုးမယ့် Orange Play account Email ကို ပို့ပေးပါရှင့်။`

Also log a duplicate attempt timeline message.

### B. Existing `pending_review`
Do not create a new `payment_case`.

Bot reply:
`ဒီငွေလွှဲ Screenshot ကို လက်ခံထားပြီး Admin Team စစ်ဆေးနေပါပြီရှင့်။`

Also log a duplicate attempt timeline message.

### C. Existing `approved`
Do not create a new `payment_case`.

Bot reply:
`ဒီငွေလွှဲ Screenshot ကို စစ်ဆေးအတည်ပြုပြီးသား ဖြစ်ပါတယ်ရှင့်။`

Also log a duplicate attempt timeline message.

### D. Existing `rejected`
Do not create a new `payment_case` for the same transaction evidence.

Bot reply:
`ဒီငွေလွှဲ Screenshot ကို အတည်မပြုနိုင်ခဲ့ပါရှင့်။ မှန်ကန်တဲ့ Screenshot အသစ်ကို ပြန်ပို့ပေးပါရှင့်။`

Also log a duplicate attempt timeline message.

This keeps the customer message consistent and makes the support trail easier to read.

## 5. Data model needs
Current `payment_cases.transaction_id` is indexed, but it is not unique.

That is acceptable for the first implementation because:
- `transaction_id` can be null
- worker extraction can improve later
- status-specific handling needs to stay flexible

Recommended first step:
- keep duplicate detection in service logic
- normalize `provider` and `transaction_id` before lookup
- check the latest matching case first
- optionally narrow to the same customer when appropriate, but keep a global fallback

Possible future fields, not required yet:
- `duplicate_of_payment_case_id`
- `duplicate_attempts_count`
- `external_image_unique_id`

## 6. Service design
Recommended design:
- add `DuplicatePaymentDetector`, or extend `PaymentScreenshotService` with duplicate lookup
- before creating a new `payment_case`, search for an existing case by normalized `provider` and `transaction_id`
- if `transaction_id` is missing, optionally compare Telegram `file_unique_id` from image metadata
- return a result object that makes the branch explicit:
  - `created_case`
  - `duplicate_case`
  - `action_taken`

The service should decide whether to create a new case, attach to an existing one, or send a duplicate response without touching unrelated payment behavior.

## 7. Timeline and message design
When a duplicate is detected:
- create a system timeline message
- use `message_type = payment_duplicate_notice`
- use `sender_type = system`
- set metadata such as:
  - `duplicate_of_payment_case_id`
  - `duplicate_status`
  - `transaction_id`
  - `provider`
  - `image_message_id`

Also save the outbound bot message with metadata such as:
- `event = payment_duplicate_detected`
- `duplicate_of_payment_case_id`
- `duplicate_status`

This keeps the duplicate trail visible in the conversation without inventing new business states too early.

## 8. Smoke tests
Recommended smoke command:

`php artisan smoke:payment-duplicate`

Suggested coverage:
- existing `needs_email` case receives duplicate screenshot
- existing `pending_review` case receives duplicate screenshot
- existing `approved` case receives duplicate screenshot
- existing `rejected` case receives duplicate screenshot
- different `transaction_id` still creates a new case normally
- null `transaction_id` does not incorrectly block unrelated payments

Expected outcomes:
- no extra `payment_case` for duplicates
- duplicate timeline message is created
- customer gets the correct status-specific reply

## 9. UI
The dashboard can eventually show duplicate attempt history on the payment card, but that is not required for the first implementation.

For F8A, backend behavior and smoke coverage are enough.

## 10. Risks
Main risks:
- false duplicate if `transaction_id` extraction is wrong
- formatting differences in `transaction_id`
- same transaction reused fraudulently across customers
- null `transaction_id` edge cases
- customer confusion if duplicate replies are not clear

Mitigation:
- normalize before comparing
- keep service-level lookup first
- preserve timeline evidence
- avoid a hard unique constraint until behavior is well understood

## 11. Recommended implementation phases

### F8A
Duplicate detection service and smoke coverage only, no UI change.

Likely files:
- payment screenshot service or a small duplicate detector service
- smoke command for duplicate handling

Tests:
- `php artisan test`
- `php artisan smoke:payment-duplicate`
- `php artisan smoke:locked`

Manual test:
- resend the same screenshot and confirm no new case is created

Risk level:
- medium, because behavior branches by existing case state

### F8B
Wire duplicate detection into the webhook path and send the duplicate bot replies.

Likely files:
- Telegram webhook processing path
- payment screenshot service
- message/timeline creation

Tests:
- `php artisan test`
- `php artisan smoke:payment-duplicate`
- `php artisan smoke:locked`

Manual test:
- resend the same payment screenshot in Telegram and verify the right message

Risk level:
- medium to high, because this affects customer-facing behavior

### F8C
Dashboard display polish for duplicate attempts.

Likely files:
- dashboard conversation view
- payment card partials if any

Tests:
- `php artisan test`
- `php artisan smoke:locked`

Manual test:
- inspect duplicate trail in the dashboard

Risk level:
- low

### F8D
Lock duplicate handling after manual confirmation.

## 12. Recommendation
Recommended next task: **F8A and F8B together only if kept narrow; otherwise split them.**

The reason is simple: duplicate detection is only useful when it runs in the webhook path, but the first implementation should still stay small enough to verify safely.

Preferred order if split:
1. build the duplicate detection service and smoke test
2. wire it into the webhook path
3. manually resend a screenshot
4. then lock it
