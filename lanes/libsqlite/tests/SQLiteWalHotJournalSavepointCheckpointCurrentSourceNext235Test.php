<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next235 database image after checkpoint publication');
$walDigest = $hash('next235 restarted wal after checkpoint publication');
$readerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next232',
    'current_source_readable' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next235.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next235.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next235.sqlite-journal',
    'source_token' => 'wp-next235-current-source',
    'next_writer_generation' => 235,
    'database_digest' => $databaseDigest,
    'expected_schema_cookie' => 23577,
    'expected_wal_salt' => '2350abcd2350dcba',
    'covered_page_numbers' => [1, 2, 3, 4],
    'operation_names' => ['admit_reopened_checkpoint_reader_slots_next232'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next232'],
];
$receipt = static function (string $name, string $kind, array $overrides = []) use ($readerPlan, $databaseDigest, $walDigest): array {
    $path = match ($kind) {
        'database' => $readerPlan['database_path'],
        'wal' => $readerPlan['wal_path'],
        'journal' => $readerPlan['journal_path'],
        'directory' => dirname($readerPlan['database_path']),
    };

    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'path' => $path,
        'source_token' => $readerPlan['source_token'],
        'generation' => $readerPlan['next_writer_generation'],
        'schema_cookie' => $readerPlan['expected_schema_cookie'],
        'wal_salt' => $readerPlan['expected_wal_salt'],
        'digest' => $kind === 'database' ? $databaseDigest : $walDigest,
        'page_numbers' => [1, 2, 3, 4],
        'lock_receipt' => true,
        'synced' => in_array($kind, ['database', 'wal'], true),
        'truncated' => $kind === 'database',
        'deleted' => $kind === 'journal',
        'hot_journal_visible' => false,
        'read_mark_frame' => $kind === 'wal' ? 0 : null,
        'checkpoint_backfill_complete' => $kind === 'wal',
        'directory_synced' => $kind === 'directory',
        'persisted_paths' => [$readerPlan['database_path'], $readerPlan['wal_path'], $readerPlan['journal_path']],
    ], $overrides);
};
$receipts = [
    $receipt('wp-next235-database', 'database'),
    $receipt('wp-next235-wal', 'wal'),
    $receipt('wp-next235-journal', 'journal'),
    $receipt('wp-next235-directory', 'directory'),
];
$plan = static fn (?array $inputPlan = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next235AdmitDurablePublication($inputPlan ?? $readerPlan, $inputReceipts ?? $receipts);
$blocked = static fn (string $kind, array $overrides): array => $plan(null, array_map(
    static fn (array $row): array => $row['kind'] === $kind ? array_replace($row, $overrides) : $row,
    $receipts
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next235'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'durable_publication_receipts_admit_reopened_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next232'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $readerPlan['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $readerPlan['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $readerPlan['journal_path']],
    'source token' => [static fn (): mixed => $plan()['source_token'], $readerPlan['source_token']],
    'generation' => [static fn (): mixed => $plan()['next_writer_generation'], 235],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'schema cookie' => [static fn (): mixed => $plan()['expected_schema_cookie'], 23577],
    'wal salt' => [static fn (): mixed => $plan()['expected_wal_salt'], '2350abcd2350dcba'],
    'covered pages' => [static fn (): mixed => $plan()['covered_page_numbers'], [1, 2, 3, 4]],
    'receipt names' => [static fn (): mixed => $plan()['receipt_names'], ['wp-next235-database', 'wp-next235-wal', 'wp-next235-journal', 'wp-next235-directory']],
    'receipt kinds' => [static fn (): mixed => $plan()['receipt_kinds'], ['database', 'directory', 'journal', 'wal']],
    'missing kinds empty' => [static fn (): mixed => $plan()['missing_receipt_kinds'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_receipt_names'], []],
    'accepted names' => [static fn (): mixed => $plan()['accepted_receipt_names'], ['wp-next235-database', 'wp-next235-wal', 'wp-next235-journal', 'wp-next235-directory']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_receipt_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guards' => [static fn (): mixed => $plan()['guard_names'], ['next232_reader_slots_admitted', 'database_wal_journal_directory_receipts_present', 'publication_receipt_names_unique', 'all_publication_receipts_match_current_source', 'database_receipt_covers_checkpoint_pages', 'wal_receipt_has_reset_readmarks', 'journal_receipt_deletes_hot_journal', 'directory_receipt_fsyncs_sidecars']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'publication admitted' => [static fn (): mixed => $plan()['publication_admitted'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'keep_reopened_readers_on_durable_current_source'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'allow_restarted_wal_after_directory_sync'],
    'publication digest length' => [static fn (): mixed => strlen($plan()['publication_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_reopened_checkpoint_reader_slots_next232', $plan()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_durable_publication_receipts_current_source_next235', $plan()['operation_names'], true), true],
    'operation admit' => [static fn (): mixed => in_array('admit_durable_reopened_current_source_next235', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next232', $plan()['dependencies'], true), true],
    'dependency next235' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next235', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-hot-journal-checkpoint-durable-current-source', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'database row accepted' => [static fn (): mixed => $plan()['receipt_rows'][0]['accepted'], true],
    'database row reason' => [static fn (): mixed => $plan()['receipt_rows'][0]['receipt_reason'], 'publication_receipt_matches_durable_current_source'],
    'database row pages' => [static fn (): mixed => $plan()['receipt_rows'][0]['page_numbers'], [1, 2, 3, 4]],
    'wal readmark' => [static fn (): mixed => $plan()['receipt_rows'][1]['read_mark_frame'], 0],
    'wal backfill' => [static fn (): mixed => $plan()['receipt_rows'][1]['checkpoint_backfill_complete'], true],
    'journal deleted' => [static fn (): mixed => $plan()['receipt_rows'][2]['deleted'], true],
    'directory synced' => [static fn (): mixed => $plan()['receipt_rows'][3]['directory_synced'], true],
    'directory persisted paths' => [static fn (): mixed => $plan()['receipt_rows'][3]['persisted_paths'], [$readerPlan['database_path'], $readerPlan['wal_path'], $readerPlan['journal_path']]],
    'blocked status' => [static fn (): mixed => $blocked('database', ['synced' => false])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next235'],
    'blocked action' => [static fn (): mixed => $blocked('database', ['synced' => false])['reader_action'], 'force_reopen_after_durable_publication'],
    'database path block' => [static fn (): mixed => $blocked('database', ['path' => '/tmp/other.sqlite'])['receipt_rows'][0]['blocked_reasons'], ['database_publication_path_mismatch']],
    'database digest block' => [static fn (): mixed => $blocked('database', ['digest' => $hash('old database')])['receipt_rows'][0]['blocked_reasons'], ['database_publication_digest_mismatch']],
    'database sync block' => [static fn (): mixed => $blocked('database', ['synced' => false])['receipt_rows'][0]['blocked_reasons'], ['database_publication_not_synced']],
    'database truncate block' => [static fn (): mixed => $blocked('database', ['truncated' => false])['receipt_rows'][0]['blocked_reasons'], ['database_publication_not_truncated']],
    'wal path block' => [static fn (): mixed => $blocked('wal', ['path' => '/tmp/other.sqlite-wal'])['receipt_rows'][1]['blocked_reasons'], ['wal_publication_path_mismatch']],
    'wal digest block' => [static fn (): mixed => $blocked('wal', ['digest' => 'short'])['receipt_rows'][1]['blocked_reasons'], ['wal_publication_digest_missing']],
    'wal sync block' => [static fn (): mixed => $blocked('wal', ['synced' => false])['receipt_rows'][1]['blocked_reasons'], ['wal_publication_not_synced']],
    'wal readmark block' => [static fn (): mixed => $blocked('wal', ['read_mark_frame' => 3])['receipt_rows'][1]['blocked_reasons'], ['wal_publication_readmark_not_reset']],
    'wal backfill block' => [static fn (): mixed => $blocked('wal', ['checkpoint_backfill_complete' => false])['receipt_rows'][1]['blocked_reasons'], ['wal_publication_backfill_incomplete']],
    'journal path block' => [static fn (): mixed => $blocked('journal', ['path' => '/tmp/other-journal'])['receipt_rows'][2]['blocked_reasons'], ['journal_publication_path_mismatch']],
    'journal delete block' => [static fn (): mixed => $blocked('journal', ['deleted' => false])['receipt_rows'][2]['blocked_reasons'], ['hot_journal_delete_receipt_missing']],
    'journal visible block' => [static fn (): mixed => $blocked('journal', ['hot_journal_visible' => true])['receipt_rows'][2]['blocked_reasons'], ['hot_journal_still_visible']],
    'directory path block' => [static fn (): mixed => $blocked('directory', ['path' => '/tmp'])['receipt_rows'][3]['blocked_reasons'], ['directory_publication_path_mismatch']],
    'directory sync block' => [static fn (): mixed => $blocked('directory', ['directory_synced' => false])['receipt_rows'][3]['blocked_reasons'], ['directory_publication_not_synced']],
    'directory sidecar block' => [static fn (): mixed => $blocked('directory', ['persisted_paths' => [$readerPlan['database_path']]])['receipt_rows'][3]['blocked_reasons'], ['directory_publication_missing_sidecar']],
    'token block' => [static fn (): mixed => $blocked('wal', ['source_token' => 'old-source'])['receipt_rows'][1]['blocked_reasons'], ['publication_source_token_mismatch']],
    'generation block' => [static fn (): mixed => $blocked('wal', ['generation' => 234])['receipt_rows'][1]['blocked_reasons'], ['publication_generation_mismatch']],
    'schema block' => [static fn (): mixed => $blocked('wal', ['schema_cookie' => 1])['receipt_rows'][1]['blocked_reasons'], ['publication_schema_cookie_mismatch']],
    'salt block' => [static fn (): mixed => $blocked('wal', ['wal_salt' => '0000abcd2350dcba'])['receipt_rows'][1]['blocked_reasons'], ['publication_wal_salt_mismatch']],
    'lock block' => [static fn (): mixed => $blocked('wal', ['lock_receipt' => false])['receipt_rows'][1]['blocked_reasons'], ['publication_lock_receipt_missing']],
    'page block' => [static fn (): mixed => $blocked('database', ['page_numbers' => [1, 5]])['receipt_rows'][0]['blocked_reasons'], ['publication_page_not_checkpointed']],
    'missing kind reason' => [static fn (): mixed => $plan(null, array_slice($receipts, 0, 3))['blocked_reasons'], ['publication_receipt_kind_missing']],
    'missing kind guard' => [static fn (): mixed => $plan(null, array_slice($receipts, 0, 3))['blocked_guard_names'], ['database_wal_journal_directory_receipts_present', 'directory_receipt_fsyncs_sidecars']],
    'duplicate name reason' => [static fn (): mixed => in_array('publication_receipt_name_duplicate', $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2], $receipts[3]])['blocked_reasons'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next235 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => $plan(array_replace($readerPlan, ['status' => 'bad'])),
    'not readable rejected' => static fn () => $plan(array_replace($readerPlan, ['current_source_readable' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($readerPlan, ['database_path' => ''])),
    'bad token rejected' => static fn () => $plan(array_replace($readerPlan, ['source_token' => 'bad token'])),
    'bad generation rejected' => static fn () => $plan(array_replace($readerPlan, ['next_writer_generation' => 0])),
    'bad digest rejected' => static fn () => $plan(array_replace($readerPlan, ['database_digest' => 'short'])),
    'bad salt rejected' => static fn () => $plan(array_replace($readerPlan, ['expected_wal_salt' => 'short'])),
    'bad pages rejected' => static fn () => $plan(array_replace($readerPlan, ['covered_page_numbers' => [0]])),
    'bad receipt kind rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['kind' => 'bad'])]),
    'bad receipt page rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['page_numbers' => [0]])]),
    'bad persisted paths rejected' => static fn () => $plan(null, [$receipts[0], $receipts[1], $receipts[2], array_replace($receipts[3], ['persisted_paths' => []])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next235 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
