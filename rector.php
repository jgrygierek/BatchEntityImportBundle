<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withComposerBased(twig: true, doctrine: true, phpunit: true, symfony: true)
    ->withPhpSets(php84: true)
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withPreparedSets(
        typeDeclarations: true,
        instanceOf: true,
        symfonyConfigs: true,
    )
    ->withAttributesSets(symfony: true, doctrine: true, phpunit: true)
    ->withImportNames(removeUnusedImports: true)
    ->withSets([
        SetList::TYPE_DECLARATION,
    ])
    ->withCodingStyleLevel(10)
    ->withCodeQualityLevel(10)
    ->withSkip([
        ReturnNeverTypeRector::class,
        NewlineAfterStatementRector::class,
        NewlineBetweenClassLikeStmtsRector::class,
        // interface of optional package can't be referenced by ::class, because it would be reported as not existing class
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/src/Utils/EntityTranslationHelper.php',
        ],
    ])
    ->withSymfonyContainerXml(
        __DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml',
    );
