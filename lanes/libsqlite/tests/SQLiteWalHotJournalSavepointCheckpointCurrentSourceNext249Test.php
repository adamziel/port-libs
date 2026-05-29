<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next249 reopened checkpoint database image');
$pageCacheDigest = $hash('next249 reopened clean page cache');
$handoffPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next246',
    'durable_handoff_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next249.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next249.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next249.sqlite-wal',
    'source_token' => 'wp-next249-current-source',
    'commit_generation' => 249,
    'schema_cookie' => 949,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'checkpoint_frame' => 34,
    'dirty_pages' => [1, 2, 6, 11],
    'commit_frames' => [31, 32, 34],
    'accepted_reader_names' => ['schema-reader', 'options-reader', 'autoload-reader'],
    'operation_names' => ['admit_durable_current_source_handoff_next246'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next246'],
];

$reader = static fn (string $name, array $override = []) => array_replace([
    'name' => $name,
    'source_token' => $handoffPlan['source_token'],
    'reader_generation' => $handoffPlan['commit_generation'],
    'checkpoint_frame' => $handoffPlan['checkpoint_frame'],
    'snapshot_reopened' => true,
    'readmark_cleared' => true,
], $override);

$reopenedState = [
    'database_path' => $handoffPlan['database_path'],
    'journal_path' => $handoffPlan['journal_path'],
    'wal_path' => $handoffPlan['wal_path'],
    'source_token' => $handoffPlan['source_token'],
    'commit_generation' => $handoffPlan['commit_generation'],
    'schema_cookie' => $handoffPlan['schema_cookie'],
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'wal_commit_frames' => [31, 32, 34],
    'clean_page_numbers' => [1, 2, 6, 11],
    'journal_exists' => false,
    'wal_exists' => true,
    'reader_states' => [
        $reader('schema-reader'),
        $reader('options-reader'),
        $reader('autoload-reader'),
    ],
];

$plan = static fn (array $state = [], array $handoff = []): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next249VerifyReopenedCurrentSource(
    array_replace($handoffPlan, $handoff),
    array_replace($reopenedState, $state)
);

$blocked = static fn (array $state): array => $plan($state);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next249'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reopened_files_confirm_durable_checkpoint_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next246'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next249.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next249.sqlite-wal'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next249.sqlite-journal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next249-current-source'],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 249],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 949],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 34],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'observed database digest' => [static fn (): mixed => $plan()['observed_database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'observed page cache digest' => [static fn (): mixed => $plan()['observed_page_cache_digest'], $pageCacheDigest],
    'dirty pages' => [static fn (): mixed => $plan()['dirty_pages'], [1, 2, 6, 11]],
    'clean page numbers' => [static fn (): mixed => $plan()['clean_page_numbers'], [1, 2, 6, 11]],
    'missing clean pages empty' => [static fn (): mixed => $plan()['missing_clean_pages'], []],
    'expected commit frames' => [static fn (): mixed => $plan()['expected_commit_frames'], [31, 32, 34]],
    'observed commit frames' => [static fn (): mixed => $plan()['observed_commit_frames'], [31, 32, 34]],
    'missing commit frames empty' => [static fn (): mixed => $plan()['missing_commit_frames'], []],
    'unexpected commit frames empty' => [static fn (): mixed => $plan()['unexpected_commit_frames'], []],
    'accepted reader names' => [static fn (): mixed => $plan()['accepted_reader_names'], ['schema-reader', 'options-reader', 'autoload-reader']],
    'blocked reader names empty' => [static fn (): mixed => $plan()['blocked_reader_names'], []],
    'journal gone' => [static fn (): mixed => $plan()['journal_exists_after_reopen'], false],
    'wal retained' => [static fn (): mixed => $plan()['wal_exists_after_reopen'], true],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'admitted' => [static fn (): mixed => $plan()['reopened_current_source_admitted'], true],
    'checkpoint action' => [static fn (): mixed => $plan()['checkpoint_action'], 'serve_checkpoint_database_as_current_source'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'keep_wal_sidecar_until_reader_epoch_advances'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'confirm_hot_journal_retired'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'serve_reopened_readers_from_generation_249'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['database_digest_matches_handoff', 'page_cache_digest_matches_handoff', 'wal_commit_frames_match_handoff', 'dirty_pages_clean_after_reopen', 'hot_journal_retired_after_reopen', 'wal_sidecar_retained_for_reader_epoch', 'schema_cookie_generation_and_source_match', 'all_readers_reopened_on_current_source']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'first reader name' => [static fn (): mixed => $plan()['reader_rows'][0]['name'], 'schema-reader'],
    'first reader reason' => [static fn (): mixed => $plan()['reader_rows'][0]['reader_reason'], 'reader_reopened_on_checkpoint_current_source'],
    'second reader generation' => [static fn (): mixed => $plan()['reader_rows'][1]['reader_generation'], 249],
    'third reader checkpoint frame' => [static fn (): mixed => $plan()['reader_rows'][2]['checkpoint_frame'], 34],
    'missing reader names empty' => [static fn (): mixed => $plan()['missing_reader_names'], []],
    'reopen digest length' => [static fn (): mixed => strlen($plan()['reopen_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_durable_current_source_handoff_next246', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_reopened_current_source_next249', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next246', $plan()['dependencies'], true), true],
    'dependency next249' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next249', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-reopen-after-hot-journal-checkpoint', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat VFS receipt ordering'), true],
    'database mismatch reason' => [static fn (): mixed => $blocked(['database_digest' => $hash('stale database')])['blocked_reasons'], ['reopened_database_digest_mismatch']],
    'database mismatch guard' => [static fn (): mixed => $blocked(['database_digest' => $hash('stale database')])['blocked_guard_names'], ['database_digest_matches_handoff']],
    'page cache mismatch reason' => [static fn (): mixed => $blocked(['page_cache_digest' => $hash('stale cache')])['blocked_reasons'], ['reopened_page_cache_digest_mismatch']],
    'missing frame reason' => [static fn (): mixed => $blocked(['wal_commit_frames' => [31, 34]])['blocked_reasons'], ['reopened_wal_commit_frame_missing']],
    'missing frame list' => [static fn (): mixed => $blocked(['wal_commit_frames' => [31, 34]])['missing_commit_frames'], [32]],
    'unexpected frame reason' => [static fn (): mixed => $blocked(['wal_commit_frames' => [31, 32, 34, 35]])['blocked_reasons'], ['reopened_wal_commit_frame_unexpected']],
    'unexpected frame list' => [static fn (): mixed => $blocked(['wal_commit_frames' => [31, 32, 34, 35]])['unexpected_commit_frames'], [35]],
    'missing clean page reason' => [static fn (): mixed => $blocked(['clean_page_numbers' => [1, 2, 11]])['blocked_reasons'], ['reopened_dirty_page_not_clean']],
    'missing clean page list' => [static fn (): mixed => $blocked(['clean_page_numbers' => [1, 2, 11]])['missing_clean_pages'], [6]],
    'hot journal still exists reason' => [static fn (): mixed => $blocked(['journal_exists' => true])['blocked_reasons'], ['reopened_hot_journal_still_exists']],
    'wal missing reason' => [static fn (): mixed => $blocked(['wal_exists' => false])['blocked_reasons'], ['reopened_wal_sidecar_missing']],
    'schema cookie mismatch reason' => [static fn (): mixed => $blocked(['schema_cookie' => 948])['blocked_reasons'], ['reopened_schema_cookie_mismatch']],
    'generation mismatch reason' => [static fn (): mixed => $blocked(['commit_generation' => 248])['blocked_reasons'], ['reopened_commit_generation_mismatch']],
    'source mismatch reason' => [static fn (): mixed => $blocked(['source_token' => 'wp-next249-old-source'])['blocked_reasons'], ['reopened_source_token_mismatch']],
    'path mismatch reason' => [static fn (): mixed => $blocked(['database_path' => '/tmp/other.sqlite'])['blocked_reasons'], ['reopened_path_mismatch']],
    'reader source mismatch reason' => [static fn (): mixed => $blocked(['reader_states' => [$reader('schema-reader', ['source_token' => 'old-source']), $reader('options-reader'), $reader('autoload-reader')]])['blocked_reasons'], ['reopened_reader_source_token_mismatch']],
    'reader generation mismatch reason' => [static fn (): mixed => $blocked(['reader_states' => [$reader('schema-reader', ['reader_generation' => 248]), $reader('options-reader'), $reader('autoload-reader')]])['blocked_reasons'], ['reopened_reader_generation_mismatch']],
    'reader checkpoint mismatch reason' => [static fn (): mixed => $blocked(['reader_states' => [$reader('schema-reader', ['checkpoint_frame' => 33]), $reader('options-reader'), $reader('autoload-reader')]])['blocked_reasons'], ['reopened_reader_checkpoint_frame_mismatch']],
    'reader not reopened reason' => [static fn (): mixed => $blocked(['reader_states' => [$reader('schema-reader', ['snapshot_reopened' => false]), $reader('options-reader'), $reader('autoload-reader')]])['blocked_reasons'], ['reopened_reader_snapshot_not_reopened']],
    'reader readmark reason' => [static fn (): mixed => $blocked(['reader_states' => [$reader('schema-reader', ['readmark_cleared' => false]), $reader('options-reader'), $reader('autoload-reader')]])['blocked_reasons'], ['reopened_reader_readmark_not_cleared']],
    'missing reader reason' => [static fn (): mixed => $blocked(['reader_states' => [$reader('schema-reader'), $reader('options-reader')]])['blocked_reasons'], ['reopened_reader_missing']],
    'missing reader list' => [static fn (): mixed => $blocked(['reader_states' => [$reader('schema-reader'), $reader('options-reader')]])['missing_reader_names'], ['autoload-reader']],
    'unexpected reader reason' => [static fn (): mixed => $blocked(['reader_states' => [$reader('schema-reader'), $reader('options-reader'), $reader('autoload-reader'), $reader('plugin-reader')]])['blocked_reasons'], ['reopened_reader_unexpected']],
    'blocked status' => [static fn (): mixed => $blocked(['journal_exists' => true])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next249'],
    'blocked checkpoint action' => [static fn (): mixed => $blocked(['journal_exists' => true])['checkpoint_action'], 'retain_prior_current_source_until_reopen_matches'],
    'blocked wal action' => [static fn (): mixed => $blocked(['journal_exists' => true])['wal_action'], 'preserve_wal_for_recovery_replay'],
    'blocked journal action' => [static fn (): mixed => $blocked(['journal_exists' => true])['journal_action'], 'treat_hot_journal_as_recovery_blocker'],
    'blocked reader action' => [static fn (): mixed => $blocked(['journal_exists' => true])['reader_action'], 'hold_readers_on_prior_generation'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next249 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => $plan([], ['status' => 'bad']),
    'not admitted rejected' => static fn () => $plan([], ['durable_handoff_admitted' => false]),
    'bad database path rejected' => static fn () => $plan([], ['database_path' => '']),
    'bad wal path rejected' => static fn () => $plan([], ['wal_path' => '']),
    'bad journal path rejected' => static fn () => $plan([], ['journal_path' => '']),
    'bad source token rejected' => static fn () => $plan([], ['source_token' => 'bad token']),
    'bad generation rejected' => static fn () => $plan([], ['commit_generation' => 0]),
    'bad schema cookie rejected' => static fn () => $plan([], ['schema_cookie' => 0]),
    'bad database digest rejected' => static fn () => $plan([], ['database_digest' => 'short']),
    'bad cache digest rejected' => static fn () => $plan([], ['page_cache_digest' => 'short']),
    'bad checkpoint frame rejected' => static fn () => $plan([], ['checkpoint_frame' => -1]),
    'bad dirty pages rejected' => static fn () => $plan([], ['dirty_pages' => []]),
    'bad commit frames rejected' => static fn () => $plan([], ['commit_frames' => [0]]),
    'bad reader names rejected' => static fn () => $plan([], ['accepted_reader_names' => []]),
    'bad reopened digest rejected' => static fn () => $plan(['database_digest' => 'short']),
    'bad reopened cache digest rejected' => static fn () => $plan(['page_cache_digest' => 'short']),
    'bad reopened frames rejected' => static fn () => $plan(['wal_commit_frames' => []]),
    'bad reopened clean pages rejected' => static fn () => $plan(['clean_page_numbers' => [0]]),
    'bad reader states rejected' => static fn () => $plan(['reader_states' => []]),
    'bad reader row rejected' => static fn () => $plan(['reader_states' => ['not-row']]),
    'bad reader name rejected' => static fn () => $plan(['reader_states' => [$reader('bad reader')]]),
    'bad reader source rejected' => static fn () => $plan(['reader_states' => [$reader('schema-reader', ['source_token' => 'bad token'])]]),
    'bad reader generation rejected' => static fn () => $plan(['reader_states' => [$reader('schema-reader', ['reader_generation' => 0])]]),
    'bad reader checkpoint rejected' => static fn () => $plan(['reader_states' => [$reader('schema-reader', ['checkpoint_frame' => -1])]]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next249 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
