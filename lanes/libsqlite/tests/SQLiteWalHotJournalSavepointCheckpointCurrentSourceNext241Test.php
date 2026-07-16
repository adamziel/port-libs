<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next241 checkpoint database image');
$writerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next238',
    'writer_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next241.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next241.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next241.sqlite-journal',
    'source_token' => 'wp-next241-current-source',
    'published_writer_generation' => 240,
    'next_writer_generation' => 241,
    'database_digest' => $databaseDigest,
    'expected_schema_cookie' => 24177,
    'expected_wal_salt' => '2410abcd2410dcba',
    'covered_page_numbers' => [1, 2, 3, 4, 5, 8],
    'operation_names' => ['admit_next_writer_after_restart_checkpoint_next238'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next238'],
];

$receipt = static function (string $name, string $kind, string $path, array $overrides = []) use ($writerPlan, $databaseDigest, $hash): array {
    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'path' => $path,
        'source_token' => $writerPlan['source_token'],
        'generation' => $writerPlan['next_writer_generation'],
        'schema_cookie' => $writerPlan['expected_schema_cookie'],
        'wal_salt' => $writerPlan['expected_wal_salt'],
        'database_digest' => $databaseDigest,
        'page_numbers' => [2, 5],
        'frame_numbers' => [1, 2],
        'commit_marker_present' => true,
        'transaction_complete' => true,
        'commit_digest' => $hash('next241 committed writer frame set'),
        'synced' => true,
        'frames_synced' => true,
        'hot_journal_visible' => false,
        'reserved_lock_released' => true,
        'shared_lock_preserved' => true,
        'directory_synced' => true,
        'persisted_paths' => [
            $writerPlan['database_path'],
            $writerPlan['wal_path'],
            $writerPlan['journal_path'],
        ],
    ], $overrides);
};

$receipts = [
    $receipt('wp-next241-commit', 'commit', $writerPlan['wal_path'], ['frame_numbers' => [1, 2, 3]]),
    $receipt('wp-next241-wal', 'wal', $writerPlan['wal_path'], ['frame_numbers' => [1, 2, 3], 'page_numbers' => [2, 5, 8]]),
    $receipt('wp-next241-lock', 'lock', $writerPlan['database_path'], ['frame_numbers' => [3], 'page_numbers' => [1]]),
    $receipt('wp-next241-directory', 'directory', dirname($writerPlan['database_path']), ['frame_numbers' => [1, 3], 'page_numbers' => [1, 2, 5]]),
];

$plan = static fn (?array $inputWriter = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next241AdmitCommittedWriter($inputWriter ?? $writerPlan, $inputReceipts ?? $receipts);
$blocked = static fn (int $index, array $overrides): array => $plan(null, array_map(
    static fn (array $row, int $rowIndex): array => $rowIndex === $index ? array_replace($row, $overrides) : $row,
    $receipts,
    array_keys($receipts)
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next241'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'post_publication_writer_commit_receipts_advance_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next238'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $writerPlan['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $writerPlan['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $writerPlan['journal_path']],
    'source token' => [static fn (): mixed => $plan()['source_token'], $writerPlan['source_token']],
    'published generation' => [static fn (): mixed => $plan()['published_writer_generation'], 240],
    'committed generation' => [static fn (): mixed => $plan()['committed_writer_generation'], 241],
    'next reader generation' => [static fn (): mixed => $plan()['next_reader_generation'], 241],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'schema cookie' => [static fn (): mixed => $plan()['expected_schema_cookie'], 24177],
    'wal salt' => [static fn (): mixed => $plan()['expected_wal_salt'], '2410abcd2410dcba'],
    'covered pages' => [static fn (): mixed => $plan()['covered_page_numbers'], [1, 2, 3, 4, 5, 8]],
    'receipt names' => [static fn (): mixed => $plan()['receipt_names'], ['wp-next241-commit', 'wp-next241-wal', 'wp-next241-lock', 'wp-next241-directory']],
    'receipt kinds' => [static fn (): mixed => $plan()['receipt_kinds'], ['commit', 'directory', 'lock', 'wal']],
    'missing kinds empty' => [static fn (): mixed => $plan()['missing_receipt_kinds'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_receipt_names'], []],
    'accepted names' => [static fn (): mixed => $plan()['accepted_receipt_names'], ['wp-next241-commit', 'wp-next241-wal', 'wp-next241-lock', 'wp-next241-directory']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_receipt_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'committed frames' => [static fn (): mixed => $plan()['committed_frame_numbers'], [1, 2, 3]],
    'committed frame count' => [static fn (): mixed => $plan()['committed_frame_count'], 3],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next238_writer_admitted', 'writer_commit_receipt_kinds_present', 'writer_commit_receipt_names_unique', 'all_commit_receipts_match_writer_source', 'commit_receipt_marks_transaction_complete', 'wal_receipt_flushes_appended_frames', 'lock_receipt_releases_reserved_lock', 'directory_receipt_persists_wal_sidecar']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'current source advanced' => [static fn (): mixed => $plan()['current_source_advanced'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'advance_readers_to_committed_writer_generation_241'],
    'writer action' => [static fn (): mixed => $plan()['writer_action'], 'publish_committed_wal_frames_next241'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'retain_committed_wal_frames_for_next_reader'],
    'commit digest length' => [static fn (): mixed => strlen($plan()['commit_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_next_writer_after_restart_checkpoint_next238', $plan()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_post_publication_writer_commit_receipts_next241', $plan()['operation_names'], true), true],
    'operation advance' => [static fn (): mixed => in_array('advance_current_source_after_writer_commit_next241', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next238', $plan()['dependencies'], true), true],
    'dependency next241' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next241', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-committed-wal-writer-before-next-reader', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next238 reader reopen admission'), true],
    'first row accepted' => [static fn (): mixed => $plan()['commit_receipt_rows'][0]['accepted'], true],
    'first row reason' => [static fn (): mixed => $plan()['commit_receipt_rows'][0]['receipt_reason'], 'writer_commit_receipt_matches_current_source'],
    'first row frames' => [static fn (): mixed => $plan()['commit_receipt_rows'][0]['frame_numbers'], [1, 2, 3]],
    'wal row synced' => [static fn (): mixed => [$plan()['commit_receipt_rows'][1]['synced'], $plan()['commit_receipt_rows'][1]['frames_synced']], [true, true]],
    'lock row release' => [static fn (): mixed => [$plan()['commit_receipt_rows'][2]['reserved_lock_released'], $plan()['commit_receipt_rows'][2]['shared_lock_preserved']], [true, true]],
    'directory row persisted wal' => [static fn (): mixed => $plan()['commit_receipt_rows'][3]['persisted_wal_path'], true],
    'blocked status' => [static fn (): mixed => $blocked(1, ['frames_synced' => false])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next241'],
    'blocked reader action' => [static fn (): mixed => $blocked(1, ['frames_synced' => false])['reader_action'], 'keep_readers_on_restart_checkpoint_source'],
    'blocked writer action' => [static fn (): mixed => $blocked(1, ['frames_synced' => false])['writer_action'], 'hold_writer_commit_until_receipts_match'],
    'blocked wal action' => [static fn (): mixed => $blocked(1, ['frames_synced' => false])['wal_action'], 'preserve_unpublished_writer_frames'],
    'source token block' => [static fn (): mixed => $blocked(0, ['source_token' => 'old-source'])['commit_receipt_rows'][0]['blocked_reasons'], ['writer_commit_source_token_mismatch']],
    'generation block' => [static fn (): mixed => $blocked(0, ['generation' => 240])['commit_receipt_rows'][0]['blocked_reasons'], ['writer_commit_generation_mismatch']],
    'schema cookie block' => [static fn (): mixed => $blocked(0, ['schema_cookie' => 1])['commit_receipt_rows'][0]['blocked_reasons'], ['writer_commit_schema_cookie_mismatch']],
    'wal salt block' => [static fn (): mixed => $blocked(0, ['wal_salt' => '0000abcd2410dcba'])['commit_receipt_rows'][0]['blocked_reasons'], ['writer_commit_wal_salt_mismatch']],
    'database digest block' => [static fn (): mixed => $blocked(0, ['database_digest' => $hash('old database')])['commit_receipt_rows'][0]['blocked_reasons'], ['writer_commit_database_digest_mismatch']],
    'page block' => [static fn (): mixed => $blocked(0, ['page_numbers' => [2, 9]])['commit_receipt_rows'][0]['blocked_reasons'], ['writer_commit_page_not_checkpoint_covered']],
    'commit path block' => [static fn (): mixed => $blocked(0, ['path' => '/tmp/other.sqlite-wal'])['commit_receipt_rows'][0]['blocked_reasons'], ['commit_receipt_path_mismatch']],
    'commit marker block' => [static fn (): mixed => $blocked(0, ['commit_marker_present' => false])['commit_receipt_rows'][0]['blocked_reasons'], ['writer_commit_marker_missing']],
    'transaction incomplete block' => [static fn (): mixed => $blocked(0, ['transaction_complete' => false])['commit_receipt_rows'][0]['blocked_reasons'], ['writer_transaction_incomplete']],
    'commit digest block' => [static fn (): mixed => $blocked(0, ['commit_digest' => 'short'])['commit_receipt_rows'][0]['blocked_reasons'], ['writer_commit_digest_missing']],
    'wal path block' => [static fn (): mixed => $blocked(1, ['path' => '/tmp/other.sqlite-wal'])['commit_receipt_rows'][1]['blocked_reasons'], ['wal_commit_path_mismatch']],
    'wal sync block' => [static fn (): mixed => $blocked(1, ['synced' => false])['commit_receipt_rows'][1]['blocked_reasons'], ['wal_commit_not_synced']],
    'wal frames sync block' => [static fn (): mixed => $blocked(1, ['frames_synced' => false])['commit_receipt_rows'][1]['blocked_reasons'], ['wal_commit_frames_not_synced']],
    'hot journal block' => [static fn (): mixed => $blocked(1, ['hot_journal_visible' => true])['commit_receipt_rows'][1]['blocked_reasons'], ['wal_commit_hot_journal_visible']],
    'lock path block' => [static fn (): mixed => $blocked(2, ['path' => '/tmp/other.sqlite'])['commit_receipt_rows'][2]['blocked_reasons'], ['lock_receipt_database_path_mismatch']],
    'reserved lock block' => [static fn (): mixed => $blocked(2, ['reserved_lock_released' => false])['commit_receipt_rows'][2]['blocked_reasons'], ['writer_reserved_lock_not_released']],
    'shared lock block' => [static fn (): mixed => $blocked(2, ['shared_lock_preserved' => false])['commit_receipt_rows'][2]['blocked_reasons'], ['reader_shared_lock_not_preserved']],
    'directory path block' => [static fn (): mixed => $blocked(3, ['path' => '/tmp'])['commit_receipt_rows'][3]['blocked_reasons'], ['directory_commit_path_mismatch']],
    'directory sync block' => [static fn (): mixed => $blocked(3, ['directory_synced' => false])['commit_receipt_rows'][3]['blocked_reasons'], ['directory_commit_not_synced']],
    'directory wal block' => [static fn (): mixed => $blocked(3, ['persisted_paths' => [$writerPlan['database_path'], $writerPlan['journal_path']]])['commit_receipt_rows'][3]['blocked_reasons'], ['directory_commit_missing_wal_sidecar']],
    'directory database block' => [static fn (): mixed => $blocked(3, ['persisted_paths' => [$writerPlan['wal_path'], $writerPlan['journal_path']]])['commit_receipt_rows'][3]['blocked_reasons'], ['directory_commit_missing_database_path']],
    'directory journal block' => [static fn (): mixed => $blocked(3, ['persisted_paths' => [$writerPlan['database_path'], $writerPlan['wal_path']]])['commit_receipt_rows'][3]['blocked_reasons'], ['directory_commit_missing_journal_path']],
    'missing kind blocked' => [static fn (): mixed => $plan(null, [$receipts[0], $receipts[1], $receipts[2]])['blocked_reasons'], ['writer_commit_receipt_kind_missing']],
    'duplicate name blocked' => [static fn (): mixed => in_array('writer_commit_receipt_name_duplicate', $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2], $receipts[3]])['blocked_reasons'], true), true],
    'duplicate guard block' => [static fn (): mixed => $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2], $receipts[3]])['blocked_guard_names'], ['writer_commit_receipt_names_unique']],
    'combined block reasons' => [static fn (): mixed => $blocked(1, ['source_token' => 'old-source', 'synced' => false, 'hot_journal_visible' => true])['commit_receipt_rows'][1]['blocked_reasons'], ['writer_commit_source_token_mismatch', 'wal_commit_not_synced', 'wal_commit_hot_journal_visible']],
    'combined guard block' => [static fn (): mixed => $blocked(1, ['frames_synced' => false])['blocked_guard_names'], ['all_commit_receipts_match_writer_source', 'wal_receipt_flushes_appended_frames']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next241 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => $plan(array_replace($writerPlan, ['status' => 'bad'])),
    'not admitted rejected' => static fn () => $plan(array_replace($writerPlan, ['writer_admitted' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($writerPlan, ['database_path' => ''])),
    'bad source token rejected' => static fn () => $plan(array_replace($writerPlan, ['source_token' => 'bad token'])),
    'bad published generation rejected' => static fn () => $plan(array_replace($writerPlan, ['published_writer_generation' => 0])),
    'bad writer generation rejected' => static fn () => $plan(array_replace($writerPlan, ['next_writer_generation' => 240])),
    'bad digest rejected' => static fn () => $plan(array_replace($writerPlan, ['database_digest' => 'short'])),
    'bad salt rejected' => static fn () => $plan(array_replace($writerPlan, ['expected_wal_salt' => 'short'])),
    'bad covered pages rejected' => static fn () => $plan(array_replace($writerPlan, ['covered_page_numbers' => [0]])),
    'bad receipt name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt kind rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['kind' => 'bogus'])]),
    'bad receipt pages rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['page_numbers' => [0]])]),
    'bad receipt frames rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['frame_numbers' => [0]])]),
    'bad receipt digest rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['database_digest' => 'short'])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next241 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
