<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base current source next90')
    . $page('wp options retained base current source next90')
    . $page('wp plugin draft base current source next90')
    . $page('wp autoload retained base current source next90')
    . $page('wp transient draft base current source next90');

$makeWalBytes = static function (int $checkpoint, int $salt1, int $salt2, array $labels) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($labels as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$labels = [
    [1, 0, 'next90 retained schema draft'],
    [2, 5, 'next90 retained options commit'],
    [3, 0, 'next90 plugin settings discarded draft'],
    [4, 0, 'next90 retained autoload draft after savepoint'],
    [4, 5, 'next90 retained autoload commit after savepoint'],
    [5, 5, 'next90 transient discarded commit'],
];
$walBytes = $makeWalBytes(90, 0x90909090, 0x19199090, $labels);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$mutatedLabels = $labels;
$mutatedLabels[3] = [4, 0, 'next90 retained autoload stale same header'];
$mutatedWalBytes = $makeWalBytes(90, 0x90909090, 0x19199090, $mutatedLabels);
$mutatedWal = SQLiteWal::parse($mutatedWalBytes, $pageSize, true);
$shortWalBytes = substr($walBytes, 0, 32 + (5 * (24 + $pageSize)));

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings');
    $stack->recordWalFrameWrite(3, 3);
    $stack->savepoint('autoload-refresh');
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 4, true);
    $stack->savepoint('transient-row');
    $stack->recordWalFrameWrite(6, 5, true);

    return $stack;
};

$plan = static fn (string $mode = 'restart'): array => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointPinnedCurrentSourceNext(
    $makeStack(),
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    $mode,
    6
);

$restart = static fn (): array => $plan('restart');
$truncate = static fn (): array => $plan('truncate');

$cases = [
    'status busy while reader is pinned' => [static fn (): mixed => $restart()['status'], 'busy'],
    'savepoint retained' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'checkpoint busy' => [static fn (): mixed => $restart()['checkpoint_busy'], true],
    'checkpoint reason reader blocks reset' => [static fn (): mixed => $restart()['checkpoint_reason'], 'reader_blocks_wal_reset'],
    'wal action preserves retained wal' => [static fn (): mixed => $restart()['wal_action'], 'preserve_wal'],
    'current source verified' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'current source checkpoint' => [static fn (): mixed => $restart()['current_source']['checkpoint_sequence'], 90],
    'current source salt one' => [static fn (): mixed => $restart()['current_source']['salt1'], 0x90909090],
    'current source salt two' => [static fn (): mixed => $restart()['current_source']['salt2'], 0x19199090],
    'current source page size' => [static fn (): mixed => $restart()['current_source']['page_size'], $pageSize],
    'current source frame count' => [static fn (): mixed => $restart()['current_source']['frame_count'], 6],
    'current source byte length' => [static fn (): mixed => $restart()['current_source']['wal_bytes_length'], strlen($walBytes)],
    'retained source frame count' => [static fn (): mixed => $restart()['retained_source']['frame_count'], 2],
    'retained source byte length' => [static fn (): mixed => $restart()['retained_source']['wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'retained source checkpoint preserved' => [static fn (): mixed => $restart()['retained_source']['checkpoint_sequence'], 90],
    'original reader end frame' => [static fn (): mixed => $restart()['original_reader_end_frame'], 6],
    'current reader end frame clamps to retained prefix' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'next reader end frame restart' => [static fn (): mixed => $restart()['next_reader_end_frame'], 2],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 4],
    'rolled back frame indexes' => [static fn (): mixed => $restart()['rolled_back_frame_indexes'], [3, 4, 5, 6]],
    'rolled back page numbers' => [static fn (): mixed => $restart()['rolled_back_page_numbers'], [3, 4, 5]],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'current source counts wal' => [static fn (): mixed => $restart()['current_source_counts']['wal'], 2],
    'current source counts database' => [static fn (): mixed => $restart()['current_source_counts']['database'], 3],
    'next source counts database' => [static fn (): mixed => $restart()['next_source_counts']['database'], 3],
    'next source counts wal' => [static fn (): mixed => $restart()['next_source_counts']['wal'], 2],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>wal', 'wal>wal>wal', 'wal>database>database', 'wal>database>database', 'wal>database>database']],
    'current uses rollback prefix' => [static fn (): mixed => $restart()['current_uses_rollback_prefix'], true],
    'next does not use checkpoint database while pinned' => [static fn (): mixed => $restart()['next_uses_checkpoint_database'], false],
    'next uses preserved wal' => [static fn (): mixed => $restart()['next_uses_preserved_wal'], true],
    'current and next images match after checkpoint' => [static fn (): mixed => $restart()['images_match'], true],
    'yield count' => [static fn (): mixed => $restart()['yield_count'], 15],
    'row page numbers' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'row current frames' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'current_frame'), [1, 2, null, null, null]],
    'row next frames' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'next_frame'), [1, 2, null, null, null]],
    'rollback changed discarded plugin page' => [static fn (): mixed => $restart()['current_source_rows'][2]['rollback_changed_current'], true],
    'rollback changed discarded autoload page' => [static fn (): mixed => $restart()['current_source_rows'][3]['rollback_changed_current'], true],
    'rollback changed discarded transient page' => [static fn (): mixed => $restart()['current_source_rows'][4]['rollback_changed_current'], true],
    'checkpoint preserves page one image' => [static fn (): mixed => $restart()['current_source_rows'][0]['checkpoint_changed_next'], false],
    'checkpoint preserves page two image' => [static fn (): mixed => $restart()['current_source_rows'][1]['checkpoint_changed_next'], false],
    'current label retained options' => [static fn (): mixed => str_contains($restart()['current_source_rows'][1]['current_label'], 'retained options commit'), true],
    'next label base plugin page' => [static fn (): mixed => str_contains($restart()['current_source_rows'][2]['next_label'], 'plugin draft base'), true],
    'frame rows count' => [static fn (): mixed => count($restart()['frame_source_rows']), 6],
    'frame row indexes' => [static fn (): mixed => array_column($restart()['frame_source_rows'], 'frame_index'), [1, 2, 3, 4, 5, 6]],
    'frame row pages' => [static fn (): mixed => array_column($restart()['frame_source_rows'], 'page_number'), [1, 2, 3, 4, 4, 5]],
    'frame row commit flags' => [static fn (): mixed => array_column($restart()['frame_source_rows'], 'commit_frame'), [false, true, false, false, true, true]],
    'commit frame indexes' => [static fn (): mixed => $restart()['commit_frame_indexes'], [2, 5, 6]],
    'frame source offsets' => [static fn (): mixed => array_column($restart()['frame_source_rows'], 'source_offset'), [32, 568, 1104, 1640, 2176, 2712]],
    'frame source lengths' => [static fn (): mixed => array_unique(array_column($restart()['frame_source_rows'], 'source_length')), [536]],
    'frame rows matched' => [static fn (): mixed => array_unique(array_column($restart()['frame_source_rows'], 'matched_current_wal')), [true]],
    'frame digest length' => [static fn (): mixed => strlen($restart()['frame_source_rows'][3]['image_sha256']), 64],
    'frame digest matches page image' => [static fn (): mixed => $restart()['frame_source_rows'][3]['image_sha256'], hash('sha256', $page('next90 retained autoload draft after savepoint'))],
    'dependency next90' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-savepoint-reader-current-source-next90', $restart()['dependencies'], true), true],
    'dependency next85 retained' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-savepoint-current-source-next85', $restart()['dependencies'], true), true],
    'truncate mode normalized' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'preserve_wal'],
    'truncate next reader end frame' => [static fn (): mixed => $truncate()['next_reader_end_frame'], 2],
    'truncate keeps frame source rows' => [static fn (): mixed => count($truncate()['frame_source_rows']), 6],
    'truncate next keeps retained wal while pinned' => [static fn (): mixed => $truncate()['next_uses_checkpoint_database'], false],
    'short source rejected' => [static function () use ($makeStack, $wal, $shortWalBytes, $databaseBytes): string {
        try {
            SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointPinnedCurrentSourceNext($makeStack(), 'plugin-settings', $wal, $shortWalBytes, $databaseBytes, [1]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    }, 'SQLite WAL savepoint checkpoint current source frame count mismatch'],
    'mutated source against current wal rejected' => [static function () use ($makeStack, $wal, $mutatedWalBytes, $databaseBytes): string {
        try {
            SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointPinnedCurrentSourceNext($makeStack(), 'plugin-settings', $wal, $mutatedWalBytes, $databaseBytes, [1]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    }, 'SQLite WAL savepoint checkpoint current source frame 4 mismatch'],
    'current bytes against stale parsed wal rejected' => [static function () use ($makeStack, $mutatedWal, $walBytes, $databaseBytes): string {
        try {
            SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointPinnedCurrentSourceNext($makeStack(), 'plugin-settings', $mutatedWal, $walBytes, $databaseBytes, [1]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    }, 'SQLite WAL savepoint checkpoint current source frame 4 mismatch'],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal checkpoint savepoint reader current source next90 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

return $tests;
