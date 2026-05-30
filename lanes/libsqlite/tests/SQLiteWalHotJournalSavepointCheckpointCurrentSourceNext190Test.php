<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/srv/www/wp-content/database/wp-next190.sqlite';
$walPath = $databasePath . '-wal';
$journalPath = $databasePath . '-journal';
$databaseBytes = $page('next190 checkpoint database page 1') . $page('next190 checkpoint database page 2');
$makeWalBytes = static function (array $frames, int $checkpointSequence = 190) use ($pageSize): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, 0x19019001, 0x19019002);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, 0x19019001, 0x19019002);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};
$walBytes = $makeWalBytes([
    [1, 0, $page('next190 uncommitted option cache')],
    [2, 2, $page('next190 committed retry checkpoint')],
]);
$fence = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next187',
    'database_path' => $databasePath,
    'wal_path' => $walPath,
    'retry_reader_token' => 'wal-hot-journal-savepoint-checkpoint-next187:retry:next190',
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
$walCheckpointFiles = [
    $databasePath => $databaseBytes,
    $walPath => $walBytes,
    $journalPath => null,
];
$plan = static fn (?array $readerFence = null, ?array $fileMap = null, ?string $expectedDb = null, int $checkpoint = 190, bool $requireDirectorySync = true): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next190Plan(
        $readerFence ?? $fence,
        $fileMap ?? $walCheckpointFiles,
        $expectedDb ?? $databaseBytes,
        $pageSize,
        $checkpoint,
        $requireDirectorySync
    );

$badFenceStatus = $fence;
$badFenceStatus['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next187';
$badFenceAdmit = $fence;
$badFenceAdmit['can_admit_retry_checkpoint_source'] = false;
$badFenceReopen = $fence;
$badFenceReopen['requires_reader_reopen'] = true;
$badFenceToken = $fence;
$badFenceToken['post_apply_token_retired'] = false;
$badFenceJournal = $fence;
$badFenceJournal['hot_journal_observed'] = false;
$badSyncFence = $fence;
$badSyncFence['dependencies'] = ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next187'];
$missingDatabase = $walCheckpointFiles;
unset($missingDatabase[$databasePath]);
$driftDatabase = $walCheckpointFiles;
$driftDatabase[$databasePath] = $page('wrong database page') . $page('wrong page two');
$missingWal = $walCheckpointFiles;
unset($missingWal[$walPath]);
$emptyWal = $walCheckpointFiles;
$emptyWal[$walPath] = '';
$badWal = $walCheckpointFiles;
$badWal[$walPath] = substr($walBytes, 0, -3) . 'bad';
$wrongSequenceWal = $walCheckpointFiles;
$wrongSequenceWal[$walPath] = $makeWalBytes([[2, 2, $page('wrong checkpoint sequence')]], 191);
$uncommittedWal = $walCheckpointFiles;
$uncommittedWal[$walPath] = $makeWalBytes([[1, 0, $page('only uncommitted frame')]]);
$journalPresent = $walCheckpointFiles;
$journalPresent[$journalPath] = 'leftover hot journal';
$combinedBad = $missingDatabase;
$combinedBad[$walPath] = '';
$combinedBad[$journalPath] = 'leftover hot journal';

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next190'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'retry_checkpoint_publication_matches_reader_fence_and_current_files'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $walPath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'reader token' => [static fn (): mixed => $plan()['reader_retry_token'], $fence['retry_reader_token']],
    'publication token prefix' => [static fn (): mixed => str_starts_with($plan()['publication_token'], 'wal-hot-journal-savepoint-checkpoint-next190:publish:'), true],
    'publication token stable' => [static fn (): mixed => $plan()['publication_token'], $plan()['publication_token']],
    'database digest' => [static fn (): mixed => $plan()['database_sha256'], hash('sha256', $databaseBytes)],
    'expected database digest' => [static fn (): mixed => $plan()['expected_database_sha256'], hash('sha256', $databaseBytes)],
    'wal digest' => [static fn (): mixed => $plan()['wal_sha256'], hash('sha256', $walBytes)],
    'expected wal digest' => [static fn (): mixed => $plan()['expected_wal_sha256'], hash('sha256', $walBytes)],
    'journal absent' => [static fn (): mixed => $plan()['journal_present'], false],
    'wal header sequence' => [static fn (): mixed => $plan()['wal_header']['checkpoint_sequence'], 190],
    'wal byte order' => [static fn (): mixed => $plan()['wal_byte_order'], 'big-endian'],
    'wal checksums' => [static fn (): mixed => $plan()['wal_checksums_validated'], true],
    'wal frame count' => [static fn (): mixed => $plan()['wal_frame_count'], 2],
    'wal commit frame count' => [static fn (): mixed => $plan()['wal_commit_frame_count'], 1],
    'wal commit frame index' => [static fn (): mixed => $plan()['wal_commit_frames'][0]['frame_index'], 2],
    'wal commit page' => [static fn (): mixed => $plan()['wal_commit_frames'][0]['page_number'], 2],
    'last commit page count' => [static fn (): mixed => $plan()['last_commit_page_count'], 2],
    'expected checkpoint' => [static fn (): mixed => $plan()['expected_checkpoint_sequence'], 190],
    'expected page size' => [static fn (): mixed => $plan()['expected_page_size'], $pageSize],
    'reader pages' => [static fn (): mixed => $plan()['reader_page_numbers'], [1, 2]],
    'reader sources' => [static fn (): mixed => $plan()['reader_next_sources'], ['checkpoint-database', 'next-wal']],
    'can publish' => [static fn (): mixed => $plan()['can_publish_retry_checkpoint_source'], true],
    'requires directory sync' => [static fn (): mixed => $plan()['requires_directory_sync'], true],
    'blocked empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'dependency next187' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next187', $plan()['dependencies'], true), true],
    'dependency next190' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next190', $plan()['dependencies'], true), true],
    'application dependency' => [static fn (): mixed => in_array('application-import-retry-checkpoint-current-source-publication', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'bad fence status blocks' => [static fn (): mixed => $plan($badFenceStatus)['blocked_reasons'], ['next187_reader_token_fence_not_admitted']],
    'bad fence admit blocks' => [static fn (): mixed => $plan($badFenceAdmit)['blocked_reasons'], ['next187_reader_token_fence_not_admitted']],
    'bad fence reopen blocks' => [static fn (): mixed => $plan($badFenceReopen)['blocked_reasons'], ['next187_reader_token_fence_not_admitted']],
    'bad fence token blocks' => [static fn (): mixed => $plan($badFenceToken)['blocked_reasons'], ['next187_reader_token_fence_not_admitted']],
    'bad fence hot journal blocks' => [static fn (): mixed => $plan($badFenceJournal)['blocked_reasons'], ['next187_reader_token_fence_not_admitted']],
    'missing database blocks' => [static fn (): mixed => $plan(null, $missingDatabase)['blocked_reasons'], ['database_file_missing_after_retry_checkpoint_publication']],
    'drift database blocks' => [static fn (): mixed => $plan(null, $driftDatabase)['blocked_reasons'], ['database_file_digest_drift_after_retry_checkpoint_publication']],
    'missing wal blocks' => [static fn (): mixed => $plan(null, $missingWal)['blocked_reasons'], ['wal_file_missing_after_retry_checkpoint_publication']],
    'empty wal blocks' => [static fn (): mixed => $plan(null, $emptyWal)['blocked_reasons'], ['wal_file_missing_after_retry_checkpoint_publication']],
    'bad wal blocks' => [static fn (): mixed => $plan(null, $badWal)['blocked_reasons'], ['wal_retry_checkpoint_publication_checksum_or_header_invalid']],
    'bad wal header error' => [static fn (): mixed => isset($plan(null, $badWal)['wal_header']['error']), true],
    'wrong checkpoint blocks' => [static fn (): mixed => $plan(null, $wrongSequenceWal)['blocked_reasons'], ['wal_checkpoint_sequence_drift_after_retry_checkpoint_publication']],
    'uncommitted wal blocks' => [static fn (): mixed => $plan(null, $uncommittedWal)['blocked_reasons'], ['wal_retry_checkpoint_publication_has_no_commit_frame']],
    'journal present blocks' => [static fn (): mixed => $plan(null, $journalPresent)['blocked_reasons'], ['hot_journal_file_must_be_absent_after_retry_checkpoint_publication']],
    'missing directory sync blocks' => [static fn (): mixed => $plan($badSyncFence)['blocked_reasons'], ['directory_sync_evidence_required_for_retry_checkpoint_publication']],
    'directory sync optional admits' => [static fn (): mixed => $plan($badSyncFence, null, null, 190, false)['can_publish_retry_checkpoint_source'], true],
    'combined blocks' => [static fn (): mixed => $plan($badFenceStatus, $combinedBad)['blocked_reasons'], [
        'next187_reader_token_fence_not_admitted',
        'database_file_missing_after_retry_checkpoint_publication',
        'wal_file_missing_after_retry_checkpoint_publication',
        'hot_journal_file_must_be_absent_after_retry_checkpoint_publication',
    ]],
    'blocked status' => [static fn (): mixed => $plan($badFenceStatus)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next190'],
    'blocked reason' => [static fn (): mixed => $plan($badFenceStatus)['reason'], 'retry_checkpoint_publication_waits_for_current_source_file_map'],
    'blocked cannot publish' => [static fn (): mixed => $plan($badFenceStatus)['can_publish_retry_checkpoint_source'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next190 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing fence key rejected' => static function () use ($plan, $fence): void {
        $bad = $fence;
        unset($bad['retry_reader_token']);
        $plan($bad);
    },
    'empty database path rejected' => static function () use ($plan, $fence): void {
        $bad = $fence;
        $bad['database_path'] = '';
        $plan($bad);
    },
    'empty wal path rejected' => static function () use ($plan, $fence): void {
        $bad = $fence;
        $bad['wal_path'] = '';
        $plan($bad);
    },
    'empty retry token rejected' => static function () use ($plan, $fence): void {
        $bad = $fence;
        $bad['retry_reader_token'] = '';
        $plan($bad);
    },
    'bad fence arrays rejected' => static function () use ($plan, $fence): void {
        $bad = $fence;
        $bad['dependencies'] = 'bad';
        $plan($bad);
    },
    'bad file path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next190Plan($fence, ['' => 'x'], $databaseBytes, $pageSize, 190),
    'bad file bytes rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next190Plan($fence, [$databasePath => []], $databaseBytes, $pageSize, 190),
    'empty expected database rejected' => static fn () => $plan(null, null, ''),
    'bad page size rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next190Plan($fence, $walCheckpointFiles, $databaseBytes, 513, 190),
    'negative checkpoint rejected' => static fn () => $plan(null, null, null, -1),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next190 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
