<?php

namespace Tests\Unit\Support\Email;

use App\Support\Email\EmailBodyCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

final class EmailBodyCompilerTest extends TestCase
{
    #[Test]
    public function it_inlines_css_and_returns_a_body_fragment(): void
    {
        $compiler = new EmailBodyCompiler(new CssToInlineStyles());

        $designHtml = '<div class="x"><strong>Hello</strong> world</div>';
        $designCss  = '.x{color:red;font-weight:700;}';

        $compiled = $compiler->compile($designHtml, $designCss);

        $this->assertArrayHasKey('html', $compiled);
        $this->assertArrayHasKey('text', $compiled);

        // Should be a fragment (no <html> or <body> wrapper)
        $this->assertStringNotContainsString('<html', strtolower($compiled['html']));
        $this->assertStringNotContainsString('<body', strtolower($compiled['html']));

        // Should inline our CSS somewhere on the element
        $this->assertMatchesRegularExpression('/style="[^"]*color:\s*red;?[^"]*"/i', $compiled['html']);

        // Text should be stripped
        $this->assertSame('Hello world', $compiled['text']);
    }

    #[Test]
    public function it_handles_full_document_inputs_and_still_outputs_fragment(): void
    {
        $compiler = new EmailBodyCompiler(new CssToInlineStyles());

        $designHtml = '<!doctype html><html><head><title>X</title></head><body><p class="x">Hi</p></body></html>';
        $designCss  = '.x{font-size:18px;}';

        $compiled = $compiler->compile($designHtml, $designCss);

        $this->assertStringNotContainsString('<body', strtolower($compiled['html']));
        $this->assertStringContainsString('Hi', $compiled['html']);
        $this->assertMatchesRegularExpression('/style="[^"]*font-size:\s*18px;?[^"]*"/i', $compiled['html']);
    }

    #[Test]
    public function text_from_html_decodes_entities_and_collapses_whitespace_and_limits_length(): void
    {
        $compiler = new EmailBodyCompiler(new CssToInlineStyles());

        $html = "<p>Hello&nbsp;&amp;&nbsp;goodbye</p>\n<p>  spaced   out </p>";
        $this->assertSame('Hello & goodbye spaced out', $compiler->textFromHtml($html));

        $veryLong = '<p>' . str_repeat('a', 20050) . '</p>';
        $text = $compiler->textFromHtml($veryLong);

        $this->assertSame(10000, strlen($text), 'textFromHtml should limit to 10,000 characters');
    }
}
