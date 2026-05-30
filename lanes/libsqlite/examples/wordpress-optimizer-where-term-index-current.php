<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCoveringIndexPlan;

$indexes = [
    [
        'name' => 'idx_value_name_autoload',
        'rootPage' => 61,
        'estimatedRows' => 1200,
        'sql' => 'CREATE INDEX idx_value_name_autoload ON wp_options(option_value, option_name, autoload)',
        'stat4Samples' => [
            ['values' => ['option_value' => null, 'option_name' => 'empty_option'], 'rows' => 6],
            ['values' => ['option_value' => null, 'option_name' => 'missing_payload'], 'rows' => 3],
            ['values' => ['option_value' => 'a:1', 'option_name' => 'plugin_alpha'], 'rows' => 40],
        ],
    ],
];

$predicate = ['operator' => 'IS NULL', 'left' => ['column' => 'option_value']];
$plan = SQLiteCoveringIndexPlan::choose(
    $indexes,
    $predicate,
    ['option_value', 'option_name', 'autoload'],
    [['column' => 'option_name']]
);

if (($argv[1] ?? null) === '--self-test') {
    if (!is_array($plan) || ($plan['name'] ?? null) !== 'idx_value_name_autoload') {
        fwrite(STDERR, "expected idx_value_name_autoload\n");
        exit(1);
    }
    if (($plan['equalityPrefix'] ?? null) !== 1 || ($plan['usedColumns'] ?? null) !== ['option_value']) {
        fwrite(STDERR, "expected IS NULL equality prefix\n");
        exit(1);
    }
    if (($plan['stat4Used'] ?? null) !== true || ($plan['estimatedRows'] ?? null) !== 9) {
        fwrite(STDERR, "expected NULL STAT4 samples\n");
        exit(1);
    }

    echo "wordpress-optimizer-where-term-index-current self-test passed\n";
    exit(0);
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
