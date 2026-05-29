<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachTempMainWalSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempTransactionCurrentNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentNextPlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentNextPlan;

$schemas = [
    'main' => [
        'schema_cookie' => 41,
        'wal_schema_cookie' => 42,
        'tables' => ['wp_options', 'wp_posts'],
        'next_tables' => ['wp_options', 'wp_posts', 'wp_plugin_state'],
        'indexes' => ['wp_options_name'],
        'next_indexes' => ['wp_options_name', 'wp_plugin_state_name'],
        'file' => '/srv/wp/current.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 7,
        'tables' => ['wp_options_stage'],
        'next_tables' => ['wp_options'],
        'indexes' => ['wp_options_stage_name'],
        'next_indexes' => ['wp_options_name_temp'],
        'temp' => true,
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 11,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 12, 'commit' => true],
        ],
        'tables' => ['wp_archive_options'],
        'next_tables' => ['wp_archive_options', 'wp_options'],
        'indexes' => [],
        'next_indexes' => ['wp_options_name'],
        'file' => '/srv/wp/archive.sqlite',
        'cache' => 'shared',
    ],
    'network' => [
        'schema_cookie' => 3,
        'tables' => ['wp_blogs'],
        'next_tables' => ['wp_blogs'],
        'indexes' => [],
        'next_indexes' => [],
        'file' => '/srv/wp/network.sqlite',
    ],
];

$operations = [
    ['op' => 'schema_write', 'schema' => 'main', 'object' => 'wp_plugin_state'],
    ['op' => 'savepoint', 'savepoint' => 'plugin_import'],
    ['op' => 'schema_write', 'schema' => 'temp', 'object' => 'wp_options_stage'],
    ['op' => 'schema_write', 'schema' => 'archive', 'object' => 'wp_options_name'],
    ['op' => 'rollback_to', 'savepoint' => 'plugin_import'],
    ['op' => 'schema_write', 'schema' => 'network', 'object' => 'wp_blogs_domain'],
    ['op' => 'release', 'savepoint' => 'plugin_import'],
];

$statements = [
    ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
    ['name' => 'stage-insert', 'sql' => 'INSERT INTO wp_options_stage(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'archive-options-reader', 'sql' => 'SELECT option_name FROM archive.wp_options'],
    ['name' => 'network-reader', 'sql' => 'SELECT blog_id FROM network.wp_blogs WHERE domain = ?'],
    ['name' => 'plugin-state-reader', 'sql' => 'SELECT option_name FROM main.wp_plugin_state'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentNextPlan::plan($schemas, $operations, $statements);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'schema_cache_expired');
    assert($plan['changed_schemas'] === ['main', 'network']);
    assert($plan['active_current_snapshot_statements'] === ['active-options-reader']);
    assert($plan['retryable_read_statements'] === ['active-options-reader', 'network-reader', 'plugin-state-reader']);
    assert($plan['write_statements_blocked_before_retry'] === []);
    echo "wordpress-attach-wal-temp-schema-cache-current self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'copied wp_options attach WAL temp schema-cache current',
    'wordpressUse' => 'During plugin import, active copied wp_options readers keep their current snapshot while committed main/network schema writes expire the next prepared-statement cache; rolled-back temp/archive savepoint DDL does not add committed schema-cookie changes.',
    'status' => $plan['status'],
    'changedSchemas' => $plan['changed_schemas'],
    'activeCurrentSnapshotStatements' => $plan['active_current_snapshot_statements'],
    'retryableReadStatements' => $plan['retryable_read_statements'],
    'writeStatementsBlockedBeforeRetry' => $plan['write_statements_blocked_before_retry'],
    'schemaCookiesCurrent' => $plan['schema_cookies_current'],
    'schemaCookiesNext' => $plan['schema_cookies_next'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
