<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Internal;

enum DoctypeKind
{
    case Html5;
    case Html4;
    case Xhtml1;

    /**
     * Categorise a doctype string emitted by HtmlMin::getDoctype(). An empty
     * string means the document has no doctype (or libxml synthesised one
     * that we deliberately ignore) and yields null.
     */
    public static function fromDoctypeString(string $doctype): ?self
    {
        if ($doctype === '') {
            return null;
        }

        if (str_contains($doctype, 'html4')) {
            return self::Html4;
        }

        if (str_contains($doctype, 'xhtml1')) {
            return self::Xhtml1;
        }

        return self::Html5;
    }
}
