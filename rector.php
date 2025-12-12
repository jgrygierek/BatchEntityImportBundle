<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Set\TwigSetList;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withComposerBased(phpunit: true)
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
        TwigSetList::TWIG_20,
        SymfonySetList::SYMFONY_54,
    ])
    ->withCodingStyleLevel(10)
    ->withCodeQualityLevel(10)
    ->withSkip([
        ReturnNeverTypeRector::class,
        NewlineAfterStatementRector::class,
    ])
    ->withSymfonyContainerXml(
        __DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml',
    );
