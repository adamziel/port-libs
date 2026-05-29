<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next131.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next131 clean sqlite header before reader restart'),
    2 => $page('next131 clean wp_options root before reader restart'),
    3 => $page('next131 clean active_plugins before reader restart'),
    4 => $page('next131 clean autoload index before reader restart'),
    5 => $page('next131 clean rewrite rules before reader restart'),
];
$dirtyDatabase = $page('next131 dirty sqlite header after interrupted import')
    . $page('next131 dirty wp_options root after interrupted import')
    . $page('next131 dirty active_plugins after interrupted import')
    . $page('next131 dirty autoload index after interrupted import')
    . $page('next131 dirty rewrite rules after interrupted import');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026131) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $checkpoint = 131, int $salt1 = 0x13113101, int $salt2 = 0x13113102) use ($pageSize, $page): string {
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
$frames = [
    [1, 0, 'next131 wal schema retained draft'],
    [2, 5, 'next131 wal options retained commit'],
    [3, 0, 'next131 wal active_plugins current reader draft'],
    [4, 5, 'next131 wal autoload current reader commit'],
    [2, 5, 'next131 wal options current reader tail commit'],
];
$walBytes = $makeWalBytes($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$plan = static fn (
    int $readerEndFrame = 5,
    array $pages = [1, 2, 3, 4, 5],
    bool $reservedLock = false,
): array => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $wal,
    $walBytes,
    $pages,
    $readerEndFrame,
    $reservedLock
);

$restart = static fn (): array => $plan();
$partial = static fn (): array => $plan(3, [2, 3, 5]);
$blocked = static fn (): array => $plan(5, [1, 2], true);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-reader-restart-current-source-next131'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'reader_restart_reuses_preserved_current_wal_after_hot_journal_recovery'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $restart()['reader_end_frame'], 5],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'pinned checkpoint busy' => [static fn (): mixed => $restart()['pinned_checkpoint_busy'], true],
    'released checkpoint not busy' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'pinned action' => [static fn (): mixed => $restart()['pinned_wal_action'], 'preserve_wal'],
    'released action' => [static fn (): mixed => $restart()['released_wal_action'], 'restart_wal'],
    'current source reused' => [static fn (): mixed => $restart()['current_source_reused_for_reader_restart'], true],
    'restart header separated' => [static fn (): mixed => $restart()['restart_header_separated_for_next_reader'], true],
    'reader restart sources' => [static fn (): mixed => $restart()['reader_restart_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'reader restart frames' => [static fn (): mixed => $restart()['reader_restart_frame_indexes'], [1, 5, 3, 4, null]],
    'next generation sources' => [static fn (): mixed => $restart()['next_generation_sources'], ['database', 'database', 'database', 'database', 'database']],
    'next generation frames' => [static fn (): mixed => $restart()['next_generation_frame_indexes'], [null, null, null, null, null]],
    'reader restart labels' => [static fn (): mixed => $restart()['reader_restart_labels'], [
        'next131 wal schema retained draft',
        'next131 wal options current reader tail commit',
        'next131 wal active_plugins current reader draft',
        'next131 wal autoload current reader commit',
        'next131 clean rewrite rules before reader restart',
    ]],
    'next generation labels' => [static fn (): mixed => $restart()['next_generation_labels'], [
        'next131 wal schema retained draft',
        'next131 wal options current reader tail commit',
        'next131 wal active_plugins current reader draft',
        'next131 wal autoload current reader commit',
        'next131 clean rewrite rules before reader restart',
    ]],
    'restart matches original reader' => [static fn (): mixed => $restart()['reader_restart_matches_original_reader'], true],
    'next generation separated' => [static fn (): mixed => $restart()['next_generation_separated_from_pinned_reader'], true],
    'transitions' => [static fn (): mixed => $restart()['reader_restart_transitions'], ['wal>wal>database', 'wal>wal>database', 'wal>wal>database', 'wal>wal>database', 'database>database>database']],
    'current sha matches restarted current' => [static fn (): mixed => $restart()['current_reader_wal_sha256'] === $restart()['restarted_current_reader_wal_sha256'], true],
    'next sha differs' => [static fn (): mixed => $restart()['next_generation_wal_sha256'] !== $restart()['current_reader_wal_sha256'], true],
    'operation suffix' => [static fn (): mixed => array_slice($restart()['operation_reasons'], -2), [
        'restart_current_reader_from_preserved_wal_next131',
        'open_next_reader_on_restarted_generation_next131',
    ]],
    'base status' => [static fn (): mixed => $restart()['base_plan']['status'], 'wal-hot-journal-checkpoint-restart-current-source-next129'],
    'base released wal length' => [static fn (): mixed => $restart()['base_plan']['released_wal_bytes_length'], 32],
    'base pinned wal length' => [static fn (): mixed => $restart()['base_plan']['pinned_wal_bytes_length'], 32 + (5 * (24 + $pageSize))],
    'page two restart label' => [static fn (): mixed => $restart()['reader_restart_labels'][1], 'next131 wal options current reader tail commit'],
    'page five restart label' => [static fn (): mixed => $restart()['reader_restart_labels'][4], 'next131 clean rewrite rules before reader restart'],
    'partial reader end frame' => [static fn (): mixed => $partial()['reader_end_frame'], 3],
    'partial restart sources' => [static fn (): mixed => $partial()['reader_restart_sources'], ['wal', 'database', 'database']],
    'partial restart frames' => [static fn (): mixed => $partial()['reader_restart_frame_indexes'], [2, null, null]],
    'partial restart labels' => [static fn (): mixed => $partial()['reader_restart_labels'], [
        'next131 wal options retained commit',
        'next131 clean active_plugins before reader restart',
        'next131 clean rewrite rules before reader restart',
    ]],
    'partial next sources' => [static fn (): mixed => $partial()['next_generation_sources'], ['database', 'database', 'database']],
    'partial separated' => [static fn (): mixed => $partial()['next_generation_separated_from_pinned_reader'], true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-reader-restart-current-source-blocked-next131'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'database_has_reserved_lock'],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'dependency next131' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-reader-restart-current-source-next131', $restart()['dependencies'], true), true],
    'dependency next129' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-checkpoint-restart-current-source-next129', $restart()['dependencies'], true), true],
    'dependency hot journal' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery', $restart()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal reader restart current source next131 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'negative reader rejected' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan($databasePath, $dirtyDatabase, $journalBytes, $wal, $walBytes, [1], -1),
    'reader past wal rejected' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan($databasePath, $dirtyDatabase, $journalBytes, $wal, $walBytes, [1], 6),
    'empty path rejected by base' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan('', $dirtyDatabase, $journalBytes, $wal, $walBytes, [1], 1),
    'empty database rejected by base' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan($databasePath, '', $journalBytes, $wal, $walBytes, [1], 1),
    'empty journal rejected by base' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan($databasePath, $dirtyDatabase, '', $wal, $walBytes, [1], 1),
    'empty pages rejected by base' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan($databasePath, $dirtyDatabase, $journalBytes, $wal, $walBytes, [], 1),
    'mismatched wal rejected by base' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan($databasePath, $dirtyDatabase, $journalBytes, $wal, substr_replace($walBytes, 'x', 88, 1), [1], 1),
    'zero page rejected' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan($databasePath, $dirtyDatabase, $journalBytes, $wal, $walBytes, [0], 1),
    'string page rejected' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan($databasePath, $dirtyDatabase, $journalBytes, $wal, $walBytes, ['1'], 1),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal reader restart current source next131 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
