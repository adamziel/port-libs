<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next238 checkpoint database image');
$publication = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next235',
    'publication_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next238.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next238.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next238.sqlite-journal',
    'source_token' => 'wp-next238-current-source',
    'next_writer_generation' => 238,
    'database_digest' => $databaseDigest,
    'expected_schema_cookie' => 23877,
    'expected_wal_salt' => '2380abcd2380dcba',
    'covered_page_numbers' => [1, 2, 3, 4, 5],
    'operation_names' => ['admit_durable_reopened_current_source_next235'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next235'],
];
$reader = static function (string $name, array $overrides = []) use ($publication, $databaseDigest): array {
    return array_replace([
        'name' => $name,
        'database_path' => $publication['database_path'],
        'wal_path' => $publication['wal_path'],
        'journal_path' => $publication['journal_path'],
        'source_token' => $publication['source_token'],
        'generation' => $publication['next_writer_generation'],
        'observed_database_digest' => $databaseDigest,
        'observed_schema_cookie' => $publication['expected_schema_cookie'],
        'observed_wal_salt' => $publication['expected_wal_salt'],
        'observed_wal_frame' => 0,
        'observed_page_numbers' => [1, 2, 3, 4],
        'hot_journal_visible' => false,
        'shared_lock' => true,
        'dirty_page_cache' => false,
        'wal_header_restarted' => true,
    ], $overrides);
};
$receipts = [
    $reader('wp-next238-options-reader'),
    $reader('wp-next238-meta-reader', ['observed_page_numbers' => [2, 5]]),
    $reader('wp-next238-schema-reader', ['observed_page_numbers' => [1]]),
];
$plan = static fn (?array $inputPlan = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next238AdmitNextWriter($inputPlan ?? $publication, $inputReceipts ?? $receipts);
$blocked = static fn (int $index, array $overrides): array => $plan(null, array_map(
    static fn (array $row, int $rowIndex): array => $rowIndex === $index ? array_replace($row, $overrides) : $row,
    $receipts,
    array_keys($receipts)
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next238'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'post_publication_writer_admitted_after_reopened_readers_observe_clean_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next235'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $publication['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $publication['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $publication['journal_path']],
    'source token' => [static fn (): mixed => $plan()['source_token'], $publication['source_token']],
    'published generation' => [static fn (): mixed => $plan()['published_writer_generation'], 238],
    'next generation' => [static fn (): mixed => $plan()['next_writer_generation'], 239],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'schema cookie' => [static fn (): mixed => $plan()['expected_schema_cookie'], 23877],
    'wal salt' => [static fn (): mixed => $plan()['expected_wal_salt'], '2380abcd2380dcba'],
    'covered pages' => [static fn (): mixed => $plan()['covered_page_numbers'], [1, 2, 3, 4, 5]],
    'reader names sorted' => [static fn (): mixed => $plan()['reader_names'], ['wp-next238-meta-reader', 'wp-next238-options-reader', 'wp-next238-schema-reader']],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_reader_names'], []],
    'accepted names' => [static fn (): mixed => $plan()['accepted_reader_names'], ['wp-next238-options-reader', 'wp-next238-meta-reader', 'wp-next238-schema-reader']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_reader_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next235_durable_publication_admitted', 'reader_reopen_receipts_unique', 'readers_observe_published_database_digest', 'readers_start_at_restarted_wal_frame_zero', 'readers_do_not_observe_hot_journal', 'readers_hold_shared_lock_after_reopen', 'reader_pages_are_checkpoint_covered', 'all_reopened_readers_match_current_source']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'writer admitted' => [static fn (): mixed => $plan()['writer_admitted'], true],
    'writer action' => [static fn (): mixed => $plan()['writer_action'], 'start_writer_generation_239'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'keep_readers_on_restarted_wal_zero_frame'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'append_new_frames_after_clean_restart'],
    'admission digest length' => [static fn (): mixed => strlen($plan()['admission_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_durable_reopened_current_source_next235', $plan()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_reopened_readers_observe_clean_current_source_next238', $plan()['operation_names'], true), true],
    'operation admit' => [static fn (): mixed => in_array('admit_next_writer_after_restart_checkpoint_next238', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next235', $plan()['dependencies'], true), true],
    'dependency next238' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next238', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-reopened-readers-before-next-writer', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat checkpoint byte materialization'), true],
    'first row accepted' => [static fn (): mixed => $plan()['reader_rows'][0]['accepted'], true],
    'first row reason' => [static fn (): mixed => $plan()['reader_rows'][0]['receipt_reason'], 'reader_reopened_on_clean_current_source'],
    'first row wal zero' => [static fn (): mixed => $plan()['reader_rows'][0]['wal_frame_zero'], true],
    'first row hot journal absent' => [static fn (): mixed => $plan()['reader_rows'][0]['hot_journal_absent'], true],
    'first row shared lock' => [static fn (): mixed => $plan()['reader_rows'][0]['shared_lock_held'], true],
    'second row pages' => [static fn (): mixed => $plan()['reader_rows'][1]['observed_page_numbers'], [2, 5]],
    'blocked status' => [static fn (): mixed => $blocked(0, ['observed_wal_frame' => 2])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next238'],
    'blocked writer action' => [static fn (): mixed => $blocked(0, ['observed_wal_frame' => 2])['writer_action'], 'hold_writer_until_reopen_receipts_match'],
    'blocked reader action' => [static fn (): mixed => $blocked(0, ['observed_wal_frame' => 2])['reader_action'], 'force_reader_reopen_before_writer'],
    'blocked wal action' => [static fn (): mixed => $blocked(0, ['observed_wal_frame' => 2])['wal_action'], 'preserve_restarted_wal_without_new_frames'],
    'database path block' => [static fn (): mixed => $blocked(0, ['database_path' => '/tmp/other.sqlite'])['reader_rows'][0]['blocked_reasons'], ['reader_database_path_mismatch']],
    'wal path block' => [static fn (): mixed => $blocked(0, ['wal_path' => '/tmp/other.sqlite-wal'])['reader_rows'][0]['blocked_reasons'], ['reader_wal_path_mismatch']],
    'journal path block' => [static fn (): mixed => $blocked(0, ['journal_path' => '/tmp/other.sqlite-journal'])['reader_rows'][0]['blocked_reasons'], ['reader_journal_path_mismatch']],
    'source token block' => [static fn (): mixed => $blocked(0, ['source_token' => 'old-source'])['reader_rows'][0]['blocked_reasons'], ['reader_source_token_mismatch']],
    'generation block' => [static fn (): mixed => $blocked(0, ['generation' => 237])['reader_rows'][0]['blocked_reasons'], ['reader_generation_mismatch']],
    'database digest block' => [static fn (): mixed => $blocked(0, ['observed_database_digest' => $hash('old database')])['reader_rows'][0]['blocked_reasons'], ['reader_database_digest_mismatch']],
    'schema cookie block' => [static fn (): mixed => $blocked(0, ['observed_schema_cookie' => 1])['reader_rows'][0]['blocked_reasons'], ['reader_schema_cookie_mismatch']],
    'wal salt block' => [static fn (): mixed => $blocked(0, ['observed_wal_salt' => '0000abcd2380dcba'])['reader_rows'][0]['blocked_reasons'], ['reader_wal_salt_mismatch']],
    'wal frame block' => [static fn (): mixed => $blocked(0, ['observed_wal_frame' => 1])['reader_rows'][0]['blocked_reasons'], ['reader_wal_frame_not_zero']],
    'hot journal block' => [static fn (): mixed => $blocked(0, ['hot_journal_visible' => true])['reader_rows'][0]['blocked_reasons'], ['reader_hot_journal_visible']],
    'shared lock block' => [static fn (): mixed => $blocked(0, ['shared_lock' => false])['reader_rows'][0]['blocked_reasons'], ['reader_shared_lock_missing']],
    'dirty cache block' => [static fn (): mixed => $blocked(0, ['dirty_page_cache' => true])['reader_rows'][0]['blocked_reasons'], ['reader_dirty_page_cache_visible']],
    'wal header block' => [static fn (): mixed => $blocked(0, ['wal_header_restarted' => false])['reader_rows'][0]['blocked_reasons'], ['reader_wal_header_not_restarted']],
    'page block' => [static fn (): mixed => $blocked(0, ['observed_page_numbers' => [1, 6]])['reader_rows'][0]['blocked_reasons'], ['reader_page_not_checkpoint_covered']],
    'duplicate name block' => [static fn (): mixed => in_array('reader_reopen_receipt_name_duplicate', $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2]])['blocked_reasons'], true), true],
    'duplicate guard block' => [static fn (): mixed => $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2]])['blocked_guard_names'], ['reader_reopen_receipts_unique']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next238 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => $plan(array_replace($publication, ['status' => 'bad'])),
    'not admitted rejected' => static fn () => $plan(array_replace($publication, ['publication_admitted' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($publication, ['database_path' => ''])),
    'bad token rejected' => static fn () => $plan(array_replace($publication, ['source_token' => 'bad token'])),
    'bad generation rejected' => static fn () => $plan(array_replace($publication, ['next_writer_generation' => 0])),
    'bad digest rejected' => static fn () => $plan(array_replace($publication, ['database_digest' => 'short'])),
    'bad salt rejected' => static fn () => $plan(array_replace($publication, ['expected_wal_salt' => 'short'])),
    'bad covered pages rejected' => static fn () => $plan(array_replace($publication, ['covered_page_numbers' => [0]])),
    'bad reader name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad reader pages rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['observed_page_numbers' => [0]])]),
    'bad reader digest rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['observed_database_digest' => 'short'])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next238 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
