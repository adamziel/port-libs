<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 1024;
$salt1 = 0x6c697465;
$salt2 = 0x77616c30;
$page = static fn (string $label): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = '';
for ($pageNumber = 1; $pageNumber <= 12; $pageNumber++) {
    $databaseBytes .= $page("db-page-{$pageNumber}-before");
}

$makeWal = static function (array $frames, int $checkpointSequence) use ($pageSize, $salt1, $salt2): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$scenario = static function (int $case) use ($databaseBytes, $makeWal, $page): array {
    $frameCount = 4 + ($case % 23);
    $pageCount = 6 + ($case % 7);
    $frames = [];
    $latestImageByPage = [];
    for ($index = 1; $index <= $frameCount; $index++) {
        $pageNumber = 2 + (($case + ($index * 3)) % ($pageCount - 1));
        $image = $page("walckptnoop-case-{$case}-frame-{$index}-page-{$pageNumber}");
        $frames[] = [$pageNumber, $pageCount, $image];
        $latestImageByPage[$pageNumber] = $image;
    }

    $wal = SQLiteWal::parse($makeWal($frames, 1000 + $case), $pageCount, true);
    $noop1 = $wal->durableCheckpointResult($databaseBytes, 'noop');
    $noop2 = $wal->durableCheckpointResult($noop1['database_bytes'], 'noop');
    $passive = $wal->durableCheckpointResult($noop2['database_bytes'], 'passive');
    $afterPassiveNoop = $wal->durableCheckpointResult($passive['database_bytes'], 'noop');

    return [$wal, $noop1, $noop2, $passive, $afterPassiveNoop, $latestImageByPage, $pageCount];
};

for ($case = 1; $case <= 625; $case++) {
    $tests["real upstream walckptnoop dynamic {$case} keeps noop checkpoint side-effect free"] = static function (TestRunner $t) use ($scenario, $databaseBytes, $case): void {
        [$wal, $noop1, $noop2, $passive, $afterPassiveNoop, $latestImageByPage, $pageCount] = $scenario($case);
        $committable = count($latestImageByPage);

        $t->same('noop', $noop1['mode']);
        $t->same('noop_checkpoint_does_not_backfill', $noop1['reason']);
        $t->same(0, $noop1['checkpointed_frame_count']);
        $t->same($committable, $noop1['total_committable_frame_count']);
        $t->same($committable, $noop1['remaining_committed_frame_count']);
        $t->same('preserve_wal', $noop1['wal_action']);
        $t->same($databaseBytes, $noop1['database_bytes']);
        $t->same($wal->toBytes(), $noop1['wal_bytes']);

        $t->same($noop1['database_bytes'], $noop2['database_bytes']);
        $t->same($noop1['wal_bytes'], $noop2['wal_bytes']);

        $t->same('passive', $passive['mode']);
        $t->same('passive_checkpoint_complete', $passive['reason']);
        $t->same($committable, $passive['checkpointed_frame_count']);
        $t->same(0, $passive['remaining_committed_frame_count']);
        $t->same($pageCount, $passive['database_page_count']);

        foreach ($latestImageByPage as $pageNumber => $image) {
            $offset = ($pageNumber - 1) * 1024;
            $t->same($image, substr($passive['database_bytes'], $offset, 1024));
        }

        $t->same('noop', $afterPassiveNoop['mode']);
        $t->same('noop_checkpoint_does_not_backfill', $afterPassiveNoop['reason']);
        $t->same($passive['database_bytes'], $afterPassiveNoop['database_bytes']);
    };
}

$tests['real upstream walckptnoop rejects unsupported checkpoint mode'] = static function (TestRunner $t) use ($scenario): void {
    [$wal] = $scenario(626);

    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->durableCheckpointResult('', 'invalid'));
};

return $tests;
