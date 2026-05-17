<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramFileController extends Controller
{
    public function show(string $fileId)
    {
        $token = env('TELEGRAM_BOT_TOKEN');

        if (empty($token)) {
            return response('Not configured', 500);
        }

        $response = Http::timeout(15)->get("https://api.telegram.org/bot{$token}/getFile", [
            'file_id' => $fileId,
        ]);

        if (! $response->successful() || ! ($response->json('ok') ?? false)) {
            Log::warning('Telegram getFile failed', [
                'file_id' => $fileId,
                'status' => $response->status(),
            ]);
            return response('File not found', 404);
        }

        $filePath = $response->json('result.file_path');

        if (empty($filePath)) {
            return response('File path missing', 502);
        }

        $fileResponse = Http::timeout(30)->get("https://api.telegram.org/file/bot{$token}/{$filePath}");

        if (! $fileResponse->successful()) {
            return response('File download failed', 502);
        }

        $contentType = $fileResponse->header('Content-Type') ?? 'image/jpeg';

        return response($fileResponse->body(), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
