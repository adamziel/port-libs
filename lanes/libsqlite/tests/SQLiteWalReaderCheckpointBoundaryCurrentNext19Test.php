<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db-page-1-before') . $page('db-page-2-before') . $page('db-page-3-before');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x31415926;
    $salt2 = 0x27182818;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 219, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWal([
    [1, 0, 'schema-current-prefix'],
    [2, 3, 'option-current-commit'],
    [3, 0, 'plugin-draft-uncommitted'],
    [2, 3, 'plugin-rolled-back-commit'],
    [1, 0, 'nested-draft-after'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('application-import');
    $stack->recordPageImageWrite(1, $page('db-page-1-before'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordPageImageWrite(2, $page('db-page-2-before'));
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-options');
    $stack->recordPageImageWrite(3, $page('db-page-3-before'));
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 2, true);
    $stack->savepoint('nested-draft');
    $stack->recordPageImageWrite(1, $page('db-page-1-before'));
    $stack->recordWalFrameWrite(5, 1);

    return $stack;
};

$boundary = static fn (string $mode = 'truncate', ?int $currentReader = null, ?int $nextReader = null, array $pages = [1, 2, 3]): array => SQLiteWalSavepointCheckpointPlan::readerBoundaryAfterRollbackTo(
    $makeStack(),
    'plugin-options',
    $wal,
    $walBytes,
    $databaseBytes,
    $pages,
    $mode,
    $currentReader,
    $nextReader
);

$cases = [
    'truncate boundary is ready' => static fn (): mixed => $boundary()['status'],
    'truncate boundary keeps savepoint name' => static fn (): mixed => $boundary()['savepoint'],
    'truncate boundary normalizes mode' => static fn (): mixed => SQLiteWalSavepointCheckpointPlan::readerBoundaryAfterRollbackTo($makeStack(), 'plugin-options', $wal, $walBytes, $databaseBytes, [2], 'TRUNCATE')['mode'],
    'truncate boundary retains pre savepoint frames' => static fn (): mixed => $boundary()['retained_frame_count'],
    'truncate boundary discards current and nested frames' => static fn (): mixed => $boundary()['discarded_frame_count'],
    'truncate boundary is not busy without active reader pin' => static fn (): mixed => $boundary()['checkpoint_busy'],
    'truncate boundary reports reset reason' => static fn (): mixed => $boundary()['checkpoint_reason'],
    'truncate boundary truncates wal for next reader' => static fn (): mixed => $boundary()['wal_action'],
    'current reader defaults to retained mx frame' => static fn (): mixed => $boundary()['current_reader_end_frame'],
    'next reader defaults to database snapshot after truncate' => static fn (): mixed => $boundary()['next_reader_end_frame'],
    'current reader page one comes from wal' => static fn (): mixed => $boundary()['current_reader_sources'][0],
    'current reader page two comes from wal' => static fn (): mixed => $boundary()['current_reader_sources'][1],
    'current reader page three comes from database' => static fn (): mixed => $boundary()['current_reader_sources'][2],
    'next reader page one comes from checkpoint database' => static fn (): mixed => $boundary()['next_reader_sources'][0],
    'next reader page two comes from checkpoint database' => static fn (): mixed => $boundary()['next_reader_sources'][1],
    'next reader page three comes from checkpoint database' => static fn (): mixed => $boundary()['next_reader_sources'][2],
    'current reader frame indexes include schema frame' => static fn (): mixed => $boundary()['current_reader_frame_indexes'][0],
    'current reader frame indexes include option frame' => static fn (): mixed => $boundary()['current_reader_frame_indexes'][1],
    'current reader frame indexes leave page three in database' => static fn (): mixed => $boundary()['current_reader_frame_indexes'][2],
    'next reader frame index page one is null after truncate' => static fn (): mixed => $boundary()['next_reader_frame_indexes'][0],
    'next reader frame index page two is null after truncate' => static fn (): mixed => $boundary()['next_reader_frame_indexes'][1],
    'next reader uses checkpoint database flag' => static fn (): mixed => $boundary()['next_reader_uses_checkpoint_database'],
    'current reader kept wal snapshot flag' => static fn (): mixed => $boundary()['current_reader_kept_wal_snapshot'],
    'current and next images match across checkpoint boundary' => static fn (): mixed => $boundary()['images_match'],
    'current page one image is retained prefix frame' => static fn (): mixed => str_starts_with($boundary()['current_reader_images'][0], 'schema-current-prefix'),
    'next page one image is checkpointed prefix frame' => static fn (): mixed => str_starts_with($boundary()['next_reader_images'][0], 'schema-current-prefix'),
    'current page two image is retained commit frame' => static fn (): mixed => str_starts_with($boundary()['current_reader_images'][1], 'option-current-commit'),
    'next page two image is checkpointed commit frame' => static fn (): mixed => str_starts_with($boundary()['next_reader_images'][1], 'option-current-commit'),
    'current page three image is base database' => static fn (): mixed => str_starts_with($boundary()['current_reader_images'][2], 'db-page-3-before'),
    'next page three image remains base database' => static fn (): mixed => str_starts_with($boundary()['next_reader_images'][2], 'db-page-3-before'),
    'rolled back plugin commit is absent from current reader' => static fn (): mixed => str_contains(implode('', $boundary()['current_reader_images']), 'plugin-rolled-back-commit'),
    'rolled back plugin commit is absent from next reader' => static fn (): mixed => str_contains(implode('', $boundary()['next_reader_images']), 'plugin-rolled-back-commit'),
    'dependency includes current prefix' => static fn (): mixed => in_array('sqlite-savepoint-wal-current-prefix', $boundary()['dependencies'], true),
    'dependency includes current next boundary' => static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-boundary-current-next', $boundary()['dependencies'], true),
    'restart boundary restarts wal for next reader' => static fn (): mixed => $boundary('restart')['wal_action'],
    'restart next reader sees database page one' => static fn (): mixed => $boundary('restart')['next_reader_sources'][0],
    'restart next reader snapshot end is empty wal header' => static fn (): mixed => $boundary('restart')['next_reader_end_frame'],
    'restart images match across boundary' => static fn (): mixed => $boundary('restart')['images_match'],
    'full boundary preserves wal bytes' => static fn (): mixed => $boundary('full')['wal_action'],
    'full next reader page one still uses wal' => static fn (): mixed => $boundary('full')['next_reader_sources'][0],
    'full next reader page two still uses wal' => static fn (): mixed => $boundary('full')['next_reader_sources'][1],
    'full images match across boundary' => static fn (): mixed => $boundary('full')['images_match'],
    'passive boundary preserves wal bytes' => static fn (): mixed => $boundary('passive')['wal_action'],
    'explicit current reader before commit sees database page one' => static fn (): mixed => $boundary('truncate', 0)['current_reader_sources'][0],
    'explicit current reader before commit sees database page two' => static fn (): mixed => $boundary('truncate', 0)['current_reader_sources'][1],
    'explicit current reader before commit does not match next checkpoint image' => static fn (): mixed => $boundary('truncate', 0)['images_match'],
    'explicit current reader at first uncommitted frame sees database page two' => static fn (): mixed => $boundary('truncate', 1)['current_reader_sources'][1],
    'explicit current reader at commit sees option frame' => static fn (): mixed => $boundary('truncate', 2)['current_reader_frame_indexes'][1],
    'busy restart with current reader preserves wal' => static fn (): mixed => $boundary('restart', 1)['wal_action'],
    'busy restart reports busy' => static fn (): mixed => $boundary('restart', 1)['checkpoint_busy'],
    'busy restart reports checkpoint completion blocker' => static fn (): mixed => $boundary('restart', 1)['checkpoint_reason'],
    'busy restart next reader still uses retained wal page two' => static fn (): mixed => $boundary('restart', 1)['next_reader_sources'][1],
    'single page boundary keeps requested page count' => static fn (): mixed => count($boundary('truncate', null, null, [2])['current_reader']),
    'single page boundary returns requested page number' => static fn (): mixed => $boundary('truncate', null, null, [2])['next_reader'][0]['page_number'],
    'empty page list is rejected' => static function () use ($makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            SQLiteWalSavepointCheckpointPlan::readerBoundaryAfterRollbackTo($makeStack(), 'plugin-options', $wal, $walBytes, $databaseBytes, []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'non integer page list is rejected' => static function () use ($makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            SQLiteWalSavepointCheckpointPlan::readerBoundaryAfterRollbackTo($makeStack(), 'plugin-options', $wal, $walBytes, $databaseBytes, [2, '3']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'zero page is rejected' => static function () use ($boundary): mixed {
        try {
            $boundary('truncate', null, null, [0]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'missing savepoint is rejected' => static function () use ($makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            SQLiteWalSavepointCheckpointPlan::readerBoundaryAfterRollbackTo($makeStack(), 'missing', $wal, $walBytes, $databaseBytes, [2]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'truncate boundary is ready' => 'ready',
    'truncate boundary keeps savepoint name' => 'plugin-options',
    'truncate boundary normalizes mode' => 'truncate',
    'truncate boundary retains pre savepoint frames' => 2,
    'truncate boundary discards current and nested frames' => 3,
    'truncate boundary is not busy without active reader pin' => false,
    'truncate boundary reports reset reason' => 'truncate_checkpoint_can_reset_and_truncate_wal',
    'truncate boundary truncates wal for next reader' => 'truncate_wal',
    'current reader defaults to retained mx frame' => 2,
    'next reader defaults to database snapshot after truncate' => 0,
    'current reader page one comes from wal' => 'wal',
    'current reader page two comes from wal' => 'wal',
    'current reader page three comes from database' => 'database',
    'next reader page one comes from checkpoint database' => 'database',
    'next reader page two comes from checkpoint database' => 'database',
    'next reader page three comes from checkpoint database' => 'database',
    'current reader frame indexes include schema frame' => 1,
    'current reader frame indexes include option frame' => 2,
    'current reader frame indexes leave page three in database' => null,
    'next reader frame index page one is null after truncate' => null,
    'next reader frame index page two is null after truncate' => null,
    'next reader uses checkpoint database flag' => true,
    'current reader kept wal snapshot flag' => true,
    'current and next images match across checkpoint boundary' => true,
    'current page one image is retained prefix frame' => true,
    'next page one image is checkpointed prefix frame' => true,
    'current page two image is retained commit frame' => true,
    'next page two image is checkpointed commit frame' => true,
    'current page three image is base database' => true,
    'next page three image remains base database' => true,
    'rolled back plugin commit is absent from current reader' => false,
    'rolled back plugin commit is absent from next reader' => false,
    'dependency includes current prefix' => true,
    'dependency includes current next boundary' => true,
    'restart boundary restarts wal for next reader' => 'restart_wal',
    'restart next reader sees database page one' => 'database',
    'restart next reader snapshot end is empty wal header' => 0,
    'restart images match across boundary' => true,
    'full boundary preserves wal bytes' => 'preserve_wal',
    'full next reader page one still uses wal' => 'wal',
    'full next reader page two still uses wal' => 'wal',
    'full images match across boundary' => true,
    'passive boundary preserves wal bytes' => 'preserve_wal',
    'explicit current reader before commit sees database page one' => 'database',
    'explicit current reader before commit sees database page two' => 'database',
    'explicit current reader before commit does not match next checkpoint image' => false,
    'explicit current reader at first uncommitted frame sees database page two' => 'database',
    'explicit current reader at commit sees option frame' => 2,
    'busy restart with current reader preserves wal' => 'preserve_wal',
    'busy restart reports busy' => true,
    'busy restart reports checkpoint completion blocker' => 'reader_blocks_checkpoint_completion',
    'busy restart next reader still uses retained wal page two' => 'wal',
    'single page boundary keeps requested page count' => 1,
    'single page boundary returns requested page number' => 2,
    'empty page list is rejected' => 'rejected',
    'non integer page list is rejected' => 'rejected',
    'zero page is rejected' => 'rejected',
    'missing savepoint is rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['wal reader checkpoint boundary current next19 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
