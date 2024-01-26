<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);

    // $rectorConfig->disableParallel();
    $rectorConfig->parallel(maxNumberOfProcess: 2);

    // is your PHP version different from the one you refactor to? [default: your PHP version], uses PHP_VERSION_ID format
    $rectorConfig->phpVersion(PhpVersion::PHP_81);

    // Ensure file system caching is used instead of in-memory.
    // $rectorConfig->cacheClass(\Rector\Caching\ValueObject\Storage\FileCacheStorage::class);
    $rectorConfig->cacheDirectory('./tmp/rector');

    // Path to PHPStan with extensions, that PHPStan in Rector uses to determine types
    $rectorConfig->phpstanConfig(__DIR__ . '/rector-phpstan.neon');

    $rectorConfig->paths([
        __DIR__ . '/ecs.php',
        __DIR__ . '/rector.php',
        __DIR__ . '/src',
    ]);

    // define sets of rules
    $rectorConfig->sets([
        /*
         # Uncomment to enable them just once during the upgrade period
         *
            \Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_82,
         *
         */
        SetList::PHP_81,

        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        DoctrineSetList::DOCTRINE_ORM_29,
        DoctrineSetList::DOCTRINE_DBAL_30,

        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::NAMING,
        SetList::TYPE_DECLARATION,
        SetList::INSTANCEOF,
    ]);

    /*
     *
    $rectorConfig->skip([
        NewlineBeforeNewAssignSetRector::class,
        NewlineAfterStatementRector::class,
        RenamePropertyToMatchTypeRector::class,
    ]);
     */

    // register a single rule
    $rectorConfig->rules([
        InlineConstructorDefaultToPropertyRector::class,
    ]);
};
