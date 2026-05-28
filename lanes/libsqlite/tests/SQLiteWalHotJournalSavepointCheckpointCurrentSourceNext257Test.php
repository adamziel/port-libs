<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$nextSourcePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next253',
    'next_source_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next257.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next257.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next257.sqlite-journal',
    'source_token' => 'wp-next257-checkpoint-source',
    'next_source_token' => 'wp-next257-retry-source',
    'commit_generation' => 257,
    'next_commit_generation' => 258,
    'checkpoint_frame' => 64,
    'next_checkpoint_frame' => 68,
    'database_digest' => $hash('next257 checkpoint database'),
    'wal_digest' => $hash('next257 checkpoint wal'),
    'next_database_digest' => $hash('next257 retry database'),
    'next_wal_digest' => $hash('next257 retry wal'),
    'accepted_reader_names' => ['schema-reader', 'options-reader', 'autoload-reader'],
    'written_next_pages' => [1, 2, 5, 9],
    'synced_next_frames' => [65, 66, 68],
    'operation_names' => ['admit_retry_readers_to_next_current_source_next253'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next253'],
];

$receipt = static function (string $name, string $type, string $operation, array $override = []) use ($nextSourcePlan): array {
    $paths = [
        'reader-retire' => $nextSourcePlan['database_path'],
        'wal-retain' => $nextSourcePlan['wal_path'],
        'journal-delete' => $nextSourcePlan['journal_path'],
        'savepoint-close' => $nextSourcePlan['database_path'],
        'page-cache-seal' => $nextSourcePlan['database_path'],
    ];

    return array_replace([
        'name' => $name,
        'receipt_type' => $type,
        'operation' => $operation,
        'path' => $paths[$type],
        'retired_reader_names' => [],
        'retained_pages' => [],
        'retained_frames' => [],
        'checkpoint_source_token' => $nextSourcePlan['source_token'],
        'next_source_token' => $nextSourcePlan['next_source_token'],
        'checkpoint_commit_generation' => $nextSourcePlan['commit_generation'],
        'next_commit_generation' => $nextSourcePlan['next_commit_generation'],
        'checkpoint_frame' => $nextSourcePlan['checkpoint_frame'],
        'next_checkpoint_frame' => $nextSourcePlan['next_checkpoint_frame'],
        'checkpoint_database_digest' => $nextSourcePlan['database_digest'],
        'checkpoint_wal_digest' => $nextSourcePlan['wal_digest'],
        'next_database_digest' => $nextSourcePlan['next_database_digest'],
        'next_wal_digest' => $nextSourcePlan['next_wal_digest'],
        'exclusive_lock_held' => true,
        'hot_journal_deleted' => false,
        'savepoint_closed' => false,
        'page_cache_sealed' => false,
        'io_error' => null,
    ], $override);
};

$receipts = [
    $receipt('retire-checkpoint-readers', 'reader-retire', 'retire_readers', ['retired_reader_names' => ['schema-reader', 'options-reader', 'autoload-reader']]),
    $receipt('retain-retry-wal-frames', 'wal-retain', 'retain_next_wal', ['retained_frames' => [65, 66, 68]]),
    $receipt('delete-checkpoint-hot-journal', 'journal-delete', 'delete_checkpoint_journal', ['hot_journal_deleted' => true]),
    $receipt('close-checkpoint-savepoint', 'savepoint-close', 'close_checkpoint_savepoint', ['savepoint_closed' => true]),
    $receipt('seal-retry-page-cache', 'page-cache-seal', 'seal_page_cache', ['retained_pages' => [1, 2, 5, 9], 'page_cache_sealed' => true]),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, $receipts);
$replaceReceipt = static fn (int $index, array $replace): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource(
    $nextSourcePlan,
    array_replace($receipts, [$index => array_replace($receipts[$index], $replace)])
);
$replacePlan = static fn (array $replace): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource(array_replace($nextSourcePlan, $replace), $receipts);
$withoutReceipt = static fn (int $index): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource(
    $nextSourcePlan,
    array_values(array_filter($receipts, static fn (array $_, int $key): bool => $key !== $index, ARRAY_FILTER_USE_BOTH))
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next257'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'checkpoint_source_retired_after_next_source_admission'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next253'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next257.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next257.sqlite-wal'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next257.sqlite-journal'],
    'checkpoint token' => [static fn (): mixed => $plan()['checkpoint_source_token'], 'wp-next257-checkpoint-source'],
    'next token' => [static fn (): mixed => $plan()['next_source_token'], 'wp-next257-retry-source'],
    'checkpoint generation' => [static fn (): mixed => $plan()['checkpoint_commit_generation'], 257],
    'next generation' => [static fn (): mixed => $plan()['next_commit_generation'], 258],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 64],
    'next checkpoint frame' => [static fn (): mixed => $plan()['next_checkpoint_frame'], 68],
    'checkpoint database digest' => [static fn (): mixed => $plan()['checkpoint_database_digest'], $hash('next257 checkpoint database')],
    'checkpoint wal digest' => [static fn (): mixed => $plan()['checkpoint_wal_digest'], $hash('next257 checkpoint wal')],
    'next database digest' => [static fn (): mixed => $plan()['next_database_digest'], $hash('next257 retry database')],
    'next wal digest' => [static fn (): mixed => $plan()['next_wal_digest'], $hash('next257 retry wal')],
    'old readers' => [static fn (): mixed => $plan()['old_reader_names'], ['autoload-reader', 'options-reader', 'schema-reader']],
    'receipt names' => [static fn (): mixed => $plan()['retirement_receipt_names'], ['retire-checkpoint-readers', 'retain-retry-wal-frames', 'delete-checkpoint-hot-journal', 'close-checkpoint-savepoint', 'seal-retry-page-cache']],
    'accepted names' => [static fn (): mixed => $plan()['accepted_retirement_receipt_names'], ['retire-checkpoint-readers', 'retain-retry-wal-frames', 'delete-checkpoint-hot-journal', 'close-checkpoint-savepoint', 'seal-retry-page-cache']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_retirement_receipt_names'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_retirement_receipt_names'], []],
    'receipt types' => [static fn (): mixed => $plan()['retirement_receipt_types'], ['reader-retire', 'wal-retain', 'journal-delete', 'savepoint-close', 'page-cache-seal']],
    'missing types empty' => [static fn (): mixed => $plan()['missing_retirement_receipt_types'], []],
    'retired readers' => [static fn (): mixed => $plan()['retired_reader_names'], ['autoload-reader', 'options-reader', 'schema-reader']],
    'missing retired readers empty' => [static fn (): mixed => $plan()['missing_retired_reader_names'], []],
    'retained pages' => [static fn (): mixed => $plan()['retained_next_pages'], [1, 2, 5, 9]],
    'missing retained pages empty' => [static fn (): mixed => $plan()['missing_retained_next_pages'], []],
    'retained frames' => [static fn (): mixed => $plan()['retained_next_frames'], [65, 66, 68]],
    'missing retained frames empty' => [static fn (): mixed => $plan()['missing_retained_next_frames'], []],
    'operation order' => [static fn (): mixed => $plan()['operation_order'], ['retire_readers', 'retain_next_wal', 'delete_checkpoint_journal', 'close_checkpoint_savepoint', 'seal_page_cache']],
    'order safe' => [static fn (): mixed => $plan()['retirement_order_safe'], true],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'retired bool' => [static fn (): mixed => $plan()['checkpoint_source_retired'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'retire_checkpoint_readers_for_generation_257'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'retain_next_wal_generation_68_and_discard_checkpoint_source_64'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'delete_checkpoint_hot_journal_after_reader_retirement'],
    'savepoint action' => [static fn (): mixed => $plan()['savepoint_action'], 'close_checkpoint_savepoint_after_next_source_retention'],
    'page cache action' => [static fn (): mixed => $plan()['page_cache_action'], 'seal_page_cache_to_next_source_258'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next253_next_source_admitted', 'checkpoint_retirement_receipt_names_unique', 'old_checkpoint_readers_retired', 'next_source_pages_retained', 'next_source_frames_retained', 'checkpoint_sidecars_removed', 'page_cache_sealed_to_next_source', 'checkpoint_retirement_order_safe', 'all_checkpoint_retirement_receipts_accepted']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'reader row reason' => [static fn (): mixed => $plan()['retirement_rows'][0]['receipt_reason'], 'checkpoint_retirement_receipt_matches_next_source'],
    'wal row frames' => [static fn (): mixed => $plan()['retirement_rows'][1]['retained_frames'], [65, 66, 68]],
    'journal row type' => [static fn (): mixed => $plan()['retirement_rows'][2]['receipt_type'], 'journal-delete'],
    'savepoint row operation' => [static fn (): mixed => $plan()['retirement_rows'][3]['operation'], 'close_checkpoint_savepoint'],
    'page cache row pages' => [static fn (): mixed => $plan()['retirement_rows'][4]['retained_pages'], [1, 2, 5, 9]],
    'digest length' => [static fn (): mixed => strlen($plan()['retirement_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_retry_readers_to_next_current_source_next253', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('retire_checkpoint_current_source_next257', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next253', $plan()['dependencies'], true), true],
    'dependency next257' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next257', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-hot-journal-savepoint-checkpoint-source-retirement', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next-source handoff admission'), true],
    'path mismatch blocked' => [static fn (): mixed => $replaceReceipt(0, ['path' => '/tmp/wrong.sqlite'])['blocked_reasons'], ['checkpoint_retirement_path_mismatch', 'checkpoint_reader_retirement_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'checkpoint token mismatch blocked' => [static fn (): mixed => $replaceReceipt(0, ['checkpoint_source_token' => 'old-source'])['blocked_reasons'], ['checkpoint_source_token_mismatch', 'checkpoint_reader_retirement_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'next token mismatch blocked' => [static fn (): mixed => $replaceReceipt(1, ['next_source_token' => 'wrong-next'])['blocked_reasons'], ['checkpoint_next_source_token_mismatch', 'next_source_frame_retention_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'checkpoint generation mismatch blocked' => [static fn (): mixed => $replaceReceipt(0, ['checkpoint_commit_generation' => 256])['blocked_reasons'], ['checkpoint_generation_mismatch', 'checkpoint_reader_retirement_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'next generation mismatch blocked' => [static fn (): mixed => $replaceReceipt(4, ['next_commit_generation' => 257])['blocked_reasons'], ['checkpoint_next_generation_mismatch', 'next_source_page_retention_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'checkpoint frame mismatch blocked' => [static fn (): mixed => $replaceReceipt(2, ['checkpoint_frame' => 63])['blocked_reasons'], ['checkpoint_frame_mismatch', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'next frame mismatch blocked' => [static fn (): mixed => $replaceReceipt(1, ['next_checkpoint_frame' => 67])['blocked_reasons'], ['checkpoint_next_frame_mismatch', 'next_source_frame_retention_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'checkpoint database digest blocked' => [static fn (): mixed => $replaceReceipt(0, ['checkpoint_database_digest' => $hash('bad database')])['blocked_reasons'], ['checkpoint_database_digest_mismatch', 'checkpoint_reader_retirement_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'checkpoint wal digest blocked' => [static fn (): mixed => $replaceReceipt(1, ['checkpoint_wal_digest' => $hash('bad wal')])['blocked_reasons'], ['checkpoint_wal_digest_mismatch', 'next_source_frame_retention_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'next database digest blocked' => [static fn (): mixed => $replaceReceipt(4, ['next_database_digest' => $hash('bad next database')])['blocked_reasons'], ['checkpoint_next_database_digest_mismatch', 'next_source_page_retention_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'next wal digest blocked' => [static fn (): mixed => $replaceReceipt(1, ['next_wal_digest' => $hash('bad next wal')])['blocked_reasons'], ['checkpoint_next_wal_digest_mismatch', 'next_source_frame_retention_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'lock missing blocked' => [static fn (): mixed => $replaceReceipt(0, ['exclusive_lock_held' => false])['blocked_reasons'], ['checkpoint_retirement_exclusive_lock_missing', 'checkpoint_reader_retirement_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'io error blocked' => [static fn (): mixed => $replaceReceipt(1, ['io_error' => 'SQLITE_IOERR_FSYNC'])['blocked_reasons'], ['checkpoint_retirement_io_error', 'next_source_frame_retention_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'missing reader blocked' => [static fn (): mixed => $replaceReceipt(0, ['retired_reader_names' => ['schema-reader', 'options-reader']])['missing_retired_reader_names'], ['autoload-reader']],
    'bad reader blocked' => [static fn (): mixed => $replaceReceipt(0, ['retired_reader_names' => ['schema-reader', 'bad-reader']])['blocked_reasons'], ['checkpoint_reader_retirement_invalid', 'checkpoint_reader_retirement_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'missing frame blocked' => [static fn (): mixed => $replaceReceipt(1, ['retained_frames' => [65, 66]])['missing_retained_next_frames'], [68]],
    'journal delete blocked' => [static fn (): mixed => $replaceReceipt(2, ['hot_journal_deleted' => false])['blocked_reasons'], ['checkpoint_hot_journal_delete_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'savepoint close blocked' => [static fn (): mixed => $replaceReceipt(3, ['savepoint_closed' => false])['blocked_reasons'], ['checkpoint_savepoint_close_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'page cache seal blocked' => [static fn (): mixed => $replaceReceipt(4, ['page_cache_sealed' => false])['blocked_reasons'], ['checkpoint_page_cache_seal_missing', 'next_source_page_retention_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'missing page blocked' => [static fn (): mixed => $replaceReceipt(4, ['retained_pages' => [1, 2, 5], 'page_cache_sealed' => true])['missing_retained_next_pages'], [9]],
    'missing wal receipt blocked' => [static fn (): mixed => $withoutReceipt(1)['blocked_reasons'], ['next_source_frame_retention_missing', 'checkpoint_retirement_receipt_type_missing', 'checkpoint_retirement_order_unsafe']],
    'missing journal type' => [static fn (): mixed => $withoutReceipt(2)['missing_retirement_receipt_types'], ['journal-delete']],
    'unsafe order blocked' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, array_merge([$receipts[2]], array_slice($receipts, 0, 2), array_slice($receipts, 3)))['blocked_guard_names'], ['checkpoint_retirement_order_safe']],
    'duplicate receipt blocked' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, array_replace($receipts, [1 => array_replace($receipts[1], ['name' => 'retire-checkpoint-readers'])]))['duplicate_retirement_receipt_names'], ['retire-checkpoint-readers']],
    'blocked status' => [static fn (): mixed => $replaceReceipt(1, ['retained_frames' => [65]])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next257'],
    'blocked reason' => [static fn (): mixed => $replaceReceipt(1, ['retained_frames' => [65]])['reason'], 'checkpoint_source_retirement_held_after_next_source_admission'],
    'blocked reader action' => [static fn (): mixed => $replaceReceipt(0, ['retired_reader_names' => ['schema-reader']])['reader_action'], 'hold_checkpoint_readers_until_retirement_receipts_match'],
    'blocked wal action' => [static fn (): mixed => $replaceReceipt(1, ['retained_frames' => [65]])['wal_action'], 'keep_checkpoint_wal_source_available'],
    'blocked journal action' => [static fn (): mixed => $replaceReceipt(2, ['hot_journal_deleted' => false])['journal_action'], 'retain_checkpoint_hot_journal_fence'],
    'blocked savepoint action' => [static fn (): mixed => $replaceReceipt(3, ['savepoint_closed' => false])['savepoint_action'], 'keep_checkpoint_savepoint_replayable'],
    'blocked page cache action' => [static fn (): mixed => $replaceReceipt(4, ['page_cache_sealed' => false])['page_cache_action'], 'discard_unsealed_page_cache_retirement'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next257 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource(array_replace($nextSourcePlan, ['status' => 'bad']), $receipts),
    'not admitted rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource(array_replace($nextSourcePlan, ['next_source_admitted' => false]), $receipts),
    'empty receipts rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, []),
    'bad database path rejected' => static fn () => $replacePlan(['database_path' => '']),
    'bad wal path rejected' => static fn () => $replacePlan(['wal_path' => '']),
    'bad journal path rejected' => static fn () => $replacePlan(['journal_path' => '']),
    'bad checkpoint token rejected' => static fn () => $replacePlan(['source_token' => 'bad token']),
    'bad next token rejected' => static fn () => $replacePlan(['next_source_token' => 'bad token']),
    'bad checkpoint generation rejected' => static fn () => $replacePlan(['commit_generation' => 0]),
    'non advancing next generation rejected' => static fn () => $replacePlan(['next_commit_generation' => 257]),
    'bad checkpoint frame rejected' => static fn () => $replacePlan(['checkpoint_frame' => -1]),
    'bad next frame rejected' => static fn () => $replacePlan(['next_checkpoint_frame' => -1]),
    'bad database digest rejected' => static fn () => $replacePlan(['database_digest' => 'short']),
    'bad wal digest rejected' => static fn () => $replacePlan(['wal_digest' => 'short']),
    'bad next database digest rejected' => static fn () => $replacePlan(['next_database_digest' => 'short']),
    'bad next wal digest rejected' => static fn () => $replacePlan(['next_wal_digest' => 'short']),
    'bad old readers rejected' => static fn () => $replacePlan(['accepted_reader_names' => []]),
    'bad pages rejected' => static fn () => $replacePlan(['written_next_pages' => []]),
    'bad frames rejected' => static fn () => $replacePlan(['synced_next_frames' => [0]]),
    'bad receipt name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt type rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, [array_replace($receipts[0], ['receipt_type' => 'wal'])]),
    'bad operation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, [array_replace($receipts[0], ['operation' => 'delete'])]),
    'bad receipt path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, [array_replace($receipts[0], ['path' => ''])]),
    'bad retired readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, [array_replace($receipts[0], ['retired_reader_names' => ['bad reader']])]),
    'bad retained pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, [array_replace($receipts[4], ['retained_pages' => [0]])]),
    'bad retained frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, [array_replace($receipts[1], ['retained_frames' => ['bad']])]),
    'bad receipt checkpoint token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, [array_replace($receipts[0], ['checkpoint_source_token' => 'bad token'])]),
    'bad receipt next token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, [array_replace($receipts[0], ['next_source_token' => 'bad token'])]),
    'bad receipt generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, [array_replace($receipts[0], ['checkpoint_commit_generation' => 0])]),
    'bad receipt digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Plan::retireCheckpointSource($nextSourcePlan, [array_replace($receipts[0], ['next_wal_digest' => 'short'])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next257 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
