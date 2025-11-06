<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 06/11/25
*/

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

    $rectorConfig->rules([
        TypedPropertyFromStrictConstructorRector::class,
    ]);

    $rectorConfig->sets([
        \Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_82,
        \Rector\Set\ValueObject\SetList::DEAD_CODE,
        \Rector\Set\ValueObject\SetList::CODE_QUALITY,
        \Rector\Set\ValueObject\SetList::CODING_STYLE,
        \Rector\Set\ValueObject\SetList::NAMING,
        \Rector\Set\ValueObject\SetList::TYPE_DECLARATION,
        \Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_100,
    ]);
};
