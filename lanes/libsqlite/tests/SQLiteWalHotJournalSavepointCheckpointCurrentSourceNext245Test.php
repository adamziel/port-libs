<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext245Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext245Plan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next245 reopened reader database image');
$commitPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next242',
    'commit_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next245.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next245.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next245.sqlite-journal',
    'source_token' => 'wp-next245-current-source',
    'writer_generation' => 245,
    'next_source_generation' => 246,
    'database_digest' => $databaseDigest,
    'expected_schema_cookie' => 24577,
    'expected_wal_salt' => '2450abcd2450dcba',
    'covered_page_numbers' => [1, 2, 3, 4, 5, 6, 9],
    'operation_names' => ['publish_post_checkpoint_writer_current_source_next242'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next242'],
];

$receipt = static function (string $name, array $overrides = []) use ($commitPlan, $databaseDigest): array {
    return array_replace([
        'name' => $name,
        'database_path' => $commitPlan['database_path'],
        'wal_path' => $commitPlan['wal_path'],
        'journal_path' => $commitPlan['journal_path'],
        'source_token' => $commitPlan['source_token'],
        'writer_generation' => $commitPlan['writer_generation'],
        'reader_generation' => $commitPlan['next_source_generation'],
        'database_digest' => $databaseDigest,
        'schema_cookie' => $commitPlan['expected_schema_cookie'],
        'wal_salt' => $commitPlan['expected_wal_salt'],
        'readmark_frame' => 2,
        'last_visible_frame' => 4,
        'page_numbers' => [1, 2, 5],
        'page_cache_clean' => true,
        'snapshot_open' => true,
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
        'reserved_lock_held' => false,
    ], $overrides);
};

$receipts = [
    $receipt('wp-next245-front-page', ['page_numbers' => [1, 2, 3], 'readmark_frame' => 1, 'last_visible_frame' => 3]),
    $receipt('wp-next245-options-import', ['page_numbers' => [2, 5, 6], 'readmark_frame' => 3, 'last_visible_frame' => 5]),
    $receipt('wp-next245-plugin-cache', ['page_numbers' => [1, 4, 9], 'reader_generation' => 247]),
];

$plan = static fn (?array $inputPlan = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext245Plan::admitReopenedReaders($inputPlan ?? $commitPlan, $inputReceipts ?? $receipts);
$blocked = static fn (int $index, array $overrides): array => $plan(null, array_map(
    static fn (array $row, int $rowIndex): array => $rowIndex === $index ? array_replace($row, $overrides) : $row,
    $receipts,
    array_keys($receipts)
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next245'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reopened_readers_are_bound_to_committed_checkpoint_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next242'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $commitPlan['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $commitPlan['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $commitPlan['journal_path']],
    'source token' => [static fn (): mixed => $plan()['source_token'], $commitPlan['source_token']],
    'writer generation' => [static fn (): mixed => $plan()['writer_generation'], 245],
    'next source generation' => [static fn (): mixed => $plan()['next_source_generation'], 246],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'schema cookie' => [static fn (): mixed => $plan()['expected_schema_cookie'], 24577],
    'wal salt' => [static fn (): mixed => $plan()['expected_wal_salt'], '2450abcd2450dcba'],
    'covered pages' => [static fn (): mixed => $plan()['covered_page_numbers'], [1, 2, 3, 4, 5, 6, 9]],
    'reader names' => [static fn (): mixed => $plan()['reader_names'], ['wp-next245-front-page', 'wp-next245-options-import', 'wp-next245-plugin-cache']],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_reader_names'], []],
    'accepted names' => [static fn (): mixed => $plan()['accepted_reader_names'], ['wp-next245-front-page', 'wp-next245-options-import', 'wp-next245-plugin-cache']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_reader_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next242_commit_admitted', 'reader_receipt_names_unique', 'reader_generations_follow_committed_writer', 'reader_tokens_match_current_source', 'reader_snapshots_match_database_digest', 'reader_wal_salt_and_frames_current', 'reader_page_cache_is_checkpoint_covered', 'hot_journal_and_savepoint_fences_clear', 'all_reopened_readers_current']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'readers admitted' => [static fn (): mixed => $plan()['readers_admitted'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'serve_reopened_readers_from_generation_246'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'retain_checkpoint_wal_until_reader_release'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'hot_journal_delete_fence_satisfied'],
    'cache action' => [static fn (): mixed => $plan()['cache_action'], 'reuse_checkpoint_page_cache_for_reopened_readers'],
    'digest length' => [static fn (): mixed => strlen($plan()['reader_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('publish_post_checkpoint_writer_current_source_next242', $plan()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_reopened_reader_cache_current_source_next245', $plan()['operation_names'], true), true],
    'operation admit' => [static fn (): mixed => in_array('admit_reopened_reader_cache_current_source_next245', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next242', $plan()['dependencies'], true), true],
    'dependency next245' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next245', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-reopened-reader-after-checkpoint-commit', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat writer commit receipt validation'), true],
    'first row accepted' => [static fn (): mixed => $plan()['reader_rows'][0]['accepted'], true],
    'first row reason' => [static fn (): mixed => $plan()['reader_rows'][0]['receipt_reason'], 'reader_receipt_current'],
    'first row frames' => [static fn (): mixed => [$plan()['reader_rows'][0]['readmark_frame'], $plan()['reader_rows'][0]['last_visible_frame']], [1, 3]],
    'first row generation safe' => [static fn (): mixed => $plan()['reader_rows'][0]['generation_safe'], true],
    'first row source match' => [static fn (): mixed => $plan()['reader_rows'][0]['source_token_match'], true],
    'first row digest match' => [static fn (): mixed => $plan()['reader_rows'][0]['database_digest_match'], true],
    'first row wal current' => [static fn (): mixed => $plan()['reader_rows'][0]['wal_snapshot_current'], true],
    'first row cache covered' => [static fn (): mixed => $plan()['reader_rows'][0]['page_cache_covered'], true],
    'first row fences clear' => [static fn (): mixed => $plan()['reader_rows'][0]['fences_clear'], true],
    'third reader generation' => [static fn (): mixed => $plan()['reader_rows'][2]['reader_generation'], 247],
    'blocked status' => [static fn (): mixed => $blocked(0, ['reader_generation' => 245])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next245'],
    'blocked reader action' => [static fn (): mixed => $blocked(0, ['reader_generation' => 245])['reader_action'], 'reopen_reader_snapshots_before_serving'],
    'blocked wal action' => [static fn (): mixed => $blocked(0, ['reader_generation' => 245])['wal_action'], 'preserve_wal_until_reader_snapshot_matches'],
    'blocked journal action' => [static fn (): mixed => $blocked(0, ['reader_generation' => 245])['journal_action'], 'hold_hot_journal_delete_fence'],
    'blocked cache action' => [static fn (): mixed => $blocked(0, ['reader_generation' => 245])['cache_action'], 'discard_stale_reader_page_cache'],
    'database path block' => [static fn (): mixed => $blocked(0, ['database_path' => '/tmp/other.sqlite'])['reader_rows'][0]['blocked_reasons'], ['reader_database_path_mismatch']],
    'wal path block' => [static fn (): mixed => $blocked(0, ['wal_path' => '/tmp/other.sqlite-wal'])['reader_rows'][0]['blocked_reasons'], ['reader_wal_path_mismatch']],
    'journal path block' => [static fn (): mixed => $blocked(0, ['journal_path' => '/tmp/other.sqlite-journal'])['reader_rows'][0]['blocked_reasons'], ['reader_journal_path_mismatch']],
    'source token block' => [static fn (): mixed => $blocked(0, ['source_token' => 'old-source'])['reader_rows'][0]['blocked_reasons'], ['reader_source_token_mismatch']],
    'writer generation block' => [static fn (): mixed => $blocked(0, ['writer_generation' => 244])['reader_rows'][0]['blocked_reasons'], ['reader_writer_generation_mismatch']],
    'reader generation block' => [static fn (): mixed => $blocked(0, ['reader_generation' => 245])['reader_rows'][0]['blocked_reasons'], ['reader_generation_stale']],
    'database digest block' => [static fn (): mixed => $blocked(0, ['database_digest' => $hash('stale reader')])['reader_rows'][0]['blocked_reasons'], ['reader_database_digest_mismatch']],
    'schema cookie block' => [static fn (): mixed => $blocked(0, ['schema_cookie' => 1])['reader_rows'][0]['blocked_reasons'], ['reader_schema_cookie_mismatch']],
    'wal salt block' => [static fn (): mixed => $blocked(0, ['wal_salt' => '0000abcd2450dcba'])['reader_rows'][0]['blocked_reasons'], ['reader_wal_salt_mismatch']],
    'frame order block' => [static fn (): mixed => $blocked(0, ['readmark_frame' => 5, 'last_visible_frame' => 4])['reader_rows'][0]['blocked_reasons'], ['reader_visible_frame_before_readmark']],
    'frame past writer block' => [static fn (): mixed => $blocked(0, ['last_visible_frame' => 246])['reader_rows'][0]['blocked_reasons'], ['reader_visible_frame_past_writer_generation']],
    'page block' => [static fn (): mixed => $blocked(0, ['page_numbers' => [1, 8]])['reader_rows'][0]['blocked_reasons'], ['reader_page_not_checkpoint_covered']],
    'cache dirty block' => [static fn (): mixed => $blocked(0, ['page_cache_clean' => false])['reader_rows'][0]['blocked_reasons'], ['reader_page_cache_dirty']],
    'snapshot closed block' => [static fn (): mixed => $blocked(0, ['snapshot_open' => false])['reader_rows'][0]['blocked_reasons'], ['reader_snapshot_not_open']],
    'hot journal block' => [static fn (): mixed => $blocked(0, ['hot_journal_visible' => true])['reader_rows'][0]['blocked_reasons'], ['reader_hot_journal_visible']],
    'savepoint block' => [static fn (): mixed => $blocked(0, ['savepoint_depth' => 1])['reader_rows'][0]['blocked_reasons'], ['reader_savepoint_scope_open']],
    'reserved lock block' => [static fn (): mixed => $blocked(0, ['reserved_lock_held' => true])['reader_rows'][0]['blocked_reasons'], ['reader_reserved_lock_held']],
    'duplicate name block' => [static fn (): mixed => in_array('reader_receipt_name_duplicate', $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2]])['blocked_reasons'], true), true],
    'duplicate guard block' => [static fn (): mixed => $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2]])['blocked_guard_names'], ['reader_receipt_names_unique']],
    'combined block reasons' => [static fn (): mixed => $blocked(1, ['source_token' => 'old-source', 'page_cache_clean' => false, 'hot_journal_visible' => true])['reader_rows'][1]['blocked_reasons'], ['reader_source_token_mismatch', 'reader_page_cache_dirty', 'reader_hot_journal_visible']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next245 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => $plan(array_replace($commitPlan, ['status' => 'bad'])),
    'not admitted rejected' => static fn () => $plan(array_replace($commitPlan, ['commit_admitted' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($commitPlan, ['database_path' => ''])),
    'bad source token rejected' => static fn () => $plan(array_replace($commitPlan, ['source_token' => 'bad token'])),
    'bad writer generation rejected' => static fn () => $plan(array_replace($commitPlan, ['writer_generation' => 0])),
    'bad next generation rejected' => static fn () => $plan(array_replace($commitPlan, ['next_source_generation' => 0])),
    'bad digest rejected' => static fn () => $plan(array_replace($commitPlan, ['database_digest' => 'short'])),
    'bad schema cookie rejected' => static fn () => $plan(array_replace($commitPlan, ['expected_schema_cookie' => 0])),
    'bad salt rejected' => static fn () => $plan(array_replace($commitPlan, ['expected_wal_salt' => 'short'])),
    'bad covered pages rejected' => static fn () => $plan(array_replace($commitPlan, ['covered_page_numbers' => [0]])),
    'bad reader name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad reader generation rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['reader_generation' => 0])]),
    'bad reader digest rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['database_digest' => 'short'])]),
    'bad readmark rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['readmark_frame' => -1])]),
    'bad page rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['page_numbers' => [0]])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next245 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
