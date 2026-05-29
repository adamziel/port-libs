<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas605620 = [
    'main' => [
        'schema_cookie' => 604,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next604'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next604'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 604, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 526,
        'tables' => ['wp_theme_stage_publish_retries_next558'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 571,
        'tables' => ['wp_schema_archive_meta_next569'],
        'indexes' => ['wp_schema_archive_key_next568'],
        'file' => '/srv/wp/archive-next605.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 584,
        'tables' => ['wp_schema_handoff_next582', 'wp_schema_handoff_meta_next583'],
        'indexes' => ['wp_schema_handoff_key_next582', 'wp_schema_handoff_meta_key_next594'],
        'file' => '/srv/wp/handoff-next605.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 599,
        'tables' => ['wp_schema_publish_next598', 'wp_schema_publish_meta_next599'],
        'indexes' => ['wp_schema_publish_key_next598', 'wp_schema_publish_meta_key_next599'],
        'file' => '/srv/wp/publish-next605.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 592,
        'tables' => ['wp_job_retry_checkpoint_receipt_next587', 'wp_job_retry_checkpoint_handoff_next592'],
        'indexes' => ['wp_job_retry_checkpoint_receipt_job_next587', 'wp_job_retry_checkpoint_handoff_job_next592'],
        'file' => '/srv/wp/queue-next605.sqlite',
    ],
];

$statements605620 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_publish_final_next604 INDEXED BY wp_navigation_rule_locale_publish_final_key_next604 WHERE final_key = ?', 'active' => true],
    ['name' => 'queue-handoff-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_handoff_next592 INDEXED BY wp_job_retry_checkpoint_handoff_job_next592 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'handoff-meta-reader', 'sql' => 'SELECT meta_id FROM handoff.wp_schema_handoff_meta_next583 INDEXED BY wp_schema_handoff_meta_key_next594 WHERE meta_key = ?'],
    ['name' => 'publish-meta-reader', 'sql' => 'SELECT meta_id FROM publish.wp_schema_publish_meta_next599 INDEXED BY wp_schema_publish_meta_key_next599 WHERE meta_key = ?', 'active' => true],
    ['name' => 'archive-meta-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_meta_next569 WHERE archive_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_next610 INDEXED BY wp_schema_audit_key_next610 WHERE audit_key = ?'],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 WHERE cache_key = ?'],
];

$plan605620 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext605620(
    $schemas ?? $schemas605620,
    $statements ?? $statements605620,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next605-620 extends next589-604 handoff'] = static function (TestRunner $t) use ($plan605620): void {
    $result = $plan605620([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 605, 'table' => 'wp_navigation_rule_locale_publish_receipt_next605', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next605'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 606, 'table' => 'wp_schema_publish_audit_next606', 'indexes' => ['wp_schema_publish_audit_key_next606'], 'commit' => true],
        ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_handoff_next592', 'to' => 'wp_job_retry_checkpoint_final_next607'],
        ['op' => 'drop_index', 'schema' => 'handoff', 'index' => 'wp_schema_handoff_meta_key_next594'],
        ['op' => 'attach', 'schema' => 'audit', 'schema_cookie' => 610, 'tables' => ['wp_schema_audit_next610'], 'indexes' => ['wp_schema_audit_key_next610'], 'file' => '/srv/wp/audit-next610.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 611, 'table' => 'wp_schema_audit_meta_next611', 'indexes' => ['wp_schema_audit_meta_key_next611'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 612, 'table' => 'wp_job_retry_checkpoint_preview_next612', 'indexes' => ['wp_job_retry_checkpoint_preview_job_next612'], 'commit' => false],
        ['op' => 'drop_table', 'schema' => 'archive', 'table' => 'wp_schema_archive_meta_next569'],
        ['op' => 'detach', 'schema' => 'publish'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 620, 'table' => 'wp_navigation_rule_locale_publish_final_next620', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next620'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next605-620', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next605', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next620', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next589', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next604', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(620, $result['schema_cookies_next']['main']);
    $t->same(526, $result['schema_cookies_next']['temp']);
    $t->same(593, $result['schema_cookies_next']['queue']);
    $t->same(585, $result['schema_cookies_next']['handoff']);
    $t->same(572, $result['schema_cookies_next']['archive']);
    $t->same(611, $result['schema_cookies_next']['audit']);
    $t->same(['main-final-reader', 'publish-meta-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-handoff-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-handoff-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-meta-reader']['index_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['publish-meta-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['archive-meta-reader']['schema_transitions'][0]['next_found']);
    $t->same('audit', $result['statements']['audit-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['temp-retry-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next605-620 keeps transient audit preview stable'] = static function (TestRunner $t) use ($plan605620): void {
    $result = $plan605620([
        ['op' => 'attach', 'schema' => 'preview', 'schema_cookie' => 605, 'tables' => ['wp_preview_next605'], 'indexes' => ['wp_preview_key_next605'], 'file' => '/srv/wp/preview-next605.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'preview', 'schema_cookie' => 606, 'table' => 'wp_preview_meta_next606', 'indexes' => ['wp_preview_meta_key_next606'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'preview'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'handoff', 'publish', 'queue'], $result['search_order_next']);
};

return $tests;
