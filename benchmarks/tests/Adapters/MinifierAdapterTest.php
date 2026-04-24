<?php

declare(strict_types=1);

namespace Akankov\HtmlMinBench\Tests\Adapters;

use Akankov\HtmlMinBench\Adapters\MinifierAdapter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MinifierAdapterTest extends TestCase
{
    public function testIsAnInterface(): void
    {
        $reflection = new ReflectionClass(MinifierAdapter::class);
        self::assertTrue($reflection->isInterface());
    }

    public function testDeclaresRequiredMethods(): void
    {
        $reflection = new ReflectionClass(MinifierAdapter::class);
        self::assertTrue($reflection->hasMethod('name'));
        self::assertTrue($reflection->hasMethod('version'));
        self::assertTrue($reflection->hasMethod('minify'));
        self::assertTrue($reflection->hasMethod('isUnsafeReference'));
    }
}
