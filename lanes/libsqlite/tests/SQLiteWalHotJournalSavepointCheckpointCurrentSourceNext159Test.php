<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next159.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirty = [
    1 => $page('next159 dirty sqlite schema after crashed import'),
    2 => $page('next159 dirty wp_options root after crashed import'),
    3 => $page('next159 dirty active_plugins after crashed import'),
    4 => $page('next159 dirty autoload index after crashed import'),
    5 => $page('next159 dirty transient rows after crashed import'),
    6 => $page('next159 dirty cron array after crashed import'),
];
$clean = [
    1 => $page('next159 clean sqlite schema before crashed import'),
    2 => $page('next159 clean wp_options root before crashed import'),
    3 => $page('next159 clean active_plugins before crashed import'),
    4 => $page('next159 clean autoload index before crashed import'),
    5 => $page('next159 clean transient rows before crashed import'),
    6 => $page('next159 clean cron array before crashed import'),
];
$databaseBytes = implode('', $dirty);

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

$currentWalBytes = $makeWalBytes([
    [1, 0, 'next159 current wal schema draft'],
    [2, 6, 'next159 current wal wp_options commit'],
    [3, 0, 'next159 current wal active_plugins draft'],
    [4, 6, 'next159 current wal autoload commit'],
    [5, 0, 'next159 current wal transient draft'],
], 159, 0x15900101, 0x15900102);
$nextWalBytes = $makeWalBytes([
    [2, 0, 'next159 next wal wp_options retry draft'],
    [5, 0, 'next159 next wal transient retry draft'],
    [6, 6, 'next159 next wal cron retry commit'],
], 160, 0x16000101, 0x16000102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$plan = static fn (
    string $mode = 'restart',
    int $readerEndFrame = 5,
    array $pages = [1, 2, 3, 4, 5, 6],
    bool $reservedLock = false
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next159Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next159',
    [2 => $clean[2], 4 => $clean[4], 6 => $clean[6]],
    [2 => $dirty[2], 3 => $dirty[3], 4 => $dirty[4], 5 => $dirty[5], 6 => $dirty[6]],
    [
        2 => $page('next159 current savepoint wp_options draft'),
        4 => $page('next159 current savepoint autoload draft'),
        6 => $page('next159 current savepoint cron draft'),
    ],
    [
        3 => $page('next159 next savepoint active_plugins retry'),
        5 => $page('next159 next savepoint transient retry'),
    ],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    $pages,
    $readerEndFrame,
    $mode,
    159,
    $reservedLock,
    true,
    true,
);

$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$full = static fn (): array => $plan('full');
$partial = static fn (): array => $plan('restart', 3, [2, 3, 6]);
$blocked = static fn (): array => $plan('restart', 5, [2, 6], true);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next159'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'hot_journal_recovered_savepoint_retry_checkpointed_before_next_wal_source'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-import-next159'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'reader end frame' => [static fn (): mixed => $restart()['reader_end_frame'], 5],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'checkpoint not busy' => [static fn (): mixed => $restart()['checkpoint_busy'], false],
    'checkpoint reason' => [static fn (): mixed => $restart()['checkpoint_reason'], 'uncommitted_frames_after_last_commit'],
    'checkpoint action' => [static fn (): mixed => $restart()['checkpoint_wal_action'], 'preserve_wal'],
    'next checkpoint action' => [static fn (): mixed => $restart()['next_checkpoint_wal_action'], 'preserve_wal'],
    'current checkpoint sequence' => [static fn (): mixed => $restart()['current_wal_source']['checkpoint_sequence'], 159],
    'next checkpoint sequence' => [static fn (): mixed => $restart()['next_wal_source']['checkpoint_sequence'], 160],
    'current salt one' => [static fn (): mixed => $restart()['current_wal_source']['salt_1'], 0x15900101],
    'next salt one' => [static fn (): mixed => $restart()['next_wal_source']['salt_1'], 0x16000101],
    'current frame count' => [static fn (): mixed => $restart()['current_wal_source']['frame_count'], 5],
    'next frame count' => [static fn (): mixed => $restart()['next_wal_source']['frame_count'], 3],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'wal', 'wal', 'database', 'database']],
    'checkpoint sources' => [static fn (): mixed => $restart()['checkpoint_sources'], ['wal', 'wal', 'wal', 'wal', 'database', 'database']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'wal', 'database', 'database', 'wal', 'wal']],
    'next checkpoint sources' => [static fn (): mixed => $restart()['next_checkpoint_sources'], ['database', 'database', 'database', 'database', 'database', 'database']],
    'current frames' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, 3, 4, null, null]],
    'checkpoint frames' => [static fn (): mixed => $restart()['checkpoint_frame_indexes'], [1, 2, 3, 4, null, null]],
    'next frames' => [static fn (): mixed => $restart()['next_frame_indexes'], [null, 1, null, null, 2, 3]],
    'current labels' => [static fn (): mixed => $restart()['current_labels'], [
        'next159 current wal schema draft',
        'next159 current wal wp_options commit',
        'next159 current wal active_plugins draft',
        'next159 current wal autoload commit',
        'next159 dirty transient rows after crashed import',
        'next159 clean cron array before crashed import',
    ]],
    'checkpoint labels match current' => [static fn (): mixed => $restart()['checkpoint_labels'], $restart()['current_labels']],
    'next labels' => [static fn (): mixed => $restart()['next_labels'], [
        'next159 dirty sqlite schema after crashed import',
        'next159 next wal wp_options retry draft',
        'next159 dirty active_plugins after crashed import',
        'next159 clean autoload index before crashed import',
        'next159 next wal transient retry draft',
        'next159 next wal cron retry commit',
    ]],
    'next checkpoint labels match next' => [static fn (): mixed => $restart()['next_checkpoint_labels'], $restart()['next_labels']],
    'checkpoint matches current' => [static fn (): mixed => $restart()['checkpoint_matches_current_reader'], true],
    'next checkpoint matches next' => [static fn (): mixed => $restart()['next_checkpoint_matches_next_reader'], true],
    'checkpoint uses database' => [static fn (): mixed => $restart()['checkpoint_uses_database_pages'], false],
    'next checkpoint uses database' => [static fn (): mixed => $restart()['next_checkpoint_uses_database_pages'], true],
    'next separate wal' => [static fn (): mixed => $restart()['next_uses_separate_wal_source'], true],
    'source transition first' => [static fn (): mixed => $restart()['source_transitions'][0], 'wal>checkpoint>wal>next-wal>database>next-checkpoint>database'],
    'source transition second' => [static fn (): mixed => $restart()['source_transitions'][1], 'wal>checkpoint>wal>next-wal>wal>next-checkpoint>database'],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'current durable wal preserved after uncommitted tail' => [static fn (): mixed => strlen($restart()['current_durable']['wal_bytes']), strlen($currentWalBytes)],
    'current durable database has current schema' => [static fn (): mixed => str_contains($restart()['current_durable']['database_bytes'], 'current wal schema draft'), true],
    'current durable database has hot clean cron' => [static fn (): mixed => str_contains($restart()['current_durable']['database_bytes'], 'clean cron array'), true],
    'next durable keeps wal bytes' => [static fn (): mixed => strlen($restart()['next_durable']['wal_bytes']), strlen($nextWalBytes)],
    'operation count' => [static fn (): mixed => count($restart()['operations']), 5],
    'operation one reason' => [static fn (): mixed => $restart()['operation_reasons'][0], 'checkpoint_hot_journal_savepoint_current_source_database'],
    'operation two preserves current' => [static fn (): mixed => $restart()['operation_reasons'][1], 'preserve_current_wal_after_reader_blocked_checkpoint'],
    'operation install next' => [static fn (): mixed => $restart()['operation_reasons'][2], 'install_next_wal_generation_after_current_checkpoint'],
    'operation sync db' => [static fn (): mixed => $restart()['operation_reasons'][3], 'sync_database_after_hot_journal_savepoint_checkpoint'],
    'operation sync wal' => [static fn (): mixed => $restart()['operation_reasons'][4], 'sync_next_wal_generation_after_checkpoint'],
    'payload current database key' => [static fn (): mixed => in_array($databasePath . '#next159-checkpoint-current', $restart()['payload_keys'], true), true],
    'payload current wal key' => [static fn (): mixed => in_array($databasePath . '-wal#next159-checkpoint-current', $restart()['payload_keys'], true), true],
    'payload next database key' => [static fn (): mixed => in_array($databasePath . '#next159-checkpoint-next', $restart()['payload_keys'], true), true],
    'payload next wal key' => [static fn (): mixed => in_array($databasePath . '-wal#next159-checkpoint-next', $restart()['payload_keys'], true), true],
    'base status' => [static fn (): mixed => $restart()['base_plan']['status'], 'pager-savepoint-wal-hot-journal-current-source-next148'],
    'base hot recovered' => [static fn (): mixed => $restart()['base_plan']['hot_recovered'], true],
    'base retry matched reader' => [static fn (): mixed => $restart()['base_plan']['retry_matches_current_reader'], true],
    'row count' => [static fn (): mixed => count($restart()['rows']), 6],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5, 6]],
    'row two checkpoint label' => [static fn (): mixed => $restart()['rows'][1]['checkpoint_label'], 'next159 current wal wp_options commit'],
    'row five next label' => [static fn (): mixed => $restart()['rows'][4]['next_label'], 'next159 next wal transient retry draft'],
    'row six next checkpoint label' => [static fn (): mixed => $restart()['rows'][5]['next_checkpoint_label'], 'next159 next wal cron retry commit'],
    'truncate action' => [static fn (): mixed => $truncate()['checkpoint_wal_action'], 'preserve_wal'],
    'truncate operation' => [static fn (): mixed => $truncate()['operation_reasons'][1], 'preserve_current_wal_after_reader_blocked_checkpoint'],
    'full action' => [static fn (): mixed => $full()['checkpoint_wal_action'], 'preserve_wal'],
    'full keeps current wal' => [static fn (): mixed => strlen($full()['current_durable']['wal_bytes']), strlen($currentWalBytes)],
    'partial current sources' => [static fn (): mixed => $partial()['current_sources'], ['wal', 'database', 'database']],
    'partial checkpoint labels' => [static fn (): mixed => $partial()['checkpoint_labels'], [
        'next159 current wal wp_options commit',
        'next159 current wal active_plugins draft',
        'next159 clean cron array before crashed import',
    ]],
    'partial next frames' => [static fn (): mixed => $partial()['next_frame_indexes'], [1, null, 3]],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next159'],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next159', $restart()['dependencies'], true), true],
    'dependency wordpress marker' => [static fn (): mixed => in_array('wordpress-import-hot-journal-savepoint-checkpoint-current-source', $restart()['dependencies'], true), true],
    'dependency closure text' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap text' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'checkpoint materialization'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next159 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad mode rejected' => static fn () => $plan('passive'),
    'empty reader pages rejected' => static fn () => $plan('restart', 5, []),
    'reader past current wal rejected' => static fn () => $plan('restart', 6),
    'zero reader page rejected' => static fn () => $plan('restart', 5, [0]),
    'non integer reader page rejected' => static fn () => $plan('restart', 5, ['2']),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next159 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
