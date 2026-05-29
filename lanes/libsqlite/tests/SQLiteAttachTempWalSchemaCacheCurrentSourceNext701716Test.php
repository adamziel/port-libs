<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas701716 = [
    'main' => [
        'schema_cookie' => 700,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next700', 'wp_navigation_rule_locale_publish_final_next698'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next700'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 700, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 526,
        'tables' => ['wp_theme_stage_publish_retries_next558', 'wp_theme_stage_publish_preview_next704'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 690,
        'tables' => ['wp_schema_archive_receipt_next690'],
        'indexes' => ['wp_schema_archive_receipt_key_next690'],
        'file' => '/srv/wp/archive-next701.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 677,
        'tables' => ['wp_schema_handoff_receipt_next701'],
        'indexes' => ['wp_schema_handoff_receipt_key_next701'],
        'file' => '/srv/wp/handoff-next701.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 681,
        'tables' => ['wp_schema_publish_next679', 'wp_schema_publish_meta_next680'],
        'indexes' => ['wp_schema_publish_key_next686', 'wp_schema_publish_meta_key_next680'],
        'file' => '/srv/wp/publish-next701.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 688,
        'tables' => ['wp_job_retry_checkpoint_delivered_next688', 'wp_job_retry_checkpoint_preview_next620'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key_next688'],
        'file' => '/srv/wp/queue-next701.sqlite',
    ],
    'report' => [
        'schema_cookie' => 694,
        'tables' => ['wp_schema_report_next693', 'wp_schema_report_meta_next694'],
        'indexes' => ['wp_schema_report_key_next693', 'wp_schema_report_meta_key_next694'],
        'file' => '/srv/wp/report-next701.sqlite',
    ],
];

$statements701716 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_publish_final_next700 INDEXED BY wp_navigation_rule_locale_publish_final_key_next700 WHERE final_key = ?', 'active' => true],
    ['name' => 'queue-delivery-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_delivered_next688 INDEXED BY wp_job_retry_checkpoint_delivered_key_next688 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_next679 INDEXED BY wp_schema_publish_key_next686 WHERE publish_key = ?', 'active' => true],
    ['name' => 'handoff-receipt-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next701 INDEXED BY wp_schema_handoff_receipt_key_next701 WHERE handoff_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next690 INDEXED BY wp_schema_archive_receipt_key_next690 WHERE archive_key = ?'],
    ['name' => 'report-reader', 'sql' => 'SELECT report_id FROM report.wp_schema_report_next693 INDEXED BY wp_schema_report_key_next693 WHERE report_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next709 INDEXED BY wp_schema_review_receipt_key_next709 WHERE review_key = ?'],
    ['name' => 'metrics-reader', 'sql' => 'SELECT metric_id FROM metrics.wp_schema_metrics_next713 INDEXED BY wp_schema_metrics_key_next713 WHERE metric_key = ?'],
    ['name' => 'temp-preview-reader', 'sql' => 'SELECT preview_id FROM temp.wp_theme_stage_publish_preview_next704 WHERE cache_key = ?'],
];

$plan701716 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext701716(
    $schemas ?? $schemas701716,
    $statements ?? $statements701716,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next701-716 extends next685-700 handoff'] = static function (TestRunner $t) use ($plan701716): void {
    $result = $plan701716([
        ['op' => 'schema_write', 'schema' => 'handoff', 'schema_cookie' => 701, 'table' => 'wp_schema_handoff_receipt_next701', 'indexes' => ['wp_schema_handoff_receipt_key_next701'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_delivered_key_next688', 'to' => 'wp_job_retry_checkpoint_delivered_key_next702'],
        ['op' => 'drop_table', 'schema' => 'archive', 'table' => 'wp_schema_archive_receipt_next690'],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 704, 'table' => 'wp_theme_stage_publish_preview_next704', 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 706, 'table' => 'wp_schema_publish_done_next706', 'indexes' => ['wp_schema_publish_done_key_next706'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 707, 'table' => 'wp_schema_publish_uncommitted_next707', 'indexes' => ['wp_schema_publish_uncommitted_key_next707'], 'commit' => false],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 709, 'tables' => ['wp_schema_review_receipt_next709'], 'indexes' => ['wp_schema_review_receipt_key_next709'], 'file' => '/srv/wp/review-next709.sqlite'],
        ['op' => 'rename_table', 'schema' => 'report', 'from' => 'wp_schema_report_next693', 'to' => 'wp_schema_report_next711'],
        ['op' => 'attach', 'schema' => 'metrics', 'schema_cookie' => 713, 'tables' => ['wp_schema_metrics_next713'], 'indexes' => ['wp_schema_metrics_key_next713'], 'file' => '/srv/wp/metrics-next713.sqlite'],
        ['op' => 'detach', 'schema' => 'handoff'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 716, 'table' => 'wp_navigation_rule_locale_publish_receipt_next716', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next716'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next701-716', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next701', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next716', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next685', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next700', $result['dependencies'][31]);
    $t->same(10, $result['event_count']);
    $t->same(716, $result['schema_cookies_next']['main']);
    $t->same(704, $result['schema_cookies_next']['temp']);
    $t->same(691, $result['schema_cookies_next']['archive']);
    $t->same(713, $result['schema_cookies_next']['metrics']);
    $t->same(706, $result['schema_cookies_next']['publish']);
    $t->same(689, $result['schema_cookies_next']['queue']);
    $t->same(695, $result['schema_cookies_next']['report']);
    $t->same(709, $result['schema_cookies_next']['review']);
    $t->same(['main-final-reader', 'publish-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-delivery-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-delivery-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['archive-reader']['schema_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['handoff-receipt-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['report-reader']['schema_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same('metrics', $result['statements']['metrics-reader']['schema_transitions'][0]['next_schema']);
    $t->same([], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next701-716 ignores detached scratch receipt'] = static function (TestRunner $t) use ($plan701716): void {
    $result = $plan701716([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 701, 'tables' => ['wp_scratch_next701'], 'indexes' => ['wp_scratch_key_next701'], 'file' => '/srv/wp/scratch-next701.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 702, 'table' => 'wp_scratch_meta_next702', 'indexes' => ['wp_scratch_meta_key_next702'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
