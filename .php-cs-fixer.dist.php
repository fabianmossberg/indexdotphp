<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                      => true,
        'declare_strict_types'        => true,
        'array_syntax'                => ['syntax' => 'short'],
        'no_unused_imports'           => true,
        'ordered_imports'             => ['imports_order' => ['class', 'function', 'const']],
        'trailing_comma_in_multiline' => true,
        'single_quote'                => true,
    ])
    ->setFinder($finder);
