<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemasFinalPublication = [
    'main' => [
        'schema_cookie' => 780,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_current', 'wp_navigation_rule_locale_publish_final_current'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_current', 'wp_navigation_rule_locale_publish_final_key_current'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 780, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 784,
        'tables' => ['wp_theme_stage_publish_review_temp', 'wp_theme_stage_publish_notice_temp'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 781,
        'tables' => ['wp_schema_archive_done_archive'],
        'indexes' => ['wp_schema_archive_done_key_archive'],
        'file' => '/srv/wp/archive-archive.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 789,
        'tables' => ['wp_schema_audit_receipt_review'],
        'indexes' => ['wp_schema_audit_receipt_key_review'],
        'file' => '/srv/wp/audit-archive.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 777,
        'tables' => ['wp_schema_handoff_receipt_current'],
        'indexes' => ['wp_schema_handoff_receipt_key_current'],
        'file' => '/srv/wp/handoff-archive.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 794,
        'tables' => ['wp_schema_publish_archive', 'wp_schema_publish_done_publish'],
        'indexes' => ['wp_schema_publish_key_queue', 'wp_schema_publish_done_key_publish'],
        'file' => '/srv/wp/publish-archive.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 786,
        'tables' => ['wp_job_retry_checkpoint_delivered_delta', 'wp_job_retry_checkpoint_archive_queue'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key_delta', 'wp_job_retry_checkpoint_archive_key_queue'],
        'file' => '/srv/wp/queue-archive.sqlite',
    ],
    'report' => [
        'schema_cookie' => 779,
        'tables' => ['wp_schema_report_report', 'wp_schema_report_meta_current'],
        'indexes' => ['wp_schema_report_key_report', 'wp_schema_report_meta_key_current'],
        'file' => '/srv/wp/report-archive.sqlite',
    ],
];

$statementsFinalPublication = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_current INDEXED BY wp_navigation_rule_locale_publish_final_key_current WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_current INDEXED BY wp_navigation_rule_locale_publish_receipt_key_current WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_publish INDEXED BY wp_schema_publish_done_key_publish WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_queue INDEXED BY wp_job_retry_checkpoint_archive_key_queue SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_review INDEXED BY wp_schema_audit_receipt_key_review WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_current INDEXED BY wp_schema_handoff_receipt_key_current WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_current INDEXED BY wp_schema_report_meta_key_current WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_review INDEXED BY wp_schema_review_receipt_key_review WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_temp WHERE cache_key = ?'],
];

$planFinalPublication = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemasFinalPublication,
    $statements ?? $statementsFinalPublication,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache final publication extends consolidated handoff'] = static function (TestRunner $t) use ($planFinalPublication): void {
    $result = $planFinalPublication([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 782, 'table' => 'wp_navigation_rule_locale_publish_delta_delta', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_delta'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 784, 'table' => 'wp_theme_stage_publish_notice_temp', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_queue', 'to' => 'wp_job_retry_checkpoint_archive_key_renamed'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 789, 'tables' => ['wp_schema_review_receipt_review'], 'indexes' => ['wp_schema_review_receipt_key_review'], 'file' => '/srv/wp/review-review.sqlite'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_review'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_current', 'to' => 'wp_schema_handoff_receipt_handoff'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 794, 'table' => 'wp_schema_publish_receipt_publish', 'indexes' => ['wp_schema_publish_receipt_key_publish'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 796, 'table' => 'wp_navigation_rule_locale_publish_final_final', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_final'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 781, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_archive', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_archive'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(796, $result['schema_cookies_next']['main']);
    $t->same(784, $result['schema_cookies_next']['temp']);
    $t->same(790, $result['schema_cookies_next']['audit']);
    $t->same(778, $result['schema_cookies_next']['handoff']);
    $t->same(794, $result['schema_cookies_next']['publish']);
    $t->same(787, $result['schema_cookies_next']['queue']);
    $t->same(789, $result['schema_cookies_next']['review']);
    $t->same(false, isset($result['schema_cookies_next']['archive']));
    $t->same(['main-final-reader', 'queue-archive-writer'], $result['active_current_snapshot_statements']);
    $t->same(['queue-archive-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-archive-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['audit-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-reader']['schema_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['publish-done-reader', 'report-meta-reader', 'temp-notice-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache final publication ignores detached scratch handoff'] = static function (TestRunner $t) use ($planFinalPublication): void {
    $result = $planFinalPublication([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 781, 'tables' => ['wp_scratch_archive'], 'indexes' => ['wp_scratch_key_archive'], 'file' => '/srv/wp/scratch-archive.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 782, 'table' => 'wp_scratch_meta_delta', 'indexes' => ['wp_scratch_meta_key_delta'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
