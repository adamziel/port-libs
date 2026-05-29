<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$salt1 = 0x13913901;
$salt2 = 0x13913902;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next139 base schema')
    . $page('next139 base active_plugins')
    . $page('next139 base plugin settings')
    . $page('next139 base transient cache')
    . $page('next139 base option index');

$makeWalBytes = static function (int $checkpoint = 139, int $firstSalt = 0x13913901) use ($pageSize, $salt2, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $firstSalt, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ([
        [1, 0, 'next139 retained schema draft'],
        [2, 5, 'next139 retained active_plugins commit'],
        [3, 0, 'next139 rolled plugin settings draft'],
        [3, 5, 'next139 rolled plugin settings commit'],
        [4, 0, 'next139 rolled transient draft'],
        [4, 5, 'next139 rolled transient commit'],
        [5, 5, 'next139 rolled option index commit'],
    ] as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $firstSalt, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 7, int $firstSalt = 0x13913901) use ($pageSize, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 139, $pageSizeField, $mxFrame, 5, 1, 2, $firstSalt, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$walBytes = $makeWalBytes();
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$activeShm = SQLiteShmIndex::parse($makeShm([0, 6, null, null, null], [false, true, false, false, false], 1, 4));
$releasedShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 7, 7));

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next139');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next139');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 3, true);
    $stack->savepoint('transient-cache-next139');
    $stack->recordWalFrameWrite(5, 4);
    $stack->recordWalFrameWrite(6, 4, true);
    $stack->recordWalFrameWrite(7, 5, true);

    return $stack;
};

$plan = static fn (string $mode = 'restart', array $pages = [1, 2, 3, 4, 5]): array => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointCurrentSourceNext(
    $makeStack(),
    'plugin-settings-next139',
    $wal,
    $walBytes,
    $databaseBytes,
    $activeShm,
    $releasedShm,
    $pages,
    $mode
);
$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'reader-checkpoint-savepoint-current-source-next139'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next139'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'current source verified' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'shm source verified' => [static fn (): mixed => $restart()['shm_source_verified'], true],
    'active reader frame' => [static fn (): mixed => $restart()['active_reader_end_frame'], 6],
    'writer current frame' => [static fn (): mixed => $restart()['writer_current_reader_end_frame'], 2],
    'active next frame' => [static fn (): mixed => $restart()['active_next_reader_end_frame'], 2],
    'released next frame' => [static fn (): mixed => $restart()['released_next_reader_end_frame'], 0],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 5],
    'rolled back frames' => [static fn (): mixed => $restart()['rolled_back_frame_indexes'], [3, 4, 5, 6, 7]],
    'rolled back pages' => [static fn (): mixed => $restart()['rolled_back_page_numbers'], [3, 4, 5]],
    'current source frame count' => [static fn (): mixed => $restart()['current_source']['frame_count'], 7],
    'retained source frame count' => [static fn (): mixed => $restart()['retained_source']['frame_count'], 2],
    'current source checkpoint' => [static fn (): mixed => $restart()['current_source']['checkpoint_sequence'], 139],
    'active shm pin' => [static fn (): mixed => $restart()['active_shm_source']['checkpoint_pinned_frame'], 6],
    'active shm reset blocked' => [static fn (): mixed => $restart()['active_shm_source']['reset_blocked'], true],
    'released shm reset clear' => [static fn (): mixed => $restart()['released_shm_source']['reset_blocked'], false],
    'active action preserves wal' => [static fn (): mixed => $restart()['active_wal_action'], 'preserve_wal'],
    'released action restart' => [static fn (): mixed => $restart()['released_wal_action'], 'restart_wal'],
    'active checkpoint busy' => [static fn (): mixed => $restart()['active_checkpoint_busy'], true],
    'released checkpoint ready' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'active reason reset blocked' => [static fn (): mixed => $restart()['active_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'released reason restart' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'active sources' => [static fn (): mixed => $restart()['active_reader_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'writer current sources' => [static fn (): mixed => $restart()['writer_current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'active next sources' => [static fn (): mixed => $restart()['active_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'released next sources' => [static fn (): mixed => $restart()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'active frames' => [static fn (): mixed => $restart()['active_reader_frame_indexes'], [1, 2, 4, 6, null]],
    'writer frames' => [static fn (): mixed => $restart()['writer_current_frame_indexes'], [1, 2, null, null, null]],
    'active next frames' => [static fn (): mixed => $restart()['active_next_frame_indexes'], [1, 2, null, null, null]],
    'released next frames' => [static fn (): mixed => $restart()['released_next_frame_indexes'], [null, null, null, null, null]],
    'reader keeps original wal' => [static fn (): mixed => $restart()['active_reader_keeps_original_wal'], true],
    'writer uses retained prefix' => [static fn (): mixed => $restart()['writer_current_uses_retained_prefix'], true],
    'active blocks checkpoint reset' => [static fn (): mixed => $restart()['active_reader_blocks_checkpoint_reset'], true],
    'release unblocks checkpoint' => [static fn (): mixed => $restart()['reader_release_unblocks_checkpoint'], true],
    'released uses checkpoint db' => [static fn (): mixed => $restart()['released_next_uses_checkpoint_database'], true],
    'released matches writer current' => [static fn (): mixed => $restart()['released_next_matches_writer_current'], true],
    'plugin row held rolled back frame' => [static fn (): mixed => $restart()['current_source_rows'][2]['reader_held_rolled_back_frame'], true],
    'transient row rolled back' => [static fn (): mixed => $restart()['current_source_rows'][3]['writer_rolled_back_reader_image'], true],
    'option index next matches writer' => [static fn (): mixed => $restart()['current_source_rows'][4]['released_next_matches_writer_current'], true],
    'plugin active label' => [static fn (): mixed => str_contains($restart()['current_source_rows'][2]['active_reader_label'], 'rolled plugin settings commit'), true],
    'plugin current label' => [static fn (): mixed => str_contains($restart()['current_source_rows'][2]['writer_current_label'], 'base plugin settings'), true],
    'transient active label' => [static fn (): mixed => str_contains($restart()['current_source_rows'][3]['active_reader_label'], 'rolled transient commit'), true],
    'transition for plugin' => [static fn (): mixed => $restart()['source_transitions'][2], 'wal>database>database>database'],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'yield count' => [static fn (): mixed => $restart()['yield_count'], 25],
    'dependency next139' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-savepoint-current-source-next139', $restart()['dependencies'], true), true],
    'dependency checkpoint' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $restart()['dependencies'], true), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate released action' => [static fn (): mixed => $truncate()['released_wal_action'], 'truncate_wal'],
    'truncate released frame' => [static fn (): mixed => $truncate()['released_next_reader_end_frame'], 0],
    'truncate released sources' => [static fn (): mixed => $truncate()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'truncate released matches writer' => [static fn (): mixed => $truncate()['released_next_matches_writer_current'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader checkpoint savepoint current source next139 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader checkpoint savepoint current source next139 rejects stale wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $makeWalBytes, $databaseBytes, $activeShm, $releasedShm): void {
    $staleBytes = $makeWalBytes(140);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next139', $wal, $staleBytes, $databaseBytes, $activeShm, $releasedShm, [1]));
};

$tests['wal reader checkpoint savepoint current source next139 rejects active shm salt mismatch'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $makeShm, $releasedShm): void {
    $badShm = SQLiteShmIndex::parse($makeShm([0, 6], [false, true], 1, 4, 7, 0x13913903));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next139', $wal, $walBytes, $databaseBytes, $badShm, $releasedShm, [1]));
};

$tests['wal reader checkpoint savepoint current source next139 rejects released shm mx frame mismatch'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $makeShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointCurrentSourceNext(
        $makeStack(),
        'plugin-settings-next139',
        $wal,
        $walBytes,
        $databaseBytes,
        $activeShm,
        SQLiteShmIndex::parse($makeShm([0], [false], 7, 7, 6)),
        [1]
    ));
};

$tests['wal reader checkpoint savepoint current source next139 rejects missing active pin'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $makeShm, $releasedShm): void {
    $unpinned = SQLiteShmIndex::parse($makeShm([0, null], [false, false], 7, 7));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next139', $wal, $walBytes, $databaseBytes, $unpinned, $releasedShm, [1]));
};

$tests['wal reader checkpoint savepoint current source next139 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next139', $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm, []));
};

$tests['wal reader checkpoint savepoint current source next139 rejects bad mode'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointCurrentSourceNext($makeStack(), 'plugin-settings-next139', $wal, $walBytes, $databaseBytes, $activeShm, $releasedShm, [1], 'passive'));
};

return $tests;
