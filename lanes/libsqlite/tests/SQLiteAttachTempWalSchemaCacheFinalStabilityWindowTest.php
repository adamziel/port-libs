<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemasFinalStability = [
    'main' => [
        'schema_cookie' => 812,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_final_navigation', 'wp_navigation_rule_locale_publish_final_final_navigation'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_final_navigation', 'wp_navigation_rule_locale_publish_final_key_final_navigation'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 812, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 816,
        'tables' => ['wp_theme_stage_publish_review_final_temp', 'wp_theme_stage_publish_notice_final_temp'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 813,
        'tables' => ['wp_schema_archive_done_final_archive'],
        'indexes' => ['wp_schema_archive_done_key_final_archive'],
        'file' => '/srv/wp/archive-final_archive.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 821,
        'tables' => ['wp_schema_audit_receipt_final_audit'],
        'indexes' => ['wp_schema_audit_receipt_key_final_audit'],
        'file' => '/srv/wp/audit-final_archive.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 809,
        'tables' => ['wp_schema_handoff_receipt_prior_handoff'],
        'indexes' => ['wp_schema_handoff_receipt_key_prior_handoff'],
        'file' => '/srv/wp/handoff-final_archive.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 826,
        'tables' => ['wp_schema_publish_final_archive', 'wp_schema_publish_done_final_publish'],
        'indexes' => ['wp_schema_publish_key_final_queue', 'wp_schema_publish_done_key_final_publish'],
        'file' => '/srv/wp/publish-final_archive.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 818,
        'tables' => ['wp_job_retry_checkpoint_delivered_final_delta', 'wp_job_retry_checkpoint_archive_final_queue'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key_final_delta', 'wp_job_retry_checkpoint_archive_key_final_queue'],
        'file' => '/srv/wp/queue-final_archive.sqlite',
    ],
    'report' => [
        'schema_cookie' => 811,
        'tables' => ['wp_schema_report_prior_report', 'wp_schema_report_meta_prior_report'],
        'indexes' => ['wp_schema_report_key_prior_report', 'wp_schema_report_meta_key_prior_report'],
        'file' => '/srv/wp/report-final_archive.sqlite',
    ],
];

$statementsFinalStability = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_final_navigation INDEXED BY wp_navigation_rule_locale_publish_final_key_final_navigation WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_final_navigation INDEXED BY wp_navigation_rule_locale_publish_receipt_key_final_navigation WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_final_publish INDEXED BY wp_schema_publish_done_key_final_publish WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_final_queue INDEXED BY wp_job_retry_checkpoint_archive_key_final_queue SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_final_audit INDEXED BY wp_schema_audit_receipt_key_final_audit WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_prior_handoff INDEXED BY wp_schema_handoff_receipt_key_prior_handoff WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_prior_report INDEXED BY wp_schema_report_meta_key_prior_report WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_final_audit INDEXED BY wp_schema_review_receipt_key_final_audit WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_final_temp WHERE cache_key = ?'],
];

$planFinalStability = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemasFinalStability,
    $statements ?? $statementsFinalStability,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source final stability window extends predecessor consolidated handoff'] = static function (TestRunner $t) use ($planFinalStability): void {
    $result = $planFinalStability([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 814, 'table' => 'wp_navigation_rule_locale_publish_delta_final_delta', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_final_delta'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 816, 'table' => 'wp_theme_stage_publish_notice_final_temp', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_final_queue', 'to' => 'wp_job_retry_checkpoint_archive_key_final_queue_replacement'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 821, 'tables' => ['wp_schema_review_receipt_final_audit'], 'indexes' => ['wp_schema_review_receipt_key_final_audit'], 'file' => '/srv/wp/review-final_audit.sqlite'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_final_audit'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_prior_handoff', 'to' => 'wp_schema_handoff_receipt_final_handoff'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 826, 'table' => 'wp_schema_publish_receipt_final_publish', 'indexes' => ['wp_schema_publish_receipt_key_final_publish'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 828, 'table' => 'wp_navigation_rule_locale_publish_final_final_navigation', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_final_navigation'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 813, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_final_archive', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_final_archive'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(828, $result['schema_cookies_next']['main']);
    $t->same(816, $result['schema_cookies_next']['temp']);
    $t->same(822, $result['schema_cookies_next']['audit']);
    $t->same(810, $result['schema_cookies_next']['handoff']);
    $t->same(826, $result['schema_cookies_next']['publish']);
    $t->same(819, $result['schema_cookies_next']['queue']);
    $t->same(821, $result['schema_cookies_next']['review']);
    $t->same(false, isset($result['schema_cookies_next']['archive']));
    $t->same(['main-final-reader', 'queue-archive-writer'], $result['active_current_snapshot_statements']);
    $t->same(['queue-archive-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-archive-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['audit-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-reader']['schema_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['publish-done-reader', 'report-meta-reader', 'temp-notice-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source final stability window ignores detached scratch handoff'] = static function (TestRunner $t) use ($planFinalStability): void {
    $result = $planFinalStability([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 813, 'tables' => ['wp_scratch_final_archive'], 'indexes' => ['wp_scratch_key_final_archive'], 'file' => '/srv/wp/scratch-final_archive.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 814, 'table' => 'wp_scratch_meta_final_delta', 'indexes' => ['wp_scratch_meta_key_final_delta'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
