<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next154.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next154 clean sqlite header before crash'),
    2 => $page('next154 clean options root before crash'),
    3 => $page('next154 clean active plugins before crash'),
    4 => $page('next154 clean autoload index before crash'),
    5 => $page('next154 clean rewrite rules before crash'),
];
$dirtyDatabase = $page('next154 dirty sqlite header from crashed savepoint')
    . $page('next154 dirty options root from crashed savepoint')
    . $page('next154 dirty active plugins from crashed savepoint')
    . $page('next154 dirty autoload index from crashed savepoint')
    . $page('next154 dirty rewrite rules from crashed savepoint');
$hotDatabase = implode('', $cleanPages);

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026154) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $checkpoint = 154, int $salt1 = 0x15415401, int $salt2 = 0x15415402) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
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

$journalBytes = $makeJournalBytes($cleanPages);
$walBytes = $makeWalBytes([
    [1, 0, 'next154 savepoint-visible schema draft'],
    [2, 5, 'next154 savepoint-visible options commit'],
    [3, 5, 'next154 savepoint-visible active plugins draft'],
    [4, 5, 'next154 tail autoload after failed savepoint'],
    [5, 5, 'next154 tail rewrite after failed savepoint'],
]);
$currentWal = SQLiteWal::parse($walBytes, $pageSize, true);
$checkpointDatabase = $page('next154 savepoint-visible schema draft')
    . $page('next154 savepoint-visible options commit')
    . $page('next154 savepoint-visible active plugins draft')
    . $page('next154 clean autoload index before crash')
    . $page('next154 clean rewrite rules before crash');
$tailCheckpointDatabase = $page('next154 savepoint-visible schema draft')
    . $page('next154 savepoint-visible options commit')
    . $page('next154 savepoint-visible active plugins draft')
    . $page('next154 tail autoload after failed savepoint')
    . $page('next154 tail rewrite after failed savepoint');
$badCheckpointDatabase = $page('next154 savepoint-visible schema draft')
    . $page('next154 bad options checkpoint from tail')
    . $page('next154 savepoint-visible active plugins draft')
    . $page('next154 clean autoload index before crash')
    . $page('next154 clean rewrite rules before crash');

$plan = static fn (
    string $readerDatabaseBytes = null,
    string $readerWalBytes = null,
    string $checkpointBytes = null,
    int $savepointEndFrame = 3,
    ?int $readerEndFrame = 3,
    bool $reservedLock = false,
    array $pages = [1, 2, 3, 4, 5],
    string $savepoint = 'plugin-batch',
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next154Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $currentWal,
    $walBytes,
    $readerDatabaseBytes ?? $hotDatabase,
    $readerWalBytes ?? $walBytes,
    $checkpointBytes ?? $checkpointDatabase,
    $savepoint,
    $savepointEndFrame,
    $pages,
    $readerEndFrame,
    $reservedLock
);

$ready = static fn (): array => $plan();
$tailCheckpoint = static fn (): array => $plan($hotDatabase, $walBytes, $tailCheckpointDatabase);
$badCheckpoint = static fn (): array => $plan($hotDatabase, $walBytes, $badCheckpointDatabase);
$readerPastSavepoint = static fn (): array => $plan($hotDatabase, $walBytes, $checkpointDatabase, 3, 5);
$readerBeforeSavepoint = static fn (): array => $plan($hotDatabase, $walBytes, $checkpointDatabase, 4, 3);
$notHot = static fn (): array => $plan($hotDatabase, $walBytes, $checkpointDatabase, 3, 3, true);
$single = static fn (): array => $plan($hotDatabase, $walBytes, $checkpointDatabase, 3, 3, false, [4]);

$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next154'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'checkpoint_database_matches_savepoint_visible_current_source_after_hot_journal_recovery'],
    'tail checkpoint status' => [static fn (): mixed => $tailCheckpoint()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-deferred-next154'],
    'bad checkpoint status' => [static fn (): mixed => $badCheckpoint()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-deferred-next154'],
    'reader past savepoint status' => [static fn (): mixed => $readerPastSavepoint()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-deferred-next154'],
    'reader before savepoint status' => [static fn (): mixed => $readerBeforeSavepoint()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-deferred-next154'],
    'not hot status' => [static fn (): mixed => $notHot()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next154'],
    'database path' => [static fn (): mixed => $ready()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ready()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ready()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ready()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $ready()['savepoint'], 'plugin-batch'],
    'savepoint frame' => [static fn (): mixed => $ready()['savepoint_end_frame'], 3],
    'reader frame' => [static fn (): mixed => $ready()['reader_end_frame'], 3],
    'current end frame' => [static fn (): mixed => $ready()['current_end_frame'], 5],
    'hot recovered' => [static fn (): mixed => $ready()['hot_recovered'], true],
    'not hot recovered false' => [static fn (): mixed => $notHot()['hot_recovered'], false],
    'base checkpoint allowed' => [static fn (): mixed => $ready()['base_checkpoint_allowed'], true],
    'checkpoint allowed' => [static fn (): mixed => $ready()['checkpoint_allowed'], true],
    'tail checkpoint disallowed' => [static fn (): mixed => $tailCheckpoint()['checkpoint_allowed'], false],
    'reader before savepoint disallowed' => [static fn (): mixed => $readerBeforeSavepoint()['checkpoint_allowed'], false],
    'checkpoint matches savepoint' => [static fn (): mixed => $ready()['checkpoint_matches_savepoint'], true],
    'tail checkpoint mismatch' => [static fn (): mixed => $tailCheckpoint()['checkpoint_matches_savepoint'], false],
    'reader preserved' => [static fn (): mixed => $ready()['reader_preserved_at_savepoint'], true],
    'reader before savepoint preserved false' => [static fn (): mixed => $readerBeforeSavepoint()['reader_preserved_at_savepoint'], false],
    'tail frames discarded' => [static fn (): mixed => $ready()['tail_frames_discarded_by_savepoint'], true],
    'mismatch pages empty' => [static fn (): mixed => $ready()['checkpoint_mismatched_page_numbers'], []],
    'tail checkpoint mismatch pages' => [static fn (): mixed => $tailCheckpoint()['checkpoint_mismatched_page_numbers'], [4, 5]],
    'bad checkpoint mismatch pages' => [static fn (): mixed => $badCheckpoint()['checkpoint_mismatched_page_numbers'], [2]],
    'tail discarded pages' => [static fn (): mixed => $ready()['tail_discarded_page_numbers'], [4, 5]],
    'tail discarded count' => [static fn (): mixed => $ready()['tail_discarded_page_count'], 2],
    'reader sources' => [static fn (): mixed => $ready()['reader_sources'], ['wal', 'wal', 'wal', 'database', 'database']],
    'reader frames' => [static fn (): mixed => $ready()['reader_frame_indexes'], [1, 2, 3, null, null]],
    'savepoint sources' => [static fn (): mixed => $ready()['savepoint_sources'], ['wal', 'wal', 'wal', 'database', 'database']],
    'savepoint frames' => [static fn (): mixed => $ready()['savepoint_frame_indexes'], [1, 2, 3, null, null]],
    'tail sources' => [static fn (): mixed => $ready()['tail_sources'], ['wal', 'wal', 'wal', 'wal', 'wal']],
    'tail frames' => [static fn (): mixed => $ready()['tail_frame_indexes'], [1, 2, 3, 4, 5]],
    'checkpoint sources' => [static fn (): mixed => $ready()['checkpoint_sources'], ['checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'reader label page one' => [static fn (): mixed => $ready()['reader_labels'][0], 'next154 savepoint-visible schema draft'],
    'savepoint label page three' => [static fn (): mixed => $ready()['savepoint_labels'][2], 'next154 savepoint-visible active plugins draft'],
    'tail label page four' => [static fn (): mixed => $ready()['tail_labels'][3], 'next154 tail autoload after failed savepoint'],
    'checkpoint label page four' => [static fn (): mixed => $ready()['checkpoint_labels'][3], 'next154 clean autoload index before crash'],
    'checkpoint labels match savepoint labels' => [static fn (): mixed => $ready()['checkpoint_labels'], $ready()['savepoint_labels']],
    'tail labels differ from checkpoint' => [static fn (): mixed => $ready()['tail_labels'] !== $ready()['checkpoint_labels'], true],
    'checkpoint database page count' => [static fn (): mixed => $ready()['checkpoint_database_source']['page_count'], 5],
    'checkpoint sha length' => [static fn (): mixed => strlen($ready()['checkpoint_database_source']['sha256']), 64],
    'hot database page count' => [static fn (): mixed => $ready()['hot_database_source']['page_count'], 5],
    'current wal frame count' => [static fn (): mixed => $ready()['current_wal_source']['frame_count'], 5],
    'reader wal checkpoint sequence' => [static fn (): mixed => $ready()['reader_wal_source']['checkpoint_sequence'], 154],
    'row count' => [static fn (): mixed => count($ready()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($ready()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'row four tail differs' => [static fn (): mixed => $ready()['rows'][3]['tail_differs_from_savepoint'], true],
    'row four checkpoint matches savepoint' => [static fn (): mixed => $ready()['rows'][3]['checkpoint_matches_savepoint'], true],
    'tail checkpoint row four mismatch' => [static fn (): mixed => $tailCheckpoint()['rows'][3]['checkpoint_matches_savepoint'], false],
    'source transitions' => [static fn (): mixed => $ready()['source_transitions'], [
        'wal>savepoint-wal>tail-wal>checkpoint-db',
        'wal>savepoint-wal>tail-wal>checkpoint-db',
        'wal>savepoint-wal>tail-wal>checkpoint-db',
        'database>savepoint-database>tail-wal>checkpoint-db',
        'database>savepoint-database>tail-wal>checkpoint-db',
    ]],
    'source digest length' => [static fn (): mixed => strlen($ready()['source_digest']), 64],
    'operation apply checkpoint' => [static fn (): mixed => in_array('apply_checkpoint_at_savepoint_visible_frame_next154', $ready()['operation_reasons'], true), true],
    'operation discard tail' => [static fn (): mixed => in_array('discard_wal_tail_after_savepoint_before_reset_next154', $ready()['operation_reasons'], true), true],
    'operation defer' => [static fn (): mixed => in_array('defer_checkpoint_until_savepoint_visible_source_matches_next154', $tailCheckpoint()['operation_reasons'], true), true],
    'base status' => [static fn (): mixed => $ready()['base_plan']['status'], 'wal-checkpoint-hot-journal-reader-current-source-next144'],
    'base not hot status' => [static fn (): mixed => $notHot()['base_plan']['status'], 'wal-checkpoint-hot-journal-reader-current-source-blocked-next144'],
    'dependency next154' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next154', $ready()['dependencies'], true), true],
    'dependency next144' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-hot-journal-reader-current-source-next144', $ready()['dependencies'], true), true],
    'dependency savepoint boundary' => [static fn (): mixed => in_array('sqlite-savepoint-visible-wal-frame-boundary', $ready()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ready()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ready()['non_overlap'], 'savepoint-visible WAL frame boundary'), true],
    'single page status' => [static fn (): mixed => $single()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next154'],
    'single page savepoint source' => [static fn (): mixed => $single()['savepoint_sources'], ['database']],
    'single page tail discarded' => [static fn (): mixed => $single()['tail_discarded_page_numbers'], [4]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next154 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty checkpoint database rejected' => static fn () => $plan($hotDatabase, $walBytes, ''),
    'empty savepoint rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 3, 3, false, [1], ''),
    'negative savepoint frame rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, -1),
    'past savepoint frame rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 6),
    'unaligned checkpoint rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase . 'x'),
    'empty path rejected by base' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next154Plan('', $dirtyDatabase, $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, $checkpointDatabase, 'sp', 3, [1], 1),
    'empty dirty database rejected by base' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next154Plan($databasePath, '', $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, $checkpointDatabase, 'sp', 3, [1], 1),
    'empty journal rejected by base' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next154Plan($databasePath, $dirtyDatabase, '', $currentWal, $walBytes, $hotDatabase, $walBytes, $checkpointDatabase, 'sp', 3, [1], 1),
    'empty wal rejected by base' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next154Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, '', $hotDatabase, $walBytes, $checkpointDatabase, 'sp', 3, [1], 1),
    'empty reader database rejected by base' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next154Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $walBytes, '', $walBytes, $checkpointDatabase, 'sp', 3, [1], 1),
    'empty pages rejected by base' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next154Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, $checkpointDatabase, 'sp', 3, [], 1),
    'zero page rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 3, 3, false, [0]),
    'string page rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 3, 3, false, ['1']),
    'page outside checkpoint rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 3, 3, false, [6]),
    'reader past wal rejected by base' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 3, 6),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next154 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
