<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x89112233;
$salt2 = 0x89445566;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db89 page1 schema baseline')
    . $page('db89 page2 option baseline')
    . $page('db89 page3 autoload baseline')
    . $page('db89 page4 plugin baseline')
    . $page('db89 page5 transient baseline');

$makeWal = static function (array $frames, int $checkpointSequence = 89, int $firstSalt = 0x89112233) use ($pageSize, $salt2): string {
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

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 7) use ($pageSize, $salt1, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 189, $pageSizeField, $mxFrame, 5, 1, 2, $salt1, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$frames = [
    [2, 0, $page('wal89 frame1 option old reader')],
    [3, 3, $page('wal89 frame2 autoload first commit')],
    [2, 0, $page('wal89 frame3 option next reader')],
    [4, 0, $page('wal89 frame4 plugin draft')],
    [5, 0, $page('wal89 frame5 transient draft')],
    [4, 5, $page('wal89 frame6 plugin committed')],
    [2, 5, $page('wal89 frame7 option final')],
];
$walBytes = $makeWal($frames);
$wal = SQLiteWal::parse($walBytes, null, true);

$currentShm = SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4));
$nextReaderShm = SQLiteShmIndex::parse($makeShm([0, 2, 7, null, null], [false, true, true, false, false], 1, 6));
$currentReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, 7, null, null], [false, false, true, false, false], 2, 7));
$allReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 7, 7));

$restart = static fn (): array => $wal->checkpointReaderRestartCurrentSourceNext(
    $databaseBytes,
    $walBytes,
    $currentShm,
    $nextReaderShm,
    $currentReleasedShm,
    $allReleasedShm,
    [1, 2, 3, 4, 5],
    'restart'
);
$truncate = static fn (): array => $wal->checkpointReaderRestartCurrentSourceNext(
    $databaseBytes,
    $walBytes,
    $currentShm,
    $nextReaderShm,
    $currentReleasedShm,
    $allReleasedShm,
    [2, 4, 5],
    'truncate'
);

$cases = [
    'restart status' => [static fn (): mixed => $restart()['status'], 'reader-pin-next-reader-blocks-restart-current-source-next89'],
    'restart verifies current source' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'restart current source kind' => [static fn (): mixed => $restart()['current_source']['kind'], 'current_wal_sidecar'],
    'restart current source length' => [static fn (): mixed => $restart()['current_source']['wal_bytes_length'], strlen($walBytes)],
    'restart current source frame count' => [static fn (): mixed => $restart()['current_source']['frame_count'], 7],
    'restart committed frame count' => [static fn (): mixed => $restart()['current_source']['committed_frame_count'], 7],
    'restart checkpoint sequence' => [static fn (): mixed => $restart()['current_source']['checkpoint_sequence'], 89],
    'restart page size' => [static fn (): mixed => $restart()['current_source']['page_size'], $pageSize],
    'restart salt one' => [static fn (): mixed => $restart()['current_source']['salt1'], $salt1],
    'restart salt two' => [static fn (): mixed => $restart()['current_source']['salt2'], $salt2],
    'restart checksums validated' => [static fn (): mixed => $restart()['current_source']['checksums_validated'], true],
    'restart source action' => [static fn (): mixed => $restart()['restart_source']['kind'], 'restart_wal'],
    'restart source length header only' => [static fn (): mixed => $restart()['restart_source']['wal_bytes_length'], 32],
    'restart source checkpoint increments' => [static fn (): mixed => $restart()['restart_source']['checkpoint_sequence'], 90],
    'restart source salt changed' => [static fn (): mixed => $restart()['restart_source']['salt1'] !== $restart()['current_source']['salt1'], true],
    'restart source database length' => [static fn (): mixed => $restart()['restart_source']['database_bytes_length'], strlen($databaseBytes)],
    'restart current frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart next frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 7],
    'restart final frame' => [static fn (): mixed => $restart()['final_reader_end_frame'], 0],
    'restart current checkpointed count' => [static fn (): mixed => $restart()['current_source_checkpointed_frame_count'], 1],
    'restart next checkpointed count' => [static fn (): mixed => $restart()['next_source_checkpointed_frame_count'], 4],
    'restart final checkpointed count' => [static fn (): mixed => $restart()['final_source_checkpointed_frame_count'], 4],
    'restart current pinned frame' => [static fn (): mixed => $restart()['current_checkpoint_pinned_frame'], 2],
    'restart next pinned frame' => [static fn (): mixed => $restart()['next_checkpoint_pinned_frame'], 2],
    'restart current released pinned frame' => [static fn (): mixed => $restart()['current_released_checkpoint_pinned_frame'], null],
    'restart all released pinned frame' => [static fn (): mixed => $restart()['all_released_checkpoint_pinned_frame'], null],
    'restart current preserves sidecar' => [static fn (): mixed => $restart()['current_reader_preserves_sidecar_source'], true],
    'restart next uses checkpoint source' => [static fn (): mixed => $restart()['next_reader_uses_checkpoint_source'], true],
    'restart final uses restarted source' => [static fn (): mixed => $restart()['final_reader_uses_restarted_source'], true],
    'restart current source names' => [static fn (): mixed => $restart()['current_source_names'], ['database', 'preserved-wal', 'preserved-wal', 'missing', 'missing']],
    'restart next source names' => [static fn (): mixed => $restart()['next_source_names_after_current_release'], ['database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'restart final source names' => [static fn (): mixed => $restart()['final_source_names_after_all_release'], ['reset-database', 'reset-database', 'reset-database', 'reset-database', 'reset-database']],
    'restart current row one database' => [static fn (): mixed => $restart()['current_source_rows'][0]['current_source'], 'database'],
    'restart current row two wal' => [static fn (): mixed => $restart()['current_source_rows'][1]['current_source'], 'preserved-wal'],
    'restart current row three wal' => [static fn (): mixed => $restart()['current_source_rows'][2]['current_source'], 'preserved-wal'],
    'restart next row two checkpointed' => [static fn (): mixed => $restart()['next_source_rows_after_current_release'][1]['checkpoint_database_has_page'], true],
    'restart next row four checkpointed' => [static fn (): mixed => $restart()['next_source_rows_after_current_release'][3]['checkpoint_database_has_page'], true],
    'restart final row two reset' => [static fn (): mixed => $restart()['final_source_rows_after_all_release'][1]['current_source'], 'reset-database'],
    'restart current option old' => [static fn (): mixed => str_contains($restart()['current_reader'][1]['image'], 'option old reader'), true],
    'restart next option final' => [static fn (): mixed => str_contains($restart()['next_source_rows_after_current_release'][1]['image'], 'option final'), true],
    'restart final option final' => [static fn (): mixed => str_contains($restart()['final_source_rows_after_all_release'][1]['image'], 'option final'), true],
    'restart next plugin committed' => [static fn (): mixed => str_contains($restart()['next_source_rows_after_current_release'][3]['image'], 'plugin committed'), true],
    'restart final plugin committed' => [static fn (): mixed => str_contains($restart()['final_source_rows_after_all_release'][3]['image'], 'plugin committed'), true],
    'restart current release is pinned' => [static fn (): mixed => $restart()['after_current_release']['status'], 'current-reader-pinned'],
    'restart all release is ready' => [static fn (): mixed => $restart()['after_all_release']['status'], 'restart-ready'],
    'restart current release action' => [static fn (): mixed => $restart()['after_current_release']['checkpoint']['wal_action'], 'preserve_wal'],
    'restart all release action' => [static fn (): mixed => $restart()['after_all_release']['checkpoint']['wal_action'], 'restart_wal'],
    'restart current next images differ' => [static fn (): mixed => $restart()['current_next_images_match'], false],
    'restart next final images match' => [static fn (): mixed => $restart()['next_final_images_match'], true],
    'restart read mark source current ready' => [static fn (): mixed => $restart()['read_mark_sources']['current']['status'], 'ready'],
    'restart read mark source next ready' => [static fn (): mixed => $restart()['read_mark_sources']['next_reader']['checkpoint_pinned_frame'], 2],
    'restart read mark source all release reusable' => [static fn (): mixed => $restart()['read_mark_sources']['all_released']['reset_blocked'], false],
    'restart dependency next89' => [static fn (): mixed => in_array('wal-reader-checkpoint-restart-current-source-next89', $restart()['dependencies'], true), true],
    'restart dependency next83' => [static fn (): mixed => in_array('wal-reader-pin-checkpoint-restart-current-source-next83', $restart()['dependencies'], true), true],
    'restart dependency read marks' => [static fn (): mixed => in_array('wal-index-read-marks', $restart()['dependencies'], true), true],

    'truncate status' => [static fn (): mixed => $truncate()['status'], 'reader-pin-next-reader-blocks-restart-current-source-next89'],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate source action' => [static fn (): mixed => $truncate()['restart_source']['kind'], 'truncate_wal'],
    'truncate source length empty' => [static fn (): mixed => $truncate()['restart_source']['wal_bytes_length'], 0],
    'truncate source checkpoint absent' => [static fn (): mixed => $truncate()['restart_source']['checkpoint_sequence'], null],
    'truncate final uses restarted source' => [static fn (): mixed => $truncate()['final_reader_uses_restarted_source'], true],
    'truncate current names' => [static fn (): mixed => $truncate()['current_source_names'], ['preserved-wal', 'missing', 'missing']],
    'truncate next names' => [static fn (): mixed => $truncate()['next_source_names_after_current_release'], ['checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'truncate final names' => [static fn (): mixed => $truncate()['final_source_names_after_all_release'], ['reset-database', 'reset-database', 'reset-database']],
    'truncate next checkpoint source' => [static fn (): mixed => $truncate()['next_reader_uses_checkpoint_source'], true],
    'truncate current sidecar source' => [static fn (): mixed => $truncate()['current_reader_preserves_sidecar_source'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader checkpoint restart current source next89 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader checkpoint restart current source next89 rejects stale checkpoint sequence'] = static function (TestRunner $t) use ($wal, $makeWal, $frames, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $staleBytes = $makeWal($frames, 90);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderRestartCurrentSourceNext($databaseBytes, $staleBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2]));
};

$tests['wal reader checkpoint restart current source next89 rejects stale salt'] = static function (TestRunner $t) use ($wal, $makeWal, $frames, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $staleBytes = $makeWal($frames, 89, 0x89112234);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderRestartCurrentSourceNext($databaseBytes, $staleBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2]));
};

$tests['wal reader checkpoint restart current source next89 rejects stale frame count'] = static function (TestRunner $t) use ($wal, $makeWal, $frames, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $staleBytes = $makeWal(array_slice($frames, 0, 6));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderRestartCurrentSourceNext($databaseBytes, $staleBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2]));
};

$tests['wal reader checkpoint restart current source next89 rejects mutated matching header bytes'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $mutated = substr_replace($walBytes, 'X', 32 + 24 + 12, 1);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderRestartCurrentSourceNext($databaseBytes, $mutated, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2]));
};

$tests['wal reader checkpoint restart current source next89 rejects empty page list'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderRestartCurrentSourceNext($databaseBytes, $walBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, []));
};

$tests['wal reader checkpoint restart current source next89 rejects non integer page'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderRestartCurrentSourceNext($databaseBytes, $walBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, ['2']));
};

$tests['wal reader checkpoint restart current source next89 rejects passive mode'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointReaderRestartCurrentSourceNext($databaseBytes, $walBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2], 'passive'));
};

return $tests;
