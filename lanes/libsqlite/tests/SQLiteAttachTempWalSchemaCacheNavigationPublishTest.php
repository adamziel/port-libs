<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemasNavigationPublish = [
    'main' => [
        'schema_cookie' => 828,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_final', 'wp_navigation_rule_locale_publish_final_final'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_final', 'wp_navigation_rule_locale_publish_final_key_final'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 828, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 832,
        'tables' => ['wp_theme_stage_publish_review_notice', 'wp_theme_stage_publish_notice_notice'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 829,
        'tables' => ['wp_schema_archive_done_archive'],
        'indexes' => ['wp_schema_archive_done_key_archive'],
        'file' => '/srv/wp/archive-archive.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 837,
        'tables' => ['wp_schema_audit_receipt_review'],
        'indexes' => ['wp_schema_audit_receipt_key_review'],
        'file' => '/srv/wp/audit-archive.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 825,
        'tables' => ['wp_schema_handoff_receipt_handoff'],
        'indexes' => ['wp_schema_handoff_receipt_key_handoff'],
        'file' => '/srv/wp/handoff-archive.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 842,
        'tables' => ['wp_schema_publish_archive', 'wp_schema_publish_done_done'],
        'indexes' => ['wp_schema_publish_key_archive', 'wp_schema_publish_done_key_done'],
        'file' => '/srv/wp/publish-archive.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 834,
        'tables' => ['wp_job_retry_checkpoint_delivered_delta', 'wp_job_retry_checkpoint_archive_archive'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key_delta', 'wp_job_retry_checkpoint_archive_key_archive'],
        'file' => '/srv/wp/queue-archive.sqlite',
    ],
    'report' => [
        'schema_cookie' => 827,
        'tables' => ['wp_schema_report_report', 'wp_schema_report_meta_meta'],
        'indexes' => ['wp_schema_report_key_report', 'wp_schema_report_meta_key_meta'],
        'file' => '/srv/wp/report-archive.sqlite',
    ],
];

$statementsNavigationPublish = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_final INDEXED BY wp_navigation_rule_locale_publish_final_key_final WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_final INDEXED BY wp_navigation_rule_locale_publish_receipt_key_final WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_done INDEXED BY wp_schema_publish_done_key_done WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_archive INDEXED BY wp_job_retry_checkpoint_archive_key_archive SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_review INDEXED BY wp_schema_audit_receipt_key_review WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_handoff INDEXED BY wp_schema_handoff_receipt_key_handoff WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_meta INDEXED BY wp_schema_report_meta_key_meta WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_review INDEXED BY wp_schema_review_receipt_key_review WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_notice WHERE cache_key = ?'],
];

$planNavigationPublish = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemasNavigationPublish,
    $statements ?? $statementsNavigationPublish,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache navigation publish extends prior handoff'] = static function (TestRunner $t) use ($planNavigationPublish): void {
    $result = $planNavigationPublish([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 830, 'table' => 'wp_navigation_rule_locale_publish_delta_delta', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_delta'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 832, 'table' => 'wp_theme_stage_publish_notice_notice', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_archive', 'to' => 'wp_job_retry_checkpoint_archive_key_renamed'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 837, 'tables' => ['wp_schema_review_receipt_review'], 'indexes' => ['wp_schema_review_receipt_key_review'], 'file' => '/srv/wp/review-review.sqlite'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_review'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_handoff', 'to' => 'wp_schema_handoff_receipt_published'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 842, 'table' => 'wp_schema_publish_receipt_done', 'indexes' => ['wp_schema_publish_receipt_key_done'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 844, 'table' => 'wp_navigation_rule_locale_publish_final_final', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_final'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 829, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_archive', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_archive'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(844, $result['schema_cookies_next']['main']);
    $t->same(832, $result['schema_cookies_next']['temp']);
    $t->same(838, $result['schema_cookies_next']['audit']);
    $t->same(826, $result['schema_cookies_next']['handoff']);
    $t->same(842, $result['schema_cookies_next']['publish']);
    $t->same(835, $result['schema_cookies_next']['queue']);
    $t->same(837, $result['schema_cookies_next']['review']);
    $t->same(false, isset($result['schema_cookies_next']['archive']));
    $t->same(['main-final-reader', 'queue-archive-writer'], $result['active_current_snapshot_statements']);
    $t->same(['queue-archive-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-archive-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['audit-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-reader']['schema_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['publish-done-reader', 'report-meta-reader', 'temp-notice-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache navigation publish ignores detached scratch handoff'] = static function (TestRunner $t) use ($planNavigationPublish): void {
    $result = $planNavigationPublish([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 829, 'tables' => ['wp_scratch_archive'], 'indexes' => ['wp_scratch_key_archive'], 'file' => '/srv/wp/scratch-archive.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 830, 'table' => 'wp_scratch_meta_delta', 'indexes' => ['wp_scratch_meta_key_delta'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
