<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next132.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next132 clean sqlite header before crashed import'),
    2 => $page('next132 clean wp_options root before crashed import'),
    3 => $page('next132 clean active_plugins before crashed import'),
    4 => $page('next132 clean transient cache before crashed import'),
];
$dirtyDatabase = $page('next132 dirty sqlite header after crashed import')
    . $page('next132 dirty wp_options root after crashed import')
    . $page('next132 dirty active_plugins after crashed import')
    . $page('next132 dirty transient cache after crashed import');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026132) use ($sectorSize, $pageSize): string {
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
    [1, 0, 'next132 current wal schema draft'],
    [2, 4, 'next132 current wal options commit'],
    [3, 0, 'next132 current wal active_plugins draft'],
    [4, 4, 'next132 current wal transient cache commit'],
], 132, 0x13213201, 0x13213202);
$staleReaderWalBytes = $makeWalBytes([
    [1, 0, 'next132 stale reader schema draft'],
    [2, 4, 'next132 stale reader options commit'],
    [3, 0, 'next132 stale reader active_plugins draft'],
    [4, 4, 'next132 stale reader transient cache commit'],
], 131, 0x13113101, 0x13113102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);

$plan = static fn (string $readerWalBytes = null, ?int $readerEndFrame = 4, bool $reservedLock = false): array => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $currentWal,
    $currentWalBytes,
    $readerWalBytes ?? $staleReaderWalBytes,
    [1, 2, 3, 4],
    $readerEndFrame,
    $reservedLock
);

$stale = static fn (): array => $plan();
$matched = static fn (): array => $plan($currentWalBytes);
$blocked = static fn (): array => $plan($staleReaderWalBytes, 4, true);

$cases = [
    'stale status' => [static fn (): mixed => $stale()['status'], 'wal-checkpoint-reader-hot-journal-current-source-stale-reader-next132'],
    'stale reason' => [static fn (): mixed => $stale()['reason'], 'reader_wal_source_mismatch_requires_reopen_before_checkpoint_reset'],
    'matched status' => [static fn (): mixed => $matched()['status'], 'wal-checkpoint-reader-hot-journal-current-source-next132'],
    'matched reason' => [static fn (): mixed => $matched()['reason'], 'reader_wal_source_matches_hot_journal_current_source_checkpoint_allowed'],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-checkpoint-reader-hot-journal-current-source-blocked-next132'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'database_has_reserved_lock'],
    'database path' => [static fn (): mixed => $stale()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $stale()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $stale()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $stale()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $stale()['reader_end_frame'], 4],
    'hot recovered' => [static fn (): mixed => $stale()['hot_recovered'], true],
    'journal action' => [static fn (): mixed => $stale()['journal_action'], 'delete_journal_after_recovery'],
    'stale source mismatch' => [static fn (): mixed => $stale()['reader_source_matches_current'], false],
    'matched source match' => [static fn (): mixed => $matched()['reader_source_matches_current'], true],
    'stale checkpoint disallowed' => [static fn (): mixed => $stale()['checkpoint_allowed'], false],
    'matched checkpoint allowed' => [static fn (): mixed => $matched()['checkpoint_allowed'], true],
    'stale reopen required' => [static fn (): mixed => $stale()['reader_reopen_required'], true],
    'matched reopen not required' => [static fn (): mixed => $matched()['reader_reopen_required'], false],
    'current sha length' => [static fn (): mixed => strlen($stale()['current_wal_sha256']), 64],
    'reader sha length' => [static fn (): mixed => strlen($stale()['reader_wal_sha256']), 64],
    'current checkpoint sequence' => [static fn (): mixed => $stale()['current_wal_source']['checkpoint_sequence'], 132],
    'reader checkpoint sequence' => [static fn (): mixed => $stale()['reader_wal_source']['checkpoint_sequence'], 131],
    'current salt one' => [static fn (): mixed => $stale()['current_wal_source']['salt_1'], 0x13213201],
    'reader salt one' => [static fn (): mixed => $stale()['reader_wal_source']['salt_1'], 0x13113101],
    'current frame count' => [static fn (): mixed => $stale()['current_frame_count'], 4],
    'reader frame count' => [static fn (): mixed => $stale()['reader_frame_count'], 4],
    'reader sources' => [static fn (): mixed => $stale()['reader_sources'], ['wal', 'wal', 'wal', 'wal']],
    'current sources' => [static fn (): mixed => $stale()['current_sources'], ['wal', 'wal', 'wal', 'wal']],
    'reader frame indexes' => [static fn (): mixed => $stale()['reader_frame_indexes'], [1, 2, 3, 4]],
    'current frame indexes' => [static fn (): mixed => $stale()['current_frame_indexes'], [1, 2, 3, 4]],
    'reader images do not match current' => [static fn (): mixed => $stale()['reader_images_match_current'], false],
    'matched images match current' => [static fn (): mixed => $matched()['reader_images_match_current'], true],
    'row count' => [static fn (): mixed => count($stale()['rows']), 4],
    'row pages' => [static fn (): mixed => array_column($stale()['rows'], 'page_number'), [1, 2, 3, 4]],
    'stale transitions' => [static fn (): mixed => $stale()['source_transitions'], ['wal>wal>reopen', 'wal>wal>reopen', 'wal>wal>reopen', 'wal>wal>reopen']],
    'matched transitions' => [static fn (): mixed => $matched()['source_transitions'], ['wal>wal>checkpoint', 'wal>wal>checkpoint', 'wal>wal>checkpoint', 'wal>wal>checkpoint']],
    'stale op reasons' => [static fn (): mixed => $stale()['operation_reasons'], [
        'restore_hot_journal_database_before_reader_reopen_next132',
        'preserve_current_wal_until_stale_reader_reopens_next132',
        'defer_restart_checkpoint_for_current_source_reader_next132',
    ]],
    'matched restart plan present' => [static fn (): mixed => is_array($matched()['restart_plan']), true],
    'stale restart plan absent' => [static fn (): mixed => $stale()['restart_plan'], null],
    'matched restart status' => [static fn (): mixed => $matched()['restart_plan']['status'], 'wal-hot-journal-checkpoint-restart-current-source-next129'],
    'matched restart released action' => [static fn (): mixed => $matched()['restart_plan']['released_wal_action'], 'restart_wal'],
    'row two reader label' => [static fn (): mixed => $stale()['rows'][1]['reader_label'], 'next132 stale reader options commit'],
    'row two current label' => [static fn (): mixed => $stale()['rows'][1]['current_label'], 'next132 current wal options commit'],
    'source digest length' => [static fn (): mixed => strlen($stale()['source_digest']), 64],
    'dependency next132' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-hot-journal-current-source-next132', $stale()['dependencies'], true), true],
    'dependency current source' => [static fn (): mixed => in_array('sqlite-wal-reader-current-source-validation', $stale()['dependencies'], true), true],
    'dependency hot journal' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery', $stale()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint reader hot journal current source next132 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan('', $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $staleReaderWalBytes, [1], 1),
    'empty database rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan($databasePath, '', $journalBytes, $currentWal, $currentWalBytes, $staleReaderWalBytes, [1], 1),
    'empty journal rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, '', $currentWal, $currentWalBytes, $staleReaderWalBytes, [1], 1),
    'empty current wal rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, '', $staleReaderWalBytes, [1], 1),
    'empty reader wal rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, '', [1], 1),
    'empty pages rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $staleReaderWalBytes, [], 1),
    'source mismatch rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, substr_replace($currentWalBytes, 'x', 100, 1), $staleReaderWalBytes, [1], 1),
    'unaligned database rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase . 'x', $journalBytes, $currentWal, $currentWalBytes, $staleReaderWalBytes, [1], 1),
    'negative reader rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $staleReaderWalBytes, [1], -1),
    'reader past wal rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $staleReaderWalBytes, [1], 5),
    'zero page rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $staleReaderWalBytes, [0], 1),
    'string page rejected' => static fn () => SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $staleReaderWalBytes, ['1'], 1),
];

foreach ($throws as $name => $callback) {
    $tests['wal checkpoint reader hot journal current source next132 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
