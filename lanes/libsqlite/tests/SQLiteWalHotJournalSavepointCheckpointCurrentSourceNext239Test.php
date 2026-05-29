<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next239 atomic checkpoint image');
$finalizerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next236',
    'next_writer_allowed' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next239.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next239.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next239.sqlite-journal',
    'source_token' => 'wp-next239-current-source',
    'current_writer_generation' => 239,
    'next_writer_generation' => 240,
    'schema_cookie' => 23977,
    'database_digest' => $databaseDigest,
    'finalized_statement_names' => ['select-schema', 'select-options', 'select-option-name-index'],
    'operation_names' => ['admit_next_wal_writer_after_checkpoint_finalizers_next236'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next236'],
];

$receipt = static function (string $name, string $kind, array $statements, array $override = []) use ($finalizerPlan, $databaseDigest): array {
    $path = match ($kind) {
        'database' => $finalizerPlan['database_path'],
        'wal' => $finalizerPlan['wal_path'],
        'journal' => $finalizerPlan['journal_path'],
        'directory' => dirname($finalizerPlan['database_path']),
    };

    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'path' => $path,
        'statement_names' => $statements,
        'source_token' => $finalizerPlan['source_token'],
        'current_generation' => $finalizerPlan['current_writer_generation'],
        'next_generation' => $finalizerPlan['next_writer_generation'],
        'schema_cookie' => $finalizerPlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'exclusive_lock_held' => true,
        'fsync_complete' => true,
        'page_images_written' => $kind === 'database',
        'header_cookie_persisted' => $kind === 'database',
        'mx_frame' => $kind === 'wal' ? 0 : null,
        'readmarks_reset' => $kind === 'wal',
        'hot_journal_deleted' => $kind === 'journal',
        'persisted_paths' => [$finalizerPlan['database_path'], $finalizerPlan['wal_path'], $finalizerPlan['journal_path']],
    ], $override);
};

$receipts = [
    $receipt('wp-next239-database', 'database', ['select-schema', 'select-options']),
    $receipt('wp-next239-wal', 'wal', ['select-options', 'select-option-name-index']),
    $receipt('wp-next239-journal', 'journal', ['select-schema']),
    $receipt('wp-next239-directory', 'directory', ['select-schema', 'select-options', 'select-option-name-index']),
];
$plan = static fn (?array $inputPlan = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next239AdmitAtomicCommitBarrier($inputPlan ?? $finalizerPlan, $inputReceipts ?? $receipts);
$blockedReceipt = static fn (string $kind, array $override): array => $plan(null, array_map(
    static fn (array $row): array => $row['kind'] === $kind ? array_replace($row, $override) : $row,
    $receipts
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next239'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'atomic_commit_barrier_admits_checkpoint_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next236'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next239.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next239.sqlite-wal'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next239.sqlite-journal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next239-current-source'],
    'current generation' => [static fn (): mixed => $plan()['current_writer_generation'], 239],
    'next generation' => [static fn (): mixed => $plan()['next_writer_generation'], 240],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 23977],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'expected statements' => [static fn (): mixed => $plan()['expected_statement_names'], ['select-option-name-index', 'select-options', 'select-schema']],
    'covered statements' => [static fn (): mixed => $plan()['covered_statement_names'], ['select-option-name-index', 'select-options', 'select-schema']],
    'missing statements empty' => [static fn (): mixed => $plan()['missing_statement_names'], []],
    'commit kinds' => [static fn (): mixed => $plan()['commit_kinds'], ['database', 'directory', 'journal', 'wal']],
    'missing kinds empty' => [static fn (): mixed => $plan()['missing_commit_kinds'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_commit_names'], []],
    'accepted commits' => [static fn (): mixed => $plan()['accepted_commit_names'], ['wp-next239-database', 'wp-next239-wal', 'wp-next239-journal', 'wp-next239-directory']],
    'blocked commits empty' => [static fn (): mixed => $plan()['blocked_commit_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_commit_reasons'], []],
    'current source admitted' => [static fn (): mixed => $plan()['current_source_admitted'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'publish_atomic_current_source_to_reopened_readers'],
    'writer action' => [static fn (): mixed => $plan()['writer_action'], 'start_next_writer_generation_240'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'forget_hot_journal_after_atomic_directory_sync'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'reuse_restarted_wal_after_atomic_commit_barrier'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next236_finalizers_admitted', 'database_wal_journal_directory_commit_receipts_present', 'commit_receipt_names_unique', 'all_finalized_statements_covered', 'all_commit_receipts_match_generation_and_digest']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'barrier digest length' => [static fn (): mixed => strlen($plan()['barrier_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_next_wal_writer_after_checkpoint_finalizers_next236', $plan()['operation_names'], true), true],
    'operation admit' => [static fn (): mixed => in_array('admit_atomic_checkpoint_current_source_next239', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next236', $plan()['dependencies'], true), true],
    'dependency next239' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next239', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-atomic-current-source-switch', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat durable publication receipts'), true],
    'database row accepted' => [static fn (): mixed => $plan()['commit_rows'][0]['accepted'], true],
    'database row reason' => [static fn (): mixed => $plan()['commit_rows'][0]['commit_reason'], 'atomic_commit_receipt_matches_checkpoint_current_source'],
    'database row statements' => [static fn (): mixed => $plan()['commit_rows'][0]['statement_names'], ['select-options', 'select-schema']],
    'wal row statements' => [static fn (): mixed => $plan()['commit_rows'][1]['statement_names'], ['select-option-name-index', 'select-options']],
    'journal row accepted' => [static fn (): mixed => $plan()['commit_rows'][2]['accepted'], true],
    'directory row accepted' => [static fn (): mixed => $plan()['commit_rows'][3]['accepted'], true],
    'blocked status' => [static fn (): mixed => $blockedReceipt('database', ['fsync_complete' => false])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next239'],
    'blocked reason' => [static fn (): mixed => $blockedReceipt('database', ['fsync_complete' => false])['reason'], 'atomic_commit_barrier_holds_checkpoint_current_source'],
    'blocked reader action' => [static fn (): mixed => $blockedReceipt('database', ['fsync_complete' => false])['reader_action'], 'retain_previous_current_source_until_atomic_commit'],
    'blocked writer action' => [static fn (): mixed => $blockedReceipt('database', ['fsync_complete' => false])['writer_action'], 'hold_next_writer_generation_240'],
    'blocked journal action' => [static fn (): mixed => $blockedReceipt('database', ['fsync_complete' => false])['journal_action'], 'keep_hot_journal_delete_receipt_pending'],
    'blocked wal action' => [static fn (): mixed => $blockedReceipt('database', ['fsync_complete' => false])['wal_action'], 'pin_restarted_wal_until_atomic_commit'],
    'database path block' => [static fn (): mixed => $blockedReceipt('database', ['path' => '/tmp/other.sqlite'])['commit_rows'][0]['blocked_reasons'], ['atomic_commit_database_path_mismatch']],
    'database pages block' => [static fn (): mixed => $blockedReceipt('database', ['page_images_written' => false])['commit_rows'][0]['blocked_reasons'], ['atomic_commit_database_pages_not_written']],
    'database cookie block' => [static fn (): mixed => $blockedReceipt('database', ['header_cookie_persisted' => false])['commit_rows'][0]['blocked_reasons'], ['atomic_commit_schema_cookie_not_persisted']],
    'wal path block' => [static fn (): mixed => $blockedReceipt('wal', ['path' => '/tmp/other.sqlite-wal'])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_wal_path_mismatch']],
    'wal frame block' => [static fn (): mixed => $blockedReceipt('wal', ['mx_frame' => 4])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_wal_not_reset']],
    'wal readmark block' => [static fn (): mixed => $blockedReceipt('wal', ['readmarks_reset' => false])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_readmarks_not_reset']],
    'journal path block' => [static fn (): mixed => $blockedReceipt('journal', ['path' => '/tmp/other-journal'])['commit_rows'][2]['blocked_reasons'], ['atomic_commit_journal_path_mismatch']],
    'journal delete block' => [static fn (): mixed => $blockedReceipt('journal', ['hot_journal_deleted' => false])['commit_rows'][2]['blocked_reasons'], ['atomic_commit_hot_journal_delete_missing']],
    'directory path block' => [static fn (): mixed => $blockedReceipt('directory', ['path' => '/tmp'])['commit_rows'][3]['blocked_reasons'], ['atomic_commit_directory_path_mismatch']],
    'directory persisted block' => [static fn (): mixed => $blockedReceipt('directory', ['persisted_paths' => [$finalizerPlan['database_path']]])['commit_rows'][3]['blocked_reasons'], ['atomic_commit_directory_missing_path']],
    'token block' => [static fn (): mixed => $blockedReceipt('wal', ['source_token' => 'old-source'])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_source_token_mismatch']],
    'current generation block' => [static fn (): mixed => $blockedReceipt('wal', ['current_generation' => 238])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_current_generation_mismatch']],
    'next generation block' => [static fn (): mixed => $blockedReceipt('wal', ['next_generation' => 241])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_next_generation_mismatch']],
    'schema block' => [static fn (): mixed => $blockedReceipt('wal', ['schema_cookie' => 77])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_schema_cookie_mismatch']],
    'digest block' => [static fn (): mixed => $blockedReceipt('wal', ['database_digest' => $hash('old database')])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_database_digest_mismatch']],
    'lock block' => [static fn (): mixed => $blockedReceipt('wal', ['exclusive_lock_held' => false])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_exclusive_lock_missing']],
    'fsync block' => [static fn (): mixed => $blockedReceipt('wal', ['fsync_complete' => false])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_fsync_missing']],
    'statement block' => [static fn (): mixed => $blockedReceipt('wal', ['statement_names' => ['not-finalized']])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_statement_not_finalized']],
    'combined block' => [static fn (): mixed => $blockedReceipt('wal', ['source_token' => 'old-source', 'fsync_complete' => false, 'readmarks_reset' => false])['commit_rows'][1]['blocked_reasons'], ['atomic_commit_source_token_mismatch', 'atomic_commit_fsync_missing', 'atomic_commit_readmarks_not_reset']],
    'missing kind reason' => [static fn (): mixed => $plan(null, array_slice($receipts, 0, 3))['blocked_commit_reasons'], ['atomic_commit_receipt_kind_missing']],
    'missing kind guard' => [static fn (): mixed => $plan(null, array_slice($receipts, 0, 3))['blocked_guard_names'], ['database_wal_journal_directory_commit_receipts_present']],
    'duplicate name reason' => [static fn (): mixed => in_array('atomic_commit_receipt_name_duplicate', $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2], $receipts[3]])['blocked_commit_reasons'], true), true],
    'missing statement reason' => [static fn (): mixed => in_array('atomic_commit_finalized_statement_missing', $plan(null, [
        $receipt('wp-next239-database-short', 'database', ['select-schema', 'select-options']),
        $receipt('wp-next239-wal-short', 'wal', ['select-options']),
        $receipt('wp-next239-journal-short', 'journal', ['select-schema']),
        $receipt('wp-next239-directory-short', 'directory', ['select-schema', 'select-options']),
    ])['blocked_commit_reasons'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next239 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => $plan(array_replace($finalizerPlan, ['status' => 'bad'])),
    'not allowed rejected' => static fn () => $plan(array_replace($finalizerPlan, ['next_writer_allowed' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($finalizerPlan, ['database_path' => ''])),
    'bad token rejected' => static fn () => $plan(array_replace($finalizerPlan, ['source_token' => 'bad token'])),
    'bad current generation rejected' => static fn () => $plan(array_replace($finalizerPlan, ['current_writer_generation' => 0])),
    'stale next generation rejected' => static fn () => $plan(array_replace($finalizerPlan, ['next_writer_generation' => 239])),
    'bad schema rejected' => static fn () => $plan(array_replace($finalizerPlan, ['schema_cookie' => 0])),
    'bad digest rejected' => static fn () => $plan(array_replace($finalizerPlan, ['database_digest' => 'short'])),
    'bad statements rejected' => static fn () => $plan(array_replace($finalizerPlan, ['finalized_statement_names' => []])),
    'bad receipt kind rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['kind' => 'bad'])]),
    'bad receipt name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt path rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['path' => ''])]),
    'bad receipt statement rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['statement_names' => ['bad statement']])]),
    'bad receipt digest rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['database_digest' => 'short'])]),
    'bad persisted paths rejected' => static fn () => $plan(null, [array_replace($receipts[3], ['persisted_paths' => []])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next239 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
