<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$tests = [];

$pageSize = 512;
$salt1 = 0x26262626;
$salt2 = 0x51515151;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('database page one before writer') . $page('database page two before writer');

$walHeaderBytes = static function (int $checkpoint = 26) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};

$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$baseWalBytes = static function () use ($walHeaderBytes, $appendFrame, $page): string {
    $bytes = $walHeaderBytes();
    $seed = SQLiteWal::checksumPair(substr($bytes, 0, 24), false);
    $bytes = $appendFrame($bytes, $seed, 1, 0, $page('wal page one draft before current'));
    $bytes = $appendFrame($bytes, $seed, 2, 2, $page('wal page two committed current'));

    return $bytes;
};

$baseWal = static fn (): SQLiteWal => SQLiteWal::parse($baseWalBytes(), null, true);
$transactions = static fn (): array => [
    [
        'pages' => [
            2 => $page('writer replaces active_plugins option'),
            3 => $page('writer adds autoload index page'),
        ],
        'database_page_count' => 3,
        'commit' => true,
    ],
    [
        'pages' => [
            1 => $page('writer draft root page not committed'),
            4 => $page('writer draft plugin option page'),
        ],
        'commit' => false,
    ],
];
$boundary = static fn (): array => SQLiteWalAppendPlan::readerWriterSnapshotBoundary(
    $baseWal(),
    $databaseBytes,
    $databasePath,
    $transactions(),
    [1, 2, 3, 4]
);
$nextWal = static fn (): SQLiteWal => SQLiteWal::parse($boundary()['append']['wal_bytes'], null, true);

$cases = [
    'status is planned' => [static fn (): mixed => $boundary()['status'], 'planned'],
    'reason reports pinned current reader' => [static fn (): mixed => $boundary()['reason'], 'next_reader_sees_committed_append_current_reader_pinned'],
    'database path preserved' => [static fn (): mixed => $boundary()['database_path'], $databasePath],
    'wal path derived' => [static fn (): mixed => $boundary()['wal_path'], $databasePath . '-wal'],
    'current reader is pinned to base end frame' => [static fn (): mixed => $boundary()['current_reader_end_frame'], 2],
    'next reader ends at appended commit frame' => [static fn (): mixed => $boundary()['next_reader_end_frame'], 4],
    'appended frame count includes draft tail' => [static fn (): mixed => $boundary()['appended_frame_count'], 4],
    'committed transaction count' => [static fn (): mixed => $boundary()['committed_transaction_count'], 1],
    'uncommitted transaction count' => [static fn (): mixed => $boundary()['uncommitted_transaction_count'], 1],
    'current database page count remains two' => [static fn (): mixed => $boundary()['current_database_page_count'], 2],
    'next database page count grows to three' => [static fn (): mixed => $boundary()['next_database_page_count'], 3],
    'uncommitted tail not visible' => [static fn (): mixed => $boundary()['uncommitted_tail_visible'], false],
    'images do not match after writer commit' => [static fn (): mixed => $boundary()['images_match'], false],
    'current page one source is wal' => [static fn (): mixed => $boundary()['current_reader_sources'][0], 'wal'],
    'current page two source is wal' => [static fn (): mixed => $boundary()['current_reader_sources'][1], 'wal'],
    'current page three reports error' => [static fn (): mixed => $boundary()['current_reader_sources'][2], 'error'],
    'current page four reports error' => [static fn (): mixed => $boundary()['current_reader_sources'][3], 'error'],
    'next page one still uses base wal frame' => [static fn (): mixed => $boundary()['next_reader_frame_indexes'][0], 1],
    'next page two uses writer frame' => [static fn (): mixed => $boundary()['next_reader_frame_indexes'][1], 3],
    'next page three uses writer commit frame' => [static fn (): mixed => $boundary()['next_reader_frame_indexes'][2], 4],
    'next page four remains beyond commit' => [static fn (): mixed => $boundary()['next_reader_sources'][3], 'error'],
    'current page two frame index is old commit' => [static fn (): mixed => $boundary()['current_reader_frame_indexes'][1], 2],
    'current page three has no frame index' => [static fn (): mixed => $boundary()['current_reader_frame_indexes'][2], null],
    'next page four has no frame index' => [static fn (): mixed => $boundary()['next_reader_frame_indexes'][3], null],
    'current error count' => [static fn (): mixed => count($boundary()['current_reader_errors']), 2],
    'next error count' => [static fn (): mixed => count($boundary()['next_reader_errors']), 1],
    'current page three error names committed size' => [static fn (): mixed => str_contains($boundary()['current_reader_errors'][0], 'beyond the committed database size'), true],
    'next page four error names committed size' => [static fn (): mixed => str_contains($boundary()['next_reader_errors'][0], 'beyond the committed database size'), true],
    'current page two image is old option' => [static fn (): mixed => str_contains($boundary()['current_reader'][1]['image'], 'committed current'), true],
    'next page two image is writer option' => [static fn (): mixed => str_contains($boundary()['next_reader'][1]['image'], 'active_plugins'), true],
    'next page three image is writer index' => [static fn (): mixed => str_contains($boundary()['next_reader'][2]['image'], 'autoload index'), true],
    'next page one ignores uncommitted draft replacement' => [static fn (): mixed => str_contains($boundary()['next_reader'][0]['image'], 'draft root'), false],
    'append reason still contains commit' => [static fn (): mixed => $boundary()['append']['reason'], 'wal_append_contains_commit_frame'],
    'append start frame' => [static fn (): mixed => $boundary()['append']['start_frame'], 3],
    'append end frame' => [static fn (): mixed => $boundary()['append']['end_frame'], 6],
    'append last commit frame' => [static fn (): mixed => $boundary()['append']['last_commit_frame'], 4],
    'append last page count' => [static fn (): mixed => $boundary()['append']['last_database_page_count'], 3],
    'append bytes length' => [static fn (): mixed => $boundary()['append']['append_bytes_length'], 4 * (24 + $pageSize)],
    'append wal bytes length' => [static fn (): mixed => $boundary()['append']['wal_bytes_length'], strlen($baseWalBytes()) + (4 * (24 + $pageSize))],
    'append write offset is base wal length' => [static fn (): mixed => $boundary()['append']['operations'][0]['offset'], strlen($baseWalBytes())],
    'append has wal sync operation' => [static fn (): mixed => $boundary()['append']['operations'][1]['op'], 'sync'],
    'append has directory sync operation' => [static fn (): mixed => $boundary()['append']['operations'][2]['op'], 'sync_directory'],
    'dependency includes append transaction' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $boundary()['dependencies'], true), true],
    'dependency includes snapshot boundary' => [static fn (): mixed => in_array('sqlite-wal-reader-writer-snapshot-boundary', $boundary()['dependencies'], true), true],
    'next wal frame count includes uncommitted frames' => [static fn (): mixed => $nextWal()->frameCount(), 6],
    'next wal uncommitted count' => [static fn (): mixed => $nextWal()->uncommittedFrameCount(), 2],
    'next wal last commit frame' => [static fn (): mixed => $nextWal()->lastCommitFrame()?->index, 4],
    'next wal reader snapshot at frame four page count' => [static fn (): mixed => $nextWal()->readerSnapshot($databaseBytes, 4)['database_page_count'], 3],
    'next wal reader snapshot at frame six still commits at four' => [static fn (): mixed => $nextWal()->readerSnapshot($databaseBytes, 6)['commit_frame']?->index, 4],
    'next wal full reader ignores draft page one' => [static fn (): mixed => str_contains($nextWal()->readerSnapshotPageImage($databaseBytes, 1, 6)['image'], 'draft root'), false],
    'next wal full reader rejects draft page four' => [static function () use ($nextWal, $databaseBytes): mixed {
        try {
            $nextWal()->readerSnapshotPageImage($databaseBytes, 4, 6);
        } catch (OutOfBoundsException) {
            return 'rejected';
        }

        return 'unexpected';
    }, 'rejected'],
    'current snapshot map has two pages' => [static fn (): mixed => count($baseWal()->readerSnapshotPageMap($databaseBytes, 2)), 2],
    'next snapshot map has three pages' => [static fn (): mixed => count($nextWal()->readerSnapshotPageMap($databaseBytes, 4)), 3],
    'next snapshot map page two is writer frame' => [static fn (): mixed => $nextWal()->readerSnapshotPageMap($databaseBytes, 4)[1]['frame_index'], 3],
    'next snapshot map page three is commit frame' => [static fn (): mixed => $nextWal()->readerSnapshotPageMap($databaseBytes, 4)[2]['frame_index'], 4],
    'uncommitted-only plan has no next commit' => [static fn (): mixed => SQLiteWalAppendPlan::readerWriterSnapshotBoundary($baseWal(), $databaseBytes, $databasePath, [['pages' => [1 => $page('only draft')], 'commit' => false]], [1])['reason'], 'append_has_no_committed_next_snapshot'],
    'uncommitted-only next frame remains current' => [static fn (): mixed => SQLiteWalAppendPlan::readerWriterSnapshotBoundary($baseWal(), $databaseBytes, $databasePath, [['pages' => [1 => $page('only draft')], 'commit' => false]], [1])['next_reader_end_frame'], 2],
    'syncs can be omitted' => [static fn (): mixed => array_column(SQLiteWalAppendPlan::readerWriterSnapshotBoundary($baseWal(), $databaseBytes, $databasePath, $transactions(), [2], false, false)['append']['operations'], 'op'), ['write']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader writer snapshot current next26 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader writer snapshot current next26 rejects empty page list'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerWriterSnapshotBoundary($baseWal(), $databaseBytes, $databasePath, $transactions(), []));
};

$tests['wal reader writer snapshot current next26 rejects non integer page'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::readerWriterSnapshotBoundary($baseWal(), $databaseBytes, $databasePath, $transactions(), ['2']));
};

$tests['wal reader writer snapshot current next26 applies writer bytes then preserves current snapshot'] = static function (TestRunner $t) use ($baseWalBytes, $baseWal, $databaseBytes, $databasePath, $transactions): void {
    $root = sys_get_temp_dir() . '/port-libsqlite-wal-snapshot26-' . bin2hex(random_bytes(4));
    $localWal = $root . '/' . $databasePath . '-wal';
    $directory = dirname($localWal);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create WAL snapshot test directory');
    }
    file_put_contents($localWal, $baseWalBytes());

    $before = $baseWal()->readerSnapshotPageImage($databaseBytes, 2, 2);
    $applied = (new SQLiteVfsFileWriter($root))->applyWalAppendTransactions($baseWal(), $databasePath, $transactions());
    $afterWal = SQLiteWal::parse((string) file_get_contents($localWal), null, true);
    $afterCurrent = $baseWal()->readerSnapshotPageImage($databaseBytes, 2, 2);
    $afterNext = $afterWal->readerSnapshotPageImage($databaseBytes, 2, 4);

    $t->same('applied', $applied['status']);
    $t->same(3, $afterNext['frame_index']);
    $t->same($before['image'], $afterCurrent['image']);
    $t->same(true, str_contains($afterNext['image'], 'active_plugins'));
};

return $tests;
