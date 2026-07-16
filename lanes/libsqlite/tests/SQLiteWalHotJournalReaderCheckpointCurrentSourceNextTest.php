<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next148.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next148 clean sqlite header before hot journal'),
    2 => $page('next148 clean wp_options root before hot journal'),
    3 => $page('next148 clean active_plugins before hot journal'),
    4 => $page('next148 clean autoload index before hot journal'),
    5 => $page('next148 clean rewrite rules before hot journal'),
];
$dirtyDatabase = $page('next148 dirty sqlite header from crashed import')
    . $page('next148 dirty wp_options root from crashed import')
    . $page('next148 dirty active_plugins from crashed import')
    . $page('next148 dirty autoload index from crashed import')
    . $page('next148 dirty rewrite rules from crashed import');
$hotDatabase = implode('', $cleanPages);

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026148) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $checkpoint = 148, int $salt1 = 0x14814801, int $salt2 = 0x14814802) use ($pageSize, $page): string {
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
    [1, 0, 'next148 current schema reader draft'],
    [2, 5, 'next148 current wp_options checkpoint commit'],
    [3, 0, 'next148 current active_plugins reader draft'],
    [4, 5, 'next148 current autoload checkpoint commit'],
]);
$currentWal = SQLiteWal::parse($walBytes, $pageSize, true);
$checkpointDatabase = $page('next148 current schema reader draft')
    . $page('next148 current wp_options checkpoint commit')
    . $page('next148 current active_plugins reader draft')
    . $page('next148 current autoload checkpoint commit')
    . $page('next148 clean rewrite rules before hot journal');
$badCheckpointDatabase = $page('next148 current schema reader draft')
    . $page('next148 current wp_options checkpoint commit')
    . $page('next148 bad active_plugins checkpoint source')
    . $page('next148 current autoload checkpoint commit')
    . $page('next148 clean rewrite rules before hot journal');
$staleWalBytes = $makeWalBytes([
    [1, 0, 'next148 stale schema reader draft'],
    [2, 5, 'next148 stale wp_options commit'],
], 147, 0x14714701, 0x14714702);

$plan = static fn (
    string $readerDatabaseBytes = null,
    string $readerWalBytes = null,
    string $checkpointBytes = null,
    bool $reservedLock = false,
    array $pages = [1, 2, 3, 4, 5],
    ?int $readerEndFrame = 4,
): array => SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan::plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $currentWal,
    $walBytes,
    $readerDatabaseBytes ?? $hotDatabase,
    $readerWalBytes ?? $walBytes,
    $checkpointBytes ?? $checkpointDatabase,
    $pages,
    $readerEndFrame,
    $reservedLock
);

$ready = static fn (): array => $plan();
$badCheckpoint = static fn (): array => $plan($hotDatabase, $walBytes, $badCheckpointDatabase);
$dirtyReader = static fn (): array => $plan($dirtyDatabase);
$staleWal = static fn (): array => $plan($hotDatabase, $staleWalBytes, $checkpointDatabase, false, [1, 2], 2);
$notHot = static fn (): array => $plan($hotDatabase, $walBytes, $checkpointDatabase, true);
$single = static fn (): array => $plan($hotDatabase, $walBytes, $checkpointDatabase, false, [5], 4);

$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-hot-journal-reader-checkpoint-current-source-next148'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'checkpoint_database_matches_hot_journal_current_source_before_reader_reset'],
    'bad checkpoint status' => [static fn (): mixed => $badCheckpoint()['status'], 'wal-hot-journal-reader-checkpoint-current-source-deferred-next148'],
    'dirty reader status' => [static fn (): mixed => $dirtyReader()['status'], 'wal-hot-journal-reader-checkpoint-current-source-deferred-next148'],
    'stale wal status' => [static fn (): mixed => $staleWal()['status'], 'wal-hot-journal-reader-checkpoint-current-source-deferred-next148'],
    'not hot status' => [static fn (): mixed => $notHot()['status'], 'wal-hot-journal-reader-checkpoint-current-source-blocked-next148'],
    'database path' => [static fn (): mixed => $ready()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ready()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ready()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ready()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $ready()['reader_end_frame'], 4],
    'hot recovered' => [static fn (): mixed => $ready()['hot_recovered'], true],
    'not hot recovered false' => [static fn (): mixed => $notHot()['hot_recovered'], false],
    'base checkpoint allowed' => [static fn (): mixed => $ready()['base_checkpoint_allowed'], true],
    'dirty base checkpoint blocked' => [static fn (): mixed => $dirtyReader()['base_checkpoint_allowed'], false],
    'checkpoint allowed' => [static fn (): mixed => $ready()['checkpoint_allowed'], true],
    'bad checkpoint disallowed' => [static fn (): mixed => $badCheckpoint()['checkpoint_allowed'], false],
    'reader reopen false' => [static fn (): mixed => $ready()['reader_reopen_required'], false],
    'dirty reader reopen true' => [static fn (): mixed => $dirtyReader()['reader_reopen_required'], true],
    'bad checkpoint reopen true' => [static fn (): mixed => $badCheckpoint()['reader_reopen_required'], true],
    'checkpoint matches expected' => [static fn (): mixed => $ready()['checkpoint_database_matches_expected'], true],
    'bad checkpoint mismatch' => [static fn (): mixed => $badCheckpoint()['checkpoint_database_matches_expected'], false],
    'bad mismatch pages' => [static fn (): mixed => $badCheckpoint()['checkpoint_mismatched_page_numbers'], [3]],
    'ready mismatch pages empty' => [static fn (): mixed => $ready()['checkpoint_mismatched_page_numbers'], []],
    'reader separated pages' => [static fn (): mixed => $ready()['reader_separated_from_checkpoint_page_numbers'], [1, 2, 3, 4, 5]],
    'reader separated count' => [static fn (): mixed => $ready()['reader_separated_from_checkpoint_page_count'], 5],
    'reader sources' => [static fn (): mixed => $ready()['reader_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'reader frames' => [static fn (): mixed => $ready()['reader_frame_indexes'], [1, 2, 3, 4, null]],
    'checkpoint expected sources' => [static fn (): mixed => $ready()['checkpoint_expected_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'checkpoint expected frames' => [static fn (): mixed => $ready()['checkpoint_expected_frame_indexes'], [1, 2, 3, 4, null]],
    'checkpoint actual sources' => [static fn (): mixed => $ready()['checkpoint_actual_sources'], ['checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'reader label page one' => [static fn (): mixed => $ready()['reader_labels'][0], 'next148 current schema reader draft'],
    'checkpoint label page two' => [static fn (): mixed => $ready()['checkpoint_actual_labels'][1], 'next148 current wp_options checkpoint commit'],
    'checkpoint label page five' => [static fn (): mixed => $ready()['checkpoint_actual_labels'][4], 'next148 clean rewrite rules before hot journal'],
    'expected labels match actual' => [static fn (): mixed => $ready()['checkpoint_expected_labels'], $ready()['checkpoint_actual_labels']],
    'bad expected labels differ' => [static fn (): mixed => $badCheckpoint()['checkpoint_expected_labels'][2] !== $badCheckpoint()['checkpoint_actual_labels'][2], true],
    'checkpoint database page count' => [static fn (): mixed => $ready()['checkpoint_database_source']['page_count'], 5],
    'checkpoint sha length' => [static fn (): mixed => strlen($ready()['checkpoint_database_source']['sha256']), 64],
    'hot database page count' => [static fn (): mixed => $ready()['hot_database_source']['page_count'], 5],
    'reader database page count' => [static fn (): mixed => $ready()['reader_database_source']['page_count'], 5],
    'current wal checkpoint sequence' => [static fn (): mixed => $ready()['current_wal_source']['checkpoint_sequence'], 148],
    'reader wal checkpoint sequence' => [static fn (): mixed => $ready()['reader_wal_source']['checkpoint_sequence'], 148],
    'stale reader wal checkpoint sequence' => [static fn (): mixed => $staleWal()['reader_wal_source']['checkpoint_sequence'], 147],
    'row count' => [static fn (): mixed => count($ready()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($ready()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'row three expected frame' => [static fn (): mixed => $ready()['rows'][2]['checkpoint_expected_frame'], 3],
    'row five expected source database' => [static fn (): mixed => $ready()['rows'][4]['checkpoint_expected_source'], 'database'],
    'row five actual source checkpoint database' => [static fn (): mixed => $ready()['rows'][4]['checkpoint_actual_source'], 'checkpoint-database'],
    'row five source transition' => [static fn (): mixed => $ready()['rows'][4]['source_transition'], 'database>database>checkpoint-db'],
    'row two checkpoint matches' => [static fn (): mixed => $ready()['rows'][1]['checkpoint_page_matches_expected'], true],
    'bad row three checkpoint mismatch' => [static fn (): mixed => $badCheckpoint()['rows'][2]['checkpoint_page_matches_expected'], false],
    'ready transitions' => [static fn (): mixed => $ready()['source_transitions'], [
        'wal>wal>checkpoint-db',
        'wal>wal>checkpoint-db',
        'wal>wal>checkpoint-db',
        'wal>wal>checkpoint-db',
        'database>database>checkpoint-db',
    ]],
    'source digest length' => [static fn (): mixed => strlen($ready()['source_digest']), 64],
    'operation apply checkpoint' => [static fn (): mixed => in_array('apply_checkpoint_from_hot_journal_current_source_next148', $ready()['operation_reasons'], true), true],
    'operation keep reader' => [static fn (): mixed => in_array('keep_reader_on_current_source_until_reopen_next148', $ready()['operation_reasons'], true), true],
    'operation defer' => [static fn (): mixed => in_array('defer_checkpoint_reset_until_current_source_matches_next148', $badCheckpoint()['operation_reasons'], true), true],
    'base status' => [static fn (): mixed => $ready()['base_plan']['status'], 'wal-checkpoint-hot-journal-reader-current-source-next144'],
    'base dirty status' => [static fn (): mixed => $dirtyReader()['base_plan']['status'], 'wal-checkpoint-hot-journal-reader-current-source-reopen-next144'],
    'base not hot status' => [static fn (): mixed => $notHot()['base_plan']['status'], 'wal-checkpoint-hot-journal-reader-current-source-blocked-next144'],
    'dependency next148' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-reader-checkpoint-current-source-next148', $ready()['dependencies'], true), true],
    'dependency next144' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-hot-journal-reader-current-source-next144', $ready()['dependencies'], true), true],
    'dependency checkpoint token' => [static fn (): mixed => in_array('sqlite-checkpoint-database-source-token', $ready()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ready()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ready()['non_overlap'], 'checkpoint database bytes'), true],
    'single page status' => [static fn (): mixed => $single()['status'], 'wal-hot-journal-reader-checkpoint-current-source-next148'],
    'single page reader source' => [static fn (): mixed => $single()['reader_sources'], ['database']],
    'single page checkpoint label' => [static fn (): mixed => $single()['checkpoint_actual_labels'], ['next148 clean rewrite rules before hot journal']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal reader checkpoint current source next148 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty checkpoint database rejected' => static fn () => $plan($hotDatabase, $walBytes, ''),
    'unaligned checkpoint database rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase . 'x'),
    'empty path rejected by base' => static fn () => SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan::plan('', $dirtyDatabase, $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, $checkpointDatabase, [1], 1),
    'empty dirty database rejected by base' => static fn () => SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan::plan($databasePath, '', $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, $checkpointDatabase, [1], 1),
    'empty journal rejected by base' => static fn () => SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, '', $currentWal, $walBytes, $hotDatabase, $walBytes, $checkpointDatabase, [1], 1),
    'empty wal rejected by base' => static fn () => SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, '', $hotDatabase, $walBytes, $checkpointDatabase, [1], 1),
    'empty reader database rejected by base' => static fn () => SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $walBytes, '', $walBytes, $checkpointDatabase, [1], 1),
    'empty pages rejected by base' => static fn () => SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, $checkpointDatabase, [], 1),
    'zero page rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, false, [0]),
    'string page rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, false, ['1']),
    'page outside checkpoint rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, false, [6]),
    'reader past wal rejected by base' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, false, [1], 5),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal reader checkpoint current source next148 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
