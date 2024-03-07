<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;

return RectorConfig::configure()
    // ->withoutParallel()
    ->withPhpSets(php81: true)
    ->withPHPStanConfigs([
        __DIR__ . '/rector-phpstan.neon',
    ])
    ->withCache(cacheDirectory: __DIR__ . '/tmp/rector')
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withRootFiles()
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    // ->withTypeCoverageLevel(23)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        strictBooleans: true,
        instanceOf: true,
        earlyReturn: true,
        naming: true
    )
    ->withAttributesSets(doctrine: true)
    ->withSets([
        DoctrineSetList::DOCTRINE_BUNDLE_210,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        DoctrineSetList::DOCTRINE_DBAL_40,
    ])
    ->withRules([
        InlineConstructorDefaultToPropertyRector::class,
    ])
;
