<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next223 checkpointed database image');
$walDigest = $digest('next223 reset wal image');
$writerDigest = $digest('next223 writer generation digest');
$oldDigest = $digest('next223 stale digest');

$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next218',
    'mode' => 'restart',
    'database_path' => '/srv/www/wp-content/database/wp-next223.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next223.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next223.sqlite-wal',
    'page_size' => 512,
    'checkpointed_frame' => 223,
    'can_reset_wal' => true,
    'database_digest' => $databaseDigest,
    'wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'next_writer_generation' => 224,
    'wal_action' => 'restart_wal_header_with_new_salt',
    'operation_names' => ['verify_restart_truncate_current_source_next218'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next218'],
];
$truncateBase = array_merge($base, ['mode' => 'truncate', 'wal_action' => 'truncate_wal_to_zero_bytes']);

$receipt = static function (string $name, string $role, array $override = []) use ($databaseDigest, $walDigest, $writerDigest): array {
    return array_replace([
        'name' => $name,
        'role' => $role,
        'checkpoint_frame' => 223,
        'writer_generation' => 224,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'sync_receipt' => true,
    ], $override);
};

$receipts = [
    $receipt('database-write-receipt', 'database'),
    $receipt('wal-reset-receipt', 'wal'),
    $receipt('hot-journal-delete-receipt', 'journal'),
    $receipt('reader-cache-reopen-receipt', 'reader-cache'),
];
$mixedReceipts = array_merge($receipts, [
    $receipt('stale-frame-receipt', 'database', ['checkpoint_frame' => 222]),
    $receipt('stale-generation-receipt', 'wal', ['writer_generation' => 223]),
    $receipt('stale-database-receipt', 'database', ['observed_database_digest' => $oldDigest]),
    $receipt('stale-wal-receipt', 'wal', ['observed_wal_digest' => $oldDigest]),
    $receipt('stale-writer-receipt', 'database', ['observed_writer_digest' => $oldDigest]),
    $receipt('hot-journal-receipt', 'journal', ['hot_journal_present' => true]),
    $receipt('savepoint-open-receipt', 'reader-cache', ['savepoint_depth' => 1]),
    $receipt('dirty-cache-receipt', 'reader-cache', ['reader_cache_dirty' => true]),
    $receipt('unsynced-receipt', 'database', ['sync_receipt' => false]),
]);
$missingReaderCacheReceipts = array_slice($receipts, 0, 3);

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($base, $receipts, 225);
$truncate = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($truncateBase, $receipts, 225);
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($base, $mixedReceipts, 225);
$missingRole = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($base, $missingReaderCacheReceipts, 225);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next223'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'checkpoint_reset_publication_receipts_advance_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next218'],
    'mode' => [static fn (): mixed => $plan()['mode'], 'restart'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next223.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next223.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next223.sqlite-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpointed_frame'], 223],
    'next source epoch' => [static fn (): mixed => $plan()['next_source_epoch'], 225],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'wal digest' => [static fn (): mixed => $plan()['wal_digest'], $walDigest],
    'writer digest' => [static fn (): mixed => $plan()['writer_digest'], $writerDigest],
    'writer generation' => [static fn (): mixed => $plan()['writer_generation'], 224],
    'admitted names' => [static fn (): mixed => $plan()['admitted_receipt_names'], ['database-write-receipt', 'wal-reset-receipt', 'hot-journal-delete-receipt', 'reader-cache-reopen-receipt']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_receipt_names'], []],
    'required roles' => [static fn (): mixed => $plan()['required_roles'], ['database', 'wal', 'journal', 'reader-cache']],
    'admitted roles' => [static fn (): mixed => $plan()['admitted_roles'], ['database', 'wal', 'journal', 'reader-cache']],
    'missing roles empty' => [static fn (): mixed => $plan()['missing_roles'], []],
    'publication allowed' => [static fn (): mixed => $plan()['publication_allowed'], true],
    'current source action' => [static fn (): mixed => $plan()['current_source_action'], 'advance_current_source_epoch_225'],
    'reader cache action' => [static fn (): mixed => $plan()['reader_cache_action'], 'drop_reopened_reader_cache_images'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'forget_hot_journal_generation_after_receipt'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'restart_wal_header_with_new_salt'],
    'first receipt reason' => [static fn (): mixed => $plan()['receipt_rows'][0]['receipt_reason'], 'receipt_can_publish_current_source'],
    'first receipt role' => [static fn (): mixed => $plan()['receipt_rows'][0]['role'], 'database'],
    'first receipt frame' => [static fn (): mixed => $plan()['receipt_rows'][0]['checkpoint_frame'], 223],
    'first expected frame' => [static fn (): mixed => $plan()['receipt_rows'][0]['expected_checkpoint_frame'], 223],
    'first receipt generation' => [static fn (): mixed => $plan()['receipt_rows'][0]['writer_generation'], 224],
    'first expected generation' => [static fn (): mixed => $plan()['receipt_rows'][0]['expected_writer_generation'], 224],
    'first receipt sync' => [static fn (): mixed => $plan()['receipt_rows'][0]['sync_receipt'], true],
    'first hot journal absent' => [static fn (): mixed => $plan()['receipt_rows'][0]['hot_journal_present'], false],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next218_reset_admitted', 'all_publication_receipts_current', 'required_publication_roles_present']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'verify_restart_truncate_current_source_next218'],
    'operation verify present' => [static fn (): mixed => in_array('verify_checkpoint_reset_publication_current_source_next223', $plan()['operation_names'], true), true],
    'operation advance present' => [static fn (): mixed => in_array('advance_checkpoint_current_source_epoch_next223', $plan()['operation_names'], true), true],
    'publication digest length' => [static fn (): mixed => strlen($plan()['publication_digest']), 64],
    'dependency next223' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next223', $plan()['dependencies'], true), true],
    'dependency receipts' => [static fn (): mixed => in_array('sqlite-checkpoint-reset-publication-receipts', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-checkpoint-current-source-publication', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next218 restart/truncate reset admission'), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal_to_zero_bytes'],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next223'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'checkpoint_reset_publication_waits_for_hot_journal_savepoint_receipts'],
    'blocked publication false' => [static fn (): mixed => $blocked()['publication_allowed'], false],
    'blocked names' => [static fn (): mixed => $blocked()['blocked_receipt_names'], ['stale-frame-receipt', 'stale-generation-receipt', 'stale-database-receipt', 'stale-wal-receipt', 'stale-writer-receipt', 'hot-journal-receipt', 'savepoint-open-receipt', 'dirty-cache-receipt', 'unsynced-receipt']],
    'stale frame reason' => [static fn (): mixed => $blocked()['receipt_rows'][4]['receipt_reason'], 'receipt_checkpoint_frame_mismatch'],
    'stale generation reason' => [static fn (): mixed => $blocked()['receipt_rows'][5]['receipt_reason'], 'receipt_writer_generation_mismatch'],
    'stale database reason' => [static fn (): mixed => $blocked()['receipt_rows'][6]['receipt_reason'], 'receipt_database_digest_mismatch'],
    'stale wal reason' => [static fn (): mixed => $blocked()['receipt_rows'][7]['receipt_reason'], 'receipt_wal_digest_mismatch'],
    'stale writer reason' => [static fn (): mixed => $blocked()['receipt_rows'][8]['receipt_reason'], 'receipt_writer_digest_mismatch'],
    'hot journal reason' => [static fn (): mixed => $blocked()['receipt_rows'][9]['receipt_reason'], 'receipt_hot_journal_still_present'],
    'savepoint reason' => [static fn (): mixed => $blocked()['receipt_rows'][10]['receipt_reason'], 'receipt_savepoint_scope_not_closed'],
    'dirty cache reason' => [static fn (): mixed => $blocked()['receipt_rows'][11]['receipt_reason'], 'receipt_reader_cache_dirty'],
    'unsynced reason' => [static fn (): mixed => $blocked()['receipt_rows'][12]['receipt_reason'], 'receipt_missing_sync_receipt'],
    'blocked reasons unique' => [static fn (): mixed => $blocked()['blocked_receipt_reasons'], ['receipt_checkpoint_frame_mismatch', 'receipt_writer_generation_mismatch', 'receipt_database_digest_mismatch', 'receipt_wal_digest_mismatch', 'receipt_writer_digest_mismatch', 'receipt_hot_journal_still_present', 'receipt_savepoint_scope_not_closed', 'receipt_reader_cache_dirty', 'receipt_missing_sync_receipt']],
    'blocked guard receipts' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['all_publication_receipts_current']],
    'blocked current action' => [static fn (): mixed => $blocked()['current_source_action'], 'preserve_previous_current_source_epoch'],
    'blocked journal action' => [static fn (): mixed => $blocked()['journal_action'], 'retain_hot_journal_generation_fence'],
    'missing role status' => [static fn (): mixed => $missingRole()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next223'],
    'missing role list' => [static fn (): mixed => $missingRole()['missing_roles'], ['reader-cache']],
    'missing role guard' => [static fn (): mixed => $missingRole()['blocked_guard_names'], ['required_publication_roles_present']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next223 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad status rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource(['status' => 'bad'], $receipts, 225),
    'reset false rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource(array_merge($base, ['can_reset_wal' => false]), $receipts, 225),
    'empty receipts rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($base, [], 225),
    'bad epoch rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($base, $receipts, 0),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource(array_merge($base, ['database_digest' => 'short']), $receipts, 225),
    'bad wal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource(array_merge($base, ['wal_digest' => 'short']), $receipts, 225),
    'bad writer digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource(array_merge($base, ['writer_digest' => 'short']), $receipts, 225),
    'bad checkpoint frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource(array_merge($base, ['checkpointed_frame' => 0]), $receipts, 225),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource(array_merge($base, ['next_writer_generation' => 0]), $receipts, 225),
    'bad mode rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource(array_merge($base, ['mode' => 'passive']), $receipts, 225),
    'missing name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($base, [array_merge($receipts[0], ['name' => ''])], 225),
    'missing role rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($base, [array_merge($receipts[0], ['role' => ''])], 225),
    'bad receipt digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($base, [array_merge($receipts[0], ['observed_wal_digest' => 'short'])], 225),
    'bad receipt frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($base, [array_merge($receipts[0], ['checkpoint_frame' => 0])], 225),
    'bad receipt generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($base, [array_merge($receipts[0], ['writer_generation' => 0])], 225),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next223 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
