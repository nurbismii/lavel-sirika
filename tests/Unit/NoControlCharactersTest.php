<?php

namespace Tests\Unit;

use App\Rules\NoControlCharacters;
use PHPUnit\Framework\TestCase;

class NoControlCharactersTest extends TestCase
{
    /** @test */
    public function it_rejects_ascii_control_characters_without_rewriting_the_value()
    {
        $rule = new NoControlCharacters();

        $this->assertTrue($rule->passes('email', 'valid@example.com'));
        $this->assertFalse($rule->passes('email', "unsafe@example.com\r\nBcc:test@example.com"));
        $this->assertFalse($rule->passes('email', "unsafe@example.com\x00"));
    }
}
