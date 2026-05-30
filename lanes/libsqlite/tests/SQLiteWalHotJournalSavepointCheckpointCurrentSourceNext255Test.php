<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next255 checkpoint database image');
$pageCacheDigest = $hash('next255 clean page cache');
$resetPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next251',
    'wal_reset_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next255.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next255.sqlite-wal',
    'source_token' => 'wp-next255-current-source',
    'commit_generation' => 255,
    'checkpoint_frame' => 42,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'next_wal_salt' => ['next255-salt-a', 'next255-salt-b'],
    'accepted_reader_names' => ['front-reader', 'import-reader', 'cache-reader'],
    'released_reader_names' => ['front-reader', 'import-reader', 'cache-reader'],
    'operation_names' => ['admit_wal_sidecar_reset_next251'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next251'],
];

$reader = static function (string $name, string $readerName, int $slot, array $overrides = []) use ($resetPlan, $databaseDigest, $pageCacheDigest): array {
    return array_replace([
        'name' => $name,
        'reader_name' => $readerName,
        'readmark_slot' => $slot,
        'database_path' => $resetPlan['database_path'],
        'wal_path' => $resetPlan['wal_path'],
        'source_token' => $resetPlan['source_token'],
        'commit_generation' => $resetPlan['commit_generation'],
        'checkpoint_frame' => $resetPlan['checkpoint_frame'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_salt' => $resetPlan['next_wal_salt'],
        'wal_size' => 32,
        'mx_frame' => 0,
        'visible_frame_count' => 0,
        'hot_journal_visible' => false,
        'clean_page_cache' => true,
        'read_transaction_open' => true,
        'io_error' => null,
    ], $overrides);
};

$receipts = [
    $reader('front-reopen', 'front-reader', 1),
    $reader('import-reopen', 'import-reader', 2),
    $reader('cache-reopen', 'cache-reader', 3),
];

$plan = static fn (?array $inputPlan = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next255AdmitRestartedWalReaders($inputPlan ?? $resetPlan, $inputReceipts ?? $receipts);
$blocked = static fn (int $index, array $overrides): array => $plan(null, array_map(
    static fn (array $row, int $rowIndex): array => $rowIndex === $index ? array_replace($row, $overrides) : $row,
    $receipts,
    array_keys($receipts)
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next255'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'restarted_wal_readers_admitted_after_hot_journal_checkpoint_reset'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next251'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next255.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next255.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next255-current-source'],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 255],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 42],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'wal salt' => [static fn (): mixed => $plan()['next_wal_salt'], ['next255-salt-a', 'next255-salt-b']],
    'accepted reader names' => [static fn (): mixed => $plan()['accepted_reader_names'], ['front-reader', 'import-reader', 'cache-reader']],
    'released reader names' => [static fn (): mixed => $plan()['released_reader_names'], ['front-reader', 'import-reader', 'cache-reader']],
    'reader receipt names' => [static fn (): mixed => $plan()['reader_receipt_names'], ['front-reopen', 'import-reopen', 'cache-reopen']],
    'accepted receipt names' => [static fn (): mixed => $plan()['accepted_reader_receipt_names'], ['front-reopen', 'import-reopen', 'cache-reopen']],
    'blocked receipt names empty' => [static fn (): mixed => $plan()['blocked_reader_receipt_names'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_reader_receipt_names'], []],
    'reopened readers' => [static fn (): mixed => $plan()['reopened_reader_names'], ['cache-reader', 'front-reader', 'import-reader']],
    'missing readers empty' => [static fn (): mixed => $plan()['missing_reopened_reader_names'], []],
    'readmark slots' => [static fn (): mixed => $plan()['readmark_slots'], [1, 2, 3]],
    'duplicate readmarks empty' => [static fn (): mixed => $plan()['duplicate_readmark_slots'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reader_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next251_wal_reset_admitted', 'reader_receipt_names_unique', 'released_readers_reopened', 'readmark_slots_unique', 'all_reader_receipts_current']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'admitted flag' => [static fn (): mixed => $plan()['restarted_reader_admitted'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'serve_readers_from_checkpoint_database_with_empty_restarted_wal'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'keep_restarted_wal_generation_255_empty'],
    'cache action' => [static fn (): mixed => $plan()['cache_action'], 'reuse_clean_page_cache_digest_' . $pageCacheDigest],
    'digest length' => [static fn (): mixed => strlen($plan()['admission_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_wal_sidecar_reset_next251', $plan()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_restarted_wal_reader_receipts_current_source_next255', $plan()['operation_names'], true), true],
    'operation admit' => [static fn (): mixed => in_array('admit_restarted_wal_readers_next255', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next251', $plan()['dependencies'], true), true],
    'dependency next255' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next255', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-restarted-wal-reader-admission', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat durable page writes'), true],
    'row accepted' => [static fn (): mixed => $plan()['reader_rows'][0]['accepted'], true],
    'row reason' => [static fn (): mixed => $plan()['reader_rows'][0]['receipt_reason'], 'restarted_reader_receipt_matches_current_source'],
    'row readmark' => [static fn (): mixed => $plan()['reader_rows'][1]['readmark_slot'], 2],
    'row wal size' => [static fn (): mixed => $plan()['reader_rows'][2]['wal_size'], 32],
    'blocked status' => [static fn (): mixed => $blocked(0, ['wal_size' => 64])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next255'],
    'blocked reason' => [static fn (): mixed => $blocked(0, ['wal_size' => 64])['reason'], 'restarted_wal_readers_wait_for_current_source_receipts'],
    'blocked reader action' => [static fn (): mixed => $blocked(0, ['wal_size' => 64])['reader_action'], 'hold_readers_until_restarted_wal_receipts_match'],
    'blocked wal action' => [static fn (): mixed => $blocked(0, ['wal_size' => 64])['wal_action'], 'preserve_reset_fence_for_restarted_wal'],
    'blocked cache action' => [static fn (): mixed => $blocked(0, ['clean_page_cache' => false])['cache_action'], 'discard_reopened_reader_cache'],
    'database path block' => [static fn (): mixed => $blocked(0, ['database_path' => '/tmp/other.sqlite'])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_database_path_mismatch']],
    'wal path block' => [static fn (): mixed => $blocked(0, ['wal_path' => '/tmp/other.sqlite-wal'])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_wal_path_mismatch']],
    'source token block' => [static fn (): mixed => $blocked(0, ['source_token' => 'old-source'])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_source_token_mismatch']],
    'generation block' => [static fn (): mixed => $blocked(0, ['commit_generation' => 254])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_generation_mismatch']],
    'checkpoint block' => [static fn (): mixed => $blocked(0, ['checkpoint_frame' => 41])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_checkpoint_frame_mismatch']],
    'database digest block' => [static fn (): mixed => $blocked(0, ['database_digest' => $hash('stale database')])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_database_digest_mismatch']],
    'cache digest block' => [static fn (): mixed => $blocked(0, ['page_cache_digest' => $hash('dirty cache')])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_page_cache_digest_mismatch']],
    'salt block' => [static fn (): mixed => $blocked(0, ['wal_salt' => ['old-a', 'old-b']])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_wal_salt_mismatch']],
    'unknown reader block' => [static fn (): mixed => $blocked(0, ['reader_name' => 'stale-reader'])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_name_not_released']],
    'wal size block' => [static fn (): mixed => $blocked(0, ['wal_size' => 64])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_wal_header_size_mismatch']],
    'mx frame block' => [static fn (): mixed => $blocked(0, ['mx_frame' => 1])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_mxframe_not_zero']],
    'visible frames block' => [static fn (): mixed => $blocked(0, ['visible_frame_count' => 1])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_visible_frames_not_empty']],
    'hot journal block' => [static fn (): mixed => $blocked(0, ['hot_journal_visible' => true])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_hot_journal_visible']],
    'dirty cache block' => [static fn (): mixed => $blocked(0, ['clean_page_cache' => false])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_page_cache_not_clean']],
    'transaction block' => [static fn (): mixed => $blocked(0, ['read_transaction_open' => false])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_transaction_not_open']],
    'io block' => [static fn (): mixed => $blocked(0, ['io_error' => 'SQLITE_IOERR_SHORT_READ'])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_io_error']],
    'combined block' => [static fn (): mixed => $blocked(0, ['wal_size' => 64, 'mx_frame' => 1, 'clean_page_cache' => false])['reader_rows'][0]['blocked_reasons'], ['restarted_reader_wal_header_size_mismatch', 'restarted_reader_mxframe_not_zero', 'restarted_reader_page_cache_not_clean']],
    'missing reopened reader' => [static fn (): mixed => $plan(null, [$receipts[0], $receipts[1]])['missing_reopened_reader_names'], ['cache-reader']],
    'missing reopened guard' => [static fn (): mixed => in_array('released_readers_reopened', $plan(null, [$receipts[0], $receipts[1]])['blocked_guard_names'], true), true],
    'duplicate receipt name' => [static fn (): mixed => $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => 'front-reopen']), $receipts[2]])['duplicate_reader_receipt_names'], ['front-reopen']],
    'duplicate receipt reason' => [static fn (): mixed => in_array('restarted_reader_receipt_name_duplicate:front-reopen', $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => 'front-reopen']), $receipts[2]])['blocked_reader_reasons'], true), true],
    'duplicate readmark' => [static fn (): mixed => $plan(null, [$receipts[0], array_replace($receipts[1], ['readmark_slot' => 1]), $receipts[2]])['duplicate_readmark_slots'], [1]],
    'duplicate readmark guard' => [static fn (): mixed => in_array('readmark_slots_unique', $plan(null, [$receipts[0], array_replace($receipts[1], ['readmark_slot' => 1]), $receipts[2]])['blocked_guard_names'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next255 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => $plan(array_replace($resetPlan, ['status' => 'bad'])),
    'not admitted rejected' => static fn () => $plan(array_replace($resetPlan, ['wal_reset_admitted' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($resetPlan, ['database_path' => ''])),
    'bad source token rejected' => static fn () => $plan(array_replace($resetPlan, ['source_token' => 'bad token'])),
    'bad generation rejected' => static fn () => $plan(array_replace($resetPlan, ['commit_generation' => 0])),
    'bad digest rejected' => static fn () => $plan(array_replace($resetPlan, ['database_digest' => 'short'])),
    'bad salt rejected' => static fn () => $plan(array_replace($resetPlan, ['next_wal_salt' => ['one']])),
    'bad accepted readers rejected' => static fn () => $plan(array_replace($resetPlan, ['accepted_reader_names' => []])),
    'bad released readers rejected' => static fn () => $plan(array_replace($resetPlan, ['released_reader_names' => []])),
    'bad receipt name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad reader name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['reader_name' => 'bad name'])]),
    'bad readmark rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['readmark_slot' => 0])]),
    'bad receipt salt rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['wal_salt' => ['one']])]),
    'bad wal size rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['wal_size' => -1])]),
    'bad mx frame rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['mx_frame' => -1])]),
    'bad visible frame count rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['visible_frame_count' => -1])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next255 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
