<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext152Plan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next152.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next152 clean sqlite header before hot journal'),
    2 => $page('next152 clean wp_options root before hot journal'),
    3 => $page('next152 clean active_plugins before hot journal'),
    4 => $page('next152 clean plugin setting before hot journal'),
    5 => $page('next152 clean transient before hot journal'),
];
$dirtyDatabase = $page('next152 dirty sqlite header interrupted import')
    . $page('next152 dirty wp_options root interrupted import')
    . $page('next152 dirty active_plugins interrupted import')
    . $page('next152 dirty plugin setting interrupted import')
    . $page('next152 dirty transient interrupted import');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026152) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWrongPageSizeJournalBytes = static function (array $pages, int $nonce = 0x2026152) use ($sectorSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, 1024);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $checkpoint = 152, int $salt1 = 0x15215201, int $salt2 = 0x15215202) use ($pageSize, $page): string {
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
    [1, 0, 'next152 retained schema draft after hot journal'],
    [2, 5, 'next152 retained wp_options commit after hot journal'],
    [3, 0, 'next152 rolled active_plugins savepoint draft'],
    [4, 5, 'next152 rolled plugin setting savepoint commit'],
    [5, 5, 'next152 rolled transient savepoint commit'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = static function (): SQLiteSavepointStack {
    $savepoints = new SQLiteSavepointStack();
    $savepoints->beginTransaction('wp-import-next152');
    $savepoints->recordWalFrameWrite(1, 1);
    $savepoints->recordWalFrameWrite(2, 2, true);
    $savepoints->savepoint('plugin-batch-next152');
    $savepoints->recordWalFrameWrite(3, 3);
    $savepoints->recordWalFrameWrite(4, 4, true);
    $savepoints->recordWalFrameWrite(5, 5, true);

    return $savepoints;
};

$plan = static fn (
    array $pages = [1, 2, 3, 4, 5],
    string $mode = 'restart',
    ?int $readerEndFrame = null,
    bool $reservedLock = false,
    string $dbPath = null,
    string $dbBytes = null,
    string $journal = null,
    string $currentWalBytes = null,
    SQLiteWal $currentWal = null,
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext152Plan::plan(
    $dbPath ?? $databasePath,
    $dbBytes ?? $dirtyDatabase,
    $journal ?? $journalBytes,
    $currentWal ?? $wal,
    $currentWalBytes ?? $walBytes,
    $stack(),
    'plugin-batch-next152',
    $pages,
    $mode,
    $readerEndFrame,
    $reservedLock
);

$ready = static fn (): array => $plan();
$busy = static fn (): array => $plan([1, 2, 3, 4, 5], 'restart', 1);
$blocked = static fn (): array => $plan([1], 'restart', null, true);
$truncate = static fn (): array => $plan([1, 2], 'truncate');

$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next152'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'hot_journal_recovery_precedes_savepoint_wal_checkpoint_current_source'],
    'busy status' => [static fn (): mixed => $busy()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-busy-next152'],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next152'],
    'database path' => [static fn (): mixed => $ready()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ready()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ready()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ready()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $ready()['savepoint'], 'plugin-batch-next152'],
    'mode' => [static fn (): mixed => $ready()['mode'], 'restart'],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'reader end frame' => [static fn (): mixed => $ready()['reader_end_frame'], 2],
    'busy reader frame' => [static fn (): mixed => $busy()['reader_end_frame'], 1],
    'hot recovered' => [static fn (): mixed => $ready()['hot_recovered'], true],
    'blocked not recovered' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'journal action' => [static fn (): mixed => $ready()['journal_action'], 'delete_journal_after_recovery'],
    'blocked action' => [static fn (): mixed => $blocked()['journal_action'], 'preserve_journal'],
    'checkpoint allowed' => [static fn (): mixed => $ready()['checkpoint_allowed'], true],
    'busy checkpoint not allowed' => [static fn (): mixed => $busy()['checkpoint_allowed'], false],
    'wal action restart' => [static fn (): mixed => $ready()['wal_action'], 'restart_wal'],
    'wal action truncate' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal'],
    'checkpoint reason' => [static fn (): mixed => $ready()['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'busy checkpoint reason' => [static fn (): mixed => $busy()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'original frame count' => [static fn (): mixed => $ready()['original_frame_count'], 5],
    'retained frame count' => [static fn (): mixed => $ready()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $ready()['discarded_frame_count'], 3],
    'truncate bytes' => [static fn (): mixed => $ready()['truncate_to_bytes'], 32 + (2 * (24 + $pageSize))],
    'current wal bytes length' => [static fn (): mixed => $ready()['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'durable wal bytes length' => [static fn (): mixed => $ready()['durable_wal_bytes_length'], 32],
    'truncate durable wal removed' => [static fn (): mixed => $truncate()['durable_wal_bytes_length'], 0],
    'hot database sha length' => [static fn (): mixed => strlen($ready()['hot_database_sha256']), 64],
    'durable database sha length' => [static fn (): mixed => strlen($ready()['durable_database_sha256']), 64],
    'current sources' => [static fn (): mixed => $ready()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'next sources' => [static fn (): mixed => $ready()['next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'busy next sources preserve wal' => [static fn (): mixed => $busy()['next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'current frames' => [static fn (): mixed => $ready()['current_frame_indexes'], [1, 2, null, null, null]],
    'next frames' => [static fn (): mixed => $ready()['next_frame_indexes'], [null, null, null, null, null]],
    'busy next frames' => [static fn (): mixed => $busy()['next_frame_indexes'], [1, 2, null, null, null]],
    'hot restored pages' => [static fn (): mixed => $ready()['hot_restored_page_numbers'], [1, 2, 3, 4, 5]],
    'savepoint restored pages' => [static fn (): mixed => $ready()['savepoint_restored_page_numbers'], [1, 2]],
    'checkpoint changed pages' => [static fn (): mixed => $ready()['checkpoint_changed_page_numbers'], []],
    'row count' => [static fn (): mixed => count($ready()['rows']), 5],
    'row page numbers' => [static fn (): mixed => array_column($ready()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'row one dirty label' => [static fn (): mixed => $ready()['rows'][0]['dirty_label'], 'next152 dirty sqlite header interrupted import'],
    'row one hot label' => [static fn (): mixed => $ready()['rows'][0]['hot_label'], 'next152 clean sqlite header before hot journal'],
    'row one current label' => [static fn (): mixed => $ready()['rows'][0]['current_label'], 'next152 retained schema draft after hot journal'],
    'row two current label' => [static fn (): mixed => $ready()['rows'][1]['current_label'], 'next152 retained wp_options commit after hot journal'],
    'row three current label' => [static fn (): mixed => $ready()['rows'][2]['current_label'], 'next152 clean active_plugins before hot journal'],
    'row four next label' => [static fn (): mixed => $ready()['rows'][3]['next_label'], 'next152 clean plugin setting before hot journal'],
    'source transitions' => [static fn (): mixed => $ready()['source_transitions'], ['database>database>wal>database', 'database>database>wal>database', 'database>database>database>database', 'database>database>database>database', 'database>database>database>database']],
    'busy transitions' => [static fn (): mixed => $busy()['source_transitions'][0], 'database>database>database>wal'],
    'hot journal includes clean active plugins' => [static fn (): mixed => str_contains($ready()['hot_journal']['database_bytes'], 'next152 clean active_plugins'), true],
    'hot journal excludes dirty active plugins' => [static fn (): mixed => str_contains($ready()['hot_journal']['database_bytes'], 'next152 dirty active_plugins'), false],
    'checkpoint dependency' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $ready()['dependencies'], true), true],
    'next152 dependency' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next152', $ready()['dependencies'], true), true],
    'hot dependency' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery', $ready()['dependencies'], true), true],
    'savepoint dependency' => [static fn (): mixed => in_array('sqlite-savepoint-wal-current-prefix', $ready()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ready()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ready()['non_overlap'], 'next145/next146'), true],
    'source digest length' => [static fn (): mixed => strlen($ready()['source_digest']), 64],
    'single page sources' => [static fn (): mixed => $plan([3])['current_sources'], ['database']],
    'single page label' => [static fn (): mixed => $plan([3])['rows'][0]['current_label'], 'next152 clean active_plugins before hot journal'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next152 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => $plan([1], 'restart', null, false, ''),
    'empty database rejected' => static fn () => $plan([1], 'restart', null, false, null, ''),
    'empty journal rejected' => static fn () => $plan([1], 'restart', null, false, null, null, ''),
    'empty wal bytes rejected' => static fn () => $plan([1], 'restart', null, false, null, null, null, ''),
    'parsed wal mismatch rejected' => static fn () => $plan([1], 'restart', null, false, null, null, null, substr_replace($walBytes, 'x', 80, 1)),
    'bad mode rejected' => static fn () => $plan([1], 'invalid'),
    'empty pages rejected' => static fn () => $plan([]),
    'zero page rejected' => static fn () => $plan([0]),
    'string page rejected' => static fn () => $plan(['1']),
    'outside page rejected' => static fn () => $plan([6]),
    'unaligned database rejected' => static fn () => $plan([1], 'restart', null, false, null, $dirtyDatabase . 'x'),
    'reader past retained wal rejected' => static fn () => $plan([1], 'restart', 3),
    'journal page size mismatch rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext152Plan::plan($databasePath, $dirtyDatabase, $makeWrongPageSizeJournalBytes($cleanPages), $wal, $walBytes, $stack(), 'plugin-batch-next152', [1]),
    'empty savepoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext152Plan::plan($databasePath, $dirtyDatabase, $journalBytes, $wal, $walBytes, $stack(), '', [1]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next152 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
