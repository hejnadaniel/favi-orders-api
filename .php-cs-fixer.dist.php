<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/migrations'])
    ->name('*.php');

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => true,
        '@PHP84Migration' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => false,
        ],
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => false, 'remove_inheritdoc' => true],
        'ordered_class_elements' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        'phpdoc_to_comment' => false,
        'single_line_throw' => false,
        'yoda_style' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/cache/php-cs-fixer/.php-cs-fixer.cache');
