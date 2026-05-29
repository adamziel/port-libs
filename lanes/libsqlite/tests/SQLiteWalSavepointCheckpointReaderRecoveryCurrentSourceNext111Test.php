<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next111 base schema page')
    . $page('next111 base options page')
    . $page('next111 base plugin page')
    . $page('next111 base autoload page')
    . $page('next111 base transient page');

$makeWalBytes = static function (array $frames, bool $corruptLast = false) use ($pageSize, $page): string {
    $salt1 = 0x11111101;
    $salt2 = 0x11111102;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 111, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $index => [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $frame = $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
        if ($corruptLast && $index === array_key_last($frames)) {
            $frame = substr_replace($frame, 'Z', 24 + 20, 1);
        }
        $bytes .= $frame;
    }

    return $bytes;
};

$frames = [
    [1, 0, 'next111 retained schema draft'],
    [2, 5, 'next111 retained options commit'],
    [3, 0, 'next111 plugin savepoint draft'],
    [4, 0, 'next111 autoload savepoint draft'],
    [4, 5, 'next111 autoload savepoint commit'],
    [5, 0, 'next111 valid uncommitted transient tail'],
    [2, 5, 'next111 corrupt options stale tail'],
];
$walBytes = $makeWalBytes($frames, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next111');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next111');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 4, true);
    $stack->recordWalFrameWrite(6, 5);

    return $stack;
};

$plan = static fn (string $mode = 'restart', ?int $reader = null, array $pages = [1, 2, 3, 4, 5]): array => SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan::plan(
    $makeStack(),
    'plugin-settings-next111',
    $walBytes,
    $databaseBytes,
    $pages,
    $mode,
    $reader,
    $pageSize
);
$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$retainedReader = static fn (): array => $plan('restart', 2);
$single = static fn (): array => $plan('restart', null, [4]);

$cases = [
    'status release unblocks' => [static fn (): mixed => $restart()['status'], 'reader-recovered-savepoint-checkpoint-release-unblocks-next111'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'uncommitted_valid_tail_before_corrupt_frame'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next111'],
    'page size' => [static fn (): mixed => $restart()['page_size'], $pageSize],
    'original reader end defaults to total slots' => [static fn (): mixed => $restart()['original_reader_end_frame'], 7],
    'valid reader clamped before corrupt' => [static fn (): mixed => $restart()['valid_reader_end_frame'], 6],
    'recovered reader clamped to commit' => [static fn (): mixed => $restart()['recovered_reader_end_frame'], 5],
    'current reader clamped to savepoint prefix' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'valid frame count' => [static fn (): mixed => $restart()['valid_frame_count'], 6],
    'committed frame count' => [static fn (): mixed => $restart()['committed_frame_count'], 5],
    'total frame slots' => [static fn (): mixed => $restart()['total_frame_slots'], 7],
    'first invalid frame' => [static fn (): mixed => $restart()['first_invalid_frame'], 7],
    'discarded valid tail count' => [static fn (): mixed => $restart()['discarded_valid_tail_frame_count'], 1],
    'discarded corrupt tail count' => [static fn (): mixed => $restart()['discarded_corrupt_tail_frame_count'], 1],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded savepoint frame count' => [static fn (): mixed => $restart()['discarded_savepoint_frame_count'], 3],
    'pinned checkpoint busy' => [static fn (): mixed => $restart()['pinned_checkpoint_busy'], true],
    'pinned checkpoint reason' => [static fn (): mixed => $restart()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned wal action' => [static fn (): mixed => $restart()['pinned_wal_action'], 'preserve_wal'],
    'released checkpoint not busy' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'released checkpoint reason' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released wal action' => [static fn (): mixed => $restart()['released_wal_action'], 'restart_wal'],
    'current wal bytes length' => [static fn (): mixed => $restart()['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'pinned wal bytes length' => [static fn (): mixed => $restart()['pinned_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released wal bytes length' => [static fn (): mixed => $restart()['released_wal_bytes_length'], 32],
    'before sources' => [static fn (): mixed => $restart()['before_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'recovered sources' => [static fn (): mixed => $restart()['recovered_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'pinned next sources' => [static fn (): mixed => $restart()['pinned_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'released next sources' => [static fn (): mixed => $restart()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'before frame indexes' => [static fn (): mixed => $restart()['before_frame_indexes'], [1, 2, 3, 5, null]],
    'recovered frame indexes' => [static fn (): mixed => $restart()['recovered_frame_indexes'], [1, 2, 3, 5, null]],
    'current frame indexes' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, null, null, null]],
    'pinned next frame indexes' => [static fn (): mixed => $restart()['pinned_next_frame_indexes'], [1, 2, null, null, null]],
    'released next frame indexes' => [static fn (): mixed => $restart()['released_next_frame_indexes'], [null, null, null, null, null]],
    'tail recovery changed images false' => [static fn (): mixed => $restart()['tail_recovery_changed_images'], false],
    'savepoint rollback changed images true' => [static fn (): mixed => $restart()['savepoint_rollback_changed_images'], true],
    'pinned checkpoint preserved images' => [static fn (): mixed => $restart()['pinned_checkpoint_preserved_images'], true],
    'released checkpoint preserved images' => [static fn (): mixed => $restart()['released_checkpoint_preserved_images'], true],
    'released reader uses checkpoint database' => [static fn (): mixed => $restart()['released_reader_uses_checkpoint_database'], true],
    'reader release unblocked checkpoint' => [static fn (): mixed => $restart()['reader_release_unblocked_checkpoint'], true],
    'reader recovered from stale end frame' => [static fn (): mixed => $restart()['reader_recovered_from_stale_end_frame'], true],
    'row page numbers' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'transitions' => [static fn (): mixed => $restart()['transitions'], ['wal>wal>wal>wal>database', 'wal>wal>wal>wal>database', 'wal>wal>database>database>database', 'wal>wal>database>database>database', 'database>database>database>database>database']],
    'page three savepoint changed' => [static fn (): mixed => $restart()['rows'][2]['savepoint_rollback_changed_current'], true],
    'page four savepoint changed' => [static fn (): mixed => $restart()['rows'][3]['savepoint_rollback_changed_current'], true],
    'page one not savepoint changed' => [static fn (): mixed => $restart()['rows'][0]['savepoint_rollback_changed_current'], false],
    'page two before label retained' => [static fn (): mixed => str_contains($restart()['rows'][1]['before_label'], 'retained options'), true],
    'page four recovered label savepoint commit' => [static fn (): mixed => str_contains($restart()['rows'][3]['recovered_label'], 'autoload savepoint commit'), true],
    'page four current label base' => [static fn (): mixed => str_contains($restart()['rows'][3]['current_label'], 'base autoload'), true],
    'page two released label retained' => [static fn (): mixed => str_contains($restart()['rows'][1]['released_next_label'], 'retained options'), true],
    'operation recover reader' => [static fn (): mixed => $restart()['operations'][0]['reason'], 'clamp_reader_to_recovered_committed_wal_prefix'],
    'operation rollback savepoint' => [static fn (): mixed => $restart()['operations'][1]['reason'], 'apply_savepoint_rollback_to_current_wal_prefix'],
    'operation pinned checkpoint' => [static fn (): mixed => $restart()['operations'][2]['action'], 'preserve_wal'],
    'operation released checkpoint' => [static fn (): mixed => $restart()['operations'][3]['action'], 'restart_wal'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-reader-recovery-current-source-next111', $restart()['dependencies'], true), true],
    'dependency transaction recovery' => [static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $restart()['dependencies'], true), true],
    'dependency savepoint checkpoint' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $restart()['dependencies'], true), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate released action' => [static fn (): mixed => $truncate()['released_wal_action'], 'truncate_wal'],
    'truncate released bytes length' => [static fn (): mixed => $truncate()['released_wal_bytes_length'], 0],
    'retained reader original frame' => [static fn (): mixed => $retainedReader()['original_reader_end_frame'], 2],
    'retained reader current frame' => [static fn (): mixed => $retainedReader()['current_reader_end_frame'], 2],
    'retained reader still busy' => [static fn (): mixed => $retainedReader()['pinned_checkpoint_busy'], true],
    'retained reader recovered flag false' => [static fn (): mixed => $retainedReader()['reader_recovered_from_stale_end_frame'], false],
    'single page current source' => [static fn (): mixed => $single()['current_sources'], ['database']],
    'single page released source' => [static fn (): mixed => $single()['released_next_sources'], ['database']],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal savepoint checkpoint reader recovery current source next111 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal savepoint checkpoint reader recovery current source next111 rejects empty savepoint'] = static function (TestRunner $t) use ($makeStack, $walBytes, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan::plan($makeStack(), '', $walBytes, $databaseBytes, [1], 'restart', null, $pageSize));
};

$tests['wal savepoint checkpoint reader recovery current source next111 rejects empty wal bytes'] = static function (TestRunner $t) use ($makeStack, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next111', '', $databaseBytes, [1], 'restart', null, $pageSize));
};

$tests['wal savepoint checkpoint reader recovery current source next111 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $walBytes, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next111', $walBytes, $databaseBytes, [], 'restart', null, $pageSize));
};

$tests['wal savepoint checkpoint reader recovery current source next111 rejects passive mode'] = static function (TestRunner $t) use ($makeStack, $walBytes, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next111', $walBytes, $databaseBytes, [1], 'passive', null, $pageSize));
};

$tests['wal savepoint checkpoint reader recovery current source next111 rejects negative reader'] = static function (TestRunner $t) use ($makeStack, $walBytes, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next111', $walBytes, $databaseBytes, [1], 'restart', -1, $pageSize));
};

$tests['wal savepoint checkpoint reader recovery current source next111 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $walBytes, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next111', $walBytes, $databaseBytes, ['1'], 'restart', null, $pageSize));
};

return $tests;
