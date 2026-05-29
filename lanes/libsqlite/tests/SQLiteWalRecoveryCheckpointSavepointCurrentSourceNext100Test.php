<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalRecoveryCheckpointSavepointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next100 base schema page')
    . $page('next100 base wp_options autoload page')
    . $page('next100 base plugin settings page')
    . $page('next100 base transient cache page')
    . $page('next100 base future page');

$makeWalBytes = static function (array $frames, bool $corruptLast = false) use ($pageSize, $page): string {
    $salt1 = 0x10010064;
    $salt2 = 0x20020064;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 100, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $index => [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $frame = $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
        if ($corruptLast && $index === array_key_last($frames)) {
            $frame = substr_replace($frame, 'X', 24 + 12, 1);
        }
        $bytes .= $frame;
    }

    return $bytes;
};

$frames = [
    [1, 0, 'next100 retained schema draft'],
    [2, 5, 'next100 retained options commit'],
    [3, 0, 'next100 plugin setting savepoint draft'],
    [4, 0, 'next100 transient savepoint draft'],
    [4, 5, 'next100 transient savepoint commit'],
    [5, 0, 'next100 valid uncommitted future page tail'],
    [2, 5, 'next100 corrupt stale options tail'],
];
$walBytes = $makeWalBytes($frames, true);
$cleanWalBytes = $makeWalBytes(array_slice($frames, 0, 5));

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next100');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next100');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 4, true);
    $stack->recordWalFrameWrite(6, 5);

    return $stack;
};

$plan = static fn (string $mode = 'restart', ?int $reader = null, array $pages = [1, 2, 3, 4, 5]): array => SQLiteWalRecoveryCheckpointSavepointCurrentSourceNextPlan::plan(
    $makeStack(),
    'plugin-settings-next100',
    $walBytes,
    $databaseBytes,
    $databasePath,
    $pages,
    $mode,
    $reader,
    $pageSize
);
$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$pinned = static fn (): array => $plan('restart', 2);
$single = static fn (): array => $plan('restart', null, [4]);

$cases = [
    'status recovered' => [static fn (): mixed => $restart()['status'], 'recovered'],
    'reason valid tail before corrupt' => [static fn (): mixed => $restart()['reason'], 'uncommitted_valid_tail_before_corrupt_frame'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next100'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], $pageSize],
    'valid frame count' => [static fn (): mixed => $restart()['valid_frame_count'], 6],
    'committed frame count' => [static fn (): mixed => $restart()['committed_frame_count'], 5],
    'total frame slots' => [static fn (): mixed => $restart()['total_frame_slots'], 7],
    'first invalid frame' => [static fn (): mixed => $restart()['first_invalid_frame'], 7],
    'recovery end offset' => [static fn (): mixed => $restart()['recovery_end_offset'], 32 + (6 * (24 + $pageSize))],
    'committed end offset' => [static fn (): mixed => $restart()['committed_end_offset'], 32 + (5 * (24 + $pageSize))],
    'discarded valid tail count' => [static fn (): mixed => $restart()['discarded_valid_tail_frame_count'], 1],
    'discarded corrupt tail count' => [static fn (): mixed => $restart()['discarded_corrupt_tail_frame_count'], 1],
    'before reader end frame' => [static fn (): mixed => $restart()['before_reader_end_frame'], 6],
    'recovered reader end frame' => [static fn (): mixed => $restart()['recovered_reader_end_frame'], 5],
    'current reader end frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'next reader end frame restart' => [static fn (): mixed => $restart()['next_reader_end_frame'], 0],
    'retained frame count after savepoint' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'savepoint discarded frame count' => [static fn (): mixed => $restart()['savepoint_discarded_frame_count'], 3],
    'wal action restart' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal'],
    'checkpoint reason restart' => [static fn (): mixed => $restart()['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'checkpoint not busy' => [static fn (): mixed => $restart()['checkpoint_busy'], false],
    'before sources' => [static fn (): mixed => $restart()['before_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'recovered sources' => [static fn (): mixed => $restart()['recovered_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'before frame indexes' => [static fn (): mixed => $restart()['before_frame_indexes'], [1, 2, 3, 5, null]],
    'recovered frame indexes' => [static fn (): mixed => $restart()['recovered_frame_indexes'], [1, 2, 3, 5, null]],
    'current frame indexes' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, null, null, null]],
    'next frame indexes' => [static fn (): mixed => $restart()['next_frame_indexes'], [null, null, null, null, null]],
    'tail recovery changed images' => [static fn (): mixed => $restart()['tail_recovery_changed_images'], false],
    'savepoint rollback changed images' => [static fn (): mixed => $restart()['savepoint_rollback_changed_images'], true],
    'current to next images match' => [static fn (): mixed => $restart()['current_to_next_images_match'], true],
    'next uses checkpoint database' => [static fn (): mixed => $restart()['next_uses_checkpoint_database'], true],
    'row page numbers' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'future page tail recovery unchanged after last commit' => [static fn (): mixed => $restart()['rows'][4]['tail_recovery_changed_current'], false],
    'plugin page savepoint rollback changed' => [static fn (): mixed => $restart()['rows'][2]['savepoint_rollback_changed_current'], true],
    'transient page savepoint rollback changed' => [static fn (): mixed => $restart()['rows'][3]['savepoint_rollback_changed_current'], true],
    'schema checkpoint unchanged' => [static fn (): mixed => $restart()['rows'][0]['checkpoint_changed_next'], false],
    'options checkpoint unchanged' => [static fn (): mixed => $restart()['rows'][1]['checkpoint_changed_next'], false],
    'before label excludes uncommitted tail after last commit' => [static fn (): mixed => str_contains($restart()['rows'][4]['before_label'], 'base future'), true],
    'recovered label excludes uncommitted tail' => [static fn (): mixed => str_contains($restart()['rows'][4]['recovered_label'], 'base future'), true],
    'current label base plugin' => [static fn (): mixed => str_contains($restart()['rows'][2]['current_label'], 'base plugin'), true],
    'next label retained options' => [static fn (): mixed => str_contains($restart()['rows'][1]['next_label'], 'retained options commit'), true],
    'recovery status detail' => [static fn (): mixed => $restart()['recovery']['status'], 'recovered_committed_prefix'],
    'recovery can checkpoint' => [static fn (): mixed => $restart()['recovery']['can_checkpoint'], true],
    'checkpoint original frame count' => [static fn (): mixed => $restart()['checkpoint']['original_frame_count'], 5],
    'checkpoint current wal bytes length' => [static fn (): mixed => $restart()['checkpoint']['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'checkpoint durable database includes retained schema' => [static fn (): mixed => str_contains($restart()['checkpoint']['current_durable']['database_bytes'], 'retained schema draft'), true],
    'checkpoint durable database excludes savepoint transient' => [static fn (): mixed => str_contains($restart()['checkpoint']['current_durable']['database_bytes'], 'transient savepoint commit'), false],
    'operation recover first' => [static fn (): mixed => $restart()['operations'][0]['reason'], 'recover_committed_wal_prefix_before_savepoint_checkpoint'],
    'operation rollback prefix' => [static fn (): mixed => $restart()['operations'][2]['reason'], 'rollback_savepoint_to_recovered_wal_prefix'],
    'operation restart last' => [static fn (): mixed => $restart()['operations'][5]['reason'], 'restart_wal_after_recovered_savepoint_checkpoint'],
    'dependency next100' => [static fn (): mixed => in_array('sqlite-wal-recovery-checkpoint-savepoint-current-source-next100', $restart()['dependencies'], true), true],
    'dependency transaction recovery' => [static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $restart()['dependencies'], true), true],
    'dependency savepoint checkpoint current' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $restart()['dependencies'], true), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal'],
    'truncate next frame' => [static fn (): mixed => $truncate()['next_reader_end_frame'], 0],
    'truncate operation last' => [static fn (): mixed => $truncate()['operations'][5]['reason'], 'truncate_wal_after_recovered_savepoint_checkpoint'],
    'pinned status busy' => [static fn (): mixed => $pinned()['checkpoint_busy'], true],
    'pinned reason reader blocks reset' => [static fn (): mixed => $pinned()['checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned wal preserved' => [static fn (): mixed => $pinned()['wal_action'], 'preserve_wal'],
    'pinned next keeps wal sources' => [static fn (): mixed => $pinned()['next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'single page current source' => [static fn (): mixed => $single()['current_sources'], ['database']],
    'single page next source' => [static fn (): mixed => $single()['next_sources'], ['database']],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal recovery checkpoint savepoint current source next100 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal recovery checkpoint savepoint current source next100 clean source reports ready'] = static function (TestRunner $t) use ($makeStack, $cleanWalBytes, $databaseBytes, $databasePath, $pageSize): void {
    $clean = SQLiteWalRecoveryCheckpointSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next100', $cleanWalBytes, $databaseBytes, $databasePath, [1], 'restart', null, $pageSize);
    $t->same('ready', $clean['status']);
    $t->same('all_frames_valid', $clean['reason']);
};

$tests['wal recovery checkpoint savepoint current source next100 rejects empty savepoint'] = static function (TestRunner $t) use ($makeStack, $walBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalRecoveryCheckpointSavepointCurrentSourceNextPlan::plan($makeStack(), '', $walBytes, $databaseBytes, $databasePath, [1], 'restart', null, $pageSize));
};

$tests['wal recovery checkpoint savepoint current source next100 rejects empty wal bytes'] = static function (TestRunner $t) use ($makeStack, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalRecoveryCheckpointSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next100', '', $databaseBytes, $databasePath, [1], 'restart', null, $pageSize));
};

$tests['wal recovery checkpoint savepoint current source next100 rejects passive mode'] = static function (TestRunner $t) use ($makeStack, $walBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalRecoveryCheckpointSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next100', $walBytes, $databaseBytes, $databasePath, [1], 'passive', null, $pageSize));
};

$tests['wal recovery checkpoint savepoint current source next100 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $walBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalRecoveryCheckpointSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next100', $walBytes, $databaseBytes, $databasePath, ['1'], 'restart', null, $pageSize));
};

return $tests;
