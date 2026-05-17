<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orange Play Dashboard</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-gray-100 text-gray-900">
    <div class="max-w-5xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Customers</h1>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 text-left text-sm text-gray-500">
                        <th class="px-6 py-3">Display Name</th>
                        <th class="px-6 py-3">Username</th>
                        <th class="px-6 py-3">Platform</th>
                        <th class="px-6 py-3">Latest Message</th>
                        <th class="px-6 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($customers as $customer)
                        @php
                            $latestMsg = $customer->messages->first();
                            $latestConv = $customer->conversations->first();
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <a href="/customers/{{ $customer->platform }}/{{ $customer->platform_user_id }}"
                                   class="text-blue-600 hover:underline font-medium">
                                    {{ $customer->display_name ?? $customer->platform_user_id }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                {{ $customer->username ?? '-' }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-block px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                    {{ $customer->platform }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                {{ $latestMsg ? Str::limit($latestMsg->text, 40) : '-' }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-block px-2 py-1 text-xs rounded-full
                                    {{ ($latestConv->status ?? '') === 'resolved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ $latestConv->status ?? '-' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                No customers yet. Send a Telegram message to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
