<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->phpVersion(80300);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_83,
        SetList::TYPE_DECLARATION,
        SetList::DEAD_CODE,
    ]);

    // This is an extensible library: consumers subclass our classes. Don't let
    // Rector collapse them to `readonly class`, which (unlike property-level
    // readonly) can only be extended by readonly subclasses. Property-level
    // readonly is fine — it keeps value objects immutable without blocking
    // subclassing.
    $rectorConfig->skip([
        ReadOnlyClassRector::class,
    ]);
};
