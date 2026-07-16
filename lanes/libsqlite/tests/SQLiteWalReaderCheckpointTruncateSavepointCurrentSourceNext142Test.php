<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$salt1 = 0x14214201;
$salt2 = 0x14214202;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next142 base schema')
    . $page('next142 base active plugins')
    . $page('next142 base plugin settings')
    . $page('next142 base transient cache')
    . $page('next142 base autoload index')
    . $page('next142 base cron option');

$makeWalBytes = static function (int $checkpoint = 142, int $firstSalt = 0x14214201) use ($pageSize, $salt2, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $firstSalt, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ([
        [1, 0, 'next142 retained schema draft'],
        [2, 6, 'next142 retained active_plugins commit'],
        [3, 0, 'next142 rolled plugin settings draft'],
        [3, 6, 'next142 rolled plugin settings commit'],
        [4, 0, 'next142 rolled transient draft'],
        [4, 6, 'next142 rolled transient commit'],
        [5, 6, 'next142 rolled autoload index commit'],
        [6, 6, 'next142 rolled cron option commit'],
    ] as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $firstSalt, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 8, int $firstSalt = 0x14214201) use ($pageSize, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 142, $pageSizeField, $mxFrame, 6, 1, 2, $firstSalt, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$walBytes = $makeWalBytes();
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$activeShm = SQLiteShmIndex::parse($makeShm([0, 7, null, null, null], [false, true, false, false, false], 2, 5));
$releasedShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 8, 8));
$transactions = [[
    'pages' => [
        3 => $page('next142 appended plugin settings fresh generation'),
        6 => $page('next142 appended cron option fresh generation'),
    ],
    'database_page_count' => 6,
    'commit' => true,
]];

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next142');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next142');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 3, true);
    $stack->savepoint('transient-cache-next142');
    $stack->recordWalFrameWrite(5, 4);
    $stack->recordWalFrameWrite(6, 4, true);
    $stack->recordWalFrameWrite(7, 5, true);
    $stack->recordWalFrameWrite(8, 6, true);

    return $stack;
};

$plan = static fn (array $pages = [1, 2, 3, 4, 5, 6], array $next = null): array => SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext(
    $makeStack(),
    'plugin-settings-next142',
    $wal,
    $walBytes,
    $databaseBytes,
    $activeShm,
    $releasedShm,
    '/srv/www/wp-content/database/wp-next142.sqlite',
    $next ?? $transactions,
    $pages
);

$ok = static fn (): array => $plan();
$single = static fn (): array => $plan([3]);

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'reader-checkpoint-truncate-savepoint-current-source-next142'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'active_current_source_reader_pins_savepoint_truncate_until_release_then_next_writer_uses_fresh_wal_generation'],
    'savepoint' => [static fn (): mixed => $ok()['savepoint'], 'plugin-settings-next142'],
    'mode' => [static fn (): mixed => $ok()['mode'], 'truncate'],
    'database path' => [static fn (): mixed => $ok()['database_path'], '/srv/www/wp-content/database/wp-next142.sqlite'],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], '/srv/www/wp-content/database/wp-next142.sqlite-wal'],
    'current source verified' => [static fn (): mixed => $ok()['current_source_verified'], true],
    'shm source verified' => [static fn (): mixed => $ok()['shm_source_verified'], true],
    'active reader frame' => [static fn (): mixed => $ok()['active_reader_end_frame'], 7],
    'writer current frame' => [static fn (): mixed => $ok()['writer_current_reader_end_frame'], 2],
    'released next frame' => [static fn (): mixed => $ok()['released_next_reader_end_frame'], 0],
    'appended next frame' => [static fn (): mixed => $ok()['appended_next_reader_end_frame'], 2],
    'retained frame count' => [static fn (): mixed => $ok()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $ok()['discarded_frame_count'], 6],
    'rolled back frames' => [static fn (): mixed => $ok()['rolled_back_frame_indexes'], [3, 4, 5, 6, 7, 8]],
    'rolled back pages' => [static fn (): mixed => $ok()['rolled_back_page_numbers'], [3, 4, 5, 6]],
    'active checkpoint busy' => [static fn (): mixed => $ok()['active_checkpoint_busy'], true],
    'active checkpoint reason' => [static fn (): mixed => $ok()['active_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'active wal action' => [static fn (): mixed => $ok()['active_wal_action'], 'preserve_wal'],
    'active wal length' => [static fn (): mixed => $ok()['active_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released checkpoint ready' => [static fn (): mixed => $ok()['released_checkpoint_busy'], false],
    'released reason' => [static fn (): mixed => $ok()['released_checkpoint_reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'released action' => [static fn (): mixed => $ok()['released_wal_action'], 'truncate_wal'],
    'released wal removed' => [static fn (): mixed => $ok()['released_wal_bytes_length'], 0],
    'released database sha length' => [static fn (): mixed => strlen($ok()['released_database_sha256']), 64],
    'fresh checkpoint sequence' => [static fn (): mixed => $ok()['fresh_wal_checkpoint_sequence'], 143],
    'fresh salt' => [static fn (): mixed => $ok()['fresh_wal_salt'], [0x14214202, 0x14214202]],
    'append start' => [static fn (): mixed => $ok()['append_start_frame'], 1],
    'append end' => [static fn (): mixed => $ok()['append_end_frame'], 2],
    'append count' => [static fn (): mixed => $ok()['append_frame_count'], 2],
    'append commit frame' => [static fn (): mixed => $ok()['append_last_commit_frame'], 2],
    'append wal length' => [static fn (): mixed => $ok()['append_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'active sources' => [static fn (): mixed => $ok()['active_reader_sources'], ['wal', 'wal', 'wal', 'wal', 'wal', 'database']],
    'writer current sources' => [static fn (): mixed => $ok()['writer_current_sources'], ['wal', 'wal', 'database', 'database', 'database', 'database']],
    'released truncate sources' => [static fn (): mixed => $ok()['released_truncate_sources'], ['database', 'database', 'database', 'database', 'database', 'database']],
    'appended next sources' => [static fn (): mixed => $ok()['appended_next_sources'], ['database', 'database', 'wal', 'database', 'database', 'wal']],
    'active frames' => [static fn (): mixed => $ok()['active_reader_frame_indexes'], [1, 2, 4, 6, 7, null]],
    'writer frames' => [static fn (): mixed => $ok()['writer_current_frame_indexes'], [1, 2, null, null, null, null]],
    'released frames' => [static fn (): mixed => $ok()['released_truncate_frame_indexes'], [null, null, null, null, null, null]],
    'appended frames' => [static fn (): mixed => $ok()['appended_next_frame_indexes'], [null, null, 1, null, null, 2]],
    'row count' => [static fn (): mixed => count($ok()['current_source_rows']), 6],
    'source transitions' => [static fn (): mixed => $ok()['source_transitions'], ['wal>wal>database>database', 'wal>wal>database>database', 'wal>database>database>wal', 'wal>database>database>database', 'wal>database>database>database', 'database>database>database>wal']],
    'active reader keeps original wal' => [static fn (): mixed => $ok()['active_reader_keeps_original_wal'], true],
    'writer retained prefix' => [static fn (): mixed => $ok()['writer_current_uses_retained_prefix'], true],
    'active blocks truncate' => [static fn (): mixed => $ok()['active_reader_blocks_truncate_reset'], true],
    'release unblocks truncate' => [static fn (): mixed => $ok()['reader_release_unblocks_truncate'], true],
    'released removes wal' => [static fn (): mixed => $ok()['released_truncate_removes_wal'], true],
    'released uses db' => [static fn (): mixed => $ok()['released_next_uses_checkpoint_database'], true],
    'released matches writer' => [static fn (): mixed => $ok()['released_next_matches_writer_current'], true],
    'appended fresh generation' => [static fn (): mixed => $ok()['appended_next_uses_fresh_generation'], true],
    'appended separated' => [static fn (): mixed => $ok()['appended_next_separated_from_released_truncate'], true],
    'plugin active label' => [static fn (): mixed => str_contains($ok()['current_source_rows'][2]['active_reader_label'], 'rolled plugin settings commit'), true],
    'plugin writer label' => [static fn (): mixed => str_contains($ok()['current_source_rows'][2]['writer_current_label'], 'base plugin settings'), true],
    'plugin appended label' => [static fn (): mixed => str_contains($ok()['current_source_rows'][2]['appended_next_label'], 'appended plugin settings'), true],
    'cron appended label' => [static fn (): mixed => str_contains($ok()['current_source_rows'][5]['appended_next_label'], 'appended cron option'), true],
    'append write op' => [static fn (): mixed => $ok()['append_operations'][0]['op'], 'write'],
    'append sync op' => [static fn (): mixed => $ok()['append_operations'][1]['op'], 'sync'],
    'append directory op' => [static fn (): mixed => $ok()['append_operations'][2]['op'], 'sync_directory'],
    'digest length' => [static fn (): mixed => strlen($ok()['source_digest']), 64],
    'yield count' => [static fn (): mixed => $ok()['yield_count'], 32],
    'dependency next142' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-truncate-savepoint-current-source-next142', $ok()['dependencies'], true), true],
    'dependency current prefix' => [static fn (): mixed => in_array('sqlite-wal-savepoint-current-prefix', $ok()['dependencies'], true), true],
    'dependency fresh generation' => [static fn (): mixed => in_array('sqlite-wal-truncate-fresh-generation', $ok()['dependencies'], true), true],
    'dependency closure text' => [static fn (): mixed => $ok()['dependency_closure'], 'no new support component needed; reuses native WAL parser/checkpoint/savepoint, SHM read-mark, and WAL append planning helpers'],
    'non overlap mentions next139' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'next139'), true],
    'single page transition' => [static fn (): mixed => $single()['source_transitions'], ['wal>database>database>wal']],
    'single page next label' => [static fn (): mixed => $single()['current_source_rows'][0]['appended_next_label'], 'next142 appended plugin settings fresh generation'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader checkpoint truncate savepoint current source next142 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader checkpoint truncate savepoint current source next142 rejects empty savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext($makeStack(), '', $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm, '/tmp/wp.sqlite', $transactions, [1]));
};

$tests['wal reader checkpoint truncate savepoint current source next142 rejects empty database'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $activeShm, $releasedShm, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next142', $wal, $walBytes, '', $activeShm, $releasedShm, '/tmp/wp.sqlite', $transactions, [1]));
};

$tests['wal reader checkpoint truncate savepoint current source next142 rejects empty path'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next142', $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm, '', $transactions, [1]));
};

$tests['wal reader checkpoint truncate savepoint current source next142 rejects empty transactions'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next142', $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm, '/tmp/wp.sqlite', [], [1]));
};

$tests['wal reader checkpoint truncate savepoint current source next142 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next142', $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm, '/tmp/wp.sqlite', $transactions, []));
};

$tests['wal reader checkpoint truncate savepoint current source next142 rejects stale wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $makeWalBytes, $databaseBytes, $activeShm, $releasedShm, $transactions): void {
    $staleBytes = $makeWalBytes(143);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next142', $wal, $staleBytes, $databaseBytes, $activeShm, $releasedShm, '/tmp/wp.sqlite', $transactions, [1]));
};

$tests['wal reader checkpoint truncate savepoint current source next142 rejects active shm salt mismatch'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $makeShm, $releasedShm, $transactions): void {
    $badShm = SQLiteShmIndex::parse($makeShm([0, 7], [false, true], 2, 5, 8, 0x14214203));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next142', $wal, $walBytes, $databaseBytes, $badShm, $releasedShm, '/tmp/wp.sqlite', $transactions, [1]));
};

$tests['wal reader checkpoint truncate savepoint current source next142 rejects missing active pin'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $makeShm, $releasedShm, $transactions): void {
    $unpinned = SQLiteShmIndex::parse($makeShm([0, null], [false, false], 8, 8));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next142', $wal, $walBytes, $databaseBytes, $unpinned, $releasedShm, '/tmp/wp.sqlite', $transactions, [1]));
};

$tests['wal reader checkpoint truncate savepoint current source next142 rejects unreleased shm'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next142', $wal, $walBytes, $databaseBytes, $activeShm, $activeShm, '/tmp/wp.sqlite', $transactions, [1]));
};

$tests['wal reader checkpoint truncate savepoint current source next142 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next142', $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm, '/tmp/wp.sqlite', $transactions, ['1']));
};

return $tests;
