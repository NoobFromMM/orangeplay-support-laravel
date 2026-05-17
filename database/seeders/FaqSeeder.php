<?php

namespace Database\Seeders;

use App\Models\FaqEntry;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
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
}
