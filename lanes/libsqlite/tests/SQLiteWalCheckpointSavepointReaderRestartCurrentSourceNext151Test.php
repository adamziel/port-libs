<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$salt1 = 0x15115101;
$salt2 = 0x15115102;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next151 base schema')
    . $page('next151 base active plugins')
    . $page('next151 base plugin settings')
    . $page('next151 base transient cache')
    . $page('next151 base cron option');

$makeWalBytes = static function (int $checkpoint = 151, int $firstSalt = 0x15115101) use ($pageSize, $salt2, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $firstSalt, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ([
        [1, 0, 'next151 retained schema draft'],
        [2, 5, 'next151 retained active plugins commit'],
        [3, 0, 'next151 rolled plugin settings draft'],
        [3, 5, 'next151 rolled plugin settings commit'],
        [4, 0, 'next151 rolled transient draft'],
        [4, 5, 'next151 rolled transient commit'],
        [5, 5, 'next151 rolled cron option commit'],
    ] as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $firstSalt, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 7, int $firstSalt = 0x15115101) use ($pageSize, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 151, $pageSizeField, $mxFrame, 5, 1, 2, $firstSalt, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$walBytes = $makeWalBytes();
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$activeShm = SQLiteShmIndex::parse($makeShm([0, 6, null, null, null], [false, true, false, false, false], 2, 5));
$releasedShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 7, 7));

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next151');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next151');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 3, true);
    $stack->recordWalFrameWrite(5, 4);
    $stack->recordWalFrameWrite(6, 4, true);
    $stack->recordWalFrameWrite(7, 5, true);

    return $stack;
};

$plan = static fn (array $pages = [1, 2, 3, 4, 5], ?string $readerBytes = null): array => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext(
    $makeStack(),
    'plugin-settings-next151',
    $wal,
    $walBytes,
    $readerBytes ?? $walBytes,
    $databaseBytes,
    $activeShm,
    $releasedShm,
    '/srv/www/wp-content/database/wp-next151.sqlite',
    $pages
);

$ok = static fn (): array => $plan();
$single = static fn (): array => $plan([3]);
$stale = static fn (): array => $plan([3], $makeWalBytes(152));

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-checkpoint-savepoint-reader-restart-current-source-next151'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'released_restart_checkpoint_restarts_reader_on_fresh_current_source_before_next_writer_append'],
    'savepoint' => [static fn (): mixed => $ok()['savepoint'], 'plugin-settings-next151'],
    'mode' => [static fn (): mixed => $ok()['mode'], 'restart'],
    'database path' => [static fn (): mixed => $ok()['database_path'], '/srv/www/wp-content/database/wp-next151.sqlite'],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], '/srv/www/wp-content/database/wp-next151.sqlite-wal'],
    'current source verified' => [static fn (): mixed => $ok()['current_source_verified'], true],
    'shm source verified' => [static fn (): mixed => $ok()['shm_source_verified'], true],
    'reader source matches' => [static fn (): mixed => $ok()['reader_source_matches_current'], true],
    'current sha length' => [static fn (): mixed => strlen($ok()['current_wal_sha256']), 64],
    'reader sha length' => [static fn (): mixed => strlen($ok()['reader_wal_sha256']), 64],
    'active reader frame' => [static fn (): mixed => $ok()['active_reader_end_frame'], 6],
    'writer current frame' => [static fn (): mixed => $ok()['writer_current_reader_end_frame'], 2],
    'restart reader frame' => [static fn (): mixed => $ok()['restart_reader_end_frame'], 0],
    'retained frame count' => [static fn (): mixed => $ok()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $ok()['discarded_frame_count'], 5],
    'rolled back frames' => [static fn (): mixed => $ok()['rolled_back_frame_indexes'], [3, 4, 5, 6, 7]],
    'rolled back pages' => [static fn (): mixed => $ok()['rolled_back_page_numbers'], [3, 4, 5]],
    'active checkpoint busy' => [static fn (): mixed => $ok()['active_checkpoint_busy'], true],
    'active checkpoint reason' => [static fn (): mixed => $ok()['active_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'active wal action' => [static fn (): mixed => $ok()['active_wal_action'], 'preserve_wal'],
    'active wal length' => [static fn (): mixed => $ok()['active_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released checkpoint ready' => [static fn (): mixed => $ok()['released_checkpoint_busy'], false],
    'released reason' => [static fn (): mixed => $ok()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released action' => [static fn (): mixed => $ok()['released_wal_action'], 'restart_wal'],
    'released wal header retained' => [static fn (): mixed => $ok()['released_wal_bytes_length'], 32],
    'released database sha length' => [static fn (): mixed => strlen($ok()['released_database_sha256']), 64],
    'fresh frame count' => [static fn (): mixed => $ok()['fresh_wal_frame_count'], 0],
    'fresh wal length' => [static fn (): mixed => $ok()['fresh_wal_bytes_length'], 32],
    'fresh checkpoint sequence' => [static fn (): mixed => $ok()['fresh_wal_checkpoint_sequence'], 152],
    'fresh salt' => [static fn (): mixed => $ok()['fresh_wal_salt'], [0x15115102, 0x15115102]],
    'active sources' => [static fn (): mixed => $ok()['active_reader_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'writer current sources' => [static fn (): mixed => $ok()['writer_current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'released restart sources' => [static fn (): mixed => $ok()['released_restart_sources'], ['database', 'database', 'database', 'database', 'database']],
    'restart reader sources' => [static fn (): mixed => $ok()['restart_reader_sources'], ['database', 'database', 'database', 'database', 'database']],
    'active frames' => [static fn (): mixed => $ok()['active_reader_frame_indexes'], [1, 2, 4, 6, null]],
    'writer frames' => [static fn (): mixed => $ok()['writer_current_frame_indexes'], [1, 2, null, null, null]],
    'released frames' => [static fn (): mixed => $ok()['released_restart_frame_indexes'], [null, null, null, null, null]],
    'restart frames' => [static fn (): mixed => $ok()['restart_reader_frame_indexes'], [null, null, null, null, null]],
    'row count' => [static fn (): mixed => count($ok()['current_source_rows']), 5],
    'source transitions' => [static fn (): mixed => $ok()['source_transitions'], ['wal>wal>database>database', 'wal>wal>database>database', 'wal>database>database>database', 'wal>database>database>database', 'database>database>database>database']],
    'active keeps original' => [static fn (): mixed => $ok()['active_reader_keeps_original_wal'], true],
    'writer retained prefix' => [static fn (): mixed => $ok()['writer_current_uses_retained_prefix'], true],
    'active blocks restart' => [static fn (): mixed => $ok()['active_reader_blocks_restart_reset'], true],
    'release unblocks restart' => [static fn (): mixed => $ok()['reader_release_unblocks_restart'], true],
    'released keeps header' => [static fn (): mixed => $ok()['released_restart_keeps_header'], true],
    'restart fresh current source' => [static fn (): mixed => $ok()['restart_reader_uses_fresh_current_source'], true],
    'restart separated stale source' => [static fn (): mixed => $ok()['restart_reader_separated_from_stale_source'], true],
    'restart matches writer current' => [static fn (): mixed => $ok()['restart_matches_writer_current'], true],
    'reader restart required' => [static fn (): mixed => $ok()['reader_restart_required'], true],
    'plugin active label' => [static fn (): mixed => str_contains($ok()['current_source_rows'][2]['active_reader_label'], 'rolled plugin settings commit'), true],
    'plugin writer label' => [static fn (): mixed => str_contains($ok()['current_source_rows'][2]['writer_current_label'], 'base plugin settings'), true],
    'plugin restart label' => [static fn (): mixed => str_contains($ok()['current_source_rows'][2]['restart_reader_label'], 'base plugin settings'), true],
    'digest length' => [static fn (): mixed => strlen($ok()['source_digest']), 64],
    'yield count' => [static fn (): mixed => $ok()['yield_count'], 26],
    'dependency next151' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-savepoint-reader-restart-current-source-next151', $ok()['dependencies'], true), true],
    'dependency restart' => [static fn (): mixed => in_array('sqlite-wal-current-source-reader-restart', $ok()['dependencies'], true), true],
    'dependency fresh generation' => [static fn (): mixed => in_array('sqlite-wal-restart-fresh-generation', $ok()['dependencies'], true), true],
    'dependency closure text' => [static fn (): mixed => $ok()['dependency_closure'], 'no new support component needed; reuses native WAL parser/checkpoint/savepoint, SHM read-mark, and fresh WAL header helpers'],
    'non overlap mentions next145' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'next145'), true],
    'single page transition' => [static fn (): mixed => $single()['source_transitions'], ['wal>database>database>database']],
    'single page restart label' => [static fn (): mixed => $single()['current_source_rows'][0]['restart_reader_label'], 'next151 base plugin settings'],
    'stale status blocked' => [static fn (): mixed => $stale()['status'], 'wal-checkpoint-savepoint-reader-restart-current-source-blocked-next151'],
    'stale reason' => [static fn (): mixed => $stale()['reason'], 'reader_wal_source_mismatch_requires_reopen_before_savepoint_restart_checkpoint'],
    'stale mismatch' => [static fn (): mixed => $stale()['reader_source_matches_current'], false],
    'stale restart required' => [static fn (): mixed => $stale()['reader_restart_required'], true],
    'stale restart not fresh' => [static fn (): mixed => $stale()['restart_reader_uses_fresh_current_source'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint savepoint reader restart current source next151 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint savepoint reader restart current source next151 rejects empty savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext($makeStack(), '', $wal, $walBytes, $walBytes, $databaseBytes, $activeShm, $releasedShm, '/tmp/wp.sqlite', [1]));
};

$tests['wal checkpoint savepoint reader restart current source next151 rejects empty reader bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext($makeStack(), 'plugin-settings-next151', $wal, $walBytes, '', $databaseBytes, $activeShm, $releasedShm, '/tmp/wp.sqlite', [1]));
};

$tests['wal checkpoint savepoint reader restart current source next151 rejects empty database'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $activeShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext($makeStack(), 'plugin-settings-next151', $wal, $walBytes, $walBytes, '', $activeShm, $releasedShm, '/tmp/wp.sqlite', [1]));
};

$tests['wal checkpoint savepoint reader restart current source next151 rejects empty path'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext($makeStack(), 'plugin-settings-next151', $wal, $walBytes, $walBytes, $databaseBytes, $activeShm, $releasedShm, '', [1]));
};

$tests['wal checkpoint savepoint reader restart current source next151 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext($makeStack(), 'plugin-settings-next151', $wal, $walBytes, $walBytes, $databaseBytes, $activeShm, $releasedShm, '/tmp/wp.sqlite', []));
};

$tests['wal checkpoint savepoint reader restart current source next151 rejects stale current wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $makeWalBytes, $walBytes, $databaseBytes, $activeShm, $releasedShm): void {
    $staleCurrent = $makeWalBytes(152);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext($makeStack(), 'plugin-settings-next151', $wal, $staleCurrent, $walBytes, $databaseBytes, $activeShm, $releasedShm, '/tmp/wp.sqlite', [1]));
};

$tests['wal checkpoint savepoint reader restart current source next151 rejects active shm salt mismatch'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $makeShm, $releasedShm): void {
    $badShm = SQLiteShmIndex::parse($makeShm([0, 6], [false, true], 2, 5, 7, 0x15115103));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext($makeStack(), 'plugin-settings-next151', $wal, $walBytes, $walBytes, $databaseBytes, $badShm, $releasedShm, '/tmp/wp.sqlite', [1]));
};

$tests['wal checkpoint savepoint reader restart current source next151 rejects missing active pin'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $makeShm, $releasedShm): void {
    $unpinned = SQLiteShmIndex::parse($makeShm([0, null], [false, false], 7, 7));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext($makeStack(), 'plugin-settings-next151', $wal, $walBytes, $walBytes, $databaseBytes, $unpinned, $releasedShm, '/tmp/wp.sqlite', [1]));
};

$tests['wal checkpoint savepoint reader restart current source next151 rejects unreleased shm'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext($makeStack(), 'plugin-settings-next151', $wal, $walBytes, $walBytes, $databaseBytes, $activeShm, $activeShm, '/tmp/wp.sqlite', [1]));
};

$tests['wal checkpoint savepoint reader restart current source next151 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext($makeStack(), 'plugin-settings-next151', $wal, $walBytes, $walBytes, $databaseBytes, $activeShm, $releasedShm, '/tmp/wp.sqlite', ['1']));
};

return $tests;
