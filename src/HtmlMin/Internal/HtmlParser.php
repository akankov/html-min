<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Internal;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

use const LIBXML_BIGLINES;
use const LIBXML_COMPACT;
use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;
use const LIBXML_NONET;
use const XML_PI_NODE;

/**
 * HTML5-aware adapter around \DOMDocument.
 *
 * Replaces voku/simple_html_dom for our minifier's needs. Wraps libxml's HTML
 * parser with the pre- and post-processing steps voku did internally:
 *
 *   - preserve UTF-8 (libxml defaults to ISO-8859-1),
 *   - escape entity-like characters (`&`, `|`, `+`, `%`, `@`, `[`, `]`,
 *     `{`, `}`) with placeholder tokens so libxml does not rewrite them,
 *   - keep `<script>` / special-script / svg contents opaque to the parser,
 *   - skip the implied `<html><body>` wrapper for fragments.
 *
 * All entry points are static/pure.
 */
final class HtmlParser
{
    /** @var array<string, string>|null */
    private static ?array $entityRestoreMap = null;

    /** @var array<string, string>|null */
    private static ?array $globalEntityMap = null;

    /**
     * HTML5 void elements — serialized without a closing tag and without the
     * XHTML self-closing slash.
     *
     * @var array<string, true>
     */
    private const array VOID_TAGS = [
        'area'   => true,
        'base'   => true,
        'br'     => true,
        'col'    => true,
        'embed'  => true,
        'hr'     => true,
        'img'    => true,
        'input'  => true,
        'keygen' => true,
        'link'   => true,
        'meta'   => true,
        'param'  => true,
        'source' => true,
        'track'  => true,
        'wbr'    => true,
    ];

    /**
     * Precomputed regex that strips the XHTML self-closing slash from void
     * elements. Must stay in sync with VOID_TAGS above.
     */
    private const string VOID_TAGS_PATTERN = '#<(area|base|br|col|embed|hr|img|input|keygen|link|meta|param|source|track|wbr)([^>]*)\s*/>#i';

    /**
     * Character-to-placeholder pairs for URL-metacharacters. Identical byte
     * shape to voku/simple_html_dom so downstream regex filters keep working.
     *
     * @var array<string, string>
     */
    private const array URL_CHAR_PLACEHOLDERS = [
        '[' => '____SIMPLE_HTML_DOM__VOKU__SQUARE_BRACKET_LEFT____',
        ']' => '____SIMPLE_HTML_DOM__VOKU__SQUARE_BRACKET_RIGHT____',
        '{' => '____SIMPLE_HTML_DOM__VOKU__BRACKET_LEFT____',
        '}' => '____SIMPLE_HTML_DOM__VOKU__BRACKET_RIGHT____',
    ];

    /**
     * Character-to-placeholder pairs for HTML-entity-significant characters
     * that must survive libxml's entity decoding round-trip.
     *
     * @var array<string, string>
     */
    private const array ENTITY_CHAR_PLACEHOLDERS = [
        '&' => '____SIMPLE_HTML_DOM__VOKU__AMP____',
        '|' => '____SIMPLE_HTML_DOM__VOKU__PIPE____',
        '+' => '____SIMPLE_HTML_DOM__VOKU__PLUS____',
        '%' => '____SIMPLE_HTML_DOM__VOKU__PERCENT____',
        '@' => '____SIMPLE_HTML_DOM__VOKU__AT____',
    ];

    /**
     * Special "<html ⚡" marker (Google AMP): libxml doesn't like raw "⚡"
     * bytes in tag names. Replaced with a placeholder attribute.
     */
    private const string AMP_PLACEHOLDER_SEARCH  = '<html ⚡';
    private const string AMP_PLACEHOLDER_REPLACE = '<html ____SIMPLE_HTML_DOM__VOKU__GOOGLE_AMP____="true"';

    /**
     * Prefix for custom placeholder tags that wrap broken HTML chunks so they
     * survive the DOM round-trip unmodified. Uses a hyphen-safe custom-element
     * name compatible with libxml2 ≥ 2.9.14.
     */
    private const string BROKEN_HTML_PLACEHOLDER = 'htmlmin-broken-html-';

    private const string SPECIAL_SCRIPT_TAG = 'htmlmin-special-script';

    /**
     * Extra text-replacement table populated by preprocessing (keepBrokenHtml,
     * keepSpecialSvgTags, keepSpecialScriptTags with template logic).
     *
     * @var array{orig: string[], tmp: string[]}
     */
    public static array $brokenHtmlMap = ['orig' => [], 'tmp' => []];

    /**
     * Reset all cross-call state. Call once per minify() run.
     */
    public static function reset(): void
    {
        self::$brokenHtmlMap = ['orig' => [], 'tmp' => []];
    }

    /**
     * Options controlling the preprocessing.
     *
     * @param string[]|null $specialScriptTags
     * @param string[]|null $templateLogicSyntaxInSpecialScriptTags
     */
    public static function parse(
        string $html,
        bool $keepBrokenHtml = false,
        ?array $specialScriptTags = null,
        ?array $templateLogicSyntaxInSpecialScriptTags = null,
    ): DOMDocument {
        $doc = new DOMDocument('1.0', 'UTF-8');
        // Keep whitespace text nodes — sumUpWhitespace() normalizes them later,
        // and dropping them at parse time loses significant inline whitespace
        // (e.g. "<span>foo</span> <span>bar</span>").
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;
        $doc->encoding = 'UTF-8';

        if ($html === '') {
            return $doc;
        }

        // Remove junk before any <!DOCTYPE> — libxml can't cope.
        $isDOMDocumentCreatedWithDoctype = false;
        if (stripos($html, '<!DOCTYPE') !== false) {
            $isDOMDocumentCreatedWithDoctype = true;
            if (
                preg_match('/(^.*?)<!DOCTYPE(?: [^>]*)?>/sui', $html, $matches_before_doctype)
                && trim($matches_before_doctype[1]) !== ''
            ) {
                $html = str_replace($matches_before_doctype[1], '', $html);
            }
        }

        if ($keepBrokenHtml) {
            $html = self::rewriteBrokenHtml(trim($html));
        }
        $isDOMDocumentCreatedWithoutHtmlWrapper = !str_contains($html, '<html ')
            && !str_contains($html, '<html>')  ;
        $isDOMDocumentCreatedWithoutBodyWrapper = !str_contains($html, '<body ')
            && !str_contains($html, '<body>')  ;

        // Trim content after a trailing </html>.
        if (stripos($html, '</html>') !== false) {
            if (
                preg_match('/<\/html>(.*?)/suiU', $html, $matches_after_html)
                && trim($matches_after_html[1]) !== ''
            ) {
                $html = str_replace($matches_after_html[0], $matches_after_html[1] . '</html>', $html);
            }
        }

        // Escape raw </script> markers inside script bodies and protect
        // special-script template contents.
        if (str_contains($html, '<script')) {
            self::html5FallbackForScriptTags($html);

            if ($specialScriptTags !== null) {
                $templateLogic = $templateLogicSyntaxInSpecialScriptTags
                    ?? ['+', '<%', '{%', '{{'];
                foreach ($specialScriptTags as $tag) {
                    if (str_contains($html, $tag)) {
                        self::keepSpecialScriptTags($html, $specialScriptTags, $templateLogic);
                    }
                }
            } else {
                // Default special-script tags (matches voku defaults).
                $defaultSpecialTags = [
                    'text/html',
                    'text/template',
                    'text/x-custom-template',
                    'text/x-handlebars-template',
                ];
                $defaultTemplateLogic = $templateLogicSyntaxInSpecialScriptTags
                    ?? ['+', '<%', '{%', '{{'];
                foreach ($defaultSpecialTags as $tag) {
                    if (str_contains($html, $tag)) {
                        self::keepSpecialScriptTags($html, $defaultSpecialTags, $defaultTemplateLogic);
                    }
                }
            }
        }

        if (str_contains((string) $html, '<svg')) {
            self::keepSpecialSvgTags($html);
        }

        // Apply placeholder wrapping to prevent libxml from collapsing
        // "<br>" into "<br/>" etc. — same as voku.
        $html = str_replace(
            array_map(static fn (string $e): string => '<' . $e . '>', array_keys(self::VOID_TAGS)),
            array_map(static fn (string $e): string => '<' . $e . '/>', array_keys(self::VOID_TAGS)),
            $html,
        );

        // Wrap fragments in a custom root element so libxml preserves
        // inter-element whitespace. The wrapper is stripped during
        // putReplacedBackToPreserveHtmlEntities().
        $needsRootWrapper = !$isDOMDocumentCreatedWithDoctype
            && $isDOMDocumentCreatedWithoutHtmlWrapper
            && $isDOMDocumentCreatedWithoutBodyWrapper;

        if ($needsRootWrapper) {
            $html = '<htmlmin-wrapper>' . $html . '</htmlmin-wrapper>';
        }

        $html = self::replaceToPreserveHtmlEntities($html);

        $options = LIBXML_NONET;
        if (\defined('LIBXML_BIGLINES')) {
            $options |= LIBXML_BIGLINES;
        }
        if (\defined('LIBXML_COMPACT')) {
            $options |= LIBXML_COMPACT;
        }
        $options |= LIBXML_HTML_NODEFDTD;
        // Always skip the implied <html><body> wrapper — matches voku's behavior.
        // We add our own <htmlmin-wrapper> for multi-root fragments upstream.
        $options |= LIBXML_HTML_NOIMPLIED;

        // UTF-8 hack.
        $xmlHackUsed = false;
        if (stripos($html, '<?xml') !== 0) {
            $xmlHackUsed = true;
            $html = '<?xml encoding="UTF-8" ?>' . $html;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        if ($html !== '') {
            $doc->loadHTML($html, $options);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xmlHackUsed) {
            foreach (iterator_to_array($doc->childNodes) as $child) {
                if ($child->nodeType === XML_PI_NODE) {
                    $doc->removeChild($child);

                    break;
                }
            }
        }

        $doc->encoding = 'UTF-8';

        return $doc;
    }

    /**
     * Pre-load entity-preserving replacement, mirrors voku's implementation
     * byte-for-byte so downstream regex filters that look for these markers
     * keep working.
     */
    public static function replaceToPreserveHtmlEntities(string $html): string
    {
        // The URL placeholder pass is scoped to http(s) URLs (so square/curly
        // brackets only get masked inside links), so it stays a separate
        // regex-callback pass. The AMP marker and the entity-significant chars,
        // however, are document-global; merge them into a single strtr() so
        // libxml-troublesome characters are masked in one scan instead of two.
        // strtr picks the longest matching key at each position, so the multi-
        // byte AMP marker resolves before the single-byte '&'.
        if (str_contains($html, 'http')) {
            $regExUrl = '/(\[?\bhttps?:\/\/[^\s<>]+(?:\(\w+\)|[^[:punct:]\s]|\/|}|]))/i';
            $replaced = preg_replace_callback(
                $regExUrl,
                static fn (array $m): string => strtr($m[0], self::URL_CHAR_PLACEHOLDERS),
                $html,
            );
            if ($replaced !== null) {
                $html = $replaced;
            }
        }

        return strtr($html, self::buildGlobalEntityMap());
    }

    /**
     * @return array<string, string>
     */
    private static function buildGlobalEntityMap(): array
    {
        if (self::$globalEntityMap !== null) {
            return self::$globalEntityMap;
        }

        return self::$globalEntityMap = [self::AMP_PLACEHOLDER_SEARCH => self::AMP_PLACEHOLDER_REPLACE]
                                       + self::ENTITY_CHAR_PLACEHOLDERS;
    }

    /**
     * Single strtr map: placeholders → originals + wrapper/special-script tag
     * cleanup. strtr picks the longest matching key first at each position, so
     * overlapping SPECIAL_SCRIPT_TAG variants ("</htmlmin-special-script>"
     * before the shorter "</htmlmin-special-script" and "htmlmin-special-script")
     * resolve correctly.
     *
     * libxml lowercases attribute names, so placeholders used inside attribute
     * names (e.g. "@change" → "____SIMPLE_HTML_DOM__VOKU__AT____change") come
     * back lowercase after the DOM round-trip. The original str_ireplace version
     * relied on case-insensitivity; we emulate that by adding lowercase aliases
     * for each placeholder.
     *
     * @return array<string, string>
     */
    private static function buildEntityRestoreMap(): array
    {
        if (self::$entityRestoreMap !== null) {
            return self::$entityRestoreMap;
        }

        $placeholders = array_flip(self::URL_CHAR_PLACEHOLDERS)
                      + array_flip(self::ENTITY_CHAR_PLACEHOLDERS);
        $map = $placeholders;
        foreach ($placeholders as $key => $val) {
            $map[strtolower($key)] = $val;
        }
        $map['<htmlmin-wrapper>']                   = '';
        $map['</htmlmin-wrapper>']                  = '';
        $map['htmlmin-wrapper>']                    = '';
        $map['</htmlmin-wrapper']                   = '';
        $map['<' . self::SPECIAL_SCRIPT_TAG]        = '<script';
        $map['</' . self::SPECIAL_SCRIPT_TAG . '>'] = '</script>';
        $map[self::SPECIAL_SCRIPT_TAG]              = 'script';
        $map['</' . self::SPECIAL_SCRIPT_TAG]       = '</script';

        return self::$entityRestoreMap = $map;
    }

    /**
     * Reverse of replaceToPreserveHtmlEntities plus cleanup of the broken-html
     * placeholders populated during parse().
     */
    public static function putReplacedBackToPreserveHtmlEntities(string $html, bool $putBrokenReplacedBack = true): string
    {
        if ($putBrokenReplacedBack && !empty(self::$brokenHtmlMap['tmp'])) {
            $html = str_ireplace(self::$brokenHtmlMap['tmp'], self::$brokenHtmlMap['orig'], $html);
        }

        // Undo the <html ⚡ placeholder. libxml lowercases attribute names and
        // may strip the quotes around the "true" value, so regex it back.
        $ampRestored = preg_replace(
            '/<html\s+____SIMPLE_HTML_DOM__VOKU__GOOGLE_AMP____\s*=\s*(?:"true"|\'true\'|true)/i',
            self::AMP_PLACEHOLDER_SEARCH,
            $html,
        );
        if ($ampRestored !== null) {
            $html = $ampRestored;
        }

        return strtr($html, self::buildEntityRestoreMap());
    }

    /**
     * Serialize a DOMDocument back to HTML5 — void tags without XHTML slash.
     */
    public static function serialize(DOMDocument $doc): string
    {
        $html = (string) $doc->saveHTML();

        return (string) preg_replace(self::VOID_TAGS_PATTERN, '<$1$2>', $html);
    }

    /**
     * @return DOMNode[]
     */
    public static function findAll(DOMNode $root, string $selector): array
    {
        $doc = $root instanceof DOMDocument ? $root : $root->ownerDocument;
        if ($doc === null) {
            return [];
        }

        // Fast path: simple tag selectors ("*", "code", "script, style") resolve
        // via DOMDocument::getElementsByTagName, which is implemented in C and
        // avoids xpath parsing + evaluation. Noticeably cheaper than an xpath
        // round-trip on large documents.
        $selector = trim($selector);
        if (
            !str_starts_with($selector, '//')
            &&
            ($root instanceof DOMDocument || $root instanceof DOMElement)
        ) {
            $tags = str_contains($selector, ',')
                ? array_filter(array_map(trim(...), explode(',', $selector)))
                : [$selector];
            $allSimple = $tags !== [];
            foreach ($tags as $tag) {
                if ($tag !== '*' && preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', $tag) !== 1) {
                    $allSimple = false;

                    break;
                }
            }
            if ($allSimple) {
                $out = [];
                foreach ($tags as $tag) {
                    foreach ($root->getElementsByTagName($tag) as $node) {
                        $out[] = $node;
                    }
                }

                /** @var DOMNode[] $out */
                return $out;
            }
        }

        $xpath = new DOMXPath($doc);
        $query = self::selectorToXPath($selector);
        // Always pass the root explicitly; with null context, PHP's DOMXPath
        // treats the document ELEMENT (not the document itself) as implicit
        // context, which makes ".//*" miss the root element.
        $nodes = $xpath->query($query, $root);

        if ($nodes === false) {
            return [];
        }

        $out = [];
        foreach ($nodes as $node) {
            $out[] = $node;
        }

        /** @var DOMNode[] $out */
        return $out;
    }

    public static function innerHtml(DOMElement $el): string
    {
        $doc = $el->ownerDocument;
        if ($doc === null) {
            return '';
        }

        $html = '';
        foreach ($el->childNodes as $child) {
            $rendered = $doc->saveHTML($child);
            if ($rendered !== false) {
                $html .= $rendered;
            }
        }

        return (string) preg_replace(self::VOID_TAGS_PATTERN, '<$1$2>', $html);
    }

    public static function setInnerHtml(DOMElement $el, string $html): void
    {
        $doc = $el->ownerDocument;
        if ($doc === null) {
            return;
        }

        while (($firstChild = $el->firstChild) !== null) {
            $el->removeChild($firstChild);
        }

        if ($html === '') {
            return;
        }

        $fragment = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $wrapped = '<?xml encoding="UTF-8" ?><htmlmin-root>' . $html . '</htmlmin-root>';
        $fragment->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $fragment->getElementsByTagName('htmlmin-root')->item(0);
        if ($root === null) {
            return;
        }

        foreach (iterator_to_array($root->childNodes) as $child) {
            $imported = $doc->importNode($child, true);
            $el->appendChild($imported);
        }
    }

    /**
     * @return array<string, string> Mapping of attribute name to (decoded) value.
     */
    public static function getAllAttributes(DOMElement $el): array
    {
        $out = [];
        /** @var DOMAttr $attr */
        foreach ($el->attributes as $attr) {
            $out[$attr->name] = $attr->value;
        }

        return $out;
    }

    private static function selectorToXPath(string $selector): string
    {
        $selector = trim($selector);

        if (str_starts_with($selector, '//')) {
            return $selector;
        }

        if ($selector === '*' || $selector === '') {
            return './/*';
        }

        if (str_contains($selector, ',')) {
            $parts = array_filter(array_map(trim(...), explode(',', $selector)));
            $queries = [];
            foreach ($parts as $part) {
                $queries[] = str_starts_with($part, '//') ? $part : './/' . $part;
            }

            return implode(' | ', $queries);
        }

        return './/' . $selector;
    }

    /**
     * Port of voku's html5FallbackForScriptTags: protects raw "</" sequences
     * inside <script> bodies.
     */
    private static function html5FallbackForScriptTags(string &$html): void
    {
        $regExSpecialScript = '/<script(?<attr>[^>]*?)>(?<content>.*)<\/script>/isU';
        $htmlTmp = preg_replace_callback(
            $regExSpecialScript,
            static function (array $scripts): string {
                if (empty($scripts['content'])) {
                    return $scripts[0];
                }

                return '<script' . $scripts['attr'] . '>' . str_replace('</', '<\/', $scripts['content']) . '</script>';
            },
            $html,
        );

        if ($htmlTmp !== null) {
            $html = $htmlTmp;
        }
    }

    /**
     * Port of voku's keepSpecialScriptTags: renames special-template script
     * tags to a non-script placeholder element so libxml leaves their bodies
     * untouched. Bodies containing template-logic tokens get stashed in
     * self::$brokenHtmlMap for later reinsertion.
     *
     * @param string[] $specialScriptTags
     * @param string[] $templateLogicSyntax
     */
    private static function keepSpecialScriptTags(string &$html, array $specialScriptTags, array $templateLogicSyntax): void
    {
        $tags = implode('|', array_map(
            static fn (string $value): string => preg_quote($value, '/'),
            $specialScriptTags,
        ));

        $result = preg_replace_callback(
            '/(?<start>(<script [^>]*type=["\']?(?:' . $tags . ')+[^>]*>))(?<innerContent>.*)(?<end><\/script>)/isU',
            static function (array $matches) use ($templateLogicSyntax): string {
                foreach ($templateLogicSyntax as $logic) {
                    if (str_contains($matches['innerContent'], $logic)) {
                        $matches['innerContent'] = str_replace('<\/', '</', $matches['innerContent']);

                        self::$brokenHtmlMap['orig'][] = $matches['innerContent'];
                        self::$brokenHtmlMap['tmp'][] = $hash = self::BROKEN_HTML_PLACEHOLDER . crc32($matches['innerContent']);

                        return $matches['start'] . $hash . $matches['end'];
                    }
                }

                $matches[0] = str_replace('<\/', '</', $matches[0]);
                $specialNonScript = '<' . self::SPECIAL_SCRIPT_TAG . substr($matches[0], \strlen('<script'));

                return substr($specialNonScript, 0, -\strlen('</script>')) . '</' . self::SPECIAL_SCRIPT_TAG . '>';
            },
            $html,
        );

        if ($result !== null) {
            $html = $result;
        }
    }

    /**
     * Workaround for https://bugs.php.net/bug.php?id=74628 — SVG embedded in
     * data-URL values.
     */
    private static function keepSpecialSvgTags(string &$html): void
    {
        $regExSpecialSvg = '/\((["\'])?(?<start>data:image\/svg.*)<svg(?<attr>[^>]*?)>(?<content>.*)<\/svg>\1\)/isU';
        $htmlTmp = preg_replace_callback(
            $regExSpecialSvg,
            static function (array $svgs): string {
                if (empty($svgs['content'])) {
                    return $svgs[0];
                }

                $content = '<svg' . $svgs['attr'] . '>' . $svgs['content'] . '</svg>';
                self::$brokenHtmlMap['orig'][] = $content;
                self::$brokenHtmlMap['tmp'][] = $hash = self::BROKEN_HTML_PLACEHOLDER . crc32($content);

                return '(' . $svgs[1] . $svgs['start'] . $hash . $svgs[1] . ')';
            },
            $html,
        );

        if ($htmlTmp !== null) {
            $html = $htmlTmp;
        }
    }

    /**
     * Voku's keepBrokenHtml: wrap all well-formed tag pairs, mark the leftover
     * unbalanced bits, then restore the wrapped ones. Anything unbalanced ends
     * up in self::$brokenHtmlMap keyed by crc32 hash.
     */
    private static function rewriteBrokenHtml(string $html): string
    {
        do {
            $original = $html;
            $html = (string) preg_replace_callback(
                '/(?<start>.*)<(?<element_start>[a-z]+)(?<element_start_addon> [^>]*)?>(?<value>.*?)<\/(?<element_end>\2)>(?<end>.*)/sui',
                static fn ($m): string => $m['start']
                        . '°lt_simple_html_dom__voku_°' . $m['element_start'] . $m['element_start_addon'] . '°gt_simple_html_dom__voku_°'
                        . $m['value']
                        . '°lt/_simple_html_dom__voku_°' . $m['element_end'] . '°gt_simple_html_dom__voku_°'
                        . $m['end'],
                $html,
            );
        } while ($original !== $html);

        do {
            $original = $html;
            $html = (string) preg_replace_callback(
                '/(?<start>[^<]*)?(?<broken>(?:<\/\w+(?:\s+\w+="[^"]+")*+[^<]+>)+)(?<end>.*)/u',
                static function (array $m): string {
                    $m['broken'] = str_replace(
                        ['°lt/_simple_html_dom__voku_°', '°lt_simple_html_dom__voku_°', '°gt_simple_html_dom__voku_°'],
                        ['</', '<', '>'],
                        $m['broken'],
                    );

                    self::$brokenHtmlMap['orig'][] = $m['broken'];
                    self::$brokenHtmlMap['tmp'][] = $hash = self::BROKEN_HTML_PLACEHOLDER . crc32($m['broken']);

                    return $m['start'] . $hash . $m['end'];
                },
                $html,
            );
        } while ($original !== $html);

        return str_replace(
            ['°lt/_simple_html_dom__voku_°', '°lt_simple_html_dom__voku_°', '°gt_simple_html_dom__voku_°'],
            ['</', '<', '>'],
            $html,
        );
    }
}
