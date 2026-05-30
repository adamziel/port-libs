<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachTempMainWalSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempStatementLifecyclePlan;

$plan = SQLiteAttachWalTempStatementLifecyclePlan::plan([
    'main' => [
        'schema_cookie' => 40,
        'wal_schema_cookie' => 41,
        'tables' => ['wp_options', 'wp_posts'],
        'next_tables' => ['wp_posts'],
        'indexes' => ['wp_options_name'],
        'next_indexes' => [],
        'file' => 'wp-content/database/.ht.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 7,
        'tables' => ['wp_options_stage'],
        'next_tables' => ['wp_options'],
        'indexes' => ['wp_options_stage_name'],
        'next_indexes' => ['wp_options_name_temp'],
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 10,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 11, 'commit' => true],
        ],
        'tables' => ['wp_archive_options'],
        'next_tables' => ['wp_archive_options', 'wp_options'],
        'file' => 'wp-content/database/archive.sqlite',
        'cache' => 'shared',
    ],
], [
    ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
    ['name' => 'stage-insert', 'sql' => 'INSERT INTO wp_options_stage(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_options'],
]);

if (($argv[1] ?? '') === '--self-test') {
    $ok = $plan['status'] === 'schema_changed'
        && $plan['statements']['0']['current_step_action'] === 'continue_current_snapshot'
        && $plan['statements']['0']['next_step_action'] === 'finish_current_snapshot_then_sqlite_schema_on_reset'
        && $plan['statements']['1']['next_step_action'] === 'sqlite_schema_before_write_retry'
        && $plan['statements']['2']['next_step_action'] === 'sqlite_schema_then_reprepare_read_statement';

    if (!$ok) {
        fwrite(STDERR, "attach WAL temp statement lifecycle plan smoke failed\n");
        exit(1);
    }
}

printf(
    "status: %s; expired: %s; activeNext: %s; stageNext: %s; archiveResult: %s\n",
    $plan['status'],
    implode(',', $plan['expired_statements']),
    $plan['statements']['0']['next_step_action'],
    $plan['statements']['1']['next_step_action'],
    $plan['statements']['2']['sqlite_result'],
);
