<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachSchemaCookieRepreparePlan;

$plan = SQLiteAttachSchemaCookieRepreparePlan::plan(
    [
        'main' => [
            'schema_cookie' => 81,
            'wal_schema_cookie' => 82,
            'tables' => ['wp_options', 'wp_posts'],
            'file' => '/srv/wp/current.sqlite',
        ],
        'temp' => [
            'schema_cookie' => 4,
            'tables' => ['wp_stage_options'],
            'file' => '',
            'temp' => true,
        ],
        'archive' => [
            'schema_cookie' => 12,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 13, 'commit' => true],
            ],
            'tables' => ['wp_archive_options'],
            'file' => '/srv/wp/archive.sqlite',
        ],
        'network' => [
            'schema_cookie' => 3,
            'tables' => ['wp_blogs'],
            'file' => '/srv/wp/network.sqlite',
        ],
    ],
    [
        ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
        ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_archive_options'],
        ['name' => 'network-reader', 'sql' => 'SELECT blog_id FROM network.wp_blogs WHERE domain = ?'],
        ['name' => 'stage-insert', 'sql' => 'INSERT INTO wp_stage_options(option_name, option_value) VALUES (?, ?)'],
        ['name' => 'theme-update', 'sql' => 'UPDATE main.wp_posts SET post_title = ? WHERE ID = ?'],
        ['name' => 'new-blog-reader', 'sql' => 'SELECT option_value FROM blog42.wp_options WHERE option_name = ?'],
    ],
    [
        ['op' => 'attach', 'schema' => 'blog42', 'schema_cookie' => 1, 'tables' => ['wp_options'], 'file' => '/srv/wp/blog42.sqlite'],
        ['op' => 'schema_write', 'schema' => 'main', 'object' => 'wp_plugin_state'],
        ['op' => 'detach', 'schema' => 'network'],
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 14],
    ],
);

echo json_encode([
    'operation' => $plan['operation'],
    'status' => $plan['status'],
    'expired_statements' => $plan['expired_statements'],
    'stable_statements' => $plan['stable_statements'],
    'active_current_snapshot_statements' => $plan['active_current_snapshot_statements'],
    'retryable_read_statements' => $plan['retryable_read_statements'],
    'write_statements_blocked_before_retry' => $plan['write_statements_blocked_before_retry'],
    'schema_cookies_next' => $plan['schema_cookies_next'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
