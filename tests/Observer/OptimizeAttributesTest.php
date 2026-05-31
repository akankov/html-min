<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Observer;

use Akankov\HtmlMin\HtmlMin;
use Akankov\HtmlMin\Observer\OptimizeAttributes;
use DOMDocument;

use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;

use PHPUnit\Framework\TestCase;

final class OptimizeAttributesTest extends TestCase
{
    public function testBeforeMinificationHookIsANoOp(): void
    {
        // OptimizeAttributes only acts in the "after" phase; its before-hook is
        // an intentional no-op that must leave the element untouched.
        $doc = new DOMDocument();
        $doc->loadHTML('<div class="b a">x</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $div = $doc->getElementsByTagName('div')->item(0);
        self::assertNotNull($div);

        (new OptimizeAttributes())->domElementBeforeMinification($div, new HtmlMin());

        self::assertSame('b a', $div->getAttribute('class'));
    }
}
