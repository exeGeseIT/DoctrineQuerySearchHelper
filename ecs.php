<?php

declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSpaceFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use PhpCsFixer\Fixer\Strict\StrictComparisonFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withCache(directory: './tmp/ecs')
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPreparedSets(psr12: true, common: true)
    ->withSets([
        SetList::CLEAN_CODE,
    ])
    ->withPhpCsFixerSets(perCS: true, symfony: true)
    ->withRules([
        NoUnusedImportsFixer::class,
    ])
    ->withConfiguredRule(ClassAttributesSeparationFixer::class, [
        'elements' => [
            'method' => 'one',
            'property' => 'one',
            'trait_import' => 'one',
            'const' => 'only_if_meta',
        ],
    ])
    ->withConfiguredRule(YodaStyleFixer::class, [
        'equal' => true,
        'identical' => true,
        'less_and_greater' => null,
        'always_move_variable' => true,
    ])
    ->withConfiguredRule(BlankLineBeforeStatementFixer::class, [
        'statements' => ['if', 'phpdoc', 'return', 'switch', 'throw', 'try'],
    ])
    ->withSkip([
        //        StrictComparisonFixer::class => [
        //            __DIR__ . '/src/Helper/SearchData/DatasearchAbstract.php',
        //        ],
        NotOperatorWithSpaceFixer::class,
        NotOperatorWithSuccessorSpaceFixer::class,

        // PhpCsFixer\Fixer\Strict\StrictParamFixer::class,
        // PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer::class,
        // PhpCsFixer\Fixer\FunctionNotation\NullableTypeDeclarationForDefaultNullValueFixer::class,

        AssignmentInConditionSniff::class,
    ])
    ->withRootFiles();
