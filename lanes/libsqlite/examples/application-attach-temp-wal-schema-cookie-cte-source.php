<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCookieSourcePlan;

$plan = SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan(
    [
        'main' => [
            'schema_cookie' => 100,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 101, 'commit' => true],
                ['page' => 1, 'schema_cookie' => 107, 'commit' => false],
            ],
            'wal_schema_cookie' => 102,
            'tables' => ['sqlite_schema', 'wp_options', 'wp_posts'],
            'next_tables' => ['sqlite_schema', 'wp_options', 'wp_posts', 'wp_plugin_state'],
            'file' => '/srv/wp/current.sqlite',
        ],
        'temp' => [
            'schema_cookie' => 11,
            'temp_schema_cookie' => 12,
            'tables' => ['sqlite_schema', 'wp_options_stage'],
            'next_tables' => ['sqlite_schema', 'wp_options_stage', 'wp_options'],
            'file' => '',
            'temp' => true,
        ],
        'site' => [
            'schema_cookie' => 20,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 21, 'commit' => true],
            ],
            'tables' => ['sqlite_schema', 'wp_2_options', 'wp_blogs'],
            'next_tables' => ['sqlite_schema', 'wp_2_options', 'wp_blogs'],
            'file' => '/srv/wp/site.sqlite',
        ],
    ],
    [
        [
            'name' => 'cte-options-reader',
            'active' => true,
            'sql' => 'WITH recent_options AS (SELECT option_name FROM main.wp_options WHERE autoload = ?) SELECT option_name FROM recent_options ORDER BY option_name',
        ],
        [
            'name' => 'stage-import',
            'sql' => 'WITH incoming AS (SELECT option_name FROM main.wp_options) INSERT INTO temp.wp_options_stage(option_name) SELECT option_name FROM incoming',
        ],
        [
            'name' => 'site-blog-preview',
            'sql' => 'WITH RECURSIVE blog_tree(id) AS (SELECT blog_id FROM site.wp_blogs UNION ALL SELECT id + 1 FROM blog_tree WHERE id < 3) SELECT id FROM blog_tree',
        ],
    ],
);

$summary = [
    'operation' => $plan['operation'],
    'status' => $plan['status'],
    'changed_schemas' => $plan['changed_schemas'],
    'expired_statements' => $plan['expired_statements'],
    'stable_statements' => $plan['stable_statements'],
    'cte_reader_tables' => $plan['statements'][0]['tables'],
    'stage_import_tables' => $plan['statements'][1]['tables'],
    'site_blog_tables' => $plan['statements'][2]['tables'],
    'active_current_snapshot_statements' => $plan['active_current_snapshot_statements'],
    'write_statements_blocked_before_retry' => $plan['write_statements_blocked_before_retry'],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['operation'] !== 'attach-temp-wal-schema-cookie-cte-source') {
        throw new RuntimeException('Unexpected operation marker');
    }
    if ($summary['cte_reader_tables'] !== ['main.wp_options']) {
        throw new RuntimeException('Expected CTE reader to track only the real main table');
    }
    if ($summary['stage_import_tables'] !== ['main.wp_options', 'temp.wp_options_stage']) {
        throw new RuntimeException('Expected CTE import to track real source and temp target tables');
    }
    if ($summary['site_blog_tables'] !== ['site.wp_blogs']) {
        throw new RuntimeException('Expected recursive CTE self-reference to be ignored');
    }
    if ($summary['active_current_snapshot_statements'] !== ['cte-options-reader']) {
        throw new RuntimeException('Expected active CTE reader to finish current snapshot');
    }
    if ($summary['write_statements_blocked_before_retry'] !== ['stage-import']) {
        throw new RuntimeException('Expected temp stage import write to block before retry');
    }

    echo "application-attach-temp-wal-schema-cookie-cte-source self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
