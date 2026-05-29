<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next170.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = [
    1 => $page('next170 dirty schema page before recovery'),
    2 => $page('next170 dirty wp_options page before recovery'),
    3 => $page('next170 dirty active_plugins page before recovery'),
    4 => $page('next170 dirty autoload index page before recovery'),
];
$databaseBytes = implode('', $database);
$hotJournal = [
    2 => $page('next170 hot journal clean wp_options page'),
];
$savepointBefore = [
    3 => $page('next170 savepoint before active_plugins retry'),
];

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
    [1, 0, 'next170 wal schema draft'],
    [2, 0, 'next170 wal wp_options uncommitted draft'],
    [3, 4, 'next170 wal active_plugins committed'],
    [4, 4, 'next170 wal autoload committed'],
], 170, 0x17000101, 0x17000102);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$currentGeneration = [
    'checkpoint_sequence' => $wal->header->checkpointSequence,
    'salt' => [$wal->header->salt1, $wal->header->salt2],
    'frame_count' => $wal->frameCount(),
];
$rolledBack = $database;
$rolledBack[2] = $hotJournal[2];
$rolledBack[3] = $savepointBefore[3];
$currentImages = [
    1 => $page('next170 wal schema draft'),
    2 => $page('next170 wal wp_options uncommitted draft'),
    3 => $page('next170 wal active_plugins committed'),
    4 => $page('next170 wal autoload committed'),
];
$cache = [
    1 => ['image' => $currentImages[1], 'checkpoint_sequence' => 169, 'salt' => $currentGeneration['salt'], 'frame_count' => 4, 'label' => 'schema stale checkpoint sequence'],
    2 => ['image' => $currentImages[2], 'checkpoint_sequence' => 170, 'salt' => [0x17000100, 0x17000102], 'frame_count' => 4, 'label' => 'wp_options stale salt'],
    3 => ['image' => $currentImages[3], 'checkpoint_sequence' => 170, 'salt' => $currentGeneration['salt'], 'frame_count' => 4, 'label' => 'active_plugins current generation'],
    4 => ['image' => $currentImages[4], 'checkpoint_sequence' => 170, 'salt' => $currentGeneration['salt'], 'frame_count' => 3, 'label' => 'autoload stale frame count'],
];

$plan = static fn (
    string $mode = 'restart',
    ?int $readerEndFrame = null,
    ?array $cacheArg = null,
    ?array $pagesArg = null,
    ?array $hotArg = null,
    ?array $beforeArg = null,
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next170Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next170',
    $hotArg ?? $hotJournal,
    $beforeArg ?? $savepointBefore,
    $wal,
    $walBytes,
    $cacheArg ?? $cache,
    $pagesArg ?? [1, 2, 3, 4],
    $mode,
    $readerEndFrame,
);

$restart = static fn (): array => $plan('restart', null);
$truncate = static fn (): array => $plan('truncate', null);
$pinnedFull = static function () use ($plan, $cache, $currentGeneration, $currentImages): array {
    $currentCache = $cache;
    $pinnedImages = [
        1 => $GLOBALS['database'][1],
        2 => $GLOBALS['hotJournal'][2],
        3 => $GLOBALS['savepointBefore'][3],
        4 => $GLOBALS['database'][4],
    ];
    foreach ([1, 2, 3, 4] as $pageNumber) {
        $currentCache[$pageNumber] = [
            'image' => $pinnedImages[$pageNumber],
            'checkpoint_sequence' => $currentGeneration['checkpoint_sequence'],
            'salt' => $currentGeneration['salt'],
            'frame_count' => $currentGeneration['frame_count'],
            'label' => 'current generation page ' . $pageNumber,
        ];
    }

    return $plan('full', 2, $currentCache);
};
$passive = static function () use ($plan, $cache, $currentGeneration, $currentImages): array {
    $currentCache = $cache;
    $pinnedImages = [
        1 => $GLOBALS['database'][1],
        2 => $GLOBALS['hotJournal'][2],
        3 => $GLOBALS['savepointBefore'][3],
        4 => $GLOBALS['database'][4],
    ];
    foreach ([1, 2, 3, 4] as $pageNumber) {
        $currentCache[$pageNumber] = [
            'image' => $pinnedImages[$pageNumber],
            'checkpoint_sequence' => $currentGeneration['checkpoint_sequence'],
            'salt' => $currentGeneration['salt'],
            'frame_count' => $currentGeneration['frame_count'],
        ];
    }

    return $plan('passive', 2, $currentCache);
};

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next170'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'reader_cache_generation_is_fenced_when_checkpoint_resets_or_truncates_wal_after_hot_journal_savepoint'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-import-next170'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'reader frame' => [static fn (): mixed => $restart()['reader_end_frame'], null],
    'current checkpoint sequence' => [static fn (): mixed => $restart()['current_generation']['checkpoint_sequence'], 170],
    'current salt' => [static fn (): mixed => $restart()['current_generation']['salt'], [0x17000101, 0x17000102]],
    'current frame count' => [static fn (): mixed => $restart()['current_generation']['frame_count'], 4],
    'after checkpoint sequence increments' => [static fn (): mixed => $restart()['after_generation']['checkpoint_sequence'], 171],
    'after salt increments' => [static fn (): mixed => $restart()['after_generation']['salt'], [0x17000102, 0x17000102]],
    'after frame count reset' => [static fn (): mixed => $restart()['after_generation']['frame_count'], 0],
    'generation changed' => [static fn (): mixed => $restart()['generation_changed'], true],
    'restart wal action' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal'],
    'restart reason' => [static fn (): mixed => $restart()['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'restart not busy' => [static fn (): mixed => $restart()['checkpoint_busy'], false],
    'checkpointed frame count' => [static fn (): mixed => $restart()['checkpointed_frame_count'], 4],
    'remaining frames' => [static fn (): mixed => $restart()['remaining_committed_frame_count'], 0],
    'restart wal header bytes retained' => [static fn (): mixed => $restart()['wal_bytes_length_after_checkpoint'], 32],
    'retained cache empty on generation reset' => [static fn (): mixed => $restart()['retained_cache_page_numbers'], []],
    'invalidated pages' => [static fn (): mixed => $restart()['invalidated_cache_page_numbers'], [1, 2, 3, 4]],
    'requires reopen' => [static fn (): mixed => $restart()['requires_reader_reopen'], true],
    'page numbers' => [static fn (): mixed => $restart()['page_numbers'], [1, 2, 3, 4]],
    'current labels' => [static fn (): mixed => $restart()['current_labels'], [
        'next170 wal schema draft',
        'next170 wal wp_options uncommitted draft',
        'next170 wal active_plugins committed',
        'next170 wal autoload committed',
    ]],
    'after labels stable' => [static fn (): mixed => $restart()['after_labels'], $restart()['current_labels']],
    'cache reasons' => [static fn (): mixed => $restart()['cache_reasons'], [
        'reader_cache_not_from_current_wal_generation',
        'reader_cache_not_from_current_wal_generation',
        'reader_cache_generation_predates_checkpoint_reset',
        'reader_cache_not_from_current_wal_generation',
    ]],
    'row one stale checkpoint sequence' => [static fn (): mixed => $restart()['rows'][0]['cache_reason'], 'reader_cache_not_from_current_wal_generation'],
    'row two stale salt' => [static fn (): mixed => $restart()['rows'][1]['cache_reason'], 'reader_cache_not_from_current_wal_generation'],
    'row three identical image invalidated by reset' => [static fn (): mixed => $restart()['rows'][2]['cache_reason'], 'reader_cache_generation_predates_checkpoint_reset'],
    'row three image stable' => [static fn (): mixed => $restart()['rows'][2]['image_stable'], true],
    'row three generation changed' => [static fn (): mixed => $restart()['rows'][2]['generation_changed'], true],
    'row four stale frame count' => [static fn (): mixed => $restart()['rows'][3]['cache_reason'], 'reader_cache_not_from_current_wal_generation'],
    'operation prefix' => [static fn (): mixed => array_slice($restart()['operation_names'], 0, 2), [
        'recover_hot_journal_page_before_checkpoint',
        'rollback_savepoint_page_before_checkpoint',
    ]],
    'operation invalidations' => [static fn (): mixed => array_slice($restart()['operation_names'], 2, 4), [
        'invalidate_reader_cache_after_checkpoint_generation',
        'invalidate_reader_cache_after_checkpoint_generation',
        'invalidate_reader_cache_after_checkpoint_generation',
        'invalidate_reader_cache_after_checkpoint_generation',
    ]],
    'digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next170', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'does not repeat next161'), true],
    'truncate action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal'],
    'truncate generation null checkpoint' => [static fn (): mixed => $truncate()['after_generation']['checkpoint_sequence'], null],
    'truncate generation null salt' => [static fn (): mixed => $truncate()['after_generation']['salt'], null],
    'truncate wal removed' => [static fn (): mixed => $truncate()['wal_bytes_length_after_checkpoint'], 0],
    'truncate invalidates current generation cache' => [static fn (): mixed => $truncate()['rows'][2]['cache_reason'], 'reader_cache_generation_predates_checkpoint_reset'],
    'pinned full preserves wal' => [static fn (): mixed => $pinnedFull()['wal_action'], 'preserve_wal'],
    'pinned full busy' => [static fn (): mixed => $pinnedFull()['checkpoint_busy'], true],
    'pinned full retains all current cache' => [static fn (): mixed => $pinnedFull()['retained_cache_page_numbers'], [1, 2, 3, 4]],
    'pinned full reason retained' => [static fn (): mixed => $pinnedFull()['rows'][0]['cache_reason'], 'reader_cache_generation_matches_checkpoint'],
    'passive preserves wal' => [static fn (): mixed => $passive()['wal_action'], 'preserve_wal'],
    'passive retains cache' => [static fn (): mixed => $passive()['retained_cache_page_numbers'], [1, 2, 3, 4]],
    'partial page list' => [static fn (): mixed => $plan('restart', null, null, [2, 3])['page_numbers'], [2, 3]],
    'partial labels' => [static fn (): mixed => $plan('restart', null, null, [2, 3])['current_labels'], [
        'next170 wal wp_options uncommitted draft',
        'next170 wal active_plugins committed',
    ]],
    'missing cache reason' => [static fn (): mixed => $plan('restart', null, [3 => $cache[3]], [2, 3])['rows'][0]['cache_reason'], 'reader_cache_missing_after_checkpoint_generation'],
    'dirty cache reason' => [static fn (): mixed => $plan('restart', null, [3 => array_replace($cache[3], ['dirty' => true])], [3])['rows'][0]['cache_reason'], 'reader_cache_dirty_after_failed_savepoint'],
    'stale image reason' => [static fn (): mixed => $plan('restart', null, [3 => array_replace($cache[3], ['image' => $page('next170 stale active plugins cache')])], [3])['rows'][0]['cache_reason'], 'reader_cache_image_predates_hot_journal_savepoint_checkpoint'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next170 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next170Plan('', $databaseBytes, $pageSize, 's', $hotJournal, $savepointBefore, $wal, $walBytes, $cache, [1]),
    'empty database bytes rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next170Plan($databasePath, '', $pageSize, 's', $hotJournal, $savepointBefore, $wal, $walBytes, $cache, [1]),
    'empty savepoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next170Plan($databasePath, $databaseBytes, $pageSize, '', $hotJournal, $savepointBefore, $wal, $walBytes, $cache, [1]),
    'empty wal bytes rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next170Plan($databasePath, $databaseBytes, $pageSize, 's', $hotJournal, $savepointBefore, $wal, '', $cache, [1]),
    'bad page size rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next170Plan($databasePath, $databaseBytes, 500, 's', $hotJournal, $savepointBefore, $wal, $walBytes, $cache, [1]),
    'unaligned database rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next170Plan($databasePath, $databaseBytes . 'x', $pageSize, 's', $hotJournal, $savepointBefore, $wal, $walBytes, $cache, [1]),
    'bad mode rejected' => static fn () => $plan('invalid'),
    'reader past end rejected' => static fn () => $plan('restart', 5),
    'empty hot journal rejected' => static fn () => $plan('restart', null, null, null, []),
    'empty savepoint rejected' => static fn () => $plan('restart', null, null, null, null, []),
    'empty cache rejected' => static fn () => $plan('restart', null, []),
    'empty page list rejected' => static fn () => $plan('restart', null, null, []),
    'hot page zero rejected' => static fn () => $plan('restart', null, null, null, [0 => $hotJournal[2]]),
    'hot page short rejected' => static fn () => $plan('restart', null, null, null, [2 => 'short']),
    'savepoint page zero rejected' => static fn () => $plan('restart', null, null, null, null, [0 => $savepointBefore[3]]),
    'savepoint page short rejected' => static fn () => $plan('restart', null, null, null, null, [3 => 'short']),
    'cache page zero rejected' => static fn () => $plan('restart', null, [0 => $cache[1]]),
    'cache image short rejected' => static fn () => $plan('restart', null, [1 => ['image' => 'short']]),
    'cache salt malformed rejected' => static fn () => $plan('restart', null, [1 => ['image' => $currentImages[1], 'salt' => [1]]]),
    'cache checkpoint negative rejected' => static fn () => $plan('restart', null, [1 => ['image' => $currentImages[1], 'checkpoint_sequence' => -1]]),
    'cache frame negative rejected' => static fn () => $plan('restart', null, [1 => ['image' => $currentImages[1], 'frame_count' => -1]]),
    'page zero rejected' => static fn () => $plan('restart', null, null, [0]),
    'hot page outside database rejected' => static fn () => $plan('restart', null, null, null, [9 => $page('outside')]),
    'savepoint page outside database rejected' => static fn () => $plan('restart', null, null, null, null, [9 => $page('outside')]),
    'checkpoint page outside database rejected' => static fn () => $plan('restart', null, null, [9]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next170 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
