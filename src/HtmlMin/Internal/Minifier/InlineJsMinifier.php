<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Internal\Minifier;

use Override;

/**
 * Bundled, zero-dependency minifier for the contents of inline `<script>`
 * blocks. Performs conservative, ASI-safe minification:
 *
 *  - line and block comments are removed (block comments become a single
 *    space so adjacent identifiers don't fuse)
 *  - horizontal whitespace runs collapse to a single space
 *  - runs of vertical whitespace collapse to a single newline; newlines
 *    outside strings/regex/templates are preserved so Automatic Semicolon
 *    Insertion behaviour is unaffected
 *
 * Strings, regex literals, and template literals (including `${…}`
 * interpolations) are emitted verbatim. The regex-vs-division heuristic
 * inspects the previous significant token: after a regex-context keyword
 * (`return`, `typeof`, `throw`, etc.) or after an operator/punctuator
 * the `/` is a regex; after an identifier or `)`/`]` it is division.
 *
 * For aggressive minification (identifier renaming, ASI-aware joining),
 * wire {@see \Akankov\HtmlMin\HtmlMin::setInlineJsMinifier()} to a third-
 * party tool such as `matthiasmullie/minify`.
 */
final class InlineJsMinifier implements InlineMinifier
{
    /**
     * @var list<string>
     */
    private const array REGEX_CONTEXT_KEYWORDS = [
        'return', 'typeof', 'in', 'instanceof', 'delete', 'void',
        'throw', 'new', 'else', 'do', 'case', 'yield', 'await',
    ];

    #[Override]
    public function minify(string $source): string
    {
        $len = \strlen($source);
        if ($len === 0) {
            return '';
        }

        $i = 0;
        $out = '';
        $pendingNl = false;
        $pendingSp = false;
        $lastChar = '';
        $lastIdent = '';

        while ($i < $len) {
            $c = $source[$i];
            $next = $i + 1 < $len ? $source[$i + 1] : '';

            if ($c === '/' && $next === '*') {
                $endPos = strpos($source, '*/', $i + 2);
                $stopAt = $endPos === false ? $len : $endPos + 2;
                $body = substr($source, $i, $stopAt - $i);
                if (str_contains($body, "\n") || str_contains($body, "\r")) {
                    $pendingNl = true;
                } else {
                    $pendingSp = true;
                }
                $i = $stopAt;
                continue;
            }

            if ($c === '/' && $next === '/') {
                $j = $i + 2;
                while ($j < $len && $source[$j] !== "\n" && $source[$j] !== "\r") {
                    $j++;
                }
                $i = $j;
                continue;
            }

            if ($c === '/' && $this->isRegexContext($lastChar, $lastIdent)) {
                $this->flush($out, $pendingNl, $pendingSp);
                $endIdx = $this->scanRegex($source, $i, $len);
                $out .= substr($source, $i, $endIdx - $i);
                $lastChar = '/';
                $lastIdent = '';
                $i = $endIdx;
                continue;
            }

            if ($c === '"' || $c === "'") {
                $this->flush($out, $pendingNl, $pendingSp);
                $endIdx = $this->scanString($source, $i, $c, $len);
                $out .= substr($source, $i, $endIdx - $i);
                $lastChar = $c;
                $lastIdent = '';
                $i = $endIdx;
                continue;
            }

            if ($c === '`') {
                $this->flush($out, $pendingNl, $pendingSp);
                $endIdx = $this->scanTemplate($source, $i, $len);
                $out .= substr($source, $i, $endIdx - $i);
                $lastChar = '`';
                $lastIdent = '';
                $i = $endIdx;
                continue;
            }

            if ($c === "\n") {
                $pendingNl = true;
                $pendingSp = false;
                $i++;
                continue;
            }

            if ($c === "\r") {
                $pendingNl = true;
                $pendingSp = false;
                $i++;
                if (($source[$i] ?? '') === "\n") {
                    $i++;
                }
                continue;
            }

            if ($c === ' ' || $c === "\t" || $c === "\f" || $c === "\v") {
                $pendingSp = true;
                $i++;
                continue;
            }

            if ($this->isIdentStart($c)) {
                $this->flush($out, $pendingNl, $pendingSp);
                $j = $i + 1;
                while ($j < $len && $this->isIdentPart($source[$j])) {
                    $j++;
                }
                $ident = substr($source, $i, $j - $i);
                $out .= $ident;
                $lastChar = $ident[\strlen($ident) - 1];
                $lastIdent = $ident;
                $i = $j;
                continue;
            }

            $this->flush($out, $pendingNl, $pendingSp);
            $out .= $c;
            $lastChar = $c;
            $lastIdent = '';
            $i++;
        }

        return trim($out);
    }

    /**
     * @param-out bool $pendingNl
     * @param-out bool $pendingSp
     */
    private function flush(string &$out, bool &$pendingNl, bool &$pendingSp): void
    {
        if ($out === '') {
            $pendingNl = false;
            $pendingSp = false;

            return;
        }
        if ($pendingNl) {
            $out .= "\n";
        } elseif ($pendingSp) {
            $out .= ' ';
        }
        $pendingNl = false;
        $pendingSp = false;
    }

    private function isIdentStart(string $c): bool
    {
        return ($c >= 'a' && $c <= 'z')
            || ($c >= 'A' && $c <= 'Z')
            || $c === '_'
            || $c === '$';
    }

    private function isIdentPart(string $c): bool
    {
        return $this->isIdentStart($c) || ($c >= '0' && $c <= '9');
    }

    private function isRegexContext(string $lastChar, string $lastIdent): bool
    {
        if ($lastIdent !== '') {
            return \in_array($lastIdent, self::REGEX_CONTEXT_KEYWORDS, true);
        }
        if ($lastChar === '') {
            return true;
        }
        if ($lastChar >= '0' && $lastChar <= '9') {
            return false;
        }
        if ($lastChar === ')' || $lastChar === ']') {
            return false;
        }

        return true;
    }

    private function scanString(string $source, int $start, string $quote, int $len): int
    {
        $i = $start + 1;
        while ($i < $len) {
            $c = $source[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $i += 2;
                continue;
            }
            if ($c === $quote) {
                return $i + 1;
            }
            if ($c === "\n" || $c === "\r") {
                return $i;
            }
            $i++;
        }

        return $len;
    }

    private function scanRegex(string $source, int $start, int $len): int
    {
        $i = $start + 1;
        $inCharClass = false;
        while ($i < $len) {
            $c = $source[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $i += 2;
                continue;
            }
            if ($c === '[') {
                $inCharClass = true;
            } elseif ($c === ']') {
                $inCharClass = false;
            } elseif ($c === '/' && !$inCharClass) {
                $i++;
                while ($i < $len && $this->isIdentPart($source[$i])) {
                    $i++;
                }

                return $i;
            } elseif ($c === "\n" || $c === "\r") {
                return $i;
            }
            $i++;
        }

        return $len;
    }

    private function scanTemplate(string $source, int $start, int $len): int
    {
        $i = $start + 1;
        $depth = 0;
        while ($i < $len) {
            $c = $source[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $i += 2;
                continue;
            }
            if ($depth === 0) {
                if ($c === '`') {
                    return $i + 1;
                }
                if ($c === '$' && ($source[$i + 1] ?? '') === '{') {
                    $depth = 1;
                    $i += 2;
                    continue;
                }
            } else {
                if ($c === '{') {
                    $depth++;
                } elseif ($c === '}') {
                    $depth--;
                }
            }
            $i++;
        }

        return $len;
    }
}
