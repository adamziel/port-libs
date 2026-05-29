<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas765780 = [
    'main' => [
        'schema_cookie' => 764,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next732', 'wp_navigation_rule_locale_publish_final_next764'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next732', 'wp_navigation_rule_locale_publish_final_key_next764'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 764, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 752,
        'tables' => ['wp_theme_stage_publish_review_next752', 'wp_theme_stage_publish_notice_next768'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 750,
        'tables' => ['wp_schema_archive_done_next750'],
        'indexes' => ['wp_schema_archive_done_key_next750'],
        'file' => '/srv/wp/archive-next765.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 773,
        'tables' => ['wp_schema_audit_receipt_next773'],
        'indexes' => ['wp_schema_audit_receipt_key_next773'],
        'file' => '/srv/wp/audit-next765.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 761,
        'tables' => ['wp_schema_handoff_receipt_next761'],
        'indexes' => ['wp_schema_handoff_receipt_key_next761'],
        'file' => '/srv/wp/handoff-next765.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 739,
        'tables' => ['wp_schema_publish_next679', 'wp_schema_publish_done_next706'],
        'indexes' => ['wp_schema_publish_key_next686', 'wp_schema_publish_done_key_next722'],
        'file' => '/srv/wp/publish-next765.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 756,
        'tables' => ['wp_job_retry_checkpoint_delivered_next688', 'wp_job_retry_checkpoint_archive_next756'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key_next702', 'wp_job_retry_checkpoint_archive_key_next756'],
        'file' => '/srv/wp/queue-next765.sqlite',
    ],
    'report' => [
        'schema_cookie' => 728,
        'tables' => ['wp_schema_report_next730', 'wp_schema_report_meta_next694'],
        'indexes' => ['wp_schema_report_key_next693', 'wp_schema_report_meta_key_next694'],
        'file' => '/srv/wp/report-next765.sqlite',
    ],
];

$statements765780 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next764 INDEXED BY wp_navigation_rule_locale_publish_final_key_next764 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next732 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next732 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next706 INDEXED BY wp_schema_publish_done_key_next722 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next756 INDEXED BY wp_job_retry_checkpoint_archive_key_next756 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next773 INDEXED BY wp_schema_audit_receipt_key_next773 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next761 INDEXED BY wp_schema_handoff_receipt_key_next761 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next694 INDEXED BY wp_schema_report_meta_key_next694 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next773 INDEXED BY wp_schema_review_receipt_key_next773 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next768 WHERE cache_key = ?'],
];

$plan765780 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext765780(
    $schemas ?? $schemas765780,
    $statements ?? $statements765780,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next765-780 extends next749-764 handoff'] = static function (TestRunner $t) use ($plan765780): void {
    $result = $plan765780([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 766, 'table' => 'wp_navigation_rule_locale_publish_delta_next766', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next766'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 768, 'table' => 'wp_theme_stage_publish_notice_next768', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next756', 'to' => 'wp_job_retry_checkpoint_archive_key_next770'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 773, 'tables' => ['wp_schema_review_receipt_next773'], 'indexes' => ['wp_schema_review_receipt_key_next773'], 'file' => '/srv/wp/review-next773.sqlite'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next773'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next761', 'to' => 'wp_schema_handoff_receipt_next776'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 778, 'table' => 'wp_schema_publish_receipt_next778', 'indexes' => ['wp_schema_publish_receipt_key_next778'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 780, 'table' => 'wp_navigation_rule_locale_publish_final_next780', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next780'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 765, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next765', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next765'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next765-780', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next765', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next780', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next749', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next764', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(780, $result['schema_cookies_next']['main']);
    $t->same(768, $result['schema_cookies_next']['temp']);
    $t->same(774, $result['schema_cookies_next']['audit']);
    $t->same(762, $result['schema_cookies_next']['handoff']);
    $t->same(778, $result['schema_cookies_next']['publish']);
    $t->same(757, $result['schema_cookies_next']['queue']);
    $t->same(773, $result['schema_cookies_next']['review']);
    $t->same(false, isset($result['schema_cookies_next']['archive']));
    $t->same(['main-final-reader', 'queue-archive-writer'], $result['active_current_snapshot_statements']);
    $t->same(['queue-archive-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-archive-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['audit-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-reader']['schema_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['report-meta-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next765-780 ignores detached scratch handoff'] = static function (TestRunner $t) use ($plan765780): void {
    $result = $plan765780([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 765, 'tables' => ['wp_scratch_next765'], 'indexes' => ['wp_scratch_key_next765'], 'file' => '/srv/wp/scratch-next765.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 766, 'table' => 'wp_scratch_meta_next766', 'indexes' => ['wp_scratch_meta_key_next766'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
