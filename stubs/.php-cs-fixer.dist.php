<?php

$finder = (new PhpCsFixer\Finder())
    ->files()
    ->name("*.php")
    ->in("app")
    ->in("bootstrap")
    ->in("config")
    ->in("database")
    ->in("tests")
    ->exclude("cache");

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_class_elements' => true,
        'simplified_null_return' => false,
        'is_null' => false,
        'single_quote' => true,
        'blank_line_after_opening_tag' => false,
        'linebreak_after_opening_tag' => true,
        'unary_operator_spaces' => true,
        'blank_line_before_statement' => false,
        'trailing_comma_in_multiline' => true,

        // PHPDOC Rules
        'phpdoc_align' => false,
        'phpdoc_annotation_without_dot' => false,
        'phpdoc_order' => true,
        'phpdoc_separation' => false,
        'phpdoc_add_missing_param_annotation' => true,
        'no_superfluous_phpdoc_tags' => false,
        'single_trait_insert_per_statement' => false
    ])
    ->setFinder($finder);
