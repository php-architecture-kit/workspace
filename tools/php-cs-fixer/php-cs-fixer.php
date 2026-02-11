<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        dirname(__DIR__, 2)
    ])
;

return (new PhpCsFixer\Config())
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRiskyAllowed(true)
    ->setRules([
        'global_namespace_import' => [
            'import_classes' => false,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'psr_autoloading' => true,
        'fully_qualified_strict_types' => false,
        'phpdoc_to_comment' => ['ignored_tags' => ['var', 'phpstan-var', 'phpstan-ignore-next-line', 'phpstan-return', 'phpstan-assert', 'phpstan-type', 'phpstan-template']],
        'phpdoc_annotation_without_dot' => false,
        'single_class_element_per_statement' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments'],
        ],
        'method_argument_space' => [
            'keep_multiple_spaces_after_comma' => false,
            'attribute_placement' => 'standalone',
        ],
        'class_attributes_separation' => [
            'elements' => [
                'property' => 'only_if_meta',
            ],
        ],
    ])
    ->setFinder($finder)
;
