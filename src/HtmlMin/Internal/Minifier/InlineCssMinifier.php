<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Internal\Minifier;

use Override;

/**
 * Bundled, zero-dependency minifier for the contents of inline `<style>`
 * blocks. Performs:
 *
 *  - removal of `/* ... *\/` comments outside strings
 *  - collapse of inter-token whitespace runs
 *  - dropping of whitespace adjacent to the structural chars `{}();,:`
 *    (intentionally NOT around `(` or `)` so `@media (min-width:768px)`
 *    keeps its single inner space)
 *  - dropping of a trailing `;` immediately before `}`
 *
 * The contents of strings and `url(...)` blocks are emitted verbatim so
 * that things like `content:"/* keep *\/"` or `url(// foo)` survive.
 */
final class InlineCssMinifier implements InlineMinifier
{
    /**
     * @var list<string>
     */
    private const array STRUCTURAL = ['{', '}', ';', ',', ':'];

    #[Override]
    public function minify(string $source): string
    {
        $len = \strlen($source);
        if ($len === 0) {
            return '';
        }

        $i = 0;
        $out = '';
        $pendingSpace = false;

        while ($i < $len) {
            $c = $source[$i];
            $next = $i + 1 < $len ? $source[$i + 1] : '';

            if ($c === '/' && $next === '*') {
                $end = strpos($source, '*/', $i + 2);
                $i = $end === false ? $len : $end + 2;
                $pendingSpace = true;
                continue;
            }

            if ($c === '"' || $c === "'") {
                $this->emitPendingSpace($out, $pendingSpace);
                $end = $this->scanString($source, $i, $c, $len);
                $out .= substr($source, $i, $end - $i);
                $i = $end;
                continue;
            }

            if (
                $i + 4 <= $len
                && ($source[$i] === 'u' || $source[$i] === 'U')
                && strcasecmp(substr($source, $i, 4), 'url(') === 0
            ) {
                $this->emitPendingSpace($out, $pendingSpace);
                $end = $this->scanUrl($source, $i, $len);
                $out .= substr($source, $i, $end - $i);
                $i = $end;
                continue;
            }

            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r" || $c === "\f") {
                $pendingSpace = true;
                $i++;
                continue;
            }

            if (\in_array($c, self::STRUCTURAL, true)) {
                $pendingSpace = false;
                $out .= $c;
                $i++;
                continue;
            }

            $this->emitPendingSpace($out, $pendingSpace);
            $out .= $c;
            $i++;
        }

        $out = str_replace(';}', '}', $out);

        return trim($out);
    }

    /**
     * @param-out bool $pendingSpace
     */
    private function emitPendingSpace(string &$out, bool &$pendingSpace): void
    {
        if ($pendingSpace && $out !== '') {
            $last = $out[\strlen($out) - 1];
            if (!\in_array($last, self::STRUCTURAL, true)) {
                $out .= ' ';
            }
        }
        $pendingSpace = false;
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
            $i++;
        }

        return $len;
    }

    private function scanUrl(string $source, int $start, int $len): int
    {
        $i = $start + 4; // past 'url('
        while ($i < $len) {
            $c = $source[$i];
            if ($c === '"' || $c === "'") {
                $i = $this->scanString($source, $i, $c, $len);
                continue;
            }
            if ($c === ')') {
                return $i + 1;
            }
            $i++;
        }

        return $len;
    }
}
