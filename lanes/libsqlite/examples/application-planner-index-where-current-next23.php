<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCoveringIndexPlan;

$indexes = [
    [
        'name' => 'idx_name_plain',
        'rootPage' => 41,
        'estimatedRows' => 20000,
        'sql' => 'CREATE INDEX idx_name_plain ON wp_options(option_name)',
    ],
    [
        'name' => 'idx_plugin_name_cover',
        'rootPage' => 42,
        'estimatedRows' => 600,
        'sql' => "CREATE INDEX idx_plugin_name_cover ON wp_options(option_name, autoload, option_value) WHERE option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_autoload_plugin_cover',
        'rootPage' => 44,
        'estimatedRows' => 750,
        'sql' => "CREATE INDEX idx_autoload_plugin_cover ON wp_options(autoload, option_name, option_value) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ],
];

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_cache'],
    ],
];

$plan = SQLiteCoveringIndexPlan::choose($indexes, $predicate, ['autoload', 'option_name', 'option_value']);

if (($argv[1] ?? null) === '--self-test') {
    if (!is_array($plan) || ($plan['name'] ?? null) !== 'idx_autoload_plugin_cover') {
        fwrite(STDERR, "expected idx_autoload_plugin_cover\n");
        exit(1);
    }
    if (($plan['partial'] ?? null) !== true || ($plan['covering'] ?? null) !== true) {
        fwrite(STDERR, "expected proved partial covering plan\n");
        exit(1);
    }
    if (($plan['usedColumns'] ?? null) !== ['autoload', 'option_name']) {
        fwrite(STDERR, "expected equality plus range prefix\n");
        exit(1);
    }

    echo "application-planner-index-where-current-next23 self-test passed\n";
    exit(0);
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
