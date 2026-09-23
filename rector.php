<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;

return RectorConfig::configure()
    // ->withoutParallel()
    ->withPhpSets(php82: true)
    ->withPHPStanConfigs([
        __DIR__ . '/rector-phpstan.neon',
    ])
    ->withCache(cacheDirectory: __DIR__ . '/tmp/rector')
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRootFiles()
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    // ->withTypeCoverageLevel(23)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
        phpunitMockToStub: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withComposerBased(
        doctrine: true,
        phpunit: true,
    )
    ->withAttributesSets(all: true)
    ->withSkip([
        NewlineBetweenClassLikeStmtsRector::class,
        PreferPHPUnitThisCallRector::class,
    ])
    ->withRules([
        PreferPHPUnitSelfCallRector::class,
    ])
;
