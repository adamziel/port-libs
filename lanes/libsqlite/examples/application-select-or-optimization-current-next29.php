<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteOrOptimizationPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$or = static fn (array ...$terms): array => ['operator' => 'OR', 'terms' => $terms];

$indexes = [
    [
        'name' => 'wp_options_autoload',
        'rootPage' => 12,
        'estimatedRows' => 18000,
        'coveringColumns' => ['option_id', 'option_name', 'autoload'],
        'sql' => 'CREATE INDEX wp_options_autoload ON wp_options(autoload)',
    ],
    [
        'name' => 'wp_options_option_name',
        'rootPage' => 14,
        'estimatedRows' => 24000,
        'coveringColumns' => ['option_id', 'option_name', 'autoload'],
        'sql' => 'CREATE INDEX wp_options_option_name ON wp_options(option_name)',
    ],
];

$autoloadOrTransient = SQLiteOrOptimizationPlan::choose(
    $indexes,
    $or($point('autoload', 'yes'), $range('option_name', '>=', '_transient_')),
    ['option_id', 'option_name', 'autoload']
);

$namedOptions = SQLiteOrOptimizationPlan::choose(
    $indexes,
    $or($point('option_name', 'siteurl'), $point('option_name', 'home'), $point('option_name', 'blogname')),
    ['option_id', 'option_name', 'autoload']
);

echo json_encode([
    'autoload_or_transient' => [
        'strategy' => $autoloadOrTransient['strategy'],
        'indexes' => $autoloadOrTransient['indexes'],
        'requires_rowid_union' => $autoloadOrTransient['requiresRowidUnion'],
        'deduplicates_rowids' => $autoloadOrTransient['deduplicatesRowids'],
        'residual_predicate_required' => $autoloadOrTransient['residualPredicateRequired'],
        'estimated_rows' => $autoloadOrTransient['estimatedRows'],
    ],
    'named_options' => [
        'strategy' => $namedOptions['strategy'],
        'index' => $namedOptions['index'],
        'operator' => $namedOptions['operator'],
        'values' => $namedOptions['values'],
        'requires_rowid_union' => $namedOptions['requiresRowidUnion'],
        'estimated_rows' => $namedOptions['estimatedRows'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
