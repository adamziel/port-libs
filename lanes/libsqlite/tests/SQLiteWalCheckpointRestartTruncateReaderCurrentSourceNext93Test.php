<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x93112233;
$salt2 = 0x93445566;
$page = static fn (string $label): string => str_pad($label, $pageSize, ' ', STR_PAD_RIGHT);
$databaseBytes = $page('db93 page1 schema baseline')
    . $page('db93 page2 option baseline')
    . $page('db93 page3 autoload baseline')
    . $page('db93 page4 plugin baseline')
    . $page('db93 page5 transient baseline')
    . $page('db93 page6 cron baseline');

$makeWal = static function (array $frames, int $checkpointSequence = 93, int $firstSalt = 0x93112233) use ($pageSize, $salt2): string {
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
    $header = pack('V*', 3007000, $backfill, 193, $pageSizeField, $mxFrame, 8, 1, 2, $salt1, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");

    return $header . $header . pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);
};

$frames = [
    [2, 0, $page('wal93 frame1 option old reader')],
    [3, 3, $page('wal93 frame2 autoload first commit')],
    [2, 0, $page('wal93 frame3 option next reader')],
    [4, 0, $page('wal93 frame4 plugin draft')],
    [5, 0, $page('wal93 frame5 transient draft')],
    [4, 5, $page('wal93 frame6 plugin committed')],
    [2, 0, $page('wal93 frame7 option final')],
    [6, 6, $page('wal93 frame8 cron committed')],
];
$walBytes = $makeWal($frames);
$wal = SQLiteWal::parse($walBytes, null, true);

$currentShm = SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 5));
$nextReaderShm = SQLiteShmIndex::parse($makeShm([0, 2, 8, null, null], [false, true, true, false, false], 1, 6));
$currentReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, 8, null, null], [false, false, true, false, false], 3, 8));
$allReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 8, 8));

$restart = static fn (): array => $wal->checkpointRestartTruncateReaderCurrentSourceNext(
    $databaseBytes,
    $walBytes,
    $currentShm,
    $nextReaderShm,
    $currentReleasedShm,
    $allReleasedShm,
    [1, 2, 3, 4, 5, 6],
    'restart'
);
$truncate = static fn (): array => $wal->checkpointRestartTruncateReaderCurrentSourceNext(
    $databaseBytes,
    $walBytes,
    $currentShm,
    $nextReaderShm,
    $currentReleasedShm,
    $allReleasedShm,
    [2, 4, 6],
    'truncate'
);

$cases = [
    'restart status' => [static fn (): mixed => $restart()['status'], 'reader-pin-next-reader-blocks-restart-current-source-next93'],
    'restart verifies current source' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart current source kind' => [static fn (): mixed => $restart()['current_source']['kind'], 'current_wal_sidecar'],
    'restart current source hash' => [static fn (): mixed => $restart()['source_generation']['current_wal_bytes_sha1'], sha1($walBytes)],
    'restart current source length' => [static fn (): mixed => $restart()['source_generation']['current_wal_bytes_length'], strlen($walBytes)],
    'restart frame count' => [static fn (): mixed => $restart()['source_generation']['current_frame_count'], 8],
    'restart checkpoint sequence' => [static fn (): mixed => $restart()['source_generation']['current_checkpoint_sequence'], 93],
    'restart salt pair' => [static fn (): mixed => $restart()['source_generation']['current_salt'], [$salt1, $salt2]],
    'restart current reader frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart next reader frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 8],
    'restart final reader frame' => [static fn (): mixed => $restart()['final_reader_end_frame'], 0],
    'restart after current release preserves wal' => [static fn (): mixed => $restart()['source_generation']['after_current_release_wal_action'], 'preserve_wal'],
    'restart after current release keeps bytes' => [static fn (): mixed => $restart()['source_generation']['after_current_release_wal_bytes_length'], strlen($walBytes)],
    'restart after all release restarts wal' => [static fn (): mixed => $restart()['source_generation']['after_all_release_wal_action'], 'restart_wal'],
    'restart after all release header only' => [static fn (): mixed => $restart()['source_generation']['after_all_release_wal_bytes_length'], 32],
    'restart sequence advances' => [static fn (): mixed => $restart()['source_generation']['after_all_release_checkpoint_sequence'], 94],
    'restart salt advances' => [static fn (): mixed => $restart()['source_generation']['after_all_release_salt'], [($salt1 + 1) & 0xffffffff, $salt2]],
    'restart current source names' => [static fn (): mixed => $restart()['current_source_names_next93'], ['database', 'preserved-wal', 'preserved-wal', 'missing', 'missing', 'missing']],
    'restart next source names' => [static fn (): mixed => $restart()['next_source_names_next93'], ['database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'restart final source names' => [static fn (): mixed => $restart()['final_source_names_next93'], ['reset-database', 'reset-database', 'reset-database', 'reset-database', 'reset-database', 'reset-database']],
    'restart current uses sidecar' => [static fn (): mixed => $restart()['current_uses_verified_sidecar'], true],
    'restart next uses checkpoint database' => [static fn (): mixed => $restart()['next_uses_checkpoint_database'], true],
    'restart next no longer uses sidecar after current release' => [static fn (): mixed => $restart()['next_still_preserves_sidecar_for_reader_pin'], false],
    'restart final reset database only' => [static fn (): mixed => $restart()['final_uses_reset_database_only_next93'], true],
    'restart new generation' => [static fn (): mixed => $restart()['restart_source_is_new_generation'], true],
    'restart not empty truncate generation' => [static fn (): mixed => $restart()['truncate_source_is_empty_generation'], false],
    'restart current option old' => [static fn (): mixed => str_contains($restart()['current_reader'][1]['image'], 'option old reader'), true],
    'restart next option final' => [static fn (): mixed => str_contains($restart()['next_reader'][1]['image'], 'option final'), true],
    'restart final option final' => [static fn (): mixed => str_contains($restart()['final_reader'][1]['image'], 'option final'), true],
    'restart current cron missing' => [static fn (): mixed => $restart()['current_reader'][5]['source'], 'missing'],
    'restart next cron checkpointed' => [static fn (): mixed => str_contains($restart()['next_reader'][5]['image'], 'cron committed'), true],
    'restart final cron reset' => [static fn (): mixed => str_contains($restart()['final_reader'][5]['image'], 'cron committed'), true],
    'restart current to next page two changes' => [static fn (): mixed => $restart()['current_to_next_source_transition'][1]['image_changed'], true],
    'restart next to final page two image stable' => [static fn (): mixed => $restart()['next_to_final_source_transition'][1]['image_changed'], false],
    'restart current to final page six changes' => [static fn (): mixed => $restart()['current_to_final_source_transition'][5]['image_changed'], true],
    'restart page two before source' => [static fn (): mixed => $restart()['current_to_next_source_transition'][1]['before_source'], 'preserved-wal'],
    'restart page two after source' => [static fn (): mixed => $restart()['current_to_next_source_transition'][1]['after_source'], 'checkpoint-database'],
    'restart page six final source' => [static fn (): mixed => $restart()['next_to_final_source_transition'][5]['after_source'], 'reset-database'],
    'restart page four checkpoint flag' => [static fn (): mixed => $restart()['current_to_next_source_transition'][3]['after_checkpoint_database_has_page'], true],
    'restart current next differ' => [static fn (): mixed => $restart()['current_next_images_match'], false],
    'restart next final match' => [static fn (): mixed => $restart()['next_final_images_match'], true],
    'restart dependency next93' => [static fn (): mixed => in_array('wal-checkpoint-restart-truncate-reader-current-source-next93', $restart()['dependencies'], true), true],

    'truncate status' => [static fn (): mixed => $truncate()['status'], 'reader-pin-next-reader-blocks-restart-current-source-next93'],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate after all action' => [static fn (): mixed => $truncate()['source_generation']['after_all_release_wal_action'], 'truncate_wal'],
    'truncate after all bytes empty' => [static fn (): mixed => $truncate()['source_generation']['after_all_release_wal_bytes_length'], 0],
    'truncate restart sequence absent' => [static fn (): mixed => $truncate()['source_generation']['after_all_release_checkpoint_sequence'], null],
    'truncate restart salt absent' => [static fn (): mixed => $truncate()['source_generation']['after_all_release_salt'], null],
    'truncate source is empty generation' => [static fn (): mixed => $truncate()['truncate_source_is_empty_generation'], true],
    'truncate not restart generation' => [static fn (): mixed => $truncate()['restart_source_is_new_generation'], false],
    'truncate current names' => [static fn (): mixed => $truncate()['current_source_names_next93'], ['preserved-wal', 'missing', 'missing']],
    'truncate next names' => [static fn (): mixed => $truncate()['next_source_names_next93'], ['checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'truncate final names' => [static fn (): mixed => $truncate()['final_source_names_next93'], ['reset-database', 'reset-database', 'reset-database']],
    'truncate current option old' => [static fn (): mixed => str_contains($truncate()['current_reader'][0]['image'], 'option old reader'), true],
    'truncate next option final' => [static fn (): mixed => str_contains($truncate()['next_reader'][0]['image'], 'option final'), true],
    'truncate final option final' => [static fn (): mixed => str_contains($truncate()['final_reader'][0]['image'], 'option final'), true],
    'truncate current to next changes' => [static fn (): mixed => $truncate()['current_to_next_source_transition'][0]['image_changed'], true],
    'truncate next to final stable' => [static fn (): mixed => $truncate()['next_to_final_source_transition'][0]['image_changed'], false],
    'truncate final reset only' => [static fn (): mixed => $truncate()['final_uses_reset_database_only_next93'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint restart truncate reader current source next93 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint restart truncate reader current source next93 rejects stale checkpoint sequence'] = static function (TestRunner $t) use ($wal, $makeWal, $frames, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $staleBytes = $makeWal($frames, 94);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderCurrentSourceNext($databaseBytes, $staleBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2]));
};

$tests['wal checkpoint restart truncate reader current source next93 rejects stale salt'] = static function (TestRunner $t) use ($wal, $makeWal, $frames, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $staleBytes = $makeWal($frames, 93, 0x93112234);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderCurrentSourceNext($databaseBytes, $staleBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2]));
};

$tests['wal checkpoint restart truncate reader current source next93 rejects stale frame count'] = static function (TestRunner $t) use ($wal, $makeWal, $frames, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $staleBytes = $makeWal(array_slice($frames, 0, 7));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderCurrentSourceNext($databaseBytes, $staleBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2]));
};

$tests['wal checkpoint restart truncate reader current source next93 rejects mutated matching header bytes'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $mutated = substr_replace($walBytes, 'Z', 32 + 24 + 8, 1);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderCurrentSourceNext($databaseBytes, $mutated, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2]));
};

$tests['wal checkpoint restart truncate reader current source next93 rejects passive mode'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointRestartTruncateReaderCurrentSourceNext($databaseBytes, $walBytes, $currentShm, $nextReaderShm, $currentReleasedShm, $allReleasedShm, [2], 'passive'));
};

return $tests;
