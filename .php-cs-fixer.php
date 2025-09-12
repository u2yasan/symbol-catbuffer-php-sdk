<?php

$rules = [
    '@PHP80Migration:risky' => true,
    '@PHPUnit100Migration:risky' => true,
    '@Symfony' => true,
    '@Symfony:risky' => true,

    'declare_strict_types' => true,
    'native_function_invocation' => ['include' => ['@all']],
    'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
    'no_unused_imports' => true,
    'array_syntax' => ['syntax' => 'short'],
    'blank_line_before_statement' => ['statements' => ['return', 'try', 'if', 'for', 'foreach', 'while']],
    'phpdoc_align' => ['align' => 'left'],
    'phpdoc_to_comment' => false,
    'phpdoc_no_empty_return' => false,
    'no_superfluous_phpdoc_tags' => false, // array-shape のために DocBlock を残す
];

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__.'/src', __DIR__.'/tests'])
    );
