<?php

declare(strict_types=1);

namespace Akankov\HtmlMin\Tests\Cli;

use Akankov\HtmlMin\Cli\Cli;
use Akankov\HtmlMin\HtmlMin;
use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    public function testReadsHtmlFromStdinAndWritesMinifiedToStdout(): void
    {
        // Default invocation with no path argument: read full HTML from
        // stdin, minify, write to stdout, return exit code 0.
        $stdin  = self::stream("<html>\n<body>\n  <p>hi</p>\n</body>\n</html>");
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');
        self::assertNotFalse($stdout);
        self::assertNotFalse($stderr);

        $cli = new Cli(new HtmlMin(), $stdin, $stdout, $stderr);
        $exit = $cli->run(['html-min']);

        self::assertSame(0, $exit);
        self::assertStringNotContainsString("\n", self::read($stdout));
        self::assertSame('', self::read($stderr));
    }

    public function testReadsHtmlFromFileArgument(): void
    {
        // tempnam() returns a unique path in the system temp dir; we let
        // the OS reap it rather than chase teardown bookkeeping.
        $path = tempnam(sys_get_temp_dir(), 'htmlmin');
        self::assertNotFalse($path);
        file_put_contents($path, "<p>\n  hello\n</p>");

        $stdin  = self::stream('');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');
        self::assertNotFalse($stdout);
        self::assertNotFalse($stderr);

        $cli  = new Cli(new HtmlMin(), $stdin, $stdout, $stderr);
        $exit = $cli->run(['html-min', $path]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('hello', self::read($stdout));
        self::assertStringNotContainsString("\n  hello", self::read($stdout));
    }

    public function testWritesToOutputPathWhenFlagGiven(): void
    {
        $out = tempnam(sys_get_temp_dir(), 'htmlmin-out');
        self::assertNotFalse($out);

        $stdin  = self::stream("<p>\n  hi\n</p>");
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');
        self::assertNotFalse($stdout);
        self::assertNotFalse($stderr);

        $cli  = new Cli(new HtmlMin(), $stdin, $stdout, $stderr);
        $exit = $cli->run(['html-min', '--output=' . $out]);

        self::assertSame(0, $exit);
        self::assertSame('', self::read($stdout), 'no stdout when --output is set');
        $written = (string) file_get_contents($out);
        self::assertStringContainsString('hi', $written);
        self::assertStringNotContainsString("\n  hi", $written);
    }

    public function testHelpFlagPrintsUsageAndReturnsZero(): void
    {
        $stdin  = self::stream('');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');
        self::assertNotFalse($stdout);
        self::assertNotFalse($stderr);

        $cli  = new Cli(new HtmlMin(), $stdin, $stdout, $stderr);
        $exit = $cli->run(['html-min', '--help']);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Usage', self::read($stdout));
    }

    public function testMissingInputFileExitsOneAndWritesToStderr(): void
    {
        $stdin  = self::stream('');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');
        self::assertNotFalse($stdout);
        self::assertNotFalse($stderr);

        $cli  = new Cli(new HtmlMin(), $stdin, $stdout, $stderr);
        $exit = $cli->run(['html-min', '/nope/does/not/exist.html']);

        self::assertSame(1, $exit);
        self::assertNotSame('', self::read($stderr));
    }

    public function testMinifyInlineCssFlagStripsStyleComments(): void
    {
        $stdin  = self::stream('<style>a { /* hi */ color: red; }</style>');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');
        self::assertNotFalse($stdout);
        self::assertNotFalse($stderr);

        $cli  = new Cli(new HtmlMin(), $stdin, $stdout, $stderr);
        $exit = $cli->run(['html-min', '--minify-inline-css']);

        self::assertSame(0, $exit);
        $out = self::read($stdout);
        self::assertStringContainsString('<style>a{color:red}</style>', $out);
        self::assertStringNotContainsString('/* hi */', $out);
    }

    public function testMinifyInlineJsFlagStripsScriptComments(): void
    {
        $stdin  = self::stream('<script>// hi' . "\n" . 'let x = 1;</script>');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');
        self::assertNotFalse($stdout);
        self::assertNotFalse($stderr);

        $cli  = new Cli(new HtmlMin(), $stdin, $stdout, $stderr);
        $exit = $cli->run(['html-min', '--minify-inline-js']);

        self::assertSame(0, $exit);
        $out = self::read($stdout);
        self::assertStringNotContainsString('// hi', $out);
        self::assertStringContainsString('let x = 1;', $out);
    }

    public function testUnknownFlagExitsTwoAndWritesToStderr(): void
    {
        $stdin  = self::stream('');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');
        self::assertNotFalse($stdout);
        self::assertNotFalse($stderr);

        $cli  = new Cli(new HtmlMin(), $stdin, $stdout, $stderr);
        $exit = $cli->run(['html-min', '--bogus-flag']);

        self::assertSame(2, $exit);
        self::assertStringContainsString('--bogus-flag', self::read($stderr));
    }

    /**
     * @return resource
     */
    private static function stream(string $content)
    {
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            self::fail('cannot open in-memory stream');
        }

        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private static function read($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
