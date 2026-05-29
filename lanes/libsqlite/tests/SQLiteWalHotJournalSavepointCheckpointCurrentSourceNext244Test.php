<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next244 durable checkpoint database image');
$pageCacheDigest = $hash('next244 checkpoint page cache');
$baselinePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next240',
    'autocheckpoint_baseline_allowed' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next244.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next244.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next244.sqlite-wal',
    'source_token' => 'wp-next244-current-source',
    'schema_cookie' => 744,
    'commit_generation' => 245,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'wal_index_salt' => ['next244-salt-a', 'next244-salt-b'],
    'wal_index_mx_frame' => 18,
    'checkpoint_frame' => 15,
    'dirty_pages' => [1, 2, 5],
    'commit_frames' => [16, 17, 18],
    'operation_names' => ['admit_autocheckpoint_baseline_next240'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next240'],
];

$durable = static function (string $name, array $pages, array $frames, array $override = []) use ($baselinePlan, $databaseDigest, $pageCacheDigest): array {
    return array_replace([
        'name' => $name,
        'source_token' => $baselinePlan['source_token'],
        'schema_cookie' => $baselinePlan['schema_cookie'],
        'commit_generation' => $baselinePlan['commit_generation'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_index_salt' => $baselinePlan['wal_index_salt'],
        'wal_index_mx_frame' => $baselinePlan['wal_index_mx_frame'],
        'checkpoint_frame' => $baselinePlan['checkpoint_frame'],
        'database_pages_written' => $pages,
        'wal_frames_synced' => $frames,
        'exclusive_lock_held' => true,
        'database_sync_done' => true,
        'wal_sync_done' => true,
        'directory_sync_done' => true,
        'hot_journal_deleted' => true,
        'stale_wal_preserved' => false,
    ], $override);
};
$reader = static function (string $name, array $override = []) use ($baselinePlan, $databaseDigest): array {
    return array_replace([
        'name' => $name,
        'source_token' => $baselinePlan['source_token'],
        'schema_cookie' => $baselinePlan['schema_cookie'],
        'commit_generation' => $baselinePlan['commit_generation'],
        'database_digest' => $databaseDigest,
        'wal_index_salt' => $baselinePlan['wal_index_salt'],
        'wal_index_mx_frame' => $baselinePlan['wal_index_mx_frame'],
        'checkpoint_frame' => $baselinePlan['checkpoint_frame'],
        'reader_generation' => $baselinePlan['commit_generation'],
        'snapshot_reopened' => true,
        'readmark_cleared' => true,
        'hot_journal_seen' => false,
        'stale_wal_seen' => false,
    ], $override);
};

$durables = [
    $durable('database-main-sync', [1, 2], [16, 17]),
    $durable('database-index-sync', [5], [18]),
];
$readers = [
    $reader('wp-options-reader'),
    $reader('wp-usermeta-reader'),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource($baselinePlan, $durables, $readers);
$blockedDurable = static fn (array $override): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(
    $baselinePlan,
    [$durables[0], $durable('database-index-blocked', [5], [18], $override)],
    $readers
);
$blockedReader = static fn (array $override): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(
    $baselinePlan,
    $durables,
    [$readers[0], $reader('wp-usermeta-blocked', $override)]
);
$missingPage = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(
    $baselinePlan,
    [$durable('database-main-sync', [1, 2], [16, 17, 18])],
    $readers
);
$missingFrame = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(
    $baselinePlan,
    [$durable('database-main-sync', [1, 2, 5], [16, 17])],
    $readers
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next244'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'durable_current_source_sealed_after_hot_journal_savepoint_checkpoint'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next240'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next244.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next244.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next244.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next244-current-source'],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 744],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 245],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'wal salt' => [static fn (): mixed => $plan()['wal_index_salt'], ['next244-salt-a', 'next244-salt-b']],
    'wal mx frame' => [static fn (): mixed => $plan()['wal_index_mx_frame'], 18],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 15],
    'expected pages' => [static fn (): mixed => $plan()['expected_dirty_pages'], [1, 2, 5]],
    'written pages' => [static fn (): mixed => $plan()['durably_written_pages'], [1, 2, 5]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_dirty_pages'], []],
    'expected frames' => [static fn (): mixed => $plan()['expected_commit_frames'], [16, 17, 18]],
    'synced frames' => [static fn (): mixed => $plan()['durably_synced_frames'], [16, 17, 18]],
    'missing frames empty' => [static fn (): mixed => $plan()['missing_commit_frames'], []],
    'admitted durable names' => [static fn (): mixed => $plan()['admitted_durable_names'], ['database-main-sync', 'database-index-sync']],
    'blocked durable empty' => [static fn (): mixed => $plan()['blocked_durable_names'], []],
    'admitted readers' => [static fn (): mixed => $plan()['admitted_reader_names'], ['wp-options-reader', 'wp-usermeta-reader']],
    'blocked readers empty' => [static fn (): mixed => $plan()['blocked_reader_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'sealed' => [static fn (): mixed => $plan()['sealed_current_source'], true],
    'hot journal action' => [static fn (): mixed => $plan()['hot_journal_action'], 'delete_hot_journal_after_durable_checkpoint'],
    'wal sidecar action' => [static fn (): mixed => $plan()['wal_sidecar_action'], 'reset_or_truncate_wal_sidecar_after_reader_acknowledgements'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'advance_readers_to_commit_generation_245'],
    'page cache action' => [static fn (): mixed => $plan()['page_cache_action'], 'seal_clean_page_cache_digest_' . $pageCacheDigest],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['all_dirty_pages_durably_written', 'all_commit_frames_durably_synced', 'durable_receipts_match_current_source', 'reader_acknowledgements_match_current_source']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'durable row reason' => [static fn (): mixed => $plan()['durable_rows'][0]['receipt_reason'], 'durable_receipt_seals_checkpoint_current_source'],
    'durable row pages' => [static fn (): mixed => $plan()['durable_rows'][0]['database_pages_written'], [1, 2]],
    'durable row frames' => [static fn (): mixed => $plan()['durable_rows'][0]['wal_frames_synced'], [16, 17]],
    'durable lock' => [static fn (): mixed => $plan()['durable_rows'][0]['exclusive_lock_held'], true],
    'durable database sync' => [static fn (): mixed => $plan()['durable_rows'][0]['database_sync_done'], true],
    'durable wal sync' => [static fn (): mixed => $plan()['durable_rows'][0]['wal_sync_done'], true],
    'durable directory sync' => [static fn (): mixed => $plan()['durable_rows'][0]['directory_sync_done'], true],
    'durable hot journal deleted' => [static fn (): mixed => $plan()['durable_rows'][0]['hot_journal_deleted'], true],
    'reader row reason' => [static fn (): mixed => $plan()['reader_rows'][0]['reader_reason'], 'reader_acknowledges_durable_checkpoint_current_source'],
    'reader generation' => [static fn (): mixed => $plan()['reader_rows'][0]['reader_generation'], 245],
    'reader snapshot' => [static fn (): mixed => $plan()['reader_rows'][0]['snapshot_reopened'], true],
    'reader readmark' => [static fn (): mixed => $plan()['reader_rows'][0]['readmark_cleared'], true],
    'digest length' => [static fn (): mixed => strlen($plan()['seal_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_autocheckpoint_baseline_next240', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('seal_durable_current_source_next244', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next240', $plan()['dependencies'], true), true],
    'dependency next244' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next244', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-hot-journal-savepoint-checkpoint-durable-seal', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next240 commit baseline admission'), true],
    'durable source mismatch blocked' => [static fn (): mixed => $blockedDurable(['source_token' => 'stale-source'])['blocked_reasons'], ['source_token_mismatch']],
    'durable schema mismatch blocked' => [static fn (): mixed => $blockedDurable(['schema_cookie' => 743])['blocked_reasons'], ['schema_cookie_mismatch']],
    'durable generation mismatch blocked' => [static fn (): mixed => $blockedDurable(['commit_generation' => 246])['blocked_reasons'], ['commit_generation_mismatch']],
    'durable checkpoint mismatch blocked' => [static fn (): mixed => $blockedDurable(['checkpoint_frame' => 14])['blocked_reasons'], ['checkpoint_frame_mismatch']],
    'durable mx frame mismatch blocked' => [static fn (): mixed => $blockedDurable(['wal_index_mx_frame' => 17])['blocked_reasons'], ['wal_index_mx_frame_mismatch']],
    'durable database mismatch blocked' => [static fn (): mixed => $blockedDurable(['database_digest' => $hash('stale db')])['blocked_reasons'], ['database_digest_mismatch']],
    'durable cache mismatch blocked' => [static fn (): mixed => $blockedDurable(['page_cache_digest' => $hash('stale cache')])['blocked_reasons'], ['durable_page_cache_digest_mismatch']],
    'durable salt mismatch blocked' => [static fn (): mixed => $blockedDurable(['wal_index_salt' => ['old-a', 'old-b']])['blocked_reasons'], ['wal_index_salt_mismatch']],
    'unexpected page blocked' => [static fn (): mixed => $blockedDurable(['database_pages_written' => [5, 9]])['blocked_reasons'], ['durable_unexpected_database_page']],
    'unexpected frame blocked' => [static fn (): mixed => $blockedDurable(['wal_frames_synced' => [18, 19]])['blocked_reasons'], ['durable_unexpected_wal_frame']],
    'missing lock blocked' => [static fn (): mixed => $blockedDurable(['exclusive_lock_held' => false])['blocked_reasons'], ['durable_exclusive_lock_missing']],
    'missing db sync blocked' => [static fn (): mixed => $blockedDurable(['database_sync_done' => false])['blocked_reasons'], ['durable_database_sync_missing']],
    'missing wal sync blocked' => [static fn (): mixed => $blockedDurable(['wal_sync_done' => false])['blocked_reasons'], ['durable_wal_sync_missing']],
    'missing dir sync blocked' => [static fn (): mixed => $blockedDurable(['directory_sync_done' => false])['blocked_reasons'], ['durable_directory_sync_missing']],
    'hot journal delete blocked' => [static fn (): mixed => $blockedDurable(['hot_journal_deleted' => false])['blocked_reasons'], ['durable_hot_journal_not_deleted']],
    'stale wal preserved blocked' => [static fn (): mixed => $blockedDurable(['stale_wal_preserved' => true])['blocked_reasons'], ['durable_stale_wal_preserved']],
    'reader generation blocked' => [static fn (): mixed => $blockedReader(['reader_generation' => 244])['blocked_reasons'], ['reader_generation_mismatch']],
    'reader snapshot blocked' => [static fn (): mixed => $blockedReader(['snapshot_reopened' => false])['blocked_reasons'], ['reader_snapshot_not_reopened']],
    'reader readmark blocked' => [static fn (): mixed => $blockedReader(['readmark_cleared' => false])['blocked_reasons'], ['reader_readmark_not_cleared']],
    'reader hot journal blocked' => [static fn (): mixed => $blockedReader(['hot_journal_seen' => true])['blocked_reasons'], ['reader_hot_journal_still_visible']],
    'reader stale wal blocked' => [static fn (): mixed => $blockedReader(['stale_wal_seen' => true])['blocked_reasons'], ['reader_stale_wal_still_visible']],
    'reader source mismatch blocked' => [static fn (): mixed => $blockedReader(['source_token' => 'old-source'])['blocked_reasons'], ['source_token_mismatch']],
    'missing page status' => [static fn (): mixed => $missingPage()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next244'],
    'missing page list' => [static fn (): mixed => $missingPage()['missing_dirty_pages'], [5]],
    'missing page guard' => [static fn (): mixed => $missingPage()['blocked_guard_names'], ['all_dirty_pages_durably_written']],
    'missing frame status' => [static fn (): mixed => $missingFrame()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next244'],
    'missing frame list' => [static fn (): mixed => $missingFrame()['missing_commit_frames'], [18]],
    'blocked status' => [static fn (): mixed => $blockedDurable(['source_token' => 'stale-source'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next244'],
    'blocked hot journal action' => [static fn (): mixed => $blockedDurable(['source_token' => 'stale-source'])['hot_journal_action'], 'retain_hot_journal_until_durable_receipts_match'],
    'blocked wal sidecar action' => [static fn (): mixed => $blockedReader(['stale_wal_seen' => true])['wal_sidecar_action'], 'preserve_wal_sidecar_for_current_source_replay'],
    'combined blocked reasons sorted' => [static fn (): mixed => $blockedDurable(['source_token' => 'stale-source', 'wal_sync_done' => false, 'directory_sync_done' => false])['blocked_reasons'], ['durable_directory_sync_missing', 'durable_wal_sync_missing', 'source_token_mismatch']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next244 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['status' => 'bad']), $durables, $readers),
    'baseline not allowed rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['autocheckpoint_baseline_allowed' => false]), $durables, $readers),
    'empty durable rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource($baselinePlan, [], $readers),
    'empty readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource($baselinePlan, $durables, []),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['source_token' => 'bad token']), $durables, $readers),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['database_digest' => 'short']), $durables, $readers),
    'bad page cache digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['page_cache_digest' => 'short']), $durables, $readers),
    'bad schema rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['schema_cookie' => 0]), $durables, $readers),
    'bad commit generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['commit_generation' => 0]), $durables, $readers),
    'bad checkpoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['checkpoint_frame' => 0]), $durables, $readers),
    'bad mx frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['wal_index_mx_frame' => 0]), $durables, $readers),
    'bad salt rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['wal_index_salt' => ['one']]), $durables, $readers),
    'empty pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['dirty_pages' => []]), $durables, $readers),
    'empty frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource(array_replace($baselinePlan, ['commit_frames' => []]), $durables, $readers),
    'bad durable name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource($baselinePlan, [array_replace($durables[0], ['name' => 'bad name'])], $readers),
    'bad durable page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource($baselinePlan, [array_replace($durables[0], ['database_pages_written' => [0]])], $readers),
    'bad durable frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource($baselinePlan, [array_replace($durables[0], ['wal_frames_synced' => ['bad']])], $readers),
    'bad reader name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource($baselinePlan, $durables, [array_replace($readers[0], ['name' => 'bad name'])]),
    'bad reader generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource($baselinePlan, $durables, [array_replace($readers[0], ['commit_generation' => 0])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next244 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
