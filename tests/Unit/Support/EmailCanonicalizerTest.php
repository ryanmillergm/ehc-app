<?php

namespace Tests\Unit\Support;

use App\Support\EmailCanonicalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailCanonicalizerTest extends TestCase
{
    #[Test]
    public function it_trims_and_lowercases(): void
    {
        $this->assertSame(
            'user@example.com',
            EmailCanonicalizer::canonicalize('  USER@Example.com ')
        );
    }

    #[Test]
    public function it_strips_plus_tags_for_all_domains(): void
    {
        $this->assertSame(
            'user@example.com',
            EmailCanonicalizer::canonicalize('user+tag@example.com')
        );

        $this->assertSame(
            'user@mydomain.org',
            EmailCanonicalizer::canonicalize('User+whatever@MyDomain.org')
        );
    }

    #[Test]
    public function it_normalizes_gmail_dots_and_googlemail_domain(): void
    {
        $this->assertSame(
            'username@gmail.com',
            EmailCanonicalizer::canonicalize('User.Name+tag@GMAIL.com')
        );

        $this->assertSame(
            'username@gmail.com',
            EmailCanonicalizer::canonicalize('user.name@googlemail.com')
        );
    }

    #[Test]
    public function it_returns_null_for_null_or_empty(): void
    {
        $this->assertNull(EmailCanonicalizer::canonicalize(null));
        $this->assertNull(EmailCanonicalizer::canonicalize('   '));
    }
}
