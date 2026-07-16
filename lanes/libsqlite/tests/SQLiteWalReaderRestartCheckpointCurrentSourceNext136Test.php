<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite-next136';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next136 schema base')
    . $page('next136 option base')
    . $page('next136 autoload base')
    . $page('next136 cron base')
    . $page('next136 transient base')
    . $page('next136 rewrite base');

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
    [2, 0, 'next136 option current reader draft'],
    [3, 6, 'next136 autoload current reader commit'],
    [4, 0, 'next136 cron current reader draft'],
    [5, 6, 'next136 transient current reader commit'],
], 136, 0x13600101, 0x13600102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);

$firstRestartWalBytes = $makeWal([
    [2, 0, 'next136 option first restart draft'],
    [4, 6, 'next136 cron first restart commit'],
    [6, 6, 'next136 rewrite first restart tail'],
], 137, 0x13600102, 0x13600103);
$secondRestartWalBytes = $makeWal([
    [2, 0, 'next136 option second restart draft'],
    [5, 0, 'next136 transient second restart draft'],
    [6, 6, 'next136 rewrite second restart commit'],
    [4, 6, 'next136 cron second restart tail'],
], 138, 0x13600103, 0x13600104);

$plan = static fn (int $reader = 2, array $pages = [1, 2, 3, 4, 5, 6], ?string $firstBytes = null, ?string $secondBytes = null): array => SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan::plan(
    $GLOBALS['databasePath'],
    $GLOBALS['currentWal'],
    $GLOBALS['currentWalBytes'],
    $GLOBALS['databaseBytes'],
    $firstBytes ?? $GLOBALS['firstRestartWalBytes'],
    $secondBytes ?? $GLOBALS['secondRestartWalBytes'],
    $pages,
    $reader
);
$restart = static fn (): array => $plan();
$latest = static fn (): array => $plan(4);
$single = static fn (): array => $plan(2, [2]);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-reader-restart-checkpoint-current-source-next136'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'current_reader_keeps_original_wal_source_across_consecutive_restart_checkpoints'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart-restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'current reader frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'current checkpoint sequence' => [static fn (): mixed => $restart()['current_checkpoint_sequence'], 136],
    'first restart checkpoint sequence' => [static fn (): mixed => $restart()['first_restart_checkpoint_sequence'], 137],
    'second restart checkpoint sequence' => [static fn (): mixed => $restart()['second_restart_checkpoint_sequence'], 138],
    'current frame count' => [static fn (): mixed => $restart()['current_frame_count'], 4],
    'first restart frame count' => [static fn (): mixed => $restart()['first_restart_frame_count'], 3],
    'second restart frame count' => [static fn (): mixed => $restart()['second_restart_frame_count'], 4],
    'first checkpointed frame count' => [static fn (): mixed => $restart()['first_checkpointed_frame_count'], 4],
    'second checkpointed frame count' => [static fn (): mixed => $restart()['second_checkpointed_frame_count'], 3],
    'first restart header length' => [static fn (): mixed => $restart()['first_restart_header_bytes_length'], 32],
    'second restart header length' => [static fn (): mixed => $restart()['second_restart_header_bytes_length'], 32],
    'current sha length' => [static fn (): mixed => strlen($restart()['current_wal_sha256']), 64],
    'first sha length' => [static fn (): mixed => strlen($restart()['first_restart_wal_sha256']), 64],
    'second sha length' => [static fn (): mixed => strlen($restart()['second_restart_wal_sha256']), 64],
    'first checkpoint sha length' => [static fn (): mixed => strlen($restart()['first_checkpoint_database_sha256']), 64],
    'second checkpoint sha length' => [static fn (): mixed => strlen($restart()['second_checkpoint_database_sha256']), 64],
    'current salt' => [static fn (): mixed => $restart()['current_salt'], [0x13600101, 0x13600102]],
    'first restart salt' => [static fn (): mixed => $restart()['first_restart_salt'], [0x13600102, 0x13600103]],
    'second restart salt' => [static fn (): mixed => $restart()['second_restart_salt'], [0x13600103, 0x13600104]],
    'reader preserved' => [static fn (): mixed => $restart()['current_reader_preserved_by_source_handle'], true],
    'second restart replaced path' => [static fn (): mixed => $restart()['second_restart_replaced_wal_path'], true],
    'changed pages' => [static fn (): mixed => $restart()['changed_page_numbers'], [2, 4, 5, 6]],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['database', 'wal', 'wal', 'database', 'database', 'database']],
    'first restart sources' => [static fn (): mixed => $restart()['first_restart_sources'], ['database', 'wal', 'database', 'wal', 'database', 'wal']],
    'second restart sources' => [static fn (): mixed => $restart()['second_restart_sources'], ['database', 'wal', 'database', 'wal', 'wal', 'wal']],
    'first restart frames' => [static fn (): mixed => $restart()['first_restart_frame_indexes'], [null, 1, null, 2, null, 3]],
    'second restart frames' => [static fn (): mixed => $restart()['second_restart_frame_indexes'], [null, 1, null, 4, 2, 3]],
    'row count' => [static fn (): mixed => count($restart()['rows']), 6],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5, 6]],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['database>database>database>database>database', 'wal>database>wal>database>wal', 'wal>database>database>database>database', 'database>database>wal>database>wal', 'database>database>database>database>wal', 'database>database>wal>database>wal']],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'row one unchanged' => [static fn (): mixed => $restart()['rows'][0]['current_survives_second_restart'], false],
    'row two changed' => [static fn (): mixed => $restart()['rows'][1]['current_survives_second_restart'], true],
    'row three remains checkpointed current' => [static fn (): mixed => $restart()['rows'][2]['current_survives_second_restart'], false],
    'row four changed' => [static fn (): mixed => $restart()['rows'][3]['current_survives_second_restart'], true],
    'row five changed' => [static fn (): mixed => $restart()['rows'][4]['current_survives_second_restart'], true],
    'row six changed' => [static fn (): mixed => $restart()['rows'][5]['current_survives_second_restart'], true],
    'row two current label' => [static fn (): mixed => $restart()['rows'][1]['current_label'], 'next136 option current reader draft'],
    'row two first label' => [static fn (): mixed => $restart()['rows'][1]['first_restart_label'], 'next136 option first restart draft'],
    'row two second label' => [static fn (): mixed => $restart()['rows'][1]['second_restart_label'], 'next136 option second restart draft'],
    'row three current label' => [static fn (): mixed => $restart()['rows'][2]['current_label'], 'next136 autoload current reader commit'],
    'row three second checkpoint label' => [static fn (): mixed => $restart()['rows'][2]['second_checkpoint_label'], 'next136 autoload current reader commit'],
    'row four first checkpoint label' => [static fn (): mixed => $restart()['rows'][3]['first_checkpoint_label'], 'next136 cron current reader draft'],
    'row four second label' => [static fn (): mixed => $restart()['rows'][3]['second_restart_label'], 'next136 cron second restart tail'],
    'row five first checkpoint label' => [static fn (): mixed => $restart()['rows'][4]['first_checkpoint_label'], 'next136 transient current reader commit'],
    'row five second label' => [static fn (): mixed => $restart()['rows'][4]['second_restart_label'], 'next136 transient second restart draft'],
    'row six first label' => [static fn (): mixed => $restart()['rows'][5]['first_restart_label'], 'next136 rewrite first restart tail'],
    'row six checkpointed before second' => [static fn (): mixed => $restart()['rows'][5]['first_restart_checkpointed_before_second'], true],
    'single changed pages' => [static fn (): mixed => $single()['changed_page_numbers'], [2]],
    'single transition' => [static fn (): mixed => $single()['source_transitions'], ['wal>database>wal>database>wal']],
    'latest current sources' => [static fn (): mixed => $latest()['current_sources'], ['database', 'wal', 'wal', 'wal', 'wal', 'database']],
    'latest row four label' => [static fn (): mixed => $latest()['rows'][3]['current_label'], 'next136 cron current reader draft'],
    'latest changed pages' => [static fn (): mixed => $latest()['changed_page_numbers'], [2, 4, 5, 6]],
    'operation list' => [static fn (): mixed => array_column($restart()['operations'], 'op'), ['checkpoint_database_write', 'replace_wal', 'checkpoint_database_write', 'replace_wal', 'keep_reader_source']],
    'operation generations' => [static fn (): mixed => array_column($restart()['operations'], 'generation'), ['current-to-first-restart', 'first-restart', 'first-to-second-restart', 'second-restart', 'original-current-reader']],
    'dependency next136' => [static fn (): mixed => in_array('sqlite-wal-reader-restart-checkpoint-current-source-next136', $restart()['dependencies'], true), true],
    'dependency source handle' => [static fn (): mixed => in_array('sqlite-wal-current-source-handle', $restart()['dependencies'], true), true],
    'dependency restart boundary' => [static fn (): mixed => in_array('sqlite-wal-restart-generation-boundary', $restart()['dependencies'], true), true],
    'dependency durable sidecar' => [static fn (): mixed => in_array('durable-sidecar-write', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'avoids accepted next133 single restart replacement'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader restart checkpoint current source next136 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan::plan('', $currentWal, $currentWalBytes, $databaseBytes, $firstRestartWalBytes, $secondRestartWalBytes, [1], 2),
    'empty current wal rejected' => static fn () => SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan::plan($databasePath, $currentWal, '', $databaseBytes, $firstRestartWalBytes, $secondRestartWalBytes, [1], 2),
    'empty first wal rejected' => static fn () => SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan::plan($databasePath, $currentWal, $currentWalBytes, $databaseBytes, '', $secondRestartWalBytes, [1], 2),
    'empty second wal rejected' => static fn () => SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan::plan($databasePath, $currentWal, $currentWalBytes, $databaseBytes, $firstRestartWalBytes, '', [1], 2),
    'empty database rejected' => static fn () => SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan::plan($databasePath, $currentWal, $currentWalBytes, '', $firstRestartWalBytes, $secondRestartWalBytes, [1], 2),
    'empty pages rejected' => static fn () => $plan(2, []),
    'non integer page rejected' => static fn () => $plan(2, ['2']),
    'zero page rejected' => static fn () => $plan(2, [0]),
    'negative reader rejected' => static fn () => $plan(-1),
    'reader past current rejected' => static fn () => $plan(5),
    'current bytes mismatch rejected' => static fn () => SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan::plan($databasePath, $currentWal, substr_replace($currentWalBytes, 'x', 100, 1), $databaseBytes, $firstRestartWalBytes, $secondRestartWalBytes, [1], 2),
    'first sequence stale rejected' => static fn () => $plan(2, [1], $makeWal([[2, 6, 'stale next136 first']], 136, 0x13600102, 0x13600103), null),
    'first salt stale rejected' => static fn () => $plan(2, [1], $makeWal([[2, 6, 'same salt next136 first']], 137, 0x13600101, 0x13600102), null),
    'second sequence stale rejected' => static fn () => $plan(2, [1], null, $makeWal([[2, 6, 'stale next136 second']], 137, 0x13600103, 0x13600104)),
    'second salt stale rejected' => static fn () => $plan(2, [1], null, $makeWal([[2, 6, 'same salt next136 second']], 138, 0x13600102, 0x13600103)),
];

foreach ($throws as $name => $callback) {
    $tests['wal reader restart checkpoint current source next136 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
