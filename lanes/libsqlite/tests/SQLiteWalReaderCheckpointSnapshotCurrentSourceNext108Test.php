<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next108 schema base')
    . $page('next108 active_plugins base')
    . $page('next108 autoload base')
    . $page('next108 transient base')
    . $page('next108 cron base');

$makeWalBytes = static function (array $frames, int $checkpoint = 108, int $salt1 = 0x10810801, int $salt2 = 0x10810802) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$frames = [
    [2, 0, 'next108 active_plugins draft one'],
    [3, 5, 'next108 autoload commit one'],
    [2, 0, 'next108 active_plugins draft two'],
    [4, 5, 'next108 transient commit two'],
    [5, 0, 'next108 cron uncommitted tail'],
    [2, 5, 'next108 active_plugins commit three'],
];
$walBytes = $makeWalBytes($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$mutatedWalBytes = $makeWalBytes([
    [2, 0, 'next108 active_plugins draft one'],
    [3, 5, 'next108 autoload commit one'],
    [2, 0, 'next108 active_plugins mismatch'],
    [4, 5, 'next108 transient commit two'],
    [5, 0, 'next108 cron uncommitted tail'],
    [2, 5, 'next108 active_plugins commit three'],
]);
$staleHeaderBytes = $makeWalBytes($frames, 109);

$plan = static fn (?int $current = 2, ?int $next = null, array $pages = [1, 2, 3, 4, 5]): array => $wal->checkpointSnapshotCurrentSourceNext(
    $walBytes,
    $databaseBytes,
    $pages,
    $current ?? 2,
    $next
);
$oldReader = static fn (): array => $plan(2);
$midReader = static fn (): array => $plan(4);
$latestReader = static fn (): array => $plan(6);

$cases = [
    'status' => [static fn (): mixed => $oldReader()['status'], 'reader-checkpoint-snapshot-current-source-next108'],
    'source status' => [static fn (): mixed => $oldReader()['source_status'], 'current-source'],
    'page size' => [static fn (): mixed => $oldReader()['page_size'], $pageSize],
    'source frame count' => [static fn (): mixed => $oldReader()['source_frame_count'], 6],
    'parsed frame count' => [static fn (): mixed => $oldReader()['parsed_frame_count'], 6],
    'current reader end frame' => [static fn (): mixed => $oldReader()['current_reader_end_frame'], 2],
    'next reader end frame defaults latest' => [static fn (): mixed => $oldReader()['next_reader_end_frame'], 6],
    'current snapshot commit frame' => [static fn (): mixed => $oldReader()['current_snapshot']['commit_frame']->index, 2],
    'next snapshot commit frame' => [static fn (): mixed => $oldReader()['next_snapshot']['commit_frame']->index, 6],
    'current snapshot page count' => [static fn (): mixed => $oldReader()['current_snapshot']['database_page_count'], 5],
    'next snapshot page count' => [static fn (): mixed => $oldReader()['next_snapshot']['database_page_count'], 5],
    'current sources' => [static fn (): mixed => $oldReader()['current_sources'], ['database', 'wal', 'wal', 'database', 'database']],
    'next sources' => [static fn (): mixed => $oldReader()['next_sources'], ['database', 'wal', 'wal', 'wal', 'wal']],
    'limited current sources' => [static fn (): mixed => $oldReader()['limited_current_sources'], ['database', 'wal', 'wal', 'database', 'database']],
    'limited next sources' => [static fn (): mixed => $oldReader()['limited_next_sources'], ['database', 'wal', 'wal', 'wal', 'wal']],
    'released database sources' => [static fn (): mixed => $oldReader()['released_database_sources'], ['database', 'database', 'database', 'database', 'database']],
    'current frame indexes' => [static fn (): mixed => $oldReader()['current_frame_indexes'], [null, 1, 2, null, null]],
    'next frame indexes' => [static fn (): mixed => $oldReader()['next_frame_indexes'], [null, 6, 2, 4, 5]],
    'limited current frame indexes' => [static fn (): mixed => $oldReader()['limited_current_frame_indexes'], [null, 1, 2, null, null]],
    'limited next frame indexes' => [static fn (): mixed => $oldReader()['limited_next_frame_indexes'], [null, 6, 2, 4, 5]],
    'released database frame indexes' => [static fn (): mixed => $oldReader()['released_database_frame_indexes'], [null, null, null, null, null]],
    'current errors empty' => [static fn (): mixed => $oldReader()['current_errors'], []],
    'next errors empty' => [static fn (): mixed => $oldReader()['next_errors'], []],
    'limited current errors empty' => [static fn (): mixed => $oldReader()['limited_current_errors'], []],
    'limited next errors empty' => [static fn (): mixed => $oldReader()['limited_next_errors'], []],
    'released database errors empty' => [static fn (): mixed => $oldReader()['released_database_errors'], []],
    'limited passive mode' => [static fn (): mixed => $oldReader()['limited_passive_checkpoint']['mode'], 'passive'],
    'limited passive reason' => [static fn (): mixed => $oldReader()['limited_passive_checkpoint']['reason'], 'reader_limited_passive_checkpoint'],
    'limited passive not busy' => [static fn (): mixed => $oldReader()['limited_passive_checkpoint']['busy'], false],
    'limited passive checkpointed one frame' => [static fn (): mixed => $oldReader()['limited_passive_checkpoint']['checkpointed_frame_count'], 1],
    'limited passive leaves committed frames' => [static fn (): mixed => $oldReader()['limited_passive_checkpoint']['remaining_committed_frame_count'], 3],
    'limited passive preserves wal action' => [static fn (): mixed => $oldReader()['limited_passive_checkpoint']['wal_action'], 'preserve_wal'],
    'limited passive wal bytes remain full' => [static fn (): mixed => $oldReader()['limited_passive_checkpoint']['wal_bytes_length'], strlen($walBytes)],
    'limited full mode' => [static fn (): mixed => $oldReader()['limited_full_checkpoint']['mode'], 'full'],
    'limited full busy' => [static fn (): mixed => $oldReader()['limited_full_checkpoint']['busy'], true],
    'limited full reason' => [static fn (): mixed => $oldReader()['limited_full_checkpoint']['reason'], 'reader_blocks_checkpoint_completion'],
    'limited full preserves wal action' => [static fn (): mixed => $oldReader()['limited_full_checkpoint']['wal_action'], 'preserve_wal'],
    'released full mode' => [static fn (): mixed => $oldReader()['released_full_checkpoint']['mode'], 'full'],
    'released full not busy' => [static fn (): mixed => $oldReader()['released_full_checkpoint']['busy'], false],
    'released full reason' => [static fn (): mixed => $oldReader()['released_full_checkpoint']['reason'], 'full_checkpoint_complete'],
    'released full checkpointed all committed frames' => [static fn (): mixed => $oldReader()['released_full_checkpoint']['checkpointed_frame_count'], 4],
    'released full remaining committed zero' => [static fn (): mixed => $oldReader()['released_full_checkpoint']['remaining_committed_frame_count'], 0],
    'released full preserves wal bytes' => [static fn (): mixed => $oldReader()['released_full_checkpoint']['wal_bytes_length'], strlen($walBytes)],
    'current stable after limited checkpoint' => [static fn (): mixed => $oldReader()['current_stable_after_limited_checkpoint'], true],
    'next stable after limited checkpoint' => [static fn (): mixed => $oldReader()['next_stable_after_limited_checkpoint'], true],
    'next matches released database' => [static fn (): mixed => $oldReader()['next_matches_released_checkpoint_database'], true],
    'current next images differ' => [static fn (): mixed => $oldReader()['current_next_images_match'], false],
    'limited checkpoint preserves wal' => [static fn (): mixed => $oldReader()['limited_checkpoint_preserves_wal'], true],
    'limited full reports busy' => [static fn (): mixed => $oldReader()['limited_full_reports_busy'], true],
    'released full not busy flag' => [static fn (): mixed => $oldReader()['released_full_not_busy'], true],
    'released database has all frames flag' => [static fn (): mixed => $oldReader()['released_database_has_all_committed_frames'], true],
    'reader pin limits passive flag' => [static fn (): mixed => $oldReader()['reader_pin_limits_passive_checkpoint'], true],
    'reader pin blocks full flag' => [static fn (): mixed => $oldReader()['reader_pin_blocks_full_checkpoint'], true],
    'source digest length' => [static fn (): mixed => strlen($oldReader()['source_digest']), 64],
    'current page two old label' => [static fn (): mixed => str_contains($oldReader()['current_reader'][1]['image'], 'draft one'), true],
    'next page two latest label' => [static fn (): mixed => str_contains($oldReader()['next_reader'][1]['image'], 'commit three'), true],
    'released page two latest label' => [static fn (): mixed => str_contains($oldReader()['released_database_reader'][1]['image'], 'commit three'), true],
    'next page four wal label' => [static fn (): mixed => str_contains($oldReader()['next_reader'][3]['image'], 'transient commit two'), true],
    'released page four database label' => [static fn (): mixed => str_contains($oldReader()['released_database_reader'][3]['image'], 'transient commit two'), true],
    'page five tail becomes committed by frame six' => [static fn (): mixed => str_contains($oldReader()['next_reader'][4]['image'], 'cron uncommitted tail'), true],
    'dependency checkpoint' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $oldReader()['dependencies'], true), true],
    'dependency durable write' => [static fn (): mixed => in_array('durable-sidecar-write', $oldReader()['dependencies'], true), true],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-snapshot-current-source-next108', $oldReader()['dependencies'], true), true],
    'mid reader passive checkpointed frames' => [static fn (): mixed => $midReader()['limited_passive_checkpoint']['checkpointed_frame_count'], 2],
    'mid reader next sources' => [static fn (): mixed => $midReader()['next_sources'], ['database', 'wal', 'wal', 'wal', 'wal']],
    'latest reader current matches next' => [static fn (): mixed => $latestReader()['current_next_images_match'], true],
    'latest reader full not busy' => [static fn (): mixed => $latestReader()['limited_full_checkpoint']['busy'], false],
    'latest reader full complete' => [static fn (): mixed => $latestReader()['limited_full_checkpoint']['reason'], 'full_checkpoint_complete'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader checkpoint snapshot current source next108 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader checkpoint snapshot current source next108 rejects empty pages'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointSnapshotCurrentSourceNext($walBytes, $databaseBytes, [], 2));
};

$tests['wal reader checkpoint snapshot current source next108 rejects negative current reader'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointSnapshotCurrentSourceNext($walBytes, $databaseBytes, [1], -1));
};

$tests['wal reader checkpoint snapshot current source next108 rejects current reader past mx frame'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointSnapshotCurrentSourceNext($walBytes, $databaseBytes, [1], 7));
};

$tests['wal reader checkpoint snapshot current source next108 rejects negative next reader'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointSnapshotCurrentSourceNext($walBytes, $databaseBytes, [1], 2, -1));
};

$tests['wal reader checkpoint snapshot current source next108 rejects next reader past mx frame'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointSnapshotCurrentSourceNext($walBytes, $databaseBytes, [1], 2, 7));
};

$tests['wal reader checkpoint snapshot current source next108 rejects non integer page'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointSnapshotCurrentSourceNext($walBytes, $databaseBytes, ['1'], 2));
};

$tests['wal reader checkpoint snapshot current source next108 rejects stale header source'] = static function (TestRunner $t) use ($wal, $staleHeaderBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointSnapshotCurrentSourceNext($staleHeaderBytes, $databaseBytes, [1], 2));
};

$tests['wal reader checkpoint snapshot current source next108 rejects mutated frame source'] = static function (TestRunner $t) use ($wal, $mutatedWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointSnapshotCurrentSourceNext($mutatedWalBytes, $databaseBytes, [1], 2));
};

return $tests;
