<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas733748 = [
    'main' => [
        'schema_cookie' => 732,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next716', 'wp_navigation_rule_locale_publish_final_next732'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next716', 'wp_navigation_rule_locale_publish_final_key_next732'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 732, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 720,
        'tables' => ['wp_theme_stage_publish_review_next720', 'wp_theme_stage_publish_notice_next736'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 718,
        'tables' => ['wp_schema_archive_done_next718'],
        'indexes' => ['wp_schema_archive_done_key_next718'],
        'file' => '/srv/wp/archive-next733.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 725,
        'tables' => ['wp_schema_audit_receipt_next725'],
        'indexes' => ['wp_schema_audit_receipt_key_next725'],
        'file' => '/srv/wp/audit-next733.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 729,
        'tables' => ['wp_schema_handoff_receipt_next729'],
        'indexes' => ['wp_schema_handoff_receipt_key_next729'],
        'file' => '/srv/wp/handoff-next733.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 707,
        'tables' => ['wp_schema_publish_next679', 'wp_schema_publish_done_next706'],
        'indexes' => ['wp_schema_publish_key_next686', 'wp_schema_publish_done_key_next722'],
        'file' => '/srv/wp/publish-next733.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 724,
        'tables' => ['wp_job_retry_checkpoint_delivered_next688', 'wp_job_retry_checkpoint_archive_next724'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key_next702', 'wp_job_retry_checkpoint_archive_key_next724'],
        'file' => '/srv/wp/queue-next733.sqlite',
    ],
    'report' => [
        'schema_cookie' => 696,
        'tables' => ['wp_schema_report_next730', 'wp_schema_report_meta_next694'],
        'indexes' => ['wp_schema_report_key_next693', 'wp_schema_report_meta_key_next694'],
        'file' => '/srv/wp/report-next733.sqlite',
    ],
];

$statements733748 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next732 INDEXED BY wp_navigation_rule_locale_publish_final_key_next732 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next716 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next716 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next706 INDEXED BY wp_schema_publish_done_key_next722 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next724 INDEXED BY wp_job_retry_checkpoint_archive_key_next724 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next725 INDEXED BY wp_schema_audit_receipt_key_next725 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next729 INDEXED BY wp_schema_handoff_receipt_key_next729 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next694 INDEXED BY wp_schema_report_meta_key_next694 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next741 INDEXED BY wp_schema_review_receipt_key_next741 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next736 WHERE cache_key = ?'],
];

$plan733748 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas733748,
    $statements ?? $statements733748,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next733-748 extends next717-732 handoff'] = static function (TestRunner $t) use ($plan733748): void {
    $result = $plan733748([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 734, 'table' => 'wp_navigation_rule_locale_publish_delta_next734', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next734'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 736, 'table' => 'wp_theme_stage_publish_notice_next736', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next724', 'to' => 'wp_job_retry_checkpoint_archive_key_next738'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 741, 'tables' => ['wp_schema_review_receipt_next741'], 'indexes' => ['wp_schema_review_receipt_key_next741'], 'file' => '/srv/wp/review-next741.sqlite'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next725'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next729', 'to' => 'wp_schema_handoff_receipt_next744'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 746, 'table' => 'wp_schema_publish_receipt_next746', 'indexes' => ['wp_schema_publish_receipt_key_next746'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 748, 'table' => 'wp_navigation_rule_locale_publish_final_next748', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next748'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 749, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next749', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next749'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(748, $result['schema_cookies_next']['main']);
    $t->same(736, $result['schema_cookies_next']['temp']);
    $t->same(726, $result['schema_cookies_next']['audit']);
    $t->same(730, $result['schema_cookies_next']['handoff']);
    $t->same(746, $result['schema_cookies_next']['publish']);
    $t->same(725, $result['schema_cookies_next']['queue']);
    $t->same(741, $result['schema_cookies_next']['review']);
    $t->same(false, isset($result['schema_cookies_next']['archive']));
    $t->same(['main-final-reader', 'queue-archive-writer'], $result['active_current_snapshot_statements']);
    $t->same(['queue-archive-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-archive-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['audit-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-reader']['schema_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['report-meta-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next733-748 ignores detached scratch handoff'] = static function (TestRunner $t) use ($plan733748): void {
    $result = $plan733748([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 733, 'tables' => ['wp_scratch_next733'], 'indexes' => ['wp_scratch_key_next733'], 'file' => '/srv/wp/scratch-next733.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 734, 'table' => 'wp_scratch_meta_next734', 'indexes' => ['wp_scratch_meta_key_next734'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
