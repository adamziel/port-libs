<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next243 checkpoint database image');
$pageCacheDigest = $hash('next243 reopened reader page cache');
$baselinePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next240',
    'autocheckpoint_baseline_allowed' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next243.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next243.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next243.sqlite-wal',
    'source_token' => 'wp-next243-current-source',
    'commit_generation' => 243,
    'schema_cookie' => 743,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'wal_index_salt' => ['next243-salt-a', 'next243-salt-b'],
    'wal_index_mx_frame' => 18,
    'checkpoint_frame' => 14,
    'dirty_pages' => [1, 2, 5],
    'commit_frames' => [15, 16, 18],
    'operation_names' => ['admit_autocheckpoint_baseline_next240'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next240'],
];

$reader = static function (string $name, array $pages, array $override = []) use ($baselinePlan, $databaseDigest, $pageCacheDigest): array {
    return array_replace([
        'name' => $name,
        'source_token' => $baselinePlan['source_token'],
        'commit_generation' => $baselinePlan['commit_generation'],
        'schema_cookie' => $baselinePlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_index_salt' => $baselinePlan['wal_index_salt'],
        'wal_index_mx_frame' => $baselinePlan['wal_index_mx_frame'],
        'checkpoint_frame' => $baselinePlan['checkpoint_frame'],
        'readmark_frame' => 18,
        'observed_pages' => $pages,
        'observed_commit_frames' => $baselinePlan['commit_frames'],
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
        'page_cache_clean' => true,
        'shared_lock_held' => true,
        'reader_reopened_after_commit' => true,
    ], $override);
};

$receipts = [
    $reader('schema-reader', [1], ['readmark_frame' => 15]),
    $reader('options-reader', [2], ['readmark_frame' => 16]),
    $reader('index-reader', [5]),
];
$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline($baselinePlan, $receipts);
$blockedReader = static fn (array $override): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(
    $baselinePlan,
    [
        $receipts[0],
        $reader('options-blocked-reader', [2], $override),
        $receipts[2],
    ]
);
$missingDirtyPage = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(
    $baselinePlan,
    [$receipts[0], $receipts[1]]
);
$duplicateReader = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(
    $baselinePlan,
    [$receipts[0], $reader('schema-reader', [2]), $receipts[2]]
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next243'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reopened_reader_snapshots_match_autocheckpoint_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next240'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next243.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next243.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next243.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next243-current-source'],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 243],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 743],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'wal salt' => [static fn (): mixed => $plan()['wal_index_salt'], ['next243-salt-a', 'next243-salt-b']],
    'wal mx frame' => [static fn (): mixed => $plan()['wal_index_mx_frame'], 18],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 14],
    'dirty pages sorted' => [static fn (): mixed => $plan()['dirty_pages'], [1, 2, 5]],
    'commit frames sorted' => [static fn (): mixed => $plan()['commit_frames'], [15, 16, 18]],
    'reader names' => [static fn (): mixed => $plan()['reader_names'], ['schema-reader', 'options-reader', 'index-reader']],
    'accepted readers' => [static fn (): mixed => $plan()['accepted_reader_names'], ['schema-reader', 'options-reader', 'index-reader']],
    'blocked readers empty' => [static fn (): mixed => $plan()['blocked_reader_names'], []],
    'duplicate readers empty' => [static fn (): mixed => $plan()['duplicate_reader_names'], []],
    'observed dirty pages' => [static fn (): mixed => $plan()['observed_dirty_pages'], [1, 2, 5]],
    'missing dirty pages empty' => [static fn (): mixed => $plan()['missing_dirty_pages'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reader_reasons'], []],
    'snapshot admitted' => [static fn (): mixed => $plan()['reader_snapshot_admitted'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'serve_reopened_readers_from_autocheckpoint_current_source'],
    'pager action' => [static fn (): mixed => $plan()['pager_action'], 'promote_clean_checkpoint_page_cache_to_reader_snapshot'],
    'wal index action' => [static fn (): mixed => $plan()['wal_index_action'], 'publish_committed_wal_index_readmark_baseline'],
    'hot journal action' => [static fn (): mixed => $plan()['hot_journal_action'], 'keep_hot_journal_deleted_for_reopened_readers'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next240_autocheckpoint_baseline_admitted', 'reader_snapshot_receipt_names_unique', 'all_dirty_pages_observed_by_reopened_readers', 'all_reader_snapshots_match_checkpoint_baseline']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'row count' => [static fn (): mixed => count($plan()['reader_rows']), 3],
    'row reason' => [static fn (): mixed => $plan()['reader_rows'][1]['reader_reason'], 'reader_snapshot_matches_autocheckpoint_current_source'],
    'row pages' => [static fn (): mixed => $plan()['reader_rows'][1]['observed_pages'], [2]],
    'row commit frames' => [static fn (): mixed => $plan()['reader_rows'][1]['observed_commit_frames'], [15, 16, 18]],
    'row readmark' => [static fn (): mixed => $plan()['reader_rows'][1]['readmark_frame'], 16],
    'row page cache clean' => [static fn (): mixed => $plan()['reader_rows'][1]['page_cache_clean'], true],
    'row shared lock' => [static fn (): mixed => $plan()['reader_rows'][1]['shared_lock_held'], true],
    'row reopened' => [static fn (): mixed => $plan()['reader_rows'][1]['reader_reopened_after_commit'], true],
    'snapshot digest length' => [static fn (): mixed => strlen($plan()['snapshot_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_autocheckpoint_baseline_next240', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_reopened_reader_snapshot_baseline_next243', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next240', $plan()['dependencies'], true), true],
    'dependency next243' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next243', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-reader-snapshot-after-autocheckpoint', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat checkpoint publication'), true],
    'stale token blocked' => [static fn (): mixed => $blockedReader(['source_token' => 'old-source'])['blocked_reader_reasons'], ['reader_snapshot_source_token_mismatch', 'reader_snapshot_dirty_page_unobserved']],
    'stale generation blocked' => [static fn (): mixed => $blockedReader(['commit_generation' => 242])['blocked_reader_reasons'], ['reader_snapshot_commit_generation_mismatch', 'reader_snapshot_dirty_page_unobserved']],
    'stale schema blocked' => [static fn (): mixed => $blockedReader(['schema_cookie' => 742])['blocked_reader_reasons'], ['reader_snapshot_schema_cookie_mismatch', 'reader_snapshot_dirty_page_unobserved']],
    'stale database blocked' => [static fn (): mixed => $blockedReader(['database_digest' => $hash('stale database')])['blocked_reader_reasons'], ['reader_snapshot_database_digest_mismatch', 'reader_snapshot_dirty_page_unobserved']],
    'stale page cache blocked' => [static fn (): mixed => $blockedReader(['page_cache_digest' => $hash('stale page cache')])['blocked_reader_reasons'], ['reader_snapshot_page_cache_digest_mismatch', 'reader_snapshot_dirty_page_unobserved']],
    'stale wal salt blocked' => [static fn (): mixed => $blockedReader(['wal_index_salt' => ['old-a', 'old-b']])['blocked_reader_reasons'], ['reader_snapshot_wal_index_salt_mismatch', 'reader_snapshot_dirty_page_unobserved']],
    'stale mx frame blocked' => [static fn (): mixed => $blockedReader(['wal_index_mx_frame' => 17])['blocked_reader_reasons'], ['reader_snapshot_wal_index_mx_frame_mismatch', 'reader_snapshot_dirty_page_unobserved']],
    'stale checkpoint frame blocked' => [static fn (): mixed => $blockedReader(['checkpoint_frame' => 13])['blocked_reader_reasons'], ['reader_snapshot_checkpoint_frame_mismatch', 'reader_snapshot_dirty_page_unobserved']],
    'readmark beyond mx blocked' => [static fn (): mixed => $blockedReader(['readmark_frame' => 19])['blocked_reader_reasons'], ['reader_snapshot_readmark_beyond_mx_frame', 'reader_snapshot_dirty_page_unobserved']],
    'hot journal blocked' => [static fn (): mixed => $blockedReader(['hot_journal_visible' => true])['blocked_reader_reasons'], ['reader_snapshot_hot_journal_visible', 'reader_snapshot_dirty_page_unobserved']],
    'savepoint blocked' => [static fn (): mixed => $blockedReader(['savepoint_depth' => 1])['blocked_reader_reasons'], ['reader_snapshot_savepoint_scope_open', 'reader_snapshot_dirty_page_unobserved']],
    'dirty cache blocked' => [static fn (): mixed => $blockedReader(['page_cache_clean' => false])['blocked_reader_reasons'], ['reader_snapshot_page_cache_dirty', 'reader_snapshot_dirty_page_unobserved']],
    'shared lock blocked' => [static fn (): mixed => $blockedReader(['shared_lock_held' => false])['blocked_reader_reasons'], ['reader_snapshot_shared_lock_missing', 'reader_snapshot_dirty_page_unobserved']],
    'not reopened blocked' => [static fn (): mixed => $blockedReader(['reader_reopened_after_commit' => false])['blocked_reader_reasons'], ['reader_snapshot_not_reopened_after_commit', 'reader_snapshot_dirty_page_unobserved']],
    'missing commit frame blocked' => [static fn (): mixed => $blockedReader(['observed_commit_frames' => [15, 16]])['blocked_reader_reasons'], ['reader_snapshot_commit_frame_missing', 'reader_snapshot_dirty_page_unobserved']],
    'unknown page blocked' => [static fn (): mixed => $blockedReader(['observed_pages' => [2, 9]])['blocked_reader_reasons'], ['reader_snapshot_page_not_in_dirty_set', 'reader_snapshot_dirty_page_unobserved']],
    'combined reasons' => [static fn (): mixed => $blockedReader(['source_token' => 'old-source', 'hot_journal_visible' => true, 'page_cache_clean' => false])['blocked_reader_reasons'], ['reader_snapshot_source_token_mismatch', 'reader_snapshot_hot_journal_visible', 'reader_snapshot_page_cache_dirty', 'reader_snapshot_dirty_page_unobserved']],
    'blocked status' => [static fn (): mixed => $blockedReader(['source_token' => 'old-source'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next243'],
    'blocked reason' => [static fn (): mixed => $blockedReader(['source_token' => 'old-source'])['reason'], 'reopened_reader_snapshots_hold_autocheckpoint_current_source'],
    'blocked reader action' => [static fn (): mixed => $blockedReader(['source_token' => 'old-source'])['reader_action'], 'force_reopen_readers_before_current_source_switch'],
    'blocked pager action' => [static fn (): mixed => $blockedReader(['source_token' => 'old-source'])['pager_action'], 'retain_checkpoint_page_cache_until_readers_match'],
    'blocked wal action' => [static fn (): mixed => $blockedReader(['source_token' => 'old-source'])['wal_index_action'], 'hold_wal_index_readmark_baseline'],
    'blocked hot journal action' => [static fn (): mixed => $blockedReader(['source_token' => 'old-source'])['hot_journal_action'], 'fence_hot_journal_visible_readers'],
    'blocked guards' => [static fn (): mixed => $blockedReader(['source_token' => 'old-source'])['blocked_guard_names'], ['all_dirty_pages_observed_by_reopened_readers', 'all_reader_snapshots_match_checkpoint_baseline']],
    'missing dirty page status' => [static fn (): mixed => $missingDirtyPage()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next243'],
    'missing dirty page list' => [static fn (): mixed => $missingDirtyPage()['missing_dirty_pages'], [5]],
    'missing dirty page guard' => [static fn (): mixed => $missingDirtyPage()['blocked_guard_names'], ['all_dirty_pages_observed_by_reopened_readers']],
    'duplicate reader names' => [static fn (): mixed => $duplicateReader()['duplicate_reader_names'], ['schema-reader']],
    'duplicate reader guard' => [static fn (): mixed => $duplicateReader()['blocked_guard_names'], ['reader_snapshot_receipt_names_unique']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next243 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['status' => 'bad']), $receipts),
    'not admitted rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['autocheckpoint_baseline_allowed' => false]), $receipts),
    'empty receipts rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline($baselinePlan, []),
    'bad database path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['database_path' => '']), $receipts),
    'bad wal path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['wal_path' => '']), $receipts),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['source_token' => 'bad token']), $receipts),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['commit_generation' => 0]), $receipts),
    'bad schema rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['schema_cookie' => 0]), $receipts),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['database_digest' => 'short']), $receipts),
    'bad page cache digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['page_cache_digest' => 'short']), $receipts),
    'bad wal salt rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['wal_index_salt' => ['one']]), $receipts),
    'bad mx frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['wal_index_mx_frame' => -1]), $receipts),
    'bad dirty pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['dirty_pages' => []]), $receipts),
    'bad commit frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline(array_replace($baselinePlan, ['commit_frames' => [0]]), $receipts),
    'bad reader name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline($baselinePlan, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad reader generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline($baselinePlan, [array_replace($receipts[0], ['commit_generation' => 0])]),
    'bad reader digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline($baselinePlan, [array_replace($receipts[0], ['database_digest' => 'short'])]),
    'bad reader pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline($baselinePlan, [array_replace($receipts[0], ['observed_pages' => ['bad']])]),
    'bad reader frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline($baselinePlan, [array_replace($receipts[0], ['observed_commit_frames' => ['bad']])]),
    'bad reader readmark rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline($baselinePlan, [array_replace($receipts[0], ['readmark_frame' => -1])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next243 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
