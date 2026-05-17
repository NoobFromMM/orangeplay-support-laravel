<?php

namespace App\Console\Commands;

use App\Models\FaqEntry;
use App\Models\Message;
use App\Services\Support\ConversationService;
use App\Services\Support\FaqMatcher;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramUpdateNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokeF2 extends Command
{
    protected $signature = 'smoke:f2';
    protected $description = 'Smoke test for F2 DB FAQ auto replies';

    public function handle(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
    ): int {
        $this->info('F2 Smoke Test — DB FAQ Auto Replies');
        $this->info('====================================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->seedFaqEntries();

            $testCases = [
                ['input' => 'hi', 'expectedCategory' => 'greeting', 'expectedStatus' => 'resolved'],
                ['input' => 'hello', 'expectedCategory' => 'greeting', 'expectedStatus' => 'resolved'],
                ['input' => 'တစ်လဘယ်လောက်လဲ', 'expectedCategory' => 'pricing', 'expectedStatus' => 'resolved'],
                ['input' => 'သက်တမ်းတိုးချင်လို့', 'expectedCategory' => 'pricing', 'expectedStatus' => 'resolved'],
                ['input' => 'သက်တန်းတိုးချင်လို့', 'expectedCategory' => 'pricing', 'expectedStatus' => 'resolved'],
                ['input' => 'မင်ဘာဝင်ချင်တယ်', 'expectedCategory' => 'pricing', 'expectedStatus' => 'resolved'],
                ['input' => 'member ဝင်ချင်တယ်', 'expectedCategory' => 'pricing', 'expectedStatus' => 'resolved'],
                ['input' => 'kpay နံပါတ်ပေးပါ', 'expectedCategory' => 'payment', 'expectedStatus' => 'resolved'],
                ['input' => 'ငွေလွှဲမယ်', 'expectedCategory' => 'payment', 'expectedStatus' => 'resolved'],
                ['input' => 'xyzzy123blah', 'expectedCategory' => null, 'expectedStatus' => 'Needs Reply'],
            ];

            foreach ($testCases as $i => $tc) {
                $idx = $i + 1;
                $payload = $this->mockTelegramPayload($tc['input'], 10000 + $idx);

                $normalized = $normalizer->normalize($payload);

                $customer = $conversationService->findOrCreateCustomer(
                    $normalized['platform'],
                    $normalized['platform_user_id'],
                    [
                        'display_name' => $normalized['display_name'],
                        'username' => $normalized['username'],
                    ]
                );

                $conversation = $conversationService->findOrCreateConversation($customer);
                $conversationService->saveInboundMessage($conversation, $normalized);

                $matchedEntry = $faqMatcher->match($normalized['text']);

                if ($matchedEntry) {
                    $conversationService->saveOutboundMessage(
                        $conversation,
                        $normalized['platform'],
                        $matchedEntry->answer_text
                    );
                    $conversationService->setStatus($conversation, 'resolved');
                } else {
                    $conversationService->setStatus($conversation, 'Needs Reply');
                }

                $this->runAssertions(
                    $tc['input'],
                    $tc['expectedCategory'],
                    $tc['expectedStatus'],
                    $conversation,
                    $matchedEntry,
                    $errors
                );
            }

        } catch (\Throwable $e) {
            $errors[] = "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString();
        }

        DB::rollBack();

        if (empty($errors)) {
            $this->info('ALL ASSERTIONS PASSED');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->error('FAILURES:');
        foreach ($errors as $error) {
            $this->line("  ✗  {$error}");
        }
        $this->newLine();
        return self::FAILURE;
    }

    protected function seedFaqEntries(): void
    {
        FaqEntry::updateOrCreate(
            ['intent_code' => 'greeting'],
            [
                'category' => 'greeting',
                'keywords' => [
                    'hi', 'hello', 'hey', 'mingalarbar',
                    'မင်္ဂလာပါ', 'မင်လာပါ', 'ဟိုင်း',
                ],
                'answer_text' => "မင်္ဂလာပါရှင့် Orange Play Customer Service မှကြိုဆိုပါတယ်။ဘာများကူညီပေးရမလဲရှင့်",
                'priority' => 100,
                'is_active' => true,
            ]
        );

        FaqEntry::updateOrCreate(
            ['intent_code' => 'pricing'],
            [
                'category' => 'pricing',
                'keywords' => [
                    'ဘယ်လောက်လဲ', 'သက်တမ်းတိုး', 'သက်တန်းတိုး',
                    'မင်ဘာဝင်', 'ဝင်ချင်တယ်',
                    'member', 'package', 'plan', 'price', 'renew', 'vip',
                    'ပက်ကေ့',
                ],
                'answer_text' => "✅ ၁လ - ၅၀၀၀ ကျပ် (2 Devices)\n\n✅ ၃လ - ၁၃၀၀၀ ကျပ် (2 Devices)\n\n✅ ၆လ - ၂၅၀၀၀ ကျပ် (2 Devices)\n\n✅ ၁နှစ် - ၄၅၀၀၀ ကျပ် (2 Devices)\n\n✅ VIP ၁နှစ် - ၆၀၀၀၀ ကျပ် (3 Devices)\n\nသက်တမ်းတိုးရန် ငွေလွှဲပြီး Screenshot (SS) ပို့ပေးနိုင်ပါတယ်ရှင့်။",
                'priority' => 90,
                'is_active' => true,
            ]
        );

        FaqEntry::updateOrCreate(
            ['intent_code' => 'payment_account'],
            [
                'category' => 'payment',
                'keywords' => [
                    'kpay', 'wave', 'ငွေလွှဲ', 'ဘယ်ကိုလွှဲ',
                    'payment number', 'pay လုပ်', 'kbz',
                    'aya pay', 'cb pay',
                ],
                'answer_text' => "✅ Kpay / Wave Money / AYA Pay / CB Pay - 09964349887\n(Name - Su Su Hlaing)\n\nငွေလွှဲ Screenshot (SS) ပို့ပေးပါရှင့်။ SS ရပြီးနောက် ဘယ် Email ကိုတိုးချင်တာလဲ ပြန်မေးပါမယ်ရှင့်။",
                'priority' => 80,
                'is_active' => true,
            ]
        );
    }

    protected function mockTelegramPayload(string $text, int $userId): array
    {
        return [
            'update_id' => 999999 + $userId,
            'message' => [
                'message_id' => $userId,
                'from' => [
                    'id' => $userId,
                    'is_bot' => false,
                    'first_name' => 'TestUser' . $userId,
                    'username' => 'testuser' . $userId,
                ],
                'chat' => [
                    'id' => $userId,
                    'first_name' => 'TestUser' . $userId,
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'text' => $text,
            ],
        ];
    }

    protected function runAssertions(
        string $input,
        ?string $expectedCategory,
        string $expectedStatus,
        $conversation,
        ?FaqEntry $matchedEntry,
        array &$errors,
    ): void {
        $label = "{$input}";

        if ($conversation->fresh()->status !== $expectedStatus) {
            $errors[] = "{$label} — expected status='{$expectedStatus}', got '{$conversation->fresh()->status}'";
            return;
        }

        if ($expectedCategory === null) {
            $this->info("  OK  {$label} → no match, status='{$expectedStatus}'");

            $outboundMessages = Message::where('conversation_id', $conversation->id)
                ->where('direction', 'outbound')
                ->count();

            if ($outboundMessages > 0) {
                $errors[] = "{$label} — expected no outbound message, got {$outboundMessages}";
            } else {
                $this->info('  OK  No outbound bot reply for unknown input');
            }

            return;
        }

        $replyText = $matchedEntry->answer_text ?? '';

        if (str_contains((string) $replyText, 'OrangePlayAI')) {
            $errors[] = "{$label} — reply contains banned phrase 'OrangePlayAI'";
        }

        if (str_contains((string) $replyText, 'Support Bot')) {
            $errors[] = "{$label} — reply contains banned phrase 'Support Bot'";
        }

        if ($expectedCategory === 'pricing') {
            if (! str_contains((string) $replyText, '၁လ')) {
                $errors[] = "{$label} — pricing reply missing '၁လ'";
            }
            if (! str_contains((string) $replyText, '၅၀၀၀')) {
                $errors[] = "{$label} — pricing reply missing '၅၀၀၀'";
            }
            if (str_contains((string) $replyText, '09964349887')) {
                $errors[] = "{$label} — pricing reply should NOT contain payment account number";
            }
        }

        if ($expectedCategory === 'payment') {
            if (! str_contains((string) $replyText, '09964349887')) {
                $errors[] = "{$label} — payment reply missing account number '09964349887'";
            }
            if (! str_contains((string) $replyText, 'Kpay')) {
                $errors[] = "{$label} — payment reply missing 'Kpay'";
            }
        }

        $this->info("  OK  {$label} → {$expectedCategory} reply, status='{$expectedStatus}'");
    }
}
