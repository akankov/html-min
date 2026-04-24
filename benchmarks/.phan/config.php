<?php

declare(strict_types=1);

return [
    'target_php_version' => '8.3',
    'minimum_target_php_version' => '8.3',
    'allow_missing_properties' => false,
    'null_casts_as_any_type' => false,
    'null_casts_as_array' => false,
    'array_casts_as_null' => false,
    'scalar_implicit_cast' => false,
    'ignore_undeclared_variables_in_global_scope' => false,
    'backward_compatibility_checks' => false,
    'check_docblock_signature_return_type_match' => true,
    'dead_code_detection' => false,
    'unused_variable_detection' => true,
    'redundant_condition_detection' => true,
    'assume_no_external_class_overrides' => false,
    'minimum_severity' => 0,
    'directory_list' => [
        'src',
        'tests',
        'bin',
        'vendor',
    ],
    'exclude_analysis_directory_list' => [
        'vendor/',
    ],
    'plugins' => [
        'AlwaysReturnPlugin',
        'DollarDollarPlugin',
        'DuplicateArrayKeyPlugin',
        'PregRegexCheckerPlugin',
        'UnreachableCodePlugin',
        'UseReturnValuePlugin',
    ],
    'suppress_issue_types' => [
        'PhanUnreferencedUseNormal',
    ],
];
