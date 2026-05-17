<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Support\ConversationService;
use App\Services\Support\FaqMatcher;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramUpdateNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
        TelegramBotService $botService,
    ): JsonResponse {
        $update = $request->all();
        $normalized = $normalizer->normalize($update);

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
            $replyText = $matchedEntry->answer_text;

            $chatId = $normalized['platform_user_id'];
            $botService->sendMessage($chatId, $replyText);

            $conversationService->saveOutboundMessage(
                $conversation,
                $normalized['platform'],
                $replyText
            );

            $conversationService->setStatus($conversation, 'resolved');
        } else {
            $conversationService->setStatus($conversation, 'Needs Reply');
        }

        return response()->json(['ok' => true]);
    }
}
