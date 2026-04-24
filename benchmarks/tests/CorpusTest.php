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

    public function testAllReturnsUnionOfBothTiers(): void
    {
        self::assertCount(11, Corpus::all());
    }

    public function testFixtureNamesAreStableIdentifiers(): void
    {
        foreach (array_keys(Corpus::all()) as $name) {
            self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', $name);
        }
    }
}
