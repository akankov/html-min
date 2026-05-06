<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Tests;

use Akankov\HtmlMinBench\Corpus;
use PHPUnit\Framework\TestCase;

final class CorpusTest extends TestCase
{
    public function testSmallTierHasSixFixtures(): void
    {
        self::assertCount(6, Corpus::small());
    }

    public function testRealWorldTierHasFiveFixtures(): void
    {
        self::assertCount(5, Corpus::realWorld());
    }

    public function testSmallFixturesAreNonEmpty(): void
    {
        foreach (Corpus::small() as $name => $html) {
            self::assertNotSame('', $html, "fixture $name is empty");
        }
    }

    public function testRealWorldFixturesAreNonEmpty(): void
    {
        foreach (Corpus::realWorld() as $name => $html) {
            self::assertNotSame('', $html, "fixture $name is empty");
        }
    }

    public function testSyntheticTierHasThreeStressFixtures(): void
    {
        self::assertCount(3, Corpus::synthetic());
        self::assertSame(
            ['repeated-fragments', 'deep-nesting', 'attribute-heavy'],
            array_keys(Corpus::synthetic()),
        );
    }

    public function testSyntheticFixturesAreNonEmpty(): void
    {
        foreach (Corpus::synthetic() as $name => $html) {
            self::assertNotSame('', $html, "synthetic fixture $name is empty");
        }
    }

    public function testAllReturnsUnionOfAllTiers(): void
    {
        self::assertCount(14, Corpus::all());
    }

    public function testFixtureNamesAreStableIdentifiers(): void
    {
        foreach (array_keys(Corpus::all()) as $name) {
            self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', $name);
        }
    }
}
