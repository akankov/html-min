<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests;

use Akankov\HtmlMin\HtmlMin;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HtmlMinConfigTest extends TestCase
{
    public function testTogglesAreReflectedByTheirGetters(): void
    {
        $m = new HtmlMin();

        // Defaults.
        self::assertTrue($m->isDoOptimizeViaHtmlDomParser());
        self::assertTrue($m->isDoRemoveComments());
        self::assertFalse($m->isDoRemoveSpacesBetweenTags());
        self::assertTrue($m->isDoSumUpWhitespace());

        // Flipped.
        $m->doOptimizeViaHtmlDomParser(false)
            ->doRemoveComments(false)
            ->doRemoveSpacesBetweenTags(true)
            ->doSumUpWhitespace(false);

        self::assertFalse($m->isDoOptimizeViaHtmlDomParser());
        self::assertFalse($m->isDoRemoveComments());
        self::assertTrue($m->isDoRemoveSpacesBetweenTags());
        self::assertFalse($m->isDoSumUpWhitespace());
    }

    public function testOverwriteTemplateLogicSyntaxRejectsNonStrings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type (deliberately passing a non-string[])
        (new HtmlMin())->overwriteTemplateLogicSyntaxInSpecialScriptTags([123]);
    }

    public function testOverwriteSpecialScriptTagsRejectsNonStrings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type (deliberately passing a non-string[])
        (new HtmlMin())->overwriteSpecialScriptTags([123]);
    }
}
