# OrangePlay Support — Architecture

## Preferred Architecture
Use Laravel as the single source of truth.

No n8n.
No Next.js.
No Prisma.

Laravel owns:
- webhook endpoints
- channel adapters
- database writes
- FAQ logic
- payment/support logic
- dashboard UI
- outbound replies

## Suggested Stack
- Laravel
- Livewire or Blade
- Tailwind CSS
- MySQL/MariaDB
- Laravel Queue
- Telegram Bot API first
- Viber/Messenger later

## Main Tables
Suggested tables:
- customers
- conversations
- messages
- payment_cases
- support_cases
- admin_notes

## Messages Table Principle
Messages are the main timeline.

Suggested fields:
- id
- conversation_id
- customer_id
- platform
- direction: inbound/outbound/system
- sender_type: customer/bot/admin/system
- message_type: text/image/payment_card/system
- text nullable
- file_id nullable
- raw_payload json nullable
- metadata json nullable
- created_at
- updated_at

## Label Logic
Early labels:
- Resolved: bot handled or no action needed
- Needs Reply: customer needs human response
- In Chat: admin is handling/waiting for customer
- Payment: payment case/action needed

Keep label logic in one service/class and test it.

## Channel Services
Use channel service classes:
- TelegramChannelService
- ViberChannelService later
- MessengerChannelService later

All services should normalize messages into the same internal shape.
