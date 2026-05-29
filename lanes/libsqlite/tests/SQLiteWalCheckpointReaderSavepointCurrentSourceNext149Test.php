<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next149 schema base')
    . $page('next149 options base')
    . $page('next149 plugin base')
    . $page('next149 transient base');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x14914901;
    $salt2 = 0x14914902;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 149, $salt1, $salt2);
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
    [1, 0, 'next149 schema retained'],
    [2, 4, 'next149 options retained commit'],
    [3, 0, 'next149 plugin draft rolled back'],
    [2, 4, 'next149 options rolled back commit'],
    [4, 4, 'next149 transient rolled back tail'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wordpress-import');
    $stack->recordPageImageWrite(1, $page('next149 schema base'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordPageImageWrite(2, $page('next149 options base'));
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-update');
    $stack->recordPageImageWrite(3, $page('next149 plugin base'));
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 2, true);
    $stack->savepoint('transient-update');
    $stack->recordPageImageWrite(4, $page('next149 transient base'));
    $stack->recordWalFrameWrite(5, 4, true);

    return $stack;
};

$plan = static fn (string $mode = 'restart', ?int $reader = null, ?int $next = null, array $pages = [1, 2, 3, 4]): array => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReplayCurrentSourceNext(
    $makeStack(),
    'plugin-update',
    $wal,
    $walBytes,
    $databaseBytes,
    $pages,
    $mode,
    $reader,
    $next
);

$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$full = static fn (): array => $plan('full');
$oldReader = static fn (): array => $plan('restart', 2);
$single = static fn (): array => $plan('restart', null, null, [2]);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-checkpoint-reader-savepoint-current-source-next149'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-update'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'original reader end frame' => [static fn (): mixed => $restart()['original_reader_end_frame'], 5],
    'retained reader end frame' => [static fn (): mixed => $restart()['retained_reader_end_frame'], 2],
    'next reader end frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 0],
    'rollback to frame' => [static fn (): mixed => $restart()['rollback_to_frame'], 2],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 3],
    'discarded frame indexes' => [static fn (): mixed => array_column($restart()['discarded_wal_frames'], 'frame_index'), [3, 4, 5]],
    'discarded frame names' => [static fn (): mixed => array_column($restart()['discarded_wal_frames'], 'frame_name'), ['plugin-update', 'plugin-update', 'transient-update']],
    'wal action restart' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal'],
    'checkpoint reason restart' => [static fn (): mixed => $restart()['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'checkpoint not busy' => [static fn (): mixed => $restart()['checkpoint_busy'], false],
    'original sources' => [static fn (): mixed => $restart()['original_reader_sources'], ['wal', 'wal', 'wal', 'wal']],
    'retained sources' => [static fn (): mixed => $restart()['retained_reader_sources'], ['wal', 'wal', 'database', 'database']],
    'next sources' => [static fn (): mixed => $restart()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'original frames' => [static fn (): mixed => $restart()['original_reader_frame_indexes'], [1, 4, 3, 5]],
    'retained frames' => [static fn (): mixed => $restart()['retained_reader_frame_indexes'], [1, 2, null, null]],
    'next frames' => [static fn (): mixed => $restart()['next_reader_frame_indexes'], [null, null, null, null]],
    'current source keeps original wal' => [static fn (): mixed => $restart()['current_source_keeps_original_wal'], true],
    'retained source excludes tail' => [static fn (): mixed => $restart()['retained_source_excludes_savepoint_tail'], true],
    'next reader uses database' => [static fn (): mixed => $restart()['next_reader_uses_checkpoint_database'], true],
    'rolled back pages' => [static fn (): mixed => $restart()['rolled_back_pages'], [2, 3, 4]],
    'checkpointed pages' => [static fn (): mixed => $restart()['checkpointed_pages'], [1, 2]],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>database', 'wal>wal>database', 'wal>database>database', 'wal>database>database']],
    'retained equals next images' => [static fn (): mixed => $restart()['images_match_retained_to_next'], true],
    'original differs retained images' => [static fn (): mixed => $restart()['images_match_original_to_retained'], false],
    'current wal bytes length' => [static fn (): mixed => $restart()['current_wal_bytes_length'], 1104],
    'next wal bytes length restart' => [static fn (): mixed => $restart()['next_wal_bytes_length'], 32],
    'database bytes length' => [static fn (): mixed => $restart()['database_bytes_length'], 2048],
    'original page two label' => [static fn (): mixed => str_contains($restart()['original_reader'][1]['image'], 'next149 options rolled back commit'), true],
    'retained page two label' => [static fn (): mixed => str_contains($restart()['retained_reader'][1]['image'], 'next149 options retained commit'), true],
    'next page two label' => [static fn (): mixed => str_contains($restart()['next_reader'][1]['image'], 'next149 options retained commit'), true],
    'original page three label' => [static fn (): mixed => str_contains($restart()['original_reader'][2]['image'], 'next149 plugin draft rolled back'), true],
    'retained page three base label' => [static fn (): mixed => str_contains($restart()['retained_reader'][2]['image'], 'next149 plugin base'), true],
    'next page four base label' => [static fn (): mixed => str_contains($restart()['next_reader'][3]['image'], 'next149 transient base'), true],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal'],
    'truncate next wal length' => [static fn (): mixed => $truncate()['next_wal_bytes_length'], 0],
    'truncate next sources' => [static fn (): mixed => $truncate()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'full wal action' => [static fn (): mixed => $full()['wal_action'], 'preserve_wal'],
    'full next wal length' => [static fn (): mixed => $full()['next_wal_bytes_length'], 1104],
    'full next frames' => [static fn (): mixed => $full()['next_reader_frame_indexes'], [1, 2, null, null]],
    'old reader sources' => [static fn (): mixed => $oldReader()['original_reader_sources'], ['wal', 'wal', 'database', 'database']],
    'old reader frames' => [static fn (): mixed => $oldReader()['original_reader_frame_indexes'], [1, 2, null, null]],
    'old reader matches retained' => [static fn (): mixed => $oldReader()['images_match_original_to_retained'], true],
    'old reader source flag' => [static fn (): mixed => $oldReader()['current_source_keeps_original_wal'], false],
    'single page count original' => [static fn (): mixed => count($single()['original_reader']), 1],
    'single page transitions' => [static fn (): mixed => $single()['source_transitions'], ['wal>wal>database']],
    'single page rolled back pages unchanged' => [static fn (): mixed => $single()['rolled_back_pages'], [2, 3, 4]],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-savepoint-current-source-next149', $restart()['dependencies'], true), true],
    'dependency wordpress marker' => [static fn (): mixed => in_array('wordpress-import-wal-current-reader-savepoint-boundary', $restart()['dependencies'], true), true],
    'dependency current prefix marker' => [static fn (): mixed => in_array('sqlite-savepoint-wal-current-prefix', $restart()['dependencies'], true), true],
    'dependency checkpoint marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $restart()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint reader savepoint current source next149 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty savepoint rejected' => static fn () => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReplayCurrentSourceNext($makeStack(), '', $wal, $walBytes, $databaseBytes, [1]),
    'empty pages rejected' => static fn () => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReplayCurrentSourceNext($makeStack(), 'plugin-update', $wal, $walBytes, $databaseBytes, []),
    'bad mode rejected' => static fn () => $plan('checkpoint'),
    'negative reader rejected' => static fn () => $plan('restart', -1),
    'reader past wal rejected' => static fn () => $plan('restart', 6),
    'bad next reader rejected' => static fn () => $plan('restart', null, 1),
    'non integer page rejected' => static fn () => $plan('restart', null, null, ['2']),
    'zero page rejected' => static fn () => $plan('restart', null, null, [0]),
    'missing savepoint rejected' => static fn () => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReplayCurrentSourceNext($makeStack(), 'missing', $wal, $walBytes, $databaseBytes, [1]),
    'wal bytes mismatch rejected' => static fn () => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReplayCurrentSourceNext($makeStack(), 'plugin-update', $wal, substr_replace($walBytes, 'x', 64, 1), $databaseBytes, [1]),
    'empty database rejected' => static fn () => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReplayCurrentSourceNext($makeStack(), 'plugin-update', $wal, $walBytes, '', [1]),
];

foreach ($throws as $name => $callback) {
    $tests['wal checkpoint reader savepoint current source next149 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
