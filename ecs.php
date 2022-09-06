<?php

declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\Import\FullyQualifiedStrictTypesFixer;
use PhpCsFixer\Fixer\Import\GlobalNamespaceImportFixer;
use PhpCsFixer\Fixer\Import\NoUnneededImportAliasFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSpaceFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\Strict\StrictParamFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {
    
    // disabling parallel
    $parameters = $ecsConfig->parameters();
    $parameters->set(Option::PARALLEL, false);
    
    
    $ecsConfig->paths([
        __DIR__ . '/src',
    ]);
    

    $ecsConfig->sets([
         SetList::PSR_12,
         SetList::COMMON,
         SetList::CLEAN_CODE,
    ]);
    
    $ecsConfig->rules([
        YodaStyleFixer::class,
        FullyQualifiedStrictTypesFixer::class,
        NoUnusedImportsFixer::class,
        NoUnneededImportAliasFixer::class,
    ]);

    $ecsConfig->ruleWithConfiguration(ClassAttributesSeparationFixer::class, [
        'elements' => [
            'method' => 'one', 
            'property' => 'one', 
            'trait_import' => 'one', 
            'const' => 'only_if_meta'
        ]
    ]);

    $ecsConfig->ruleWithConfiguration(BlankLineBeforeStatementFixer::class, [
        'statements' => ['if', 'phpdoc', 'return', 'switch', 'throw', 'try']
    ]);

    $ecsConfig->ruleWithConfiguration(GlobalNamespaceImportFixer::class, [
        'import_classes' => false, 
        'import_constants' => false, 
        'import_functions' => false,
    ]);
    
    $ecsConfig->skip([
        StrictParamFixer::class, 
        DeclareStrictTypesFixer::class,
        NotOperatorWithSpaceFixer::class,
        NotOperatorWithSuccessorSpaceFixer::class,
        
        AssignmentInConditionSniff::class,
    ]);
};
