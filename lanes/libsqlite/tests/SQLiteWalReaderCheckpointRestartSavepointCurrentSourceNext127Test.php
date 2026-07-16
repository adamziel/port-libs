<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite-next127';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next127 schema base')
    . $page('next127 active_plugins base')
    . $page('next127 autoload base')
    . $page('next127 cron base')
    . $page('next127 transient base')
    . $page('next127 rewrite_rules base');

$makeWalBytes = static function (array $frames, int $checkpoint = 127, int $salt1 = 0x12712701, int $salt2 = 0x12712702) use ($pageSize, $page): string {
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
    [2, 0, 'next127 active_plugins retained draft'],
    [3, 6, 'next127 autoload retained commit'],
    [4, 0, 'next127 cron stale draft'],
    [5, 6, 'next127 transient stale commit'],
    [2, 6, 'next127 active_plugins stale tail'],
    [6, 6, 'next127 rewrite_rules stale commit'],
];
$walBytes = $makeWalBytes($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$retainedReaderBytes = $makeWalBytes(array_slice($frames, 0, 2));

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next127');
    $stack->recordWalFrameWrite(1, 2);
    $stack->recordWalFrameWrite(2, 3, true);
    $stack->savepoint('plugin-settings-next127');
    $stack->recordWalFrameWrite(3, 4);
    $stack->recordWalFrameWrite(4, 5, true);
    $stack->recordWalFrameWrite(5, 2, true);
    $stack->recordWalFrameWrite(6, 6, true);

    return $stack;
};

$nextTransactions = static fn (): array => [[
    'pages' => [
        2 => $page('next127 active_plugins restarted generation'),
        5 => $page('next127 transient restarted generation'),
        6 => $page('next127 rewrite_rules restarted generation'),
    ],
    'database_page_count' => 6,
]];

$plan = static function (int $reader = 6, ?string $readerBytes = null, array $pages = [1, 2, 3, 4, 5, 6], bool $syncWal = true, bool $syncDirectory = true) use ($makeStack, $wal, $walBytes, $databaseBytes, $databasePath, $nextTransactions): array {
    return SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan(
        $makeStack(),
        'plugin-settings-next127',
        $wal,
        $walBytes,
        $readerBytes ?? $walBytes,
        $databaseBytes,
        $databasePath,
        $nextTransactions(),
        $pages,
        $reader,
        $syncWal,
        $syncDirectory
    );
};

$restart = static fn (): array => $plan();
$retained = static fn (): array => $plan(2, $retainedReaderBytes);
$unsynced = static fn (): array => $plan(6, $walBytes, [2, 5], false, false);
$single = static fn (): array => $plan(6, $walBytes, [2]);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-reader-checkpoint-restart-savepoint-current-source-next127'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'savepoint_rollback_retains_current_reader_source_before_released_restart_checkpoint_accepts_next_generation'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next127'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $restart()['reader_end_frame'], 6],
    'retained reader end frame' => [static fn (): mixed => $restart()['retained_reader_end_frame'], 2],
    'retained frames' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frames' => [static fn (): mixed => $restart()['discarded_frame_count'], 4],
    'discarded frame indexes' => [static fn (): mixed => $restart()['discarded_frame_indexes'], [3, 4, 5, 6]],
    'stale reader tail frame indexes' => [static fn (): mixed => $restart()['stale_reader_tail_frame_indexes'], [3, 4, 5, 6]],
    'stale reader page numbers' => [static fn (): mixed => $restart()['stale_reader_page_numbers'], [4, 5, 2, 6]],
    'stale source offsets' => [static fn (): mixed => array_column($restart()['stale_reader_frames'], 'source_offset'), [1104, 1640, 2176, 2712]],
    'stale source lengths' => [static fn (): mixed => array_column($restart()['stale_reader_frames'], 'source_length'), [536, 536, 536, 536]],
    'stale commit flags' => [static fn (): mixed => array_column($restart()['stale_reader_frames'], 'commit_frame'), [false, true, true, true]],
    'reader source differs retained' => [static fn (): mixed => $restart()['reader_source_matches_retained'], false],
    'reader sha length' => [static fn (): mixed => strlen($restart()['reader_wal_sha256']), 64],
    'current sha length' => [static fn (): mixed => strlen($restart()['current_wal_sha256']), 64],
    'retained sha length' => [static fn (): mixed => strlen($restart()['retained_wal_sha256']), 64],
    'pinned status busy' => [static fn (): mixed => $restart()['pinned_status'], 'busy'],
    'released status planned' => [static fn (): mixed => $restart()['released_status'], 'planned'],
    'pinned checkpoint busy' => [static fn (): mixed => $restart()['pinned_checkpoint_busy'], true],
    'pinned checkpoint reason' => [static fn (): mixed => $restart()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'released checkpoint not busy' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'released checkpoint reason' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released checkpoint action' => [static fn (): mixed => $restart()['released_checkpoint_action'], 'restart_wal'],
    'released restart sequence' => [static fn (): mixed => $restart()['released_restart_header_checkpoint_sequence'], 128],
    'next append frame count' => [static fn (): mixed => $restart()['next_append_frame_count'], 3],
    'next append last commit frame' => [static fn (): mixed => $restart()['next_append_last_commit_frame'], 3],
    'operation sequence' => [static fn (): mixed => array_column($restart()['operations'], 'op'), ['write', 'sync', 'sync_directory']],
    'unsynced operation sequence' => [static fn (): mixed => array_column($unsynced()['operations'], 'op'), ['write']],
    'stale reader sources' => [static fn (): mixed => $restart()['stale_reader_sources'], ['database', 'wal', 'wal', 'wal', 'wal', 'wal']],
    'retained reader sources' => [static fn (): mixed => $restart()['retained_reader_sources'], ['database', 'wal', 'wal', 'database', 'database', 'database']],
    'pinned current sources' => [static fn (): mixed => $restart()['pinned_current_sources'], ['database', 'wal', 'wal', 'database', 'database', 'database']],
    'released next sources' => [static fn (): mixed => $restart()['released_next_sources'], ['database', 'wal', 'database', 'database', 'wal', 'wal']],
    'stale reader frames' => [static fn (): mixed => $restart()['stale_reader_frame_indexes'], [null, 5, 2, 3, 4, 6]],
    'retained reader frames' => [static fn (): mixed => $restart()['retained_reader_frame_indexes'], [null, 1, 2, null, null, null]],
    'pinned current frames' => [static fn (): mixed => $restart()['pinned_current_frame_indexes'], [null, 1, 2, null, null, null]],
    'released next frames' => [static fn (): mixed => $restart()['released_next_frame_indexes'], [null, 1, null, null, 2, 3]],
    'row count' => [static fn (): mixed => count($restart()['rows']), 6],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5, 6]],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['database>database>database>database', 'wal>wal>wal>wal', 'wal>wal>wal>database', 'wal>database>database>database', 'wal>database>database>wal', 'wal>database>database>wal']],
    'rollback changed pages' => [static fn (): mixed => $restart()['rollback_changed_pages'], [2, 4, 5, 6]],
    'pinned preserves retained images' => [static fn (): mixed => $restart()['pinned_preserved_retained_images'], true],
    'released uses restarted generation' => [static fn (): mixed => $restart()['released_reader_uses_restarted_generation'], true],
    'released uses checkpoint database' => [static fn (): mixed => $restart()['released_reader_uses_checkpoint_database'], true],
    'reader release unblocked restart' => [static fn (): mixed => $restart()['reader_release_unblocked_restart'], true],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'stale option label' => [static fn (): mixed => str_contains($restart()['rows'][1]['stale_reader_label'], 'stale tail'), true],
    'retained option label' => [static fn (): mixed => str_contains($restart()['rows'][1]['retained_reader_label'], 'retained draft'), true],
    'pinned option label' => [static fn (): mixed => str_contains($restart()['rows'][1]['pinned_current_label'], 'retained draft'), true],
    'released option label' => [static fn (): mixed => str_contains($restart()['rows'][1]['released_next_label'], 'restarted generation'), true],
    'retained reader source matches retained' => [static fn (): mixed => $retained()['reader_source_matches_retained'], true],
    'retained reader stale frames empty' => [static fn (): mixed => $retained()['stale_reader_tail_frame_indexes'], []],
    'retained reader rollback changed empty' => [static fn (): mixed => $retained()['rollback_changed_pages'], []],
    'single page rollback changed option' => [static fn (): mixed => $single()['rollback_changed_pages'], [2]],
    'dependency next127' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-restart-savepoint-current-source-next127', $restart()['dependencies'], true), true],
    'dependency next123' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-savepoint-truncate-current-source-next123', $restart()['dependencies'], true), true],
    'dependency next124' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-restart-snapshot-current-source-next124', $restart()['dependencies'], true), true],
    'dependency append' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $restart()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader checkpoint restart savepoint current source next127 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty savepoint rejected' => static fn () => SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan($makeStack(), '', $wal, $walBytes, $walBytes, $databaseBytes, $databasePath, $nextTransactions(), [1], 2),
    'empty wal bytes rejected' => static fn () => SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next127', $wal, '', $walBytes, $databaseBytes, $databasePath, $nextTransactions(), [1], 2),
    'empty reader wal bytes rejected' => static fn () => SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next127', $wal, $walBytes, '', $databaseBytes, $databasePath, $nextTransactions(), [1], 2),
    'empty database rejected' => static fn () => SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next127', $wal, $walBytes, $walBytes, '', $databasePath, $nextTransactions(), [1], 2),
    'empty path rejected' => static fn () => SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next127', $wal, $walBytes, $walBytes, $databaseBytes, '', $nextTransactions(), [1], 2),
    'empty pages rejected' => static fn () => SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next127', $wal, $walBytes, $walBytes, $databaseBytes, $databasePath, $nextTransactions(), [], 2),
    'negative reader rejected' => static fn () => SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next127', $wal, $walBytes, $walBytes, $databaseBytes, $databasePath, $nextTransactions(), [1], -1),
    'source mismatch rejected' => static fn () => SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next127', $wal, substr_replace($walBytes, 'x', 1600, 1), $walBytes, $databaseBytes, $databasePath, $nextTransactions(), [1], 2),
    'reader past wal rejected' => static fn () => SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next127', $wal, $walBytes, $walBytes, $databaseBytes, $databasePath, $nextTransactions(), [1], 7),
    'non integer page rejected' => static fn () => SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next127', $wal, $walBytes, $walBytes, $databaseBytes, $databasePath, $nextTransactions(), ['1'], 2),
];

foreach ($throws as $name => $callback) {
    $tests['wal reader checkpoint restart savepoint current source next127 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
