<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conversation - Orange Play</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-gray-100 text-gray-900">
    <div class="max-w-3xl mx-auto p-6">
        <div class="mb-6">
            <a href="/dashboard" class="text-blue-600 hover:underline text-sm">&larr; Back to Dashboard</a>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h1 class="text-xl font-bold">{{ $customer->display_name ?? $customer->platform_user_id }}</h1>
            <p class="text-gray-500 text-sm mt-1">
                @if ($customer->username)
                    @{{ $customer->username }} &middot;
                @endif
                {{ $customer->platform }}
                &middot;
                ID: {{ $customer->platform_user_id }}
            </p>
            @if ($conversation)
                <span class="inline-block mt-2 px-3 py-1 text-sm rounded-full
                    {{ $conversation->status === 'resolved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                    {{ $conversation->status }}
                </span>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Timeline</h2>
            @forelse ($messages as $message)
                <div class="mb-4 pb-4 border-b border-gray-100 last:border-0">
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="inline-block px-2 py-0.5 text-xs rounded
                                {{ $message->sender_type === 'customer' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                {{ $message->sender_type }}
                            </span>
                            <span class="inline-block px-2 py-0.5 text-xs rounded ml-1
                                {{ $message->direction === 'inbound' ? 'bg-gray-100 text-gray-600' : 'bg-gray-100 text-gray-600' }}">
                                {{ $message->direction }}
                            </span>
                        </div>
                        <span class="text-xs text-gray-400">
                            {{ $message->created_at->format('Y-m-d H:i:s') }}
                        </span>
                    </div>
                    <p class="mt-2 text-gray-800">{{ $message->text }}</p>
                </div>
            @empty
                <p class="text-gray-400">No messages yet.</p>
            @endforelse
        </div>
    </div>
</body>
</html>
