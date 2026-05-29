<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext155Plan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next155.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next155 clean sqlite header before import'),
    2 => $page('next155 clean wp_options root before import'),
    3 => $page('next155 clean active_plugins before import'),
    4 => $page('next155 clean autoload index before import'),
    5 => $page('next155 clean rewrite rules before import'),
    6 => $page('next155 clean transient timeout before import'),
];
$dirtyDatabase = $page('next155 dirty sqlite header from crashed import')
    . $page('next155 dirty wp_options root from crashed import')
    . $page('next155 dirty active_plugins from crashed import')
    . $page('next155 dirty autoload index from crashed import')
    . $page('next155 dirty rewrite rules from crashed import')
    . $page('next155 dirty transient timeout from crashed import');
$hotDatabase = implode('', $cleanPages);

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026155) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $checkpoint = 155, int $salt1 = 0x15515501, int $salt2 = 0x15515502) use ($pageSize, $page): string {
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
    [1, 0, 'next155 current schema draft before savepoint'],
    [2, 6, 'next155 current wp_options commit before savepoint'],
    [3, 0, 'next155 clean active_plugins before import'],
    [4, 6, 'next155 rolled back autoload savepoint frame'],
    [5, 0, 'next155 rolled back rewrite savepoint frame'],
    [6, 6, 'next155 rolled back transient savepoint frame'],
]);
$currentWal = SQLiteWal::parse($walBytes, $pageSize, true);
$checkpointDatabase = $page('next155 current schema draft before savepoint')
    . $page('next155 current wp_options commit before savepoint')
    . $page('next155 clean active_plugins before import')
    . $page('next155 clean autoload index before import')
    . $page('next155 clean rewrite rules before import')
    . $page('next155 clean transient timeout before import');
$badCheckpointDatabase = $page('next155 current schema draft before savepoint')
    . $page('next155 bad wp_options checkpoint after rollback')
    . $page('next155 clean active_plugins before import')
    . $page('next155 clean autoload index before import')
    . $page('next155 clean rewrite rules before import')
    . $page('next155 clean transient timeout before import');

$plan = static fn (
    string $readerDatabaseBytes = null,
    string $readerWalBytes = null,
    string $checkpointBytes = null,
    int $rollbackFrame = 2,
    ?int $readerEndFrame = 6,
    bool $reservedLock = false,
    array $pages = [1, 2, 3, 4, 5, 6],
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext155Plan::plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $currentWal,
    $walBytes,
    $readerDatabaseBytes ?? $hotDatabase,
    $readerWalBytes ?? $walBytes,
    $checkpointBytes ?? $checkpointDatabase,
    $pages,
    $rollbackFrame,
    $readerEndFrame,
    $reservedLock
);

$ready = static fn (): array => $plan();
$badCheckpoint = static fn (): array => $plan($hotDatabase, $walBytes, $badCheckpointDatabase);
$readerAtRollback = static fn (): array => $plan($hotDatabase, $walBytes, $checkpointDatabase, 2, 2);
$blocked = static fn (): array => $plan($hotDatabase, $walBytes, $checkpointDatabase, 2, 6, true);
$single = static fn (): array => $plan($hotDatabase, $walBytes, $checkpointDatabase, 2, 6, false, [4]);

$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next155'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'checkpoint_uses_hot_journal_current_source_with_savepoint_rollback_wal_prefix_next155'],
    'bad checkpoint status' => [static fn (): mixed => $badCheckpoint()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-deferred-next155'],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next155'],
    'database path' => [static fn (): mixed => $ready()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ready()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ready()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ready()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $ready()['reader_end_frame'], 6],
    'rollback frame' => [static fn (): mixed => $ready()['savepoint_rollback_frame'], 2],
    'current frame count' => [static fn (): mixed => $ready()['current_wal_frame_count'], 6],
    'hot recovered' => [static fn (): mixed => $ready()['hot_recovered'], true],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'base checkpoint allowed' => [static fn (): mixed => $ready()['base_checkpoint_allowed'], true],
    'blocked base checkpoint disallowed' => [static fn (): mixed => $blocked()['base_checkpoint_allowed'], false],
    'checkpoint allowed' => [static fn (): mixed => $ready()['checkpoint_allowed'], true],
    'bad checkpoint disallowed' => [static fn (): mixed => $badCheckpoint()['checkpoint_allowed'], false],
    'checkpoint matches rollback' => [static fn (): mixed => $ready()['checkpoint_matches_savepoint_rollback_source'], true],
    'bad checkpoint mismatch false' => [static fn (): mixed => $badCheckpoint()['checkpoint_matches_savepoint_rollback_source'], false],
    'bad mismatch pages' => [static fn (): mixed => $badCheckpoint()['checkpoint_mismatched_page_numbers'], [2]],
    'ready mismatch pages empty' => [static fn (): mixed => $ready()['checkpoint_mismatched_page_numbers'], []],
    'reader post rollback pages' => [static fn (): mixed => $ready()['reader_post_rollback_page_numbers'], [3, 4, 5, 6]],
    'reader post rollback count' => [static fn (): mixed => $ready()['reader_post_rollback_page_count'], 4],
    'reader at rollback has no post rollback pages' => [static fn (): mixed => $readerAtRollback()['reader_post_rollback_page_numbers'], []],
    'discarded current pages' => [static fn (): mixed => $ready()['discarded_current_page_numbers'], [3, 4, 5, 6]],
    'discarded current count' => [static fn (): mixed => $ready()['discarded_current_page_count'], 4],
    'reader reopen required' => [static fn (): mixed => $ready()['reader_reopen_required'], true],
    'reader at rollback reopen false' => [static fn (): mixed => $readerAtRollback()['reader_reopen_required'], false],
    'bad checkpoint reopen true' => [static fn (): mixed => $badCheckpoint()['reader_reopen_required'], true],
    'reader sources' => [static fn (): mixed => $ready()['reader_sources'], ['wal', 'wal', 'wal', 'wal', 'wal', 'wal']],
    'reader frames' => [static fn (): mixed => $ready()['reader_frame_indexes'], [1, 2, 3, 4, 5, 6]],
    'rollback expected sources' => [static fn (): mixed => $ready()['rollback_expected_sources'], ['wal', 'wal', 'database', 'database', 'database', 'database']],
    'rollback expected frames' => [static fn (): mixed => $ready()['rollback_expected_frame_indexes'], [1, 2, null, null, null, null]],
    'full current sources' => [static fn (): mixed => $ready()['full_current_sources'], ['wal', 'wal', 'wal', 'wal', 'wal', 'wal']],
    'full current frames' => [static fn (): mixed => $ready()['full_current_frame_indexes'], [1, 2, 3, 4, 5, 6]],
    'checkpoint sources' => [static fn (): mixed => $ready()['checkpoint_sources'], ['checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'reader label page four' => [static fn (): mixed => $ready()['reader_labels'][3], 'next155 rolled back autoload savepoint frame'],
    'rollback label page four' => [static fn (): mixed => $ready()['rollback_expected_labels'][3], 'next155 clean autoload index before import'],
    'full current label page six' => [static fn (): mixed => $ready()['full_current_labels'][5], 'next155 rolled back transient savepoint frame'],
    'checkpoint label page six' => [static fn (): mixed => $ready()['checkpoint_labels'][5], 'next155 clean transient timeout before import'],
    'checkpoint labels equal rollback labels' => [static fn (): mixed => $ready()['checkpoint_labels'], $ready()['rollback_expected_labels']],
    'reader differs from rollback labels' => [static fn (): mixed => $ready()['reader_labels'] !== $ready()['rollback_expected_labels'], true],
    'source transitions' => [static fn (): mixed => $ready()['source_transitions'], [
        'wal>savepoint-rollback>wal>checkpoint-db',
        'wal>savepoint-rollback>wal>checkpoint-db',
        'wal>savepoint-rollback>database>checkpoint-db',
        'wal>savepoint-rollback>database>checkpoint-db',
        'wal>savepoint-rollback>database>checkpoint-db',
        'wal>savepoint-rollback>database>checkpoint-db',
    ]],
    'source digest length' => [static fn (): mixed => strlen($ready()['source_digest']), 64],
    'checkpoint database source page count' => [static fn (): mixed => $ready()['checkpoint_database_source']['page_count'], 6],
    'checkpoint database source sha length' => [static fn (): mixed => strlen($ready()['checkpoint_database_source']['sha256']), 64],
    'row count' => [static fn (): mixed => count($ready()['rows']), 6],
    'row pages' => [static fn (): mixed => array_column($ready()['rows'], 'page_number'), [1, 2, 3, 4, 5, 6]],
    'row two checkpoint matches' => [static fn (): mixed => $ready()['rows'][1]['checkpoint_matches_rollback_source'], true],
    'bad row two checkpoint mismatch' => [static fn (): mixed => $badCheckpoint()['rows'][1]['checkpoint_matches_rollback_source'], false],
    'row four reader kept post rollback' => [static fn (): mixed => $ready()['rows'][3]['reader_kept_post_rollback_frame'], true],
    'row two reader not post rollback' => [static fn (): mixed => $ready()['rows'][1]['reader_kept_post_rollback_frame'], false],
    'row six full differs from rollback' => [static fn (): mixed => $ready()['rows'][5]['full_current_differs_from_rollback'], true],
    'row one full does not differ' => [static fn (): mixed => $ready()['rows'][0]['full_current_differs_from_rollback'], false],
    'base status' => [static fn (): mixed => $ready()['base_plan']['status'], 'wal-checkpoint-hot-journal-reader-current-source-next144'],
    'blocked base status' => [static fn (): mixed => $blocked()['base_plan']['status'], 'wal-checkpoint-hot-journal-reader-current-source-blocked-next144'],
    'operation apply prefix' => [static fn (): mixed => in_array('apply_checkpoint_from_savepoint_rollback_wal_prefix_next155', $ready()['operation_reasons'], true), true],
    'operation require reopen' => [static fn (): mixed => in_array('require_reader_reopen_for_post_rollback_frames_next155', $ready()['operation_reasons'], true), true],
    'operation defer' => [static fn (): mixed => in_array('defer_checkpoint_until_savepoint_rollback_source_matches_next155', $badCheckpoint()['operation_reasons'], true), true],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next155', $ready()['dependencies'], true), true],
    'dependency next144' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-hot-journal-reader-current-source-next144', $ready()['dependencies'], true), true],
    'dependency prefix' => [static fn (): mixed => in_array('sqlite-savepoint-rollback-wal-prefix-current-source', $ready()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ready()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ready()['non_overlap'], 'savepoint rollback WAL prefix'), true],
    'single page status' => [static fn (): mixed => $single()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next155'],
    'single page reader post rollback' => [static fn (): mixed => $single()['reader_post_rollback_page_numbers'], [4]],
    'single page checkpoint label' => [static fn (): mixed => $single()['checkpoint_labels'], ['next155 clean autoload index before import']],
    'single page rollback expected source' => [static fn (): mixed => $single()['rollback_expected_sources'], ['database']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next155 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rollback frame below zero rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, -1),
    'rollback frame past current wal rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 7),
    'reader before rollback rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 4, 2),
    'empty checkpoint rejected' => static fn () => $plan($hotDatabase, $walBytes, ''),
    'unaligned checkpoint rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase . 'x'),
    'zero page rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 2, 6, false, [0]),
    'string page rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 2, 6, false, ['1']),
    'page outside checkpoint rejected' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 2, 6, false, [7]),
    'empty path rejected by base' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext155Plan::plan('', $dirtyDatabase, $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, $checkpointDatabase, [1], 1, 1),
    'empty dirty database rejected by base' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext155Plan::plan($databasePath, '', $journalBytes, $currentWal, $walBytes, $hotDatabase, $walBytes, $checkpointDatabase, [1], 1, 1),
    'empty journal rejected by base' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext155Plan::plan($databasePath, $dirtyDatabase, '', $currentWal, $walBytes, $hotDatabase, $walBytes, $checkpointDatabase, [1], 1, 1),
    'reader past wal rejected by base' => static fn () => $plan($hotDatabase, $walBytes, $checkpointDatabase, 3, 7),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next155 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
