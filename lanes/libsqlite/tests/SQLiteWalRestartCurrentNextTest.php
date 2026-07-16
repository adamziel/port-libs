<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;

$page = static function (string $label) use ($pageSize): string {
    return str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
};

$databaseBytes = $page('db-page-1-original') . $page('db-page-2-original') . $page('db-page-3-original');

$walBytes = static function (array $frames) use ($page, $pageSize): string {
    $salt1 = 0x44556677;
    $salt2 = 0x11223344;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 19, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $image = $page($frame['label']);
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$wal = SQLiteWal::parse($walBytes([
    ['page' => 1, 'commit' => 0, 'label' => 'wal-frame-1-page-1-before-first-commit'],
    ['page' => 2, 'commit' => 3, 'label' => 'wal-frame-2-page-2-first-commit'],
    ['page' => 1, 'commit' => 0, 'label' => 'wal-frame-3-page-1-latest-draft'],
    ['page' => 3, 'commit' => 3, 'label' => 'wal-frame-4-page-3-latest-commit'],
]), $pageSize, true);

$shm = static function (int $backfill, array $readMarks, array $locks, int $mxFrame = 4) use ($pageSize): SQLiteShmIndex {
    $format = 'V*';
    $header = pack($format, 3007000, $backfill, 23, $pageSize, $mxFrame, 3, 0, 0, 0x44556677, 0x11223344, 0, 0);
    $checkpoint = pack(
        $format,
        $backfill,
        $readMarks[0] ?? 0xffffffff,
        $readMarks[1] ?? 0xffffffff,
        $readMarks[2] ?? 0xffffffff,
        $readMarks[3] ?? 0xffffffff,
        $readMarks[4] ?? 0xffffffff,
    ) . pack(
        'C*',
        $locks[0] ?? 0,
        $locks[1] ?? 0,
        $locks[2] ?? 0,
        $locks[3] ?? 0,
        $locks[4] ?? 0,
        $locks[5] ?? 0,
        $locks[6] ?? 0,
        $locks[7] ?? 0,
    ) . pack($format, $mxFrame, 0);

    return SQLiteShmIndex::parse($header . $header . $checkpoint);
};

$tests = [];

foreach (['restart', 'truncate'] as $mode) {
    foreach ([1, 2, 3, [1, 2, 3]] as $pages) {
        $pages = is_array($pages) ? $pages : [$pages];
        $tests["keeps pinned current reader and advances next reader during {$mode} for pages " . implode(',', $pages)] = static function (TestRunner $t) use ($wal, $databaseBytes, $shm, $mode, $pages): void {
            $visibility = $wal->restartCurrentNextReaderVisibility(
                $databaseBytes,
                $shm(1, [2, null, 4, null, null], [1, 0, 1, 0, 0, 0, 0, 0]),
                $pages,
                $mode,
            );

            $t->same('current-reader-pinned', $visibility['status']);
            $t->same(true, $visibility['checkpoint_busy']);
            $t->same('preserve_wal', $visibility['wal_action']);
            $t->same(2, $visibility['current_reader_end_frame']);
            $t->same(4, $visibility['next_reader_end_frame']);
            $t->same(true, $visibility['current_reader_kept_snapshot']);
            $t->same(false, $visibility['next_reader_uses_database']);
            $t->same(false, $visibility['next_reader_uses_restarted_wal']);
            $expectedImagesMatch = $pages === [2];
            $t->same($expectedImagesMatch, $visibility['images_match']);
            $t->same(true, in_array('wal-restart-current-next-reader-visibility', $visibility['dependencies'], true));
            $t->same(4, $visibility['transition']['next_read_mark_plan']['recommended_reader_frame']);

            if (in_array(1, $pages, true)) {
                $index = array_search(1, $pages, true);
                $t->same('wal', $visibility['current_reader_sources'][$index]);
                $t->same(1, $visibility['current_reader_frame_indexes'][$index]);
                $t->same('wal', $visibility['next_reader_sources'][$index]);
                $t->same(3, $visibility['next_reader_frame_indexes'][$index]);
            }
            if (in_array(2, $pages, true)) {
                $index = array_search(2, $pages, true);
                $t->same('wal', $visibility['current_reader_sources'][$index]);
                $t->same(2, $visibility['current_reader_frame_indexes'][$index]);
                $t->same('wal', $visibility['next_reader_sources'][$index]);
                $t->same(2, $visibility['next_reader_frame_indexes'][$index]);
            }
            if (in_array(3, $pages, true)) {
                $index = array_search(3, $pages, true);
                $t->same('database', $visibility['current_reader_sources'][$index]);
                $t->same(null, $visibility['current_reader_frame_indexes'][$index]);
                $t->same('wal', $visibility['next_reader_sources'][$index]);
                $t->same(4, $visibility['next_reader_frame_indexes'][$index]);
            }
        };
    }
}

for ($slot = 0; $slot < 5; $slot++) {
    foreach (['restart', 'truncate'] as $mode) {
        $tests["resets completed {$mode} checkpoint reader marks from reusable slot {$slot}"] = static function (TestRunner $t) use ($wal, $databaseBytes, $shm, $slot, $mode): void {
            $marks = array_fill(0, 5, null);
            $locks = array_fill(0, 8, 0);
            $marks[$slot] = 4;
            $locks[$slot] = 1;
            $visibility = $wal->restartCurrentNextReaderVisibility($databaseBytes, $shm(4, $marks, $locks), [1, 2, 3], $mode);

            $t->same('restart-ready', $visibility['status']);
            $t->same(false, $visibility['checkpoint_busy']);
            $t->same($mode === 'truncate' ? 'truncate_wal' : 'restart_wal', $visibility['wal_action']);
            $t->same(null, $visibility['current_reader_end_frame']);
            $t->same(0, $visibility['next_reader_end_frame']);
            $t->same(false, $visibility['current_reader_kept_snapshot']);
            $t->same(true, $visibility['next_reader_uses_database']);
            $t->same($mode === 'restart', $visibility['next_reader_uses_restarted_wal']);
            $t->same(true, $visibility['images_match']);
            $t->same(['wal', 'wal', 'wal'], $visibility['current_reader_sources']);
            $t->same(['database', 'database', 'database'], $visibility['next_reader_sources']);
            $t->same([3, 2, 4], $visibility['current_reader_frame_indexes']);
            $t->same([null, null, null], $visibility['next_reader_frame_indexes']);
            $t->same([0, null, null, null, null], $visibility['transition']['next_read_marks']);
            $t->same($mode === 'restart' ? 1 : 0, $visibility['transition']['next_reader_slot']);
            $t->same(0, $visibility['transition']['next_reader_frame']);
        };
    }
}

for ($backfill = 0; $backfill <= 4; $backfill++) {
    foreach ([1, 2, 3, 4] as $readerFrame) {
        $tests["classifies checkpoint restart pin backfill {$backfill} reader {$readerFrame}"] = static function (TestRunner $t) use ($wal, $databaseBytes, $shm, $backfill, $readerFrame): void {
            $visibility = $wal->restartCurrentNextReaderVisibility(
                $databaseBytes,
                $shm($backfill, [$readerFrame, null, null, null, null], [1, 0, 0, 0, 0, 0, 0, 0]),
                [1],
                'restart',
            );

            $expectedPinned = $readerFrame > $backfill && $readerFrame < 4;
            $t->same($expectedPinned ? 'current-reader-pinned' : 'restart-ready', $visibility['status']);
            $t->same($expectedPinned, $visibility['checkpoint_busy']);
            $t->same($expectedPinned ? $readerFrame : null, $visibility['current_reader_end_frame']);
            $t->same($expectedPinned ? 'preserve_wal' : 'restart_wal', $visibility['wal_action']);
            $t->same($expectedPinned, $visibility['current_reader_kept_snapshot']);
            $t->same($expectedPinned ? 4 : 0, $visibility['next_reader_end_frame']);
        };
    }
}

foreach ([[], ['bad'], [0]] as $index => $badPages) {
    $tests["rejects malformed restart current next input {$index}"] = static function (TestRunner $t) use ($wal, $databaseBytes, $shm, $badPages): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $wal->restartCurrentNextReaderVisibility(
                $databaseBytes,
                $shm(1, [2, null, null, null, null], [1, 0, 0, 0, 0, 0, 0, 0]),
                $badPages,
            ),
        );
    };
}

$tests['rejects unsupported restart current next mode'] = static function (TestRunner $t) use ($wal, $databaseBytes, $shm): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => $wal->restartCurrentNextReaderVisibility(
            $databaseBytes,
            $shm(1, [2, null, null, null, null], [1, 0, 0, 0, 0, 0, 0, 0]),
            [1],
            'passive',
        ),
    );
};

return $tests;
