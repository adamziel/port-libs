<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next134 schema base')
    . $page('next134 option base')
    . $page('next134 autoload base')
    . $page('next134 transient base')
    . $page('next134 plugin base');

$makeWal = static function (array $frames, int $checkpoint = 134, int $salt1 = 0x13413401) use ($pageSize, $page): string {
    $salt2 = 0x13413402;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
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
    [1, 0, 'next134 schema draft current'],
    [2, 5, 'next134 option commit current'],
    [3, 0, 'next134 autoload draft current'],
    [4, 5, 'next134 transient commit current'],
];
$walBytes = $makeWal($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$staleReaderBytes = $makeWal($frames, 135);

$transactions = [[
    'pages' => [
        2 => $page('next134 option commit next generation'),
        5 => $page('next134 plugin commit next generation'),
    ],
    'database_page_count' => 5,
    'commit' => true,
]];

$plan = static function (string $readerBytes = null, int $readerEndFrame = 4, array $pages = [1, 2, 3, 4, 5], array $next = null) use ($wal, $walBytes, $databaseBytes, $transactions): array {
    return SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan::plan(
        $wal,
        $walBytes,
        $readerBytes ?? $walBytes,
        $databaseBytes,
        '/srv/www/wp-content/database/wp-next134.sqlite',
        $next ?? $transactions,
        $pages,
        $readerEndFrame
    );
};

$ok = static fn (): array => $plan();
$baseReader = static fn (): array => $plan(null, 0);
$single = static fn (): array => $plan(null, 4, [2]);
$stale = static fn (): array => $plan($staleReaderBytes);

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-checkpoint-truncate-reader-current-source-next134'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'current_reader_source_pins_truncate_until_released_next_source_starts_fresh_wal_generation'],
    'database path' => [static fn (): mixed => $ok()['database_path'], '/srv/www/wp-content/database/wp-next134.sqlite'],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], '/srv/www/wp-content/database/wp-next134.sqlite-wal'],
    'page size' => [static fn (): mixed => $ok()['page_size'], 512],
    'reader frame' => [static fn (): mixed => $ok()['current_reader_end_frame'], 4],
    'reader source matches' => [static fn (): mixed => $ok()['reader_source_matches_current'], true],
    'current sha length' => [static fn (): mixed => strlen($ok()['current_wal_sha256']), 64],
    'reader sha length' => [static fn (): mixed => strlen($ok()['reader_wal_sha256']), 64],
    'frame count' => [static fn (): mixed => $ok()['current_frame_count'], 4],
    'pinned busy' => [static fn (): mixed => $ok()['pinned_checkpoint_busy'], true],
    'pinned reason' => [static fn (): mixed => $ok()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned action' => [static fn (): mixed => $ok()['pinned_wal_action'], 'preserve_wal'],
    'pinned wal length' => [static fn (): mixed => $ok()['pinned_wal_bytes_length'], 32 + (4 * (24 + $pageSize))],
    'released ready' => [static fn (): mixed => $ok()['released_checkpoint_busy'], false],
    'released reason' => [static fn (): mixed => $ok()['released_checkpoint_reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'released action' => [static fn (): mixed => $ok()['released_wal_action'], 'truncate_wal'],
    'released wal removed' => [static fn (): mixed => $ok()['released_wal_bytes_length'], 0],
    'released database sha length' => [static fn (): mixed => strlen($ok()['released_database_sha256']), 64],
    'fresh checkpoint sequence' => [static fn (): mixed => $ok()['fresh_wal_checkpoint_sequence'], 135],
    'fresh salt' => [static fn (): mixed => $ok()['fresh_wal_salt'], [0x13413402, 0x13413402]],
    'append start frame' => [static fn (): mixed => $ok()['next_append_start_frame'], 1],
    'append end frame' => [static fn (): mixed => $ok()['next_append_end_frame'], 2],
    'append frame count' => [static fn (): mixed => $ok()['next_append_frame_count'], 2],
    'append commit frame' => [static fn (): mixed => $ok()['next_append_last_commit_frame'], 2],
    'next wal length' => [static fn (): mixed => $ok()['next_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'current sources' => [static fn (): mixed => $ok()['current_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'pinned after sources' => [static fn (): mixed => $ok()['pinned_after_checkpoint_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'next sources' => [static fn (): mixed => $ok()['next_sources'], ['database', 'wal', 'database', 'database', 'wal']],
    'current wal count' => [static fn (): mixed => $ok()['current_source_counts']['wal'], 4],
    'pinned wal count' => [static fn (): mixed => $ok()['pinned_after_checkpoint_source_counts']['wal'], 4],
    'next wal count' => [static fn (): mixed => $ok()['next_source_counts']['wal'], 2],
    'row count' => [static fn (): mixed => count($ok()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($ok()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'current frames' => [static fn (): mixed => array_column($ok()['rows'], 'current_frame'), [1, 2, 3, 4, null]],
    'pinned frames' => [static fn (): mixed => array_column($ok()['rows'], 'pinned_after_checkpoint_frame'), [1, 2, 3, 4, null]],
    'next frames' => [static fn (): mixed => array_column($ok()['rows'], 'next_frame'), [null, 1, null, null, 2]],
    'source transitions' => [static fn (): mixed => $ok()['source_transitions'], ['wal>wal>database', 'wal>wal>wal', 'wal>wal>database', 'wal>wal>database', 'database>database>wal']],
    'row two current label' => [static fn (): mixed => $ok()['rows'][1]['current_label'], 'next134 option commit current'],
    'row two next label' => [static fn (): mixed => $ok()['rows'][1]['next_label'], 'next134 option commit next generation'],
    'row five next label' => [static fn (): mixed => $ok()['rows'][4]['next_label'], 'next134 plugin commit next generation'],
    'current preserved' => [static fn (): mixed => $ok()['current_reader_preserved_by_pinned_checkpoint'], true],
    'next separated' => [static fn (): mixed => $ok()['next_source_separated_from_current_reader'], true],
    'reader release unblocked' => [static fn (): mixed => $ok()['reader_release_unblocked_truncate'], true],
    'old sidecar removed' => [static fn (): mixed => $ok()['truncate_removed_old_wal_sidecar'], true],
    'fresh generation used' => [static fn (): mixed => $ok()['next_reader_uses_fresh_wal_generation'], true],
    'append op write' => [static fn (): mixed => $ok()['append_operations'][0]['op'], 'write'],
    'append op sync' => [static fn (): mixed => $ok()['append_operations'][1]['op'], 'sync'],
    'append op directory' => [static fn (): mixed => $ok()['append_operations'][2]['op'], 'sync_directory'],
    'source digest length' => [static fn (): mixed => strlen($ok()['source_digest']), 64],
    'base reader status blocked' => [static fn (): mixed => $baseReader()['status'], 'wal-checkpoint-truncate-reader-current-source-blocked-next134'],
    'base reader pinned reason' => [static fn (): mixed => $baseReader()['pinned_checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'single page next label' => [static fn (): mixed => $single()['rows'][0]['next_label'], 'next134 option commit next generation'],
    'single transition' => [static fn (): mixed => $single()['source_transitions'], ['wal>wal>wal']],
    'stale status blocked' => [static fn (): mixed => $stale()['status'], 'wal-checkpoint-truncate-reader-current-source-blocked-next134'],
    'stale reason' => [static fn (): mixed => $stale()['reason'], 'reader_wal_source_mismatch_requires_reopen_before_truncate_checkpoint'],
    'stale reader mismatch' => [static fn (): mixed => $stale()['reader_source_matches_current'], false],
    'dependency next134' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-truncate-reader-current-source-next134', $ok()['dependencies'], true), true],
    'dependency current reader pin' => [static fn (): mixed => in_array('sqlite-wal-current-reader-source-pin', $ok()['dependencies'], true), true],
    'dependency next source' => [static fn (): mixed => in_array('sqlite-wal-truncate-next-source-generation', $ok()['dependencies'], true), true],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal checkpoint truncate reader current source next134 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal checkpoint truncate reader current source next134 rejects empty wal bytes'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan::plan($wal, '', $walBytes, $databaseBytes, '/tmp/wp.sqlite', $transactions, [1], 1));
};

$tests['wal checkpoint truncate reader current source next134 rejects empty database'] = static function (TestRunner $t) use ($wal, $walBytes, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan::plan($wal, $walBytes, $walBytes, '', '/tmp/wp.sqlite', $transactions, [1], 1));
};

$tests['wal checkpoint truncate reader current source next134 rejects empty path'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan::plan($wal, $walBytes, $walBytes, $databaseBytes, '', $transactions, [1], 1));
};

$tests['wal checkpoint truncate reader current source next134 rejects empty next transaction list'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan::plan($wal, $walBytes, $walBytes, $databaseBytes, '/tmp/wp.sqlite', [], [1], 1));
};

$tests['wal checkpoint truncate reader current source next134 rejects empty pages'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan::plan($wal, $walBytes, $walBytes, $databaseBytes, '/tmp/wp.sqlite', $transactions, [], 1));
};

$tests['wal checkpoint truncate reader current source next134 rejects negative reader'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan::plan($wal, $walBytes, $walBytes, $databaseBytes, '/tmp/wp.sqlite', $transactions, [1], -1));
};

$tests['wal checkpoint truncate reader current source next134 rejects source mismatch'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $transactions): void {
    $bad = substr_replace($walBytes, 'x', 700, 1);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan::plan($wal, $bad, $walBytes, $databaseBytes, '/tmp/wp.sqlite', $transactions, [1], 1));
};

$tests['wal checkpoint truncate reader current source next134 rejects non integer page'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan::plan($wal, $walBytes, $walBytes, $databaseBytes, '/tmp/wp.sqlite', $transactions, ['1'], 1));
};

$tests['wal checkpoint truncate reader current source next134 rejects reader outside wal'] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan::plan($wal, $walBytes, $walBytes, $databaseBytes, '/tmp/wp.sqlite', $transactions, [1], 9));
};

return $tests;
