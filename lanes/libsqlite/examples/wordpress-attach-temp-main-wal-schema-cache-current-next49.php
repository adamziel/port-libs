<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachTempMainWalSchemaCachePlan.php';

use PortLibs\LibSqlite\SQLiteAttachTempMainWalSchemaCachePlan;

$plan = SQLiteAttachTempMainWalSchemaCachePlan::currentNext([
    'main' => [
        'schema_cookie' => 12,
        'wal_schema_cookie' => 13,
        'tables' => ['wp_options', 'wp_posts'],
        'file' => '/srv/wp/current.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 4,
        'tables' => ['wp_options'],
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 7,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 8, 'commit' => true],
        ],
        'tables' => ['wp_options'],
        'file' => '/srv/wp/archive.sqlite',
        'cache' => 'shared',
    ],
], ['wp_options', 'main.wp_options', 'archive.wp_options']);

if (($argv[1] ?? '') === '--self-test') {
    $ok = $plan['prepared_tables']['wp_options']['schema'] === 'temp'
        && $plan['prepared_tables']['main.wp_options']['requires_reprepare'] === true
        && $plan['schema_cookies_next']['archive'] === 8;

    if (!$ok) {
        fwrite(STDERR, "attach temp/main WAL schema-cache smoke failed\n");
        exit(1);
    }
}

printf(
    "status: %s; unqualified: %s; mainNextCookie: %d; archiveNextCookie: %d; reprepare: %s\n",
    $plan['status'],
    $plan['prepared_tables']['wp_options']['schema'],
    $plan['schema_cookies_next']['main'],
    $plan['schema_cookies_next']['archive'],
    $plan['requires_reprepare'] ? 'yes' : 'no',
);
