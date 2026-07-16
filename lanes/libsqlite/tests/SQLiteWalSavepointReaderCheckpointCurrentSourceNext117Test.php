<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next117 schema base')
    . $page('next117 options base')
    . $page('next117 plugin base')
    . $page('next117 autoload base')
    . $page('next117 transient base');

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x11711701;
    $salt2 = 0x11711702;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 117, $salt1, $salt2);
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
    [1, 0, 'next117 schema retained draft'],
    [2, 5, 'next117 options retained commit'],
    [3, 0, 'next117 plugin stale reader draft'],
    [4, 0, 'next117 autoload stale reader draft'],
    [4, 5, 'next117 autoload stale reader commit'],
    [5, 5, 'next117 transient stale reader commit'],
    [2, 5, 'next117 options stale reader tail'],
];
$walBytes = $makeWalBytes($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$retainedReaderBytes = $makeWalBytes(array_slice($frames, 0, 2));

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next117');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next117');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 4, true);
    $stack->recordWalFrameWrite(6, 5, true);
    $stack->recordWalFrameWrite(7, 2, true);

    return $stack;
};

$plan = static function (string $mode = 'restart', ?int $reader = 7, ?string $readerBytes = null, array $pages = [1, 2, 3, 4, 5]) use ($makeStack, $wal, $walBytes, $databaseBytes): array {
    return SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan::plan(
        $makeStack(),
        'plugin-settings-next117',
        $wal,
        $walBytes,
        $readerBytes ?? $walBytes,
        $databaseBytes,
        $pages,
        $mode,
        $reader
    );
};
$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$retainedReader = static fn (): array => $plan('restart', 2, $retainedReaderBytes);
$single = static fn (): array => $plan('restart', 7, $walBytes, [2]);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'reader-stale-source-checkpoint-current-prefix-next117'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next117'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], $pageSize],
    'reader frame' => [static fn (): mixed => $restart()['reader_end_frame'], 7],
    'current reader frame clamped' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 5],
    'discarded frame indexes' => [static fn (): mixed => $restart()['discarded_frame_indexes'], [3, 4, 5, 6, 7]],
    'stale reader frame indexes' => [static fn (): mixed => $restart()['stale_reader_frame_indexes'], [3, 4, 5, 6, 7]],
    'stale reader page numbers' => [static fn (): mixed => $restart()['stale_reader_page_numbers'], [3, 4, 5, 2]],
    'reader source mismatch' => [static fn (): mixed => $restart()['reader_source_matches_current'], false],
    'current source verified' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'reader sha length' => [static fn (): mixed => strlen($restart()['reader_wal_sha256']), 64],
    'retained sha length' => [static fn (): mixed => strlen($restart()['retained_wal_sha256']), 64],
    'reader and retained sha differ' => [static fn (): mixed => $restart()['reader_wal_sha256'] !== $restart()['retained_wal_sha256'], true],
    'reader bytes length' => [static fn (): mixed => $restart()['reader_wal_bytes_length'], strlen($walBytes)],
    'retained bytes length' => [static fn (): mixed => $restart()['retained_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'pinned checkpoint busy' => [static fn (): mixed => $restart()['pinned_checkpoint_busy'], true],
    'pinned checkpoint reason' => [static fn (): mixed => $restart()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned wal action' => [static fn (): mixed => $restart()['pinned_wal_action'], 'preserve_wal'],
    'released checkpoint ready' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'released checkpoint reason' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released wal action' => [static fn (): mixed => $restart()['released_wal_action'], 'restart_wal'],
    'pinned wal bytes length' => [static fn (): mixed => $restart()['pinned_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released restart wal bytes length' => [static fn (): mixed => $restart()['released_wal_bytes_length'], 32],
    'reader sources' => [static fn (): mixed => $restart()['reader_sources'], ['wal', 'wal', 'wal', 'wal', 'wal']],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'pinned next sources' => [static fn (): mixed => $restart()['pinned_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'released next sources' => [static fn (): mixed => $restart()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'reader wal count' => [static fn (): mixed => $restart()['reader_source_counts']['wal'], 5],
    'current wal count' => [static fn (): mixed => $restart()['current_source_counts']['wal'], 2],
    'current database count' => [static fn (): mixed => $restart()['current_source_counts']['database'], 3],
    'pinned wal count' => [static fn (): mixed => $restart()['pinned_next_source_counts']['wal'], 2],
    'released database count' => [static fn (): mixed => $restart()['released_next_source_counts']['database'], 5],
    'row count' => [static fn (): mixed => count($restart()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'reader frames' => [static fn (): mixed => array_column($restart()['rows'], 'reader_frame'), [1, 7, 3, 5, 6]],
    'current frames' => [static fn (): mixed => array_column($restart()['rows'], 'current_frame'), [1, 2, null, null, null]],
    'pinned next frames' => [static fn (): mixed => array_column($restart()['rows'], 'pinned_next_frame'), [1, 2, null, null, null]],
    'released next frames' => [static fn (): mixed => array_column($restart()['rows'], 'released_next_frame'), [null, null, null, null, null]],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>wal>database', 'wal>wal>wal>database', 'wal>database>database>database', 'wal>database>database>database', 'wal>database>database>database']],
    'stale pages' => [static fn (): mixed => $restart()['stale_reader_tail_pages'], [2, 3, 4, 5]],
    'page two stale ignored' => [static fn (): mixed => $restart()['rows'][1]['stale_reader_tail_ignored'], true],
    'page three stale ignored' => [static fn (): mixed => $restart()['rows'][2]['stale_reader_tail_ignored'], true],
    'page one retained matches' => [static fn (): mixed => $restart()['rows'][0]['stale_reader_tail_ignored'], false],
    'checkpoint used retained prefix' => [static fn (): mixed => $restart()['checkpoint_used_retained_prefix'], true],
    'pinned checkpoint preserves images' => [static fn (): mixed => $restart()['pinned_checkpoint_preserved_images'], true],
    'released checkpoint preserves images' => [static fn (): mixed => $restart()['released_checkpoint_preserved_images'], true],
    'released uses checkpoint database' => [static fn (): mixed => $restart()['released_reader_uses_checkpoint_database'], true],
    'reader release unblocked checkpoint' => [static fn (): mixed => $restart()['reader_release_unblocked_checkpoint'], true],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'reader label stale option' => [static fn (): mixed => str_contains($restart()['rows'][1]['reader_label'], 'options stale reader tail'), true],
    'current label retained option' => [static fn (): mixed => str_contains($restart()['rows'][1]['current_label'], 'options retained commit'), true],
    'released label base autoload' => [static fn (): mixed => str_contains($restart()['rows'][3]['released_next_label'], 'autoload base'), true],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-reader-checkpoint-current-source-next117', $restart()['dependencies'], true), true],
    'dependency durable sidecar write' => [static fn (): mixed => in_array('durable-sidecar-write', $restart()['dependencies'], true), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate released action' => [static fn (): mixed => $truncate()['released_wal_action'], 'truncate_wal'],
    'truncate released wal bytes' => [static fn (): mixed => $truncate()['released_wal_bytes_length'], 0],
    'retained reader matches current' => [static fn (): mixed => $retainedReader()['reader_source_matches_current'], true],
    'retained reader no stale frames' => [static fn (): mixed => $retainedReader()['stale_reader_frame_indexes'], []],
    'retained reader no stale pages' => [static fn (): mixed => $retainedReader()['stale_reader_tail_pages'], []],
    'single page stale option' => [static fn (): mixed => $single()['stale_reader_tail_pages'], [2]],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal savepoint reader checkpoint current source next117 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal savepoint reader checkpoint current source next117 rejects empty savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan::plan($makeStack(), '', $wal, $walBytes, $walBytes, $databaseBytes, [1]));
};

$tests['wal savepoint reader checkpoint current source next117 rejects empty wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next117', $wal, '', $walBytes, $databaseBytes, [1]));
};

$tests['wal savepoint reader checkpoint current source next117 rejects empty reader wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next117', $wal, $walBytes, '', $databaseBytes, [1]));
};

$tests['wal savepoint reader checkpoint current source next117 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next117', $wal, $walBytes, $walBytes, $databaseBytes, []));
};

$tests['wal savepoint reader checkpoint current source next117 rejects invalid mode'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next117', $wal, $walBytes, $walBytes, $databaseBytes, [1], 'passive'));
};

$tests['wal savepoint reader checkpoint current source next117 rejects negative reader frame'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next117', $wal, $walBytes, $walBytes, $databaseBytes, [1], 'restart', -1));
};

$tests['wal savepoint reader checkpoint current source next117 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next117', $wal, $walBytes, $walBytes, $databaseBytes, ['1']));
};

return $tests;
