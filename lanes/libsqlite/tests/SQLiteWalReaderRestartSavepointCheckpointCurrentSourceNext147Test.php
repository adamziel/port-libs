<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderRestartSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next147.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next147 database sqlite header')
    . $page('next147 database wp_options root')
    . $page('next147 database active plugins')
    . $page('next147 database autoload index')
    . $page('next147 database transient cache')
    . $page('next147 database rewrite rules');

$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
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

$walBytes = $makeWalBytes([
    [2, 0, 'next147 frame1 wp_options import draft'],
    [3, 6, 'next147 frame2 active_plugins commit'],
    [4, 0, 'next147 frame3 autoload failed savepoint'],
    [5, 0, 'next147 frame4 transient failed savepoint'],
    [6, 6, 'next147 frame5 rewrite failed commit'],
], 147, 0x14714701, 0x14714702);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$savepoints = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordWalFrameWrite(1, 2);
    $stack->recordWalFrameWrite(2, 3, true);
    $stack->savepoint('autoload-batch');
    $stack->recordWalFrameWrite(3, 4);
    $stack->recordWalFrameWrite(4, 5);
    $stack->recordWalFrameWrite(5, 6, true);

    return $stack;
};

$nextTransactions = [
    [
        'pages' => [
            4 => $page('next147 next autoload retry commit'),
            6 => $page('next147 next rewrite retry commit'),
        ],
        'database_page_count' => 6,
        'commit' => true,
    ],
];

$plan = static function (
    ?SQLiteSavepointStack $stack = null,
    string $savepoint = 'autoload-batch',
    array $pages = [2, 3, 4, 5, 6],
    int $readerEndFrame = 2,
    ?array $transactions = null,
    ?string $dbPath = null,
    ?string $dbBytes = null,
    ?string $sourceWalBytes = null
) use ($databasePath, $databaseBytes, $wal, $walBytes, $savepoints, $nextTransactions): array {
    return SQLiteWalReaderRestartSavepointCheckpointCurrentSourceNextPlan::plan(
        $dbPath ?? $databasePath,
        $dbBytes ?? $databaseBytes,
        $wal,
        $sourceWalBytes ?? $walBytes,
        $stack ?? $savepoints(),
        $savepoint,
        $pages,
        $readerEndFrame,
        $transactions ?? $nextTransactions
    );
};

$ok = static fn (): array => $plan();
$single = static fn (): array => $plan(null, 'autoload-batch', [4]);

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-reader-restart-savepoint-checkpoint-current-source-next147'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'savepoint_rollback_truncates_wal_before_restart_checkpoint_preserves_current_reader'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ok()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $ok()['savepoint'], 'autoload-batch'],
    'reader end frame' => [static fn (): mixed => $ok()['reader_end_frame'], 2],
    'original frame count' => [static fn (): mixed => $ok()['original_frame_count'], 5],
    'retained frame count' => [static fn (): mixed => $ok()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $ok()['discarded_frame_count'], 3],
    'truncate bytes' => [static fn (): mixed => $ok()['truncate_to_bytes'], 32 + (2 * (24 + $pageSize))],
    'truncated bytes length' => [static fn (): mixed => $ok()['truncated_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'checkpoint busy' => [static fn (): mixed => $ok()['checkpoint_busy'], true],
    'checkpoint reason' => [static fn (): mixed => $ok()['checkpoint_reason'], 'reader_blocks_wal_reset'],
    'checkpoint action' => [static fn (): mixed => $ok()['checkpoint_wal_action'], 'restart_wal'],
    'checkpoint database bytes' => [static fn (): mixed => $ok()['checkpoint_database_bytes_length'], 6 * $pageSize],
    'restart sequence' => [static fn (): mixed => $ok()['restart_checkpoint_sequence'], 148],
    'restart salt' => [static fn (): mixed => $ok()['restart_salt'], [0x14714702, 0x14714702]],
    'append start frame' => [static fn (): mixed => $ok()['next_append_start_frame'], 1],
    'append end frame' => [static fn (): mixed => $ok()['next_append_end_frame'], 2],
    'append frame count' => [static fn (): mixed => $ok()['next_append_frame_count'], 2],
    'append last commit' => [static fn (): mixed => $ok()['next_append_last_commit_frame'], 2],
    'reader before sources' => [static fn (): mixed => $ok()['reader_before_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'reader after sources' => [static fn (): mixed => $ok()['reader_after_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'next sources' => [static fn (): mixed => $ok()['next_sources'], ['database', 'database', 'wal', 'database', 'wal']],
    'reader before frames' => [static fn (): mixed => $ok()['reader_before_frame_indexes'], [1, 2, null, null, null]],
    'reader after frames' => [static fn (): mixed => $ok()['reader_after_frame_indexes'], [1, 2, null, null, null]],
    'next frames' => [static fn (): mixed => $ok()['next_frame_indexes'], [null, null, 1, null, 2]],
    'reader preserved' => [static fn (): mixed => $ok()['reader_preserved_by_restart_checkpoint'], true],
    'next separated' => [static fn (): mixed => $ok()['next_generation_separated_from_reader'], true],
    'discarded frame indexes' => [static fn (): mixed => array_column($ok()['discarded_wal_frames'], 'frame_index'), [3, 4, 5]],
    'discarded frame pages' => [static fn (): mixed => array_column($ok()['discarded_wal_frames'], 'page_number'), [4, 5, 6]],
    'discarded pages' => [static fn (): mixed => $ok()['discarded_page_numbers'], [4, 5, 6]],
    'source transitions' => [static fn (): mixed => $ok()['source_transitions'], ['wal>wal>database', 'wal>wal>database', 'database>database>wal', 'database>database>database', 'database>database>wal']],
    'source digest length' => [static fn (): mixed => strlen($ok()['source_digest']), 64],
    'original sha length' => [static fn (): mixed => strlen($ok()['original_wal_sha256']), 64],
    'truncated sha length' => [static fn (): mixed => strlen($ok()['truncated_wal_sha256']), 64],
    'restart sha length' => [static fn (): mixed => strlen($ok()['restart_header_wal_sha256']), 64],
    'next sha length' => [static fn (): mixed => strlen($ok()['next_wal_sha256']), 64],
    'sha separated' => [static fn (): mixed => $ok()['original_wal_sha256'] !== $ok()['truncated_wal_sha256'] && $ok()['truncated_wal_sha256'] !== $ok()['restart_header_wal_sha256'] && $ok()['restart_header_wal_sha256'] !== $ok()['next_wal_sha256'], true],
    'row count' => [static fn (): mixed => count($ok()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($ok()['rows'], 'page_number'), [2, 3, 4, 5, 6]],
    'row one reader label' => [static fn (): mixed => $ok()['rows'][0]['reader_before_label'], 'next147 frame1 wp_options import draft'],
    'row two reader label' => [static fn (): mixed => $ok()['rows'][1]['reader_after_label'], 'next147 frame2 active_plugins commit'],
    'row three next label' => [static fn (): mixed => $ok()['rows'][2]['next_label'], 'next147 next autoload retry commit'],
    'row four next label' => [static fn (): mixed => $ok()['rows'][3]['next_label'], 'next147 database transient cache'],
    'row five next label' => [static fn (): mixed => $ok()['rows'][4]['next_label'], 'next147 next rewrite retry commit'],
    'row one preserved' => [static fn (): mixed => $ok()['rows'][0]['reader_preserved'], true],
    'row three separated' => [static fn (): mixed => $ok()['rows'][2]['next_separated_from_reader'], true],
    'rollback needs truncate' => [static fn (): mixed => $ok()['rollback']['needs_truncate'], true],
    'checkpoint wal action' => [static fn (): mixed => $ok()['checkpoint']['wal_action'], 'restart_wal'],
    'next append status' => [static fn (): mixed => $ok()['next_append']['status'], 'planned'],
    'next append reason' => [static fn (): mixed => $ok()['next_append']['reason'], 'wal_append_contains_commit_frame'],
    'operation reasons' => [static fn (): mixed => $ok()['operation_reasons'], [
        'truncate_wal_to_savepoint_before_restart_checkpoint_next147',
        'reader_blocks_wal_reset',
        'restart_checkpoint_can_reset_wal',
        'append_wal_transaction_frames',
        'sync_appended_wal_frames',
        'persist_appended_wal_sidecar',
    ]],
    'dependency byte truncation' => [static fn (): mixed => in_array('sqlite-wal-savepoint-byte-truncation', $ok()['dependencies'], true), true],
    'dependency restart reader' => [static fn (): mixed => in_array('sqlite-wal-restart-checkpoint-current-reader-boundary', $ok()['dependencies'], true), true],
    'dependency next147' => [static fn (): mixed => in_array('sqlite-wal-reader-restart-savepoint-checkpoint-current-source-next147', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'savepoint rollback with a restart checkpoint'), true],
    'single row source' => [static fn (): mixed => $single()['next_sources'], ['wal']],
    'single row label' => [static fn (): mixed => $single()['rows'][0]['next_label'], 'next147 next autoload retry commit'],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal reader restart savepoint checkpoint current source next147 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$throws = [
    'empty path rejected' => static fn () => $plan(null, 'autoload-batch', [1], 1, null, ''),
    'empty database rejected' => static fn () => $plan(null, 'autoload-batch', [1], 1, null, null, ''),
    'empty wal rejected' => static fn () => $plan(null, 'autoload-batch', [1], 1, null, null, null, ''),
    'bad wal bytes rejected' => static fn () => $plan(null, 'autoload-batch', [1], 1, null, null, null, substr_replace($walBytes, 'x', 80, 1)),
    'empty pages rejected' => static fn () => $plan(null, 'autoload-batch', [], 1),
    'empty transactions rejected' => static fn () => $plan(null, 'autoload-batch', [1], 1, []),
    'negative reader rejected' => static fn () => $plan(null, 'autoload-batch', [1], -1),
    'past original reader rejected' => static fn () => $plan(null, 'autoload-batch', [1], 6),
    'past truncated reader rejected' => static fn () => $plan(null, 'autoload-batch', [1], 3),
    'zero page rejected' => static fn () => $plan(null, 'autoload-batch', [0], 1),
    'string page rejected' => static fn () => $plan(null, 'autoload-batch', ['1'], 1),
    'missing savepoint rejected' => static fn () => $plan(null, 'missing', [1], 1),
    'bad next transaction rejected' => static fn () => $plan(null, 'autoload-batch', [1], 1, [['pages' => []]]),
];

foreach ($throws as $name => $callback) {
    $tests['wal reader restart savepoint checkpoint current source next147 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
