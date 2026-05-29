<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemasPublicationContinuation = [
    'main' => [
        'schema_cookie' => 748,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt', 'wp_navigation_rule_locale_publish_final'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_receipt', 'wp_navigation_rule_locale_publish_final_key_final'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 748, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 736,
        'tables' => ['wp_theme_stage_publish_review', 'wp_theme_stage_publish_notice'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 734,
        'tables' => ['wp_schema_archive_done'],
        'indexes' => ['wp_schema_archive_done_done_key'],
        'file' => '/srv/wp/archive-final.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 757,
        'tables' => ['wp_schema_audit_receipt'],
        'indexes' => ['wp_schema_audit_receipt_key_receipt'],
        'file' => '/srv/wp/audit-final.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 745,
        'tables' => ['wp_schema_handoff_receipt'],
        'indexes' => ['wp_schema_handoff_receipt_key_receipt'],
        'file' => '/srv/wp/handoff-final.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 723,
        'tables' => ['wp_schema_publish_base', 'wp_schema_publish_done'],
        'indexes' => ['wp_schema_publish_key', 'wp_schema_publish_done_done_key'],
        'file' => '/srv/wp/publish-final.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 740,
        'tables' => ['wp_job_retry_checkpoint_delivered', 'wp_job_retry_checkpoint_archive'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key', 'wp_job_retry_checkpoint_archive_key_archive'],
        'file' => '/srv/wp/queue-final.sqlite',
    ],
    'report' => [
        'schema_cookie' => 712,
        'tables' => ['wp_schema_report', 'wp_schema_report_meta'],
        'indexes' => ['wp_schema_report_key', 'wp_schema_report_meta_key_meta'],
        'file' => '/srv/wp/report-final.sqlite',
    ],
];

$statementsPublicationContinuation = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final INDEXED BY wp_navigation_rule_locale_publish_final_key_final WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt INDEXED BY wp_navigation_rule_locale_publish_receipt_key_receipt WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done INDEXED BY wp_schema_publish_done_done_key WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive INDEXED BY wp_job_retry_checkpoint_archive_key_archive SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt INDEXED BY wp_schema_audit_receipt_key_receipt WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt INDEXED BY wp_schema_handoff_receipt_key_receipt WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta INDEXED BY wp_schema_report_meta_key_meta WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt INDEXED BY wp_schema_review_receipt_key_receipt WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice WHERE cache_key = ?'],
];

$planPublicationContinuation = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemasPublicationContinuation,
    $statements ?? $statementsPublicationContinuation,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache publication continuation extends final publication handoff'] = static function (TestRunner $t) use ($planPublicationContinuation): void {
    $result = $planPublicationContinuation([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 750, 'table' => 'wp_navigation_rule_locale_publish_delta', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_delta'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 752, 'table' => 'wp_theme_stage_publish_notice', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_archive', 'to' => 'wp_job_retry_checkpoint_archive_key_renamed'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 757, 'tables' => ['wp_schema_review_receipt'], 'indexes' => ['wp_schema_review_receipt_key_receipt'], 'file' => '/srv/wp/review-receipt.sqlite'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt', 'to' => 'wp_schema_handoff_receipt_renamed'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 762, 'table' => 'wp_schema_publish_receipt', 'indexes' => ['wp_schema_publish_receipt_key_receipt'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 764, 'table' => 'wp_navigation_rule_locale_publish_final', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_final'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 749, 'table' => 'wp_navigation_rule_locale_publish_uncommitted', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_uncommitted'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(764, $result['schema_cookies_next']['main']);
    $t->same(752, $result['schema_cookies_next']['temp']);
    $t->same(758, $result['schema_cookies_next']['audit']);
    $t->same(746, $result['schema_cookies_next']['handoff']);
    $t->same(762, $result['schema_cookies_next']['publish']);
    $t->same(741, $result['schema_cookies_next']['queue']);
    $t->same(757, $result['schema_cookies_next']['review']);
    $t->same(false, isset($result['schema_cookies_next']['archive']));
    $t->same(['main-final-reader', 'queue-archive-writer'], $result['active_current_snapshot_statements']);
    $t->same(['queue-archive-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-archive-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['audit-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-reader']['schema_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['report-meta-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache publication continuation ignores detached scratch handoff'] = static function (TestRunner $t) use ($planPublicationContinuation): void {
    $result = $planPublicationContinuation([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 749, 'tables' => ['wp_scratch_uncommitted'], 'indexes' => ['wp_scratch_key_uncommitted'], 'file' => '/srv/wp/scratch-uncommitted.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 750, 'table' => 'wp_scratch_meta_delta', 'indexes' => ['wp_scratch_meta_key_delta'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
