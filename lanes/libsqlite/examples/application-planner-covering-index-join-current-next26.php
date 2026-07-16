<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCoveringIndexPlan;

$indexes = [
    [
        'name' => 'idx_meta_option',
        'rootPage' => 81,
        'estimatedRows' => 50000,
        'sql' => 'CREATE INDEX idx_meta_option ON wp_postmeta(option_id)',
    ],
    [
        'name' => 'idx_meta_option_key_value',
        'rootPage' => 82,
        'estimatedRows' => 3000,
        'sql' => 'CREATE INDEX idx_meta_option_key_value ON wp_postmeta(option_id, meta_key, meta_value)',
    ],
    [
        'name' => 'idx_plugin_meta',
        'rootPage' => 87,
        'estimatedRows' => 650,
        'sql' => "CREATE INDEX idx_plugin_meta ON wp_postmeta(option_id, meta_key, meta_value) WHERE meta_key >= 'plugin_'",
    ],
];

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['table' => 'm', 'column' => 'option_id'], 'right' => ['table' => 'o', 'column' => 'option_id']],
        ['operator' => '>=', 'left' => ['table' => 'm', 'column' => 'meta_key'], 'right' => 'plugin_cache'],
    ],
];

$plan = SQLiteCoveringIndexPlan::chooseJoin(
    $indexes,
    $predicate,
    'm',
    ['o.option_id', 'o.option_name'],
    ['m.option_id', 'm.meta_key', 'm.meta_value'],
    [['column' => 'm.meta_key']]
);

if (($argv[1] ?? null) === '--self-test') {
    if (!is_array($plan) || ($plan['name'] ?? null) !== 'idx_plugin_meta') {
        fwrite(STDERR, "expected idx_plugin_meta\n");
        exit(1);
    }
    if (($plan['joinLoop'] ?? null) !== 'current-next') {
        fwrite(STDERR, "expected current-next join loop\n");
        exit(1);
    }
    if (($plan['outerDependencies'] ?? null) !== ['option_id' => 'o.option_id']) {
        fwrite(STDERR, "expected deferred outer option_id dependency\n");
        exit(1);
    }
    if (($plan['covering'] ?? null) !== true || ($plan['partial'] ?? null) !== true) {
        fwrite(STDERR, "expected proved partial covering index\n");
        exit(1);
    }

    echo "application-planner-covering-index-join-current-next26 self-test passed\n";
    exit(0);
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
