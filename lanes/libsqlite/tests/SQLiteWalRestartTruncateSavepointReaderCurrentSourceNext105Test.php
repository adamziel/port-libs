<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$salt1 = 0x10510501;
$salt2 = 0x10510502;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next105 schema base')
    . $page('next105 options base')
    . $page('next105 plugin base')
    . $page('next105 autoload base')
    . $page('next105 transient base');

$makeWalBytes = static function (array $frames, int $checkpointSequence = 105, int $firstSalt = 0x10510501) use ($pageSize, $salt2, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $firstSalt, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $firstSalt, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 7, int $firstSalt = 0x10510501) use ($pageSize, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 105, $pageSizeField, $mxFrame, 5, 1, 2, $firstSalt, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$frames = [
    [1, 0, 'next105 schema draft retained'],
    [2, 5, 'next105 options commit retained'],
    [3, 0, 'next105 plugin draft rolled back'],
    [4, 0, 'next105 autoload draft rolled back'],
    [4, 5, 'next105 autoload commit rolled back'],
    [5, 5, 'next105 transient commit rolled back'],
    [2, 5, 'next105 options tail rolled back'],
];
$walBytes = $makeWalBytes($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$currentShm = SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4));
$nextReaderShm = SQLiteShmIndex::parse($makeShm([0, 2, 7, null, null], [false, true, true, false, false], 1, 6));
$allReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 7, 7));

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next105');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next105');
    $stack->recordWalFrameWrite(3, 3);
    $stack->savepoint('autoload-refresh-next105');
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 4, true);
    $stack->savepoint('transient-row-next105');
    $stack->recordWalFrameWrite(6, 5, true);
    $stack->recordWalFrameWrite(7, 2, true);

    return $stack;
};

$plan = static fn (): array => SQLiteWalSavepointCheckpointPlan::checkpointRestartTruncateSavepointReaderCurrentSourceNext(
    $makeStack(),
    'plugin-settings-next105',
    $wal,
    $walBytes,
    $databaseBytes,
    $currentShm,
    $nextReaderShm,
    $allReleasedShm,
    [1, 2, 3, 4, 5]
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'savepoint-reader-current-source-next105'],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'plugin-settings-next105'],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'shm source verified' => [static fn (): mixed => $plan()['shm_source_verified'], true],
    'current source frame count' => [static fn (): mixed => $plan()['current_source']['frame_count'], 7],
    'current source checkpoint sequence' => [static fn (): mixed => $plan()['current_source']['checkpoint_sequence'], 105],
    'current source salt one' => [static fn (): mixed => $plan()['current_source']['salt1'], $salt1],
    'current source salt two' => [static fn (): mixed => $plan()['current_source']['salt2'], $salt2],
    'retained frame count' => [static fn (): mixed => $plan()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $plan()['discarded_frame_count'], 5],
    'retained source frame count' => [static fn (): mixed => $plan()['retained_source']['frame_count'], 2],
    'current reader end frame' => [static fn (): mixed => $plan()['current_reader_end_frame'], 2],
    'next reader end frame' => [static fn (): mixed => $plan()['next_reader_end_frame'], 2],
    'released restart reader end frame' => [static fn (): mixed => $plan()['released_restart_reader_end_frame'], 0],
    'released truncate reader end frame' => [static fn (): mixed => $plan()['released_truncate_reader_end_frame'], 0],
    'current shm mx frame' => [static fn (): mixed => $plan()['current_shm_source']['mx_frame'], 7],
    'current shm backfilled frame count' => [static fn (): mixed => $plan()['current_shm_source']['backfilled_frame_count'], 1],
    'current shm attempted frame count' => [static fn (): mixed => $plan()['current_shm_source']['backfill_attempted_frame_count'], 4],
    'current shm salt one' => [static fn (): mixed => $plan()['current_shm_source']['salt1'], $salt1],
    'current shm salt two' => [static fn (): mixed => $plan()['current_shm_source']['salt2'], $salt2],
    'current shm pinned frame' => [static fn (): mixed => $plan()['current_shm_source']['checkpoint_pinned_frame'], 2],
    'current shm reset blocked' => [static fn (): mixed => $plan()['current_shm_source']['reset_blocked'], true],
    'next shm pinned frame' => [static fn (): mixed => $plan()['next_shm_source']['checkpoint_pinned_frame'], 2],
    'next shm reset blocked' => [static fn (): mixed => $plan()['next_shm_source']['reset_blocked'], true],
    'all released shm pinned frame' => [static fn (): mixed => $plan()['all_released_shm_source']['checkpoint_pinned_frame'], null],
    'all released shm reset unblocked' => [static fn (): mixed => $plan()['all_released_shm_source']['reset_blocked'], false],
    'restart released action' => [static fn (): mixed => $plan()['restart_final_wal_generation']['action'], 'restart_wal'],
    'restart released wal header only' => [static fn (): mixed => $plan()['restart_final_wal_generation']['wal_bytes_length'], 32],
    'restart released checkpoint increments' => [static fn (): mixed => $plan()['restart_final_wal_generation']['checkpoint_sequence'], 106],
    'truncate released action' => [static fn (): mixed => $plan()['truncate_final_wal_generation']['action'], 'truncate_wal'],
    'truncate released wal empty' => [static fn (): mixed => $plan()['truncate_final_wal_generation']['wal_bytes_length'], 0],
    'truncate released checkpoint absent' => [static fn (): mixed => $plan()['truncate_final_wal_generation']['checkpoint_sequence'], null],
    'current sources' => [static fn (): mixed => $plan()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'pinned next sources' => [static fn (): mixed => $plan()['pinned_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'restart released sources' => [static fn (): mixed => $plan()['restart_released_sources'], ['database', 'database', 'database', 'database', 'database']],
    'truncate released sources' => [static fn (): mixed => $plan()['truncate_released_sources'], ['database', 'database', 'database', 'database', 'database']],
    'restart transition retained option' => [static fn (): mixed => $plan()['restart_source_transitions'][1], 'wal>wal>wal>database'],
    'restart transition plugin rolled back' => [static fn (): mixed => $plan()['restart_source_transitions'][2], 'database>database>database>database'],
    'truncate transition transient rolled back' => [static fn (): mixed => $plan()['truncate_source_transitions'][4], 'database>database>database>database'],
    'current reader preserves sidecar' => [static fn (): mixed => $plan()['current_reader_preserves_sidecar_source'], true],
    'pinned reader blocks restart' => [static fn (): mixed => $plan()['pinned_reader_blocks_restart_reset'], true],
    'pinned reader blocks truncate' => [static fn (): mixed => $plan()['pinned_reader_blocks_truncate_reset'], true],
    'reader release unblocks restart' => [static fn (): mixed => $plan()['reader_release_unblocks_restart'], true],
    'reader release unblocks truncate' => [static fn (): mixed => $plan()['reader_release_unblocks_truncate'], true],
    'restart uses checkpoint database' => [static fn (): mixed => $plan()['restart_released_uses_checkpoint_database'], true],
    'truncate uses checkpoint database' => [static fn (): mixed => $plan()['truncate_released_uses_checkpoint_database'], true],
    'restart truncate database match' => [static fn (): mixed => $plan()['restart_truncate_released_database_match'], true],
    'rolled back page numbers' => [static fn (): mixed => $plan()['rolled_back_page_numbers'], [2, 3, 4, 5]],
    'rolled back frame indexes' => [static fn (): mixed => $plan()['rolled_back_frame_indexes'], [3, 4, 5, 6, 7]],
    'commit frame indexes' => [static fn (): mixed => $plan()['commit_frame_indexes'], [2, 5, 6, 7]],
    'frame source row count' => [static fn (): mixed => count($plan()['frame_source_rows']), 7],
    'frame source offsets' => [static fn (): mixed => array_column($plan()['frame_source_rows'], 'source_offset'), [32, 568, 1104, 1640, 2176, 2712, 3248]],
    'restart option retained label' => [static fn (): mixed => str_contains($plan()['restart']['current_source_rows'][1]['released_next_label'], 'options commit retained'), true],
    'restart plugin base label' => [static fn (): mixed => str_contains($plan()['restart']['current_source_rows'][2]['released_next_label'], 'plugin base'), true],
    'truncate autoload base label' => [static fn (): mixed => str_contains($plan()['truncate']['current_source_rows'][3]['released_next_label'], 'autoload base'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'yield count' => [static fn (): mixed => $plan()['yield_count'], 59],
    'dependency next105' => [static fn (): mixed => in_array('sqlite-wal-restart-truncate-savepoint-reader-current-source-next105', $plan()['dependencies'], true), true],
    'dependency next99' => [static fn (): mixed => in_array('sqlite-wal-savepoint-reader-checkpoint-current-source-next99', $plan()['dependencies'], true), true],
    'dependency read marks' => [static fn (): mixed => in_array('wal-index-read-marks', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal restart truncate savepoint reader current source next105 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal restart truncate savepoint reader current source next105 rejects stale wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $makeWalBytes, $frames, $databaseBytes, $currentShm, $nextReaderShm, $allReleasedShm): void {
    $staleBytes = $makeWalBytes($frames, 106);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointRestartTruncateSavepointReaderCurrentSourceNext($makeStack(), 'plugin-settings-next105', $wal, $staleBytes, $databaseBytes, $currentShm, $nextReaderShm, $allReleasedShm, [1]));
};

$tests['wal restart truncate savepoint reader current source next105 rejects shm salt mismatch'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $makeShm, $nextReaderShm, $allReleasedShm): void {
    $badShm = SQLiteShmIndex::parse($makeShm([0, 2], [false, true], 1, 4, 7, 0x10510503));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointRestartTruncateSavepointReaderCurrentSourceNext($makeStack(), 'plugin-settings-next105', $wal, $walBytes, $databaseBytes, $badShm, $nextReaderShm, $allReleasedShm, [1]));
};

$tests['wal restart truncate savepoint reader current source next105 rejects shm mx frame mismatch'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $makeShm, $nextReaderShm, $allReleasedShm): void {
    $badShm = SQLiteShmIndex::parse($makeShm([0, 2], [false, true], 1, 4, 6));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointRestartTruncateSavepointReaderCurrentSourceNext($makeStack(), 'plugin-settings-next105', $wal, $walBytes, $databaseBytes, $badShm, $nextReaderShm, $allReleasedShm, [1]));
};

$tests['wal restart truncate savepoint reader current source next105 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointRestartTruncateSavepointReaderCurrentSourceNext($makeStack(), 'plugin-settings-next105', $wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $allReleasedShm, []));
};

$tests['wal restart truncate savepoint reader current source next105 rejects missing reader pin'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $makeShm, $nextReaderShm, $allReleasedShm): void {
    $unpinned = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 7, 7));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointRestartTruncateSavepointReaderCurrentSourceNext($makeStack(), 'plugin-settings-next105', $wal, $walBytes, $databaseBytes, $unpinned, $nextReaderShm, $allReleasedShm, [1]));
};

return $tests;
