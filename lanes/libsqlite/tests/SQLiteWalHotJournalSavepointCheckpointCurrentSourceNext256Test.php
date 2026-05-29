<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next256 sealed current source image');
$sealedPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next252',
    'post_truncate_source_sealed' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next256.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next256.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next256.sqlite-journal',
    'source_token' => 'wp-next256-sealed-source',
    'next_source_generation' => 256,
    'database_digest' => $databaseDigest,
    'released_reader_names' => ['wp-next256-front-page', 'wp-next256-options-import', 'wp-next256-plugin-cache'],
    'covered_page_numbers' => [1, 2, 3, 4, 8, 16],
    'operation_names' => ['advance_checkpoint_current_source_next252'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next252'],
];

$receipt = static function (string $name, string $reader, int $slot, array $pages, array $overrides = []) use ($sealedPlan, $databaseDigest): array {
    return array_replace([
        'name' => $name,
        'reader_name' => $reader,
        'database_path' => $sealedPlan['database_path'],
        'wal_path' => $sealedPlan['wal_path'],
        'journal_path' => $sealedPlan['journal_path'],
        'source_token' => $sealedPlan['source_token'],
        'source_generation' => $sealedPlan['next_source_generation'],
        'database_digest' => $databaseDigest,
        'page_numbers' => $pages,
        'readmark_slot' => $slot,
        'checkpoint_sequence' => 4096 + $slot,
        'database_change_counter' => 8192 + $slot,
        'schema_cookie' => 17 + $slot,
        'wal_size' => 0,
        'shm_mx_frame' => 0,
        'hot_journal_exists' => false,
        'pending_savepoint_depth' => 0,
        'database_synced' => true,
        'directory_synced' => true,
        'read_transaction_open' => true,
        'io_error' => null,
    ], $overrides);
};

$receipts = [
    $receipt('wp-next256-front-page-reopen', 'wp-next256-front-page', 1, [1, 2, 3]),
    $receipt('wp-next256-options-reopen', 'wp-next256-options-import', 2, [3, 4, 8]),
    $receipt('wp-next256-plugin-cache-reopen', 'wp-next256-plugin-cache', 3, [8, 16]),
];

$plan = static fn (?array $inputPlan = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next256AdmitReopenedReaders($inputPlan ?? $sealedPlan, $inputReceipts ?? $receipts);
$blocked = static fn (int $index, array $overrides): array => $plan(null, array_map(
    static fn (array $row, int $rowIndex): array => $rowIndex === $index ? array_replace($row, $overrides) : $row,
    $receipts,
    array_keys($receipts)
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next256'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reopened_readers_admitted_on_sealed_checkpoint_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next252'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $sealedPlan['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $sealedPlan['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $sealedPlan['journal_path']],
    'source token' => [static fn (): mixed => $plan()['source_token'], $sealedPlan['source_token']],
    'source generation' => [static fn (): mixed => $plan()['source_generation'], 256],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'sealed readers sorted' => [static fn (): mixed => $plan()['sealed_reader_names'], ['wp-next256-front-page', 'wp-next256-options-import', 'wp-next256-plugin-cache']],
    'sealed pages sorted' => [static fn (): mixed => $plan()['sealed_page_numbers'], [1, 2, 3, 4, 8, 16]],
    'reader receipt names' => [static fn (): mixed => $plan()['reader_receipt_names'], ['wp-next256-front-page-reopen', 'wp-next256-options-reopen', 'wp-next256-plugin-cache-reopen']],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_reader_receipt_names'], []],
    'accepted receipt names' => [static fn (): mixed => $plan()['accepted_reader_receipt_names'], ['wp-next256-front-page-reopen', 'wp-next256-options-reopen', 'wp-next256-plugin-cache-reopen']],
    'blocked receipt names empty' => [static fn (): mixed => $plan()['blocked_reader_receipt_names'], []],
    'covered readers' => [static fn (): mixed => $plan()['covered_reader_names'], ['wp-next256-front-page', 'wp-next256-options-import', 'wp-next256-plugin-cache']],
    'missing readers empty' => [static fn (): mixed => $plan()['missing_reader_names'], []],
    'covered pages' => [static fn (): mixed => $plan()['covered_page_numbers'], [1, 2, 3, 4, 8, 16]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_page_numbers'], []],
    'readmark slots' => [static fn (): mixed => $plan()['readmark_slots'], [1, 2, 3]],
    'readmark layout safe' => [static fn (): mixed => $plan()['readmark_layout_safe'], true],
    'max checkpoint sequence' => [static fn (): mixed => $plan()['max_checkpoint_sequence'], 4099],
    'min change counter' => [static fn (): mixed => $plan()['min_database_change_counter'], 8193],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next252_source_sealed', 'reader_receipt_names_unique', 'sealed_readers_reopened', 'sealed_pages_visible', 'readmark_layout_safe', 'all_reader_receipts_current']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'readers admitted' => [static fn (): mixed => $plan()['reopened_readers_admitted'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'serve_reopened_readers_from_current_source_generation_256'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'keep_empty_restarted_wal_as_current_source'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'hot_journal_retirement_visible_to_readers'],
    'digest length' => [static fn (): mixed => strlen($plan()['current_source_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('advance_checkpoint_current_source_next252', $plan()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_reopened_readers_current_source_next256', $plan()['operation_names'], true), true],
    'operation admit' => [static fn (): mixed => in_array('admit_reopened_readers_current_source_next256', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next252', $plan()['dependencies'], true), true],
    'dependency next256' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next256', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-reader-reopen-after-wal-checkpoint-seal', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat WAL sidecar truncation'), true],
    'first row accepted' => [static fn (): mixed => $plan()['reader_rows'][0]['accepted'], true],
    'first row reason' => [static fn (): mixed => $plan()['reader_rows'][0]['acceptance_reason'], 'reopened_reader_current_source_matches'],
    'first row reader' => [static fn (): mixed => $plan()['reader_rows'][0]['reader_name'], 'wp-next256-front-page'],
    'first row pages' => [static fn (): mixed => $plan()['reader_rows'][0]['page_numbers'], [1, 2, 3]],
    'blocked status' => [static fn (): mixed => $blocked(0, ['wal_size' => 32])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next256'],
    'blocked reader action' => [static fn (): mixed => $blocked(0, ['wal_size' => 32])['reader_action'], 'retry_reader_open_after_checkpoint_source_refresh'],
    'blocked wal action' => [static fn (): mixed => $blocked(0, ['wal_size' => 32])['wal_action'], 'preserve_checkpoint_wal_restart_guard'],
    'blocked journal action' => [static fn (): mixed => $blocked(0, ['hot_journal_exists' => true])['journal_action'], 'block_reader_until_hot_journal_absence_confirmed'],
    'database path block' => [static fn (): mixed => $blocked(0, ['database_path' => '/tmp/other.sqlite'])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_database_path_mismatch']],
    'wal path block' => [static fn (): mixed => $blocked(0, ['wal_path' => '/tmp/other.sqlite-wal'])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_wal_path_mismatch']],
    'journal path block' => [static fn (): mixed => $blocked(0, ['journal_path' => '/tmp/other.sqlite-journal'])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_journal_path_mismatch']],
    'reader name block' => [static fn (): mixed => $blocked(0, ['reader_name' => 'wp-next256-unknown-reader'])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_not_in_sealed_reader_set']],
    'source token block' => [static fn (): mixed => $blocked(0, ['source_token' => 'old-source'])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_source_token_mismatch']],
    'source generation block' => [static fn (): mixed => $blocked(0, ['source_generation' => 255])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_source_generation_mismatch']],
    'digest block' => [static fn (): mixed => $blocked(0, ['database_digest' => $hash('stale image')])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_database_digest_mismatch']],
    'page block' => [static fn (): mixed => $blocked(0, ['page_numbers' => [1, 99]])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_page_not_in_sealed_checkpoint']],
    'wal size block' => [static fn (): mixed => $blocked(0, ['wal_size' => 32])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_wal_not_empty']],
    'shm frame block' => [static fn (): mixed => $blocked(0, ['shm_mx_frame' => 1])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_shm_mxframe_not_reset']],
    'hot journal block' => [static fn (): mixed => $blocked(0, ['hot_journal_exists' => true])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_hot_journal_present']],
    'savepoint depth block' => [static fn (): mixed => $blocked(0, ['pending_savepoint_depth' => 1])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_savepoint_scope_open']],
    'database sync block' => [static fn (): mixed => $blocked(0, ['database_synced' => false])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_database_not_synced']],
    'directory sync block' => [static fn (): mixed => $blocked(0, ['directory_synced' => false])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_directory_not_synced']],
    'read transaction block' => [static fn (): mixed => $blocked(0, ['read_transaction_open' => false])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_read_transaction_missing']],
    'io error block' => [static fn (): mixed => $blocked(0, ['io_error' => 'EIO'])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_io_error']],
    'combined block' => [static fn (): mixed => $blocked(0, ['wal_size' => 32, 'hot_journal_exists' => true, 'database_synced' => false])['reader_rows'][0]['blocked_reasons'], ['reopened_reader_wal_not_empty', 'reopened_reader_hot_journal_present', 'reopened_reader_database_not_synced']],
    'missing reader coverage' => [static fn (): mixed => $plan(null, [$receipts[0], $receipts[1]])['missing_reader_names'], ['wp-next256-plugin-cache']],
    'missing reader guard' => [static fn (): mixed => in_array('sealed_readers_reopened', $plan(null, [$receipts[0], $receipts[1]])['blocked_guard_names'], true), true],
    'missing page coverage' => [static fn (): mixed => $plan(null, array_map(static fn (array $row): array => array_replace($row, ['page_numbers' => [1]]), $receipts))['missing_page_numbers'], [2, 3, 4, 8, 16]],
    'missing page guard' => [static fn (): mixed => in_array('sealed_pages_visible', $plan(null, array_map(static fn (array $row): array => array_replace($row, ['page_numbers' => [1]]), $receipts))['blocked_guard_names'], true), true],
    'duplicate receipt name' => [static fn (): mixed => $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2]])['duplicate_reader_receipt_names'], ['wp-next256-front-page-reopen']],
    'duplicate receipt reason' => [static fn (): mixed => in_array('reopened_reader_receipt_name_duplicate:wp-next256-front-page-reopen', $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2]])['blocked_reasons'], true), true],
    'duplicate readmark slot unsafe' => [static fn (): mixed => $plan(null, [$receipts[0], array_replace($receipts[1], ['readmark_slot' => 1]), $receipts[2]])['readmark_layout_safe'], false],
    'duplicate reader unsafe' => [static fn (): mixed => $plan(null, [$receipts[0], array_replace($receipts[1], ['reader_name' => 'wp-next256-front-page']), $receipts[2]])['readmark_layout_safe'], false],
    'readmark guard blocked' => [static fn (): mixed => in_array('readmark_layout_safe', $plan(null, [$receipts[0], array_replace($receipts[1], ['readmark_slot' => 1]), $receipts[2]])['blocked_guard_names'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next256 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => $plan(array_replace($sealedPlan, ['status' => 'bad'])),
    'unsealed base rejected' => static fn () => $plan(array_replace($sealedPlan, ['post_truncate_source_sealed' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($sealedPlan, ['database_path' => ''])),
    'bad wal path rejected' => static fn () => $plan(array_replace($sealedPlan, ['wal_path' => ''])),
    'bad journal path rejected' => static fn () => $plan(array_replace($sealedPlan, ['journal_path' => ''])),
    'bad source token rejected' => static fn () => $plan(array_replace($sealedPlan, ['source_token' => 'bad token'])),
    'bad source generation rejected' => static fn () => $plan(array_replace($sealedPlan, ['next_source_generation' => 0])),
    'bad digest rejected' => static fn () => $plan(array_replace($sealedPlan, ['database_digest' => 'short'])),
    'bad sealed readers rejected' => static fn () => $plan(array_replace($sealedPlan, ['released_reader_names' => []])),
    'bad sealed pages rejected' => static fn () => $plan(array_replace($sealedPlan, ['covered_page_numbers' => [0]])),
    'bad receipt name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt reader rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['reader_name' => 'bad reader'])]),
    'bad receipt pages rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['page_numbers' => [0]])]),
    'bad readmark slot rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['readmark_slot' => 0])]),
    'bad checkpoint sequence rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['checkpoint_sequence' => 0])]),
    'bad change counter rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['database_change_counter' => 0])]),
    'bad schema cookie rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['schema_cookie' => 0])]),
    'bad receipt source generation rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['source_generation' => 0])]),
    'bad receipt digest rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['database_digest' => 'short'])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next256 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
