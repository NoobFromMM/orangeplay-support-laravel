# F1 Telegram Hi Flow Prompt

Use this only after the Laravel project skeleton is ready.

```prompt
Implement F1 only: Telegram hi support flow.

Use AGENTS.md and project skills.

Behavior:
1. Telegram user sends hi.
2. Laravel webhook receives the update.
3. Normalize Telegram message.
4. Create/update customer.
5. Create/update conversation.
6. Store inbound message.
7. Reply exactly:
   မင်္ဂလာပါရှင့်။ Orange Play Support မှကြိုဆိုပါတယ်။ ဘာကူညီပေးရမလဲရှင့်။
8. Store outbound bot reply.
9. Dashboard shows customer with Resolved label.
10. Conversation timeline shows inbound hi and bot reply.

Do not add:
- payment
- images
- admin reply
- Viber
- Messenger
- n8n

Add smoke command:
php artisan smoke:f1

Smoke should simulate Telegram hi without sending real Telegram if possible.

Run:
php artisan test
php artisan smoke:f1

Do not push.

Final report:
- files changed
- migrations/models/routes
- smoke result
- manual test steps
- commit hash if committed
```
