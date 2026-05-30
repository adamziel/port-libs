<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachTempMainWalSchemaCachePlan.php';

use PortLibs\LibSqlite\SQLiteAttachTempMainWalSchemaCachePlan;

$plan = SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql([
    'main' => [
        'schema_cookie' => 21,
        'wal_schema_cookie' => 22,
        'tables' => ['wp_options', 'wp_posts', 'wp_optionmeta'],
        'file' => '/srv/wp/current.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 5,
        'tables' => ['wp_options', 'wp_temp_import'],
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 9,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 10, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_archive_meta'],
        'file' => '/srv/wp/archive.sqlite',
        'cache' => 'shared',
    ],
], [
    "SELECT option_value FROM wp_options WHERE option_name = 'siteurl'",
    'SELECT main.wp_options.option_name FROM main.wp_options JOIN archive.wp_archive_meta ON archive.wp_archive_meta.option_id = main.wp_options.option_id',
    'INSERT INTO archive.wp_options(option_name, option_value) VALUES(?, ?)',
]);

if (($argv[1] ?? '') === '--self-test') {
    $ok = $plan['statements']['0']['resolved_tables']['wp_options']['schema'] === 'temp'
        && $plan['statements']['0']['requires_reprepare'] === false
        && $plan['statements']['1']['requires_reprepare'] === true
        && $plan['statements']['2']['resolved_tables']['archive.wp_options']['next_schema_cookie'] === 10;

    if (!$ok) {
        fwrite(STDERR, "application-attach-temp-wal-schema-cache-current-next53 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-attach-temp-wal-schema-cache-current-next53 self-test passed\n");
    exit(0);
}

printf(
    "status: %s; statements: %d; tempSelectReprepare: %s; qualifiedJoinReprepare: %s; changedSchemas: %s\n",
    $plan['status'],
    $plan['statement_count'],
    $plan['statements']['0']['requires_reprepare'] ? 'yes' : 'no',
    $plan['statements']['1']['requires_reprepare'] ? 'yes' : 'no',
    implode(',', $plan['changed_schemas']),
);
