<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$generation = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next194',
    'reason' => 'reopened_reader_generation_sealed_to_retry_checkpoint_publication',
    'database_path' => '/srv/www/wp-content/database/wp-next200.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next200.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next200.sqlite-journal',
    'publication_token' => 'wal-hot-journal-savepoint-checkpoint-next190:publish:next200',
    'previous_reader_epoch' => 196,
    'sealed_reader_epoch' => 200,
    'reader_ticket_count' => 3,
    'reader_ticket_ids' => ['wp-reader-next200-a', 'wp-reader-next200-b', 'wp-reader-next200-c'],
    'reader_pages' => [1, 2, 5],
    'reader_sources' => ['checkpoint-database', 'next-wal', 'next-wal'],
    'can_expose_reopened_readers' => true,
    'blocked_reasons' => [],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next194',
        'sqlite-reopened-reader-generation-seal',
        'application-import-retry-checkpoint-reader-exposure',
    ],
];
$hotDigest = hash('sha256', 'next200 recovered hot journal page set');
$checkpointDigest = hash('sha256', 'next200 checkpoint database image');
$receipt = static fn (array $overrides = []): array => array_merge([
    'ticket_id' => 'wp-reader-next200-a',
    'page_number' => 1,
    'reader_epoch' => 197,
    'publication_token' => $generation['publication_token'],
    'hot_journal_recovery_digest' => $hotDigest,
    'checkpoint_database_digest' => $checkpointDigest,
    'savepoint_generation' => 42,
    'wal_sync_receipt' => true,
    'database_sync_receipt' => true,
    'directory_sync_receipt' => true,
    'reader_reopened_after_hot_journal' => true,
    'savepoint_release_observed' => true,
], $overrides);
$receipts = [
    $receipt(),
    $receipt([
        'ticket_id' => 'wp-reader-next200-b',
        'page_number' => 2,
        'reader_epoch' => 198,
    ]),
    $receipt([
        'ticket_id' => 'wp-reader-next200-c',
        'page_number' => 5,
        'reader_epoch' => 200,
    ]),
];
$plan = static fn (?array $input = null, ?array $rows = null, ?string $expectedHot = null, int $generationNumber = 42, ?string $expectedCheckpoint = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next200AdmitDurableReaders(
        $input ?? $generation,
        $rows ?? $receipts,
        $expectedHot ?? $hotDigest,
        $generationNumber,
        $expectedCheckpoint ?? $checkpointDigest
    );

$badStatus = $generation;
$badStatus['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next194';
$badExpose = $generation;
$badExpose['can_expose_reopened_readers'] = false;
$duplicate = [$receipt(), $receipt(['page_number' => 2])];
$missingOne = [$receipt(), $receipt(['ticket_id' => 'wp-reader-next200-b', 'page_number' => 2, 'reader_epoch' => 198])];
$badTicket = [$receipt(['ticket_id' => 'unknown-ticket'])];
$badPage = [$receipt(['page_number' => 9])];
$badEpochLow = [$receipt(['reader_epoch' => 196])];
$badEpochHigh = [$receipt(['reader_epoch' => 201])];
$badPublication = [$receipt(['publication_token' => 'wrong-publication-token'])];
$badHotDigest = [$receipt(['hot_journal_recovery_digest' => hash('sha256', 'wrong hot')])];
$badCheckpoint = [$receipt(['checkpoint_database_digest' => hash('sha256', 'wrong checkpoint')])];
$badSavepoint = [$receipt(['savepoint_generation' => 41])];
$missingWalSync = [$receipt(['wal_sync_receipt' => false])];
$missingDatabaseSync = [$receipt(['database_sync_receipt' => false])];
$missingDirectorySync = [$receipt(['directory_sync_receipt' => false])];
$notReopened = [$receipt(['reader_reopened_after_hot_journal' => false])];
$notReleased = [$receipt(['savepoint_release_observed' => false])];
$combinedBad = [$receipt([
    'ticket_id' => 'unknown-ticket',
    'page_number' => 9,
    'reader_epoch' => 196,
    'publication_token' => 'wrong-publication-token',
    'hot_journal_recovery_digest' => hash('sha256', 'wrong hot'),
    'checkpoint_database_digest' => hash('sha256', 'wrong checkpoint'),
    'savepoint_generation' => 41,
    'wal_sync_receipt' => false,
    'database_sync_receipt' => false,
    'directory_sync_receipt' => false,
    'reader_reopened_after_hot_journal' => false,
    'savepoint_release_observed' => false,
])];

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next200'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'sealed_reader_generation_has_hot_journal_savepoint_checkpoint_durability_receipts'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $generation['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $generation['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $generation['journal_path']],
    'publication token' => [static fn (): mixed => $plan()['publication_token'], $generation['publication_token']],
    'previous epoch' => [static fn (): mixed => $plan()['previous_reader_epoch'], 196],
    'sealed epoch' => [static fn (): mixed => $plan()['sealed_reader_epoch'], 200],
    'receipt count' => [static fn (): mixed => $plan()['receipt_count'], 3],
    'receipt tickets' => [static fn (): mixed => $plan()['receipt_ticket_ids'], ['wp-reader-next200-a', 'wp-reader-next200-b', 'wp-reader-next200-c']],
    'receipt pages' => [static fn (): mixed => $plan()['receipt_pages'], [1, 2, 5]],
    'expected hot digest' => [static fn (): mixed => $plan()['expected_hot_journal_recovery_digest'], $hotDigest],
    'expected savepoint generation' => [static fn (): mixed => $plan()['expected_savepoint_generation'], 42],
    'expected checkpoint digest' => [static fn (): mixed => $plan()['expected_checkpoint_database_digest'], $checkpointDigest],
    'can admit' => [static fn (): mixed => $plan()['can_admit_durable_readers'], true],
    'blocked empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'digest stable' => [static fn (): mixed => $plan()['durability_digest'], $plan()['durability_digest']],
    'row hot matches' => [static fn (): mixed => $plan()['receipt_rows'][0]['hot_journal_digest_matches'], true],
    'row checkpoint matches' => [static fn (): mixed => $plan()['receipt_rows'][0]['checkpoint_digest_matches'], true],
    'row publication matches' => [static fn (): mixed => $plan()['receipt_rows'][0]['publication_token_matches'], true],
    'row wal sync' => [static fn (): mixed => $plan()['receipt_rows'][0]['wal_sync_receipt'], true],
    'row database sync' => [static fn (): mixed => $plan()['receipt_rows'][0]['database_sync_receipt'], true],
    'row directory sync' => [static fn (): mixed => $plan()['receipt_rows'][0]['directory_sync_receipt'], true],
    'row reopened' => [static fn (): mixed => $plan()['receipt_rows'][0]['reader_reopened_after_hot_journal'], true],
    'row released' => [static fn (): mixed => $plan()['receipt_rows'][0]['savepoint_release_observed'], true],
    'dependency next194' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next194', $plan()['dependencies'], true), true],
    'dependency next200' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next200', $plan()['dependencies'], true), true],
    'application dependency' => [static fn (): mixed => in_array('application-import-retry-checkpoint-durable-reader-admission', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'bad generation status blocks' => [static fn (): mixed => $plan($badStatus)['blocked_reasons'], ['next194_reader_generation_seal_required']],
    'bad generation expose blocks' => [static fn (): mixed => $plan($badExpose)['blocked_reasons'], ['next194_reader_generation_not_exposable']],
    'duplicate blocks' => [static fn (): mixed => $plan(null, $duplicate)['blocked_reasons'], ['duplicate_durability_receipt_ticket', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'missing ticket blocks' => [static fn (): mixed => $plan(null, $missingOne)['blocked_reasons'], ['missing_durability_receipt_for_sealed_reader_ticket']],
    'bad ticket blocks' => [static fn (): mixed => $plan(null, $badTicket)['blocked_reasons'], ['durability_receipt_ticket_not_in_next194_generation', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'bad page blocks' => [static fn (): mixed => $plan(null, $badPage)['blocked_reasons'], ['durability_receipt_page_not_in_next194_generation', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'bad epoch low blocks' => [static fn (): mixed => $plan(null, $badEpochLow)['blocked_reasons'], ['durability_receipt_epoch_outside_sealed_generation', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'bad epoch high blocks' => [static fn (): mixed => $plan(null, $badEpochHigh)['blocked_reasons'], ['durability_receipt_epoch_outside_sealed_generation', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'bad publication blocks' => [static fn (): mixed => $plan(null, $badPublication)['blocked_reasons'], ['durability_receipt_publication_token_mismatch', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'bad hot blocks' => [static fn (): mixed => $plan(null, $badHotDigest)['blocked_reasons'], ['durability_receipt_hot_journal_digest_mismatch', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'bad checkpoint blocks' => [static fn (): mixed => $plan(null, $badCheckpoint)['blocked_reasons'], ['durability_receipt_checkpoint_digest_mismatch', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'bad savepoint blocks' => [static fn (): mixed => $plan(null, $badSavepoint)['blocked_reasons'], ['durability_receipt_savepoint_generation_mismatch', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'missing wal sync blocks' => [static fn (): mixed => $plan(null, $missingWalSync)['blocked_reasons'], ['durability_receipt_missing_wal_sync', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'missing database sync blocks' => [static fn (): mixed => $plan(null, $missingDatabaseSync)['blocked_reasons'], ['durability_receipt_missing_database_sync', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'missing directory sync blocks' => [static fn (): mixed => $plan(null, $missingDirectorySync)['blocked_reasons'], ['durability_receipt_missing_directory_sync', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'not reopened blocks' => [static fn (): mixed => $plan(null, $notReopened)['blocked_reasons'], ['durability_receipt_reader_not_reopened_after_hot_journal', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'not released blocks' => [static fn (): mixed => $plan(null, $notReleased)['blocked_reasons'], ['durability_receipt_savepoint_release_not_observed', 'missing_durability_receipt_for_sealed_reader_ticket']],
    'combined blocks' => [static fn (): mixed => $plan(null, $combinedBad)['blocked_reasons'], [
        'durability_receipt_ticket_not_in_next194_generation',
        'durability_receipt_page_not_in_next194_generation',
        'durability_receipt_epoch_outside_sealed_generation',
        'durability_receipt_publication_token_mismatch',
        'durability_receipt_hot_journal_digest_mismatch',
        'durability_receipt_checkpoint_digest_mismatch',
        'durability_receipt_savepoint_generation_mismatch',
        'durability_receipt_missing_wal_sync',
        'durability_receipt_missing_database_sync',
        'durability_receipt_missing_directory_sync',
        'durability_receipt_reader_not_reopened_after_hot_journal',
        'durability_receipt_savepoint_release_not_observed',
        'missing_durability_receipt_for_sealed_reader_ticket',
    ]],
    'blocked status' => [static fn (): mixed => $plan(null, $badHotDigest)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next200'],
    'blocked reason' => [static fn (): mixed => $plan(null, $badHotDigest)['reason'], 'sealed_reader_generation_waits_for_hot_journal_savepoint_checkpoint_durability'],
    'blocked cannot admit' => [static fn (): mixed => $plan(null, $badHotDigest)['can_admit_durable_readers'], false],
    'blocked row reason' => [static fn (): mixed => $plan(null, $badHotDigest)['receipt_rows'][0]['blocked_reasons'], ['durability_receipt_hot_journal_digest_mismatch']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next200 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing generation key rejected' => static function () use ($plan, $generation): void {
        $bad = $generation;
        unset($bad['publication_token']);
        $plan($bad);
    },
    'empty generation path rejected' => static function () use ($plan, $generation): void {
        $bad = $generation;
        $bad['database_path'] = '';
        $plan($bad);
    },
    'bad generation epoch rejected' => static function () use ($plan, $generation): void {
        $bad = $generation;
        $bad['sealed_reader_epoch'] = '200';
        $plan($bad);
    },
    'bad generation ticket id rejected' => static function () use ($plan, $generation): void {
        $bad = $generation;
        $bad['reader_ticket_ids'] = [''];
        $plan($bad);
    },
    'bad generation page rejected' => static function () use ($plan, $generation): void {
        $bad = $generation;
        $bad['reader_pages'] = [0];
        $plan($bad);
    },
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad hot digest rejected' => static fn () => $plan(null, null, ''),
    'bad checkpoint digest rejected' => static fn () => $plan(null, null, null, 42, ''),
    'bad savepoint generation rejected' => static fn () => $plan(null, null, null, 0),
    'non array receipt rejected' => static fn () => $plan(null, ['bad']),
    'empty ticket id rejected' => static fn () => $plan(null, [$receipt(['ticket_id' => ''])]),
    'bad receipt epoch rejected' => static fn () => $plan(null, [$receipt(['reader_epoch' => '197'])]),
    'bad receipt digest rejected' => static fn () => $plan(null, [$receipt(['hot_journal_recovery_digest' => []])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next200 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
