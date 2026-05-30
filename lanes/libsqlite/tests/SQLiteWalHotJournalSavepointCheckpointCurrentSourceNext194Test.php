<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/srv/www/wp-content/database/wp-next194.sqlite';
$walPath = $databasePath . '-wal';
$journalPath = $databasePath . '-journal';
$databaseBytes = $page('next194 checkpoint database page 1') . $page('next194 checkpoint database page 2');
$makeWalBytes = static function (array $frames, int $checkpointSequence = 194) use ($pageSize): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, 0x19419401, 0x19419402);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, 0x19419401, 0x19419402);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};
$walBytes = $makeWalBytes([
    [1, 0, $page('next194 uncommitted option batch')],
    [2, 2, $page('next194 committed retry checkpoint')],
]);
$fence = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next187',
    'database_path' => $databasePath,
    'wal_path' => $walPath,
    'retry_reader_token' => 'wal-hot-journal-savepoint-checkpoint-next187:retry:next194',
    'next_wal_sha256' => hash('sha256', $walBytes),
    'can_admit_retry_checkpoint_source' => true,
    'requires_reader_reopen' => false,
    'post_apply_token_retired' => true,
    'hot_journal_observed' => true,
    'reader_page_numbers' => [1, 2],
    'reader_next_sources' => ['checkpoint-database', 'next-wal'],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next187',
        'directory_sync_verified',
    ],
];
$publication = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next190Plan(
    $fence,
    [
        $databasePath => $databaseBytes,
        $walPath => $walBytes,
        $journalPath => null,
    ],
    $databaseBytes,
    $pageSize,
    194
);
$ticket = static fn (array $overrides = []): array => array_merge([
    'ticket_id' => 'reader-ticket-next194-a',
    'reader_epoch' => 195,
    'page_number' => 2,
    'source' => 'next-wal',
    'publication_token' => $publication['publication_token'],
    'database_sha256' => $publication['database_sha256'],
    'wal_sha256' => $publication['wal_sha256'],
    'checkpoint_sequence' => 194,
    'page_size' => $pageSize,
    'hot_journal_sha256' => null,
    'has_directory_sync_receipt' => true,
    'has_exclusive_checkpoint_lock_receipt' => true,
    'savepoint_closed' => true,
], $overrides);
$tickets = [
    $ticket(),
    $ticket([
        'ticket_id' => 'reader-ticket-next194-b',
        'reader_epoch' => 196,
        'page_number' => 1,
        'source' => 'checkpoint-database',
    ]),
];
$plan = static fn (?array $publicationInput = null, ?array $ticketRows = null, int $previousEpoch = 190, bool $requireLock = true): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next194SealReaderGeneration(
        $publicationInput ?? $publication,
        $ticketRows ?? $tickets,
        $previousEpoch,
        $requireLock
    );

$badPublicationStatus = $publication;
$badPublicationStatus['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next190';
$badPublicationPublish = $publication;
$badPublicationPublish['can_publish_retry_checkpoint_source'] = false;
$badPublicationJournal = $publication;
$badPublicationJournal['journal_present'] = true;
$badPublicationChecksum = $publication;
$badPublicationChecksum['wal_checksums_validated'] = false;
$badPublicationCommit = $publication;
$badPublicationCommit['wal_commit_frame_count'] = 0;
$duplicateTickets = [$ticket(), $ticket(['page_number' => 1, 'source' => 'checkpoint-database'])];
$staleEpoch = [$ticket(['reader_epoch' => 190])];
$badPage = [$ticket(['page_number' => 9])];
$badSource = [$ticket(['source' => 'stale-hot-journal'])];
$badPublicationToken = [$ticket(['publication_token' => 'wrong-token'])];
$badDatabase = [$ticket(['database_sha256' => hash('sha256', 'wrong db')])];
$badWal = [$ticket(['wal_sha256' => hash('sha256', 'wrong wal')])];
$badCheckpoint = [$ticket(['checkpoint_sequence' => 195])];
$badPageSize = [$ticket(['page_size' => 1024])];
$hotJournalRetained = [$ticket(['hot_journal_sha256' => hash('sha256', 'stale hot journal')])];
$missingSync = [$ticket(['has_directory_sync_receipt' => false])];
$missingLock = [$ticket(['has_exclusive_checkpoint_lock_receipt' => false])];
$openSavepoint = [$ticket(['savepoint_closed' => false])];
$combinedBad = [$ticket([
    'reader_epoch' => 190,
    'page_number' => 9,
    'source' => 'stale-hot-journal',
    'publication_token' => 'wrong-token',
    'database_sha256' => hash('sha256', 'wrong db'),
    'wal_sha256' => hash('sha256', 'wrong wal'),
    'checkpoint_sequence' => 195,
    'page_size' => 1024,
    'hot_journal_sha256' => hash('sha256', 'stale hot journal'),
    'has_directory_sync_receipt' => false,
    'has_exclusive_checkpoint_lock_receipt' => false,
    'savepoint_closed' => false,
])];

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next194'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reopened_reader_generation_sealed_to_retry_checkpoint_publication'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $walPath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'publication token' => [static fn (): mixed => $plan()['publication_token'], $publication['publication_token']],
    'previous epoch' => [static fn (): mixed => $plan()['previous_reader_epoch'], 190],
    'sealed epoch' => [static fn (): mixed => $plan()['sealed_reader_epoch'], 196],
    'ticket count' => [static fn (): mixed => $plan()['reader_ticket_count'], 2],
    'ticket ids' => [static fn (): mixed => $plan()['reader_ticket_ids'], ['reader-ticket-next194-a', 'reader-ticket-next194-b']],
    'reader pages' => [static fn (): mixed => $plan()['reader_pages'], [2, 1]],
    'reader sources' => [static fn (): mixed => $plan()['reader_sources'], ['next-wal', 'checkpoint-database']],
    'requires lock' => [static fn (): mixed => $plan()['requires_exclusive_checkpoint_lock'], true],
    'can expose readers' => [static fn (): mixed => $plan()['can_expose_reopened_readers'], true],
    'blocked empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'seal digest stable' => [static fn (): mixed => $plan()['seal_digest'], $plan()['seal_digest']],
    'ticket row token matches' => [static fn (): mixed => $plan()['ticket_rows'][0]['publication_token_matches'], true],
    'ticket row db matches' => [static fn (): mixed => $plan()['ticket_rows'][0]['database_digest_matches'], true],
    'ticket row wal matches' => [static fn (): mixed => $plan()['ticket_rows'][0]['wal_digest_matches'], true],
    'ticket row sync' => [static fn (): mixed => $plan()['ticket_rows'][0]['has_directory_sync_receipt'], true],
    'ticket row lock' => [static fn (): mixed => $plan()['ticket_rows'][0]['has_exclusive_checkpoint_lock_receipt'], true],
    'ticket row savepoint closed' => [static fn (): mixed => $plan()['ticket_rows'][0]['savepoint_closed'], true],
    'ticket row no hot journal' => [static fn (): mixed => $plan()['ticket_rows'][0]['hot_journal_digest_retained'], false],
    'dependency next190' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next190', $plan()['dependencies'], true), true],
    'dependency next194' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next194', $plan()['dependencies'], true), true],
    'application dependency' => [static fn (): mixed => in_array('application-import-retry-checkpoint-reader-exposure', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'bad publication status blocks' => [static fn (): mixed => $plan($badPublicationStatus)['blocked_reasons'], ['next190_retry_checkpoint_publication_required']],
    'bad publication publish blocks' => [static fn (): mixed => $plan($badPublicationPublish)['blocked_reasons'], ['next190_retry_checkpoint_source_not_publishable']],
    'bad publication journal blocks' => [static fn (): mixed => $plan($badPublicationJournal)['blocked_reasons'], ['hot_journal_must_be_absent_before_reader_generation_seal']],
    'bad publication checksum blocks' => [static fn (): mixed => $plan($badPublicationChecksum)['blocked_reasons'], ['retry_checkpoint_wal_checksums_required_before_reader_generation_seal']],
    'bad publication commit blocks' => [static fn (): mixed => $plan($badPublicationCommit)['blocked_reasons'], ['retry_checkpoint_commit_frame_required_before_reader_generation_seal']],
    'duplicate ticket blocks' => [static fn (): mixed => $plan(null, $duplicateTickets)['blocked_reasons'], ['duplicate_reader_ticket_id_after_retry_checkpoint_publication']],
    'stale epoch blocks' => [static fn (): mixed => $plan(null, $staleEpoch)['blocked_reasons'], ['reader_epoch_must_advance_after_retry_checkpoint_publication']],
    'bad page blocks' => [static fn (): mixed => $plan(null, $badPage)['blocked_reasons'], ['reader_ticket_page_not_in_next190_reader_page_set']],
    'bad source blocks' => [static fn (): mixed => $plan(null, $badSource)['blocked_reasons'], ['reader_ticket_source_not_in_next190_reader_source_set']],
    'bad publication token blocks' => [static fn (): mixed => $plan(null, $badPublicationToken)['blocked_reasons'], ['reader_ticket_publication_token_mismatch']],
    'bad database blocks' => [static fn (): mixed => $plan(null, $badDatabase)['blocked_reasons'], ['reader_ticket_database_digest_mismatch']],
    'bad wal blocks' => [static fn (): mixed => $plan(null, $badWal)['blocked_reasons'], ['reader_ticket_wal_digest_mismatch']],
    'bad checkpoint blocks' => [static fn (): mixed => $plan(null, $badCheckpoint)['blocked_reasons'], ['reader_ticket_checkpoint_sequence_mismatch']],
    'bad page size blocks' => [static fn (): mixed => $plan(null, $badPageSize)['blocked_reasons'], ['reader_ticket_page_size_mismatch']],
    'hot journal retained blocks' => [static fn (): mixed => $plan(null, $hotJournalRetained)['blocked_reasons'], ['reader_ticket_retains_hot_journal_digest']],
    'missing sync blocks' => [static fn (): mixed => $plan(null, $missingSync)['blocked_reasons'], ['reader_ticket_missing_directory_sync_receipt']],
    'missing lock blocks' => [static fn (): mixed => $plan(null, $missingLock)['blocked_reasons'], ['reader_ticket_missing_exclusive_checkpoint_lock_receipt']],
    'missing lock optional admits' => [static fn (): mixed => $plan(null, $missingLock, 190, false)['can_expose_reopened_readers'], true],
    'open savepoint blocks' => [static fn (): mixed => $plan(null, $openSavepoint)['blocked_reasons'], ['reader_ticket_savepoint_not_closed']],
    'combined blocks' => [static fn (): mixed => $plan(null, $combinedBad)['blocked_reasons'], [
        'reader_epoch_must_advance_after_retry_checkpoint_publication',
        'reader_ticket_page_not_in_next190_reader_page_set',
        'reader_ticket_source_not_in_next190_reader_source_set',
        'reader_ticket_publication_token_mismatch',
        'reader_ticket_database_digest_mismatch',
        'reader_ticket_wal_digest_mismatch',
        'reader_ticket_checkpoint_sequence_mismatch',
        'reader_ticket_page_size_mismatch',
        'reader_ticket_retains_hot_journal_digest',
        'reader_ticket_missing_directory_sync_receipt',
        'reader_ticket_missing_exclusive_checkpoint_lock_receipt',
        'reader_ticket_savepoint_not_closed',
    ]],
    'blocked status' => [static fn (): mixed => $plan(null, $staleEpoch)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next194'],
    'blocked reason' => [static fn (): mixed => $plan(null, $staleEpoch)['reason'], 'reopened_reader_generation_waits_for_retry_checkpoint_receipts'],
    'blocked cannot expose' => [static fn (): mixed => $plan(null, $staleEpoch)['can_expose_reopened_readers'], false],
    'blocked sealed epoch null' => [static fn (): mixed => $plan(null, $staleEpoch)['sealed_reader_epoch'], null],
    'blocked ticket row reason' => [static fn (): mixed => $plan(null, $staleEpoch)['ticket_rows'][0]['blocked_reasons'], ['reader_epoch_must_advance_after_retry_checkpoint_publication']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next194 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing publication key rejected' => static function () use ($plan, $publication): void {
        $bad = $publication;
        unset($bad['publication_token']);
        $plan($bad);
    },
    'empty publication path rejected' => static function () use ($plan, $publication): void {
        $bad = $publication;
        $bad['database_path'] = '';
        $plan($bad);
    },
    'bad publication checkpoint rejected' => static function () use ($plan, $publication): void {
        $bad = $publication;
        $bad['expected_checkpoint_sequence'] = -1;
        $plan($bad);
    },
    'bad publication page size rejected' => static function () use ($plan, $publication): void {
        $bad = $publication;
        $bad['expected_page_size'] = 513;
        $plan($bad);
    },
    'bad publication pages rejected' => static function () use ($plan, $publication): void {
        $bad = $publication;
        $bad['reader_page_numbers'] = [0];
        $plan($bad);
    },
    'bad publication sources rejected' => static function () use ($plan, $publication): void {
        $bad = $publication;
        $bad['reader_next_sources'] = [''];
        $plan($bad);
    },
    'negative previous epoch rejected' => static fn () => $plan(null, null, -1),
    'empty tickets rejected' => static fn () => $plan(null, []),
    'non array ticket rejected' => static fn () => $plan(null, ['bad']),
    'empty ticket id rejected' => static fn () => $plan(null, [$ticket(['ticket_id' => ''])]),
    'bad ticket epoch rejected' => static fn () => $plan(null, [$ticket(['reader_epoch' => '195'])]),
    'bad hot journal digest rejected' => static fn () => $plan(null, [$ticket(['hot_journal_sha256' => []])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next194 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
