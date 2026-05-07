<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Middleware;

use Akankov\HtmlMin\HtmlMin;
use Akankov\HtmlMin\Middleware\MinifierMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Override;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MinifierMiddlewareTest extends TestCase
{
    public function testMinifiesHtmlResponseBody(): void
    {
        // Given a handler that returns a verbose text/html response, the
        // middleware should hand the body through HtmlMin::minify() and
        // return a response with the shorter body. This is the core
        // contract — anything else is policy.
        $factory = new Psr17Factory();
        $handler = new StubHandler(
            $factory->createResponse(200)
                    ->withHeader('Content-Type', 'text/html; charset=utf-8')
                    ->withBody($factory->createStream("<html>\n<body>\n  <p>hi</p>\n</body>\n</html>")),
        );

        $middleware = new MinifierMiddleware(new HtmlMin(), $factory);

        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        $body = (string) $response->getBody();
        self::assertStringNotContainsString("\n", $body, 'collapsed output should not contain raw newlines');
        self::assertStringContainsString('<p>hi', $body);
    }

    public function testLeavesNonHtmlResponseUntouched(): void
    {
        // The middleware must not minify JSON / XML / binary payloads.
        // Default content-type allowlist is text/html only.
        $factory = new Psr17Factory();
        $jsonBody = "{\n  \"verbose\": true\n}";
        $handler = new StubHandler(
            $factory->createResponse(200)
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($factory->createStream($jsonBody)),
        );

        $middleware = new MinifierMiddleware(new HtmlMin(), $factory);

        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        self::assertSame($jsonBody, (string) $response->getBody(), 'non-allowlisted content-type must pass through');
    }

    public function testCustomContentTypeAllowlistIsRespected(): void
    {
        // Consumers who serve text/html;charset=utf-8 from a CDN-like
        // proxy might want to also minify application/xhtml+xml. Verify
        // the allowlist constructor argument actually drives the choice.
        $factory = new Psr17Factory();
        $body = "<root>\n  <p>x</p>\n</root>";
        $handler = new StubHandler(
            $factory->createResponse(200)
                    ->withHeader('Content-Type', 'application/xhtml+xml')
                    ->withBody($factory->createStream($body)),
        );

        $middleware = new MinifierMiddleware(
            new HtmlMin(),
            $factory,
            ['application/xhtml+xml'],
        );

        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        self::assertStringNotContainsString("\n", (string) $response->getBody());
    }

    public function testParsesContentTypeWithCharsetParameter(): void
    {
        // `Content-Type: text/html; charset=utf-8` must be treated as
        // text/html — the parameter portion gets stripped before the
        // allowlist comparison.
        $factory = new Psr17Factory();
        $handler = new StubHandler(
            $factory->createResponse(200)
                    ->withHeader('Content-Type', 'text/HTML; charset=UTF-8')
                    ->withBody($factory->createStream("<p>\n  x\n</p>")),
        );

        $middleware = new MinifierMiddleware(new HtmlMin(), $factory);

        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        self::assertStringNotContainsString("\n", (string) $response->getBody());
    }
}

final readonly class StubHandler implements RequestHandlerInterface
{
    public function __construct(private ResponseInterface $response)
    {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}
