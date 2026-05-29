<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCookieSourcePlan;

$plan = SQLiteAttachWalTempSchemaCookieSourcePlan::plan(
    [
        'main' => [
            'schema_cookie' => 30,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 31, 'commit' => true],
            ],
            'wal_schema_cookie' => 32,
            'tables' => ['wp_options', 'wp_posts'],
            'next_tables' => ['wp_options', 'wp_posts', 'wp_new_main'],
            'file' => '/srv/wp/current.sqlite',
        ],
        'temp' => [
            'schema_cookie' => 5,
            'temp_schema_cookie' => 6,
            'tables' => ['wp_options_stage'],
            'next_tables' => ['wp_options_stage', 'wp_options'],
            'file' => '',
            'temp' => true,
        ],
        'archive' => [
            'schema_cookie' => 12,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 13, 'commit' => true],
            ],
            'wal_schema_cookie' => 14,
            'tables' => ['wp_options', 'wp_optionmeta', 'wp_archive_state'],
            'next_tables' => ['wp_options', 'wp_optionmeta', 'wp_archive_state', 'wp_archive_new'],
            'file' => '/srv/wp/archive.sqlite',
        ],
    ],
    [
        ['name' => 'archive-view-options', 'source' => 'archive', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
        ['name' => 'archive-view-meta-join', 'source' => 'archive', 'sql' => 'SELECT m.meta_value FROM wp_options AS o JOIN wp_optionmeta AS m ON m.option_id = o.option_id'],
        ['name' => 'main-trigger-stage-insert', 'source' => 'main', 'sql' => 'INSERT INTO wp_options_stage(option_name, option_value) VALUES (?, ?)'],
    ],
);

echo json_encode([
    'operation' => 'wordpress-attach-temp-wal-schema-cache-current-source-next96',
    'status' => $plan['status'],
    'changed_schemas' => $plan['changed_schemas'],
    'expired_statements' => $plan['expired_statements'],
    'archive_source_resolution' => $plan['statements'][0]['schema_transitions'][0],
    'archive_join_schemas' => $plan['statements'][1]['prepare_schemas'],
    'write_retry_statements' => $plan['write_statements_blocked_before_retry'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
