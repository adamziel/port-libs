<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 11,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 12, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 3,
        'tables' => ['wp_options_stage'],
        'temp' => true,
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 4,
        'tables' => ['wp_options_archive'],
        'file' => '/srv/wp/archive.sqlite',
    ],
];

$statements = [
    ['name' => 'options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
    ['name' => 'stage-writer', 'sql' => 'INSERT INTO temp.wp_options_stage(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'archive-reader', 'sql' => 'SELECT option_value FROM archive.wp_options_archive WHERE option_name = ?'],
    ['name' => 'future-plugin-state', 'sql' => 'SELECT option_value FROM wp_plugin_state WHERE option_name = ?'],
];

$events = [
    ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 13, 'table' => 'wp_plugin_state'],
    ['op' => 'detach', 'schema' => 'archive'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::plan($schemas, $statements, $events);

if (in_array('--self-test', $argv, true)) {
    $reader = $plan['statements']['options-reader']['schema_transitions'][0] ?? null;
    $future = $plan['statements']['future-plugin-state']['schema_transitions'][0] ?? null;
    if (
        $plan['status'] !== 'schema_cache_expired'
        || !is_array($reader)
        || $reader['current_schema'] !== 'main'
        || $reader['next_schema'] !== 'temp'
        || !is_array($future)
        || $future['next_schema'] !== 'main'
    ) {
        fwrite(STDERR, "application-attach-wal-temp-schema-cache-consolidated self-test failed\n");
        exit(1);
    }

    echo "application-attach-wal-temp-schema-cache-consolidated self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application attach WAL temp schema cache current-source',
    'applicationUse' => 'Preview prepared wp_options statements during an import that creates a temp shadow table, commits WAL-backed main schema DDL, and detaches an archive database.',
    'status' => $plan['status'],
    'requiresReprepare' => $plan['requires_reprepare'],
    'changedSchemas' => $plan['changed_schemas'],
    'expiredStatements' => $plan['expired_statements'],
    'currentSearchOrder' => $plan['search_order_current'],
    'nextSearchOrder' => $plan['search_order_next'],
    'optionReaderSource' => $plan['statements']['options-reader']['schema_transitions'][0],
    'futurePluginStateSource' => $plan['statements']['future-plugin-state']['schema_transitions'][0],
], JSON_PRETTY_PRINT) . PHP_EOL;
