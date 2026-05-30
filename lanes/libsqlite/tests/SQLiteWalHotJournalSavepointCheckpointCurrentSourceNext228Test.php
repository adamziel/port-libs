<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next228 durable checkpointed database');
$sourceToken = 'next228:durable-checkpoint-source';

$publication = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next224',
    'publication_allowed' => true,
    'checkpoint_reset_visible' => true,
    'mode' => 'truncate',
    'source_token' => $sourceToken,
    'next_writer_generation' => 228,
    'database_digest' => $databaseDigest,
    'operation_names' => ['publish_checkpoint_reset_current_source_next224'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next224'],
];

$barriers = [
    [
        'name' => 'main-database-fsync',
        'type' => 'database_sync',
        'source_token' => $sourceToken,
        'generation' => 228,
        'sync_order' => 1,
        'receipt' => true,
        'exclusive_lock' => true,
        'database_digest' => $databaseDigest,
    ],
    [
        'name' => 'truncate-wal-fsync',
        'type' => 'wal_reset_sync',
        'source_token' => $sourceToken,
        'generation' => 228,
        'sync_order' => 2,
        'receipt' => true,
        'exclusive_lock' => true,
        'mode' => 'truncate',
        'wal_reset' => true,
    ],
    [
        'name' => 'hot-journal-directory-fsync',
        'type' => 'journal_unlink_dir_sync',
        'source_token' => $sourceToken,
        'generation' => 228,
        'sync_order' => 3,
        'receipt' => true,
        'exclusive_lock' => false,
        'journal_unlinked' => true,
    ],
    [
        'name' => 'shm-exclusive-epoch',
        'type' => 'shm_lock_epoch',
        'source_token' => $sourceToken,
        'generation' => 228,
        'sync_order' => 4,
        'receipt' => true,
        'exclusive_lock' => true,
    ],
    [
        'name' => 'plugin-savepoint-release',
        'type' => 'savepoint_release',
        'source_token' => $sourceToken,
        'generation' => 228,
        'sync_order' => 5,
        'receipt' => true,
        'exclusive_lock' => false,
        'savepoint_released' => true,
    ],
];

$readers = [
    [
        'name' => 'wp-options-reopened-reader',
        'source_token' => $sourceToken,
        'generation' => 228,
        'reopened' => true,
        'saw_durability_barrier' => true,
        'pinned_old_source' => false,
    ],
    [
        'name' => 'wp-postmeta-reopened-reader',
        'source_token' => $sourceToken,
        'generation' => 228,
        'reopened' => true,
        'saw_durability_barrier' => true,
        'pinned_old_source' => false,
    ],
];

$blockedBarriers = $barriers;
$blockedBarriers[0] = array_merge($blockedBarriers[0], ['database_digest' => $digest('stale database'), 'receipt' => false]);
$blockedBarriers[1] = array_merge($blockedBarriers[1], ['mode' => 'restart', 'wal_reset' => false]);
$blockedBarriers[2] = array_merge($blockedBarriers[2], ['journal_unlinked' => false]);
$blockedBarriers[3] = array_merge($blockedBarriers[3], ['generation' => 227, 'exclusive_lock' => false]);
$blockedBarriers[4] = array_merge($blockedBarriers[4], ['source_token' => 'next224:old-source', 'sync_order' => 0, 'savepoint_released' => false]);

$blockedReaders = array_merge($readers, [
    [
        'name' => 'old-pinned-reader',
        'source_token' => 'next224:old-source',
        'generation' => 227,
        'reopened' => false,
        'saw_durability_barrier' => false,
        'pinned_old_source' => true,
    ],
    [
        'name' => 'wrong-generation-reader',
        'source_token' => $sourceToken,
        'generation' => 227,
        'reopened' => true,
        'saw_durability_barrier' => true,
        'pinned_old_source' => false,
    ],
]);

$admitted = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, $barriers, $readers, $sourceToken);
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, $blockedBarriers, $blockedReaders, $sourceToken);
$missing = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, array_slice($barriers, 0, 4), $readers, $sourceToken);
$restart = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource(array_merge($publication, ['mode' => 'restart']), array_replace($barriers, [1 => array_merge($barriers[1], ['mode' => 'restart'])]), $readers, $sourceToken);

$cases = [
    'status' => [static fn (): mixed => $admitted()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next228'],
    'reason' => [static fn (): mixed => $admitted()['reason'], 'durable_checkpoint_source_barriers_admit_reopened_readers'],
    'base status' => [static fn (): mixed => $admitted()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next224'],
    'mode' => [static fn (): mixed => $admitted()['mode'], 'truncate'],
    'source token' => [static fn (): mixed => $admitted()['source_token'], $sourceToken],
    'writer generation' => [static fn (): mixed => $admitted()['next_writer_generation'], 228],
    'database digest' => [static fn (): mixed => $admitted()['database_digest'], $databaseDigest],
    'admitted barrier names' => [static fn (): mixed => $admitted()['admitted_barrier_names'], ['main-database-fsync', 'truncate-wal-fsync', 'hot-journal-directory-fsync', 'shm-exclusive-epoch', 'plugin-savepoint-release']],
    'blocked barriers empty' => [static fn (): mixed => $admitted()['blocked_barrier_names'], []],
    'blocked barrier reasons empty' => [static fn (): mixed => $admitted()['blocked_barrier_reasons'], []],
    'missing barriers empty' => [static fn (): mixed => $admitted()['missing_barrier_types'], []],
    'admitted reader names' => [static fn (): mixed => $admitted()['admitted_reader_names'], ['wp-options-reopened-reader', 'wp-postmeta-reopened-reader']],
    'blocked readers empty' => [static fn (): mixed => $admitted()['blocked_reader_names'], []],
    'blocked reader reasons empty' => [static fn (): mixed => $admitted()['blocked_reader_reasons'], []],
    'current source admitted' => [static fn (): mixed => $admitted()['current_source_admitted'], true],
    'checkpoint reusable' => [static fn (): mixed => $admitted()['checkpoint_reusable_by_readers'], true],
    'writer action' => [static fn (): mixed => $admitted()['next_writer_action'], 'start_after_durable_checkpoint_source'],
    'reader action' => [static fn (): mixed => $admitted()['reader_action'], 'reuse_reopened_durable_source_readers'],
    'database barrier reason' => [static fn (): mixed => $admitted()['barrier_rows'][0]['receipt_reason'], 'durability_barrier_matches_current_source'],
    'database barrier digest' => [static fn (): mixed => $admitted()['barrier_rows'][0]['database_digest'], $databaseDigest],
    'wal barrier reset observed' => [static fn (): mixed => $admitted()['barrier_rows'][1]['wal_reset'], true],
    'journal barrier unlinked' => [static fn (): mixed => $admitted()['barrier_rows'][2]['journal_unlinked'], true],
    'shm barrier exclusive' => [static fn (): mixed => $admitted()['barrier_rows'][3]['exclusive_lock'], true],
    'savepoint barrier released' => [static fn (): mixed => $admitted()['barrier_rows'][4]['savepoint_released'], true],
    'reader reason' => [static fn (): mixed => $admitted()['reader_rows'][0]['receipt_reason'], 'reader_observes_durable_current_source'],
    'reader saw barrier' => [static fn (): mixed => $admitted()['reader_rows'][0]['saw_durability_barrier'], true],
    'guard names' => [static fn (): mixed => $admitted()['guard_names'], ['next224_publication_visible', 'required_durability_barriers_present', 'durability_barriers_match_current_source', 'readers_observe_durable_source']],
    'guard matches' => [static fn (): mixed => $admitted()['guard_matches'], [true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $admitted()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $admitted()['operation_names'][0], 'publish_checkpoint_reset_current_source_next224'],
    'operation verify present' => [static fn (): mixed => in_array('verify_durable_checkpoint_source_barriers_current_source_next228', $admitted()['operation_names'], true), true],
    'operation admit present' => [static fn (): mixed => in_array('admit_durable_checkpoint_source_current_source_next228', $admitted()['operation_names'], true), true],
    'admission digest length' => [static fn (): mixed => strlen($admitted()['admission_digest']), 64],
    'dependency next228' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next228', $admitted()['dependencies'], true), true],
    'dependency barriers' => [static fn (): mixed => in_array('sqlite-durable-checkpoint-source-barriers', $admitted()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-reopened-reader-durable-source', $admitted()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($admitted()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($admitted()['non_overlap'], 'does not repeat next224 sidecar publication receipts'), true],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart admitted' => [static fn (): mixed => $restart()['current_source_admitted'], true],
    'restart wal barrier mode' => [static fn (): mixed => $restart()['barrier_rows'][1]['mode'], 'restart'],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next228'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'durable_checkpoint_source_waits_for_barrier_or_reader_receipts'],
    'blocked admitted false' => [static fn (): mixed => $blocked()['current_source_admitted'], false],
    'blocked reusable false' => [static fn (): mixed => $blocked()['checkpoint_reusable_by_readers'], false],
    'blocked writer action' => [static fn (): mixed => $blocked()['next_writer_action'], 'wait_for_durable_checkpoint_source'],
    'blocked reader action' => [static fn (): mixed => $blocked()['reader_action'], 'reopen_after_durability_barrier'],
    'blocked barrier names' => [static fn (): mixed => $blocked()['blocked_barrier_names'], ['main-database-fsync', 'truncate-wal-fsync', 'hot-journal-directory-fsync', 'shm-exclusive-epoch', 'plugin-savepoint-release']],
    'blocked barrier reasons' => [static fn (): mixed => $blocked()['blocked_barrier_reasons'], ['barrier_receipt_missing', 'database_digest_mismatch', 'wal_reset_not_observed', 'wal_reset_mode_mismatch', 'hot_journal_unlink_not_synced', 'generation_mismatch', 'exclusive_lock_missing', 'source_token_mismatch', 'sync_order_missing', 'savepoint_not_released']],
    'blocked reader names' => [static fn (): mixed => $blocked()['blocked_reader_names'], ['old-pinned-reader', 'wrong-generation-reader']],
    'blocked reader reasons' => [static fn (): mixed => $blocked()['blocked_reader_reasons'], ['reader_not_reopened', 'reader_missed_durability_barrier', 'reader_pins_old_source', 'reader_source_token_mismatch', 'reader_generation_mismatch']],
    'blocked guards' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['durability_barriers_match_current_source', 'readers_observe_durable_source']],
    'missing status' => [static fn (): mixed => $missing()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next228'],
    'missing barrier type' => [static fn (): mixed => $missing()['missing_barrier_types'], ['savepoint_release']],
    'missing blocked guard' => [static fn (): mixed => $missing()['blocked_guard_names'], ['required_durability_barriers_present']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next228 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource(['status' => 'bad'], $barriers, $readers, $sourceToken),
    'not visible rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource(array_merge($publication, ['checkpoint_reset_visible' => false]), $barriers, $readers, $sourceToken),
    'empty barriers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, [], $readers, $sourceToken),
    'empty readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, $barriers, [], $sourceToken),
    'source token mismatch rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, $barriers, $readers, 'next228:other-source'),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource(array_merge($publication, ['next_writer_generation' => 0]), $barriers, $readers, $sourceToken),
    'bad digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource(array_merge($publication, ['database_digest' => 'short']), $barriers, $readers, $sourceToken),
    'bad mode rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource(array_merge($publication, ['mode' => 'passive']), $barriers, $readers, $sourceToken),
    'bad barrier name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, [array_merge($barriers[0], ['name' => 'bad name'])], $readers, $sourceToken),
    'bad barrier type rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, [array_merge($barriers[0], ['type' => 'cache'])], $readers, $sourceToken),
    'bad barrier generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, [array_merge($barriers[0], ['generation' => -1])], $readers, $sourceToken),
    'bad barrier sync order rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, [array_merge($barriers[0], ['sync_order' => -1])], $readers, $sourceToken),
    'bad barrier digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, [array_merge($barriers[0], ['database_digest' => 'short'])], $readers, $sourceToken),
    'bad reader name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, $barriers, [array_merge($readers[0], ['name' => 'bad name'])], $sourceToken),
    'bad reader generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, $barriers, [array_merge($readers[0], ['generation' => -1])], $sourceToken),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next228 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
