<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next104 schema base')
    . $page('next104 options base')
    . $page('next104 plugin base')
    . $page('next104 autoload base')
    . $page('next104 transient base');

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x10410401;
    $salt2 = 0x10410402;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 104, $salt1, $salt2);
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
    [1, 0, 'next104 schema retained draft'],
    [2, 5, 'next104 options retained commit'],
    [3, 0, 'next104 plugin savepoint draft'],
    [4, 0, 'next104 autoload savepoint draft'],
    [4, 5, 'next104 autoload savepoint commit'],
    [5, 5, 'next104 transient savepoint commit'],
    [2, 5, 'next104 options savepoint tail'],
];
$walBytes = $makeWalBytes($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$mutatedWalBytes = $makeWalBytes([
    [1, 0, 'next104 schema retained draft'],
    [2, 5, 'next104 options retained commit'],
    [3, 0, 'next104 plugin source mismatch'],
    [4, 0, 'next104 autoload savepoint draft'],
    [4, 5, 'next104 autoload savepoint commit'],
    [5, 5, 'next104 transient savepoint commit'],
    [2, 5, 'next104 options savepoint tail'],
]);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next104');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next104');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 4, true);
    $stack->recordWalFrameWrite(6, 5, true);
    $stack->recordWalFrameWrite(7, 2, true);

    return $stack;
};

$plan = static fn (string $mode = 'restart', ?int $reader = 7, array $pages = [1, 2, 3, 4, 5]): array => SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan::plan(
    $makeStack(),
    'plugin-settings-next104',
    $wal,
    $walBytes,
    $databaseBytes,
    $pages,
    $mode,
    $reader
);
$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$retainedReader = static fn (): array => $plan('restart', 2);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'reader-savepoint-current-source-release-unblocks-checkpoint-next104'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next104'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], $pageSize],
    'original reader end frame' => [static fn (): mixed => $restart()['original_reader_end_frame'], 7],
    'retained reader end frame' => [static fn (): mixed => $restart()['retained_reader_end_frame'], 2],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 5],
    'discarded frame indexes' => [static fn (): mixed => $restart()['discarded_frame_indexes'], [3, 4, 5, 6, 7]],
    'discarded page numbers' => [static fn (): mixed => $restart()['discarded_page_numbers'], [3, 4, 5, 2]],
    'pinned checkpoint busy' => [static fn (): mixed => $restart()['pinned_checkpoint_busy'], true],
    'pinned checkpoint reason' => [static fn (): mixed => $restart()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned wal action' => [static fn (): mixed => $restart()['pinned_wal_action'], 'preserve_wal'],
    'released checkpoint not busy' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'released checkpoint reason' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released wal action' => [static fn (): mixed => $restart()['released_wal_action'], 'restart_wal'],
    'retained bytes length' => [static fn (): mixed => $restart()['retained_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'pinned bytes length' => [static fn (): mixed => $restart()['pinned_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released restart header bytes length' => [static fn (): mixed => $restart()['released_wal_bytes_length'], 32],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'pinned next sources' => [static fn (): mixed => $restart()['pinned_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'released next sources' => [static fn (): mixed => $restart()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'current wal count' => [static fn (): mixed => $restart()['current_source_counts']['wal'], 2],
    'current database count' => [static fn (): mixed => $restart()['current_source_counts']['database'], 3],
    'pinned wal count' => [static fn (): mixed => $restart()['pinned_next_source_counts']['wal'], 2],
    'released database count' => [static fn (): mixed => $restart()['released_next_source_counts']['database'], 5],
    'row count' => [static fn (): mixed => count($restart()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'before frames' => [static fn (): mixed => array_column($restart()['rows'], 'before_frame'), [1, 7, 3, 5, 6]],
    'current frames' => [static fn (): mixed => array_column($restart()['rows'], 'current_frame'), [1, 2, null, null, null]],
    'pinned next frames' => [static fn (): mixed => array_column($restart()['rows'], 'pinned_next_frame'), [1, 2, null, null, null]],
    'released next frames' => [static fn (): mixed => array_column($restart()['rows'], 'released_next_frame'), [null, null, null, null, null]],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>wal>database', 'wal>wal>wal>database', 'wal>database>database>database', 'wal>database>database>database', 'wal>database>database>database']],
    'reader rewound pages' => [static fn (): mixed => $restart()['reader_rewound_pages'], [2, 3, 4, 5]],
    'page two rewound' => [static fn (): mixed => $restart()['rows'][1]['reader_rewound_to_retained_prefix'], true],
    'page three rewound' => [static fn (): mixed => $restart()['rows'][2]['reader_rewound_to_retained_prefix'], true],
    'page one not rewound' => [static fn (): mixed => $restart()['rows'][0]['reader_rewound_to_retained_prefix'], false],
    'pinned checkpoint preserved images' => [static fn (): mixed => $restart()['pinned_checkpoint_preserved_images'], true],
    'released checkpoint preserved images' => [static fn (): mixed => $restart()['released_checkpoint_preserved_images'], true],
    'released uses checkpoint database' => [static fn (): mixed => $restart()['released_reader_uses_checkpoint_database'], true],
    'reader release unblocked checkpoint' => [static fn (): mixed => $restart()['reader_release_unblocked_checkpoint'], true],
    'current source verified' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'before label page two tail' => [static fn (): mixed => str_contains($restart()['rows'][1]['before_label'], 'options savepoint tail'), true],
    'current label page two retained' => [static fn (): mixed => str_contains($restart()['rows'][1]['current_label'], 'options retained commit'), true],
    'released label page four base' => [static fn (): mixed => str_contains($restart()['rows'][3]['released_next_label'], 'autoload base'), true],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-savepoint-current-source-next104', $restart()['dependencies'], true), true],
    'dependency durable sidecar write' => [static fn (): mixed => in_array('durable-sidecar-write', $restart()['dependencies'], true), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate released action' => [static fn (): mixed => $truncate()['released_wal_action'], 'truncate_wal'],
    'truncate released bytes length' => [static fn (): mixed => $truncate()['released_wal_bytes_length'], 0],
    'truncate released sources' => [static fn (): mixed => $truncate()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'retained reader status still busy for reset' => [static fn (): mixed => $retainedReader()['status'], 'reader-savepoint-current-source-release-unblocks-checkpoint-next104'],
    'retained reader checkpoint remains busy' => [static fn (): mixed => $retainedReader()['pinned_checkpoint_busy'], true],
    'retained reader still needs release unblock' => [static fn (): mixed => $retainedReader()['reader_release_unblocked_checkpoint'], true],
    'retained reader no rewound pages' => [static fn (): mixed => $retainedReader()['reader_rewound_pages'], []],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal checkpoint reader savepoint current source next104 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal checkpoint reader savepoint current source next104 rejects empty savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan::plan($makeStack(), '', $wal, $walBytes, $databaseBytes, [1]));
};

$tests['wal checkpoint reader savepoint current source next104 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next104', $wal, $walBytes, $databaseBytes, []));
};

$tests['wal checkpoint reader savepoint current source next104 rejects invalid mode'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next104', $wal, $walBytes, $databaseBytes, [1], 'passive'));
};

$tests['wal checkpoint reader savepoint current source next104 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next104', $wal, $walBytes, $databaseBytes, ['1']));
};

$tests['wal checkpoint reader savepoint current source next104 rejects negative reader'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next104', $wal, $walBytes, $databaseBytes, [1], 'restart', -1));
};

$tests['wal checkpoint reader savepoint current source next104 rejects stale source bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $mutatedWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next104', $wal, $mutatedWalBytes, $databaseBytes, [1]));
};

return $tests;
