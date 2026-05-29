<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 588, 'tables' => ['wp_options', 'wp_navigation_rule_locale_receipt_next572', 'wp_navigation_rule_locale_handoff_next573', 'wp_navigation_rule_locale_final_next588'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_receipt_key_next572', 'wp_navigation_rule_locale_handoff_key_next573', 'wp_navigation_rule_locale_final_key_next588'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 588, 'commit' => true]]],
    'temp' => ['schema_cookie' => 525, 'tables' => ['wp_theme_stage_publish_retries_next558'], 'indexes' => ['wp_theme_stage_publish_retries_key_next574'], 'temp' => true],
    'queue' => ['schema_cookie' => 576, 'tables' => ['wp_job_retry_checkpoint_lock_next560', 'wp_job_retry_checkpoint_cursor_next576', 'wp_job_retry_checkpoint_receipt_next587'], 'indexes' => ['wp_job_retry_checkpoint_lock_job_next560', 'wp_job_retry_checkpoint_cursor_job_next576', 'wp_job_retry_checkpoint_receipt_job_next587'], 'file' => '/srv/wp/queue-next589.sqlite'],
    'campaign' => ['schema_cookie' => 543, 'tables' => ['wp_campaign_restore_next524'], 'indexes' => ['wp_campaign_restore_slug_next526'], 'file' => '/srv/wp/campaign-next589.sqlite'],
    'archive' => ['schema_cookie' => 570, 'tables' => ['wp_schema_archive_next568', 'wp_schema_archive_meta_next569'], 'indexes' => ['wp_schema_archive_key_next568'], 'file' => '/srv/wp/archive-next589.sqlite'],
    'handoff' => ['schema_cookie' => 583, 'tables' => ['wp_schema_handoff_next582', 'wp_schema_handoff_meta_next583'], 'indexes' => ['wp_schema_handoff_key_next582', 'wp_schema_handoff_meta_key_next583'], 'file' => '/srv/wp/handoff-next589.sqlite'],
];

$statements = [
    ['name' => 'nav-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_final_next588 INDEXED BY wp_navigation_rule_locale_final_key_next588 WHERE final_key = ?', 'active' => true],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 INDEXED BY wp_theme_stage_publish_retries_key_next574 WHERE cache_key = ?', 'active' => true],
    ['name' => 'queue-receipt-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_receipt_next587 INDEXED BY wp_job_retry_checkpoint_receipt_job_next587 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'handoff-meta-reader', 'sql' => 'SELECT meta_id FROM handoff.wp_schema_handoff_meta_next583 INDEXED BY wp_schema_handoff_meta_key_next583 WHERE meta_key = ?'],
    ['name' => 'archive-root-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_next568 INDEXED BY wp_schema_archive_key_next568 WHERE archive_key = ?'],
    ['name' => 'campaign-restore-reader', 'sql' => 'SELECT restore_id FROM campaign.wp_campaign_restore_next524 INDEXED BY wp_campaign_restore_slug_next526 WHERE slug = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_next598 INDEXED BY wp_schema_publish_key_next598 WHERE publish_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext589604($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 589, 'table' => 'wp_navigation_rule_locale_publish_next589', 'indexes' => ['wp_navigation_rule_locale_publish_key_next589'], 'commit' => true],
    ['op' => 'drop_index', 'schema' => 'temp', 'index' => 'wp_theme_stage_publish_retries_key_next574'],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 592, 'table' => 'wp_job_retry_checkpoint_handoff_next592', 'indexes' => ['wp_job_retry_checkpoint_handoff_job_next592'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_meta_key_next583', 'to' => 'wp_schema_handoff_meta_key_next594'],
    ['op' => 'drop_table', 'schema' => 'archive', 'table' => 'wp_schema_archive_next568'],
    ['op' => 'attach', 'schema' => 'publish', 'schema_cookie' => 598, 'tables' => ['wp_schema_publish_next598'], 'indexes' => ['wp_schema_publish_key_next598'], 'file' => '/srv/wp/publish-next598.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 599, 'table' => 'wp_schema_publish_meta_next599', 'indexes' => ['wp_schema_publish_meta_key_next599'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'campaign'],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 602, 'table' => 'wp_job_retry_checkpoint_publish_next602', 'indexes' => ['wp_job_retry_checkpoint_publish_job_next602'], 'commit' => false],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 604, 'table' => 'wp_navigation_rule_locale_publish_final_next604', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next604'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next589-604');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next589');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next604');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next588', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 604);
    assert($plan['schema_cookies_next']['temp'] === 526);
    assert($plan['schema_cookies_next']['queue'] === 592);
    assert($plan['schema_cookies_next']['handoff'] === 584);
    assert($plan['schema_cookies_next']['archive'] === 571);
    assert($plan['schema_cookies_next']['publish'] === 599);
    assert(in_array('temp-retry-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-receipt-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['handoff-meta-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['campaign-restore-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['publish-reader']['schema_transitions'][0]['next_schema'] === 'publish');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next589-604 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
