<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Internal;

use Akankov\HtmlMin\Internal\Doctype;
use DOMDocument;
use DOMImplementation;
use PHPUnit\Framework\TestCase;

final class DoctypeTest extends TestCase
{
    public function testHtml5Doctype(): void
    {
        $doc = (new DOMImplementation())
            ->createDocument(null, '', (new DOMImplementation())->createDocumentType('html'));

        self::assertSame('<!DOCTYPE html>', Doctype::serialize($doc));
    }

    public function testSystemOnlyDoctype(): void
    {
        $impl = new DOMImplementation();
        $doc = $impl->createDocument(null, '', $impl->createDocumentType('html', '', 'about:legacy-compat'));

        self::assertSame('<!DOCTYPE html SYSTEM "about:legacy-compat">', Doctype::serialize($doc));
    }

    public function testPublicDoctype(): void
    {
        $impl = new DOMImplementation();
        $dtd = $impl->createDocumentType(
            'html',
            '-//W3C//DTD HTML 4.01//EN',
            'http://www.w3.org/TR/html4/strict.dtd',
        );
        $doc = $impl->createDocument(null, '', $dtd);

        self::assertSame(
            '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"  "http://www.w3.org/TR/html4/strict.dtd">',
            Doctype::serialize($doc),
        );
    }

    public function testNoDoctypeYieldsEmptyString(): void
    {
        $doc = new DOMDocument();
        $doc->appendChild($doc->createElement('html'));

        self::assertSame('', Doctype::serialize($doc));
    }
}
