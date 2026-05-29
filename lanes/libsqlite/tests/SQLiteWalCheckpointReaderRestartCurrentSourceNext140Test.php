<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite-next140';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next140 schema base')
    . $page('next140 option base')
    . $page('next140 autoload base')
    . $page('next140 cron base')
    . $page('next140 transient base')
    . $page('next140 rewrite base')
    . $page('next140 session base');

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
    [2, 0, 'next140 option first reader draft'],
    [3, 7, 'next140 autoload first reader commit'],
    [2, 0, 'next140 option restarted reader draft'],
    [4, 0, 'next140 cron restarted reader draft'],
    [5, 7, 'next140 transient restarted reader commit'],
    [7, 7, 'next140 session restarted reader tail'],
], 140, 0x14000101, 0x14000102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);

$pathRestartWalBytes = $makeWal([
    [2, 0, 'next140 option path restart draft'],
    [4, 0, 'next140 cron path restart draft'],
    [6, 7, 'next140 rewrite path restart commit'],
    [7, 7, 'next140 session path restart tail'],
], 141, 0x14000102, 0x14000103);

$plan = static fn (
    int $oldReader = 2,
    int $restartReader = 6,
    array $pages = [1, 2, 3, 4, 5, 6, 7],
    ?string $restartBytes = null,
): array => SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan::plan(
    $GLOBALS['databasePath'],
    $GLOBALS['currentWal'],
    $GLOBALS['currentWalBytes'],
    $GLOBALS['databaseBytes'],
    $restartBytes ?? $GLOBALS['pathRestartWalBytes'],
    $pages,
    $oldReader,
    $restartReader
);
$restart = static fn (): array => $plan();
$narrow = static fn (): array => $plan(2, 6, [2, 4, 6]);
$sameFrame = static fn (): array => $plan(2, 2, [2, 3]);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-checkpoint-reader-restart-current-source-next140'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'reader_restart_reuses_current_wal_source_after_path_restart_checkpoint'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'old reader end frame' => [static fn (): mixed => $restart()['old_reader_end_frame'], 2],
    'restarted reader end frame' => [static fn (): mixed => $restart()['restarted_current_reader_end_frame'], 6],
    'current checkpoint sequence' => [static fn (): mixed => $restart()['current_checkpoint_sequence'], 140],
    'path restart checkpoint sequence' => [static fn (): mixed => $restart()['path_restart_checkpoint_sequence'], 141],
    'current frame count' => [static fn (): mixed => $restart()['current_frame_count'], 6],
    'path restart frame count' => [static fn (): mixed => $restart()['path_restart_frame_count'], 4],
    'checkpointed frame count' => [static fn (): mixed => $restart()['checkpointed_frame_count'], 5],
    'checkpoint wal header bytes length' => [static fn (): mixed => $restart()['checkpoint_wal_bytes_length'], 32],
    'current sha length' => [static fn (): mixed => strlen($restart()['current_wal_sha256']), 64],
    'path restart sha length' => [static fn (): mixed => strlen($restart()['path_restart_wal_sha256']), 64],
    'checkpoint database sha length' => [static fn (): mixed => strlen($restart()['checkpoint_database_sha256']), 64],
    'current salt' => [static fn (): mixed => $restart()['current_salt'], [0x14000101, 0x14000102]],
    'path restart salt' => [static fn (): mixed => $restart()['path_restart_salt'], [0x14000102, 0x14000103]],
    'old reader sources' => [static fn (): mixed => $restart()['old_reader_sources'], ['database', 'wal', 'wal', 'database', 'database', 'database', 'database']],
    'current restart sources' => [static fn (): mixed => $restart()['current_restart_sources'], ['database', 'wal', 'wal', 'wal', 'wal', 'database', 'wal']],
    'checkpoint sources' => [static fn (): mixed => $restart()['checkpoint_sources'], ['database', 'database', 'database', 'database', 'database', 'database', 'database']],
    'path restart sources' => [static fn (): mixed => $restart()['path_restart_sources'], ['database', 'wal', 'database', 'wal', 'database', 'wal', 'wal']],
    'old reader frame indexes' => [static fn (): mixed => $restart()['old_reader_frame_indexes'], [null, 1, 2, null, null, null, null]],
    'current restart frame indexes' => [static fn (): mixed => $restart()['current_restart_frame_indexes'], [null, 3, 2, 4, 5, null, 6]],
    'path restart frame indexes' => [static fn (): mixed => $restart()['path_restart_frame_indexes'], [null, 1, null, 2, null, 3, 4]],
    'current advanced pages' => [static fn (): mixed => $restart()['current_restart_advanced_pages'], [2, 4, 5, 7]],
    'path separated pages' => [static fn (): mixed => $restart()['path_restart_separated_pages'], [2, 4, 6, 7]],
    'current reader restart uses original source' => [static fn (): mixed => $restart()['current_reader_restart_uses_original_source'], true],
    'fresh path reader uses restarted generation' => [static fn (): mixed => $restart()['fresh_path_reader_uses_restarted_generation'], true],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['database>database>database>database', 'wal>wal>database>wal', 'wal>wal>database>database', 'database>wal>database>wal', 'database>wal>database>database', 'database>database>database>wal', 'database>wal>database>wal']],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'row count' => [static fn (): mixed => count($restart()['rows']), 7],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5, 6, 7]],
    'row one database unchanged' => [static fn (): mixed => $restart()['rows'][0]['path_restart_separated_from_current_source'], false],
    'row two old label' => [static fn (): mixed => $restart()['rows'][1]['old_reader_label'], 'next140 option first reader draft'],
    'row two current restart label' => [static fn (): mixed => $restart()['rows'][1]['current_restart_label'], 'next140 option restarted reader draft'],
    'row two path restart label' => [static fn (): mixed => $restart()['rows'][1]['path_restart_label'], 'next140 option path restart draft'],
    'row two checkpoint label' => [static fn (): mixed => $restart()['rows'][1]['checkpoint_label'], 'next140 option restarted reader draft'],
    'row two current source flag' => [static fn (): mixed => $restart()['rows'][1]['current_restart_uses_original_source'], true],
    'row two moved flag' => [static fn (): mixed => $restart()['rows'][1]['current_restart_moved_from_old_reader'], true],
    'row two checkpoint matches current' => [static fn (): mixed => $restart()['rows'][1]['checkpoint_matches_current_restart'], true],
    'row two path separated' => [static fn (): mixed => $restart()['rows'][1]['path_restart_separated_from_current_source'], true],
    'row three old label' => [static fn (): mixed => $restart()['rows'][2]['old_reader_label'], 'next140 autoload first reader commit'],
    'row three current restart label' => [static fn (): mixed => $restart()['rows'][2]['current_restart_label'], 'next140 autoload first reader commit'],
    'row three path restart source' => [static fn (): mixed => $restart()['rows'][2]['path_restart_source'], 'database'],
    'row four current restart label' => [static fn (): mixed => $restart()['rows'][3]['current_restart_label'], 'next140 cron restarted reader draft'],
    'row four path restart label' => [static fn (): mixed => $restart()['rows'][3]['path_restart_label'], 'next140 cron path restart draft'],
    'row five checkpoint label' => [static fn (): mixed => $restart()['rows'][4]['checkpoint_label'], 'next140 transient restarted reader commit'],
    'row five path restart label' => [static fn (): mixed => $restart()['rows'][4]['path_restart_label'], 'next140 transient restarted reader commit'],
    'row six current restart label' => [static fn (): mixed => $restart()['rows'][5]['current_restart_label'], 'next140 rewrite base'],
    'row six path restart label' => [static fn (): mixed => $restart()['rows'][5]['path_restart_label'], 'next140 rewrite path restart commit'],
    'row seven current restart label' => [static fn (): mixed => $restart()['rows'][6]['current_restart_label'], 'next140 session restarted reader tail'],
    'row seven path restart label' => [static fn (): mixed => $restart()['rows'][6]['path_restart_label'], 'next140 session path restart tail'],
    'narrow advanced pages' => [static fn (): mixed => $narrow()['current_restart_advanced_pages'], [2, 4]],
    'narrow separated pages' => [static fn (): mixed => $narrow()['path_restart_separated_pages'], [2, 4, 6]],
    'narrow transitions' => [static fn (): mixed => $narrow()['source_transitions'], ['wal>wal>database>wal', 'database>wal>database>wal', 'database>database>database>wal']],
    'same frame no advanced pages' => [static fn (): mixed => $sameFrame()['current_restart_advanced_pages'], []],
    'same frame source still split' => [static fn (): mixed => $sameFrame()['path_restart_separated_pages'], [2]],
    'operation list' => [static fn (): mixed => array_column($restart()['operations'], 'op'), ['checkpoint_database_write', 'replace_wal', 'restart_reader_on_current_source', 'open_fresh_reader_on_path']],
    'operation generations' => [static fn (): mixed => array_column($restart()['operations'], 'generation'), ['current-to-path-restart', 'path-restart', 'original-current-source', 'path-restart']],
    'dependency next140' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-restart-current-source-next140', $restart()['dependencies'], true), true],
    'dependency source reader restart' => [static fn (): mixed => in_array('sqlite-wal-current-source-reader-restart', $restart()['dependencies'], true), true],
    'dependency restart boundary' => [static fn (): mixed => in_array('sqlite-wal-restart-generation-boundary', $restart()['dependencies'], true), true],
    'dependency durable write' => [static fn (): mixed => in_array('durable-sidecar-write', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'avoids accepted next136 consecutive restart generations'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint reader restart current source next140 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan::plan('', $currentWal, $currentWalBytes, $databaseBytes, $pathRestartWalBytes, [1], 2, 6),
    'empty current wal rejected' => static fn () => SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan::plan($databasePath, $currentWal, '', $databaseBytes, $pathRestartWalBytes, [1], 2, 6),
    'empty restart wal rejected' => static fn () => SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan::plan($databasePath, $currentWal, $currentWalBytes, $databaseBytes, '', [1], 2, 6),
    'empty database rejected' => static fn () => SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan::plan($databasePath, $currentWal, $currentWalBytes, '', $pathRestartWalBytes, [1], 2, 6),
    'empty pages rejected' => static fn () => $plan(2, 6, []),
    'non integer page rejected' => static fn () => $plan(2, 6, ['2']),
    'zero page rejected' => static fn () => $plan(2, 6, [0]),
    'negative old reader rejected' => static fn () => $plan(-1, 6),
    'old reader past current rejected' => static fn () => $plan(7, 7),
    'restart reader moves backwards rejected' => static fn () => $plan(3, 2),
    'restart reader past current rejected' => static fn () => $plan(2, 7),
    'current bytes mismatch rejected' => static fn () => SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan::plan($databasePath, $currentWal, substr_replace($currentWalBytes, 'x', 100, 1), $databaseBytes, $pathRestartWalBytes, [1], 2, 6),
    'restart sequence stale rejected' => static fn () => $plan(2, 6, [1], $makeWal([[2, 7, 'stale next140 path restart']], 140, 0x14000102, 0x14000103)),
    'restart salt stale rejected' => static fn () => $plan(2, 6, [1], $makeWal([[2, 7, 'same salt next140 path restart']], 141, 0x14000101, 0x14000102)),
];

foreach ($throws as $name => $callback) {
    $tests['wal checkpoint reader restart current source next140 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
