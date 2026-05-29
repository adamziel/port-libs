<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next123 schema base')
    . $page('next123 options base')
    . $page('next123 autoload base')
    . $page('next123 plugin base')
    . $page('next123 transient base');

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x12312301;
    $salt2 = 0x12312302;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 123, $salt1, $salt2);
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
    [1, 0, 'next123 schema retained draft'],
    [2, 5, 'next123 options retained commit'],
    [3, 0, 'next123 autoload stale draft'],
    [4, 0, 'next123 plugin stale draft'],
    [4, 5, 'next123 plugin stale commit'],
    [5, 5, 'next123 transient stale commit'],
    [2, 5, 'next123 options stale tail'],
];
$walBytes = $makeWalBytes($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$retainedReaderBytes = $makeWalBytes(array_slice($frames, 0, 2));

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next123');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next123');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 4, true);
    $stack->recordWalFrameWrite(6, 5, true);
    $stack->recordWalFrameWrite(7, 2, true);

    return $stack;
};

$plan = static function (?int $reader = 7, ?string $readerBytes = null, array $pages = [1, 2, 3, 4, 5]) use ($makeStack, $wal, $walBytes, $databaseBytes): array {
    return SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateWithStaleReaderPlan(
        $makeStack(),
        'plugin-settings-next123',
        $wal,
        $walBytes,
        $readerBytes ?? $walBytes,
        $databaseBytes,
        $pages,
        $reader
    );
};
$stale = static fn (): array => $plan();
$retained = static fn (): array => $plan(2, $retainedReaderBytes);
$single = static fn (): array => $plan(7, $walBytes, [2]);

$cases = [
    'status' => [static fn (): mixed => $stale()['status'], 'reader-checkpoint-savepoint-truncate-current-source-next123'],
    'mode' => [static fn (): mixed => $stale()['mode'], 'truncate'],
    'savepoint' => [static fn (): mixed => $stale()['savepoint'], 'plugin-settings-next123'],
    'page size' => [static fn (): mixed => $stale()['page_size'], $pageSize],
    'reader end frame' => [static fn (): mixed => $stale()['reader_end_frame'], 7],
    'current reader clamps to retained' => [static fn (): mixed => $stale()['current_reader_end_frame'], 2],
    'retained frames' => [static fn (): mixed => $stale()['retained_frame_count'], 2],
    'discarded frames' => [static fn (): mixed => $stale()['discarded_frame_count'], 5],
    'discarded frame indexes' => [static fn (): mixed => $stale()['discarded_frame_indexes'], [3, 4, 5, 6, 7]],
    'stale frame indexes' => [static fn (): mixed => $stale()['stale_reader_frame_indexes'], [3, 4, 5, 6, 7]],
    'stale page numbers' => [static fn (): mixed => $stale()['stale_reader_page_numbers'], [3, 4, 5, 2]],
    'stale source offsets' => [static fn (): mixed => array_column($stale()['stale_reader_frames'], 'source_offset'), [1104, 1640, 2176, 2712, 3248]],
    'stale source lengths' => [static fn (): mixed => array_column($stale()['stale_reader_frames'], 'source_length'), [536, 536, 536, 536, 536]],
    'stale commit flags' => [static fn (): mixed => array_column($stale()['stale_reader_frames'], 'commit_frame'), [false, false, true, true, true]],
    'current source verified' => [static fn (): mixed => $stale()['current_source_verified'], true],
    'reader source differs from current prefix' => [static fn (): mixed => $stale()['reader_source_matches_current'], false],
    'reader sha length' => [static fn (): mixed => strlen($stale()['reader_wal_sha256']), 64],
    'retained sha length' => [static fn (): mixed => strlen($stale()['retained_wal_sha256']), 64],
    'current sha length' => [static fn (): mixed => strlen($stale()['current_wal_sha256']), 64],
    'current source frame count' => [static fn (): mixed => $stale()['current_source']['frame_count'], 7],
    'reader source frame count' => [static fn (): mixed => $stale()['reader_source']['frame_count'], 7],
    'retained source frame count' => [static fn (): mixed => $stale()['retained_source']['frame_count'], 2],
    'current source bytes length' => [static fn (): mixed => $stale()['current_source']['wal_bytes_length'], strlen($walBytes)],
    'retained source bytes length' => [static fn (): mixed => $stale()['retained_source']['wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'checkpoint sequence' => [static fn (): mixed => $stale()['current_source']['checkpoint_sequence'], 123],
    'salt one' => [static fn (): mixed => $stale()['current_source']['salt1'], 0x12312301],
    'salt two' => [static fn (): mixed => $stale()['current_source']['salt2'], 0x12312302],
    'pinned checkpoint busy' => [static fn (): mixed => $stale()['pinned_checkpoint_busy'], true],
    'pinned reason' => [static fn (): mixed => $stale()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned action' => [static fn (): mixed => $stale()['pinned_wal_action'], 'preserve_wal'],
    'pinned wal bytes' => [static fn (): mixed => $stale()['pinned_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released checkpoint ready' => [static fn (): mixed => $stale()['released_checkpoint_busy'], false],
    'released reason' => [static fn (): mixed => $stale()['released_checkpoint_reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'released action' => [static fn (): mixed => $stale()['released_wal_action'], 'truncate_wal'],
    'released wal bytes' => [static fn (): mixed => $stale()['released_wal_bytes_length'], 0],
    'released database bytes' => [static fn (): mixed => $stale()['released_database_bytes_length'], strlen($databaseBytes)],
    'reader sources' => [static fn (): mixed => $stale()['reader_sources'], ['wal', 'wal', 'wal', 'wal', 'wal']],
    'current sources' => [static fn (): mixed => $stale()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'pinned sources' => [static fn (): mixed => $stale()['pinned_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'released sources' => [static fn (): mixed => $stale()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'reader wal count' => [static fn (): mixed => $stale()['reader_source_counts']['wal'], 5],
    'current wal count' => [static fn (): mixed => $stale()['current_source_counts']['wal'], 2],
    'current database count' => [static fn (): mixed => $stale()['current_source_counts']['database'], 3],
    'released database count' => [static fn (): mixed => $stale()['released_next_source_counts']['database'], 5],
    'row count' => [static fn (): mixed => count($stale()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($stale()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'reader frames' => [static fn (): mixed => array_column($stale()['rows'], 'reader_frame'), [1, 7, 3, 5, 6]],
    'current frames' => [static fn (): mixed => array_column($stale()['rows'], 'current_frame'), [1, 2, null, null, null]],
    'pinned frames' => [static fn (): mixed => array_column($stale()['rows'], 'pinned_next_frame'), [1, 2, null, null, null]],
    'released frames' => [static fn (): mixed => array_column($stale()['rows'], 'released_next_frame'), [null, null, null, null, null]],
    'transitions' => [static fn (): mixed => $stale()['source_transitions'], ['wal>wal>wal>database', 'wal>wal>wal>database', 'wal>database>database>database', 'wal>database>database>database', 'wal>database>database>database']],
    'stale tail pages' => [static fn (): mixed => $stale()['stale_reader_tail_pages'], [2, 3, 4, 5]],
    'rollback changed page two' => [static fn (): mixed => $stale()['rows'][1]['rollback_changed_current'], true],
    'rollback left page one' => [static fn (): mixed => $stale()['rows'][0]['rollback_changed_current'], false],
    'pinned preserves images' => [static fn (): mixed => $stale()['pinned_checkpoint_preserved_images'], true],
    'released preserves images' => [static fn (): mixed => $stale()['released_checkpoint_preserved_images'], true],
    'released uses database' => [static fn (): mixed => $stale()['released_reader_uses_checkpoint_database'], true],
    'reader release unblocked truncate' => [static fn (): mixed => $stale()['reader_release_unblocked_truncate'], true],
    'source digest length' => [static fn (): mixed => strlen($stale()['source_digest']), 64],
    'reader label stale option' => [static fn (): mixed => str_contains($stale()['rows'][1]['reader_label'], 'options stale tail'), true],
    'current label retained option' => [static fn (): mixed => str_contains($stale()['rows'][1]['current_label'], 'options retained commit'), true],
    'released label retained option' => [static fn (): mixed => str_contains($stale()['rows'][1]['released_next_label'], 'options retained commit'), true],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-savepoint-truncate-current-source-next123', $stale()['dependencies'], true), true],
    'dependency durable sidecar' => [static fn (): mixed => in_array('durable-sidecar-write', $stale()['dependencies'], true), true],
    'retained reader matches current prefix' => [static fn (): mixed => $retained()['reader_source_matches_current'], true],
    'retained reader stale frames empty' => [static fn (): mixed => $retained()['stale_reader_frame_indexes'], []],
    'retained reader stale pages empty' => [static fn (): mixed => $retained()['stale_reader_tail_pages'], []],
    'single page stale option' => [static fn (): mixed => $single()['stale_reader_tail_pages'], [2]],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal reader checkpoint savepoint truncate current source next123 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal reader checkpoint savepoint truncate current source next123 rejects empty savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateWithStaleReaderPlan($makeStack(), '', $wal, $walBytes, $walBytes, $databaseBytes, [1]));
};

$tests['wal reader checkpoint savepoint truncate current source next123 rejects empty wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateWithStaleReaderPlan($makeStack(), 'plugin-settings-next123', $wal, '', $walBytes, $databaseBytes, [1]));
};

$tests['wal reader checkpoint savepoint truncate current source next123 rejects empty reader wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateWithStaleReaderPlan($makeStack(), 'plugin-settings-next123', $wal, $walBytes, '', $databaseBytes, [1]));
};

$tests['wal reader checkpoint savepoint truncate current source next123 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateWithStaleReaderPlan($makeStack(), 'plugin-settings-next123', $wal, $walBytes, $walBytes, $databaseBytes, []));
};

$tests['wal reader checkpoint savepoint truncate current source next123 rejects source mismatch'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $bad = substr_replace($walBytes, 'x', 1600, 1);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateWithStaleReaderPlan($makeStack(), 'plugin-settings-next123', $wal, $bad, $walBytes, $databaseBytes, [1]));
};

$tests['wal reader checkpoint savepoint truncate current source next123 rejects negative reader frame'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateWithStaleReaderPlan($makeStack(), 'plugin-settings-next123', $wal, $walBytes, $walBytes, $databaseBytes, [1], -1));
};

$tests['wal reader checkpoint savepoint truncate current source next123 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateWithStaleReaderPlan($makeStack(), 'plugin-settings-next123', $wal, $walBytes, $walBytes, $databaseBytes, ['1']));
};

return $tests;
