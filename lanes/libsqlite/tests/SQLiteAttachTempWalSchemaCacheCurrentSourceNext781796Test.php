<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas781796 = [
    'main' => [
        'schema_cookie' => 780,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next780', 'wp_navigation_rule_locale_publish_final_next780'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next780', 'wp_navigation_rule_locale_publish_final_key_next780'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 780, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 784,
        'tables' => ['wp_theme_stage_publish_review_next784', 'wp_theme_stage_publish_notice_next784'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 781,
        'tables' => ['wp_schema_archive_done_next781'],
        'indexes' => ['wp_schema_archive_done_key_next781'],
        'file' => '/srv/wp/archive-next781.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 789,
        'tables' => ['wp_schema_audit_receipt_next789'],
        'indexes' => ['wp_schema_audit_receipt_key_next789'],
        'file' => '/srv/wp/audit-next781.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 777,
        'tables' => ['wp_schema_handoff_receipt_next777'],
        'indexes' => ['wp_schema_handoff_receipt_key_next777'],
        'file' => '/srv/wp/handoff-next781.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 794,
        'tables' => ['wp_schema_publish_next781', 'wp_schema_publish_done_next794'],
        'indexes' => ['wp_schema_publish_key_next786', 'wp_schema_publish_done_key_next794'],
        'file' => '/srv/wp/publish-next781.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 786,
        'tables' => ['wp_job_retry_checkpoint_delivered_next782', 'wp_job_retry_checkpoint_archive_next786'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key_next782', 'wp_job_retry_checkpoint_archive_key_next786'],
        'file' => '/srv/wp/queue-next781.sqlite',
    ],
    'report' => [
        'schema_cookie' => 779,
        'tables' => ['wp_schema_report_next730', 'wp_schema_report_meta_next779'],
        'indexes' => ['wp_schema_report_key_next693', 'wp_schema_report_meta_key_next779'],
        'file' => '/srv/wp/report-next781.sqlite',
    ],
];

$statements781796 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next780 INDEXED BY wp_navigation_rule_locale_publish_final_key_next780 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next780 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next780 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next794 INDEXED BY wp_schema_publish_done_key_next794 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next786 INDEXED BY wp_job_retry_checkpoint_archive_key_next786 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next789 INDEXED BY wp_schema_audit_receipt_key_next789 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next777 INDEXED BY wp_schema_handoff_receipt_key_next777 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next779 INDEXED BY wp_schema_report_meta_key_next779 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next789 INDEXED BY wp_schema_review_receipt_key_next789 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next784 WHERE cache_key = ?'],
];

$plan781796 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext781796(
    $schemas ?? $schemas781796,
    $statements ?? $statements781796,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next781-796 extends next765-780 handoff'] = static function (TestRunner $t) use ($plan781796): void {
    $result = $plan781796([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 782, 'table' => 'wp_navigation_rule_locale_publish_delta_next782', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next782'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 784, 'table' => 'wp_theme_stage_publish_notice_next784', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next786', 'to' => 'wp_job_retry_checkpoint_archive_key_next790'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 789, 'tables' => ['wp_schema_review_receipt_next789'], 'indexes' => ['wp_schema_review_receipt_key_next789'], 'file' => '/srv/wp/review-next789.sqlite'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next789'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next777', 'to' => 'wp_schema_handoff_receipt_next792'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 794, 'table' => 'wp_schema_publish_receipt_next794', 'indexes' => ['wp_schema_publish_receipt_key_next794'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 796, 'table' => 'wp_navigation_rule_locale_publish_final_next796', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next796'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 781, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next781', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next781'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next781-796', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next781', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next796', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next765', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next780', $result['dependencies'][31]);
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

$tests['attach temp wal schema cache current source next781-796 ignores detached scratch handoff'] = static function (TestRunner $t) use ($plan781796): void {
    $result = $plan781796([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 781, 'tables' => ['wp_scratch_next781'], 'indexes' => ['wp_scratch_key_next781'], 'file' => '/srv/wp/scratch-next781.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 782, 'table' => 'wp_scratch_meta_next782', 'indexes' => ['wp_scratch_meta_key_next782'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
