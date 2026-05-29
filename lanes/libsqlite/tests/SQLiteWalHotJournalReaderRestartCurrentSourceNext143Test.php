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
$databasePath = '/srv/www/wp-content/database/wp-next143.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('next143 hot recovered sqlite header'),
    2 => $page('next143 hot recovered wp_options root'),
    3 => $page('next143 hot recovered active_plugins'),
    4 => $page('next143 hot recovered autoload index'),
    5 => $page('next143 hot recovered transient rows'),
    6 => $page('next143 hot recovered rewrite rules'),
];
$dirtyDatabase = $page('next143 dirty sqlite header')
    . $page('next143 dirty wp_options root')
    . $page('next143 dirty active_plugins')
    . $page('next143 dirty autoload index')
    . $page('next143 dirty transient rows')
    . $page('next143 dirty rewrite rules');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026143) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
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
$currentWalBytes = $makeWalBytes([
    [1, 0, 'next143 current schema reader draft'],
    [2, 6, 'next143 current wp_options reader commit'],
    [3, 0, 'next143 current active_plugins reader draft'],
    [4, 6, 'next143 current autoload reader commit'],
    [5, 0, 'next143 current transient reader draft'],
], 143, 0x14314301, 0x14314302);
$restartedWalBytes = $makeWalBytes([
    [2, 0, 'next143 restarted wp_options next draft'],
    [5, 0, 'next143 restarted transient next draft'],
    [6, 6, 'next143 restarted rewrite next commit'],
], 144, 0x14314401, 0x14314402);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);

$plan = static fn (
    int $readerEndFrame = 5,
    array $pages = [1, 2, 3, 4, 5, 6],
    ?string $nextBytes = null,
    bool $reservedLock = false
): array => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartSeparatedWalPlan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $currentWal,
    $currentWalBytes,
    $nextBytes ?? $restartedWalBytes,
    $pages,
    $readerEndFrame,
    $reservedLock
);

$restart = static fn (): array => $plan();
$partial = static fn (): array => $plan(3, [2, 3, 6]);
$blocked = static fn (): array => $plan(5, [1, 2], null, true);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-reader-restart-current-source-next143'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'hot_journal_reader_restarts_on_current_wal_source_before_next_generation'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $restart()['reader_end_frame'], 5],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'current reader preserved' => [static fn (): mixed => $restart()['current_reader_preserved'], true],
    'reader matches base restart' => [static fn (): mixed => $restart()['reader_matches_base_restart'], true],
    'next source separated' => [static fn (): mixed => $restart()['next_source_separated'], true],
    'current checkpoint sequence' => [static fn (): mixed => $restart()['current_wal_source']['checkpoint_sequence'], 143],
    'next checkpoint sequence' => [static fn (): mixed => $restart()['next_wal_source']['checkpoint_sequence'], 144],
    'current frame count' => [static fn (): mixed => $restart()['current_wal_source']['frame_count'], 5],
    'next frame count' => [static fn (): mixed => $restart()['next_wal_source']['frame_count'], 3],
    'current sha length' => [static fn (): mixed => strlen($restart()['current_wal_sha256']), 64],
    'next sha length' => [static fn (): mixed => strlen($restart()['next_wal_sha256']), 64],
    'sha separated' => [static fn (): mixed => $restart()['current_wal_sha256'] !== $restart()['next_wal_sha256'], true],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'wal', 'wal', 'database', 'database']],
    'current frames' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, 3, 4, null, null]],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'wal', 'database', 'database', 'wal', 'wal']],
    'next frames' => [static fn (): mixed => $restart()['next_frame_indexes'], [null, 1, null, null, 2, 3]],
    'current labels' => [static fn (): mixed => $restart()['current_labels'], [
        'next143 current schema reader draft',
        'next143 current wp_options reader commit',
        'next143 current active_plugins reader draft',
        'next143 current autoload reader commit',
        'next143 hot recovered transient rows',
        'next143 hot recovered rewrite rules',
    ]],
    'next labels' => [static fn (): mixed => $restart()['next_labels'], [
        'next143 hot recovered sqlite header',
        'next143 restarted wp_options next draft',
        'next143 hot recovered active_plugins',
        'next143 hot recovered autoload index',
        'next143 restarted transient next draft',
        'next143 restarted rewrite next commit',
    ]],
    'separated pages' => [static fn (): mixed => $restart()['next_separated_page_numbers'], [1, 2, 3, 4, 5, 6]],
    'separated page count' => [static fn (): mixed => $restart()['next_separated_page_count'], 6],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], [
        'wal>restart-boundary>database',
        'wal>restart-boundary>wal',
        'wal>restart-boundary>database',
        'wal>restart-boundary>database',
        'database>restart-boundary>wal',
        'database>restart-boundary>wal',
    ]],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'row count' => [static fn (): mixed => count($restart()['rows']), 6],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5, 6]],
    'row one hot label' => [static fn (): mixed => $restart()['rows'][0]['hot_recovered_label'], 'next143 hot recovered sqlite header'],
    'row one current label' => [static fn (): mixed => $restart()['rows'][0]['current_label'], 'next143 current schema reader draft'],
    'row two next label' => [static fn (): mixed => $restart()['rows'][1]['next_label'], 'next143 restarted wp_options next draft'],
    'row three base label' => [static fn (): mixed => $restart()['rows'][2]['base_reader_label'], 'next143 current active_plugins reader draft'],
    'row four reader matches' => [static fn (): mixed => $restart()['rows'][3]['reader_matches_base_restart'], true],
    'row five next source' => [static fn (): mixed => $restart()['rows'][4]['next_source'], 'wal'],
    'row six current source' => [static fn (): mixed => $restart()['rows'][5]['current_source'], 'database'],
    'row six next source' => [static fn (): mixed => $restart()['rows'][5]['next_source'], 'wal'],
    'operation suffix' => [static fn (): mixed => array_slice($restart()['operation_reasons'], -2), [
        'restart_current_reader_from_hot_journal_current_source_next143',
        'open_next_reader_on_restarted_wal_generation_next143',
    ]],
    'base status' => [static fn (): mixed => $restart()['base_plan']['status'], 'wal-hot-journal-reader-restart-current-source-blocked-next131'],
    'base reader preserved' => [static fn (): mixed => $restart()['base_plan']['current_source_reused_for_reader_restart'], true],
    'base released action' => [static fn (): mixed => $restart()['base_plan']['released_wal_action'], 'preserve_wal'],
    'partial reader end frame' => [static fn (): mixed => $partial()['reader_end_frame'], 3],
    'partial current sources' => [static fn (): mixed => $partial()['current_sources'], ['wal', 'database', 'database']],
    'partial current frames' => [static fn (): mixed => $partial()['current_frame_indexes'], [2, null, null]],
    'partial next sources' => [static fn (): mixed => $partial()['next_sources'], ['wal', 'database', 'wal']],
    'partial next frames' => [static fn (): mixed => $partial()['next_frame_indexes'], [1, null, 3]],
    'partial labels' => [static fn (): mixed => $partial()['current_labels'], [
        'next143 current wp_options reader commit',
        'next143 hot recovered active_plugins',
        'next143 hot recovered rewrite rules',
    ]],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-reader-restart-current-source-blocked-next143'],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'blocked current preserved remains true' => [static fn (): mixed => $blocked()['current_reader_preserved'], true],
    'dependency next143' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-reader-restart-current-source-next143', $restart()['dependencies'], true), true],
    'dependency next131' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-reader-restart-current-source-next131', $restart()['dependencies'], true), true],
    'dependency restart boundary' => [static fn (): mixed => in_array('sqlite-wal-restart-generation-boundary', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'combined hot-recovered current source'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal reader restart current source next143 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty restart wal rejected' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartSeparatedWalPlan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, '', [1], 1),
    'negative reader rejected' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartSeparatedWalPlan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $restartedWalBytes, [1], -1),
    'reader past wal rejected' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartSeparatedWalPlan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $restartedWalBytes, [1], 6),
    'empty path rejected by base' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartSeparatedWalPlan('', $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $restartedWalBytes, [1], 1),
    'empty database rejected by base' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartSeparatedWalPlan($databasePath, '', $journalBytes, $currentWal, $currentWalBytes, $restartedWalBytes, [1], 1),
    'empty journal rejected by base' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartSeparatedWalPlan($databasePath, $dirtyDatabase, '', $currentWal, $currentWalBytes, $restartedWalBytes, [1], 1),
    'empty pages rejected by base' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartSeparatedWalPlan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $restartedWalBytes, [], 1),
    'zero page rejected' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartSeparatedWalPlan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $restartedWalBytes, [0], 1),
    'string page rejected' => static fn () => SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartSeparatedWalPlan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $restartedWalBytes, ['1'], 1),
    'stale sequence rejected' => static fn () => $plan(5, [1], $makeWalBytes([[2, 6, 'next143 stale restart']], 143, 0x14314401, 0x14314402)),
    'same salt rejected' => static fn () => $plan(5, [1], $makeWalBytes([[2, 6, 'next143 stale salt restart']], 144, 0x14314301, 0x14314302)),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal reader restart current source next143 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
