<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next260 checkpointed database');
$pageCacheDigest = $digest('next260 clean page cache');
$sourceToken = 'wp-next260-current-source';
$generation = 260;
$schemaCookie = 1260;
$checkpointFrame = 44;
$commitFrames = [40, 41, 44];
$prefixDigest = hash('sha256', json_encode([$sourceToken, $generation, $commitFrames], JSON_THROW_ON_ERROR));
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next246',
    'durable_handoff_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next260.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next260.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next260.sqlite-wal',
    'source_token' => $sourceToken,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'commit_generation' => $generation,
    'schema_cookie' => $schemaCookie,
    'checkpoint_frame' => $checkpointFrame,
    'dirty_pages' => [1, 2, 5, 8],
    'commit_frames' => $commitFrames,
    'accepted_reader_names' => ['wp-options-reader', 'autoload-index-reader'],
    'operation_names' => ['admit_durable_current_source_handoff_next246'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next246'],
];
$common = [
    'source_token' => $sourceToken,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'commit_generation' => $generation,
    'schema_cookie' => $schemaCookie,
];
$journal = [[
    'name' => 'delete-hot-journal-after-recovery',
] + $common + [
    'recovered_pages' => [1, 2],
    'journal_checksum_valid' => true,
    'hot_journal_deleted' => true,
    'directory_sync_done' => true,
]];
$savepoints = [[
    'name' => 'plugin-import-savepoint-prefix',
    'source_token' => $sourceToken,
    'commit_generation' => $generation,
    'checkpoint_frame' => $checkpointFrame,
    'retained_wal_frames' => $commitFrames,
    'prefix_digest' => $prefixDigest,
    'savepoint_scope_closed' => true,
]];
$checkpoints = [[
    'name' => 'checkpoint-database-image',
] + $common + [
    'checkpoint_frame' => $checkpointFrame,
    'database_pages' => [1, 2, 5, 8],
    'checkpointed_wal_frames' => $commitFrames,
    'database_sync_done' => true,
    'wal_index_sync_done' => true,
    'exclusive_lock_held' => true,
]];
$readers = [
    [
        'name' => 'wp-options-reader',
    ] + $common + [
        'checkpoint_frame' => $checkpointFrame,
        'snapshot_reopened' => true,
        'hot_journal_seen' => false,
    ],
    [
        'name' => 'autoload-index-reader',
    ] + $common + [
        'checkpoint_frame' => $checkpointFrame,
        'snapshot_reopened' => true,
        'hot_journal_seen' => false,
    ],
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, $journal, $savepoints, $checkpoints, $readers);
$blockedJournal = static fn (array $replace): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, [array_replace($journal[0], $replace)], $savepoints, $checkpoints, $readers);
$blockedSavepoint = static fn (array $replace): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, $journal, [array_replace($savepoints[0], $replace)], $checkpoints, $readers);
$blockedCheckpoint = static fn (array $replace): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, $journal, $savepoints, [array_replace($checkpoints[0], $replace)], $readers);
$blockedReader = static fn (array $replace, int $index = 0): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, $journal, $savepoints, $checkpoints, array_replace($readers, [$index => array_replace($readers[$index], $replace)]));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next260'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'hot_journal_savepoint_checkpoint_current_source_admitted'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next246'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next260.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next260.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next260.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], $sourceToken],
    'generation' => [static fn (): mixed => $plan()['commit_generation'], $generation],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], $schemaCookie],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], $checkpointFrame],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'dirty pages' => [static fn (): mixed => $plan()['dirty_pages'], [1, 2, 5, 8]],
    'commit frames' => [static fn (): mixed => $plan()['commit_frames'], $commitFrames],
    'reader set' => [static fn (): mixed => $plan()['accepted_reader_names'], ['wp-options-reader', 'autoload-index-reader']],
    'journal admitted' => [static fn (): mixed => $plan()['admitted_journal_names'], ['delete-hot-journal-after-recovery']],
    'journal blocked empty' => [static fn (): mixed => $plan()['blocked_journal_names'], []],
    'savepoint admitted' => [static fn (): mixed => $plan()['admitted_savepoint_names'], ['plugin-import-savepoint-prefix']],
    'savepoint blocked empty' => [static fn (): mixed => $plan()['blocked_savepoint_names'], []],
    'checkpoint admitted' => [static fn (): mixed => $plan()['admitted_checkpoint_names'], ['checkpoint-database-image']],
    'checkpoint blocked empty' => [static fn (): mixed => $plan()['blocked_checkpoint_names'], []],
    'readers admitted' => [static fn (): mixed => $plan()['admitted_reader_names'], ['wp-options-reader', 'autoload-index-reader']],
    'readers blocked empty' => [static fn (): mixed => $plan()['blocked_reader_names'], []],
    'covered pages' => [static fn (): mixed => $plan()['covered_database_pages'], [1, 2, 5, 8]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_database_pages'], []],
    'retained frames' => [static fn (): mixed => $plan()['retained_wal_frames'], $commitFrames],
    'missing retained frames empty' => [static fn (): mixed => $plan()['missing_retained_wal_frames'], []],
    'checkpointed frames' => [static fn (): mixed => $plan()['checkpointed_wal_frames'], $commitFrames],
    'missing checkpoint frames empty' => [static fn (): mixed => $plan()['missing_checkpointed_wal_frames'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next246_durable_handoff_admitted', 'hot_journal_deleted_from_current_source', 'savepoint_retained_prefix_matches_current_source', 'checkpoint_writes_cover_current_dirty_pages', 'checkpoint_frames_cover_retained_wal_prefix', 'checkpoint_and_wal_index_synced', 'reader_tokens_reopened_on_current_source', 'all_evidence_matches_current_source']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'current source admitted' => [static fn (): mixed => $plan()['current_source_admitted'], true],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'retire_hot_journal_after_delete_receipt'],
    'savepoint action' => [static fn (): mixed => $plan()['savepoint_action'], 'release_savepoint_prefix_for_checkpoint'],
    'checkpoint action' => [static fn (): mixed => $plan()['checkpoint_action'], 'publish_checkpoint_as_current_source'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'advance_reopened_readers_to_generation_260'],
    'digest length' => [static fn (): mixed => strlen($plan()['admission_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_durable_current_source_handoff_next246', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_hot_journal_savepoint_checkpoint_current_source_next260', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next246', $plan()['dependencies'], true), true],
    'dependency next260' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next260', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-hot-journal-savepoint-checkpoint-current-source', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next246 durable VFS handoff'), true],
    'journal checksum block' => [static fn (): mixed => $blockedJournal(['journal_checksum_valid' => false])['blocked_reasons'], ['journal_checksum_invalid']],
    'journal delete block' => [static fn (): mixed => $blockedJournal(['hot_journal_deleted' => false])['blocked_guard_names'], ['hot_journal_deleted_from_current_source', 'all_evidence_matches_current_source']],
    'journal directory block' => [static fn (): mixed => $blockedJournal(['directory_sync_done' => false])['blocked_reasons'], ['journal_directory_sync_missing']],
    'journal token block' => [static fn (): mixed => $blockedJournal(['source_token' => 'old-source'])['blocked_reasons'], ['source_token_mismatch']],
    'journal digest block' => [static fn (): mixed => $blockedJournal(['database_digest' => $digest('old database')])['blocked_reasons'], ['database_digest_mismatch']],
    'journal cache block' => [static fn (): mixed => $blockedJournal(['page_cache_digest' => $digest('old cache')])['blocked_reasons'], ['page_cache_digest_mismatch']],
    'journal generation block' => [static fn (): mixed => $blockedJournal(['commit_generation' => 259])['blocked_reasons'], ['commit_generation_mismatch']],
    'journal schema block' => [static fn (): mixed => $blockedJournal(['schema_cookie' => 1259])['blocked_reasons'], ['schema_cookie_mismatch']],
    'journal unexpected page block' => [static fn (): mixed => $blockedJournal(['recovered_pages' => [7]])['blocked_reasons'], ['journal_unexpected_recovered_page']],
    'savepoint prefix digest block' => [static fn (): mixed => $blockedSavepoint(['prefix_digest' => $digest('stale prefix')])['blocked_reasons'], ['savepoint_prefix_digest_mismatch']],
    'savepoint open block' => [static fn (): mixed => $blockedSavepoint(['savepoint_scope_closed' => false])['blocked_guard_names'], ['savepoint_retained_prefix_matches_current_source', 'all_evidence_matches_current_source']],
    'savepoint token block' => [static fn (): mixed => $blockedSavepoint(['source_token' => 'old-source'])['blocked_reasons'], ['savepoint_source_token_mismatch']],
    'savepoint generation block' => [static fn (): mixed => $blockedSavepoint(['commit_generation' => 259])['blocked_reasons'], ['savepoint_commit_generation_mismatch']],
    'savepoint frame block' => [static fn (): mixed => $blockedSavepoint(['checkpoint_frame' => 43])['blocked_reasons'], ['savepoint_checkpoint_frame_mismatch']],
    'savepoint unexpected frame block' => [static fn (): mixed => $blockedSavepoint(['retained_wal_frames' => [40, 45]])['blocked_reasons'], ['savepoint_unexpected_wal_frame']],
    'savepoint missing retained frame' => [static fn (): mixed => $blockedSavepoint(['retained_wal_frames' => [40, 41], 'prefix_digest' => $prefixDigest])['missing_retained_wal_frames'], [44]],
    'checkpoint page block' => [static fn (): mixed => $blockedCheckpoint(['database_pages' => [1, 2, 5]])['missing_database_pages'], [8]],
    'checkpoint frame block' => [static fn (): mixed => $blockedCheckpoint(['checkpointed_wal_frames' => [40, 41]])['missing_checkpointed_wal_frames'], [44]],
    'checkpoint token block' => [static fn (): mixed => $blockedCheckpoint(['source_token' => 'old-source'])['blocked_reasons'], ['source_token_mismatch']],
    'checkpoint frame mismatch block' => [static fn (): mixed => $blockedCheckpoint(['checkpoint_frame' => 43])['blocked_reasons'], ['checkpoint_frame_mismatch']],
    'checkpoint unexpected page block' => [static fn (): mixed => $blockedCheckpoint(['database_pages' => [1, 9]])['blocked_reasons'], ['checkpoint_unexpected_database_page']],
    'checkpoint unexpected wal frame block' => [static fn (): mixed => $blockedCheckpoint(['checkpointed_wal_frames' => [40, 45]])['blocked_reasons'], ['checkpoint_unexpected_wal_frame']],
    'checkpoint database sync block' => [static fn (): mixed => $blockedCheckpoint(['database_sync_done' => false])['blocked_reasons'], ['checkpoint_database_sync_missing']],
    'checkpoint wal index sync block' => [static fn (): mixed => $blockedCheckpoint(['wal_index_sync_done' => false])['blocked_reasons'], ['checkpoint_wal_index_sync_missing']],
    'checkpoint lock block' => [static fn (): mixed => $blockedCheckpoint(['exclusive_lock_held' => false])['blocked_reasons'], ['checkpoint_exclusive_lock_missing']],
    'reader snapshot block' => [static fn (): mixed => $blockedReader(['snapshot_reopened' => false])['blocked_reasons'], ['reader_snapshot_not_reopened']],
    'reader hot journal block' => [static fn (): mixed => $blockedReader(['hot_journal_seen' => true])['blocked_reasons'], ['reader_still_sees_hot_journal']],
    'reader name block' => [static fn (): mixed => $blockedReader(['name' => 'old-reader'])['blocked_reasons'], ['reader_not_in_next246_admitted_set']],
    'reader checkpoint frame block' => [static fn (): mixed => $blockedReader(['checkpoint_frame' => 43])['blocked_reasons'], ['reader_checkpoint_frame_mismatch']],
    'reader token block' => [static fn (): mixed => $blockedReader(['source_token' => 'old-source'])['blocked_reasons'], ['source_token_mismatch']],
    'reader generation block' => [static fn (): mixed => $blockedReader(['commit_generation' => 259])['blocked_reasons'], ['commit_generation_mismatch']],
    'blocked status' => [static fn (): mixed => $blockedReader(['snapshot_reopened' => false])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next260'],
    'blocked reason' => [static fn (): mixed => $blockedReader(['snapshot_reopened' => false])['reason'], 'hot_journal_savepoint_checkpoint_current_source_held'],
    'blocked current source' => [static fn (): mixed => $blockedReader(['snapshot_reopened' => false])['current_source_admitted'], false],
    'blocked journal action' => [static fn (): mixed => $blockedReader(['snapshot_reopened' => false])['journal_action'], 'retain_hot_journal_replay_source'],
    'blocked savepoint action' => [static fn (): mixed => $blockedReader(['snapshot_reopened' => false])['savepoint_action'], 'keep_savepoint_prefix_replayable'],
    'blocked checkpoint action' => [static fn (): mixed => $blockedReader(['snapshot_reopened' => false])['checkpoint_action'], 'hold_checkpoint_until_source_evidence_matches'],
    'blocked reader action' => [static fn (): mixed => $blockedReader(['snapshot_reopened' => false])['reader_action'], 'pin_readers_to_previous_current_source'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next260 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource(array_replace($base, ['status' => 'bad']), $journal, $savepoints, $checkpoints, $readers),
    'not durable rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource(array_replace($base, ['durable_handoff_admitted' => false]), $journal, $savepoints, $checkpoints, $readers),
    'empty journal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, [], $savepoints, $checkpoints, $readers),
    'empty savepoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, $journal, [], $checkpoints, $readers),
    'empty checkpoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, $journal, $savepoints, [], $readers),
    'empty reader rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, $journal, $savepoints, $checkpoints, []),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource(array_replace($base, ['source_token' => 'bad token']), $journal, $savepoints, $checkpoints, $readers),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource(array_replace($base, ['database_digest' => 'short']), $journal, $savepoints, $checkpoints, $readers),
    'bad page cache digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource(array_replace($base, ['page_cache_digest' => 'short']), $journal, $savepoints, $checkpoints, $readers),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource(array_replace($base, ['commit_generation' => 0]), $journal, $savepoints, $checkpoints, $readers),
    'bad schema rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource(array_replace($base, ['schema_cookie' => 0]), $journal, $savepoints, $checkpoints, $readers),
    'bad checkpoint frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource(array_replace($base, ['checkpoint_frame' => 0]), $journal, $savepoints, $checkpoints, $readers),
    'bad dirty pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource(array_replace($base, ['dirty_pages' => []]), $journal, $savepoints, $checkpoints, $readers),
    'bad commit frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource(array_replace($base, ['commit_frames' => [0]]), $journal, $savepoints, $checkpoints, $readers),
    'bad reader set rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource(array_replace($base, ['accepted_reader_names' => []]), $journal, $savepoints, $checkpoints, $readers),
    'bad journal pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, [array_replace($journal[0], ['recovered_pages' => ['bad']])], $savepoints, $checkpoints, $readers),
    'bad savepoint frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, $journal, [array_replace($savepoints[0], ['retained_wal_frames' => ['bad']])], $checkpoints, $readers),
    'bad checkpoint pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, $journal, $savepoints, [array_replace($checkpoints[0], ['database_pages' => ['bad']])], $readers),
    'bad checkpoint frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, $journal, $savepoints, [array_replace($checkpoints[0], ['checkpointed_wal_frames' => ['bad']])], $readers),
    'bad reader name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan::admitCurrentSource($base, $journal, $savepoints, $checkpoints, [array_replace($readers[0], ['name' => 'bad name'])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next260 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
