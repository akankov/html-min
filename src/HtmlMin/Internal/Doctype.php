<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Internal;

use DOMDocumentType;
use DOMNode;

/**
 * Builds the `<!DOCTYPE …>` string for a parsed document.
 *
 * Pairs with {@see DoctypeKind}, which classifies the resulting string into
 * HTML5 / HTML4 / XHTML1. Extracted from HtmlMin so the doctype string-building
 * sits next to the doctype classifier.
 */
class Doctype
{
    /**
     * Serialize the document's declared doctype, or `''` when it has no
     * `DOMDocumentType` child (or one without a name).
     */
    public static function serialize(DOMNode $node): string
    {
        foreach ($node->childNodes as $child) {
            if (
                $child instanceof DOMDocumentType
                &&
                $child->name
            ) {
                if (!$child->publicId && $child->systemId) {
                    $tmpTypeSystem = 'SYSTEM';
                    $tmpTypePublic = '';
                } else {
                    $tmpTypeSystem = '';
                    $tmpTypePublic = 'PUBLIC';
                }

                return '<!DOCTYPE ' . $child->name
                       . ($child->publicId ? ' ' . $tmpTypePublic . ' "' . $child->publicId . '"' : '')
                       . ($child->systemId ? ' ' . $tmpTypeSystem . ' "' . $child->systemId . '"' : '')
                       . '>';
            }
        }

        return '';
    }
}
