<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas145148 = [
    'main' => [
        'schema_cookie' => 145,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 146, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_type_status'],
    ],
    'temp' => [
        'schema_cookie' => 45,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_key'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 75,
        'tables' => ['wp_options_archive', 'wp_terms'],
        'indexes' => ['wp_archive_option_name', 'wp_terms_slug'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$statements145148 = [
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'temp-active-reader', 'sql' => 'SELECT payload FROM temp.wp_import_queue WHERE import_key = ?', 'active' => true],
    ['name' => 'archive-indexed-reader', 'sql' => 'SELECT name FROM archive.wp_terms INDEXED BY wp_terms_slug WHERE slug = ?'],
    ['name' => 'archive-writer', 'sql' => 'UPDATE archive.wp_options_archive SET option_value = ? WHERE option_name = ?'],
];

$plan145148 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas145148,
    $statements ?? $statements145148,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next145 attach report shadows missing unqualified table'] = static function (TestRunner $t) use ($plan145148): void {
    $result = $plan145148([
        ['op' => 'attach', 'schema' => 'report', 'schema_cookie' => 145, 'tables' => ['wp_reports'], 'indexes' => ['wp_reports_name'], 'file' => '/srv/wp/report.sqlite'],
    ], [
        ['name' => 'report-reader', 'sql' => 'SELECT report_name FROM wp_reports WHERE report_id = ?'],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same(['report'], $result['changed_schemas']);
    $t->same('main', $result['statements']['report-reader']['schema_transitions'][0]['current_schema']);
    $t->same('report', $result['statements']['report-reader']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['report-reader']['schema_transitions'][0]['resolution_changed']);
    $t->same(['report-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next146 drop temp queue lets active reader finish current snapshot'] = static function (TestRunner $t) use ($plan145148): void {
    $result = $plan145148([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_queue'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same('temp', $result['statements']['temp-active-reader']['schema_transitions'][0]['current_schema']);
    $t->same('temp', $result['statements']['temp-active-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['temp-active-reader']['schema_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['temp-active-reader']['next_step_action']);
    $t->same(['temp-active-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next147 archive rename index expires indexed reader'] = static function (TestRunner $t) use ($plan145148): void {
    $result = $plan145148([
        ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_terms_slug', 'to' => 'wp_terms_slug_next147'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(true, $result['statements']['archive-indexed-reader']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['archive-indexed-reader']['index_transitions'][0]['next_found']);
    $t->same(true, $result['statements']['archive-indexed-reader']['index_transitions'][0]['requires_reprepare']);
    $t->same(['archive-indexed-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next148 detach archive blocks writer before retry'] = static function (TestRunner $t) use ($plan145148): void {
    $result = $plan145148([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['archive-writer']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['archive-writer']['schema_transitions'][0]['next_found']);
    $t->same(['archive-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next145 148 consolidates duplicate committed wal events'] = static function (TestRunner $t) use ($plan145148): void {
    $result = $plan145148([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 147, 'indexes' => ['wp_options_name_next148']],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 148, 'indexes' => ['wp_options_name_next148']],
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 148, 'commit' => false],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(147, $result['schema_cookies_next']['main']);
    $t->same(['main'], $result['changed_schemas']);
    $t->same(['main-options-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next145 148 rejects duplicate attach schema'] = static function (TestRunner $t) use ($plan145148): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan145148([
        ['op' => 'attach', 'schema' => 'archive', 'tables' => ['wp_shadow']],
    ]));
};

return $tests;
