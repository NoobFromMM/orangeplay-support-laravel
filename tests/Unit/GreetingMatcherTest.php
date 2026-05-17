<?php

namespace Tests\Unit;

use App\Services\Support\GreetingMatcher;
use PHPUnit\Framework\TestCase;

class GreetingMatcherTest extends TestCase
{
    protected GreetingMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new GreetingMatcher();
    }

    public function test_english_greetings_detected(): void
    {
        $this->assertTrue($this->matcher->isGreeting('hi'));
        $this->assertTrue($this->matcher->isGreeting('hello'));
        $this->assertTrue($this->matcher->isGreeting('hey'));
        $this->assertTrue($this->matcher->isGreeting('mingalarbar'));
    }

    public function test_burmese_greetings_detected(): void
    {
        $this->assertTrue($this->matcher->isGreeting('မင်္ဂလာပါ'));
        $this->assertTrue($this->matcher->isGreeting('မင်လာပါ'));
        $this->assertTrue($this->matcher->isGreeting('ဟိုင်း'));
    }

    public function test_mixed_greeting_detected(): void
    {
        $this->assertTrue($this->matcher->isGreeting('hello ပါ'));
    }

    public function test_case_insensitive(): void
    {
        $this->assertTrue($this->matcher->isGreeting('Hi'));
        $this->assertTrue($this->matcher->isGreeting('HELLO'));
        $this->assertTrue($this->matcher->isGreeting('Hey'));
    }

    public function test_non_greetings_not_detected(): void
    {
        $this->assertFalse($this->matcher->isGreeting('how much is it'));
        $this->assertFalse($this->matcher->isGreeting('price'));
        $this->assertFalse($this->matcher->isGreeting('help'));
    }

    public function test_null_and_empty_not_detected(): void
    {
        $this->assertFalse($this->matcher->isGreeting(null));
        $this->assertFalse($this->matcher->isGreeting(''));
    }

    public function test_reply_text_is_exact(): void
    {
        $expected = "မင်္ဂလာပါရှင့် Orange Play Customer Service မှကြိုဆိုပါတယ်။ဘာများကူညီပေးရမလဲရှင့်";
        $this->assertSame($expected, $this->matcher->getReplyText());
    }

    public function test_reply_does_not_contain_banned_phrases(): void
    {
        $reply = $this->matcher->getReplyText();
        $this->assertStringNotContainsString('OrangePlayAI', $reply);
        $this->assertStringNotContainsString('Support Bot', $reply);
    }
}
