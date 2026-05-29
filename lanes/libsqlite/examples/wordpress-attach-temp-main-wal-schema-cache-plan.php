<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachTempMainWalSchemaCachePlan.php';

use PortLibs\LibSqlite\SQLiteAttachTempMainWalSchemaCachePlan;

$plan = SQLiteAttachTempMainWalSchemaCachePlan::currentNextObjects([
    'main' => [
        'schema_cookie' => 30,
        'wal_schema_cookie' => 31,
        'tables' => ['wp_options'],
        'next_tables' => [],
        'indexes' => ['wp_options_name'],
        'next_indexes' => [],
        'file' => 'wp-content/database/.ht.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 4,
        'tables' => ['wp_options'],
        'next_tables' => [],
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 8,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 9, 'commit' => true],
        ],
        'tables' => ['wp_archive_options'],
        'next_tables' => ['wp_options', 'wp_archive_options'],
        'file' => 'wp-content/database/archive.sqlite',
        'cache' => 'shared',
    ],
], ['wp_options', 'main.wp_options', 'archive.wp_options']);

if (($argv[1] ?? '') === '--self-test') {
    $ok = $plan['prepared_tables']['wp_options']['current']['schema'] === 'temp'
        && $plan['prepared_tables']['wp_options']['next']['schema'] === 'archive'
        && $plan['prepared_tables']['main.wp_options']['next']['found'] === false
        && $plan['requires_reprepare'] === true;

    if (!$ok) {
        fwrite(STDERR, "attach temp main WAL schema cache plan smoke failed\n");
        exit(1);
    }
}

printf(
    "status: %s; wp_options current: %s; next: %s; mainNextFound: %s; archiveCookie: %d; reprepare: %s\n",
    $plan['status'],
    $plan['prepared_tables']['wp_options']['current']['schema'],
    $plan['prepared_tables']['wp_options']['next']['schema'],
    $plan['prepared_tables']['main.wp_options']['next']['found'] ? 'yes' : 'no',
    $plan['schema_cookies_next']['archive'],
    $plan['requires_reprepare'] ? 'yes' : 'no',
);
