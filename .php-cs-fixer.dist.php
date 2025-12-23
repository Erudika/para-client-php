<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'if', 'switch', 'try'],
        ],
        'combine_consecutive_unsets' => true,
        'global_namespace_import' => ['import_constants' => true, 'import_functions' => true],
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'php_unit_method_casing' => ['case' => 'snake_case'],
        'phpdoc_align' => ['align' => 'vertical'],
        'phpdoc_summary' => true,
        'phpdoc_to_comment' => false,
        'simplified_null_return' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
    ])
    ->setFinder($finder);
