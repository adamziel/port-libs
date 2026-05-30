<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next164.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = [
    1 => $page('next164 dirty schema after option import'),
    2 => $page('next164 dirty wp_options root after option import'),
    3 => $page('next164 dirty active_plugins after option import'),
    4 => $page('next164 dirty autoload index after option import'),
    5 => $page('next164 dirty cron option after option import'),
];
$hot = [
    2 => $page('next164 hot journal clean wp_options root'),
    4 => $page('next164 hot journal clean autoload index'),
];
$savepointBefore = [
    3 => $page('next164 savepoint before active_plugins retry'),
    5 => $page('next164 savepoint before cron retry'),
];
$databaseBytes = implode('', $database);

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
    [1, 0, 'next164 current wal schema draft'],
    [2, 5, 'next164 current wal wp_options commit'],
    [4, 0, 'next164 current wal autoload draft'],
    [5, 5, 'next164 current wal cron commit'],
], 164, 0x16400101, 0x16400102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next164 next wal active_plugins retry draft'],
    [5, 5, 'next164 next wal cron commit'],
], 165, 0x16500101, 0x16500102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$checkpointPages = [1, 2, 3, 4, 5];
$baseForTokens = static function () use ($databasePath, $databaseBytes, $pageSize, $hot, $savepointBefore, $currentWal, $currentWalBytes, $nextWal, $nextWalBytes, $checkpointPages, $page): array {
    $dummyCache = [
        1 => ['image' => $page('next164 current wal schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1],
    ];

    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerCacheCheckpointPlan(
        $databasePath,
        $databaseBytes,
        $pageSize,
        'plugin-import-next164',
        $hot,
        $savepointBefore,
        $currentWal,
        $currentWalBytes,
        $nextWal,
        $nextWalBytes,
        $dummyCache,
        $checkpointPages,
        'restart',
        4,
        164
    );
};
$tokenBase = $baseForTokens();
$currentToken = $tokenBase['current_source_token'];
$nextToken = $tokenBase['next_source_token'];

$cache = [
    1 => ['image' => $page('next164 current wal schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'label' => 'schema cache current'],
    2 => ['image' => $page('next164 current wal wp_options commit'), 'source_id' => 'old-token', 'epoch' => $currentToken['epoch'], 'label' => 'wp_options stale token'],
    3 => ['image' => $savepointBefore[3], 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'] - 1, 'label' => 'active_plugins stale epoch'],
    4 => ['image' => $page('next164 stale autoload cache'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'label' => 'autoload stale image'],
    5 => ['image' => $page('next164 current wal cron commit'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'dirty' => true, 'label' => 'cron dirty cache'],
];
$readers = [
    ['name' => 'wp-current-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
    ['name' => 'wp-pinned-options', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'pinned' => true],
    ['name' => 'wp-stale-token', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ['name' => 'wp-stale-epoch', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'] - 1],
    ['name' => 'wp-next-reader', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch']],
    ['name' => 'wp-dirty-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'dirty' => true],
    ['name' => 'wp-closed-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'closed' => true],
];

$plan = static fn (?array $readerRows = null, ?array $cacheRows = null, string $mode = 'restart', int $readerEndFrame = 4): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next164Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next164',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    $cacheRows ?? $cache,
    $checkpointPages,
    $readerRows ?? $readers,
    $mode,
    $readerEndFrame,
    164
);

$restart = static fn (): array => $plan();

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next164'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'checkpoint_current_source_readers_admitted_after_hot_journal_savepoint_rollback'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-import-next164'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'reader frame' => [static fn (): mixed => $restart()['reader_end_frame'], 4],
    'base status' => [static fn (): mixed => $restart()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next161'],
    'base reason' => [static fn (): mixed => $restart()['base_reason'], 'reader_cache_rebased_after_hot_journal_savepoint_checkpoint_current_source'],
    'current token' => [static fn (): mixed => $restart()['current_source_token'], $currentToken],
    'next token' => [static fn (): mixed => $restart()['next_source_token'], $nextToken],
    'current durable action' => [static fn (): mixed => $restart()['current_durable']['wal_action'], 'preserve_wal'],
    'next durable action' => [static fn (): mixed => $restart()['next_durable']['wal_action'], 'preserve_wal'],
    'checkpoint pages' => [static fn (): mixed => $restart()['checkpoint_page_numbers'], [1, 2, 3, 4, 5]],
    'hot journal pages' => [static fn (): mixed => $restart()['hot_journal_page_numbers'], [2, 4]],
    'savepoint rollback pages' => [static fn (): mixed => $restart()['savepoint_rollback_page_numbers'], [3, 5]],
    'retained cache' => [static fn (): mixed => $restart()['retained_cache_page_numbers'], [1]],
    'invalidated cache' => [static fn (): mixed => $restart()['invalidated_cache_page_numbers'], [2, 3, 4, 5]],
    'requires reopen' => [static fn (): mixed => $restart()['requires_reader_reopen'], true],
    'admitted readers' => [static fn (): mixed => $restart()['admitted_reader_names'], ['wp-current-schema']],
    'reopen readers' => [static fn (): mixed => $restart()['reopen_reader_names'], ['wp-pinned-options', 'wp-stale-token', 'wp-stale-epoch', 'wp-next-reader', 'wp-dirty-reader', 'wp-closed-reader']],
    'reopen count' => [static fn (): mixed => $restart()['reader_reopen_count'], 6],
    'reader row count' => [static fn (): mixed => count($restart()['reader_rows']), 7],
    'reader one admitted' => [static fn (): mixed => $restart()['reader_rows'][0]['admitted'], true],
    'reader one reason' => [static fn (): mixed => $restart()['reader_rows'][0]['reason'], 'reader_matches_checkpoint_current_source_token'],
    'reader two reason' => [static fn (): mixed => $restart()['reader_rows'][1]['reason'], 'pinned_reader_must_reopen_after_cache_rebase'],
    'reader three reason' => [static fn (): mixed => $restart()['reader_rows'][2]['reason'], 'reader_source_token_predates_checkpoint_current_source'],
    'reader four reason' => [static fn (): mixed => $restart()['reader_rows'][3]['reason'], 'reader_epoch_predates_checkpoint_current_source'],
    'reader five reason' => [static fn (): mixed => $restart()['reader_rows'][4]['reason'], 'reader_already_reopened_on_next_wal_source'],
    'reader six reason' => [static fn (): mixed => $restart()['reader_rows'][5]['reason'], 'reader_dirty_after_failed_savepoint'],
    'reader seven reason' => [static fn (): mixed => $restart()['reader_rows'][6]['reason'], 'reader_closed_before_checkpoint_publish'],
    'reader reasons' => [static fn (): mixed => $restart()['reader_admission_reasons'], [
        'reader_matches_checkpoint_current_source_token',
        'pinned_reader_must_reopen_after_cache_rebase',
        'reader_source_token_predates_checkpoint_current_source',
        'reader_epoch_predates_checkpoint_current_source',
        'reader_already_reopened_on_next_wal_source',
        'reader_dirty_after_failed_savepoint',
        'reader_closed_before_checkpoint_publish',
    ]],
    'operation has admit' => [static fn (): mixed => in_array('admit_reader_on_checkpoint_current_source_next164', $restart()['operation_names'], true), true],
    'operation has reopen' => [static fn (): mixed => in_array('force_reader_reopen_for_next_wal_source_next164', $restart()['operation_names'], true), true],
    'operation count' => [static fn (): mixed => count($restart()['operation_names']), 18],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next164', $restart()['dependencies'], true), true],
    'reader admission dependency' => [static fn (): mixed => in_array('sqlite-wal-reader-admission-current-source-token', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'does not repeat next161'), true],
    'base retained row reason' => [static fn (): mixed => $restart()['base_plan']['rows'][0]['cache_reason'], 'reader_cache_matches_checkpoint_current_source_token'],
    'base invalidated dirty reason' => [static fn (): mixed => $restart()['base_plan']['rows'][4]['cache_reason'], 'reader_cache_dirty_after_failed_savepoint'],
    'truncate mode changes source token' => [static fn (): mixed => $plan($readers, $cache, 'truncate', 0)['reader_rows'][1]['reason'], 'reader_source_token_predates_checkpoint_current_source'],
    'single admitted only blocks status' => [static fn (): mixed => $plan([['name' => 'only-current', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next164'],
    'no pinned admits second reader when cache clean' => [static fn (): mixed => $plan([
        ['name' => 'current-a', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'current-b', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'old', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ])['admitted_reader_names'], ['current-a', 'current-b']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next164 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty readers rejected' => static fn () => $plan([]),
    'empty reader name rejected' => static fn () => $plan([['name' => '', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]]),
    'empty reader token rejected' => static fn () => $plan([['name' => 'bad', 'source_id' => '', 'epoch' => $currentToken['epoch']]]),
    'bad reader epoch rejected' => static fn () => $plan([['name' => 'bad', 'source_id' => $currentToken['id'], 'epoch' => 0]]),
    'bad base mode rejected' => static fn () => $plan($readers, $cache, 'passive'),
    'reader frame outside wal rejected' => static fn () => $plan($readers, $cache, 'restart', 5),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next164 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
