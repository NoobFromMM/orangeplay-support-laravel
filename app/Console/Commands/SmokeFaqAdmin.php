<?php

namespace App\Console\Commands;

use App\Models\FaqEntry;
use App\Services\Support\FaqMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokeFaqAdmin extends Command
{
    protected $signature = 'smoke:faq-admin';
    protected $description = 'Smoke test for F7 FAQ admin management';

    public function handle(FaqMatcher $faqMatcher): int
    {
        $this->info('FAQ Admin Smoke Test');
        $this->info('====================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('Test A: Create FAQ');
            $this->testCreate($errors);

            $this->info('Test B: Update FAQ');
            $this->testUpdate($errors);

            $this->info('Test C: Toggle active/inactive');
            $this->testToggle($errors);

            $this->info('Test D: Active FAQ matches via FaqMatcher');
            $this->testMatchActive($faqMatcher, $errors);

            $this->info('Test E: Inactive FAQ does not match');
            $this->testMatchInactive($faqMatcher, $errors);

        } catch (\Throwable $e) {
            $errors[] = "Exception: " . $e->getMessage();
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

    protected function testCreate(array &$errors): void
    {
        // Simulate creating via controller-style logic
        $keywords = ['test_question', 'စမ်းသပ်မေးခွန်း', 'test query'];
        $answer = 'This is a test answer for smoke testing.';

        $faq = FaqEntry::create([
            'intent_code' => 'test_smoke_' . time(),
            'category' => 'testing',
            'keywords' => $keywords,
            'answer_text' => $answer,
            'priority' => 60,
            'is_active' => true,
        ]);

        if (! $faq->exists) {
            $errors[] = "FAQ not created";
            return;
        }
        $this->info('  OK  FAQ created');

        if ($faq->intent_code === '') {
            $errors[] = "intent_code empty";
        } else {
            $this->info('  OK  intent_code saved');
        }

        $savedKeywords = $faq->keywords;
        if (! is_array($savedKeywords) || count($savedKeywords) !== 3) {
            $errors[] = "Expected 3 keywords, got " . (is_array($savedKeywords) ? count($savedKeywords) : 'null');
        } else {
            $this->info('  OK  keywords saved as JSON array (3 items)');
        }

        if ($faq->answer_text !== $answer) {
            $errors[] = "Answer text mismatch";
        } else {
            $this->info('  OK  answer_text saved');
        }

        if ($faq->priority !== 60) {
            $errors[] = "Expected priority=60, got {$faq->priority}";
        } else {
            $this->info('  OK  priority=60');
        }

        if ($faq->is_active !== true) {
            $errors[] = "Expected is_active=true";
        } else {
            $this->info('  OK  is_active=true');
        }

        $this->newLine();
    }

    protected function testUpdate(array &$errors): void
    {
        $keywords = ['old'];
        $faq = FaqEntry::create([
            'intent_code' => 'update_test_' . time(),
            'keywords' => $keywords,
            'answer_text' => 'old answer',
            'priority' => 30,
            'is_active' => true,
        ]);

        $newKeywords = ['new_keyword', 'keyword_two'];
        $faq->update([
            'keywords' => $newKeywords,
            'answer_text' => 'new answer',
            'priority' => 40,
        ]);

        if ($faq->fresh()->answer_text !== 'new answer') {
            $errors[] = "Update: answer not updated";
        } else {
            $this->info('  OK  answer_text updated');
        }

        if ($faq->fresh()->priority !== 40) {
            $errors[] = "Update: priority not updated";
        } else {
            $this->info('  OK  priority updated to 40');
        }

        $updatedKeywords = $faq->fresh()->keywords;
        if (count($updatedKeywords) !== 2) {
            $errors[] = "Update: expected 2 keywords";
        } else {
            $this->info('  OK  keywords updated (2 items)');
        }

        $this->newLine();
    }

    protected function testToggle(array &$errors): void
    {
        $faq = FaqEntry::create([
            'intent_code' => 'toggle_test_' . time(),
            'keywords' => ['toggle'],
            'answer_text' => 'toggle answer',
            'is_active' => true,
        ]);

        $faq->update(['is_active' => false]);
        if ($faq->fresh()->is_active !== false) {
            $errors[] = "Toggle: expected is_active=false after deactivate";
        } else {
            $this->info('  OK  is_active flipped to false');
        }

        $faq->update(['is_active' => true]);
        if ($faq->fresh()->is_active !== true) {
            $errors[] = "Toggle: expected is_active=true after reactivate";
        } else {
            $this->info('  OK  is_active flipped back to true');
        }

        $this->newLine();
    }

    protected function testMatchActive(FaqMatcher $faqMatcher, array &$errors): void
    {
        $faq = FaqEntry::create([
            'intent_code' => 'match_active_' . time(),
            'keywords' => ['mykeyword', 'မြန်မာစကားလုံး'],
            'answer_text' => 'Matched answer!',
            'priority' => 101,
            'is_active' => true,
        ]);

        $result = $faqMatcher->match('mykeyword is a unique tag');
        if (! $result) {
            $errors[] = "FaqMatcher: active FAQ with 'mykeyword' should match";
        } elseif ($result->id !== $faq->id) {
            $errors[] = "FaqMatcher: matched wrong entry id={$result->id}, expected id={$faq->id}";
        } else {
            $this->info("  OK  Active FAQ with 'mykeyword' matched");
        }

        $result2 = $faqMatcher->match('မြန်မာစကားလုံး');
        if (! $result2) {
            $errors[] = "FaqMatcher: Burmese keyword should match";
        } else {
            $this->info("  OK  Burmese keyword matched");
        }

        $this->newLine();
    }

    protected function testMatchInactive(FaqMatcher $faqMatcher, array &$errors): void
    {
        $faq = FaqEntry::create([
            'intent_code' => 'match_inactive_' . time(),
            'keywords' => ['zzzsecret_inactive_kw'],
            'answer_text' => 'Should not match',
            'priority' => 101,
            'is_active' => false,
        ]);

        $result = $faqMatcher->match('zzzsecret_inactive_kw not found');
        if ($result) {
            $errors[] = "FaqMatcher: inactive FAQ should NOT match (matched id={$result->id})";
        } else {
            $this->info('  OK  Inactive FAQ does not match');
        }

        $this->newLine();
    }
}
