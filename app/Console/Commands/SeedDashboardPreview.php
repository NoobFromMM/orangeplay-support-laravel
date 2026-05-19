<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FaqEntry;
use App\Models\Message;
use App\Models\PaymentCase;
use Illuminate\Console\Command;

class SeedDashboardPreview extends Command
{
    protected $signature = 'seed:dashboard-preview';
    protected $description = 'Create 4 preview customers for dashboard UI testing';

    public function handle(): int
    {
        $this->info('Seeding dashboard preview data...');
        $this->newLine();

        $counts = ['customers' => 0, 'conversations' => 0, 'messages' => 0, 'payment_cases' => 0];

        // Ensure greeting FAQ exists for Resolved preview
        $this->ensureFaqEntry();

        // 1. Needs Reply
        $this->createNeedsReply($counts);

        // 2. In Chat
        $this->createInChat($counts);

        // 3. Resolved
        $this->createResolved($counts);

        // 4. Payment Review
        $this->createPaymentReview($counts);

        $this->newLine();
        $this->info("Preview data created/updated:");
        $this->line("  Customers: {$counts['customers']}");
        $this->line("  Conversations: {$counts['conversations']}");
        $this->line("  Messages: {$counts['messages']}");
        $this->line("  Payment Cases: {$counts['payment_cases']}");
        $this->newLine();
        $this->line("Dashboard: /dashboard");
        $this->line("Payment Preview: /customers/telegram/preview_payment_review");

        return self::SUCCESS;
    }

    protected function createNeedsReply(array &$counts): void
    {
        $customer = Customer::updateOrCreate(
            ['platform' => 'telegram', 'platform_user_id' => 'preview_needs_reply'],
            ['display_name' => '[Preview] Needs Reply', 'username' => 'preview_needs_reply']
        );
        $counts['customers']++;

        $conv = Conversation::updateOrCreate(
            ['customer_id' => $customer->id, 'status' => 'Needs Reply'],
            ['last_message_at' => now()]
        );
        $counts['conversations']++;

        Message::updateOrCreate(
            ['conversation_id' => $conv->id, 'customer_id' => $customer->id, 'text' => "App ထဲဝင်မရဘူးရှင့်"],
            [
                'platform' => 'telegram', 'direction' => 'inbound', 'sender_type' => 'customer',
                'message_type' => 'text', 'created_at' => now()->subMinutes(5),
            ]
        );
        $counts['messages']++;

        $this->info('  OK  [Preview] Needs Reply');
    }

    protected function createInChat(array &$counts): void
    {
        $customer = Customer::updateOrCreate(
            ['platform' => 'telegram', 'platform_user_id' => 'preview_in_chat'],
            ['display_name' => '[Preview] In Chat', 'username' => 'preview_in_chat']
        );
        $counts['customers']++;

        $conv = Conversation::updateOrCreate(
            ['customer_id' => $customer->id, 'status' => 'in_chat'],
            ['last_message_at' => now()]
        );
        $counts['conversations']++;

        Message::updateOrCreate(
            ['conversation_id' => $conv->id, 'customer_id' => $customer->id, 'text' => "App ထဲလော့ဂ်အင်မဝင်ဘူး"],
            [
                'platform' => 'telegram', 'direction' => 'inbound', 'sender_type' => 'customer',
                'message_type' => 'text', 'created_at' => now()->subMinutes(10),
            ]
        );
        $counts['messages']++;

        Message::updateOrCreate(
            ['conversation_id' => $conv->id, 'customer_id' => $customer->id, 'text' => "စမ်းကြည့်ပါရှင့်။ password reset လုပ်ပြီး ပြန်ဝင်ကြည့်ပါ။"],
            [
                'platform' => 'telegram', 'direction' => 'outbound', 'sender_type' => 'admin',
                'message_type' => 'text', 'created_at' => now()->subMinutes(8),
                'metadata' => json_encode(['source' => 'dashboard']),
            ]
        );
        $counts['messages']++;

        $this->info('  OK  [Preview] In Chat');
    }

    protected function createResolved(array &$counts): void
    {
        $customer = Customer::updateOrCreate(
            ['platform' => 'telegram', 'platform_user_id' => 'preview_resolved'],
            ['display_name' => '[Preview] Resolved', 'username' => 'preview_resolved']
        );
        $counts['customers']++;

        $conv = Conversation::updateOrCreate(
            ['customer_id' => $customer->id, 'status' => 'resolved'],
            ['last_message_at' => now()]
        );
        $counts['conversations']++;

        Message::updateOrCreate(
            ['conversation_id' => $conv->id, 'customer_id' => $customer->id, 'text' => 'hello'],
            [
                'platform' => 'telegram', 'direction' => 'inbound', 'sender_type' => 'customer',
                'message_type' => 'text', 'created_at' => now()->subMinutes(15),
            ]
        );

        $replyText = "မင်္ဂလာပါရှင့် Orange Play Customer Service မှကြိုဆိုပါတယ်။ဘာများကူညီပေးရမလဲရှင့်";
        Message::updateOrCreate(
            ['conversation_id' => $conv->id, 'customer_id' => $customer->id, 'text' => $replyText],
            [
                'platform' => 'telegram', 'direction' => 'outbound', 'sender_type' => 'bot',
                'message_type' => 'text', 'created_at' => now()->subMinutes(14),
            ]
        );
        $counts['messages'] += 2;

        $this->info('  OK  [Preview] Resolved');
    }

    protected function createPaymentReview(array &$counts): void
    {
        $customer = Customer::updateOrCreate(
            ['platform' => 'telegram', 'platform_user_id' => 'preview_payment_review'],
            ['display_name' => '[Preview] Payment Review', 'username' => 'preview_payment_review']
        );
        $counts['customers']++;

        $conv = Conversation::updateOrCreate(
            ['customer_id' => $customer->id, 'status' => 'Needs Reply'],
            ['last_message_at' => now()]
        );
        $counts['conversations']++;

        $now = now();

        // 1. Customer sends image
        Message::updateOrCreate(
            ['conversation_id' => $conv->id, 'customer_id' => $customer->id, 'text' => null, 'message_type' => 'image'],
            [
                'platform' => 'telegram', 'direction' => 'inbound', 'sender_type' => 'customer',
                'metadata' => json_encode(['telegram_file_id' => 'preview_file_id', 'width' => 800, 'height' => 600]),
                'created_at' => $now->copy()->subMinutes(60),
            ]
        );
        $counts['messages']++;

        // 2. payment_review_card + needs_email
        $needsEmailCase = PaymentCase::create([
            'customer_id' => $customer->id, 'conversation_id' => $conv->id,
            'provider' => 'kbzpay', 'transaction_id' => 'PREVIEW-NEEDS-EMAIL',
            'status' => 'needs_email', 'created_at' => $now->copy()->subMinutes(59),
        ]);
        $counts['payment_cases']++;

        Message::create([
            'conversation_id' => $conv->id, 'customer_id' => $customer->id,
            'platform' => 'telegram', 'direction' => 'system', 'sender_type' => 'system',
            'message_type' => 'payment_review_card', 'text' => 'Payment screenshot detected',
            'metadata' => json_encode([
                'payment_case_id' => $needsEmailCase->id, 'provider' => 'kbzpay',
                'transaction_id' => 'PREVIEW-NEEDS-EMAIL',
            ]),
            'created_at' => $now->copy()->subMinutes(58),
        ]);
        $counts['messages']++;

        // 3. Bot asks email
        Message::create([
            'conversation_id' => $conv->id, 'customer_id' => $customer->id,
            'platform' => 'telegram', 'direction' => 'outbound', 'sender_type' => 'bot',
            'message_type' => 'text',
            'text' => 'Payment Screenshot ရရှိပါပြီရှင့်။ ဘယ် Orange Play account Email ကို သက်တမ်းတိုးချင်တာလဲ ပို့ပေးပါရှင့်။',
            'metadata' => json_encode(['source' => 'telegram_bot', 'event' => 'ask_email_after_payment', 'payment_case_id' => $needsEmailCase->id]),
            'created_at' => $now->copy()->subMinutes(57),
        ]);
        $counts['messages']++;

        // 4. Customer sends email
        $needsEmailCase->update(['customer_email' => 'preview@orangeplay.com', 'status' => 'pending_review']);

        Message::create([
            'conversation_id' => $conv->id, 'customer_id' => $customer->id,
            'platform' => 'telegram', 'direction' => 'inbound', 'sender_type' => 'customer',
            'message_type' => 'text', 'text' => 'preview@orangeplay.com',
            'created_at' => $now->copy()->subMinutes(55),
        ]);
        $counts['messages']++;

        // 5. Bot confirms email received
        Message::create([
            'conversation_id' => $conv->id, 'customer_id' => $customer->id,
            'platform' => 'telegram', 'direction' => 'outbound', 'sender_type' => 'bot',
            'message_type' => 'text',
            'text' => 'Email ရရှိပါပြီရှင့်။ Admin Team မှ ငွေလွှဲအချက်အလက်ကို စစ်ဆေးပြီး ပြန်လည်ဆက်သွယ်ပေးပါမယ်။',
            'metadata' => json_encode(['source' => 'telegram_bot', 'event' => 'payment_email_received', 'payment_case_id' => $needsEmailCase->id, 'customer_email' => 'preview@orangeplay.com']),
            'created_at' => $now->copy()->subMinutes(54),
        ]);
        $counts['messages']++;

        // 6. Pending review card
        $pendingCase = PaymentCase::create([
            'customer_id' => $customer->id, 'conversation_id' => $conv->id,
            'provider' => 'wavepay', 'transaction_id' => 'PREVIEW-PENDING',
            'status' => 'pending_review', 'customer_email' => 'preview@orangeplay.com',
            'created_at' => $now->copy()->subMinutes(50),
        ]);
        $counts['payment_cases']++;

        Message::create([
            'conversation_id' => $conv->id, 'customer_id' => $customer->id,
            'platform' => 'telegram', 'direction' => 'system', 'sender_type' => 'system',
            'message_type' => 'payment_review_card', 'text' => 'Payment screenshot detected',
            'metadata' => json_encode([
                'payment_case_id' => $pendingCase->id, 'provider' => 'wavepay',
                'transaction_id' => 'PREVIEW-PENDING', 'customer_email' => 'preview@orangeplay.com',
            ]),
            'created_at' => $now->copy()->subMinutes(49),
        ]);
        $counts['messages']++;

        // 7. Approved case
        $approvedCase = PaymentCase::create([
            'customer_id' => $customer->id, 'conversation_id' => $conv->id,
            'provider' => 'ayapay', 'transaction_id' => 'PREVIEW-APPROVED',
            'status' => 'approved', 'customer_email' => 'preview@orangeplay.com',
            'reviewed_at' => $now->copy()->subMinutes(40),
            'created_at' => $now->copy()->subMinutes(45),
        ]);
        $counts['payment_cases']++;

        Message::create([
            'conversation_id' => $conv->id, 'customer_id' => $customer->id,
            'platform' => 'telegram', 'direction' => 'system', 'sender_type' => 'system',
            'message_type' => 'payment_status_update', 'text' => 'Payment review approved',
            'metadata' => json_encode([
                'payment_case_id' => $approvedCase->id, 'action' => 'approved',
                'old_status' => 'pending_review', 'new_status' => 'approved',
            ]),
            'created_at' => $now->copy()->subMinutes(40),
        ]);
        $counts['messages']++;

        // 8. Rejected case
        $rejectedCase = PaymentCase::create([
            'customer_id' => $customer->id, 'conversation_id' => $conv->id,
            'provider' => 'cbpay', 'transaction_id' => 'PREVIEW-REJECTED',
            'status' => 'rejected', 'customer_email' => 'preview@orangeplay.com',
            'reviewed_at' => $now->copy()->subMinutes(30),
            'created_at' => $now->copy()->subMinutes(35),
        ]);
        $counts['payment_cases']++;

        Message::create([
            'conversation_id' => $conv->id, 'customer_id' => $customer->id,
            'platform' => 'telegram', 'direction' => 'system', 'sender_type' => 'system',
            'message_type' => 'payment_status_update', 'text' => 'Payment review rejected',
            'metadata' => json_encode([
                'payment_case_id' => $rejectedCase->id, 'action' => 'rejected',
                'old_status' => 'pending_review', 'new_status' => 'rejected',
            ]),
            'created_at' => $now->copy()->subMinutes(30),
        ]);
        $counts['messages']++;

        // 9. Duplicate notice
        Message::create([
            'conversation_id' => $conv->id, 'customer_id' => $customer->id,
            'platform' => 'telegram', 'direction' => 'system', 'sender_type' => 'system',
            'message_type' => 'payment_duplicate_notice', 'text' => 'Duplicate payment screenshot detected',
            'metadata' => json_encode([
                'duplicate_of_payment_case_id' => $approvedCase->id,
                'duplicate_status' => 'approved',
                'provider' => 'kbzpay', 'transaction_id' => 'PREVIEW-APPROVED',
            ]),
            'created_at' => $now->copy()->subMinutes(20),
        ]);
        $counts['messages']++;

        $this->info('  OK  [Preview] Payment Review (4 cases, 8 messages)');
    }

    protected function ensureFaqEntry(): void
    {
        FaqEntry::firstOrCreate(
            ['intent_code' => 'greeting'],
            [
                'keywords' => json_encode(['hi', 'hello', 'hey']),
                'answer_text' => "မင်္ဂလာပါရှင့် Orange Play Customer Service မှကြိုဆိုပါတယ်။ဘာများကူညီပေးရမလဲရှင့်",
                'priority' => 100,
                'is_active' => true,
            ]
        );
    }
}
