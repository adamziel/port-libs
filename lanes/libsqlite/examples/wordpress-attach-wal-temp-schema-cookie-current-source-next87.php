<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCookieSourcePlan;

$plan = SQLiteAttachWalTempSchemaCookieSourcePlan::plan(
    [
        'main' => [
            'schema_cookie' => 20,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 21, 'commit' => true],
                ['page' => 1, 'schema_cookie' => 22, 'commit' => false],
            ],
            'wal_schema_cookie' => 23,
            'tables' => ['wp_options', 'wp_posts'],
            'next_tables' => ['wp_options', 'wp_posts', 'wp_plugin_state'],
            'file' => '/srv/wp/current.sqlite',
        ],
        'temp' => [
            'schema_cookie' => 4,
            'temp_schema_cookie' => 5,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 99, 'commit' => true],
            ],
            'tables' => ['wp_options_stage'],
            'next_tables' => ['wp_options_stage', 'wp_options'],
            'file' => '',
            'temp' => true,
        ],
        'archive' => [
            'schema_cookie' => 11,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 12, 'commit' => true],
                ['page' => 1, 'schema_cookie' => 13, 'commit' => false],
            ],
            'tables' => ['wp_archive_options'],
            'next_tables' => ['wp_archive_options', 'wp_options'],
            'file' => '/srv/wp/archive.sqlite',
        ],
    ],
    [
        ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
        ['name' => 'stage-insert', 'sql' => 'INSERT INTO wp_options_stage(option_name, option_value) VALUES (?, ?)'],
        ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_archive_options'],
        ['name' => 'plugin-state-reader', 'sql' => 'SELECT option_name FROM main.wp_plugin_state'],
    ],
);

echo json_encode([
    'operation' => $plan['operation'],
    'status' => $plan['status'],
    'changed_schemas' => $plan['changed_schemas'],
    'expired_statements' => $plan['expired_statements'],
    'stable_statements' => $plan['stable_statements'],
    'schema_cookie_sources' => $plan['schema_cookie_sources'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
