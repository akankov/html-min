<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Middleware;

use Akankov\HtmlMin\HtmlMin;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that minifies the HTML body of an HTTP response on
 * its way out. Buffers the response body, runs it through {@see HtmlMin},
 * and returns a new response with the shorter body.
 *
 * Responses whose `Content-Type` is not in the allowlist (default
 * `text/html`) are returned unchanged so the middleware can sit safely
 * in front of mixed JSON / HTML stacks.
 */
class MinifierMiddleware implements MiddlewareInterface
{
    /**
     * Lower-cased content-type values to minify. Charset parameters and
     * other Content-Type parameters are stripped before comparison.
     *
     * @var list<string>
     */
    private readonly array $contentTypes;

    /**
     * @param list<string> $contentTypes content-types to minify;
     *                                   defaults to `['text/html']`.
     */
    public function __construct(
        private readonly HtmlMin $minifier,
        private readonly StreamFactoryInterface $streamFactory,
        array $contentTypes = ['text/html'],
    ) {
        $this->contentTypes = array_map(strtolower(...), $contentTypes);
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->shouldMinify($response)) {
            return $response;
        }

        $minified = $this->minifier->minify((string) $response->getBody());

        return $response->withBody($this->streamFactory->createStream($minified));
    }

    private function shouldMinify(ResponseInterface $response): bool
    {
        $header = $response->getHeaderLine('Content-Type');
        if ($header === '') {
            return false;
        }

        $type = strtolower(trim(explode(';', $header, 2)[0]));

        return \in_array($type, $this->contentTypes, true);
    }
}
