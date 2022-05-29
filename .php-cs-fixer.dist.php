<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('.github')
    ->exclude('var')
    ->exclude('vendor')
    ->in(__DIR__);

$config = new PhpCsFixer\Config();

return $config
    ->setFinder($finder)
    ->setRules([
        '@PSR1' => true,
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP81Migration' => true,
        '@PHP80Migration:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'global_namespace_import' => ['import_classes' => true],
        'native_function_invocation' => false,
        'static_lambda' => true,
    ])
    ->setRiskyAllowed(true)
;
