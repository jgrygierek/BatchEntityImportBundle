<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Symfony73\Rector\Class_\GetFiltersAndFunctionsToAsTwigAttributeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withComposerBased(twig: true, doctrine: true, phpunit: true, symfony: true)
    ->withPhpSets(php82: true)
    ->withPhpVersion(PhpVersion::PHP_82)
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
        // #[AsTwigFunction] requires TwigBundle 7.3+, bundle still supports Symfony 6.4
        GetFiltersAndFunctionsToAsTwigAttributeRector::class,
    ])
    ->withSymfonyContainerXml(
        __DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml',
    );
