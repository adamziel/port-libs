<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas829844 = [
    'main' => [
        'schema_cookie' => 828,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next844', 'wp_navigation_rule_locale_publish_final_next844'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next844', 'wp_navigation_rule_locale_publish_final_key_next844'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 828, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 832,
        'tables' => ['wp_theme_stage_publish_review_next832', 'wp_theme_stage_publish_notice_next832'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 829,
        'tables' => ['wp_schema_archive_done_next829'],
        'indexes' => ['wp_schema_archive_done_key_next829'],
        'file' => '/srv/wp/archive-next829.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 837,
        'tables' => ['wp_schema_audit_receipt_next837'],
        'indexes' => ['wp_schema_audit_receipt_key_next837'],
        'file' => '/srv/wp/audit-next829.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 825,
        'tables' => ['wp_schema_handoff_receipt_next809'],
        'indexes' => ['wp_schema_handoff_receipt_key_next809'],
        'file' => '/srv/wp/handoff-next829.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 842,
        'tables' => ['wp_schema_publish_next829', 'wp_schema_publish_done_next842'],
        'indexes' => ['wp_schema_publish_key_next834', 'wp_schema_publish_done_key_next842'],
        'file' => '/srv/wp/publish-next829.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 834,
        'tables' => ['wp_job_retry_checkpoint_delivered_next830', 'wp_job_retry_checkpoint_archive_next834'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key_next830', 'wp_job_retry_checkpoint_archive_key_next834'],
        'file' => '/srv/wp/queue-next829.sqlite',
    ],
    'report' => [
        'schema_cookie' => 827,
        'tables' => ['wp_schema_report_next746', 'wp_schema_report_meta_next811'],
        'indexes' => ['wp_schema_report_key_next709', 'wp_schema_report_meta_key_next811'],
        'file' => '/srv/wp/report-next829.sqlite',
    ],
];

$statements829844 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next844 INDEXED BY wp_navigation_rule_locale_publish_final_key_next844 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next844 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next844 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next842 INDEXED BY wp_schema_publish_done_key_next842 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next834 INDEXED BY wp_job_retry_checkpoint_archive_key_next834 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next837 INDEXED BY wp_schema_audit_receipt_key_next837 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next809 INDEXED BY wp_schema_handoff_receipt_key_next809 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next811 INDEXED BY wp_schema_report_meta_key_next811 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next837 INDEXED BY wp_schema_review_receipt_key_next837 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next832 WHERE cache_key = ?'],
];

$plan829844 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas829844,
    $statements ?? $statements829844,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next829-844 extends next813-828 handoff'] = static function (TestRunner $t) use ($plan829844): void {
    $result = $plan829844([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 830, 'table' => 'wp_navigation_rule_locale_publish_delta_next830', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next830'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 832, 'table' => 'wp_theme_stage_publish_notice_next832', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next834', 'to' => 'wp_job_retry_checkpoint_archive_key_next838'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 837, 'tables' => ['wp_schema_review_receipt_next837'], 'indexes' => ['wp_schema_review_receipt_key_next837'], 'file' => '/srv/wp/review-next837.sqlite'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next837'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next809', 'to' => 'wp_schema_handoff_receipt_next840'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 842, 'table' => 'wp_schema_publish_receipt_next842', 'indexes' => ['wp_schema_publish_receipt_key_next842'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 844, 'table' => 'wp_navigation_rule_locale_publish_final_next844', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next844'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 829, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next829', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next829'], 'commit' => false],
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

$tests['attach temp wal schema cache current source next829-844 ignores detached scratch handoff'] = static function (TestRunner $t) use ($plan829844): void {
    $result = $plan829844([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 829, 'tables' => ['wp_scratch_next829'], 'indexes' => ['wp_scratch_key_next829'], 'file' => '/srv/wp/scratch-next829.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 830, 'table' => 'wp_scratch_meta_next830', 'indexes' => ['wp_scratch_meta_key_next830'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
