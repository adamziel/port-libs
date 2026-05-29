<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next137.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next137 clean sqlite header before hot journal'),
    2 => $page('next137 clean wp_options root before hot journal'),
    3 => $page('next137 clean active_plugins before hot journal'),
    4 => $page('next137 clean rewrite_rules before hot journal'),
    5 => $page('next137 clean autoload index before hot journal'),
];
$dirtyDatabase = $page('next137 dirty sqlite header from interrupted import')
    . $page('next137 dirty wp_options root from interrupted import')
    . $page('next137 dirty active_plugins from interrupted import')
    . $page('next137 dirty rewrite_rules from interrupted import')
    . $page('next137 dirty autoload index from interrupted import');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026137) use ($sectorSize, $pageSize): string {
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
    [1, 0, 'next137 current reader schema draft'],
    [2, 5, 'next137 current reader wp_options commit'],
    [3, 0, 'next137 current reader active_plugins draft'],
    [4, 5, 'next137 current reader rewrite_rules commit'],
    [5, 5, 'next137 current reader autoload index commit'],
], 137, 0x13713701, 0x13713702);
$staleReaderWalBytes = $makeWalBytes([
    [1, 0, 'next137 stale reader schema draft'],
    [2, 5, 'next137 stale reader wp_options commit'],
    [3, 0, 'next137 stale reader active_plugins draft'],
    [4, 5, 'next137 stale reader rewrite_rules commit'],
    [5, 5, 'next137 stale reader autoload index commit'],
], 136, 0x13613601, 0x13613602);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);

$nextTransactions = [[
    'pages' => [
        2 => $page('next137 next generation wp_options commit'),
        5 => $page('next137 next generation autoload index commit'),
    ],
    'database_page_count' => 5,
    'commit' => true,
]];

$plan = static fn (
    ?string $readerWalBytes = null,
    ?int $readerEndFrame = 5,
    bool $reservedLock = false,
    array $transactions = null,
    array $pages = null
): array => SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan::plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $currentWal,
    $currentWalBytes,
    $readerWalBytes ?? $currentWalBytes,
    $transactions ?? $nextTransactions,
    $pages ?? [1, 2, 3, 4, 5],
    $readerEndFrame,
    $reservedLock
);

$ready = static fn (): array => $plan();
$stale = static fn (): array => $plan($staleReaderWalBytes);
$blocked = static fn (): array => $plan($currentWalBytes, 5, true);
$baseReader = static fn (): array => $plan($currentWalBytes, 0);
$single = static fn (): array => $plan($currentWalBytes, 5, false, null, [2]);

$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-hot-journal-reader-truncate-current-source-next137'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'hot_journal_recovery_feeds_truncate_checkpoint_while_current_reader_pins_source'],
    'stale status' => [static fn (): mixed => $stale()['status'], 'wal-hot-journal-reader-truncate-current-source-blocked-next137'],
    'reserved status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-reader-truncate-current-source-blocked-next137'],
    'base reader status' => [static fn (): mixed => $baseReader()['status'], 'wal-hot-journal-reader-truncate-current-source-blocked-next137'],
    'database path' => [static fn (): mixed => $ready()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ready()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ready()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ready()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $ready()['reader_end_frame'], 5],
    'hot recovered' => [static fn (): mixed => $ready()['hot_recovered'], true],
    'stale source mismatch' => [static fn (): mixed => $stale()['reader_source_matches_current'], false],
    'reader source matches' => [static fn (): mixed => $ready()['reader_source_matches_current'], true],
    'checkpoint allowed' => [static fn (): mixed => $ready()['checkpoint_allowed'], true],
    'truncate ready' => [static fn (): mixed => $ready()['truncate_ready'], true],
    'blocked truncate not ready' => [static fn (): mixed => $blocked()['truncate_ready'], false],
    'current reader pins reset' => [static fn (): mixed => $ready()['current_reader_pins_reset'], true],
    'reader release unblocked truncate' => [static fn (): mixed => $ready()['reader_release_unblocked_truncate'], true],
    'old wal sidecar removed' => [static fn (): mixed => $ready()['truncate_removed_old_wal_sidecar'], true],
    'next reader fresh generation' => [static fn (): mixed => $ready()['next_reader_uses_fresh_wal_generation'], true],
    'fresh checkpoint sequence' => [static fn (): mixed => $ready()['fresh_wal_checkpoint_sequence'], 138],
    'append start frame' => [static fn (): mixed => $ready()['next_append_start_frame'], 1],
    'append end frame' => [static fn (): mixed => $ready()['next_append_end_frame'], 2],
    'append frame count' => [static fn (): mixed => $ready()['next_append_frame_count'], 2],
    'current sources' => [static fn (): mixed => $ready()['current_sources'], ['wal', 'wal', 'wal', 'wal', 'wal']],
    'pinned sources' => [static fn (): mixed => $ready()['pinned_after_checkpoint_sources'], ['wal', 'wal', 'wal', 'wal', 'wal']],
    'next sources' => [static fn (): mixed => $ready()['next_sources'], ['database', 'wal', 'database', 'database', 'wal']],
    'row count' => [static fn (): mixed => count($ready()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($ready()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'row two hot label' => [static fn (): mixed => $ready()['rows'][1]['hot_current_label'], 'next137 current reader wp_options commit'],
    'row two pinned label' => [static fn (): mixed => $ready()['rows'][1]['pinned_after_checkpoint_label'], 'next137 current reader wp_options commit'],
    'row two next label' => [static fn (): mixed => $ready()['rows'][1]['next_label'], 'next137 next generation wp_options commit'],
    'row five next label' => [static fn (): mixed => $ready()['rows'][4]['next_label'], 'next137 next generation autoload index commit'],
    'hot preserved by pinned checkpoint' => [static fn (): mixed => $ready()['hot_current_preserved_by_pinned_checkpoint'], true],
    'changed page numbers' => [static fn (): mixed => $ready()['next_generation_changed_page_numbers'], [2, 5]],
    'source transitions' => [static fn (): mixed => $ready()['source_transitions'], [
        'wal>wal>checkpoint>wal>wal>database',
        'wal>wal>checkpoint>wal>wal>wal',
        'wal>wal>checkpoint>wal>wal>database',
        'wal>wal>checkpoint>wal>wal>database',
        'wal>wal>checkpoint>wal>wal>wal',
    ]],
    'single transition' => [static fn (): mixed => $single()['source_transitions'], ['wal>wal>checkpoint>wal>wal>wal']],
    'operation feed hot database' => [static fn (): mixed => in_array('feed_hot_journal_database_into_truncate_checkpoint_next137', $ready()['operation_reasons'], true), true],
    'operation pin reader' => [static fn (): mixed => in_array('pin_current_reader_until_truncate_checkpoint_released_next137', $ready()['operation_reasons'], true), true],
    'operation append next' => [static fn (): mixed => in_array('append_next_writer_after_truncate_on_fresh_wal_generation_next137', $ready()['operation_reasons'], true), true],
    'reader base status' => [static fn (): mixed => $ready()['reader_plan']['status'], 'wal-checkpoint-reader-hot-journal-current-source-next132'],
    'truncate base status' => [static fn (): mixed => $ready()['truncate_plan']['status'], 'wal-checkpoint-truncate-reader-current-source-next134'],
    'truncate base current reader pins reset' => [static fn (): mixed => $ready()['truncate_plan']['current_reader_pins_reset'], true],
    'source digest length' => [static fn (): mixed => strlen($ready()['source_digest']), 64],
    'dependency next137' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-reader-truncate-current-source-next137', $ready()['dependencies'], true), true],
    'dependency next132' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-hot-journal-current-source-next132', $ready()['dependencies'], true), true],
    'dependency next134' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-truncate-reader-current-source-next134', $ready()['dependencies'], true), true],
    'dependency truncate generation' => [static fn (): mixed => in_array('sqlite-wal-truncate-next-source-generation', $ready()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal reader truncate current source next137 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty next transactions rejected' => static fn () => SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $currentWalBytes, [], [1], 1),
    'empty path rejected' => static fn () => SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan::plan('', $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $currentWalBytes, $nextTransactions, [1], 1),
    'empty database rejected' => static fn () => SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan::plan($databasePath, '', $journalBytes, $currentWal, $currentWalBytes, $currentWalBytes, $nextTransactions, [1], 1),
    'empty journal rejected' => static fn () => SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, '', $currentWal, $currentWalBytes, $currentWalBytes, $nextTransactions, [1], 1),
    'empty current wal rejected' => static fn () => SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, '', $currentWalBytes, $nextTransactions, [1], 1),
    'empty reader wal rejected' => static fn () => SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, '', $nextTransactions, [1], 1),
    'empty pages rejected' => static fn () => SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $currentWalBytes, $nextTransactions, [], 1),
    'zero page rejected' => static fn () => SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $currentWalBytes, $nextTransactions, [0], 1),
    'reader past wal rejected' => static fn () => SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $currentWalBytes, $nextTransactions, [1], 9),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal reader truncate current source next137 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
