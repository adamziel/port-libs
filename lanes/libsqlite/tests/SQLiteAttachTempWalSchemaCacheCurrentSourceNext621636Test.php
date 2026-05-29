<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas621636 = [
    'main' => [
        'schema_cookie' => 620,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next620'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next620'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 620, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 526,
        'tables' => ['wp_theme_stage_publish_retries_next558'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 572,
        'tables' => ['wp_schema_archive_retry_next620'],
        'indexes' => ['wp_schema_archive_retry_key_next620'],
        'file' => '/srv/wp/archive-next621.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 585,
        'tables' => ['wp_schema_handoff_next582', 'wp_schema_handoff_meta_next621'],
        'indexes' => ['wp_schema_handoff_key_next582', 'wp_schema_handoff_meta_key_next621'],
        'file' => '/srv/wp/handoff-next621.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 611,
        'tables' => ['wp_schema_audit_next610', 'wp_schema_audit_meta_next611'],
        'indexes' => ['wp_schema_audit_key_next610', 'wp_schema_audit_meta_key_next611'],
        'file' => '/srv/wp/audit-next621.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 593,
        'tables' => ['wp_job_retry_checkpoint_final_next607', 'wp_job_retry_checkpoint_preview_next620'],
        'indexes' => ['wp_job_retry_checkpoint_final_job_next607', 'wp_job_retry_checkpoint_preview_job_next620'],
        'file' => '/srv/wp/queue-next621.sqlite',
    ],
];

$statements621636 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_publish_final_next620 INDEXED BY wp_navigation_rule_locale_publish_final_key_next620 WHERE final_key = ?', 'active' => true],
    ['name' => 'queue-final-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_final_next607 INDEXED BY wp_job_retry_checkpoint_final_job_next607 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'audit-meta-reader', 'sql' => 'SELECT meta_id FROM audit.wp_schema_audit_meta_next611 INDEXED BY wp_schema_audit_meta_key_next611 WHERE meta_key = ?', 'active' => true],
    ['name' => 'handoff-meta-reader', 'sql' => 'SELECT meta_id FROM handoff.wp_schema_handoff_meta_next621 INDEXED BY wp_schema_handoff_meta_key_next621 WHERE meta_key = ?'],
    ['name' => 'archive-retry-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_retry_next620 WHERE archive_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_next626 INDEXED BY wp_schema_review_key_next626 WHERE review_key = ?'],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 WHERE cache_key = ?'],
];

$plan621636 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext621636(
    $schemas ?? $schemas621636,
    $statements ?? $statements621636,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next621-636 extends next605-620 handoff'] = static function (TestRunner $t) use ($plan621636): void {
    $result = $plan621636([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 621, 'table' => 'wp_navigation_rule_locale_publish_receipt_next621', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next621'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 622, 'table' => 'wp_schema_audit_publish_next622', 'indexes' => ['wp_schema_audit_publish_key_next622'], 'commit' => true],
        ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_final_next607', 'to' => 'wp_job_retry_checkpoint_sealed_next623'],
        ['op' => 'drop_index', 'schema' => 'handoff', 'index' => 'wp_schema_handoff_meta_key_next621'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 626, 'tables' => ['wp_schema_review_next626'], 'indexes' => ['wp_schema_review_key_next626'], 'file' => '/srv/wp/review-next626.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'review', 'schema_cookie' => 627, 'table' => 'wp_schema_review_meta_next627', 'indexes' => ['wp_schema_review_meta_key_next627'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 628, 'table' => 'wp_schema_archive_preview_next628', 'indexes' => ['wp_schema_archive_preview_key_next628'], 'commit' => false],
        ['op' => 'drop_table', 'schema' => 'archive', 'table' => 'wp_schema_archive_retry_next620'],
        ['op' => 'detach', 'schema' => 'audit'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 636, 'table' => 'wp_navigation_rule_locale_publish_final_next636', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next636'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next621-636', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next621', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next636', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next605', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next620', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(636, $result['schema_cookies_next']['main']);
    $t->same(526, $result['schema_cookies_next']['temp']);
    $t->same(594, $result['schema_cookies_next']['queue']);
    $t->same(586, $result['schema_cookies_next']['handoff']);
    $t->same(573, $result['schema_cookies_next']['archive']);
    $t->same(627, $result['schema_cookies_next']['review']);
    $t->same(['main-final-reader', 'audit-meta-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-final-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-final-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-meta-reader']['index_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['audit-meta-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['archive-retry-reader']['schema_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['temp-retry-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next621-636 keeps transient review preview stable'] = static function (TestRunner $t) use ($plan621636): void {
    $result = $plan621636([
        ['op' => 'attach', 'schema' => 'preview', 'schema_cookie' => 621, 'tables' => ['wp_preview_next621'], 'indexes' => ['wp_preview_key_next621'], 'file' => '/srv/wp/preview-next621.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'preview', 'schema_cookie' => 622, 'table' => 'wp_preview_meta_next622', 'indexes' => ['wp_preview_meta_key_next622'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'preview'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'queue'], $result['search_order_next']);
};

return $tests;
