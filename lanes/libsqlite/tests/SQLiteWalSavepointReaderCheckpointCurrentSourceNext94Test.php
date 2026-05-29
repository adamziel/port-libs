<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next94 schema base before savepoint reader')
    . $page('next94 option base before savepoint reader')
    . $page('next94 plugin base before savepoint reader')
    . $page('next94 autoload base before savepoint reader')
    . $page('next94 transient base before savepoint reader');

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
    [1, 0, 'next94 schema retained draft'],
    [2, 5, 'next94 option retained commit'],
    [3, 0, 'next94 plugin rolled back draft'],
    [4, 0, 'next94 autoload rolled back draft'],
    [4, 5, 'next94 autoload rolled back commit'],
    [5, 5, 'next94 transient rolled back commit'],
];
$walBytes = $makeWalBytes(94, 0x94949494, 0x29299494, $labels);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('reader-visible');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 4, true);
    $stack->recordWalFrameWrite(6, 5, true);

    return $stack;
};

$plan = static fn (string $mode = 'restart'): array => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReleaseCurrentSourceNext(
    $makeStack(),
    'reader-visible',
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
    'restart status' => [static fn (): mixed => $restart()['status'], 'reader-release-checkpoint-ready-current-source-next94'],
    'restart savepoint' => [static fn (): mixed => $restart()['savepoint'], 'reader-visible'],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart current source verified' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'restart pinned status busy' => [static fn (): mixed => $restart()['pinned_status'], 'busy'],
    'restart pinned checkpoint busy' => [static fn (): mixed => $restart()['pinned_checkpoint_busy'], true],
    'restart pinned reason' => [static fn (): mixed => $restart()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'restart pinned action' => [static fn (): mixed => $restart()['pinned_wal_action'], 'preserve_wal'],
    'restart release not busy' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'restart release reason' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'restart release action' => [static fn (): mixed => $restart()['released_wal_action'], 'restart_wal'],
    'restart original reader frame' => [static fn (): mixed => $restart()['original_reader_end_frame'], 6],
    'restart current reader frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart pinned next frame' => [static fn (): mixed => $restart()['pinned_next_reader_end_frame'], 2],
    'restart released next frame' => [static fn (): mixed => $restart()['released_next_reader_end_frame'], 0],
    'restart retained count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'restart discarded count' => [static fn (): mixed => $restart()['discarded_frame_count'], 4],
    'restart current source checkpoint' => [static fn (): mixed => $restart()['current_source']['checkpoint_sequence'], 94],
    'restart current source frame count' => [static fn (): mixed => $restart()['current_source']['frame_count'], 6],
    'restart current source length' => [static fn (): mixed => $restart()['current_source']['wal_bytes_length'], strlen($walBytes)],
    'restart retained source frame count' => [static fn (): mixed => $restart()['retained_source']['frame_count'], 2],
    'restart retained source length' => [static fn (): mixed => $restart()['retained_source']['wal_bytes_length'], 32 + 2 * (24 + $pageSize)],
    'restart released source kind' => [static fn (): mixed => $restart()['released_source']['kind'], 'restart_wal'],
    'restart released source length' => [static fn (): mixed => $restart()['released_source']['wal_bytes_length'], 32],
    'restart released sequence increments' => [static fn (): mixed => $restart()['released_source']['checkpoint_sequence'], 95],
    'restart released database length' => [static fn (): mixed => $restart()['released_source']['database_bytes_length'], strlen($databaseBytes)],
    'restart current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'restart pinned next sources' => [static fn (): mixed => $restart()['pinned_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'restart released next sources' => [static fn (): mixed => $restart()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'restart transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>database', 'wal>wal>database', 'database>database>database', 'database>database>database', 'database>database>database']],
    'restart released source count' => [static fn (): mixed => $restart()['released_next_source_counts']['database'], 5],
    'restart pinned preserves current' => [static fn (): mixed => $restart()['current_reader_preserved_by_pinned_checkpoint'], true],
    'restart released database source' => [static fn (): mixed => $restart()['released_reader_uses_checkpoint_database'], true],
    'restart released reset source' => [static fn (): mixed => $restart()['released_reader_uses_reset_source'], true],
    'restart release unblocked checkpoint' => [static fn (): mixed => $restart()['reader_release_unblocked_checkpoint'], true],
    'restart rolled back pages' => [static fn (): mixed => $restart()['rolled_back_page_numbers'], [3, 4, 5]],
    'restart rolled back frames' => [static fn (): mixed => $restart()['rolled_back_frame_indexes'], [3, 4, 5, 6]],
    'restart yield count' => [static fn (): mixed => $restart()['yield_count'], 20],
    'restart row pages' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'restart row current frames' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'current_frame'), [1, 2, null, null, null]],
    'restart row pinned frames' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'pinned_next_frame'), [1, 2, null, null, null]],
    'restart row released frames' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'released_next_frame'), [null, null, null, null, null]],
    'restart row pinned matches current' => [static fn (): mixed => array_unique(array_column($restart()['current_source_rows'], 'pinned_matches_current')), [true]],
    'restart released row one kept checkpoint image' => [static fn (): mixed => $restart()['current_source_rows'][0]['released_changed_from_pinned'], false],
    'restart released row two kept checkpoint image' => [static fn (): mixed => $restart()['current_source_rows'][1]['released_changed_from_pinned'], false],
    'restart released row three unchanged base' => [static fn (): mixed => $restart()['current_source_rows'][2]['released_changed_from_pinned'], false],
    'restart released label checkpoint option' => [static fn (): mixed => str_contains($restart()['current_source_rows'][1]['released_label'], 'option retained commit'), true],
    'restart released label base plugin' => [static fn (): mixed => str_contains($restart()['current_source_rows'][2]['released_label'], 'plugin base'), true],
    'restart dependency next94' => [static fn (): mixed => in_array('sqlite-wal-savepoint-reader-checkpoint-current-source-next94', $restart()['dependencies'], true), true],
    'restart dependency next90' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-savepoint-reader-current-source-next90', $restart()['dependencies'], true), true],
    'restart dependency durable sidecar' => [static fn (): mixed => in_array('durable-sidecar-write', $restart()['dependencies'], true), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate status' => [static fn (): mixed => $truncate()['status'], 'reader-release-checkpoint-ready-current-source-next94'],
    'truncate release action' => [static fn (): mixed => $truncate()['released_wal_action'], 'truncate_wal'],
    'truncate released source kind' => [static fn (): mixed => $truncate()['released_source']['kind'], 'truncate_wal'],
    'truncate released source length' => [static fn (): mixed => $truncate()['released_source']['wal_bytes_length'], 0],
    'truncate released source checkpoint absent' => [static fn (): mixed => $truncate()['released_source']['checkpoint_sequence'], null],
    'truncate released next frame' => [static fn (): mixed => $truncate()['released_next_reader_end_frame'], 0],
    'truncate released uses database' => [static fn (): mixed => $truncate()['released_reader_uses_checkpoint_database'], true],
    'truncate released sources' => [static fn (): mixed => $truncate()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'truncate row transition option' => [static fn (): mixed => $truncate()['current_source_rows'][1]['source_transition'], 'wal>wal>database'],
    'truncate release unblocks checkpoint' => [static fn (): mixed => $truncate()['reader_release_unblocked_checkpoint'], true],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal savepoint reader checkpoint current source next94 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal savepoint reader checkpoint current source next94 rejects passive mode'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReleaseCurrentSourceNext($makeStack(), 'reader-visible', $wal, $walBytes, $databaseBytes, [1], 'passive'));
};

$tests['wal savepoint reader checkpoint current source next94 rejects empty page list'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReleaseCurrentSourceNext($makeStack(), 'reader-visible', $wal, $walBytes, $databaseBytes, []));
};

$tests['wal savepoint reader checkpoint current source next94 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReleaseCurrentSourceNext($makeStack(), 'reader-visible', $wal, $walBytes, $databaseBytes, ['1']));
};

$tests['wal savepoint reader checkpoint current source next94 rejects stale source'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $mutated = substr_replace($walBytes, 'X', 32 + 24 + 8, 1);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReleaseCurrentSourceNext($makeStack(), 'reader-visible', $wal, $mutated, $databaseBytes, [1]));
};

return $tests;
