<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

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
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        rectorPreset: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withComposerBased(
        doctrine: true,
    )
    ->withAttributesSets(all: true)
;
