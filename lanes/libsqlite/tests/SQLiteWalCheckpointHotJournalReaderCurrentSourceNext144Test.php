<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next144.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next144 clean sqlite header before hot journal'),
    2 => $page('next144 clean wp_options root before hot journal'),
    3 => $page('next144 clean active_plugins before hot journal'),
    4 => $page('next144 clean rewrite_rules before hot journal'),
];
$dirtyDatabase = $page('next144 dirty sqlite header from interrupted import')
    . $page('next144 dirty wp_options root from interrupted import')
    . $page('next144 dirty active_plugins from interrupted import')
    . $page('next144 dirty rewrite_rules from interrupted import');
$hotDatabase = implode('', $cleanPages);

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026144) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $checkpoint = 144, int $salt1 = 0x14414401, int $salt2 = 0x14414402) use ($pageSize, $page): string {
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
    [1, 0, 'next144 current reader schema draft'],
    [2, 4, 'next144 current reader wp_options commit'],
    [3, 4, 'next144 current reader active_plugins commit'],
]);
$currentWal = SQLiteWal::parse($walBytes, $pageSize, true);
$staleWalBytes = $makeWalBytes([
    [1, 0, 'next144 stale reader schema draft'],
    [2, 4, 'next144 stale reader wp_options commit'],
    [3, 4, 'next144 stale reader active_plugins commit'],
], 143, 0x14314301, 0x14314302);

$plan = static fn (
    string $readerDatabaseBytes = null,
    string $readerWalBytes = null,
    bool $reservedLock = false,
    array $pages = [1, 2, 3, 4],
): array => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $currentWal,
    $walBytes,
    $readerDatabaseBytes ?? $hotDatabase,
    $readerWalBytes ?? $walBytes,
    $pages,
    3,
    $reservedLock
);

$ready = static fn (): array => $plan();
$dirtyReader = static fn (): array => $plan($dirtyDatabase);
$staleWal = static fn (): array => $plan($hotDatabase, $staleWalBytes);
$notHot = static fn (): array => $plan($hotDatabase, $walBytes, true);

$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-checkpoint-hot-journal-reader-current-source-next144'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'reader_wal_and_database_source_match_hot_journal_checkpoint_current_source'],
    'dirty reader status' => [static fn (): mixed => $dirtyReader()['status'], 'wal-checkpoint-hot-journal-reader-current-source-reopen-next144'],
    'stale wal status' => [static fn (): mixed => $staleWal()['status'], 'wal-checkpoint-hot-journal-reader-current-source-reopen-next144'],
    'not hot status' => [static fn (): mixed => $notHot()['status'], 'wal-checkpoint-hot-journal-reader-current-source-blocked-next144'],
    'database path' => [static fn (): mixed => $ready()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ready()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ready()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ready()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $ready()['reader_end_frame'], 3],
    'hot recovered' => [static fn (): mixed => $ready()['hot_recovered'], true],
    'not hot recovered false' => [static fn (): mixed => $notHot()['hot_recovered'], false],
    'journal action' => [static fn (): mixed => $ready()['journal_action'], 'delete_journal_after_recovery'],
    'not hot journal action' => [static fn (): mixed => $notHot()['journal_action'], 'preserve_journal'],
    'wal source matches' => [static fn (): mixed => $ready()['wal_source_matches_current'], true],
    'stale wal source mismatch' => [static fn (): mixed => $staleWal()['wal_source_matches_current'], false],
    'database source matches' => [static fn (): mixed => $ready()['database_source_matches_current'], true],
    'dirty database source mismatch' => [static fn (): mixed => $dirtyReader()['database_source_matches_current'], false],
    'checkpoint allowed' => [static fn (): mixed => $ready()['checkpoint_allowed'], true],
    'dirty checkpoint deferred' => [static fn (): mixed => $dirtyReader()['checkpoint_allowed'], false],
    'stale wal checkpoint deferred' => [static fn (): mixed => $staleWal()['checkpoint_allowed'], false],
    'dirty reader reopen required' => [static fn (): mixed => $dirtyReader()['reader_reopen_required'], true],
    'ready reader reopen false' => [static fn (): mixed => $ready()['reader_reopen_required'], false],
    'current database page count' => [static fn (): mixed => $ready()['current_database_source']['page_count'], 4],
    'reader database page count' => [static fn (): mixed => $ready()['reader_database_source']['page_count'], 4],
    'current database sha length' => [static fn (): mixed => strlen($ready()['current_database_source']['sha256']), 64],
    'dirty reader different database sha' => [static fn (): mixed => $dirtyReader()['current_database_source']['sha256'] !== $dirtyReader()['reader_database_source']['sha256'], true],
    'current wal checkpoint sequence' => [static fn (): mixed => $ready()['current_wal_source']['checkpoint_sequence'], 144],
    'reader wal checkpoint sequence' => [static fn (): mixed => $ready()['reader_wal_source']['checkpoint_sequence'], 144],
    'stale reader wal checkpoint sequence' => [static fn (): mixed => $staleWal()['reader_wal_source']['checkpoint_sequence'], 143],
    'current frame count' => [static fn (): mixed => $ready()['current_frame_count'], 3],
    'reader frame count' => [static fn (): mixed => $ready()['reader_frame_count'], 3],
    'stale reader frame count' => [static fn (): mixed => $staleWal()['reader_frame_count'], 3],
    'reader sources' => [static fn (): mixed => $ready()['reader_sources'], ['wal', 'wal', 'wal', 'database']],
    'current sources' => [static fn (): mixed => $ready()['current_sources'], ['wal', 'wal', 'wal', 'database']],
    'reader frames' => [static fn (): mixed => $ready()['reader_frame_indexes'], [1, 2, 3, null]],
    'current frames' => [static fn (): mixed => $ready()['current_frame_indexes'], [1, 2, 3, null]],
    'ready images match' => [static fn (): mixed => $ready()['reader_images_match_current'], true],
    'dirty images mismatch' => [static fn (): mixed => $dirtyReader()['reader_images_match_current'], false],
    'dirty mismatched page numbers' => [static fn (): mixed => $dirtyReader()['mismatched_page_numbers'], [4]],
    'stale wal mismatched page numbers' => [static fn (): mixed => $staleWal()['mismatched_page_numbers'], [1, 2, 3]],
    'row count' => [static fn (): mixed => count($ready()['rows']), 4],
    'row page numbers' => [static fn (): mixed => array_column($ready()['rows'], 'page_number'), [1, 2, 3, 4]],
    'row page two label' => [static fn (): mixed => $ready()['rows'][1]['current_label'], 'next144 current reader wp_options commit'],
    'row page four clean label' => [static fn (): mixed => $ready()['rows'][3]['current_label'], 'next144 clean rewrite_rules before hot journal'],
    'dirty row page four label' => [static fn (): mixed => $dirtyReader()['rows'][3]['reader_label'], 'next144 dirty rewrite_rules from interrupted import'],
    'ready source transitions' => [static fn (): mixed => $ready()['source_transitions'], ['wal>wal>same-db-source', 'wal>wal>same-db-source', 'wal>wal>same-db-source', 'database>database>same-db-source']],
    'dirty source transition has reopen' => [static fn (): mixed => $dirtyReader()['source_transitions'][3], 'database>database>reopen-db-source'],
    'operation pin' => [static fn (): mixed => in_array('pin_reader_database_source_after_hot_journal_recovery_next144', $ready()['operation_reasons'], true), true],
    'operation allow checkpoint' => [static fn (): mixed => in_array('allow_checkpoint_reset_for_matching_reader_source_next144', $ready()['operation_reasons'], true), true],
    'operation defer stale' => [static fn (): mixed => in_array('defer_checkpoint_reset_for_stale_database_source_next144', $dirtyReader()['operation_reasons'], true), true],
    'hot journal payload clean page' => [static fn (): mixed => str_contains($ready()['hot_journal']['database_bytes'], 'next144 clean active_plugins'), true],
    'hot journal excludes dirty page' => [static fn (): mixed => str_contains($ready()['hot_journal']['database_bytes'], 'next144 dirty active_plugins'), false],
    'source digest length' => [static fn (): mixed => strlen($ready()['source_digest']), 64],
    'dependency next144' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-hot-journal-reader-current-source-next144', $ready()['dependencies'], true), true],
    'dependency database source token' => [static fn (): mixed => in_array('sqlite-reader-database-source-token', $ready()['dependencies'], true), true],
    'dependency hot recovery' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery', $ready()['dependencies'], true), true],
    'dependency reader validation' => [static fn (): mixed => in_array('sqlite-wal-reader-current-source-validation', $ready()['dependencies'], true), true],
    'single page source' => [static fn (): mixed => $plan($hotDatabase, $walBytes, false, [4])['reader_sources'], ['database']],
    'single page label' => [static fn (): mixed => $plan($hotDatabase, $walBytes, false, [4])['rows'][0]['current_label'], 'next144 clean rewrite_rules before hot journal'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint hot journal reader current source next144 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan('', $dirtyDatabase, $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, [1], 1),
    'empty database rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan($databasePath, '', $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, [1], 1),
    'empty journal rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan($databasePath, $dirtyDatabase, '', $currentWal, $walBytes, $hotDatabase, $walBytes, [1], 1),
    'empty wal rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, '', $hotDatabase, $walBytes, [1], 1),
    'empty reader database rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $walBytes, '', $walBytes, [1], 1),
    'empty pages rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, [], 1),
    'stale current wal rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, substr_replace($walBytes, 'x', 80, 1), $hotDatabase, $walBytes, [1], 1),
    'unaligned database rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan($databasePath, $dirtyDatabase . 'x', $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, [1], 1),
    'unaligned reader database rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $walBytes, $hotDatabase . 'x', $walBytes, [1], 1),
    'zero page rejected' => static fn () => $plan($hotDatabase, $walBytes, false, [0]),
    'string page rejected' => static fn () => $plan($hotDatabase, $walBytes, false, ['1']),
    'reader past wal rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, [1], 4),
];

foreach ($throws as $name => $callback) {
    $tests['wal checkpoint hot journal reader current source next144 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
