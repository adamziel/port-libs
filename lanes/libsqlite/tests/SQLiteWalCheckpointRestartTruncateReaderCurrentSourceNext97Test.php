<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x97112233;
$salt2 = 0x97445566;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db97 page1 schema baseline')
    . $page('db97 page2 option baseline')
    . $page('db97 page3 autoload baseline')
    . $page('db97 page4 plugin baseline')
    . $page('db97 page5 transient baseline');

$makeWal = static function (array $frames, int $checkpointSequence = 97, int $firstSalt = 0x97112233) use ($pageSize, $salt2): string {
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

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 8) use ($pageSize, $salt1, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 197, $pageSizeField, $mxFrame, 5, 1, 2, $salt1, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$frames = [
    [2, 0, $page('wal97 frame1 option old reader')],
    [3, 3, $page('wal97 frame2 autoload first commit')],
    [2, 0, $page('wal97 frame3 option next reader')],
    [4, 0, $page('wal97 frame4 plugin draft')],
    [5, 0, $page('wal97 frame5 transient draft')],
    [4, 5, $page('wal97 frame6 plugin committed')],
    [2, 0, $page('wal97 frame7 option admin final')],
    [3, 5, $page('wal97 frame8 autoload final')],
];
$walBytes = $makeWal($frames);
$wal = SQLiteWal::parse($walBytes, null, true);

$currentShm = SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4));
$nextReaderShm = SQLiteShmIndex::parse($makeShm([0, null, 8, null, null], [false, false, true, false, false], 2, 8));
$allReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 8, 8));

$plan = static fn (): array => $wal->checkpointRestartTruncateReaderPreserveCurrentSourceNext(
    $databaseBytes,
    $walBytes,
    $currentShm,
    $nextReaderShm,
    $allReleasedShm,
    [1, 2, 3, 4, 5]
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'reader-current-source-next97'],
    'source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'source kind' => [static fn (): mixed => $plan()['current_source']['kind'], 'current_wal_sidecar'],
    'source bytes length' => [static fn (): mixed => $plan()['current_source']['wal_bytes_length'], strlen($walBytes)],
    'source frame count' => [static fn (): mixed => $plan()['current_source']['frame_count'], 8],
    'source committed frame count' => [static fn (): mixed => $plan()['current_source']['committed_frame_count'], 8],
    'source checkpoint sequence' => [static fn (): mixed => $plan()['current_source']['checkpoint_sequence'], 97],
    'source page size' => [static fn (): mixed => $plan()['current_source']['page_size'], $pageSize],
    'source salt one' => [static fn (): mixed => $plan()['current_source']['salt1'], $salt1],
    'source salt two' => [static fn (): mixed => $plan()['current_source']['salt2'], $salt2],
    'source checksums validated' => [static fn (): mixed => $plan()['current_source']['checksums_validated'], true],
    'current reader frame' => [static fn (): mixed => $plan()['current_reader_end_frame'], 2],
    'next reader frame' => [static fn (): mixed => $plan()['next_reader_end_frame'], 8],
    'final reader frame' => [static fn (): mixed => $plan()['final_reader_end_frame'], 0],
    'restart after current preserves wal' => [static fn (): mixed => $plan()['restart_after_current_wal_action'], 'preserve_wal'],
    'truncate after current preserves wal' => [static fn (): mixed => $plan()['truncate_after_current_wal_action'], 'preserve_wal'],
    'restart after all action' => [static fn (): mixed => $plan()['restart_after_all_wal_action'], 'restart_wal'],
    'truncate after all action' => [static fn (): mixed => $plan()['truncate_after_all_wal_action'], 'truncate_wal'],
    'restart header length' => [static fn (): mixed => $plan()['restart_after_all_wal_bytes_length'], 32],
    'truncate removes wal' => [static fn (): mixed => $plan()['truncate_after_all_wal_bytes_length'], 0],
    'restart sequence increments' => [static fn (): mixed => $plan()['restart_after_all_checkpoint_sequence'], 98],
    'truncate sequence absent' => [static fn (): mixed => $plan()['truncate_after_all_checkpoint_sequence'], null],
    'current sources' => [static fn (): mixed => $plan()['current_sources'], ['database', 'preserved-wal', 'preserved-wal', 'missing', 'missing']],
    'next sources after current release' => [static fn (): mixed => $plan()['next_sources_after_current_release'], ['database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'restart final sources' => [static fn (): mixed => $plan()['restart_final_sources'], ['reset-database', 'reset-database', 'reset-database', 'reset-database', 'reset-database']],
    'truncate final sources' => [static fn (): mixed => $plan()['truncate_final_sources'], ['reset-database', 'reset-database', 'reset-database', 'reset-database', 'reset-database']],
    'current preserves sidecar' => [static fn (): mixed => $plan()['current_reader_preserves_sidecar_source'], true],
    'next blocks restart' => [static fn (): mixed => $plan()['next_reader_blocks_restart_reset'], true],
    'next blocks truncate' => [static fn (): mixed => $plan()['next_reader_blocks_truncate_reset'], true],
    'restart final uses header' => [static fn (): mixed => $plan()['restart_final_uses_restarted_wal_header'], true],
    'truncate final removes sidecar' => [static fn (): mixed => $plan()['truncate_final_removes_wal_sidecar'], true],
    'restart and truncate database equal' => [static fn (): mixed => $plan()['restart_and_truncate_checkpoint_same_database'], true],
    'restart next final images match' => [static fn (): mixed => $plan()['restart_next_final_images_match'], true],
    'truncate next final images match' => [static fn (): mixed => $plan()['truncate_next_final_images_match'], true],
    'restart nested status' => [static fn (): mixed => $plan()['restart']['status'], 'reader-pin-next-reader-blocks-restart-current-source-next89'],
    'truncate nested mode' => [static fn (): mixed => $plan()['truncate']['mode'], 'truncate'],
    'restart current frame indexes' => [static fn (): mixed => $plan()['restart']['current_reader_frame_indexes'], [null, 1, 2, null, null]],
    'restart next frame indexes' => [static fn (): mixed => $plan()['restart']['next_reader_frame_indexes'], [null, 7, 8, 6, 5]],
    'truncate next frame indexes' => [static fn (): mixed => $plan()['truncate']['next_reader_frame_indexes'], [null, 7, 8, 6, 5]],
    'restart final frame indexes' => [static fn (): mixed => $plan()['restart']['final_reader_frame_indexes'], [null, null, null, null, null]],
    'truncate final frame indexes' => [static fn (): mixed => $plan()['truncate']['final_reader_frame_indexes'], [null, null, null, null, null]],
    'current option old reader' => [static fn (): mixed => str_contains($plan()['restart']['current_reader'][1]['image'], 'option old reader'), true],
    'next option admin final' => [static fn (): mixed => str_contains($plan()['restart']['next_reader'][1]['image'], 'option admin final'), true],
    'restart final option admin final' => [static fn (): mixed => str_contains($plan()['restart']['final_reader'][1]['image'], 'option admin final'), true],
    'truncate final option admin final' => [static fn (): mixed => str_contains($plan()['truncate']['final_reader'][1]['image'], 'option admin final'), true],
    'next autoload final' => [static fn (): mixed => str_contains($plan()['restart']['next_reader'][2]['image'], 'autoload final'), true],
    'final plugin committed' => [static fn (): mixed => str_contains($plan()['truncate']['final_reader'][3]['image'], 'plugin committed'), true],
    'restart after current busy' => [static fn (): mixed => $plan()['restart']['after_current_release']['checkpoint']['busy'], true],
    'truncate after current busy' => [static fn (): mixed => $plan()['truncate']['after_current_release']['checkpoint']['busy'], true],
    'restart after all not busy' => [static fn (): mixed => $plan()['restart']['after_all_release']['checkpoint']['busy'], false],
    'truncate after all not busy' => [static fn (): mixed => $plan()['truncate']['after_all_release']['checkpoint']['busy'], false],
    'restart checkpointed frames' => [static fn (): mixed => $plan()['restart']['final_source_checkpointed_frame_count'], 4],
    'truncate checkpointed frames' => [static fn (): mixed => $plan()['truncate']['final_source_checkpointed_frame_count'], 4],
    'dependency next97' => [static fn (): mixed => in_array('wal-checkpoint-restart-truncate-reader-current-source-next97', $plan()['dependencies'], true), true],
    'dependency next89' => [static fn (): mixed => in_array('wal-reader-checkpoint-restart-current-source-next89', $plan()['dependencies'], true), true],
    'dependency read marks' => [static fn (): mixed => in_array('wal-index-read-marks', $plan()['dependencies'], true), true],
    'dependency next83' => [static fn (): mixed => in_array('wal-reader-pin-checkpoint-restart-current-source-next83', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint restart truncate reader current source next97 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint restart truncate reader current source next97 rejects stale checkpoint sequence'] = static function (TestRunner $t) use ($wal, $makeWal, $frames, $databaseBytes, $currentShm, $nextReaderShm, $allReleasedShm): void {
    $staleBytes = $makeWal($frames, 98);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderPreserveCurrentSourceNext($databaseBytes, $staleBytes, $currentShm, $nextReaderShm, $allReleasedShm, [2]));
};

$tests['wal checkpoint restart truncate reader current source next97 rejects stale salt'] = static function (TestRunner $t) use ($wal, $makeWal, $frames, $databaseBytes, $currentShm, $nextReaderShm, $allReleasedShm): void {
    $staleBytes = $makeWal($frames, 97, 0x97112234);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderPreserveCurrentSourceNext($databaseBytes, $staleBytes, $currentShm, $nextReaderShm, $allReleasedShm, [2]));
};

$tests['wal checkpoint restart truncate reader current source next97 rejects mutated frame bytes'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $allReleasedShm): void {
    $mutated = substr_replace($walBytes, 'X', 32 + 24 + 15, 1);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderPreserveCurrentSourceNext($databaseBytes, $mutated, $currentShm, $nextReaderShm, $allReleasedShm, [2]));
};

$tests['wal checkpoint restart truncate reader current source next97 rejects empty page list'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderPreserveCurrentSourceNext($databaseBytes, $walBytes, $currentShm, $nextReaderShm, $allReleasedShm, []));
};

$tests['wal checkpoint restart truncate reader current source next97 rejects non integer page'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderPreserveCurrentSourceNext($databaseBytes, $walBytes, $currentShm, $nextReaderShm, $allReleasedShm, ['2']));
};

return $tests;
