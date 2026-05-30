<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next242 checkpoint database after writer commit');
$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next238',
    'writer_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next242.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next242.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next242.sqlite-journal',
    'source_token' => 'wp-next242-current-source',
    'published_writer_generation' => 242,
    'next_writer_generation' => 243,
    'database_digest' => $databaseDigest,
    'expected_schema_cookie' => 24277,
    'expected_wal_salt' => '2420abcd2420dcba',
    'covered_page_numbers' => [1, 2, 3, 4, 5, 6],
    'operation_names' => ['admit_next_writer_after_restart_checkpoint_next238'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next238'],
];

$receipt = static function (string $name, string $kind, array $overrides = []) use ($admission, $databaseDigest): array {
    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'database_path' => $admission['database_path'],
        'wal_path' => $admission['wal_path'],
        'journal_path' => $admission['journal_path'],
        'source_token' => $admission['source_token'],
        'writer_generation' => $admission['next_writer_generation'],
        'published_generation' => $admission['published_writer_generation'],
        'observed_database_digest' => $databaseDigest,
        'schema_cookie' => $admission['expected_schema_cookie'],
        'wal_salt' => $admission['expected_wal_salt'],
        'first_wal_frame' => 1,
        'last_wal_frame' => 3,
        'page_numbers' => [1, 2, 3],
        'database_backfilled' => true,
        'wal_synced' => true,
        'directory_synced' => true,
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
        'reader_cache_dirty' => false,
        'reader_generation' => $admission['next_writer_generation'],
    ], $overrides);
};

$receipts = [
    $receipt('wp-next242-wal-commit', 'wal-commit', ['page_numbers' => [2, 3, 4], 'last_wal_frame' => 4]),
    $receipt('wp-next242-database-backfill', 'database-backfill', ['page_numbers' => [1, 2, 3, 4]]),
    $receipt('wp-next242-directory-sync', 'directory-sync', ['page_numbers' => [1], 'first_wal_frame' => 1, 'last_wal_frame' => 1]),
    $receipt('wp-next242-reader-generation', 'reader-generation', ['page_numbers' => [5, 6], 'reader_generation' => 244]),
];

$plan = static fn (?array $inputPlan = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next242AdmitCommittedWriter($inputPlan ?? $admission, $inputReceipts ?? $receipts);
$blocked = static fn (int $index, array $overrides): array => $plan(null, array_map(
    static fn (array $row, int $rowIndex): array => $rowIndex === $index ? array_replace($row, $overrides) : $row,
    $receipts,
    array_keys($receipts)
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next242'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'post_checkpoint_writer_commit_receipts_publish_next_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next238'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $admission['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $admission['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $admission['journal_path']],
    'source token' => [static fn (): mixed => $plan()['source_token'], $admission['source_token']],
    'published generation' => [static fn (): mixed => $plan()['published_writer_generation'], 242],
    'writer generation' => [static fn (): mixed => $plan()['writer_generation'], 243],
    'next source generation' => [static fn (): mixed => $plan()['next_source_generation'], 244],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'schema cookie' => [static fn (): mixed => $plan()['expected_schema_cookie'], 24277],
    'wal salt' => [static fn (): mixed => $plan()['expected_wal_salt'], '2420abcd2420dcba'],
    'covered pages' => [static fn (): mixed => $plan()['covered_page_numbers'], [1, 2, 3, 4, 5, 6]],
    'receipt kinds' => [static fn (): mixed => $plan()['receipt_kinds'], ['database-backfill', 'directory-sync', 'reader-generation', 'wal-commit']],
    'required kinds' => [static fn (): mixed => $plan()['required_receipt_kinds'], ['database-backfill', 'directory-sync', 'reader-generation', 'wal-commit']],
    'missing kinds empty' => [static fn (): mixed => $plan()['missing_receipt_kinds'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_receipt_names'], []],
    'accepted names' => [static fn (): mixed => $plan()['accepted_receipt_names'], ['wp-next242-wal-commit', 'wp-next242-database-backfill', 'wp-next242-directory-sync', 'wp-next242-reader-generation']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_receipt_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next238_writer_admitted', 'commit_receipt_names_unique', 'required_commit_receipt_kinds_present', 'commit_receipts_use_next_writer_generation', 'commit_receipts_preserve_current_source_token', 'wal_commit_frames_follow_restart', 'database_backfill_matches_checkpoint_digest', 'hot_journal_and_savepoint_fences_clear', 'reader_generations_advanced_past_publication', 'all_commit_receipts_current']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'commit admitted' => [static fn (): mixed => $plan()['commit_admitted'], true],
    'current action' => [static fn (): mixed => $plan()['current_source_action'], 'publish_writer_generation_244'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'retain_committed_wal_frames_after_restart'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'allow_reopened_readers_to_advance_generation'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'keep_hot_journal_deleted_after_commit'],
    'digest length' => [static fn (): mixed => strlen($plan()['commit_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_next_writer_after_restart_checkpoint_next238', $plan()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_post_checkpoint_writer_commit_receipts_next242', $plan()['operation_names'], true), true],
    'operation publish' => [static fn (): mixed => in_array('publish_post_checkpoint_writer_current_source_next242', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next238', $plan()['dependencies'], true), true],
    'dependency next242' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next242', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-post-checkpoint-writer-current-source', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat restart/truncate reset admission'), true],
    'first row accepted' => [static fn (): mixed => $plan()['receipt_rows'][0]['accepted'], true],
    'first row reason' => [static fn (): mixed => $plan()['receipt_rows'][0]['receipt_reason'], 'writer_commit_receipt_current'],
    'first row kind' => [static fn (): mixed => $plan()['receipt_rows'][0]['kind'], 'wal-commit'],
    'first row frame sequence' => [static fn (): mixed => [$plan()['receipt_rows'][0]['first_wal_frame'], $plan()['receipt_rows'][0]['last_wal_frame']], [1, 4]],
    'first row generation match' => [static fn (): mixed => $plan()['receipt_rows'][0]['generation_match'], true],
    'first row source match' => [static fn (): mixed => $plan()['receipt_rows'][0]['source_token_match'], true],
    'first row wal sequence valid' => [static fn (): mixed => $plan()['receipt_rows'][0]['wal_frame_sequence_valid'], true],
    'first row fences clear' => [static fn (): mixed => $plan()['receipt_rows'][0]['fences_clear'], true],
    'fourth row reader generation' => [static fn (): mixed => $plan()['receipt_rows'][3]['reader_generation'], 244],
    'blocked status' => [static fn (): mixed => $blocked(0, ['last_wal_frame' => 0])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next242'],
    'blocked current action' => [static fn (): mixed => $blocked(0, ['last_wal_frame' => 0])['current_source_action'], 'hold_current_source_at_generation_243'],
    'blocked wal action' => [static fn (): mixed => $blocked(0, ['last_wal_frame' => 0])['wal_action'], 'block_committed_wal_publication'],
    'blocked reader action' => [static fn (): mixed => $blocked(0, ['last_wal_frame' => 0])['reader_action'], 'force_reader_generation_recheck'],
    'blocked journal action' => [static fn (): mixed => $blocked(0, ['last_wal_frame' => 0])['journal_action'], 'retain_hot_journal_delete_fence'],
    'database path block' => [static fn (): mixed => $blocked(0, ['database_path' => '/tmp/other.sqlite'])['receipt_rows'][0]['blocked_reasons'], ['commit_database_path_mismatch']],
    'wal path block' => [static fn (): mixed => $blocked(0, ['wal_path' => '/tmp/other.sqlite-wal'])['receipt_rows'][0]['blocked_reasons'], ['commit_wal_path_mismatch']],
    'journal path block' => [static fn (): mixed => $blocked(0, ['journal_path' => '/tmp/other.sqlite-journal'])['receipt_rows'][0]['blocked_reasons'], ['commit_journal_path_mismatch']],
    'source token block' => [static fn (): mixed => $blocked(0, ['source_token' => 'old-source'])['receipt_rows'][0]['blocked_reasons'], ['commit_source_token_mismatch']],
    'writer generation block' => [static fn (): mixed => $blocked(0, ['writer_generation' => 242])['receipt_rows'][0]['blocked_reasons'], ['commit_writer_generation_mismatch']],
    'published generation block' => [static fn (): mixed => $blocked(0, ['published_generation' => 241])['receipt_rows'][0]['blocked_reasons'], ['commit_published_generation_mismatch']],
    'database digest block' => [static fn (): mixed => $blocked(0, ['observed_database_digest' => $hash('stale database')])['receipt_rows'][0]['blocked_reasons'], ['commit_database_digest_mismatch']],
    'schema cookie block' => [static fn (): mixed => $blocked(0, ['schema_cookie' => 1])['receipt_rows'][0]['blocked_reasons'], ['commit_schema_cookie_mismatch']],
    'wal salt block' => [static fn (): mixed => $blocked(0, ['wal_salt' => '0000abcd2420dcba'])['receipt_rows'][0]['blocked_reasons'], ['commit_wal_salt_mismatch']],
    'wal frame block' => [static fn (): mixed => $blocked(0, ['first_wal_frame' => 3, 'last_wal_frame' => 1])['receipt_rows'][0]['blocked_reasons'], ['commit_wal_frame_sequence_invalid']],
    'page block' => [static fn (): mixed => $blocked(0, ['page_numbers' => [1, 7]])['receipt_rows'][0]['blocked_reasons'], ['commit_page_not_checkpoint_covered']],
    'backfill block' => [static fn (): mixed => $blocked(0, ['database_backfilled' => false])['receipt_rows'][0]['blocked_reasons'], ['commit_database_backfill_missing']],
    'wal sync block' => [static fn (): mixed => $blocked(0, ['wal_synced' => false])['receipt_rows'][0]['blocked_reasons'], ['commit_wal_sync_missing']],
    'directory sync block' => [static fn (): mixed => $blocked(0, ['directory_synced' => false])['receipt_rows'][0]['blocked_reasons'], ['commit_directory_sync_missing']],
    'hot journal block' => [static fn (): mixed => $blocked(0, ['hot_journal_visible' => true])['receipt_rows'][0]['blocked_reasons'], ['commit_hot_journal_visible']],
    'savepoint block' => [static fn (): mixed => $blocked(0, ['savepoint_depth' => 1])['receipt_rows'][0]['blocked_reasons'], ['commit_savepoint_scope_open']],
    'dirty cache block' => [static fn (): mixed => $blocked(0, ['reader_cache_dirty' => true])['receipt_rows'][0]['blocked_reasons'], ['commit_reader_cache_dirty']],
    'reader generation block' => [static fn (): mixed => $blocked(3, ['reader_generation' => 242])['receipt_rows'][3]['blocked_reasons'], ['commit_reader_generation_stale']],
    'missing kind block' => [static fn (): mixed => $plan(null, array_slice($receipts, 0, 3))['missing_receipt_kinds'], ['reader-generation']],
    'missing kind reason' => [static fn (): mixed => in_array('writer_commit_receipt_kind_missing', $plan(null, array_slice($receipts, 0, 3))['blocked_reasons'], true), true],
    'duplicate name block' => [static fn (): mixed => in_array('writer_commit_receipt_name_duplicate', $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2], $receipts[3]])['blocked_reasons'], true), true],
    'duplicate guard block' => [static fn (): mixed => $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2], $receipts[3]])['blocked_guard_names'], ['commit_receipt_names_unique']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next242 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => $plan(array_replace($admission, ['status' => 'bad'])),
    'not admitted rejected' => static fn () => $plan(array_replace($admission, ['writer_admitted' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($admission, ['database_path' => ''])),
    'bad source token rejected' => static fn () => $plan(array_replace($admission, ['source_token' => 'bad token'])),
    'bad writer generation rejected' => static fn () => $plan(array_replace($admission, ['next_writer_generation' => 0])),
    'bad published generation rejected' => static fn () => $plan(array_replace($admission, ['published_writer_generation' => 0])),
    'bad digest rejected' => static fn () => $plan(array_replace($admission, ['database_digest' => 'short'])),
    'bad schema cookie rejected' => static fn () => $plan(array_replace($admission, ['expected_schema_cookie' => 0])),
    'bad salt rejected' => static fn () => $plan(array_replace($admission, ['expected_wal_salt' => 'short'])),
    'bad covered pages rejected' => static fn () => $plan(array_replace($admission, ['covered_page_numbers' => [0]])),
    'bad receipt name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt kind rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['kind' => 'bad'])]),
    'bad receipt digest rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['observed_database_digest' => 'short'])]),
    'bad receipt page rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['page_numbers' => [0]])]),
    'bad receipt reader generation rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['reader_generation' => 0])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next242 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
