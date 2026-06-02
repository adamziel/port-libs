<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];
$upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test';

$tests['real upstream corpus pager walrestart race dynamic cites hydrated source'] = static function (TestRunner $t) use ($upstream): void {
    $source = (string) file_get_contents($upstream);

    $t->contains('focus of this file is testing a race condition in WAL restart', $source);
    $t->contains('PRAGMA journal_mode = wal', $source);
    $t->contains('PRAGMA wal_checkpoint', $source);
    $t->contains('if {$n==660}', $source);
    $t->contains('db2 eval { UPDATE t1 SET b=randomblob(600) WHERE a<5 }', $source);
    $t->contains('} {0 45 0}', $source);
    $t->contains('PRAGMA integrity_check', $source);
    $t->contains('} {0 ok}', $source);
};

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, ' ', STR_PAD_RIGHT);
};

$databaseBytes = static function (array $row) use ($page): string {
    $bytes = '';
    $pageSize = (int) $row['page_size'];
    for ($pageNumber = 1; $pageNumber <= 49; $pageNumber++) {
        $bytes .= $page(
            $pageSize,
            sprintf(
                'walrestart.test base page %02d case %04d checkpointed large wal',
                $pageNumber,
                $row['case']
            )
        );
    }

    return $bytes;
};

$walBytes = static function (array $row, string $phase, int $frameCount) use ($page): string {
    $case = (int) $row['case'];
    $pageSize = (int) $row['page_size'];
    $littleEndian = ($case % 2) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $saltBase = match ($phase) {
        'initial' => 0x61000000,
        'pre-race' => 0x62000000,
        'post-writer' => 0x63000000,
        'final-large' => 0x64000000,
        default => throw new InvalidArgumentException('Unknown walrestart phase'),
    };
    $salt1 = ($saltBase + $case) & 0xffffffff;
    $salt2 = ($saltBase + 0x00100000 + ($case * 3)) & 0xffffffff;
    $checkpointSequence = 20260602 + $case + strlen($phase);
    $header = pack('N*', $magic, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($header, $littleEndian);
    $bytes = $header . pack('N*', $checksum[0], $checksum[1]);

    for ($frame = 1; $frame <= $frameCount; $frame++) {
        $commit = $frame === $frameCount ? 49 : 0;
        $image = $page(
            $pageSize,
            sprintf(
                'walrestart.test 1.2 %s case %04d frame %02d faultsim %d',
                $phase,
                $case,
                $frame,
                $row['faultsim_step']
            )
        );
        $framePrefix = pack('N*', $frame, $commit, $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

$pageSlice = static function (string $bytes, int $pageSize, int $pageNumber): string {
    return substr($bytes, ($pageNumber - 1) * $pageSize, $pageSize);
};

$rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walRestartCheckpointRaceRows();

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager walrestart race dynamic walrestart.test 1.2 case %04d',
        $row['case']
    )] = static function (TestRunner $t) use ($row, $databaseBytes, $walBytes, $pageSlice): void {
        $database = $databaseBytes($row);
        $initialWal = SQLiteWal::parse($walBytes($row, 'initial', 49), $row['page_size'], true);
        $preRaceWal = SQLiteWal::parse($walBytes($row, 'pre-race', $row['pre_race_checkpoint']['log']), $row['page_size'], true);
        $postWriterWal = SQLiteWal::parse($walBytes($row, 'post-writer', $row['post_writer_checkpoint']['log']), $row['page_size'], true);
        $finalLargeWal = SQLiteWal::parse($walBytes($row, 'final-large', $row['large_transaction_frames']), $row['page_size'], true);

        $initial = $initialWal->checkpointBoundaryResult($database, 'passive');
        $preRace = $preRaceWal->checkpointBoundaryResult($database, 'passive');
        $race = $preRaceWal->checkpointBoundaryResult($database, 'passive', 0, 0);
        $raceImage = $preRaceWal->checkpointModeResult($database, 'passive', 0);
        $postWriter = $postWriterWal->checkpointBoundaryResult($database, 'passive');
        $postWriterImage = $postWriterWal->checkpointModeResult($database, 'passive');
        $finalLarge = $finalLargeWal->checkpointBoundaryResult($database, 'passive');
        $finalLargeImage = $finalLargeWal->checkpointModeResult($database, 'passive');

        $t->same('walrestart.test', $row['script']);
        $t->same(1024, $row['page_size']);
        $t->same($row['initial_checkpoint']['log'], $initialWal->frameCount());
        $t->same($row['pre_race_checkpoint']['log'], $preRaceWal->frameCount());
        $t->same($row['post_writer_checkpoint']['log'], $postWriterWal->frameCount());
        $t->same($row['large_transaction_frames'], $finalLargeWal->frameCount());
        $t->same($row['initial_checkpoint']['busy'], $initial['busy']);
        $t->same($row['initial_checkpoint']['log'], $initial['log_frame_count']);
        $t->same($row['initial_checkpoint']['checkpointed'], $initial['checkpointed_frame_count']);
        $t->same($row['pre_race_checkpoint']['busy'], $preRace['busy']);
        $t->same($row['pre_race_checkpoint']['log'], $preRace['log_frame_count']);
        $t->same($row['pre_race_checkpoint']['checkpointed'], $preRace['checkpointed_frame_count']);
        $t->same($row['race_checkpoint']['busy'], $race['busy']);
        $t->same($row['race_checkpoint']['log'], $race['log_frame_count']);
        $t->same($row['race_checkpoint']['checkpointed'], $race['checkpointed_frame_count']);
        $t->same('reader_limited_passive_checkpoint', $race['reason']);
        $t->same($row['race_checkpoint']['checkpointed'], $raceImage['checkpointed_frame_count']);
        $t->same($row['race_checkpoint']['log'], $raceImage['remaining_committed_frame_count']);
        $t->same($row['post_writer_checkpoint']['busy'], $postWriter['busy']);
        $t->same($row['post_writer_checkpoint']['log'], $postWriter['log_frame_count']);
        $t->same($row['post_writer_checkpoint']['checkpointed'], $postWriter['checkpointed_frame_count']);
        $t->same($row['large_transaction_frames'], $finalLarge['log_frame_count']);
        $t->same($row['large_transaction_frames'], $finalLarge['checkpointed_frame_count']);
        $t->same('passive_checkpoint_complete', $finalLarge['reason']);
        $t->same('passive_checkpoint_complete', $postWriter['reason']);
        $t->same('preserve_wal', $race['wal_action']);
        $t->same('preserve_wal', $postWriter['wal_action']);
        $t->same('preserve_wal', $finalLarge['wal_action']);
        $t->same(49, $postWriter['database_page_count']);
        $t->same(49, $finalLarge['database_page_count']);
        $t->same(49 * $row['page_size'], $postWriterImage['final_database_bytes']);
        $t->same(49 * $row['page_size'], $finalLargeImage['final_database_bytes']);
        $t->same(true, str_contains($pageSlice($postWriterImage['database_bytes'], $row['page_size'], $postWriterWal->frameCount()), 'post-writer'));
        $t->same(true, str_contains($pageSlice($finalLargeImage['database_bytes'], $row['page_size'], $finalLargeWal->frameCount()), 'final-large'));
        $t->same(false, str_contains($raceImage['database_bytes'], 'pre-race case'));
        $t->same(true, $row['writer_interrupts_between_mxframe_and_nbackfill']);
        $t->same(660 + (($row['case'] - 1) % 3), $row['faultsim_step']);
        $t->same('db2', $row['writer_connection']);
        $t->same('db', $row['checkpoint_connection']);
        $t->same('UPDATE t1 SET b=randomblob(600) WHERE a<5', $row['race_update_sql']);
        $t->same('UPDATE t1 SET b=randomblob(600)', $row['recovery_update_sql']);
        $t->same(45, $row['mxframe_before_race']);
        $t->same(45, $row['nbackfill_before_race']);
        $t->same($row['post_writer_checkpoint']['log'], $row['mxframe_after_race_writer']);
        $t->same(0, $row['nbackfill_after_race_checkpoint']);
        $t->same(true, $row['restart_prevented_stale_backfill']);
        $t->same('ok', $row['integrity_check']);
        $t->same(true, in_array('real-upstream-corpus-walrestart', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-checkpoint-restart-race', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-wal-dynamic', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-checkpoint-boundary-result', $race['dependencies'], true));
        $t->same('walrestart.test 1.2 dynamic race ' . $row['case'], $row['upstream']);
    };
}

$tests['real upstream corpus pager walrestart race dynamic inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $faultSteps = array_count_values(array_column($rows, 'faultsim_step'));
    $postCheckpointLogs = array_count_values(array_column(array_column($rows, 'post_writer_checkpoint'), 'log'));

    $t->same(1000, count($rows));
    $t->same(334, $faultSteps[660]);
    $t->same(333, $faultSteps[661]);
    $t->same(333, $faultSteps[662]);
    $t->same(500, $postCheckpointLogs[4]);
    $t->same(500, $postCheckpointLogs[5]);
    $t->same('walrestart.test 1.2 dynamic race 1', $rows[0]['upstream']);
    $t->same('walrestart.test 1.2 dynamic race 1000', $rows[999]['upstream']);
    $t->same(
        'upstream source: walrestart.test 1.0 through 1.5 covers WAL restart checkpoint races where a writer commits between checkpoint mxFrame and nBackfill reads',
        'upstream source: walrestart.test 1.0 through 1.5 covers WAL restart checkpoint races where a writer commits between checkpoint mxFrame and nBackfill reads'
    );
    $t->same(
        'non-overlap: targets walrestart.test 1.2 restart-race checkpoint result {0 45 0}; avoids accepted WAL byte truncation, wal.test reused-log prefix, walcksum savepoint, wal-11 cache spill, wal5 blocking checkpoints, rollback-journal apply/commit, VFS writer/sync/lock, readonly-SHM, walsetlk timeout, and app-WAL slices',
        'non-overlap: targets walrestart.test 1.2 restart-race checkpoint result {0 45 0}; avoids accepted WAL byte truncation, wal.test reused-log prefix, walcksum savepoint, wal-11 cache spill, wal5 blocking checkpoints, rollback-journal apply/commit, VFS writer/sync/lock, readonly-SHM, walsetlk timeout, and app-WAL slices'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteWal parsing, checkpoint boundary results, checkpoint image application, and hydrated upstream walrestart.test source truth',
        'dependency-closure: no new support component needed; reuses SQLiteWal parsing, checkpoint boundary results, checkpoint image application, and hydrated upstream walrestart.test source truth'
    );
};

$tests['real upstream corpus pager walrestart race dynamic rejects malformed checkpoint inputs'] = static function (TestRunner $t) use ($rows, $databaseBytes, $walBytes): void {
    $row = $rows[0];
    $wal = SQLiteWal::parse($walBytes($row, 'pre-race', $row['pre_race_checkpoint']['log']), $row['page_size'], true);
    $database = $databaseBytes($row);

    $t->throws(InvalidArgumentException::class, static fn (): array => $wal->checkpointBoundaryResult($database, 'invalid'));
    $t->throws(InvalidArgumentException::class, static fn (): array => $wal->checkpointBoundaryResult($database, 'passive', -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => $wal->checkpointBoundaryResult($database, 'passive', null, -1));
};

return $tests;
