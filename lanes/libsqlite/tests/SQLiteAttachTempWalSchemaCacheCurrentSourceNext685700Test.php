<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas685700 = [
    'main' => [
        'schema_cookie' => 684,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next684'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next684'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 684, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 526,
        'tables' => ['wp_theme_stage_publish_retries_next558'],
        'indexes' => [],
        'temp' => true,
    ],
    'audit' => [
        'schema_cookie' => 662,
        'tables' => ['wp_schema_audit_next660'],
        'indexes' => ['wp_schema_audit_key_next660'],
        'file' => '/srv/wp/audit-next685.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 676,
        'tables' => ['wp_schema_handoff_next676'],
        'indexes' => ['wp_schema_handoff_key_next676'],
        'file' => '/srv/wp/handoff-next685.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 680,
        'tables' => ['wp_schema_publish_next679', 'wp_schema_publish_meta_next680'],
        'indexes' => ['wp_schema_publish_key_next679', 'wp_schema_publish_meta_key_next680'],
        'file' => '/srv/wp/publish-next685.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 672,
        'tables' => ['wp_job_retry_checkpoint_final_next672', 'wp_job_retry_checkpoint_preview_next620'],
        'indexes' => ['wp_job_retry_checkpoint_final_key_next672'],
        'file' => '/srv/wp/queue-next685.sqlite',
    ],
];

$statements685700 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_publish_final_next684 INDEXED BY wp_navigation_rule_locale_publish_final_key_next684 WHERE final_key = ?', 'active' => true],
    ['name' => 'queue-final-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_final_next672 INDEXED BY wp_job_retry_checkpoint_final_key_next672 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_next679 INDEXED BY wp_schema_publish_key_next679 WHERE publish_key = ?', 'active' => true],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_next676 INDEXED BY wp_schema_handoff_key_next676 WHERE handoff_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_next660 INDEXED BY wp_schema_audit_key_next660 WHERE audit_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next690 INDEXED BY wp_schema_archive_receipt_key_next690 WHERE archive_key = ?'],
    ['name' => 'report-reader', 'sql' => 'SELECT report_id FROM report.wp_schema_report_next693 INDEXED BY wp_schema_report_key_next693 WHERE report_key = ?'],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 WHERE cache_key = ?'],
];

$plan685700 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext685700(
    $schemas ?? $schemas685700,
    $statements ?? $statements685700,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next685-700 extends next669-684 handoff'] = static function (TestRunner $t) use ($plan685700): void {
    $result = $plan685700([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 685, 'table' => 'wp_navigation_rule_locale_publish_receipt_next685', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next685'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'publish', 'from' => 'wp_schema_publish_key_next679', 'to' => 'wp_schema_publish_key_next686'],
        ['op' => 'drop_table', 'schema' => 'handoff', 'table' => 'wp_schema_handoff_next676'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 688, 'table' => 'wp_job_retry_checkpoint_delivered_next688', 'indexes' => ['wp_job_retry_checkpoint_delivered_key_next688'], 'commit' => true],
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 690, 'tables' => ['wp_schema_archive_receipt_next690'], 'indexes' => ['wp_schema_archive_receipt_key_next690'], 'file' => '/srv/wp/archive-next690.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 691, 'table' => 'wp_schema_archive_meta_next691', 'indexes' => ['wp_schema_archive_meta_key_next691'], 'commit' => false],
        ['op' => 'attach', 'schema' => 'report', 'schema_cookie' => 693, 'tables' => ['wp_schema_report_next693'], 'indexes' => ['wp_schema_report_key_next693'], 'file' => '/srv/wp/report-next693.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'report', 'schema_cookie' => 694, 'table' => 'wp_schema_report_meta_next694', 'indexes' => ['wp_schema_report_meta_key_next694'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'audit'],
        ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_navigation_rule_locale_publish_final_next684', 'to' => 'wp_navigation_rule_locale_publish_final_next698'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 700, 'table' => 'wp_navigation_rule_locale_publish_final_next700', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next700'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next685-700', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next685', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next700', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next669', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next684', $result['dependencies'][31]);
    $t->same(10, $result['event_count']);
    $t->same(700, $result['schema_cookies_next']['main']);
    $t->same(526, $result['schema_cookies_next']['temp']);
    $t->same(690, $result['schema_cookies_next']['archive']);
    $t->same(677, $result['schema_cookies_next']['handoff']);
    $t->same(681, $result['schema_cookies_next']['publish']);
    $t->same(688, $result['schema_cookies_next']['queue']);
    $t->same(694, $result['schema_cookies_next']['report']);
    $t->same(['main-final-reader', 'publish-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-final-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['main-final-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['publish-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-reader']['schema_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['audit-reader']['schema_transitions'][0]['next_schema']);
    $t->same('archive', $result['statements']['archive-reader']['schema_transitions'][0]['next_schema']);
    $t->same('report', $result['statements']['report-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['temp-retry-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next685-700 ignores detached scratch receipt'] = static function (TestRunner $t) use ($plan685700): void {
    $result = $plan685700([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 685, 'tables' => ['wp_scratch_next685'], 'indexes' => ['wp_scratch_key_next685'], 'file' => '/srv/wp/scratch-next685.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 686, 'table' => 'wp_scratch_meta_next686', 'indexes' => ['wp_scratch_meta_key_next686'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'audit', 'handoff', 'publish', 'queue'], $result['search_order_next']);
};

return $tests;
