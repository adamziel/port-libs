<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite-next133';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next133 schema base')
    . $page('next133 option base')
    . $page('next133 autoload base')
    . $page('next133 cron base')
    . $page('next133 transient base');

$makeWal = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
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

$currentWalBytes = $makeWal([
    [2, 0, 'next133 option current reader draft'],
    [3, 5, 'next133 autoload current reader commit'],
    [2, 0, 'next133 option later draft'],
    [4, 5, 'next133 cron restart checkpoint commit'],
    [2, 5, 'next133 option restart checkpoint tail'],
], 133, 0x13300101, 0x13300102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$checkpointDatabaseBytes = (string) $currentWal->durableCheckpointResult($databaseBytes, 'restart')['database_bytes'];
$nextWalBytes = $makeWal([
    [2, 0, 'next133 option next generation draft'],
    [5, 5, 'next133 transient next generation commit'],
    [4, 5, 'next133 cron next generation tail'],
], 134, 0x13300102, 0x13300103);

$plan = static fn (int $reader = 2, array $pages = [1, 2, 3, 4, 5], ?string $nextBytes = null): array => SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan::plan(
    $GLOBALS['databasePath'],
    $GLOBALS['currentWal'],
    $GLOBALS['currentWalBytes'],
    $GLOBALS['databaseBytes'],
    $nextBytes ?? $GLOBALS['nextWalBytes'],
    $pages,
    $reader
);
$restart = static fn (): array => $plan();
$latest = static fn (): array => $plan(5);
$single = static fn (): array => $plan(2, [2]);
$nextWal = static fn (): SQLiteWal => SQLiteWal::parse($GLOBALS['nextWalBytes'], $GLOBALS['pageSize'], true);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-reader-checkpoint-restart-current-source-next133'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'current_reader_keeps_original_wal_source_after_restart_replaces_wal_path'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'current reader frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'current checkpoint sequence' => [static fn (): mixed => $restart()['current_checkpoint_sequence'], 133],
    'next checkpoint sequence' => [static fn (): mixed => $restart()['next_checkpoint_sequence'], 134],
    'current frame count' => [static fn (): mixed => $restart()['current_frame_count'], 5],
    'next frame count' => [static fn (): mixed => $restart()['next_frame_count'], 3],
    'checkpointed frame count' => [static fn (): mixed => $restart()['checkpointed_frame_count'], 3],
    'restart header bytes' => [static fn (): mixed => $restart()['restart_wal_header_bytes_length'], 32],
    'current wal bytes length' => [static fn (): mixed => $restart()['current_wal_bytes_length'], strlen($currentWalBytes)],
    'next wal bytes length' => [static fn (): mixed => $restart()['next_wal_bytes_length'], strlen($nextWalBytes)],
    'current sha length' => [static fn (): mixed => strlen($restart()['current_wal_sha256']), 64],
    'next sha length' => [static fn (): mixed => strlen($restart()['next_wal_sha256']), 64],
    'checkpoint db sha length' => [static fn (): mixed => strlen($restart()['checkpoint_database_sha256']), 64],
    'current salt' => [static fn (): mixed => $restart()['current_salt'], [0x13300101, 0x13300102]],
    'next salt' => [static fn (): mixed => $restart()['next_salt'], [0x13300102, 0x13300103]],
    'restart replaced wal path' => [static fn (): mixed => $restart()['restart_replaced_wal_path'], true],
    'source handle distinct' => [static fn (): mixed => $restart()['current_source_handle_is_distinct_from_path'], true],
    'current reader preserved' => [static fn (): mixed => $restart()['current_reader_preserved_by_source_handle'], true],
    'path reopen would change current' => [static fn (): mixed => $restart()['path_reopen_would_change_current_reader'], true],
    'changed pages' => [static fn (): mixed => $restart()['changed_page_numbers'], [2, 4, 5]],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['database', 'wal', 'wal', 'database', 'database']],
    'checkpointed sources' => [static fn (): mixed => $restart()['checkpointed_sources'], ['database', 'database', 'database', 'database', 'database']],
    'path reopen sources' => [static fn (): mixed => $restart()['path_reopen_sources'], ['database', 'wal', 'database', 'wal', 'wal']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'wal', 'database', 'wal', 'wal']],
    'current frames' => [static fn (): mixed => $restart()['current_frame_indexes'], [null, 1, 2, null, null]],
    'path reopen frames' => [static fn (): mixed => $restart()['path_reopen_frame_indexes'], [null, 1, null, 3, 2]],
    'next frames' => [static fn (): mixed => $restart()['next_frame_indexes'], [null, 1, null, 3, 2]],
    'row count' => [static fn (): mixed => count($restart()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['database>database>database', 'wal>database>wal', 'wal>database>database', 'database>database>wal', 'database>database>wal']],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'row one unchanged' => [static fn (): mixed => $restart()['rows'][0]['current_would_change_if_path_reopened'], false],
    'row two changed' => [static fn (): mixed => $restart()['rows'][1]['current_would_change_if_path_reopened'], true],
    'row three unchanged after checkpoint' => [static fn (): mixed => $restart()['rows'][2]['current_would_change_if_path_reopened'], false],
    'row four changed' => [static fn (): mixed => $restart()['rows'][3]['current_would_change_if_path_reopened'], true],
    'row five changed' => [static fn (): mixed => $restart()['rows'][4]['current_would_change_if_path_reopened'], true],
    'row two current label' => [static fn (): mixed => $restart()['rows'][1]['current_label'], 'next133 option current reader draft'],
    'row two path reopen label' => [static fn (): mixed => $restart()['rows'][1]['path_reopen_label'], 'next133 option next generation draft'],
    'row three current label' => [static fn (): mixed => $restart()['rows'][2]['current_label'], 'next133 autoload current reader commit'],
    'row three path reopen label' => [static fn (): mixed => $restart()['rows'][2]['path_reopen_label'], 'next133 autoload current reader commit'],
    'row four checkpointed label' => [static fn (): mixed => $restart()['rows'][3]['checkpointed_label'], 'next133 cron restart checkpoint commit'],
    'row four next label' => [static fn (): mixed => $restart()['rows'][3]['next_label'], 'next133 cron next generation tail'],
    'row five checkpointed label' => [static fn (): mixed => $restart()['rows'][4]['checkpointed_label'], 'next133 transient base'],
    'row five next label' => [static fn (): mixed => $restart()['rows'][4]['next_label'], 'next133 transient next generation commit'],
    'row two next matches path' => [static fn (): mixed => $restart()['rows'][1]['next_matches_restarted_path'], true],
    'row two current not checkpoint' => [static fn (): mixed => $restart()['rows'][1]['current_matches_checkpoint_database'], false],
    'single changed pages' => [static fn (): mixed => $single()['changed_page_numbers'], [2]],
    'single transition' => [static fn (): mixed => $single()['source_transitions'], ['wal>database>wal']],
    'latest current sources' => [static fn (): mixed => $latest()['current_sources'], ['database', 'wal', 'wal', 'wal', 'database']],
    'latest current row four label' => [static fn (): mixed => $latest()['rows'][3]['current_label'], 'next133 cron restart checkpoint commit'],
    'latest changed pages' => [static fn (): mixed => $latest()['changed_page_numbers'], [2, 4, 5]],
    'operation list' => [static fn (): mixed => array_column($restart()['operations'], 'op'), ['checkpoint_database_write', 'replace_wal', 'keep_reader_source']],
    'operation path' => [static fn (): mixed => $restart()['operations'][1]['path'], $databasePath . '-wal'],
    'dependency next133' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-restart-current-source-next133', $restart()['dependencies'], true), true],
    'dependency source handle' => [static fn (): mixed => in_array('sqlite-wal-current-source-handle', $restart()['dependencies'], true), true],
    'dependency restart boundary' => [static fn (): mixed => in_array('sqlite-wal-restart-generation-boundary', $restart()['dependencies'], true), true],
    'dependency durable sidecar' => [static fn (): mixed => in_array('durable-sidecar-write', $restart()['dependencies'], true), true],
    'next wal sequence parse' => [static fn (): mixed => $nextWal()->header->checkpointSequence, 134],
    'checkpoint database length' => [static fn (): mixed => strlen($checkpointDatabaseBytes), strlen($databaseBytes)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader checkpoint restart current source next133 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan::plan('', $currentWal, $currentWalBytes, $databaseBytes, $nextWalBytes, [1], 2),
    'empty current wal rejected' => static fn () => SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $currentWal, '', $databaseBytes, $nextWalBytes, [1], 2),
    'empty database rejected' => static fn () => SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $currentWal, $currentWalBytes, '', $nextWalBytes, [1], 2),
    'empty next wal rejected' => static fn () => SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $currentWal, $currentWalBytes, $databaseBytes, '', [1], 2),
    'empty pages rejected' => static fn () => $plan(2, []),
    'non integer page rejected' => static fn () => $plan(2, ['2']),
    'zero page rejected' => static fn () => $plan(2, [0]),
    'negative reader rejected' => static fn () => $plan(-1),
    'reader past current rejected' => static fn () => $plan(6),
    'current bytes mismatch rejected' => static fn () => SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $currentWal, substr_replace($currentWalBytes, 'x', 100, 1), $databaseBytes, $nextWalBytes, [1], 2),
    'non advancing sequence rejected' => static fn () => $plan(2, [1], $makeWal([[2, 5, 'stale next133 frame']], 133, 0x13300102, 0x13300103)),
    'same salt rejected' => static fn () => $plan(2, [1], $makeWal([[2, 5, 'same salt next133 frame']], 134, 0x13300101, 0x13300102)),
];

foreach ($throws as $name => $callback) {
    $tests['wal reader checkpoint restart current source next133 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
