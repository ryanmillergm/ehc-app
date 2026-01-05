<?php

namespace Tests\Unit\Support;

use App\Support\HtmlFragments;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HtmlFragmentsTest extends TestCase
{
    #[Test]
    public function it_returns_null_when_input_is_null(): void
    {
        $this->assertNull(HtmlFragments::bodyInner(null));
    }

    #[Test]
    public function it_returns_empty_string_when_input_is_empty_or_whitespace(): void
    {
        $this->assertSame('', HtmlFragments::bodyInner(''));
        $this->assertSame('', HtmlFragments::bodyInner("   \n\t "));
    }

    #[Test]
    public function it_extracts_body_inner_html_when_a_body_tag_exists(): void
    {
        $html = '<!doctype html><html><head></head><body><div>Hi</div></body></html>';

        $this->assertSame('<div>Hi</div>', HtmlFragments::bodyInner($html));
    }

    #[Test]
    public function it_extracts_html_inner_html_when_only_html_tag_exists(): void
    {
        $html = '<html><head><meta charset="utf-8"></head><div>Yo</div></html>';

        $this->assertSame('<head><meta charset="utf-8"></head><div>Yo</div>', HtmlFragments::bodyInner($html));
    }

    #[Test]
    public function it_returns_input_when_already_a_fragment(): void
    {
        $html = '<section><h1>Hello</h1><p>World</p></section>';

        $this->assertSame($html, HtmlFragments::bodyInner($html));
    }
}
