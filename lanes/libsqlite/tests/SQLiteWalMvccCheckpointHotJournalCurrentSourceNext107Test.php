<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMvccCheckpointHotJournalCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/wp-next107.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$clean = [
    1 => $page('next107 clean sqlite header before plugin import'),
    2 => $page('next107 clean wp_options root before plugin import'),
    3 => $page('next107 clean active_plugins before plugin import'),
    4 => $page('next107 clean autoload index before plugin import'),
];
$dirtyDatabase = $page('next107 dirty sqlite header after crashed import')
    . $page('next107 dirty wp_options root after crashed import')
    . $page('next107 dirty active_plugins after crashed import')
    . $page('next107 dirty autoload index after crashed import');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026107) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, 4, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $salt2 = 0x20260107) use ($pageSize, $page): string {
    $salt1 = 0x20260528;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 107, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$journalBytes = $makeJournalBytes($clean);
$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$walBytes = $makeWalBytes([
    [1, 0, 'next107 wal schema draft from recovered database'],
    [2, 4, 'next107 wal committed wp_options root'],
    [3, 0, 'next107 wal uncommitted active_plugins tail'],
    [4, 4, 'next107 wal committed autoload index tail'],
    [2, 0, 'next107 wal dirty uncommitted option overwrite'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$staleWalBytes = $makeWalBytes([
    [1, 0, 'next107 stale wal schema draft'],
    [2, 4, 'next107 wal committed wp_options root'],
    [3, 0, 'next107 wal uncommitted active_plugins tail'],
    [4, 4, 'next107 wal committed autoload index tail'],
], 0x20260108);

$plan = static fn (
    array $pages = [1, 2, 3, 4],
    ?SQLiteRollbackJournal $journalInput = null,
    ?string $journalBytesInput = null,
    ?string $walBytesInput = null,
    bool $reservedLock = false,
    bool $requiresSuper = false,
    ?bool $superExists = null,
): array => SQLiteWalMvccCheckpointHotJournalCurrentSourceNextPlan::plan(
    $journalInput ?? $journal,
    $dirtyDatabase,
    $journalBytesInput ?? $journalBytes,
    $walBytesInput ?? $walBytes,
    $databasePath,
    $pages,
    $pageSize,
    $reservedLock,
    $requiresSuper,
    $superExists
);

$blocked = static fn (): array => $plan([1, 2], null, null, null, false, true, false);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-mvcc-hot-journal-checkpoint-current-source-next107'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'hot_journal_restored_before_committed_wal_checkpoint_mvcc_boundary'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'pages' => [static fn (): mixed => $plan()['page_numbers'], [1, 2, 3, 4]],
    'dirty reader end frame' => [static fn (): mixed => $plan()['dirty_reader_end_frame'], 5],
    'hot reader end frame' => [static fn (): mixed => $plan()['hot_reader_end_frame'], 4],
    'next reader end frame' => [static fn (): mixed => $plan()['next_reader_end_frame'], 0],
    'hot recovered' => [static fn (): mixed => $plan()['hot_recovered'], true],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'delete_journal_after_recovery'],
    'journal bytes match' => [static fn (): mixed => $plan()['journal_bytes_match'], true],
    'wal bytes match' => [static fn (): mixed => $plan()['wal_bytes_match'], true],
    'wal status' => [static fn (): mixed => $plan()['wal_status'], 'recovered_committed_prefix'],
    'committed frame count' => [static fn (): mixed => $plan()['committed_frame_count'], 4],
    'discarded valid tail' => [static fn (): mixed => $plan()['discarded_valid_tail_frame_count'], 1],
    'discarded corrupt tail' => [static fn (): mixed => $plan()['discarded_corrupt_tail_frame_count'], 0],
    'dirty sources' => [static fn (): mixed => $plan()['dirty_reader_sources'], ['wal', 'wal', 'wal', 'wal']],
    'hot sources' => [static fn (): mixed => $plan()['hot_reader_sources'], ['wal', 'wal', 'wal', 'wal']],
    'next sources' => [static fn (): mixed => $plan()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'dirty frames' => [static fn (): mixed => $plan()['dirty_reader_frame_indexes'], [1, 2, 3, 4]],
    'hot frames' => [static fn (): mixed => $plan()['hot_reader_frame_indexes'], [1, 2, 3, 4]],
    'next frames' => [static fn (): mixed => $plan()['next_reader_frame_indexes'], [null, null, null, null]],
    'dirty and hot match committed snapshot' => [static fn (): mixed => $plan()['dirty_to_hot_images_match'], true],
    'hot and next match' => [static fn (): mixed => $plan()['hot_to_next_images_match'], true],
    'dirty keeps wal' => [static fn (): mixed => $plan()['dirty_reader_keeps_original_wal_snapshot'], true],
    'hot uses recovered database flag' => [static fn (): mixed => $plan()['hot_reader_uses_recovered_database'], false],
    'next uses database' => [static fn (): mixed => $plan()['next_reader_uses_checkpoint_database'], true],
    'checkpoint bytes' => [static fn (): mixed => $plan()['checkpoint_database_bytes'], 4 * $pageSize],
    'hot database bytes' => [static fn (): mixed => $plan()['hot_database_bytes'], 4 * $pageSize],
    'committed wal bytes' => [static fn (): mixed => $plan()['committed_wal_bytes'], 32 + (4 * (24 + $pageSize))],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 11],
    'first op hot restore' => [static fn (): mixed => $plan()['operation_reasons'][0], 'restore_hot_journal_database_before_wal_recovery'],
    'third op delete journal' => [static fn (): mixed => $plan()['operation_reasons'][3], 'delete_hot_journal_before_wal_recovery'],
    'checkpoint op follows hot' => [static fn (): mixed => $plan()['operation_reasons'][4], 'checkpoint_committed_wal_after_hot_journal_recovery'],
    'wal prefix write op' => [static fn (): mixed => $plan()['operation_reasons'][7], 'restore_committed_wal_prefix_after_hot_journal_recovery'],
    'payload hot journal' => [static fn (): mixed => in_array($databasePath . '#hot-journal', $plan()['payload_keys'], true), true],
    'payload checkpoint' => [static fn (): mixed => in_array($databasePath . '#wal-checkpoint', $plan()['payload_keys'], true), true],
    'payload wal' => [static fn (): mixed => in_array($databasePath . '-wal', $plan()['payload_keys'], true), true],
    'dirty page two ignores uncommitted overwrite' => [static fn (): mixed => str_contains($plan()['dirty_reader'][1]['image'], 'dirty uncommitted option overwrite'), false],
    'hot page two is committed root' => [static fn (): mixed => str_contains($plan()['hot_reader'][1]['image'], 'committed wp_options root'), true],
    'next page two is committed root' => [static fn (): mixed => str_contains($plan()['next_reader'][1]['image'], 'committed wp_options root'), true],
    'dirty page three uses uncommitted tail' => [static fn (): mixed => str_contains($plan()['dirty_reader'][2]['image'], 'uncommitted active_plugins tail'), true],
    'hot page three uses uncommitted pre commit frame' => [static fn (): mixed => str_contains($plan()['hot_reader'][2]['image'], 'uncommitted active_plugins tail'), true],
    'next page three still checkpointed' => [static fn (): mixed => str_contains($plan()['next_reader'][2]['image'], 'uncommitted active_plugins tail'), true],
    'dirty page one schema draft' => [static fn (): mixed => str_contains($plan()['dirty_reader'][0]['image'], 'schema draft'), true],
    'next page four committed index' => [static fn (): mixed => str_contains($plan()['next_reader'][3]['image'], 'committed autoload index tail'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency next107' => [static fn (): mixed => in_array('sqlite-wal-mvcc-checkpoint-hotjournal-current-source-next107', $plan()['dependencies'], true), true],
    'dependency hot before checkpoint' => [static fn (): mixed => in_array('sqlite-hot-journal-before-wal-checkpoint-current-source', $plan()['dependencies'], true), true],
    'dependency mvcc boundary' => [static fn (): mixed => in_array('sqlite-mvcc-reader-boundary-current-next', $plan()['dependencies'], true), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-mvcc-hot-journal-checkpoint-skipped-current-source-next107'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'hot_journal_not_hot_committed_wal_checkpoint_mvcc_boundary'],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'blocked journal action preserve' => [static fn (): mixed => $blocked()['journal_action'], 'preserve_journal'],
    'blocked sources still resolve' => [static fn (): mixed => $blocked()['next_reader_sources'], ['database', 'database']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal mvcc checkpoint hotjournal current source next107 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => SQLiteWalMvccCheckpointHotJournalCurrentSourceNextPlan::plan($journal, $dirtyDatabase, $journalBytes, $walBytes, '', [1], $pageSize),
    'empty pages rejected' => static fn () => SQLiteWalMvccCheckpointHotJournalCurrentSourceNextPlan::plan($journal, $dirtyDatabase, $journalBytes, $walBytes, $databasePath, [], $pageSize),
    'zero page rejected' => static fn () => $plan([0]),
    'string page rejected' => static fn () => $plan(['1']),
    'mismatched journal bytes rejected' => static fn () => $plan([1], null, substr($journalBytes, 0, -1) . 'x'),
    'stale parsed journal rejected' => static fn () => $plan([1], SQLiteRollbackJournal::parse($makeJournalBytes([1 => $page('next107 stale clean page')]), true)),
    'stale wal checksum rejected' => static fn () => $plan([1], null, null, substr($walBytes, 0, -1) . 'x'),
    'truncated wal rejected' => static fn () => $plan([1], null, null, substr($walBytes, 0, -1)),
];

foreach ($throws as $name => $callback) {
    $tests['wal mvcc checkpoint hotjournal current source next107 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
