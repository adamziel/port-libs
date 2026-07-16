<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next218 checkpointed database image');
$walDigest = $digest('next218 wal image before restart');
$writerDigest = $digest('next218 writer generation digest');
$oldDigest = $digest('next218 stale image');

$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next212',
    'database_path' => '/srv/www/wp-content/database/wp-next218.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next218.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next218.sqlite-wal',
    'page_size' => 512,
    'requested_checkpoint_frame' => 218,
    'checkpointed_frame' => 218,
    'busy' => false,
    'database_digest' => $databaseDigest,
    'wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'next_writer_generation' => 219,
    'minimum_statement_generation' => 200,
    'active_reader_names' => [],
    'reopen_reader_names' => [],
    'operation_names' => ['verify_passive_checkpoint_reader_pin_current_source_next212'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212'],
];

$pinnedBase = $base;
$pinnedBase['checkpointed_frame'] = 216;
$pinnedBase['busy'] = true;
$pinnedBase['active_reader_names'] = ['wp-options-reader-current'];
$reopenBase = $base;
$reopenBase['reopen_reader_names'] = ['wp-options-reader-stale'];

$writers = [
    [
        'name' => 'wp-import-writer-current',
        'writer_generation' => 219,
        'start_frame' => 200,
        'last_frame' => 218,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'sync_receipt' => true,
    ],
    [
        'name' => 'plugin-update-writer-current',
        'writer_generation' => 219,
        'start_frame' => 201,
        'last_frame' => 218,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'sync_receipt' => true,
    ],
];

$mixedWriters = array_merge($writers, [
    [
        'name' => 'old-generation-writer',
        'writer_generation' => 217,
        'start_frame' => 200,
        'last_frame' => 218,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'sync_receipt' => true,
    ],
    [
        'name' => 'old-start-frame-writer',
        'writer_generation' => 219,
        'start_frame' => 199,
        'last_frame' => 218,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'sync_receipt' => true,
    ],
    [
        'name' => 'partial-frame-writer',
        'writer_generation' => 219,
        'start_frame' => 200,
        'last_frame' => 217,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'sync_receipt' => true,
    ],
    [
        'name' => 'old-database-writer',
        'writer_generation' => 219,
        'start_frame' => 200,
        'last_frame' => 218,
        'observed_database_digest' => $oldDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'sync_receipt' => true,
    ],
    [
        'name' => 'old-wal-writer',
        'writer_generation' => 219,
        'start_frame' => 200,
        'last_frame' => 218,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $oldDigest,
        'observed_writer_digest' => $writerDigest,
        'sync_receipt' => true,
    ],
    [
        'name' => 'old-writer-digest',
        'writer_generation' => 219,
        'start_frame' => 200,
        'last_frame' => 218,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $oldDigest,
        'sync_receipt' => true,
    ],
    [
        'name' => 'hot-journal-writer',
        'writer_generation' => 219,
        'start_frame' => 200,
        'last_frame' => 218,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'hot_journal_present' => true,
        'sync_receipt' => true,
    ],
    [
        'name' => 'savepoint-writer',
        'writer_generation' => 219,
        'start_frame' => 200,
        'last_frame' => 218,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'savepoint_depth' => 1,
        'sync_receipt' => true,
    ],
    [
        'name' => 'dirty-writer',
        'writer_generation' => 219,
        'start_frame' => 200,
        'last_frame' => 218,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'dirty' => true,
        'sync_receipt' => true,
    ],
    [
        'name' => 'unsynced-writer',
        'writer_generation' => 219,
        'start_frame' => 200,
        'last_frame' => 218,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
]);

$restart = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($base, $writers, 'restart');
$truncate = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($base, $writers, 'truncate');
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($base, $mixedWriters, 'restart');
$pinned = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($pinnedBase, $writers, 'truncate');
$reopen = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($reopenBase, $writers, 'restart');

$cases = [
    'restart status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next218'],
    'restart reason' => [static fn (): mixed => $restart()['reason'], 'restart_or_truncate_checkpoint_can_reset_wal_after_hot_journal_savepoint_fence'],
    'base status' => [static fn (): mixed => $restart()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next212'],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'database path' => [static fn (): mixed => $restart()['database_path'], '/srv/www/wp-content/database/wp-next218.sqlite'],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], '/srv/www/wp-content/database/wp-next218.sqlite-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], '/srv/www/wp-content/database/wp-next218.sqlite-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'requested frame' => [static fn (): mixed => $restart()['requested_checkpoint_frame'], 218],
    'checkpointed frame' => [static fn (): mixed => $restart()['checkpointed_frame'], 218],
    'all frames checkpointed' => [static fn (): mixed => $restart()['all_frames_checkpointed'], true],
    'reader pins false' => [static fn (): mixed => $restart()['reader_pins_wal'], false],
    'active readers' => [static fn (): mixed => $restart()['active_reader_names'], []],
    'reopen readers' => [static fn (): mixed => $restart()['reopen_reader_names'], []],
    'database digest' => [static fn (): mixed => $restart()['database_digest'], $databaseDigest],
    'wal digest' => [static fn (): mixed => $restart()['wal_digest'], $walDigest],
    'writer digest' => [static fn (): mixed => $restart()['writer_digest'], $writerDigest],
    'next generation' => [static fn (): mixed => $restart()['next_writer_generation'], 219],
    'minimum statement generation' => [static fn (): mixed => $restart()['minimum_statement_generation'], 200],
    'admitted writers' => [static fn (): mixed => $restart()['admitted_writer_names'], ['wp-import-writer-current', 'plugin-update-writer-current']],
    'blocked writers empty' => [static fn (): mixed => $restart()['blocked_writer_names'], []],
    'can reset' => [static fn (): mixed => $restart()['can_reset_wal'], true],
    'restart allowed' => [static fn (): mixed => $restart()['reset_allowed'], true],
    'truncate not allowed in restart' => [static fn (): mixed => $restart()['truncate_allowed'], false],
    'restart wal action' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal_header_with_new_salt'],
    'restart sync action' => [static fn (): mixed => $restart()['sync_action'], 'sync_database_then_restart_wal_header'],
    'database action' => [static fn (): mixed => $restart()['database_action'], 'checkpoint_database_already_contains_frames_through_218'],
    'first writer reason' => [static fn (): mixed => $restart()['writer_rows'][0]['writer_reason'], 'writer_can_publish_restart_truncate_reset'],
    'first writer generation' => [static fn (): mixed => $restart()['writer_rows'][0]['writer_generation'], 219],
    'first writer expected generation' => [static fn (): mixed => $restart()['writer_rows'][0]['expected_generation'], 219],
    'first writer start frame' => [static fn (): mixed => $restart()['writer_rows'][0]['start_frame'], 200],
    'first writer last frame' => [static fn (): mixed => $restart()['writer_rows'][0]['last_frame'], 218],
    'first writer sync' => [static fn (): mixed => $restart()['writer_rows'][0]['sync_receipt'], true],
    'guard names' => [static fn (): mixed => $restart()['guard_names'], ['next212_passive_checkpoint_complete', 'no_reopen_readers_pending', 'writer_generation_fence']],
    'guard matches' => [static fn (): mixed => $restart()['guard_matches'], [true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $restart()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $restart()['operation_names'][0], 'verify_passive_checkpoint_reader_pin_current_source_next212'],
    'operation verify present' => [static fn (): mixed => in_array('verify_restart_truncate_current_source_next218', $restart()['operation_names'], true), true],
    'operation publish present' => [static fn (): mixed => in_array('publish_wal_reset_current_source_next218', $restart()['operation_names'], true), true],
    'reset digest length' => [static fn (): mixed => strlen($restart()['reset_digest']), 64],
    'dependency next212 inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212', $restart()['dependencies'], true), true],
    'dependency next218' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next218', $restart()['dependencies'], true), true],
    'dependency restart truncate' => [static fn (): mixed => in_array('sqlite-wal-restart-truncate-after-hot-journal-savepoint-fence', $restart()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-checkpoint-reset-waits-for-current-source-reopen', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'does not repeat next212 reader-pin progress'), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate allowed' => [static fn (): mixed => $truncate()['truncate_allowed'], true],
    'truncate reset false' => [static fn (): mixed => $truncate()['reset_allowed'], false],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal_to_zero_bytes'],
    'truncate sync action' => [static fn (): mixed => $truncate()['sync_action'], 'sync_database_then_truncate_wal_header'],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next218'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'restart_or_truncate_checkpoint_preserves_wal_until_readers_and_writers_reopen'],
    'blocked can reset false' => [static fn (): mixed => $blocked()['can_reset_wal'], false],
    'blocked writer names' => [static fn (): mixed => $blocked()['blocked_writer_names'], ['old-generation-writer', 'old-start-frame-writer', 'partial-frame-writer', 'old-database-writer', 'old-wal-writer', 'old-writer-digest', 'hot-journal-writer', 'savepoint-writer', 'dirty-writer', 'unsynced-writer']],
    'old generation reason' => [static fn (): mixed => $blocked()['writer_rows'][2]['writer_reason'], 'writer_generation_mismatch'],
    'old start reason' => [static fn (): mixed => $blocked()['writer_rows'][3]['writer_reason'], 'writer_start_frame_before_statement_generation'],
    'partial reason' => [static fn (): mixed => $blocked()['writer_rows'][4]['writer_reason'], 'writer_wal_frame_not_fully_checkpointed'],
    'old database reason' => [static fn (): mixed => $blocked()['writer_rows'][5]['writer_reason'], 'writer_database_digest_mismatch'],
    'old wal reason' => [static fn (): mixed => $blocked()['writer_rows'][6]['writer_reason'], 'writer_wal_digest_mismatch'],
    'old writer digest reason' => [static fn (): mixed => $blocked()['writer_rows'][7]['writer_reason'], 'writer_digest_mismatch'],
    'hot journal reason' => [static fn (): mixed => $blocked()['writer_rows'][8]['writer_reason'], 'writer_hot_journal_present'],
    'savepoint reason' => [static fn (): mixed => $blocked()['writer_rows'][9]['writer_reason'], 'writer_savepoint_scope_not_closed'],
    'dirty reason' => [static fn (): mixed => $blocked()['writer_rows'][10]['writer_reason'], 'writer_dirty_page_cache'],
    'unsynced reason' => [static fn (): mixed => $blocked()['writer_rows'][11]['writer_reason'], 'writer_missing_sync_receipt'],
    'blocked reasons unique' => [static fn (): mixed => $blocked()['blocked_writer_reasons'], ['writer_generation_mismatch', 'writer_start_frame_before_statement_generation', 'writer_wal_frame_not_fully_checkpointed', 'writer_database_digest_mismatch', 'writer_wal_digest_mismatch', 'writer_digest_mismatch', 'writer_hot_journal_present', 'writer_savepoint_scope_not_closed', 'writer_dirty_page_cache', 'writer_missing_sync_receipt']],
    'blocked guard writer fence' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['writer_generation_fence']],
    'blocked preserve action' => [static fn (): mixed => $blocked()['wal_action'], 'preserve_wal_for_reader_or_writer_reopen'],
    'blocked sync action' => [static fn (): mixed => $blocked()['sync_action'], 'skip_wal_reset_sync_until_reopen'],
    'pinned status' => [static fn (): mixed => $pinned()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next218'],
    'pinned checkpointed frame' => [static fn (): mixed => $pinned()['checkpointed_frame'], 216],
    'pinned reader flag' => [static fn (): mixed => $pinned()['reader_pins_wal'], true],
    'pinned readers' => [static fn (): mixed => $pinned()['active_reader_names'], ['wp-options-reader-current']],
    'pinned blocked guards' => [static fn (): mixed => $pinned()['blocked_guard_names'], ['next212_passive_checkpoint_complete', 'writer_generation_fence']],
    'reopen status' => [static fn (): mixed => $reopen()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next218'],
    'reopen readers' => [static fn (): mixed => $reopen()['reopen_reader_names'], ['wp-options-reader-stale']],
    'reopen blocked guards' => [static fn (): mixed => $reopen()['blocked_guard_names'], ['no_reopen_readers_pending']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next218 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate(['status' => 'bad'], $writers, 'restart'),
    'bad mode rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($base, $writers, 'passive'),
    'empty writers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($base, [], 'restart'),
    'bad requested frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate(array_merge($base, ['requested_checkpoint_frame' => 0]), $writers, 'restart'),
    'bad checkpoint frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate(array_merge($base, ['checkpointed_frame' => 0]), $writers, 'restart'),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate(array_merge($base, ['database_digest' => 'short']), $writers, 'restart'),
    'bad wal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate(array_merge($base, ['wal_digest' => 'short']), $writers, 'restart'),
    'bad writer digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate(array_merge($base, ['writer_digest' => 'short']), $writers, 'restart'),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate(array_merge($base, ['next_writer_generation' => 0]), $writers, 'restart'),
    'bad minimum statement rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate(array_merge($base, ['minimum_statement_generation' => -1]), $writers, 'restart'),
    'bad active readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate(array_merge($base, ['active_reader_names' => ['']]), $writers, 'restart'),
    'bad reopen readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate(array_merge($base, ['reopen_reader_names' => [42]]), $writers, 'restart'),
    'empty writer name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($base, [array_merge($writers[0], ['name' => ''])], 'restart'),
    'bad writer database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($base, [array_merge($writers[0], ['observed_database_digest' => 'short'])], 'restart'),
    'bad writer wal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($base, [array_merge($writers[0], ['observed_wal_digest' => 'short'])], 'restart'),
    'bad writer generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($base, [array_merge($writers[0], ['writer_generation' => -1])], 'restart'),
    'bad writer frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($base, [array_merge($writers[0], ['last_frame' => 199])], 'restart'),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next218 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
