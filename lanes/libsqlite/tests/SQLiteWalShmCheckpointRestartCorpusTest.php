<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$baseDatabase = $page('db-page-1-base-schema') . $page('db-page-2-base-options') . $page('db-page-3-base-index');
$salt1 = 0x13572468;
$salt2 = 0x24681357;

$makeWal = static function (array $frames, int $checkpointSequence = 7) use ($pageSize, $salt1, $salt2): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWal([
    [2, 0, $page('wal-frame-1-siteurl-draft')],
    [3, 3, $page('wal-frame-2-autoload-index-commit')],
    [2, 0, $page('wal-frame-3-siteurl-later')],
    [2, 3, $page('wal-frame-4-siteurl-final-commit')],
]);
$wal = SQLiteWal::parse($walBytes, null, true);

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, bool $copyMatches = true) use ($pageSize): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack(
        'V*',
        3007000,
        $backfill,
        88,
        $pageSizeField,
        4,
        3,
        0x01020304,
        0x05060708,
        0x11111111,
        0x22222222,
        0x33333333,
        0x44444444
    );
    $headerCopy = $copyMatches
        ? $header
        : pack('V*', 3007000, $backfill, 89, $pageSizeField, 4, 3, 0x01020304, 0x05060708, 0x11111111, 0x22222222, 0x33333333, 0x44444444);

    $marks = array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, 0xffffffff);
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $headerCopy . $checkpoint;
};

$pinnedShm = SQLiteShmIndex::parse($makeShm([0, 2, 4, null, 9], [false, true, true, false, true], 1, 3));
$releasedShm = SQLiteShmIndex::parse($makeShm([0, 4, null, null, null], [false, true, false, false, false], 4, 4));
$staleHeaderShm = SQLiteShmIndex::parse($makeShm([4, null, null, null, null], [true, false, false, false, false], 4, 4, false));

$cases = [
    'wal frame count' => static fn (): mixed => $wal->frameCount(),
    'wal committed transaction ranges' => static fn (): mixed => $wal->committedTransactions(),
    'wal last commit frame' => static fn (): mixed => $wal->lastCommitFrame()?->index,
    'checkpoint plan applies latest page two only' => static fn (): mixed => array_column(array_filter($wal->checkpointPlan($baseDatabase)['frames'], static fn (array $frame): bool => $frame['applied']), 'frame_index'),
    'checkpoint plan marks first page two superseded' => static fn (): mixed => $wal->checkpointPlan($baseDatabase)['frames'][0]['reason'],
    'checkpoint plan final byte length' => static fn (): mixed => $wal->checkpointPlan($baseDatabase)['final_database_bytes'],
    'restart without reader can reset' => static fn (): mixed => $wal->checkpointModePlan($baseDatabase, 'restart')['can_reset'],
    'restart without reader reason' => static fn (): mixed => $wal->checkpointModePlan($baseDatabase, 'restart')['reason'],
    'restart without reader checkpointed frames' => static fn (): mixed => $wal->checkpointModePlan($baseDatabase, 'restart')['checkpointed_frame_count'],
    'truncate without reader can truncate' => static fn (): mixed => $wal->checkpointModePlan($baseDatabase, 'truncate')['can_truncate'],
    'reader at frame two blocks restart completion' => static fn (): mixed => $wal->checkpointModePlan($baseDatabase, 'restart', 2)['reason'],
    'reader at frame two leaves remaining committed frames' => static fn (): mixed => $wal->checkpointModePlan($baseDatabase, 'restart', 2)['remaining_committed_frame_count'],
    'reader at last commit blocks restart reset only' => static fn (): mixed => $wal->checkpointModePlan($baseDatabase, 'restart', 4)['reason'],
    'reader at last commit checkpointed all frames' => static fn (): mixed => $wal->checkpointModePlan($baseDatabase, 'restart', 4)['checkpointed_frame_count'],
    'passive reader at frame two is busy false' => static fn (): mixed => $wal->checkpointModePlan($baseDatabase, 'passive', 2)['busy'],
    'full reader at frame two is busy true' => static fn (): mixed => $wal->checkpointModePlan($baseDatabase, 'full', 2)['busy'],
    'restart result emits restart wal action' => static fn (): mixed => $wal->checkpointModeResult($baseDatabase, 'restart')['wal_action'],
    'truncate result emits truncate wal action' => static fn (): mixed => $wal->checkpointModeResult($baseDatabase, 'truncate')['wal_action'],
    'restart durable result writes wal header only' => static fn (): mixed => $wal->durableCheckpointResult($baseDatabase, 'restart')['wal_bytes_length'],
    'restart durable header increments checkpoint sequence' => static fn (): mixed => $wal->durableCheckpointResult($baseDatabase, 'restart')['wal_header']['checkpoint_sequence'],
    'restart durable header advances first salt' => static fn (): mixed => $wal->durableCheckpointResult($baseDatabase, 'restart')['wal_header']['salt1'],
    'truncate durable result removes wal bytes' => static fn (): mixed => $wal->durableCheckpointResult($baseDatabase, 'truncate')['wal_bytes_length'],
    'reader-limited durable checkpoint preserves wal bytes' => static fn (): mixed => $wal->durableCheckpointResult($baseDatabase, 'passive', 2)['wal_bytes_length'],
    'reader-limited durable checkpoint keeps base page three' => static fn (): mixed => str_contains($wal->durableCheckpointResult($baseDatabase, 'passive', 1)['database_bytes'], 'db-page-3-base-index'),
    'reader-limited durable checkpoint applies page two draft' => static fn (): mixed => str_contains($wal->durableCheckpointResult($baseDatabase, 'passive', 1)['database_bytes'], 'wal-frame-1-siteurl-draft'),
    'reader visibility through restart stays stable' => static fn (): mixed => $wal->checkpointReaderVisibility($baseDatabase, [2, 3], 'restart', 4)['stable'],
    'reader visibility through restart reports preserved wal' => static fn (): mixed => $wal->checkpointReaderVisibility($baseDatabase, [2], 'restart', 4)['wal_action'],
    'new reader after restart reads database page two' => static fn (): mixed => $wal->checkpointReaderVisibility($baseDatabase, [2], 'restart')['after'][0]['source'],
    'new reader after truncate reads database page two' => static fn (): mixed => $wal->checkpointReaderVisibility($baseDatabase, [2], 'truncate')['after'][0]['source'],
    'wal read mark plan recommends reusable slot' => static fn (): mixed => $wal->readMarkPlan([0, 2, 4, null, 9])['recommended_reader_slot'],
    'wal read mark plan pins older snapshot' => static fn (): mixed => $wal->readMarkPlan([0, 2, 4, null, 9])['checkpoint_pinned_frame'],
    'wal read mark invalid mark reason' => static fn (): mixed => $wal->readMarkPlan([0, 2, 4, null, 9])['read_marks'][4]['reason'],
    'shm pinned plan blocks reset' => static fn (): mixed => $pinnedShm->checkpointPlan()['reset_blocked'],
    'shm pinned plan pinned frame' => static fn (): mixed => $pinnedShm->checkpointPlan()['checkpoint_pinned_frame'],
    'shm pinned plan reusable slots' => static fn (): mixed => $pinnedShm->checkpointPlan()['reusable_slots'],
    'shm released plan can finish' => static fn (): mixed => $releasedShm->checkpointPlan()['checkpoint_can_finish'],
    'shm released plan read locks' => static fn (): mixed => $releasedShm->checkpointPlan()['read_locks'],
    'shm stale header reports status' => static fn (): mixed => $staleHeaderShm->checkpointPlan()['status'],
    'shm invalid read mark is reusable' => static fn (): mixed => $pinnedShm->checkpointPlan()['read_marks'][4]['reason'],
    'checkpoint rejects negative reader frame' => static function () use ($wal, $baseDatabase): mixed {
        try {
            $wal->checkpointModePlan($baseDatabase, 'restart', -1);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'wal frame count' => 4,
    'wal committed transaction ranges' => [
        ['first_frame' => 1, 'last_frame' => 2, 'database_page_count' => 3, 'page_numbers' => [2, 3]],
        ['first_frame' => 3, 'last_frame' => 4, 'database_page_count' => 3, 'page_numbers' => [2]],
    ],
    'wal last commit frame' => 4,
    'checkpoint plan applies latest page two only' => [2, 4],
    'checkpoint plan marks first page two superseded' => 'superseded_by_later_committed_frame',
    'checkpoint plan final byte length' => 1536,
    'restart without reader can reset' => true,
    'restart without reader reason' => 'restart_checkpoint_can_reset_wal',
    'restart without reader checkpointed frames' => 2,
    'truncate without reader can truncate' => true,
    'reader at frame two blocks restart completion' => 'reader_blocks_checkpoint_completion',
    'reader at frame two leaves remaining committed frames' => 1,
    'reader at last commit blocks restart reset only' => 'reader_blocks_wal_reset',
    'reader at last commit checkpointed all frames' => 2,
    'passive reader at frame two is busy false' => false,
    'full reader at frame two is busy true' => true,
    'restart result emits restart wal action' => 'restart_wal',
    'truncate result emits truncate wal action' => 'truncate_wal',
    'restart durable result writes wal header only' => 32,
    'restart durable header increments checkpoint sequence' => 8,
    'restart durable header advances first salt' => 0x13572469,
    'truncate durable result removes wal bytes' => 0,
    'reader-limited durable checkpoint preserves wal bytes' => strlen($walBytes),
    'reader-limited durable checkpoint keeps base page three' => true,
    'reader-limited durable checkpoint applies page two draft' => false,
    'reader visibility through restart stays stable' => true,
    'reader visibility through restart reports preserved wal' => 'preserve_wal',
    'new reader after restart reads database page two' => 'database',
    'new reader after truncate reads database page two' => 'database',
    'wal read mark plan recommends reusable slot' => 0,
    'wal read mark plan pins older snapshot' => 2,
    'wal read mark invalid mark reason' => 'beyond_wal_mx_frame',
    'shm pinned plan blocks reset' => true,
    'shm pinned plan pinned frame' => 2,
    'shm pinned plan reusable slots' => [0, 3, 4],
    'shm released plan can finish' => true,
    'shm released plan read locks' => [false, true, false, false, false],
    'shm stale header reports status' => 'stale-header-copy',
    'shm invalid read mark is reusable' => 'beyond_wal_mx_frame',
    'checkpoint rejects negative reader frame' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['wal shm checkpoint restart corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
