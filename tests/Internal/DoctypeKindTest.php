<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Internal;

use Akankov\HtmlMin\Internal\DoctypeKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DoctypeKindTest extends TestCase
{
    public function testHtml5DoctypeIsRecognised(): void
    {
        self::assertSame(
            DoctypeKind::Html5,
            DoctypeKind::fromDoctypeString('<!DOCTYPE html>'),
        );
    }

    public function testHtml4StrictDoctypeIsRecognised(): void
    {
        $doctype = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN" '
                 . '"http://www.w3.org/TR/html4/strict.dtd">';

        self::assertSame(DoctypeKind::Html4, DoctypeKind::fromDoctypeString($doctype));
    }

    public function testXhtml1TransitionalDoctypeIsRecognised(): void
    {
        $doctype = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" '
                 . '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';

        self::assertSame(DoctypeKind::Xhtml1, DoctypeKind::fromDoctypeString($doctype));
    }

    public function testEmptyDoctypeStringYieldsNull(): void
    {
        self::assertNull(DoctypeKind::fromDoctypeString(''));
    }

    /**
     * @return iterable<string, array{string, DoctypeKind}>
     */
    public static function provideRealWorldDoctypesCases(): iterable
    {
        yield 'html4 transitional' => [
            '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" '
            . '"http://www.w3.org/TR/html4/loose.dtd">',
            DoctypeKind::Html4,
        ];

        yield 'xhtml1 strict' => [
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" '
            . '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
            DoctypeKind::Xhtml1,
        ];

        yield 'html5 lowercase' => [
            '<!doctype html>',
            DoctypeKind::Html5,
        ];
    }

    #[DataProvider('provideRealWorldDoctypesCases')]
    public function testRealWorldDoctypes(string $doctype, DoctypeKind $expected): void
    {
        self::assertSame($expected, DoctypeKind::fromDoctypeString($doctype));
    }
}
