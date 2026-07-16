<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next247 checkpoint database image');
$pageCacheDigest = $hash('next247 clean page cache image');
$readerBaseline = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next243',
    'reader_snapshot_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next247.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next247.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next247.sqlite-wal',
    'source_token' => 'wp-next247-current-source',
    'commit_generation' => 247,
    'schema_cookie' => 947,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'wal_index_salt' => ['next247-salt-a', 'next247-salt-b'],
    'wal_index_mx_frame' => 24,
    'checkpoint_frame' => 21,
    'dirty_pages' => [1, 2, 5, 8],
    'commit_frames' => [22, 23, 24],
    'accepted_reader_names' => ['schema-reader', 'options-reader', 'autoload-reader'],
    'operation_names' => ['admit_reopened_reader_snapshot_baseline_next243'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next243'],
];

$receipt = static function (string $name, string $kind, array $pages, array $frames, array $readers, array $override = []) use ($readerBaseline, $databaseDigest, $pageCacheDigest): array {
    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'database_path' => $readerBaseline['database_path'],
        'journal_path' => $readerBaseline['journal_path'],
        'wal_path' => $readerBaseline['wal_path'],
        'source_token' => $readerBaseline['source_token'],
        'commit_generation' => $readerBaseline['commit_generation'],
        'schema_cookie' => $readerBaseline['schema_cookie'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_index_salt' => $readerBaseline['wal_index_salt'],
        'wal_index_mx_frame' => $readerBaseline['wal_index_mx_frame'],
        'checkpoint_frame' => $readerBaseline['checkpoint_frame'],
        'page_numbers' => $pages,
        'commit_frames' => $frames,
        'reader_names' => $readers,
        'hot_journal_unlinked' => true,
        'wal_synced' => true,
        'directory_synced' => true,
        'reader_fenced' => true,
        'savepoint_released' => true,
        'savepoint_depth' => 0,
        'page_cache_clean' => true,
        'shared_lock_held' => true,
    ], $override);
};

$receipts = [
    $receipt('hot-journal-cleanup', 'hot-journal-unlink', [1], [22], ['schema-reader']),
    $receipt('wal-sync-cleanup', 'wal-sync', [2], [23], ['options-reader']),
    $receipt('directory-sync-cleanup', 'directory-sync', [5], [24], ['autoload-reader']),
    $receipt('savepoint-release-cleanup', 'savepoint-release', [8], [22, 23, 24], ['schema-reader', 'options-reader']),
    $receipt('reader-fence-cleanup', 'reader-fence', [1, 2, 5, 8], [22, 23, 24], ['schema-reader', 'options-reader', 'autoload-reader']),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup($readerBaseline, $receipts);
$blocked = static fn (array $override): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(
    $readerBaseline,
    [
        $receipts[0],
        $receipt('wal-sync-blocked', 'wal-sync', [2], [23], ['options-reader'], $override),
        $receipts[2],
        $receipts[3],
        $receipts[4],
    ]
);
$missingKind = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(
    $readerBaseline,
    [$receipts[0], $receipts[1], $receipts[2], $receipts[3]]
);
$missingReader = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(
    $readerBaseline,
    [
        $receipt('hot-journal-cleanup', 'hot-journal-unlink', [1], [22], ['schema-reader']),
        $receipt('wal-sync-cleanup', 'wal-sync', [2], [23], ['options-reader']),
        $receipt('directory-sync-cleanup', 'directory-sync', [5], [24], ['options-reader']),
        $receipt('savepoint-release-cleanup', 'savepoint-release', [8], [22, 23, 24], ['schema-reader']),
        $receipt('reader-fence-cleanup', 'reader-fence', [1, 2, 5, 8], [22, 23, 24], ['schema-reader', 'options-reader']),
    ]
);
$missingPage = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(
    $readerBaseline,
    [
        $receipts[0],
        $receipts[1],
        $receipts[2],
        $receipt('savepoint-release-cleanup', 'savepoint-release', [5], [22, 23, 24], ['schema-reader', 'options-reader']),
        $receipt('reader-fence-cleanup', 'reader-fence', [1, 2, 5], [22, 23, 24], ['schema-reader', 'options-reader', 'autoload-reader']),
    ]
);
$missingFrame = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(
    $readerBaseline,
    [
        $receipt('hot-journal-cleanup', 'hot-journal-unlink', [1], [22], ['schema-reader']),
        $receipt('wal-sync-cleanup', 'wal-sync', [2], [23], ['options-reader']),
        $receipt('directory-sync-cleanup', 'directory-sync', [5], [23], ['autoload-reader']),
        $receipt('savepoint-release-cleanup', 'savepoint-release', [8], [22, 23], ['schema-reader', 'options-reader']),
        $receipt('reader-fence-cleanup', 'reader-fence', [1, 2, 5, 8], [22, 23], ['schema-reader', 'options-reader', 'autoload-reader']),
    ]
);
$duplicateReceipt = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(
    $readerBaseline,
    [$receipts[0], $receipt('hot-journal-cleanup', 'wal-sync', [2], [23], ['options-reader']), $receipts[2], $receipts[3], $receipts[4]]
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next247'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'post_checkpoint_hot_journal_cleanup_sealed_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next243'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next247.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next247.sqlite-wal'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next247.sqlite-journal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next247-current-source'],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 247],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 947],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'wal salt' => [static fn (): mixed => $plan()['wal_index_salt'], ['next247-salt-a', 'next247-salt-b']],
    'wal mx frame' => [static fn (): mixed => $plan()['wal_index_mx_frame'], 24],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 21],
    'dirty pages' => [static fn (): mixed => $plan()['dirty_pages'], [1, 2, 5, 8]],
    'commit frames' => [static fn (): mixed => $plan()['commit_frames'], [22, 23, 24]],
    'reader names sorted' => [static fn (): mixed => $plan()['reader_names'], ['autoload-reader', 'options-reader', 'schema-reader']],
    'receipt names' => [static fn (): mixed => $plan()['receipt_names'], ['hot-journal-cleanup', 'wal-sync-cleanup', 'directory-sync-cleanup', 'savepoint-release-cleanup', 'reader-fence-cleanup']],
    'receipt kinds' => [static fn (): mixed => $plan()['receipt_kinds'], ['directory-sync', 'hot-journal-unlink', 'reader-fence', 'savepoint-release', 'wal-sync']],
    'required kinds' => [static fn (): mixed => $plan()['required_receipt_kinds'], ['directory-sync', 'hot-journal-unlink', 'reader-fence', 'savepoint-release', 'wal-sync']],
    'missing kinds empty' => [static fn (): mixed => $plan()['missing_receipt_kinds'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_receipt_names'], []],
    'accepted receipts' => [static fn (): mixed => $plan()['accepted_receipt_names'], ['hot-journal-cleanup', 'wal-sync-cleanup', 'directory-sync-cleanup', 'savepoint-release-cleanup', 'reader-fence-cleanup']],
    'blocked receipts empty' => [static fn (): mixed => $plan()['blocked_receipt_names'], []],
    'covered readers' => [static fn (): mixed => $plan()['covered_reader_names'], ['autoload-reader', 'options-reader', 'schema-reader']],
    'missing readers empty' => [static fn (): mixed => $plan()['missing_reader_names'], []],
    'covered pages' => [static fn (): mixed => $plan()['covered_dirty_pages'], [1, 2, 5, 8]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_dirty_pages'], []],
    'covered frames' => [static fn (): mixed => $plan()['covered_commit_frames'], [22, 23, 24]],
    'missing frames empty' => [static fn (): mixed => $plan()['missing_commit_frames'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next243_reader_baseline_admitted', 'cleanup_receipt_names_unique', 'required_cleanup_receipt_kinds_present', 'all_reader_snapshots_fenced', 'all_dirty_pages_cleaned', 'all_commit_frames_cleaned', 'all_cleanup_receipts_match_current_source']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'cleanup admitted' => [static fn (): mixed => $plan()['cleanup_admitted'], true],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'seal_hot_journal_unlink_after_reader_fence'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'trust_synced_wal_frames_for_current_source'],
    'savepoint action' => [static fn (): mixed => $plan()['savepoint_action'], 'publish_closed_savepoint_scope_for_checkpoint_cleanup'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'serve_readers_from_sealed_checkpoint_current_source'],
    'cleanup digest length' => [static fn (): mixed => strlen($plan()['cleanup_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_reopened_reader_snapshot_baseline_next243', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('seal_post_checkpoint_cleanup_current_source_next247', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next243', $plan()['dependencies'], true), true],
    'dependency next247' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next247', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-hot-journal-cleanup-after-reopened-readers', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat checkpoint publication'), true],
    'row kind' => [static fn (): mixed => $plan()['receipt_rows'][1]['kind'], 'wal-sync'],
    'row reason' => [static fn (): mixed => $plan()['receipt_rows'][1]['cleanup_reason'], 'cleanup_receipt_matches_checkpoint_current_source'],
    'row pages' => [static fn (): mixed => $plan()['receipt_rows'][1]['page_numbers'], [2]],
    'row frames' => [static fn (): mixed => $plan()['receipt_rows'][1]['commit_frames'], [23]],
    'row readers' => [static fn (): mixed => $plan()['receipt_rows'][1]['reader_names'], ['options-reader']],
    'row flags' => [static fn (): mixed => [$plan()['receipt_rows'][1]['hot_journal_unlinked'], $plan()['receipt_rows'][1]['wal_synced'], $plan()['receipt_rows'][1]['directory_synced'], $plan()['receipt_rows'][1]['reader_fenced'], $plan()['receipt_rows'][1]['savepoint_released']], [true, true, true, true, true]],
    'row closed savepoint' => [static fn (): mixed => $plan()['receipt_rows'][1]['savepoint_depth'], 0],
    'stale token blocked' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['blocked_reasons'], ['cleanup_source_token_mismatch']],
    'stale generation blocked' => [static fn (): mixed => $blocked(['commit_generation' => 246])['blocked_reasons'], ['cleanup_commit_generation_mismatch']],
    'stale schema blocked' => [static fn (): mixed => $blocked(['schema_cookie' => 946])['blocked_reasons'], ['cleanup_schema_cookie_mismatch']],
    'stale database blocked' => [static fn (): mixed => $blocked(['database_digest' => $hash('stale database')])['blocked_reasons'], ['cleanup_database_digest_mismatch']],
    'stale page cache blocked' => [static fn (): mixed => $blocked(['page_cache_digest' => $hash('stale cache')])['blocked_reasons'], ['cleanup_page_cache_digest_mismatch']],
    'stale salt blocked' => [static fn (): mixed => $blocked(['wal_index_salt' => ['old-a', 'old-b']])['blocked_reasons'], ['cleanup_wal_index_salt_mismatch']],
    'stale mx frame blocked' => [static fn (): mixed => $blocked(['wal_index_mx_frame' => 23])['blocked_reasons'], ['cleanup_wal_index_mx_frame_mismatch']],
    'stale checkpoint blocked' => [static fn (): mixed => $blocked(['checkpoint_frame' => 20])['blocked_reasons'], ['cleanup_checkpoint_frame_mismatch']],
    'unknown page blocked' => [static fn (): mixed => $blocked(['page_numbers' => [2, 9]])['blocked_reasons'], ['cleanup_page_not_dirty']],
    'unknown frame blocked' => [static fn (): mixed => $blocked(['commit_frames' => [23, 99]])['blocked_reasons'], ['cleanup_frame_not_committed']],
    'unknown reader blocked' => [static fn (): mixed => $blocked(['reader_names' => ['options-reader', 'old-reader']])['blocked_reasons'], ['cleanup_reader_not_admitted']],
    'hot journal missing blocked' => [static fn (): mixed => $blocked(['hot_journal_unlinked' => false])['blocked_reasons'], ['cleanup_hot_journal_unlink_missing']],
    'wal sync missing blocked' => [static fn (): mixed => $blocked(['wal_synced' => false])['blocked_reasons'], ['cleanup_wal_sync_missing']],
    'directory sync missing blocked' => [static fn (): mixed => $blocked(['directory_synced' => false])['blocked_reasons'], ['cleanup_directory_sync_missing']],
    'reader fence missing blocked' => [static fn (): mixed => $blocked(['reader_fenced' => false])['blocked_reasons'], ['cleanup_reader_fence_missing']],
    'savepoint release missing blocked' => [static fn (): mixed => $blocked(['savepoint_released' => false])['blocked_reasons'], ['cleanup_savepoint_release_missing']],
    'savepoint open blocked' => [static fn (): mixed => $blocked(['savepoint_depth' => 1])['blocked_reasons'], ['cleanup_savepoint_scope_open']],
    'dirty page cache blocked' => [static fn (): mixed => $blocked(['page_cache_clean' => false])['blocked_reasons'], ['cleanup_page_cache_dirty']],
    'shared lock missing blocked' => [static fn (): mixed => $blocked(['shared_lock_held' => false])['blocked_reasons'], ['cleanup_shared_lock_missing']],
    'blocked status' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next247'],
    'blocked reason' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['reason'], 'post_checkpoint_hot_journal_cleanup_held_for_current_source_receipts'],
    'blocked journal action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['journal_action'], 'retain_hot_journal_cleanup_fence'],
    'blocked wal action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['wal_action'], 'hold_wal_sync_cleanup_receipts'],
    'blocked savepoint action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['savepoint_action'], 'block_cleanup_until_savepoints_close'],
    'blocked reader action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['reader_action'], 'force_reader_cleanup_receipt_recheck'],
    'blocked guards' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['blocked_guard_names'], ['all_cleanup_receipts_match_current_source']],
    'missing kind list' => [static fn (): mixed => $missingKind()['missing_receipt_kinds'], ['reader-fence']],
    'missing kind guard' => [static fn (): mixed => $missingKind()['blocked_guard_names'], ['required_cleanup_receipt_kinds_present']],
    'missing reader list' => [static fn (): mixed => $missingReader()['missing_reader_names'], ['autoload-reader']],
    'missing reader guard' => [static fn (): mixed => $missingReader()['blocked_guard_names'], ['all_reader_snapshots_fenced']],
    'missing page list' => [static fn (): mixed => $missingPage()['missing_dirty_pages'], [8]],
    'missing page guard' => [static fn (): mixed => $missingPage()['blocked_guard_names'], ['all_dirty_pages_cleaned']],
    'missing frame list' => [static fn (): mixed => $missingFrame()['missing_commit_frames'], [24]],
    'missing frame guard' => [static fn (): mixed => $missingFrame()['blocked_guard_names'], ['all_commit_frames_cleaned']],
    'duplicate receipt names' => [static fn (): mixed => $duplicateReceipt()['duplicate_receipt_names'], ['hot-journal-cleanup']],
    'duplicate receipt guard' => [static fn (): mixed => $duplicateReceipt()['blocked_guard_names'], ['cleanup_receipt_names_unique']],
    'combined blocked reasons' => [static fn (): mixed => $blocked(['source_token' => 'old-source', 'wal_synced' => false, 'savepoint_depth' => 1])['blocked_reasons'], ['cleanup_source_token_mismatch', 'cleanup_wal_sync_missing', 'cleanup_savepoint_scope_open']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next247 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['status' => 'bad']), $receipts),
    'not admitted base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['reader_snapshot_admitted' => false]), $receipts),
    'empty receipts rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup($readerBaseline, []),
    'bad database path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['database_path' => '']), $receipts),
    'bad wal path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['wal_path' => '']), $receipts),
    'bad journal path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['journal_path' => '']), $receipts),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['source_token' => 'bad token']), $receipts),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['commit_generation' => 0]), $receipts),
    'bad schema rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['schema_cookie' => 0]), $receipts),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['database_digest' => 'short']), $receipts),
    'bad page cache digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['page_cache_digest' => 'short']), $receipts),
    'bad wal salt rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['wal_index_salt' => ['one']]), $receipts),
    'bad mx frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['wal_index_mx_frame' => -1]), $receipts),
    'bad checkpoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['checkpoint_frame' => -1]), $receipts),
    'bad dirty pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['dirty_pages' => []]), $receipts),
    'bad commit frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['commit_frames' => [0]]), $receipts),
    'bad reader names rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup(array_replace($readerBaseline, ['accepted_reader_names' => []]), $receipts),
    'bad receipt name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup($readerBaseline, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt kind rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup($readerBaseline, [array_replace($receipts[0], ['kind' => 'bad-kind'])]),
    'bad receipt digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup($readerBaseline, [array_replace($receipts[0], ['database_digest' => 'short'])]),
    'bad receipt pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup($readerBaseline, [array_replace($receipts[0], ['page_numbers' => ['bad']])]),
    'bad receipt frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup($readerBaseline, [array_replace($receipts[0], ['commit_frames' => ['bad']])]),
    'bad receipt readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup($readerBaseline, [array_replace($receipts[0], ['reader_names' => ['bad reader']])]),
    'bad receipt savepoint depth rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup($readerBaseline, [array_replace($receipts[0], ['savepoint_depth' => -1])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next247 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
