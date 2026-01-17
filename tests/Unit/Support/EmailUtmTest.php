<?php

namespace Tests\Unit\Support;

use App\Support\EmailUtm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmailUtmTest extends TestCase
{
    #[Test]
    public function it_appends_utm_params_to_http_links_but_skips_mailto_tel_hash_and_javascript(): void
    {
        $html = implode('', [
            '<a href="https://example.com/page">A</a>',
            '<a href="https://example.com/page?x=1#frag">B</a>',
            '<a href="mailto:test@example.com">C</a>',
            '<a href="tel:+15551212">D</a>',
            '<a href="#section">E</a>',
            '<a href="javascript:void(0)">F</a>',
            '<a href="/relative/path">G</a>',
        ]);

        $out = EmailUtm::apply($html, [
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'jan-2026',
        ]);

        // https link (note &amp; in HTML output)
        $this->assertStringContainsString(
            'https://example.com/page?utm_source=newsletter&amp;utm_medium=email&amp;utm_campaign=jan-2026',
            $out
        );

        // existing query + fragment preserved
        $this->assertStringContainsString(
            'https://example.com/page?x=1&amp;utm_source=newsletter&amp;utm_medium=email&amp;utm_campaign=jan-2026#frag',
            $out
        );

        // skipped protocols/fragments unchanged
        $this->assertStringContainsString('href="mailto:test@example.com"', $out);
        $this->assertStringContainsString('href="tel:+15551212"', $out);
        $this->assertStringContainsString('href="#section"', $out);
        $this->assertStringContainsString('href="javascript:void(0)"', $out);

        // relative link gets UTMs too
        $this->assertStringContainsString(
            'href="/relative/path?utm_source=newsletter&amp;utm_medium=email&amp;utm_campaign=jan-2026"',
            $out
        );
    }

    #[Test]
    public function it_does_not_overwrite_existing_utm_values(): void
    {
        $html = '<a href="https://example.com/?utm_source=already&utm_medium=email">Link</a>';

        $out = EmailUtm::apply($html, [
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'new',
        ]);

        // utm_source stays "already"
        $this->assertStringContainsString('utm_source=already', $out);

        // utm_campaign gets added because it wasn't present
        $this->assertStringContainsString('utm_campaign=new', $out);
    }
}
