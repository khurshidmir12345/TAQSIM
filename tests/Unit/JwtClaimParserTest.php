<?php

namespace Tests\Unit;

use App\Support\JwtClaimParser;
use Tests\TestCase;

class JwtClaimParserTest extends TestCase
{
    public function test_parse_email_verified_accepts_boolean_and_string_forms(): void
    {
        $this->assertTrue(JwtClaimParser::parseEmailVerified(true));
        $this->assertFalse(JwtClaimParser::parseEmailVerified(false));
        $this->assertTrue(JwtClaimParser::parseEmailVerified('true'));
        $this->assertTrue(JwtClaimParser::parseEmailVerified('1'));
        $this->assertTrue(JwtClaimParser::parseEmailVerified('yes'));
        $this->assertFalse(JwtClaimParser::parseEmailVerified('false'));
        $this->assertFalse(JwtClaimParser::parseEmailVerified('0'));
        $this->assertFalse(JwtClaimParser::parseEmailVerified(null));
    }

    public function test_parse_email_normalizes_and_rejects_invalid_values(): void
    {
        $this->assertSame('user@example.com', JwtClaimParser::parseEmail(' User@Example.com '));
        $this->assertNull(JwtClaimParser::parseEmail(''));
        $this->assertNull(JwtClaimParser::parseEmail(null));
    }
}
