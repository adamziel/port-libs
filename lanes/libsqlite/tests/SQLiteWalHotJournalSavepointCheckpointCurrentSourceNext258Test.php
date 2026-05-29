<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$databaseDigest = hash('sha256', 'next258 database checkpoint image');
$pageCacheDigest = hash('sha256', 'next258 clean page cache');
$readerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next255',
    'restarted_reader_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next258.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next258.sqlite-wal',
    'source_token' => 'wp-next258-source',
    'commit_generation' => 258,
    'checkpoint_frame' => 0,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'next_wal_salt' => ['00000102', '00000304'],
    'reopened_reader_names' => ['wp-next258-front', 'wp-next258-import', 'wp-next258-plugin'],
    'readmark_slots' => [1, 2, 3],
    'operation_names' => ['admit_restarted_wal_readers_next255'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next255'],
];

$receipt = static function (string $name, string $kind, array $overrides = []) use ($readerPlan, $databaseDigest, $pageCacheDigest): array {
    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'database_path' => $readerPlan['database_path'],
        'wal_path' => $readerPlan['wal_path'],
        'source_token' => $readerPlan['source_token'],
        'commit_generation' => 259,
        'checkpoint_frame' => 0,
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'old_wal_salt' => ['00000102', '00000304'],
        'new_wal_salt' => ['00000506', '00000708'],
        'reader_names' => $readerPlan['reopened_reader_names'],
        'readmark_slots' => $readerPlan['readmark_slots'],
        'wal_size_before' => 32,
        'first_frame_index' => 1,
        'first_frame_page' => 2,
        'mx_frame_before' => 0,
        'mx_frame_after' => 1,
        'exclusive_lock_held' => true,
        'hot_journal_visible' => false,
        'pending_savepoint_depth' => 0,
        'durably_synced' => true,
        'io_error' => null,
    ], $overrides);
};

$receipts = [
    $receipt('wp-next258-header-salt', 'header-salt'),
    $receipt('wp-next258-first-frame', 'first-frame'),
    $receipt('wp-next258-reader-fence', 'reader-fence'),
    $receipt('wp-next258-sync', 'sync'),
];

$plan = static fn (?array $inputPlan = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next258AdmitWriterAfterRestartedReaders($inputPlan ?? $readerPlan, $inputReceipts ?? $receipts);
$blocked = static fn (int $index, array $overrides): array => $plan(null, array_map(
    static fn (array $row, int $rowIndex): array => $rowIndex === $index ? array_replace($row, $overrides) : $row,
    $receipts,
    array_keys($receipts)
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next258'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'post_restart_writer_admitted_after_reader_fences'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next255'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $readerPlan['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $readerPlan['wal_path']],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next258-source'],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 258],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 0],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'restart salt' => [static fn (): mixed => $plan()['restart_wal_salt'], ['00000102', '00000304']],
    'reopened readers' => [static fn (): mixed => $plan()['reopened_reader_names'], ['wp-next258-front', 'wp-next258-import', 'wp-next258-plugin']],
    'readmark slots' => [static fn (): mixed => $plan()['readmark_slots'], [1, 2, 3]],
    'receipt names' => [static fn (): mixed => $plan()['writer_receipt_names'], ['wp-next258-header-salt', 'wp-next258-first-frame', 'wp-next258-reader-fence', 'wp-next258-sync']],
    'accepted receipt names' => [static fn (): mixed => $plan()['accepted_writer_receipt_names'], ['wp-next258-header-salt', 'wp-next258-first-frame', 'wp-next258-reader-fence', 'wp-next258-sync']],
    'blocked receipt names empty' => [static fn (): mixed => $plan()['blocked_writer_receipt_names'], []],
    'duplicate receipt names empty' => [static fn (): mixed => $plan()['duplicate_writer_receipt_names'], []],
    'accepted kinds' => [static fn (): mixed => $plan()['accepted_writer_kinds'], ['first-frame', 'header-salt', 'reader-fence', 'sync']],
    'missing kinds empty' => [static fn (): mixed => $plan()['missing_writer_kinds'], []],
    'covered readers' => [static fn (): mixed => $plan()['covered_reader_names'], ['wp-next258-front', 'wp-next258-import', 'wp-next258-plugin']],
    'missing readers empty' => [static fn (): mixed => $plan()['missing_reader_names'], []],
    'covered readmarks' => [static fn (): mixed => $plan()['covered_readmark_slots'], [1, 2, 3]],
    'missing readmarks empty' => [static fn (): mixed => $plan()['missing_readmark_slots'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_writer_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next255_restarted_readers_admitted', 'writer_receipt_names_unique', 'required_writer_kinds_present', 'all_reopened_readers_fenced', 'all_reopened_readmarks_fenced', 'all_writer_receipts_current']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'writer admitted' => [static fn (): mixed => $plan()['post_restart_writer_admitted'], true],
    'writer action' => [static fn (): mixed => $plan()['writer_action'], 'start_new_wal_transaction_after_restarted_reader_fence'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'append_first_frame_with_new_salt_after_restart'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'keep_restarted_readers_on_checkpoint_snapshot'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'keep_hot_journal_unlinked_for_post_restart_writer'],
    'digest length' => [static fn (): mixed => strlen($plan()['admission_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_restarted_wal_readers_next255', $plan()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_post_restart_writer_reader_fence_current_source_next258', $plan()['operation_names'], true), true],
    'operation admit' => [static fn (): mixed => in_array('admit_post_restart_writer_current_source_next258', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next255', $plan()['dependencies'], true), true],
    'dependency next258' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next258', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-wal-post-restart-writer-admission', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next255 reader reopen admission'), true],
    'first row accepted' => [static fn (): mixed => $plan()['writer_rows'][0]['accepted'], true],
    'first row reason' => [static fn (): mixed => $plan()['writer_rows'][0]['receipt_reason'], 'post_restart_writer_receipt_matches_current_source'],
    'first row old salt' => [static fn (): mixed => $plan()['writer_rows'][0]['old_wal_salt'], ['00000102', '00000304']],
    'first row new salt' => [static fn (): mixed => $plan()['writer_rows'][0]['new_wal_salt'], ['00000506', '00000708']],
    'first frame row index' => [static fn (): mixed => $plan()['writer_rows'][1]['first_frame_index'], 1],
    'blocked status' => [static fn (): mixed => $blocked(0, ['new_wal_salt' => ['00000102', '00000304']])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next258'],
    'blocked writer action' => [static fn (): mixed => $blocked(0, ['new_wal_salt' => ['00000102', '00000304']])['writer_action'], 'hold_writer_until_restarted_reader_fence'],
    'blocked wal action' => [static fn (): mixed => $blocked(0, ['wal_size_before' => 96])['wal_action'], 'preserve_empty_restarted_wal_generation'],
    'blocked reader action' => [static fn (): mixed => $blocked(0, ['reader_names' => ['wp-next258-unknown']])['reader_action'], 'block_writer_visibility_to_restarted_readers'],
    'blocked journal action' => [static fn (): mixed => $blocked(0, ['hot_journal_visible' => true])['journal_action'], 'reject_writer_with_visible_hot_journal'],
    'database path block' => [static fn (): mixed => $blocked(0, ['database_path' => '/tmp/other.sqlite'])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_database_path_mismatch']],
    'wal path block' => [static fn (): mixed => $blocked(0, ['wal_path' => '/tmp/other.sqlite-wal'])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_wal_path_mismatch']],
    'source token block' => [static fn (): mixed => $blocked(0, ['source_token' => 'old-source'])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_source_token_mismatch']],
    'generation block' => [static fn (): mixed => $blocked(0, ['commit_generation' => 258])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_generation_mismatch']],
    'checkpoint frame block' => [static fn (): mixed => $blocked(0, ['checkpoint_frame' => 1])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_checkpoint_frame_mismatch']],
    'database digest block' => [static fn (): mixed => $blocked(0, ['database_digest' => hash('sha256', 'stale')])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_database_digest_mismatch']],
    'cache digest block' => [static fn (): mixed => $blocked(0, ['page_cache_digest' => hash('sha256', 'dirty')])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_page_cache_digest_mismatch']],
    'old salt block' => [static fn (): mixed => $blocked(0, ['old_wal_salt' => ['11111111', '22222222']])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_old_salt_mismatch']],
    'new salt block' => [static fn (): mixed => $blocked(0, ['new_wal_salt' => ['00000102', '00000304']])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_reused_restart_salt']],
    'reader block' => [static fn (): mixed => $blocked(0, ['reader_names' => ['wp-next258-unknown']])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_unknown_reader']],
    'readmark block' => [static fn (): mixed => $blocked(0, ['readmark_slots' => [9]])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_unknown_readmark_slot']],
    'wal size block' => [static fn (): mixed => $blocked(0, ['wal_size_before' => 64])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_wal_not_empty_before_write']],
    'first frame index block' => [static fn (): mixed => $blocked(0, ['first_frame_index' => 2])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_first_frame_not_one']],
    'mx frame before block' => [static fn (): mixed => $blocked(0, ['mx_frame_before' => 1])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_mxframe_not_zero_before_write']],
    'mx frame after block' => [static fn (): mixed => $blocked(0, ['mx_frame_after' => 2])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_mxframe_not_first_frame']],
    'exclusive lock block' => [static fn (): mixed => $blocked(0, ['exclusive_lock_held' => false])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_exclusive_lock_missing']],
    'hot journal block' => [static fn (): mixed => $blocked(0, ['hot_journal_visible' => true])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_hot_journal_visible']],
    'savepoint block' => [static fn (): mixed => $blocked(0, ['pending_savepoint_depth' => 1])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_savepoint_scope_open']],
    'sync block' => [static fn (): mixed => $blocked(0, ['durably_synced' => false])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_not_durably_synced']],
    'io error block' => [static fn (): mixed => $blocked(0, ['io_error' => 'EIO'])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_io_error']],
    'combined block' => [static fn (): mixed => $blocked(0, ['wal_size_before' => 64, 'exclusive_lock_held' => false, 'hot_journal_visible' => true])['writer_rows'][0]['blocked_reasons'], ['post_restart_writer_wal_not_empty_before_write', 'post_restart_writer_exclusive_lock_missing', 'post_restart_writer_hot_journal_visible']],
    'missing kind' => [static fn (): mixed => $plan(null, [$receipts[0], $receipts[1], $receipts[2]])['missing_writer_kinds'], ['sync']],
    'missing kind guard' => [static fn (): mixed => in_array('required_writer_kinds_present', $plan(null, [$receipts[0], $receipts[1], $receipts[2]])['blocked_guard_names'], true), true],
    'duplicate name' => [static fn (): mixed => $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2], $receipts[3]])['duplicate_writer_receipt_names'], ['wp-next258-header-salt']],
    'duplicate reason' => [static fn (): mixed => in_array('post_restart_writer_receipt_name_duplicate:wp-next258-header-salt', $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2], $receipts[3]])['blocked_writer_reasons'], true), true],
    'missing reader coverage' => [static fn (): mixed => $plan(null, array_map(static fn (array $row): array => array_replace($row, ['reader_names' => ['wp-next258-front']]), $receipts))['missing_reader_names'], ['wp-next258-import', 'wp-next258-plugin']],
    'missing readmark coverage' => [static fn (): mixed => $plan(null, array_map(static fn (array $row): array => array_replace($row, ['readmark_slots' => [1]]), $receipts))['missing_readmark_slots'], [2, 3]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next258 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => $plan(array_replace($readerPlan, ['status' => 'bad'])),
    'not admitted rejected' => static fn () => $plan(array_replace($readerPlan, ['restarted_reader_admitted' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($readerPlan, ['database_path' => ''])),
    'bad source token rejected' => static fn () => $plan(array_replace($readerPlan, ['source_token' => 'bad token'])),
    'bad commit generation rejected' => static fn () => $plan(array_replace($readerPlan, ['commit_generation' => 0])),
    'bad checkpoint frame rejected' => static fn () => $plan(array_replace($readerPlan, ['checkpoint_frame' => -1])),
    'bad database digest rejected' => static fn () => $plan(array_replace($readerPlan, ['database_digest' => 'short'])),
    'bad page cache digest rejected' => static fn () => $plan(array_replace($readerPlan, ['page_cache_digest' => 'short'])),
    'bad salt rejected' => static fn () => $plan(array_replace($readerPlan, ['next_wal_salt' => ['bad', '00000304']])),
    'bad readers rejected' => static fn () => $plan(array_replace($readerPlan, ['reopened_reader_names' => []])),
    'bad readmarks rejected' => static fn () => $plan(array_replace($readerPlan, ['readmark_slots' => [0]])),
    'bad receipt name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad kind rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['kind' => 'bad-kind'])]),
    'bad writer generation rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['commit_generation' => 0])]),
    'bad old salt rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['old_wal_salt' => ['bad', '00000304']])]),
    'bad new salt rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['new_wal_salt' => ['bad', '00000708']])]),
    'bad receipt reader rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['reader_names' => []])]),
    'bad receipt readmark rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['readmark_slots' => [0]])]),
    'bad wal size rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['wal_size_before' => -1])]),
    'bad first frame rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['first_frame_index' => 0])]),
    'bad page rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['first_frame_page' => 0])]),
    'bad mx frame rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['mx_frame_after' => 0])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next258 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
