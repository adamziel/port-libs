<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$tests = [];

$pageSize = 512;
$salt1 = 0x23232323;
$salt2 = 0x45454545;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");

$walHeaderBytes = static function (int $checkpoint = 11) use ($pageSize, $salt1, $salt2): string {
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
    $bytes = $appendFrame($bytes, $seed, 1, 0, $page('wp_options schema before appended import'));
    $bytes = $appendFrame($bytes, $seed, 2, 2, $page('wp_options data before appended import'));

    return $bytes;
};

$baseWal = static fn (): SQLiteWal => SQLiteWal::parse($baseWalBytes(), null, true);
$transactions = static fn (): array => [
    [
        'pages' => [
            2 => $page('wp_options active_plugins updated in append'),
            3 => $page('wp_options autoload index updated append'),
        ],
        'database_page_count' => 3,
        'commit' => true,
    ],
    [
        'pages' => [
            4 => $page('wp_options plugin draft left uncommitted'),
        ],
        'commit' => false,
    ],
];
$plan = static fn (): array => SQLiteWalAppendPlan::appendTransactions($baseWal(), $databasePath, $transactions());
$parsedPlanWal = static fn (): SQLiteWal => SQLiteWal::parse($plan()['wal_bytes'], null, true);

$cases = [
    'planned status' => [static fn (): mixed => $plan()['status'], 'planned'],
    'commit reason' => [static fn (): mixed => $plan()['reason'], 'wal_append_contains_commit_frame'],
    'database path preserved' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path derived' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'start offset equals base wal bytes' => [static fn (): mixed => $plan()['start_offset'], strlen($baseWalBytes())],
    'append bytes include three frames' => [static fn (): mixed => $plan()['append_bytes_length'], 3 * (24 + $pageSize)],
    'full wal bytes include base and append' => [static fn (): mixed => $plan()['wal_bytes_length'], strlen($baseWalBytes()) + (3 * (24 + $pageSize))],
    'start frame follows base frames' => [static fn (): mixed => $plan()['start_frame'], 3],
    'end frame includes uncommitted tail' => [static fn (): mixed => $plan()['end_frame'], 5],
    'appended frame count' => [static fn (): mixed => $plan()['appended_frame_count'], 3],
    'committed transaction count' => [static fn (): mixed => $plan()['committed_transaction_count'], 1],
    'uncommitted transaction count' => [static fn (): mixed => $plan()['uncommitted_transaction_count'], 1],
    'last commit frame is committed append frame' => [static fn (): mixed => $plan()['last_commit_frame'], 4],
    'last database page count grows' => [static fn (): mixed => $plan()['last_database_page_count'], 3],
    'first appended frame index' => [static fn (): mixed => $plan()['frames'][0]['frame_index'], 3],
    'first appended page number' => [static fn (): mixed => $plan()['frames'][0]['page_number'], 2],
    'first appended frame has no commit marker' => [static fn (): mixed => $plan()['frames'][0]['commit'], 0],
    'second appended frame is commit marker' => [static fn (): mixed => $plan()['frames'][1]['committed'], true],
    'second appended commit page count' => [static fn (): mixed => $plan()['frames'][1]['commit'], 3],
    'tail appended frame uncommitted' => [static fn (): mixed => $plan()['frames'][2]['committed'], false],
    'tail appended page number' => [static fn (): mixed => $plan()['frames'][2]['page_number'], 4],
    'checksum one is populated' => [static fn (): mixed => $plan()['frames'][2]['checksum1'] !== 0, true],
    'checksum two is populated' => [static fn (): mixed => $plan()['frames'][2]['checksum2'] !== 0, true],
    'write operation first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'write'],
    'write operation offset' => [static fn (): mixed => $plan()['operations'][0]['offset'], strlen($baseWalBytes())],
    'write operation byte count' => [static fn (): mixed => $plan()['operations'][0]['bytes'], 3 * (24 + $pageSize)],
    'wal sync operation second' => [static fn (): mixed => $plan()['operations'][1]['op'], 'sync'],
    'directory sync operation third' => [static fn (): mixed => $plan()['operations'][2]['op'], 'sync_directory'],
    'dependencies include append transaction' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $plan()['dependencies'], true), true],
    'dependencies include checksum chain' => [static fn (): mixed => in_array('sqlite-wal-frame-checksum-chain', $plan()['dependencies'], true), true],
    'parsed appended wal frame count' => [static fn (): mixed => $parsedPlanWal()->frameCount(), 5],
    'parsed appended wal last commit frame' => [static fn (): mixed => $parsedPlanWal()->lastCommitFrame()?->index, 4],
    'parsed appended wal has uncommitted tail' => [static fn (): mixed => $parsedPlanWal()->uncommittedFrameCount(), 1],
    'reader sees committed appended page two' => [static fn (): mixed => str_contains($parsedPlanWal()->readerSnapshotPageImage(str_repeat('.', $pageSize * 2), 2)['image'], 'active_plugins updated'), true],
    'reader sees appended page three after commit' => [static fn (): mixed => str_contains($parsedPlanWal()->readerSnapshotPageImage(str_repeat('.', $pageSize * 2), 3)['image'], 'autoload index updated'), true],
    'reader ignores uncommitted appended page four' => [static fn (): mixed => $parsedPlanWal()->readerSnapshot(str_repeat('.', $pageSize * 2))['database_page_count'], 3],
    'checkpoint image contains committed appended option' => [static fn (): mixed => str_contains($parsedPlanWal()->checkpointDatabaseImage(str_repeat('.', $pageSize * 2)), 'active_plugins updated'), true],
    'checkpoint image excludes uncommitted draft' => [static fn (): mixed => str_contains($parsedPlanWal()->checkpointDatabaseImage(str_repeat('.', $pageSize * 2)), 'draft left uncommitted'), false],
    'checkpoint plan marks old page two superseded' => [static fn (): mixed => $parsedPlanWal()->checkpointPlan(str_repeat('.', $pageSize * 2))['frames'][1]['reason'], 'superseded_by_later_committed_frame'],
    'checkpoint plan marks appended page three applied' => [static fn (): mixed => $parsedPlanWal()->checkpointPlan(str_repeat('.', $pageSize * 2))['frames'][3]['applied'], true],
    'checkpoint plan leaves uncommitted tail unapplied' => [static fn (): mixed => $parsedPlanWal()->checkpointPlan(str_repeat('.', $pageSize * 2))['frames'][4]['reason'], 'after_last_commit'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal append transaction current next23 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal append transaction current next23 can omit sync operations'] = static function (TestRunner $t) use ($baseWal, $databasePath, $transactions): void {
    $plan = SQLiteWalAppendPlan::appendTransactions($baseWal(), $databasePath, $transactions(), false, false);
    $t->same(['write'], array_column($plan['operations'], 'op'));
};

$tests['wal append transaction current next23 rejects empty database path'] = static function (TestRunner $t) use ($baseWal, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::appendTransactions($baseWal(), '', $transactions()));
};

$tests['wal append transaction current next23 rejects empty transactions'] = static function (TestRunner $t) use ($baseWal, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::appendTransactions($baseWal(), $databasePath, []));
};

$tests['wal append transaction current next23 rejects empty page list'] = static function (TestRunner $t) use ($baseWal, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::appendTransactions($baseWal(), $databasePath, [['pages' => []]]));
};

$tests['wal append transaction current next23 rejects zero page number'] = static function (TestRunner $t) use ($baseWal, $databasePath, $page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::appendTransactions($baseWal(), $databasePath, [['pages' => [0 => $page('bad')], 'database_page_count' => 1]]));
};

$tests['wal append transaction current next23 rejects short page image'] = static function (TestRunner $t) use ($baseWal, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::appendTransactions($baseWal(), $databasePath, [['pages' => [1 => 'short'], 'database_page_count' => 1]]));
};

$tests['wal append transaction current next23 rejects committed transaction without page count'] = static function (TestRunner $t) use ($baseWal, $databasePath, $page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::appendTransactions($baseWal(), $databasePath, [['pages' => [1 => $page('missing count')]]]));
};

$tests['wal append transaction current next23 rejects shrinking commit page count'] = static function (TestRunner $t) use ($baseWal, $databasePath, $page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::appendTransactions($baseWal(), $databasePath, [['pages' => [4 => $page('too high')], 'database_page_count' => 3]]));
};

$tests['wal append transaction current next23 applies append through vfs writer'] = static function (TestRunner $t) use ($baseWalBytes, $baseWal, $databasePath, $transactions): void {
    $root = sys_get_temp_dir() . '/port-libsqlite-wal-append-test-' . bin2hex(random_bytes(4));
    $localWal = $root . '/' . $databasePath . '-wal';
    $directory = dirname($localWal);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create WAL append test directory');
    }
    file_put_contents($localWal, $baseWalBytes());

    $applied = (new SQLiteVfsFileWriter($root))->applyWalAppendTransactions($baseWal(), $databasePath, $transactions());
    $walBytes = file_get_contents($localWal);
    $parsed = SQLiteWal::parse($walBytes, null, true);

    $t->same('applied', $applied['status']);
    $t->same(3, $applied['applied']);
    $t->same(3 * (24 + 512), $applied['bytes_written']);
    $t->same(1, $applied['durable_syncs']);
    $t->same(1, $applied['directory_syncs']);
    $t->same(5, $parsed->frameCount());
    $t->same(4, $parsed->lastCommitFrame()?->index);
    $t->same(1, $parsed->uncommittedFrameCount());
    $t->same(true, str_contains($walBytes, 'wp_options plugin draft left uncommitted'));
    $t->same(true, in_array('sqlite-wal-append-transaction', $applied['dependencies'], true));
};

return $tests;
