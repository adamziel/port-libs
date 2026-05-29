<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas125128 = [
    'main' => [
        'schema_cookie' => 60,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name'],
    ],
    'temp' => [
        'schema_cookie' => 11,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 20,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive-a.sqlite',
    ],
];

$statements125128 = [
    ['name' => 'unqualified-options', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-qualified', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name = ?'],
    ['name' => 'temp-indexed-write', 'sql' => 'UPDATE temp.wp_import_queue INDEXED BY wp_import_queue_status SET status = ? WHERE option_name = ?'],
];

$plan125128 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext125128(
    $schemas ?? $schemas125128,
    $statements ?? $statements125128,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next125 detach reattach refreshes attached schema source'] = static function (TestRunner $t) use ($plan125128): void {
    $result = $plan125128([
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 1, 'tables' => ['wp_options'], 'indexes' => ['wp_archive_option_slug'], 'file' => '/srv/wp/archive-b.sqlite'],
    ], [
        ['name' => 'archive-old-index', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name = ?'],
        ['name' => 'archive-new-index', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_slug WHERE option_name = ?'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next125-128', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next125', $result['dependencies'][0]);
    $t->same(['archive'], $result['changed_schemas']);
    $t->same(false, $result['statements']['archive-old-index']['index_transitions'][0]['next_found']);
    $t->same(true, $result['statements']['archive-new-index']['index_transitions'][0]['next_found']);
    $t->same(['archive-old-index', 'archive-new-index'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next126 attached wal cookie expires qualified and unqualified readers'] = static function (TestRunner $t) use ($plan125128): void {
    $withoutMainOptions = [
        'main' => ['schema_cookie' => 60, 'tables' => ['wp_posts'], 'indexes' => []],
        'temp' => ['schema_cookie' => 11, 'tables' => [], 'indexes' => [], 'temp' => true],
        'archive' => [
            'schema_cookie' => 20,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 21, 'commit' => true],
            ],
            'tables' => ['wp_options'],
            'indexes' => ['wp_archive_option_name'],
        ],
    ];

    $result = $plan125128([
        ['op' => 'wal_commit', 'schema' => 'archive', 'table' => 'wp_options'],
    ], [
        ['name' => 'archive-qualified', 'sql' => 'SELECT option_name FROM archive.wp_options WHERE option_name = ?'],
        ['name' => 'unqualified-attached', 'sql' => 'SELECT option_name FROM wp_options WHERE option_name = ?'],
    ], $withoutMainOptions);

    $t->same(22, $result['schema_cookies_next']['archive']);
    $t->same(['archive'], $result['changed_schemas']);
    $t->same('archive', $result['statements']['unqualified-attached']['schema_transitions'][0]['current_schema']);
    $t->same(true, $result['statements']['archive-qualified']['schema_transitions'][0]['schema_cookie_changed']);
    $t->same(['archive-qualified', 'unqualified-attached'], $result['expired_statements']);
};

$tests['attach temp wal schema cache current source next127 temp create index unblocks indexed by reprepare'] = static function (TestRunner $t) use ($plan125128): void {
    $result = $plan125128([
        ['op' => 'create_index', 'schema' => 'temp', 'index' => 'wp_import_queue_status'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same(false, $result['statements']['temp-indexed-write']['index_transitions'][0]['current_found']);
    $t->same(true, $result['statements']['temp-indexed-write']['index_transitions'][0]['next_found']);
    $t->same(['temp-indexed-write'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next128 ignores rolled back temp and wal cache noise'] = static function (TestRunner $t) use ($plan125128): void {
    $result = $plan125128([
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options', 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'main', 'table' => 'wp_options', 'commit' => false],
    ], [
        ['name' => 'main-options', 'sql' => 'SELECT option_value FROM main.wp_options WHERE option_name = ?'],
        ['name' => 'unqualified-options', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ]);

    $t->same(0, $result['event_count']);
    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same(['main-options', 'unqualified-options'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next125 128 rejects reattach without detach'] = static function (TestRunner $t) use ($plan125128): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan125128([
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 1, 'tables' => ['wp_options']],
    ]));
};

return $tests;
