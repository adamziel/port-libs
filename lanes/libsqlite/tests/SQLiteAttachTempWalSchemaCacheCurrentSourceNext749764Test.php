<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas749764 = [
    'main' => [
        'schema_cookie' => 748,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next732', 'wp_navigation_rule_locale_publish_final_next748'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next732', 'wp_navigation_rule_locale_publish_final_key_next748'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 748, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 736,
        'tables' => ['wp_theme_stage_publish_review_next736', 'wp_theme_stage_publish_notice_next752'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 734,
        'tables' => ['wp_schema_archive_done_next734'],
        'indexes' => ['wp_schema_archive_done_key_next734'],
        'file' => '/srv/wp/archive-next749.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 757,
        'tables' => ['wp_schema_audit_receipt_next757'],
        'indexes' => ['wp_schema_audit_receipt_key_next757'],
        'file' => '/srv/wp/audit-next749.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 745,
        'tables' => ['wp_schema_handoff_receipt_next745'],
        'indexes' => ['wp_schema_handoff_receipt_key_next745'],
        'file' => '/srv/wp/handoff-next749.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 723,
        'tables' => ['wp_schema_publish_next679', 'wp_schema_publish_done_next706'],
        'indexes' => ['wp_schema_publish_key_next686', 'wp_schema_publish_done_key_next722'],
        'file' => '/srv/wp/publish-next749.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 740,
        'tables' => ['wp_job_retry_checkpoint_delivered_next688', 'wp_job_retry_checkpoint_archive_next740'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key_next702', 'wp_job_retry_checkpoint_archive_key_next740'],
        'file' => '/srv/wp/queue-next749.sqlite',
    ],
    'report' => [
        'schema_cookie' => 712,
        'tables' => ['wp_schema_report_next730', 'wp_schema_report_meta_next694'],
        'indexes' => ['wp_schema_report_key_next693', 'wp_schema_report_meta_key_next694'],
        'file' => '/srv/wp/report-next749.sqlite',
    ],
];

$statements749764 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next748 INDEXED BY wp_navigation_rule_locale_publish_final_key_next748 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next732 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next732 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next706 INDEXED BY wp_schema_publish_done_key_next722 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next740 INDEXED BY wp_job_retry_checkpoint_archive_key_next740 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next757 INDEXED BY wp_schema_audit_receipt_key_next757 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next745 INDEXED BY wp_schema_handoff_receipt_key_next745 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next694 INDEXED BY wp_schema_report_meta_key_next694 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next757 INDEXED BY wp_schema_review_receipt_key_next757 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next752 WHERE cache_key = ?'],
];

$plan749764 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext749764(
    $schemas ?? $schemas749764,
    $statements ?? $statements749764,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next749-764 extends next733-748 handoff'] = static function (TestRunner $t) use ($plan749764): void {
    $result = $plan749764([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 750, 'table' => 'wp_navigation_rule_locale_publish_delta_next750', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next750'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 752, 'table' => 'wp_theme_stage_publish_notice_next752', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next740', 'to' => 'wp_job_retry_checkpoint_archive_key_next754'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 757, 'tables' => ['wp_schema_review_receipt_next757'], 'indexes' => ['wp_schema_review_receipt_key_next757'], 'file' => '/srv/wp/review-next757.sqlite'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next757'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next745', 'to' => 'wp_schema_handoff_receipt_next760'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 762, 'table' => 'wp_schema_publish_receipt_next762', 'indexes' => ['wp_schema_publish_receipt_key_next762'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 764, 'table' => 'wp_navigation_rule_locale_publish_final_next764', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next764'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 749, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next749', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next749'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next749-764', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next749', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next764', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next733', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next748', $result['dependencies'][31]);
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

$tests['attach temp wal schema cache current source next749-764 ignores detached scratch handoff'] = static function (TestRunner $t) use ($plan749764): void {
    $result = $plan749764([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 749, 'tables' => ['wp_scratch_next749'], 'indexes' => ['wp_scratch_key_next749'], 'file' => '/srv/wp/scratch-next749.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 750, 'table' => 'wp_scratch_meta_next750', 'indexes' => ['wp_scratch_meta_key_next750'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
