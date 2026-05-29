<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas973988 = [
    'main' => [
        'schema_cookie' => 972,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_batch_next962', 'wp_navigation_rule_locale_publish_final_next972', 'wp_navigation_rule_locale_publish_gate_next960'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_batch_key_next962', 'wp_navigation_rule_locale_publish_final_key_next972', 'wp_navigation_rule_locale_publish_gate_key_next960'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 972, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 964,
        'tables' => ['wp_theme_stage_publish_notice_next944', 'wp_theme_stage_publish_token_next964'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 953,
        'tables' => ['wp_schema_archive_receipt_next952'],
        'indexes' => ['wp_schema_archive_receipt_key_next966'],
        'file' => '/srv/wp/archive-next973.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 955,
        'tables' => ['wp_schema_handoff_receipt_next970'],
        'indexes' => ['wp_schema_handoff_receipt_key_next920'],
        'file' => '/srv/wp/handoff-next973.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 971,
        'tables' => ['wp_schema_publish_done_next971', 'wp_schema_publish_final_next955'],
        'indexes' => ['wp_schema_publish_done_key_next971', 'wp_schema_publish_final_key_next955'],
        'file' => '/srv/wp/publish-next973.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 920,
        'tables' => ['wp_job_retry_checkpoint_ready_next919'],
        'indexes' => ['wp_job_retry_checkpoint_ready_key_next919'],
        'file' => '/srv/wp/queue-next973.sqlite',
    ],
    'report' => [
        'schema_cookie' => 957,
        'tables' => ['wp_schema_report_receipt_next957'],
        'indexes' => ['wp_schema_report_receipt_key_next957'],
        'file' => '/srv/wp/report-next973.sqlite',
    ],
    'review' => [
        'schema_cookie' => 968,
        'tables' => ['wp_schema_review_receipt_next968'],
        'indexes' => ['wp_schema_review_receipt_key_next968'],
        'file' => '/srv/wp/review-next973.sqlite',
    ],
];

$statements973988 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next972 INDEXED BY wp_navigation_rule_locale_publish_final_key_next972 WHERE nav_key = ?', 'active' => true],
    ['name' => 'archive-receipt-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next952 INDEXED BY wp_schema_archive_receipt_key_next966 WHERE archive_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next970 INDEXED BY wp_schema_handoff_receipt_key_next920 WHERE handoff_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next971 INDEXED BY wp_schema_publish_done_key_next971 WHERE publish_key = ?'],
    ['name' => 'queue-ready-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_ready_next919 INDEXED BY wp_job_retry_checkpoint_ready_key_next919 SET locked = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'report-reader', 'sql' => 'SELECT report_id FROM report.wp_schema_report_receipt_next957 INDEXED BY wp_schema_report_receipt_key_next957 WHERE report_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next968 INDEXED BY wp_schema_review_receipt_key_next968 WHERE review_key = ?'],
    ['name' => 'verify-reader', 'sql' => 'SELECT verify_id FROM verify.wp_schema_verify_receipt_next984 INDEXED BY wp_schema_verify_receipt_key_next984 WHERE verify_key = ?'],
];

$plan973988 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheFinalHandoffWindow(
    $schemas ?? $schemas973988,
    $statements ?? $statements973988,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next973-988 extends next957-972 handoff'] = static function (TestRunner $t) use ($plan973988): void {
    $result = $plan973988([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 976, 'table' => 'wp_navigation_rule_locale_publish_batch_next976', 'indexes' => ['wp_navigation_rule_locale_publish_batch_key_next976'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 980, 'table' => 'wp_theme_stage_publish_token_next980', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'review', 'from' => 'wp_schema_review_receipt_key_next968', 'to' => 'wp_schema_review_receipt_key_next982'],
        ['op' => 'attach', 'schema' => 'verify', 'schema_cookie' => 984, 'tables' => ['wp_schema_verify_receipt_next984'], 'indexes' => ['wp_schema_verify_receipt_key_next984'], 'file' => '/srv/wp/verify-next984.sqlite'],
        ['op' => 'detach', 'schema' => 'report'],
        ['op' => 'rename_table', 'schema' => 'archive', 'from' => 'wp_schema_archive_receipt_next952', 'to' => 'wp_schema_archive_receipt_next986'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 987, 'table' => 'wp_schema_publish_done_next987', 'indexes' => ['wp_schema_publish_done_key_next987'], 'commit' => true],
        ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_ready_next919'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 988, 'table' => 'wp_navigation_rule_locale_publish_final_next988', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next988'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'verify', 'schema_cookie' => 973, 'table' => 'wp_schema_verify_uncommitted_next973', 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(988, $result['schema_cookies_next']['main']);
    $t->same(980, $result['schema_cookies_next']['temp']);
    $t->same(954, $result['schema_cookies_next']['archive']);
    $t->same(955, $result['schema_cookies_next']['handoff']);
    $t->same(987, $result['schema_cookies_next']['publish']);
    $t->same(921, $result['schema_cookies_next']['queue']);
    $t->same(969, $result['schema_cookies_next']['review']);
    $t->same(984, $result['schema_cookies_next']['verify']);
    $t->same(false, isset($result['schema_cookies_next']['report']));
    $t->same(['main-final-reader', 'queue-ready-writer'], $result['active_current_snapshot_statements']);
    $t->same(['queue-ready-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['archive-receipt-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-ready-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['report-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['review-reader']['index_transitions'][0]['next_found']);
    $t->same('verify', $result['statements']['verify-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['handoff-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next973-988 ignores detached sandbox verify'] = static function (TestRunner $t) use ($plan973988): void {
    $result = $plan973988([
        ['op' => 'attach', 'schema' => 'sandbox', 'schema_cookie' => 973, 'tables' => ['wp_schema_sandbox_verify_next973'], 'indexes' => ['wp_schema_sandbox_verify_key_next973'], 'file' => '/srv/wp/sandbox-next973.sqlite'],
        ['op' => 'schema_write', 'schema' => 'sandbox', 'schema_cookie' => 974, 'table' => 'wp_schema_sandbox_verify_meta_next974', 'indexes' => ['wp_schema_sandbox_verify_meta_key_next974'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'sandbox'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'handoff', 'publish', 'queue', 'report', 'review'], $result['search_order_next']);
};

return $tests;
