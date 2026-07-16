<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next99 schema base')
    . $page('next99 options base')
    . $page('next99 plugin base')
    . $page('next99 autoload base')
    . $page('next99 transient base');

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x99999901;
    $salt2 = 0x99999902;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 99, $salt1, $salt2);
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
    [1, 0, 'next99 schema draft retained'],
    [2, 5, 'next99 options commit retained'],
    [3, 0, 'next99 plugin draft rolled back'],
    [4, 0, 'next99 autoload draft rolled back'],
    [4, 5, 'next99 autoload commit rolled back'],
    [5, 5, 'next99 transient commit rolled back'],
    [2, 5, 'next99 options tail rolled back'],
];
$walBytes = $makeWalBytes($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$mutatedWalBytes = $makeWalBytes([
    [1, 0, 'next99 schema draft retained'],
    [2, 5, 'next99 options commit retained'],
    [3, 0, 'next99 plugin draft rolled back'],
    [4, 0, 'next99 autoload source mismatch'],
    [4, 5, 'next99 autoload commit rolled back'],
    [5, 5, 'next99 transient commit rolled back'],
    [2, 5, 'next99 options tail rolled back'],
]);
$mutatedWal = SQLiteWal::parse($mutatedWalBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next99');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings');
    $stack->recordWalFrameWrite(3, 3);
    $stack->savepoint('autoload-refresh');
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 4, true);
    $stack->savepoint('transient-row');
    $stack->recordWalFrameWrite(6, 5, true);
    $stack->recordWalFrameWrite(7, 2, true);

    return $stack;
};

$plan = static fn (string $mode = 'restart'): array => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointRecoveryCurrentSourceNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    $mode,
    7
);
$restart = static fn (): array => $plan('restart');
$truncate = static fn (): array => $plan('truncate');

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'reader-release-checkpoint-current-source-next99'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings'],
    'pinned checkpoint busy' => [static fn (): mixed => $restart()['pinned_checkpoint_busy'], true],
    'pinned checkpoint reason' => [static fn (): mixed => $restart()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned wal action' => [static fn (): mixed => $restart()['pinned_wal_action'], 'preserve_wal'],
    'released checkpoint ready' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'released checkpoint reason' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released wal action restart' => [static fn (): mixed => $restart()['released_wal_action'], 'restart_wal'],
    'original reader frame' => [static fn (): mixed => $restart()['original_reader_end_frame'], 7],
    'current reader clamps to retained frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'pinned next reader frame' => [static fn (): mixed => $restart()['pinned_next_reader_end_frame'], 2],
    'released next reader frame restart' => [static fn (): mixed => $restart()['released_next_reader_end_frame'], 0],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 5],
    'current source frame count' => [static fn (): mixed => $restart()['current_source']['frame_count'], 7],
    'current source bytes length' => [static fn (): mixed => $restart()['current_source']['wal_bytes_length'], strlen($walBytes)],
    'current source checkpoint sequence' => [static fn (): mixed => $restart()['current_source']['checkpoint_sequence'], 99],
    'current source salt one' => [static fn (): mixed => $restart()['current_source']['salt1'], 0x99999901],
    'current source salt two' => [static fn (): mixed => $restart()['current_source']['salt2'], 0x99999902],
    'current source sha length' => [static fn (): mixed => strlen($restart()['current_source']['wal_sha256']), 64],
    'retained source frame count' => [static fn (): mixed => $restart()['retained_source']['frame_count'], 2],
    'retained source bytes length' => [static fn (): mixed => $restart()['retained_source']['wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'retained source sha length' => [static fn (): mixed => strlen($restart()['retained_source']['wal_sha256']), 64],
    'pinned next source kind' => [static fn (): mixed => $restart()['pinned_next_source']['kind'], 'preserve_wal'],
    'pinned next source frame count' => [static fn (): mixed => $restart()['pinned_next_source']['frame_count'], 2],
    'pinned next source wal bytes' => [static fn (): mixed => $restart()['pinned_next_source']['wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released source kind' => [static fn (): mixed => $restart()['released_source']['kind'], 'restart_wal'],
    'released source wal bytes' => [static fn (): mixed => $restart()['released_source']['wal_bytes_length'], 32],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'pinned next sources' => [static fn (): mixed => $restart()['pinned_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'released next sources' => [static fn (): mixed => $restart()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'pinned next wal count' => [static fn (): mixed => $restart()['pinned_next_source_counts']['wal'], 2],
    'pinned next database count' => [static fn (): mixed => $restart()['pinned_next_source_counts']['database'], 3],
    'released database count' => [static fn (): mixed => $restart()['released_next_source_counts']['database'], 5],
    'rolled back page numbers' => [static fn (): mixed => $restart()['rolled_back_page_numbers'], [2, 3, 4, 5]],
    'rolled back frame indexes' => [static fn (): mixed => $restart()['rolled_back_frame_indexes'], [3, 4, 5, 6, 7]],
    'current uses rollback prefix' => [static fn (): mixed => $restart()['current_uses_rollback_prefix'], true],
    'pinned reader preserves wal' => [static fn (): mixed => $restart()['pinned_reader_preserves_wal'], true],
    'released uses checkpoint database' => [static fn (): mixed => $restart()['released_reader_uses_checkpoint_database'], true],
    'reader release unblocked checkpoint' => [static fn (): mixed => $restart()['reader_release_unblocked_checkpoint'], true],
    'pinned images match' => [static fn (): mixed => $restart()['pinned_images_match'], true],
    'released images match pinned current' => [static fn (): mixed => $restart()['released_images_match'], true],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'yield count includes frames' => [static fn (): mixed => $restart()['yield_count'], 27],
    'frame source rows count' => [static fn (): mixed => count($restart()['frame_source_rows']), 7],
    'frame source indexes' => [static fn (): mixed => array_column($restart()['frame_source_rows'], 'frame_index'), [1, 2, 3, 4, 5, 6, 7]],
    'frame source pages' => [static fn (): mixed => array_column($restart()['frame_source_rows'], 'page_number'), [1, 2, 3, 4, 4, 5, 2]],
    'frame source offsets' => [static fn (): mixed => array_column($restart()['frame_source_rows'], 'source_offset'), [32, 568, 1104, 1640, 2176, 2712, 3248]],
    'commit frame indexes' => [static fn (): mixed => $restart()['commit_frame_indexes'], [2, 5, 6, 7]],
    'row count' => [static fn (): mixed => count($restart()['current_source_rows']), 5],
    'row page numbers' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'row current frames' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'current_frame'), [1, 2, null, null, null]],
    'row pinned next frames' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'pinned_next_frame'), [1, 2, null, null, null]],
    'row released next frames' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'released_next_frame'), [null, null, null, null, null]],
    'row transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>wal>database', 'wal>wal>wal>database', 'wal>database>database>database', 'wal>database>database>database', 'wal>database>database>database']],
    'rollback changed option tail page' => [static fn (): mixed => $restart()['current_source_rows'][1]['rollback_changed_current'], true],
    'rollback changed plugin page' => [static fn (): mixed => $restart()['current_source_rows'][2]['rollback_changed_current'], true],
    'pinned checkpoint does not change plugin page' => [static fn (): mixed => $restart()['current_source_rows'][2]['pinned_checkpoint_changed_next'], false],
    'released checkpoint changes schema source' => [static fn (): mixed => $restart()['current_source_rows'][0]['released_changed_from_pinned'], false],
    'current label retained option' => [static fn (): mixed => str_contains($restart()['current_source_rows'][1]['current_label'], 'options commit retained'), true],
    'released label option retained' => [static fn (): mixed => str_contains($restart()['current_source_rows'][1]['released_next_label'], 'options commit retained'), true],
    'dependency next99' => [static fn (): mixed => in_array('sqlite-wal-savepoint-reader-checkpoint-current-source-next99', $restart()['dependencies'], true), true],
    'dependency next94' => [static fn (): mixed => in_array('sqlite-wal-savepoint-reader-checkpoint-current-source-next94', $restart()['dependencies'], true), true],
    'dependency next90' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-savepoint-reader-current-source-next90', $restart()['dependencies'], true), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate released wal action' => [static fn (): mixed => $truncate()['released_wal_action'], 'truncate_wal'],
    'truncate released source kind' => [static fn (): mixed => $truncate()['released_source']['kind'], 'truncate_wal'],
    'truncate released wal bytes' => [static fn (): mixed => $truncate()['released_source']['wal_bytes_length'], 0],
    'truncate released sources' => [static fn (): mixed => $truncate()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'invalid mode rejected' => [static function () use ($makeStack, $wal, $walBytes, $databaseBytes): string {
        try {
            SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointRecoveryCurrentSourceNext($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, [1], 'passive');
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    }, 'SQLite WAL savepoint reader checkpoint current-source next99 requires restart or truncate mode'],
    'empty page list rejected' => [static function () use ($makeStack, $wal, $walBytes, $databaseBytes): string {
        try {
            SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointRecoveryCurrentSourceNext($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, []);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    }, 'SQLite WAL savepoint reader checkpoint current-source next99 requires at least one page number'],
    'mutated source rejected' => [static function () use ($makeStack, $wal, $mutatedWalBytes, $databaseBytes): string {
        try {
            SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointRecoveryCurrentSourceNext($makeStack(), 'plugin-settings', $wal, $mutatedWalBytes, $databaseBytes, [1]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    }, 'SQLite WAL savepoint checkpoint current source frame 4 mismatch'],
    'stale parsed wal rejected' => [static function () use ($makeStack, $mutatedWal, $walBytes, $databaseBytes): string {
        try {
            SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointRecoveryCurrentSourceNext($makeStack(), 'plugin-settings', $mutatedWal, $walBytes, $databaseBytes, [1]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    }, 'SQLite WAL savepoint checkpoint current source frame 4 mismatch'],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal savepoint reader checkpoint current source next99 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

return $tests;
