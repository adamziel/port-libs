<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas669684 = [
    'main' => [
        'schema_cookie' => 668,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next668'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next668'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 668, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 526,
        'tables' => ['wp_theme_stage_publish_retries_next558'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 575,
        'tables' => ['wp_schema_archive_preview_next628', 'wp_schema_archive_receipt_next662'],
        'indexes' => ['wp_schema_archive_receipt_key_next662'],
        'file' => '/srv/wp/archive-next669.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 661,
        'tables' => ['wp_schema_audit_next660', 'wp_schema_audit_meta_next661'],
        'indexes' => ['wp_schema_audit_key_next660', 'wp_schema_audit_meta_key_next661'],
        'file' => '/srv/wp/audit-next669.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 596,
        'tables' => ['wp_job_retry_checkpoint_sealed_next623', 'wp_job_retry_checkpoint_preview_next620'],
        'indexes' => ['wp_job_retry_checkpoint_preview_job_next655'],
        'file' => '/srv/wp/queue-next669.sqlite',
    ],
];

$statements669684 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_publish_final_next668 INDEXED BY wp_navigation_rule_locale_publish_final_key_next668 WHERE final_key = ?', 'active' => true],
    ['name' => 'queue-preview-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_preview_next620 INDEXED BY wp_job_retry_checkpoint_preview_job_next655 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'archive-receipt-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next662 INDEXED BY wp_schema_archive_receipt_key_next662 WHERE archive_key = ?', 'active' => true],
    ['name' => 'audit-meta-reader', 'sql' => 'SELECT meta_id FROM audit.wp_schema_audit_meta_next661 INDEXED BY wp_schema_audit_meta_key_next661 WHERE meta_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_next676 INDEXED BY wp_schema_handoff_key_next676 WHERE handoff_key = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_next679 INDEXED BY wp_schema_publish_key_next679 WHERE publish_key = ?'],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 WHERE cache_key = ?'],
];

$plan669684 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas669684,
    $statements ?? $statements669684,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next669-684 extends next653-668 handoff'] = static function (TestRunner $t) use ($plan669684): void {
    $result = $plan669684([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 669, 'table' => 'wp_navigation_rule_locale_publish_receipt_next669', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next669'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_schema_archive_receipt_key_next662', 'to' => 'wp_schema_archive_receipt_key_next670'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_meta_next661'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 672, 'table' => 'wp_job_retry_checkpoint_final_next672', 'indexes' => ['wp_job_retry_checkpoint_final_key_next672'], 'commit' => true],
        ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_navigation_rule_locale_publish_final_next668', 'to' => 'wp_navigation_rule_locale_publish_final_next674'],
        ['op' => 'attach', 'schema' => 'handoff', 'schema_cookie' => 676, 'tables' => ['wp_schema_handoff_next676'], 'indexes' => ['wp_schema_handoff_key_next676'], 'file' => '/srv/wp/handoff-next676.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'handoff', 'schema_cookie' => 677, 'table' => 'wp_schema_handoff_meta_next677', 'indexes' => ['wp_schema_handoff_meta_key_next677'], 'commit' => false],
        ['op' => 'attach', 'schema' => 'publish', 'schema_cookie' => 679, 'tables' => ['wp_schema_publish_next679'], 'indexes' => ['wp_schema_publish_key_next679'], 'file' => '/srv/wp/publish-next679.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 680, 'table' => 'wp_schema_publish_meta_next680', 'indexes' => ['wp_schema_publish_meta_key_next680'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 684, 'table' => 'wp_navigation_rule_locale_publish_final_next684', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next684'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(10, $result['event_count']);
    $t->same(684, $result['schema_cookies_next']['main']);
    $t->same(526, $result['schema_cookies_next']['temp']);
    $t->same(672, $result['schema_cookies_next']['queue']);
    $t->same(662, $result['schema_cookies_next']['audit']);
    $t->same(676, $result['schema_cookies_next']['handoff']);
    $t->same(680, $result['schema_cookies_next']['publish']);
    $t->same(['main-final-reader', 'archive-receipt-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-preview-writer'], $result['write_statements_blocked_before_retry']);
    $t->same('__detached__', $result['statements']['archive-receipt-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['audit-meta-reader']['schema_transitions'][0]['next_found']);
    $t->same('handoff', $result['statements']['handoff-reader']['schema_transitions'][0]['next_schema']);
    $t->same('publish', $result['statements']['publish-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['main-final-reader']['schema_transitions'][0]['next_found']);
    $t->same(['temp-retry-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next669-684 ignores detached transient publish preview'] = static function (TestRunner $t) use ($plan669684): void {
    $result = $plan669684([
        ['op' => 'attach', 'schema' => 'preview', 'schema_cookie' => 669, 'tables' => ['wp_preview_next669'], 'indexes' => ['wp_preview_key_next669'], 'file' => '/srv/wp/preview-next669.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'preview', 'schema_cookie' => 670, 'table' => 'wp_preview_meta_next670', 'indexes' => ['wp_preview_meta_key_next670'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'preview'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'queue'], $result['search_order_next']);
};

return $tests;
