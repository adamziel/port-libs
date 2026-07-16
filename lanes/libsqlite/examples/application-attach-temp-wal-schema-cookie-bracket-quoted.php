<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCookieSourcePlan;

$plan = SQLiteAttachWalTempSchemaCookieSourcePlan::bracketQuotedSchemaCookieSourcePlan(
    [
        'main' => [
            'schema_cookie' => 40,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 41, 'commit' => true],
                ['page' => 1, 'schema_cookie' => 44, 'commit' => false],
            ],
            'wal_schema_cookie' => 42,
            'tables' => ['sqlite_schema', 'wp_options', 'wp_posts'],
            'next_tables' => ['sqlite_schema', 'wp_options', 'wp_posts', 'wp_plugin_state'],
            'file' => '/srv/wp/current.sqlite',
        ],
        'temp' => [
            'schema_cookie' => 6,
            'temp_schema_cookie' => 7,
            'tables' => ['sqlite_schema', 'wp_options_stage'],
            'next_tables' => ['sqlite_schema', 'wp_options_stage', 'wp_options'],
            'file' => '',
            'temp' => true,
        ],
        'site' => [
            'schema_cookie' => 10,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 11, 'commit' => true],
            ],
            'tables' => ['sqlite_schema', 'wp_2_options'],
            'next_tables' => ['sqlite_schema', 'wp_2_options', 'wp_options'],
            'file' => '/srv/wp/site.sqlite',
        ],
    ],
    [
        ['name' => 'main-schema-reader', 'sql' => 'SELECT sql FROM [main].[sqlite_schema] WHERE name = ?'],
        ['name' => 'temp-master-reader', 'sql' => 'SELECT name FROM [temp].[sqlite_master] WHERE type = ?'],
        ['name' => 'options-reader', 'sql' => 'SELECT option_value FROM [wp_options] WHERE option_name = ?', 'active' => true],
        ['name' => 'site-options-reader', 'sql' => 'SELECT option_value FROM [site].[wp_2_options] WHERE option_name = ?'],
    ],
);

$summary = [
    'operation' => $plan['operation'],
    'status' => $plan['status'],
    'changed_schemas' => $plan['changed_schemas'],
    'expired_statements' => $plan['expired_statements'],
    'stable_statements' => $plan['stable_statements'],
    'current_option_schema' => $plan['statements'][2]['schema_transitions'][0]['prepare_schema'],
    'next_option_schema' => $plan['statements'][2]['schema_transitions'][0]['next_schema'],
    'schema_cookie_sources' => $plan['schema_cookie_sources'],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['operation'] !== 'attach-temp-wal-schema-cookie-bracket-quoted-source') {
        throw new RuntimeException('Unexpected operation marker');
    }
    if ($summary['current_option_schema'] !== 'main' || $summary['next_option_schema'] !== 'temp') {
        throw new RuntimeException('Expected unqualified bracket wp_options to switch from main to temp');
    }
    if (!in_array('main-schema-reader', $summary['expired_statements'], true)) {
        throw new RuntimeException('Expected bracket main sqlite_schema reader to expire');
    }
    if (!in_array('site-options-reader', $summary['stable_statements'], true)) {
        throw new RuntimeException('Expected qualified attached site reader to stay stable');
    }

    echo "application-attach-temp-wal-schema-cookie-bracket-quoted self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
