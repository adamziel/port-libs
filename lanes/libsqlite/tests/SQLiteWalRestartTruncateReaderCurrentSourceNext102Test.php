<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x10211223;
$salt2 = 0x10244556;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db102 page1 schema baseline')
    . $page('db102 page2 option baseline')
    . $page('db102 page3 autoload baseline')
    . $page('db102 page4 plugin baseline')
    . $page('db102 page5 transient baseline');

$makeWal = static function (array $frames, int $checkpointSequence = 102, int $firstSalt = 0x10211223) use ($pageSize, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $firstSalt, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $firstSalt, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 8, int $firstSalt = 0x10211223) use ($pageSize, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 202, $pageSizeField, $mxFrame, 5, 1, 2, $firstSalt, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$frames = [
    [2, 0, $page('wal102 frame1 option reader old')],
    [3, 3, $page('wal102 frame2 autoload first commit')],
    [2, 0, $page('wal102 frame3 option staged')],
    [4, 0, $page('wal102 frame4 plugin draft')],
    [5, 0, $page('wal102 frame5 transient draft')],
    [4, 5, $page('wal102 frame6 plugin committed')],
    [2, 0, $page('wal102 frame7 option final')],
    [5, 5, $page('wal102 frame8 transient committed')],
];
$walBytes = $makeWal($frames);
$wal = SQLiteWal::parse($walBytes, null, true);

$currentShm = SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4));
$nextReaderShm = SQLiteShmIndex::parse($makeShm([0, 2, 8, null, null], [false, true, true, false, false], 1, 7));
$allReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 8, 8));

$plan = static fn (): array => $wal->checkpointRestartTruncateReaderRecoveryCurrentSourceNext(
    $databaseBytes,
    $walBytes,
    $currentShm,
    $nextReaderShm,
    $allReleasedShm,
    [1, 2, 3, 4, 5]
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'reader-current-source-next102'],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'shm source verified' => [static fn (): mixed => $plan()['shm_source_verified'], true],
    'current source kind' => [static fn (): mixed => $plan()['current_source']['kind'], 'current_wal_sidecar'],
    'current source frame count' => [static fn (): mixed => $plan()['current_source']['frame_count'], 8],
    'current source committed frame count' => [static fn (): mixed => $plan()['current_source']['committed_frame_count'], 8],
    'current source checkpoint sequence' => [static fn (): mixed => $plan()['current_source']['checkpoint_sequence'], 102],
    'current source salt1' => [static fn (): mixed => $plan()['current_source']['salt1'], $salt1],
    'current source salt2' => [static fn (): mixed => $plan()['current_source']['salt2'], $salt2],
    'current source checksums' => [static fn (): mixed => $plan()['current_source']['checksums_validated'], true],
    'current shm mx frame' => [static fn (): mixed => $plan()['current_shm_source']['mx_frame'], 8],
    'current shm backfilled frame count' => [static fn (): mixed => $plan()['current_shm_source']['backfilled_frame_count'], 1],
    'current shm attempted frame count' => [static fn (): mixed => $plan()['current_shm_source']['backfill_attempted_frame_count'], 4],
    'current shm salt1' => [static fn (): mixed => $plan()['current_shm_source']['salt1'], $salt1],
    'current shm salt2' => [static fn (): mixed => $plan()['current_shm_source']['salt2'], $salt2],
    'current shm headers match' => [static fn (): mixed => $plan()['current_shm_source']['headers_match'], true],
    'current shm pinned frame' => [static fn (): mixed => $plan()['current_shm_source']['checkpoint_pinned_frame'], 2],
    'current shm reset blocked' => [static fn (): mixed => $plan()['current_shm_source']['reset_blocked'], true],
    'next shm pinned frame' => [static fn (): mixed => $plan()['next_shm_source']['checkpoint_pinned_frame'], 2],
    'next shm reset blocked' => [static fn (): mixed => $plan()['next_shm_source']['reset_blocked'], true],
    'all released shm pinned frame' => [static fn (): mixed => $plan()['all_released_shm_source']['checkpoint_pinned_frame'], null],
    'all released shm reset unblocked' => [static fn (): mixed => $plan()['all_released_shm_source']['reset_blocked'], false],
    'current reader end frame' => [static fn (): mixed => $plan()['current_reader_end_frame'], 2],
    'next reader end frame' => [static fn (): mixed => $plan()['next_reader_end_frame'], 8],
    'final reader end frame' => [static fn (): mixed => $plan()['final_reader_end_frame'], 0],
    'restart after current preserves wal' => [static fn (): mixed => $plan()['restart_after_current_wal_action'], 'preserve_wal'],
    'truncate after current preserves wal' => [static fn (): mixed => $plan()['truncate_after_current_wal_action'], 'preserve_wal'],
    'restart after all restarts wal' => [static fn (): mixed => $plan()['restart_after_all_wal_action'], 'restart_wal'],
    'truncate after all truncates wal' => [static fn (): mixed => $plan()['truncate_after_all_wal_action'], 'truncate_wal'],
    'restart header only' => [static fn (): mixed => $plan()['restart_after_all_wal_bytes_length'], 32],
    'truncate empty wal' => [static fn (): mixed => $plan()['truncate_after_all_wal_bytes_length'], 0],
    'restart sequence increments' => [static fn (): mixed => $plan()['restart_after_all_checkpoint_sequence'], 103],
    'truncate sequence absent' => [static fn (): mixed => $plan()['truncate_after_all_checkpoint_sequence'], null],
    'current sources' => [static fn (): mixed => $plan()['current_source_names_next102'], ['database', 'preserved-wal', 'preserved-wal', 'missing', 'missing']],
    'next sources' => [static fn (): mixed => $plan()['next_source_names_next102'], ['database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'restart final sources' => [static fn (): mixed => $plan()['restart_final_source_names_next102'], ['reset-database', 'reset-database', 'reset-database', 'reset-database', 'reset-database']],
    'truncate final sources' => [static fn (): mixed => $plan()['truncate_final_source_names_next102'], ['reset-database', 'reset-database', 'reset-database', 'reset-database', 'reset-database']],
    'current preserves sidecar' => [static fn (): mixed => $plan()['current_reader_preserves_sidecar_source'], true],
    'next blocks restart' => [static fn (): mixed => $plan()['next_reader_blocks_restart_reset'], true],
    'next blocks truncate' => [static fn (): mixed => $plan()['next_reader_blocks_truncate_reset'], true],
    'restart final uses header' => [static fn (): mixed => $plan()['restart_final_uses_restarted_wal_header'], true],
    'truncate final removes sidecar' => [static fn (): mixed => $plan()['truncate_final_removes_wal_sidecar'], true],
    'restart truncate database match' => [static fn (): mixed => $plan()['restart_truncate_final_database_match_next102'], true],
    'transition current to next count' => [static fn (): mixed => count($plan()['current_to_next_source_transition_next102']), 5],
    'transition current to next label' => [static fn (): mixed => $plan()['current_to_next_source_transition_next102'][1]['phase'], 'current_to_next102'],
    'transition current page two from wal' => [static fn (): mixed => $plan()['current_to_next_source_transition_next102'][1]['before_source'], 'preserved-wal'],
    'transition current page two to checkpoint' => [static fn (): mixed => $plan()['current_to_next_source_transition_next102'][1]['after_source'], 'checkpoint-database'],
    'transition next to restart final label' => [static fn (): mixed => $plan()['next_to_restart_final_source_transition_next102'][3]['phase'], 'next_to_restart_final102'],
    'transition next to restart final source' => [static fn (): mixed => $plan()['next_to_restart_final_source_transition_next102'][3]['after_source'], 'reset-database'],
    'transition next to truncate final label' => [static fn (): mixed => $plan()['next_to_truncate_final_source_transition_next102'][4]['phase'], 'next_to_truncate_final102'],
    'transition next to truncate final source' => [static fn (): mixed => $plan()['next_to_truncate_final_source_transition_next102'][4]['after_source'], 'reset-database'],
    'restart final generation action' => [static fn (): mixed => $plan()['restart_final_wal_generation']['action'], 'restart_wal'],
    'restart final generation length' => [static fn (): mixed => $plan()['restart_final_wal_generation']['wal_bytes_length'], 32],
    'truncate final generation action' => [static fn (): mixed => $plan()['truncate_final_wal_generation']['action'], 'truncate_wal'],
    'truncate final generation length' => [static fn (): mixed => $plan()['truncate_final_wal_generation']['wal_bytes_length'], 0],
    'restart page two current old' => [static fn (): mixed => str_contains($plan()['restart']['current_reader'][1]['image'], 'option reader old'), true],
    'restart page two final' => [static fn (): mixed => str_contains($plan()['restart']['final_source_rows_after_all_release'][1]['image'], 'option final'), true],
    'truncate page five final' => [static fn (): mixed => str_contains($plan()['truncate']['final_source_rows_after_all_release'][4]['image'], 'transient committed'), true],
    'dependency next102' => [static fn (): mixed => in_array('wal-restart-truncate-reader-current-source-next102', $plan()['dependencies'], true), true],
    'dependency next97' => [static fn (): mixed => in_array('wal-checkpoint-restart-truncate-reader-current-source-next97', $plan()['dependencies'], true), true],
    'dependency read marks' => [static fn (): mixed => in_array('wal-index-read-marks', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal restart truncate reader current source next102 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal restart truncate reader current source next102 rejects stale wal bytes'] = static function (TestRunner $t) use ($wal, $makeWal, $frames, $databaseBytes, $currentShm, $nextReaderShm, $allReleasedShm): void {
    $staleBytes = $makeWal($frames, 103);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderRecoveryCurrentSourceNext($databaseBytes, $staleBytes, $currentShm, $nextReaderShm, $allReleasedShm, [2]));
};

$tests['wal restart truncate reader current source next102 rejects shm salt mismatch'] = static function (TestRunner $t) use ($wal, $walBytes, $makeShm, $databaseBytes, $nextReaderShm, $allReleasedShm): void {
    $badShm = SQLiteShmIndex::parse($makeShm([0, 2], [false, true], 1, 4, 8, 0x10211224));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderRecoveryCurrentSourceNext($databaseBytes, $walBytes, $badShm, $nextReaderShm, $allReleasedShm, [2]));
};

$tests['wal restart truncate reader current source next102 rejects shm mx frame mismatch'] = static function (TestRunner $t) use ($wal, $walBytes, $makeShm, $databaseBytes, $nextReaderShm, $allReleasedShm): void {
    $badShm = SQLiteShmIndex::parse($makeShm([0, 2], [false, true], 1, 4, 7));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderRecoveryCurrentSourceNext($databaseBytes, $walBytes, $badShm, $nextReaderShm, $allReleasedShm, [2]));
};

$tests['wal restart truncate reader current source next102 rejects empty page list'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderRecoveryCurrentSourceNext($databaseBytes, $walBytes, $currentShm, $nextReaderShm, $allReleasedShm, []));
};

return $tests;
