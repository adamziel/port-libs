<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp88 schema base')
    . $page('wp88 option_value active_plugins base')
    . $page('wp88 autoload index base')
    . $page('wp88 transient timeout base')
    . $page('wp88 cron option base');

$makeWalBytes = static function (int $checkpoint, int $salt1, int $salt2, string $tag) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $append = static function (int $pageNumber, int $commit, string $image) use (&$bytes, &$seed, $salt1, $salt2): void {
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    $append(2, 0, $page("{$tag} active_plugins old reader"));
    $append(3, 5, $page("{$tag} autoload first commit"));
    $append(2, 0, $page("{$tag} active_plugins latest"));
    $append(4, 0, $page("{$tag} transient draft"));
    $append(5, 5, $page("{$tag} cron committed"));

    return $bytes;
};

$currentWalBytes = $makeWalBytes(88, 0x88000011, 0x88000022, 'current');
$staleSaltBytes = $makeWalBytes(88, 0x88000012, 0x88000022, 'stale salt');
$staleCheckpointBytes = $makeWalBytes(89, 0x88000011, 0x88000022, 'stale checkpoint');
$shortWalBytes = substr($currentWalBytes, 0, 32 + (4 * (24 + $pageSize)));
$currentWal = SQLiteWal::parse($currentWalBytes, null, true);
$staleWal = SQLiteWal::parse($staleSaltBytes, null, true);

$pinned = static fn (): array => $currentWal->checkpointTruncateReaderCurrentSourceNext(
    $currentWalBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    2
);
$unpinned = static fn (): array => $currentWal->checkpointTruncateReaderCurrentSourceNext(
    $currentWalBytes,
    $databaseBytes,
    [2, 3, 4, 5],
    null
);

$cases = [
    'pinned status' => [static fn (): mixed => $pinned()['status'], 'reader-pinned-truncate-preserves-wal'],
    'pinned source status' => [static fn (): mixed => $pinned()['source_status'], 'current-source'],
    'pinned mode' => [static fn (): mixed => $pinned()['mode'], 'truncate'],
    'pinned current reader frame' => [static fn (): mixed => $pinned()['current_reader_end_frame'], 2],
    'pinned next reader frame keeps wal' => [static fn (): mixed => $pinned()['next_reader_end_frame'], 5],
    'pinned wal action preserves wal' => [static fn (): mixed => $pinned()['wal_action'], 'preserve_wal'],
    'pinned drained wal action truncates' => [static fn (): mixed => $pinned()['drained_wal_action'], 'truncate_wal'],
    'pinned wal bytes keep full source' => [static fn (): mixed => $pinned()['wal_bytes_length'], strlen($currentWalBytes)],
    'pinned drained wal bytes empty' => [static fn (): mixed => $pinned()['drained_wal_bytes_length'], 0],
    'pinned checkpoint busy' => [static fn (): mixed => $pinned()['pinned_checkpoint']['busy'], true],
    'pinned checkpoint reason' => [static fn (): mixed => $pinned()['pinned_checkpoint']['reason'], 'reader_blocks_checkpoint_completion'],
    'pinned checkpointed frames' => [static fn (): mixed => $pinned()['pinned_checkpoint']['checkpointed_frame_count'], 1],
    'pinned checkpoint remaining committed frames' => [static fn (): mixed => $pinned()['pinned_checkpoint']['remaining_committed_frame_count'], 3],
    'pinned drained reason' => [static fn (): mixed => $pinned()['drained_checkpoint']['reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'pinned drained checkpointed frames' => [static fn (): mixed => $pinned()['drained_checkpoint']['checkpointed_frame_count'], 4],
    'pinned current sources' => [static fn (): mixed => $pinned()['current_sources'], ['database', 'wal', 'wal', 'database', 'database']],
    'pinned next sources' => [static fn (): mixed => $pinned()['next_sources'], ['database', 'wal', 'wal', 'wal', 'wal']],
    'pinned drained sources' => [static fn (): mixed => $pinned()['drained_sources'], ['database', 'database', 'database', 'database', 'database']],
    'pinned current frame indexes' => [static fn (): mixed => $pinned()['current_frame_indexes'], [null, 1, 2, null, null]],
    'pinned next frame indexes' => [static fn (): mixed => $pinned()['next_frame_indexes'], [null, 3, 2, 4, 5]],
    'pinned drained frame indexes' => [static fn (): mixed => $pinned()['drained_frame_indexes'], [null, null, null, null, null]],
    'pinned current source names' => [static fn (): mixed => $pinned()['current_source_names'], ['database', 'preserved-wal', 'checkpoint-database', 'database', 'database']],
    'pinned next source names' => [static fn (): mixed => $pinned()['next_source_names'], ['database', 'preserved-wal', 'checkpoint-database', 'preserved-wal', 'preserved-wal']],
    'pinned drained source names' => [static fn (): mixed => $pinned()['drained_source_names'], ['reset-database', 'reset-database', 'reset-database', 'reset-database', 'reset-database']],
    'pinned page two old current label' => [static fn (): mixed => str_contains($pinned()['current_reader'][1]['image'], 'old reader'), true],
    'pinned page two next latest label' => [static fn (): mixed => str_contains($pinned()['next_reader'][1]['image'], 'active_plugins latest'), true],
    'pinned page two drained latest label' => [static fn (): mixed => str_contains($pinned()['drained_reader'][1]['image'], 'active_plugins latest'), true],
    'pinned page five next wal label' => [static fn (): mixed => str_contains($pinned()['next_reader'][4]['image'], 'cron committed'), true],
    'pinned page five drained database label' => [static fn (): mixed => str_contains($pinned()['drained_reader'][4]['image'], 'cron committed'), true],
    'pinned current next images differ' => [static fn (): mixed => $pinned()['current_next_images_match'], false],
    'pinned next drained images match' => [static fn (): mixed => $pinned()['next_drained_images_match'], true],
    'pinned current drained images differ' => [static fn (): mixed => $pinned()['current_drained_images_match'], false],
    'pinned next uses preserved wal' => [static fn (): mixed => $pinned()['next_uses_preserved_wal'], true],
    'pinned next uses checkpoint database' => [static fn (): mixed => $pinned()['next_uses_checkpoint_database'], true],
    'pinned drained database only' => [static fn (): mixed => $pinned()['drained_uses_reset_database_only'], true],
    'pinned reader blocks truncate' => [static fn (): mixed => $pinned()['reader_pin_blocks_truncate'], true],
    'pinned drained retry truncates wal' => [static fn (): mixed => $pinned()['drained_retry_truncates_wal'], true],
    'pinned source frame count' => [static fn (): mixed => $pinned()['source_frame_count'], 5],
    'pinned parsed frame count' => [static fn (): mixed => $pinned()['parsed_frame_count'], 5],
    'pinned dependency includes checkpoint' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $pinned()['dependencies'], true), true],
    'pinned dependency includes current source' => [static fn (): mixed => in_array('sqlite-wal-current-source-admission', $pinned()['dependencies'], true), true],
    'pinned dependency includes slice' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-truncate-current-source-next88', $pinned()['dependencies'], true), true],
    'pinned no current errors' => [static fn (): mixed => $pinned()['current_errors'], []],
    'pinned no next errors' => [static fn (): mixed => $pinned()['next_errors'], []],
    'pinned no drained errors' => [static fn (): mixed => $pinned()['drained_errors'], []],

    'unpinned status ready' => [static fn (): mixed => $unpinned()['status'], 'truncate-ready'],
    'unpinned current reader latest' => [static fn (): mixed => $unpinned()['current_reader_end_frame'], 5],
    'unpinned next reader no wal' => [static fn (): mixed => $unpinned()['next_reader_end_frame'], 0],
    'unpinned action truncates wal' => [static fn (): mixed => $unpinned()['wal_action'], 'truncate_wal'],
    'unpinned wal bytes empty' => [static fn (): mixed => $unpinned()['wal_bytes_length'], 0],
    'unpinned current sources' => [static fn (): mixed => $unpinned()['current_sources'], ['wal', 'wal', 'wal', 'wal']],
    'unpinned next sources' => [static fn (): mixed => $unpinned()['next_sources'], ['database', 'database', 'database', 'database']],
    'unpinned drained sources' => [static fn (): mixed => $unpinned()['drained_sources'], ['database', 'database', 'database', 'database']],
    'unpinned source names' => [static fn (): mixed => $unpinned()['next_source_names'], ['database', 'database', 'database', 'database']],
    'unpinned images match' => [static fn (): mixed => $unpinned()['current_next_images_match'], true],
    'unpinned next drained images match' => [static fn (): mixed => $unpinned()['next_drained_images_match'], true],
    'unpinned does not use preserved wal' => [static fn (): mixed => $unpinned()['next_uses_preserved_wal'], false],
    'unpinned drained database only' => [static fn (): mixed => $unpinned()['drained_uses_reset_database_only'], true],
    'unpinned reader does not block truncate' => [static fn (): mixed => $unpinned()['reader_pin_blocks_truncate'], false],
    'unpinned retry truncates wal' => [static fn (): mixed => $unpinned()['drained_retry_truncates_wal'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader checkpoint truncate current source next88 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader checkpoint truncate current source next88 rejects stale salt bytes'] = static function (TestRunner $t) use ($currentWal, $staleSaltBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->checkpointTruncateReaderCurrentSourceNext($staleSaltBytes, $databaseBytes, [2], 2));
};

$tests['wal reader checkpoint truncate current source next88 rejects stale checkpoint bytes'] = static function (TestRunner $t) use ($currentWal, $staleCheckpointBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->checkpointTruncateReaderCurrentSourceNext($staleCheckpointBytes, $databaseBytes, [2], 2));
};

$tests['wal reader checkpoint truncate current source next88 rejects short frame count bytes'] = static function (TestRunner $t) use ($currentWal, $shortWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->checkpointTruncateReaderCurrentSourceNext($shortWalBytes, $databaseBytes, [2], 2));
};

$tests['wal reader checkpoint truncate current source next88 rejects stale parsed wal'] = static function (TestRunner $t) use ($staleWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $staleWal->checkpointTruncateReaderCurrentSourceNext($currentWalBytes, $databaseBytes, [2], 2));
};

$tests['wal reader checkpoint truncate current source next88 rejects empty page list'] = static function (TestRunner $t) use ($currentWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->checkpointTruncateReaderCurrentSourceNext($currentWalBytes, $databaseBytes, [], 2));
};

$tests['wal reader checkpoint truncate current source next88 rejects non integer page'] = static function (TestRunner $t) use ($currentWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->checkpointTruncateReaderCurrentSourceNext($currentWalBytes, $databaseBytes, [2, '3'], 2));
};

$tests['wal reader checkpoint truncate current source next88 rejects negative reader frame'] = static function (TestRunner $t) use ($currentWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->checkpointTruncateReaderCurrentSourceNext($currentWalBytes, $databaseBytes, [2], -1));
};

$tests['wal reader checkpoint truncate current source next88 rejects reader beyond wal'] = static function (TestRunner $t) use ($currentWal, $currentWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $currentWal->checkpointTruncateReaderCurrentSourceNext($currentWalBytes, $databaseBytes, [2], 6));
};

return $tests;
