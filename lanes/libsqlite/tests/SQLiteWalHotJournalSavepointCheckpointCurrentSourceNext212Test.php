<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next212 checkpoint database');
$walDigest = $digest('next212 retained wal');
$writerDigest = $digest('next212 writer generation');
$oldDatabaseDigest = $digest('next212 old database');
$oldWalDigest = $digest('next212 old wal');
$oldWriterDigest = $digest('next212 old writer');

$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next209',
    'database_path' => '/srv/www/wp-content/database/wp-next212.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next212.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next212.sqlite-wal',
    'page_size' => 512,
    'minimum_statement_generation' => 209,
    'next_writer_generation' => 212,
    'checkpointed_database_digest' => $databaseDigest,
    'expected_wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'admitted_writer_names' => ['wp-options-autoload-writer'],
    'reopen_writer_names' => ['stale-plugin-writer'],
    'operation_names' => ['verify_post_checkpoint_writer_generation_current_source_next209'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next209'],
];

$readers = [
    [
        'name' => 'wp-options-current-reader',
        'reader_end_frame' => 210,
        'reader_generation' => 212,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'wp-cron-current-reader',
        'reader_end_frame' => 211,
        'reader_generation' => 212,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'old-generation-reader',
        'reader_end_frame' => 210,
        'reader_generation' => 211,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'old-frame-reader',
        'reader_end_frame' => 208,
        'reader_generation' => 212,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'future-frame-reader',
        'reader_end_frame' => 213,
        'reader_generation' => 212,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'old-database-reader',
        'reader_end_frame' => 210,
        'reader_generation' => 212,
        'observed_database_digest' => $oldDatabaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'old-wal-reader',
        'reader_end_frame' => 210,
        'reader_generation' => 212,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $oldWalDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'old-writer-reader',
        'reader_end_frame' => 210,
        'reader_generation' => 212,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $oldWriterDigest,
    ],
    [
        'name' => 'dirty-reader',
        'reader_end_frame' => 210,
        'reader_generation' => 212,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'dirty' => true,
    ],
    [
        'name' => 'closed-reader',
        'reader_end_frame' => 210,
        'reader_generation' => 212,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'closed' => true,
    ],
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint($base, $readers, 212);
$complete = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint($base, [$readers[1]], 211);
$noStale = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint($base, [$readers[0], $readers[1]], 212);
$noPin = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint($base, array_slice($readers, 2), 212);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next212'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'passive_checkpoint_stops_at_current_reader_pin_after_hot_journal_recovery'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next209'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next212.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next212.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next212.sqlite-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'requested checkpoint frame' => [static fn (): mixed => $plan()['requested_checkpoint_frame'], 212],
    'checkpointed frame stops at earliest current reader' => [static fn (): mixed => $plan()['checkpointed_frame'], 210],
    'busy flag' => [static fn (): mixed => $plan()['busy'], true],
    'wal action preserves wal' => [static fn (): mixed => $plan()['wal_action'], 'preserve_wal'],
    'database action' => [static fn (): mixed => $plan()['database_action'], 'write_frames_through_210'],
    'reset disallowed' => [static fn (): mixed => $plan()['reset_allowed'], false],
    'truncate disallowed' => [static fn (): mixed => $plan()['truncate_allowed'], false],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'wal digest' => [static fn (): mixed => $plan()['wal_digest'], $walDigest],
    'writer digest' => [static fn (): mixed => $plan()['writer_digest'], $writerDigest],
    'next writer generation' => [static fn (): mixed => $plan()['next_writer_generation'], 212],
    'minimum statement generation' => [static fn (): mixed => $plan()['minimum_statement_generation'], 209],
    'active reader names' => [static fn (): mixed => $plan()['active_reader_names'], ['wp-options-current-reader', 'wp-cron-current-reader']],
    'reopen reader names' => [static fn (): mixed => $plan()['reopen_reader_names'], ['old-generation-reader', 'old-frame-reader', 'future-frame-reader', 'old-database-reader', 'old-wal-reader', 'old-writer-reader', 'dirty-reader', 'closed-reader']],
    'first reader admitted' => [static fn (): mixed => $plan()['reader_rows'][0]['admitted'], true],
    'first reader pins source' => [static fn (): mixed => $plan()['reader_rows'][0]['pins_current_source'], true],
    'first reader reason' => [static fn (): mixed => $plan()['reader_rows'][0]['reader_reason'], 'reader_pins_current_source_for_passive_checkpoint'],
    'second reader frame' => [static fn (): mixed => $plan()['reader_rows'][1]['reader_end_frame'], 211],
    'old generation reason' => [static fn (): mixed => $plan()['reader_rows'][2]['reader_reason'], 'reader_generation_mismatch'],
    'old frame reason' => [static fn (): mixed => $plan()['reader_rows'][3]['reader_reason'], 'reader_end_frame_before_current_statement'],
    'future frame reason' => [static fn (): mixed => $plan()['reader_rows'][4]['reader_reason'], 'reader_end_frame_after_requested_checkpoint'],
    'old database reason' => [static fn (): mixed => $plan()['reader_rows'][5]['reader_reason'], 'reader_database_digest_mismatch'],
    'old wal reason' => [static fn (): mixed => $plan()['reader_rows'][6]['reader_reason'], 'reader_wal_digest_mismatch'],
    'old writer reason' => [static fn (): mixed => $plan()['reader_rows'][7]['reader_reason'], 'reader_writer_digest_mismatch'],
    'dirty reason' => [static fn (): mixed => $plan()['reader_rows'][8]['reader_reason'], 'reader_cache_dirty'],
    'dirty flag' => [static fn (): mixed => $plan()['reader_rows'][8]['dirty'], true],
    'closed reason' => [static fn (): mixed => $plan()['reader_rows'][9]['reader_reason'], 'reader_handle_closed'],
    'closed flag' => [static fn (): mixed => $plan()['reader_rows'][9]['closed'], true],
    'blocked reader reasons' => [static fn (): mixed => $plan()['blocked_reader_reasons'], ['reader_generation_mismatch', 'reader_end_frame_before_current_statement', 'reader_end_frame_after_requested_checkpoint', 'reader_database_digest_mismatch', 'reader_wal_digest_mismatch', 'reader_writer_digest_mismatch', 'reader_cache_dirty', 'reader_handle_closed']],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next209_writer_generation_admitted', 'checkpoint_frame_not_before_statement_generation', 'active_reader_pin_detected', 'stale_readers_reopened']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true]],
    'blocked guard names' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'verify_post_checkpoint_writer_generation_current_source_next209'],
    'operation verify present' => [static fn (): mixed => in_array('verify_passive_checkpoint_reader_pin_current_source_next212', $plan()['operation_names'], true), true],
    'operation preserve present' => [static fn (): mixed => in_array('preserve_wal_for_pinned_reader_next212', $plan()['operation_names'], true), true],
    'checkpoint digest length' => [static fn (): mixed => strlen($plan()['checkpoint_digest']), 64],
    'dependency next212' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212', $plan()['dependencies'], true), true],
    'dependency passive checkpoint' => [static fn (): mixed => in_array('sqlite-passive-checkpoint-current-reader-pin-after-hot-journal', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-passive-checkpoint-preserves-wal-for-current-reader', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next209 writer fences'), true],
    'complete checkpoint status' => [static fn (): mixed => $complete()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next212'],
    'complete checkpoint frame' => [static fn (): mixed => $complete()['checkpointed_frame'], 211],
    'complete checkpoint wal action' => [static fn (): mixed => $complete()['wal_action'], 'passive_checkpoint_complete'],
    'complete checkpoint busy false' => [static fn (): mixed => $complete()['busy'], false],
    'complete checkpoint operation' => [static fn (): mixed => in_array('complete_passive_checkpoint_next212', $complete()['operation_names'], true), true],
    'complete checkpoint blocked guard' => [static fn (): mixed => $complete()['blocked_guard_names'], ['stale_readers_reopened']],
    'no stale blocked guard' => [static fn (): mixed => $noStale()['blocked_guard_names'], ['stale_readers_reopened']],
    'no pin blocked guard' => [static fn (): mixed => $noPin()['blocked_guard_names'], ['active_reader_pin_detected']],
    'bad base blocked guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint(array_merge($base, ['admitted_writer_names' => []]), $readers, 212)['blocked_guard_names'], ['next209_writer_generation_admitted']],
    'bad frame blocked guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint($base, $readers, 208)['blocked_guard_names'], ['checkpoint_frame_not_before_statement_generation', 'active_reader_pin_detected']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next212 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad status rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint(['status' => 'bad'], $readers, 212),
    'empty readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint($base, [], 212),
    'zero frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint($base, $readers, 0),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint(array_merge($base, ['checkpointed_database_digest' => 'short']), $readers, 212),
    'bad writer generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint(array_merge($base, ['next_writer_generation' => 0]), $readers, 212),
    'bad statement generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint(array_merge($base, ['minimum_statement_generation' => -1]), $readers, 212),
    'bad writer list rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint(array_merge($base, ['admitted_writer_names' => [null]]), $readers, 212),
    'missing reader name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint($base, [array_merge($readers[0], ['name' => ''])], 212),
    'bad reader frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint($base, [array_merge($readers[0], ['reader_end_frame' => 0])], 212),
    'bad reader digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint($base, [array_merge($readers[0], ['observed_wal_digest' => 'short'])], 212),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next212 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
