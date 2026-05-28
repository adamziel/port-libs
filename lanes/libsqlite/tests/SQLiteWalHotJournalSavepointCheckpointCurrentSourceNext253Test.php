<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$reopenPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next249',
    'reopened_current_source_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next253.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next253.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next253.sqlite-journal',
    'source_token' => 'wp-next253-checkpoint-source',
    'next_source_token' => 'wp-next253-retry-source',
    'commit_generation' => 253,
    'next_commit_generation' => 254,
    'schema_cookie' => 1253,
    'next_schema_cookie' => 1254,
    'checkpoint_frame' => 48,
    'next_checkpoint_frame' => 52,
    'database_digest' => $hash('next253 checkpoint database'),
    'next_database_digest' => $hash('next253 retry database'),
    'wal_digest' => $hash('next253 checkpoint wal'),
    'next_wal_digest' => $hash('next253 retry wal'),
    'accepted_reader_names' => ['schema-reader', 'options-reader', 'autoload-reader'],
    'next_dirty_pages' => [1, 2, 7, 11],
    'next_commit_frames' => [49, 50, 52],
    'operation_names' => ['verify_reopened_current_source_next249'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next249'],
];

$receipt = static function (string $name, string $type, string $operation, array $override = []) use ($reopenPlan): array {
    $paths = [
        'database' => $reopenPlan['database_path'],
        'wal' => $reopenPlan['wal_path'],
        'readers' => $reopenPlan['database_path'],
        'journal' => $reopenPlan['journal_path'],
        'savepoint' => $reopenPlan['database_path'],
    ];

    return array_replace([
        'name' => $name,
        'receipt_type' => $type,
        'operation' => $operation,
        'path' => $paths[$type],
        'pages' => [],
        'frames' => [],
        'reader_names' => [],
        'source_token' => $reopenPlan['next_source_token'],
        'commit_generation' => $reopenPlan['next_commit_generation'],
        'schema_cookie' => $reopenPlan['next_schema_cookie'],
        'checkpoint_frame' => $reopenPlan['next_checkpoint_frame'],
        'database_digest' => $reopenPlan['next_database_digest'],
        'wal_digest' => $reopenPlan['next_wal_digest'],
        'exclusive_lock_held' => true,
        'hot_journal_fenced' => false,
        'savepoint_released' => false,
        'io_error' => null,
    ], $override);
};

$receipts = [
    $receipt('write-retry-schema-options', 'database', 'write_database_pages', ['pages' => [1, 2]]),
    $receipt('write-retry-autoload-index', 'database', 'write_database_pages', ['pages' => [7, 11]]),
    $receipt('sync-retry-wal-frames', 'wal', 'sync_wal_frames', ['frames' => [49, 50, 52]]),
    $receipt('ack-retry-readers', 'readers', 'ack_readers', ['reader_names' => ['schema-reader', 'options-reader', 'autoload-reader']]),
    $receipt('fence-checkpoint-hot-journal', 'journal', 'fence_hot_journal', ['hot_journal_fenced' => true]),
    $receipt('release-checkpoint-savepoint', 'savepoint', 'release_savepoint', ['savepoint_released' => true]),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource($reopenPlan, $receipts);
$replaceReceipt = static fn (int $index, array $replace): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource(
    $reopenPlan,
    array_replace($receipts, [$index => array_replace($receipts[$index], $replace)])
);
$replacePlan = static fn (array $replace): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource(array_replace($reopenPlan, $replace), $receipts);
$withoutReceipt = static fn (int $index): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource(
    $reopenPlan,
    array_values(array_filter($receipts, static fn (array $_, int $key): bool => $key !== $index, ARRAY_FILTER_USE_BOTH))
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next253'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'next_source_handoff_admits_retry_readers_after_checkpoint'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next249'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next253.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next253.sqlite-wal'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next253.sqlite-journal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next253-checkpoint-source'],
    'next source token' => [static fn (): mixed => $plan()['next_source_token'], 'wp-next253-retry-source'],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 253],
    'next commit generation' => [static fn (): mixed => $plan()['next_commit_generation'], 254],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 1253],
    'next schema cookie' => [static fn (): mixed => $plan()['next_schema_cookie'], 1254],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 48],
    'next checkpoint frame' => [static fn (): mixed => $plan()['next_checkpoint_frame'], 52],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $hash('next253 checkpoint database')],
    'next database digest' => [static fn (): mixed => $plan()['next_database_digest'], $hash('next253 retry database')],
    'wal digest' => [static fn (): mixed => $plan()['wal_digest'], $hash('next253 checkpoint wal')],
    'next wal digest' => [static fn (): mixed => $plan()['next_wal_digest'], $hash('next253 retry wal')],
    'reader names' => [static fn (): mixed => $plan()['accepted_reader_names'], ['autoload-reader', 'options-reader', 'schema-reader']],
    'dirty pages' => [static fn (): mixed => $plan()['next_dirty_pages'], [1, 2, 7, 11]],
    'commit frames' => [static fn (): mixed => $plan()['next_commit_frames'], [49, 50, 52]],
    'receipt names' => [static fn (): mixed => $plan()['receipt_names'], ['write-retry-schema-options', 'write-retry-autoload-index', 'sync-retry-wal-frames', 'ack-retry-readers', 'fence-checkpoint-hot-journal', 'release-checkpoint-savepoint']],
    'accepted names' => [static fn (): mixed => $plan()['accepted_receipt_names'], ['write-retry-schema-options', 'write-retry-autoload-index', 'sync-retry-wal-frames', 'ack-retry-readers', 'fence-checkpoint-hot-journal', 'release-checkpoint-savepoint']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_receipt_names'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_receipt_names'], []],
    'receipt types' => [static fn (): mixed => $plan()['receipt_types'], ['database', 'wal', 'readers', 'journal', 'savepoint']],
    'missing types empty' => [static fn (): mixed => $plan()['missing_receipt_types'], []],
    'written pages' => [static fn (): mixed => $plan()['written_next_pages'], [1, 2, 7, 11]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_next_pages'], []],
    'synced frames' => [static fn (): mixed => $plan()['synced_next_frames'], [49, 50, 52]],
    'missing frames empty' => [static fn (): mixed => $plan()['missing_next_frames'], []],
    'ack readers' => [static fn (): mixed => $plan()['acknowledged_reader_names'], ['autoload-reader', 'options-reader', 'schema-reader']],
    'missing readers empty' => [static fn (): mixed => $plan()['missing_reader_names'], []],
    'operation order' => [static fn (): mixed => $plan()['operation_order'], ['write_database_pages', 'write_database_pages', 'sync_wal_frames', 'ack_readers', 'fence_hot_journal', 'release_savepoint']],
    'order safe' => [static fn (): mixed => $plan()['handoff_order_safe'], true],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'admitted' => [static fn (): mixed => $plan()['next_source_admitted'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'advance_retry_readers_to_next_source_254'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'publish_next_wal_generation_52'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'keep_hot_journal_fenced_for_next_source'],
    'savepoint action' => [static fn (): mixed => $plan()['savepoint_action'], 'release_checkpoint_savepoint_scope_after_next_source_ack'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next249_reopen_plan_admitted', 'next_source_generation_advances', 'next_source_receipt_names_unique', 'next_source_pages_written', 'next_source_commit_frames_synced', 'next_source_readers_acknowledged', 'hot_journal_and_savepoint_fenced', 'next_source_order_safe', 'all_next_source_receipts_accepted']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'database row accepted' => [static fn (): mixed => $plan()['receipt_rows'][0]['accepted'], true],
    'wal row frames' => [static fn (): mixed => $plan()['receipt_rows'][2]['frames'], [49, 50, 52]],
    'reader row names' => [static fn (): mixed => $plan()['receipt_rows'][3]['reader_names'], ['autoload-reader', 'options-reader', 'schema-reader']],
    'journal row reason' => [static fn (): mixed => $plan()['receipt_rows'][4]['receipt_reason'], 'next_source_receipt_matches_checkpoint_handoff'],
    'savepoint row operation' => [static fn (): mixed => $plan()['receipt_rows'][5]['operation'], 'release_savepoint'],
    'digest length' => [static fn (): mixed => strlen($plan()['handoff_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('verify_reopened_current_source_next249', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_retry_readers_to_next_current_source_next253', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next249', $plan()['dependencies'], true), true],
    'dependency next253' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next253', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-hot-journal-savepoint-checkpoint-retry-source', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat durable VFS receipt ordering'), true],
    'path mismatch blocked' => [static fn (): mixed => $replaceReceipt(0, ['path' => '/tmp/wrong.sqlite'])['blocked_reasons'], ['next_source_path_mismatch', 'next_source_dirty_page_missing']],
    'source token mismatch blocked' => [static fn (): mixed => $replaceReceipt(0, ['source_token' => 'old-source'])['blocked_reasons'], ['next_source_token_mismatch', 'next_source_dirty_page_missing']],
    'generation mismatch blocked' => [static fn (): mixed => $replaceReceipt(0, ['commit_generation' => 253])['blocked_reasons'], ['next_source_generation_mismatch', 'next_source_dirty_page_missing']],
    'schema mismatch blocked' => [static fn (): mixed => $replaceReceipt(0, ['schema_cookie' => 1253])['blocked_reasons'], ['next_source_schema_cookie_mismatch', 'next_source_dirty_page_missing']],
    'checkpoint mismatch blocked' => [static fn (): mixed => $replaceReceipt(2, ['checkpoint_frame' => 48])['blocked_reasons'], ['next_source_checkpoint_frame_mismatch', 'next_source_commit_frame_missing', 'next_source_receipt_type_missing', 'next_source_handoff_order_unsafe']],
    'database digest blocked' => [static fn (): mixed => $replaceReceipt(0, ['database_digest' => $hash('old database')])['blocked_reasons'], ['next_source_database_digest_mismatch', 'next_source_dirty_page_missing']],
    'wal digest blocked' => [static fn (): mixed => $replaceReceipt(2, ['wal_digest' => $hash('old wal')])['blocked_reasons'], ['next_source_wal_digest_mismatch', 'next_source_commit_frame_missing', 'next_source_receipt_type_missing', 'next_source_handoff_order_unsafe']],
    'lock missing blocked' => [static fn (): mixed => $replaceReceipt(0, ['exclusive_lock_held' => false])['blocked_reasons'], ['next_source_exclusive_lock_missing', 'next_source_dirty_page_missing']],
    'io error blocked' => [static fn (): mixed => $replaceReceipt(2, ['io_error' => 'SQLITE_IOERR_FSYNC'])['blocked_reasons'], ['next_source_io_error', 'next_source_commit_frame_missing', 'next_source_receipt_type_missing', 'next_source_handoff_order_unsafe']],
    'missing page blocked' => [static fn (): mixed => $replaceReceipt(1, ['pages' => [7]])['missing_next_pages'], [11]],
    'missing frame blocked' => [static fn (): mixed => $replaceReceipt(2, ['frames' => [49, 50]])['missing_next_frames'], [52]],
    'missing reader blocked' => [static fn (): mixed => $replaceReceipt(3, ['reader_names' => ['schema-reader', 'options-reader']])['missing_reader_names'], ['autoload-reader']],
    'bad reader blocked' => [static fn (): mixed => $replaceReceipt(3, ['reader_names' => ['schema-reader', 'bad-reader']])['blocked_reasons'], ['next_source_reader_receipt_invalid', 'next_source_reader_ack_missing', 'next_source_receipt_type_missing', 'next_source_handoff_order_unsafe']],
    'journal fence blocked' => [static fn (): mixed => $replaceReceipt(4, ['hot_journal_fenced' => false])['blocked_reasons'], ['next_source_hot_journal_fence_missing', 'next_source_receipt_type_missing', 'next_source_handoff_order_unsafe']],
    'savepoint release blocked' => [static fn (): mixed => $replaceReceipt(5, ['savepoint_released' => false])['blocked_reasons'], ['next_source_savepoint_release_missing', 'next_source_receipt_type_missing', 'next_source_handoff_order_unsafe']],
    'missing wal receipt blocked' => [static fn (): mixed => $withoutReceipt(2)['blocked_reasons'], ['next_source_commit_frame_missing', 'next_source_receipt_type_missing', 'next_source_handoff_order_unsafe']],
    'missing journal receipt blocked' => [static fn (): mixed => $withoutReceipt(4)['missing_receipt_types'], ['journal']],
    'unsafe order blocked' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource($reopenPlan, array_merge([$receipts[3]], array_slice($receipts, 0, 3), array_slice($receipts, 4)))['blocked_guard_names'], ['next_source_order_safe']],
    'duplicate receipt blocked' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource($reopenPlan, array_replace($receipts, [1 => array_replace($receipts[1], ['name' => 'write-retry-schema-options'])]))['duplicate_receipt_names'], ['write-retry-schema-options']],
    'blocked status' => [static fn (): mixed => $replaceReceipt(0, ['path' => '/tmp/wrong.sqlite'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next253'],
    'blocked reason' => [static fn (): mixed => $replaceReceipt(0, ['path' => '/tmp/wrong.sqlite'])['reason'], 'next_source_handoff_holds_retry_readers_after_checkpoint'],
    'blocked reader action' => [static fn (): mixed => $replaceReceipt(0, ['path' => '/tmp/wrong.sqlite'])['reader_action'], 'hold_retry_readers_on_checkpoint_source_253'],
    'blocked wal action' => [static fn (): mixed => $replaceReceipt(2, ['frames' => [49]])['wal_action'], 'retain_checkpoint_wal_generation_48'],
    'blocked journal action' => [static fn (): mixed => $replaceReceipt(4, ['hot_journal_fenced' => false])['journal_action'], 'preserve_hot_journal_recovery_fence'],
    'blocked savepoint action' => [static fn (): mixed => $replaceReceipt(5, ['savepoint_released' => false])['savepoint_action'], 'keep_checkpoint_savepoint_scope_replayable'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next253 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource(array_replace($reopenPlan, ['status' => 'bad']), $receipts),
    'not admitted rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource(array_replace($reopenPlan, ['reopened_current_source_admitted' => false]), $receipts),
    'empty receipts rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource($reopenPlan, []),
    'bad database path rejected' => static fn () => $replacePlan(['database_path' => '']),
    'bad wal path rejected' => static fn () => $replacePlan(['wal_path' => '']),
    'bad journal path rejected' => static fn () => $replacePlan(['journal_path' => '']),
    'bad token rejected' => static fn () => $replacePlan(['source_token' => 'bad token']),
    'same next token rejected' => static fn () => $replacePlan(['next_source_token' => 'wp-next253-checkpoint-source']),
    'bad generation rejected' => static fn () => $replacePlan(['commit_generation' => 0]),
    'non advancing generation rejected' => static fn () => $replacePlan(['next_commit_generation' => 253]),
    'bad schema rejected' => static fn () => $replacePlan(['schema_cookie' => 0]),
    'bad next schema rejected' => static fn () => $replacePlan(['next_schema_cookie' => 0]),
    'bad checkpoint rejected' => static fn () => $replacePlan(['checkpoint_frame' => -1]),
    'regressing checkpoint rejected' => static fn () => $replacePlan(['next_checkpoint_frame' => 47]),
    'bad database digest rejected' => static fn () => $replacePlan(['database_digest' => 'short']),
    'bad next database digest rejected' => static fn () => $replacePlan(['next_database_digest' => 'short']),
    'bad wal digest rejected' => static fn () => $replacePlan(['wal_digest' => 'short']),
    'bad next wal digest rejected' => static fn () => $replacePlan(['next_wal_digest' => 'short']),
    'bad readers rejected' => static fn () => $replacePlan(['accepted_reader_names' => []]),
    'bad dirty pages rejected' => static fn () => $replacePlan(['next_dirty_pages' => []]),
    'bad commit frames rejected' => static fn () => $replacePlan(['next_commit_frames' => [0]]),
    'bad receipt name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource($reopenPlan, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt type rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource($reopenPlan, [array_replace($receipts[0], ['receipt_type' => 'temp'])]),
    'bad operation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource($reopenPlan, [array_replace($receipts[0], ['operation' => 'delete'])]),
    'bad receipt path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource($reopenPlan, [array_replace($receipts[0], ['path' => ''])]),
    'bad receipt pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource($reopenPlan, [array_replace($receipts[0], ['pages' => [0]])]),
    'bad receipt frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource($reopenPlan, [array_replace($receipts[2], ['frames' => ['bad']])]),
    'bad receipt readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Plan::admitNextCurrentSource($reopenPlan, [array_replace($receipts[3], ['reader_names' => ['bad reader']])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next253 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
