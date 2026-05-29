<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite-next124';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next124 schema base')
    . $page('next124 active_plugins base')
    . $page('next124 autoload base')
    . $page('next124 cron base')
    . $page('next124 transient base');

$makeWal = static function (array $frames, int $checkpoint = 124, int $salt1 = 0x12412401, int $salt2 = 0x12412402) use ($pageSize, $page): string {
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

$walBytes = $makeWal([
    [2, 0, 'next124 active_plugins reader draft'],
    [3, 5, 'next124 autoload reader commit'],
    [2, 0, 'next124 active_plugins later draft'],
    [4, 5, 'next124 cron checkpoint commit'],
    [2, 5, 'next124 active_plugins checkpoint tail'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$transactions = static fn (): array => [[
    'pages' => [
        2 => $page('next124 active_plugins restarted generation'),
        5 => $page('next124 transient restarted generation'),
    ],
    'database_page_count' => 5,
]];
$plan = static fn (int $reader = 2, bool $syncWal = true, bool $syncDirectory = true, array $pages = [1, 2, 3, 4, 5], ?string $sourceBytes = null): array => SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextPlan::plan(
    $databasePath,
    $wal,
    $sourceBytes ?? $walBytes,
    $databaseBytes,
    $transactions(),
    $pages,
    $reader,
    $syncWal,
    $syncDirectory
);
$restart = static fn (): array => $plan();
$latest = static fn (): array => $plan(5);
$unsynced = static fn (): array => $plan(2, false, false);
$nextWal = static fn (): SQLiteWal => SQLiteWal::parse((string) $restart()['append']['wal_bytes'], $pageSize, true);
$mutatedWalBytes = $makeWal([
    [2, 0, 'next124 active_plugins reader draft'],
    [3, 5, 'next124 autoload reader commit'],
    [2, 0, 'next124 active_plugins mismatch'],
    [4, 5, 'next124 cron checkpoint commit'],
    [2, 5, 'next124 active_plugins checkpoint tail'],
]);
$staleHeaderBytes = $makeWal([
    [2, 0, 'next124 active_plugins reader draft'],
    [3, 5, 'next124 autoload reader commit'],
    [2, 0, 'next124 active_plugins later draft'],
    [4, 5, 'next124 cron checkpoint commit'],
    [2, 5, 'next124 active_plugins checkpoint tail'],
], 125);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-checkpoint-reader-restart-snapshot-current-source-next124'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'reader_pins_current_source_while_released_restart_generation_accepts_next_writer'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'source status' => [static fn (): mixed => $restart()['source_status'], 'current-source'],
    'source frame count' => [static fn (): mixed => $restart()['source_frame_count'], 5],
    'parsed frame count' => [static fn (): mixed => $restart()['parsed_frame_count'], 5],
    'reader end frame' => [static fn (): mixed => $restart()['reader_end_frame'], 2],
    'next reader end frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 2],
    'pinned checkpoint busy' => [static fn (): mixed => $restart()['pinned_checkpoint']['busy'], true],
    'pinned checkpoint reason' => [static fn (): mixed => $restart()['pinned_checkpoint']['reason'], 'reader_blocks_checkpoint_completion'],
    'pinned checkpoint action' => [static fn (): mixed => $restart()['pinned_checkpoint']['wal_action'], 'preserve_wal'],
    'pinned checkpoint frames' => [static fn (): mixed => $restart()['pinned_checkpoint']['checkpointed_frame_count'], 1],
    'pinned checkpoint remaining frames' => [static fn (): mixed => $restart()['pinned_checkpoint']['remaining_committed_frame_count'], 2],
    'released checkpoint busy' => [static fn (): mixed => $restart()['released_checkpoint']['busy'], false],
    'released checkpoint reason' => [static fn (): mixed => $restart()['released_checkpoint']['reason'], 'restart_checkpoint_can_reset_wal'],
    'released checkpoint action' => [static fn (): mixed => $restart()['released_checkpoint']['wal_action'], 'restart_wal'],
    'released checkpoint bytes length' => [static fn (): mixed => $restart()['released_checkpoint']['wal_bytes_length'], 32],
    'restart header checkpoint sequence' => [static fn (): mixed => $restart()['restart_wal_header']['checkpoint_sequence'], 125],
    'restart header salt advanced' => [static fn (): mixed => $restart()['restart_wal_header']['salt1'], 0x12412402],
    'append status' => [static fn (): mixed => $restart()['append']['status'], 'planned'],
    'append reason' => [static fn (): mixed => $restart()['append']['reason'], 'wal_append_contains_commit_frame'],
    'append start offset header only' => [static fn (): mixed => $restart()['append']['start_offset'], 32],
    'append frame count' => [static fn (): mixed => $restart()['append']['appended_frame_count'], 2],
    'append committed count' => [static fn (): mixed => $restart()['append']['committed_transaction_count'], 1],
    'append uncommitted count' => [static fn (): mixed => $restart()['append']['uncommitted_transaction_count'], 0],
    'append last commit frame' => [static fn (): mixed => $restart()['append']['last_commit_frame'], 2],
    'append wal bytes length' => [static fn (): mixed => $restart()['append']['wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'append write offset' => [static fn (): mixed => $restart()['operations'][0]['offset'], 32],
    'append operations synced' => [static fn (): mixed => array_column($restart()['operations'], 'op'), ['write', 'sync', 'sync_directory']],
    'unsynced operations only write' => [static fn (): mixed => array_column($unsynced()['operations'], 'op'), ['write']],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['database', 'wal', 'wal', 'database', 'database']],
    'pinned sources' => [static fn (): mixed => $restart()['pinned_sources'], ['database', 'wal', 'wal', 'database', 'database']],
    'released sources' => [static fn (): mixed => $restart()['released_sources'], ['database', 'database', 'database', 'database', 'database']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'wal', 'database', 'database', 'wal']],
    'current frame indexes' => [static fn (): mixed => $restart()['current_frame_indexes'], [null, 1, 2, null, null]],
    'pinned frame indexes' => [static fn (): mixed => $restart()['pinned_frame_indexes'], [null, 1, 2, null, null]],
    'released frame indexes' => [static fn (): mixed => $restart()['released_frame_indexes'], [null, null, null, null, null]],
    'next frame indexes' => [static fn (): mixed => $restart()['next_frame_indexes'], [null, 1, null, null, 2]],
    'current errors empty' => [static fn (): mixed => $restart()['current_errors'], []],
    'pinned errors empty' => [static fn (): mixed => $restart()['pinned_errors'], []],
    'released errors empty' => [static fn (): mixed => $restart()['released_errors'], []],
    'next errors empty' => [static fn (): mixed => $restart()['next_errors'], []],
    'reader pin blocks restart reset' => [static fn (): mixed => $restart()['reader_pin_blocks_restart_reset'], true],
    'reader release restarts generation' => [static fn (): mixed => $restart()['reader_release_restarts_generation'], true],
    'current stable after pinned checkpoint' => [static fn (): mixed => $restart()['current_reader_stable_after_pinned_checkpoint'], true],
    'released database has checkpoint frames' => [static fn (): mixed => $restart()['released_database_has_checkpointed_frames'], true],
    'next uses restarted generation' => [static fn (): mixed => $restart()['next_uses_restarted_wal_generation'], true],
    'next uses appended wal' => [static fn (): mixed => $restart()['next_uses_appended_wal'], true],
    'current next images differ' => [static fn (): mixed => $restart()['current_next_images_match'], false],
    'released next images differ' => [static fn (): mixed => $restart()['released_next_images_match'], false],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['database>database>database>database', 'wal>wal>database>wal', 'wal>wal>database>database', 'database>database>database>database', 'database>database>database>wal']],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'current page two label' => [static fn (): mixed => str_contains($restart()['current_reader'][1]['image'], 'reader draft'), true],
    'pinned page two label stable' => [static fn (): mixed => str_contains($restart()['pinned_reader'][1]['image'], 'reader draft'), true],
    'released page two label checkpointed tail' => [static fn (): mixed => str_contains($restart()['released_database_reader'][1]['image'], 'checkpoint tail'), true],
    'next page two label restarted' => [static fn (): mixed => str_contains($restart()['next_reader'][1]['image'], 'restarted generation'), true],
    'next page five label restarted' => [static fn (): mixed => str_contains($restart()['next_reader'][4]['image'], 'transient restarted generation'), true],
    'latest reader current sources' => [static fn (): mixed => $latest()['current_sources'], ['database', 'wal', 'wal', 'wal', 'database']],
    'latest reader pinned reason' => [static fn (): mixed => $latest()['pinned_checkpoint']['reason'], 'reader_blocks_wal_reset'],
    'latest reader stable after pinned checkpoint' => [static fn (): mixed => $latest()['current_reader_stable_after_pinned_checkpoint'], true],
    'next wal frame count' => [static fn (): mixed => $nextWal()->frameCount(), 2],
    'next wal checkpoint sequence' => [static fn (): mixed => $nextWal()->header->checkpointSequence, 125],
    'dependency next124' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-restart-snapshot-current-source-next124', $restart()['dependencies'], true), true],
    'dependency next108' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-snapshot-current-source-next108', $restart()['dependencies'], true), true],
    'dependency append transaction' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $restart()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint reader restart snapshot current source next124 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextPlan::plan('', $wal, $walBytes, $databaseBytes, $transactions(), [1], 2),
    'empty pages rejected' => static fn () => SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextPlan::plan($databasePath, $wal, $walBytes, $databaseBytes, $transactions(), [], 2),
    'negative reader rejected' => static fn () => SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextPlan::plan($databasePath, $wal, $walBytes, $databaseBytes, $transactions(), [1], -1),
    'reader past wal rejected' => static fn () => SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextPlan::plan($databasePath, $wal, $walBytes, $databaseBytes, $transactions(), [1], 6),
    'stale source header rejected' => static fn () => $plan(2, true, true, [1], $staleHeaderBytes),
    'mutated source frame rejected' => static fn () => $plan(2, true, true, [1], $mutatedWalBytes),
    'non integer page rejected' => static fn () => $plan(2, true, true, ['1']),
    'empty transactions rejected' => static fn () => SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextPlan::plan($databasePath, $wal, $walBytes, $databaseBytes, [], [1], 2),
];

foreach ($throws as $name => $callback) {
    $tests['wal checkpoint reader restart snapshot current source next124 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
