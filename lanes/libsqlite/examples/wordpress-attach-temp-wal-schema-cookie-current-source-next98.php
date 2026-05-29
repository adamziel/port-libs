<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaCookieCurrentSourceNextPlan;

$plan = SQLiteAttachTempWalSchemaCookieCurrentSourceNextPlan::plan(
    [
        'main' => [
            'schema_cookie' => 44,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 44, 'commit' => true],
            ],
            'wal_schema_cookie' => 44,
            'tables' => ['wp_options', 'wp_posts'],
            'next_tables' => ['wp_options', 'wp_posts'],
            'schema_roots' => ['wp_options' => 2, 'wp_posts' => 5],
            'next_schema_roots' => ['wp_options' => 2, 'wp_posts' => 5],
            'file' => '/srv/wp/current.sqlite',
        ],
        'temp' => [
            'schema_cookie' => 8,
            'temp_schema_cookie' => 8,
            'tables' => ['wp_options_stage'],
            'next_tables' => ['wp_options_stage', 'wp_options'],
            'schema_roots' => ['wp_options_stage' => 3],
            'next_schema_roots' => ['wp_options_stage' => 3, 'wp_options' => 9],
            'file' => '',
            'temp' => true,
        ],
        'archive' => [
            'schema_cookie' => 17,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 17, 'commit' => true],
            ],
            'tables' => ['wp_archive_options'],
            'next_tables' => ['wp_archive_options'],
            'schema_roots' => ['wp_archive_options' => 6],
            'next_schema_roots' => ['wp_archive_options' => 10],
            'file' => '/srv/wp/archive.sqlite',
        ],
    ],
    [
        ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options WHERE option_name = ?', 'active' => true],
        ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM wp_options'],
        ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_archive_options'],
    ],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['stable_statements'] === ['main-options-reader']);
    assert($plan['expired_statements'] === ['unqualified-options-reader', 'archive-reader']);
    assert($plan['source_only_cookie_move_schemas'] === ['main']);
    assert($plan['changed_root_schemas'] === ['temp', 'archive']);
    echo "wordpress-attach-temp-wal-schema-cookie-current-source-next98 self-test passed\n";
    return;
}

echo json_encode([
    'operation' => $plan['operation'],
    'status' => $plan['status'],
    'stable_statements' => $plan['stable_statements'],
    'expired_statements' => $plan['expired_statements'],
    'source_only_cookie_move_schemas' => $plan['source_only_cookie_move_schemas'],
    'changed_root_schemas' => $plan['changed_root_schemas'],
    'schema_root_signatures' => $plan['schema_root_signatures'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
