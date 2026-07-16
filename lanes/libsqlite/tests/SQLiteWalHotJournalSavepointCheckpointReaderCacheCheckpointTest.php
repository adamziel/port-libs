<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next161.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = [
    1 => $page('next161 dirty schema page after interrupted import'),
    2 => $page('next161 dirty wp_options root after interrupted import'),
    3 => $page('next161 dirty active_plugins after interrupted import'),
    4 => $page('next161 dirty autoload index after interrupted import'),
    5 => $page('next161 dirty cron option after interrupted import'),
];
$hot = [
    2 => $page('next161 hot journal clean wp_options root'),
    4 => $page('next161 hot journal clean autoload index'),
];
$savepointBefore = [
    3 => $page('next161 savepoint before active_plugins retry'),
    5 => $page('next161 savepoint before cron retry'),
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
    [1, 0, 'next161 current wal schema draft'],
    [2, 5, 'next161 current wal wp_options commit'],
    [4, 0, 'next161 current wal autoload draft'],
    [5, 5, 'next161 current wal cron commit'],
], 161, 0x16100101, 0x16100102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next161 next wal active_plugins retry draft'],
    [5, 5, 'next161 next wal cron commit'],
], 162, 0x16200101, 0x16200102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$rolledBack = $database;
$rolledBack[2] = $hot[2];
$rolledBack[4] = $hot[4];
$rolledBack[3] = $savepointBefore[3];
$rolledBack[5] = $savepointBefore[5];
ksort($rolledBack, SORT_NUMERIC);
$rolledBackBytes = implode('', $rolledBack);
$sourceId = 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-next161|restart|4|' . $currentWalBytes . '|' . $rolledBackBytes), 0, 24);
$epoch = 162;
$currentImages = [
    1 => $page('next161 current wal schema draft'),
    2 => $page('next161 current wal wp_options commit'),
    3 => $savepointBefore[3],
    4 => $page('next161 current wal autoload draft'),
    5 => $page('next161 current wal cron commit'),
];
$cache = [
    1 => ['image' => $currentImages[1], 'source_id' => $sourceId, 'epoch' => $epoch, 'label' => 'schema cache current'],
    2 => ['image' => $currentImages[2], 'source_id' => 'old-source-token', 'epoch' => $epoch, 'label' => 'wp_options stale token'],
    3 => ['image' => $currentImages[3], 'source_id' => $sourceId, 'epoch' => 161, 'label' => 'active_plugins stale epoch'],
    4 => ['image' => $page('next161 stale autoload cache image'), 'source_id' => $sourceId, 'epoch' => $epoch, 'label' => 'autoload stale image'],
    5 => ['image' => $currentImages[5], 'source_id' => $sourceId, 'epoch' => $epoch, 'dirty' => true, 'label' => 'cron dirty failed savepoint'],
];
$checkpointPages = [1, 2, 3, 4, 5];

$plan = static fn (
    string $mode = 'restart',
    int $readerEndFrame = 4,
    ?array $cachePages = null,
    ?array $pages = null,
    ?array $hotPages = null,
    ?array $beforePages = null,
    int $epochArg = 161,
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerCacheCheckpointPlan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next161',
    $hotPages ?? $hot,
    $beforePages ?? $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    $cachePages ?? $cache,
    $pages ?? $checkpointPages,
    $mode,
    $readerEndFrame,
    $epochArg,
);

$restart = static fn (): array => $plan();
$truncateReset = static function () use ($plan, $sourceId, $epoch, $page): array {
    $resetSourceId = 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', '/srv/www/wp-content/database/wp-next161.sqlite|plugin-import-next161|truncate|0|' . $GLOBALS['currentWalBytes'] . '|' . $GLOBALS['rolledBackBytes']), 0, 24);
    return $plan('truncate', 0, [
        1 => ['image' => $page('next161 dirty schema page after interrupted import'), 'source_id' => $resetSourceId, 'epoch' => $epoch, 'pinned' => true],
        2 => ['image' => $page('next161 hot journal clean wp_options root'), 'source_id' => $resetSourceId, 'epoch' => $epoch],
    ], [1, 2]);
};

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next161'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'reader_cache_rebased_after_hot_journal_savepoint_checkpoint_current_source'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-import-next161'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'reader frame' => [static fn (): mixed => $restart()['reader_end_frame'], 4],
    'checkpoint reason' => [static fn (): mixed => $restart()['current_checkpoint']['reason'], 'reader_blocks_wal_reset'],
    'checkpoint action' => [static fn (): mixed => $restart()['current_durable']['wal_action'], 'preserve_wal'],
    'next durable action' => [static fn (): mixed => $restart()['next_durable']['wal_action'], 'preserve_wal'],
    'current source token epoch' => [static fn (): mixed => $restart()['current_source_token']['epoch'], 162],
    'next source token epoch' => [static fn (): mixed => $restart()['next_source_token']['epoch'], 163],
    'current source token prefix' => [static fn (): mixed => str_starts_with($restart()['current_source_token']['id'], 'wal-hot-journal-savepoint-checkpoint-next161:current:'), true],
    'next source token prefix' => [static fn (): mixed => str_starts_with($restart()['next_source_token']['id'], 'wal-hot-journal-savepoint-checkpoint-next161:next:'), true],
    'hot journal pages' => [static fn (): mixed => $restart()['hot_journal_page_numbers'], [2, 4]],
    'savepoint rollback pages' => [static fn (): mixed => $restart()['savepoint_rollback_page_numbers'], [3, 5]],
    'checkpoint pages' => [static fn (): mixed => $restart()['checkpoint_page_numbers'], [1, 2, 3, 4, 5]],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'wal', 'wal']],
    'checkpoint sources' => [static fn (): mixed => $restart()['checkpoint_sources'], ['wal', 'wal', 'database', 'wal', 'wal']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'database', 'wal', 'database', 'wal']],
    'current frames' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, null, 3, 4]],
    'checkpoint frames' => [static fn (): mixed => $restart()['checkpoint_frame_indexes'], [1, 2, null, 3, 4]],
    'next frames' => [static fn (): mixed => $restart()['next_frame_indexes'], [null, null, 1, null, 2]],
    'current labels' => [static fn (): mixed => $restart()['current_labels'], [
        'next161 current wal schema draft',
        'next161 current wal wp_options commit',
        'next161 savepoint before active_plugins retry',
        'next161 current wal autoload draft',
        'next161 current wal cron commit',
    ]],
    'checkpoint labels match current' => [static fn (): mixed => $restart()['checkpoint_labels'], $restart()['current_labels']],
    'next labels' => [static fn (): mixed => $restart()['next_labels'], [
        'next161 current wal schema draft',
        'next161 current wal wp_options commit',
        'next161 next wal active_plugins retry draft',
        'next161 current wal autoload draft',
        'next161 next wal cron commit',
    ]],
    'retained cache' => [static fn (): mixed => $restart()['retained_cache_page_numbers'], [1]],
    'invalidated cache' => [static fn (): mixed => $restart()['invalidated_cache_page_numbers'], [2, 3, 4, 5]],
    'requires reader reopen' => [static fn (): mixed => $restart()['requires_reader_reopen'], true],
    'current matches checkpoint' => [static fn (): mixed => $restart()['current_matches_checkpoint'], true],
    'next changes pages' => [static fn (): mixed => $restart()['next_changes_pages'], true],
    'row count' => [static fn (): mixed => count($restart()['rows']), 5],
    'row one cache admitted' => [static fn (): mixed => $restart()['rows'][0]['cache_admitted'], true],
    'row one cache reason' => [static fn (): mixed => $restart()['rows'][0]['cache_reason'], 'reader_cache_matches_checkpoint_current_source_token'],
    'row two cache reason' => [static fn (): mixed => $restart()['rows'][1]['cache_reason'], 'reader_cache_source_token_predates_checkpoint_current_source'],
    'row three cache reason' => [static fn (): mixed => $restart()['rows'][2]['cache_reason'], 'reader_cache_epoch_predates_checkpoint_current_source'],
    'row four cache reason' => [static fn (): mixed => $restart()['rows'][3]['cache_reason'], 'reader_cache_image_predates_hot_journal_savepoint_checkpoint'],
    'row five cache reason' => [static fn (): mixed => $restart()['rows'][4]['cache_reason'], 'reader_cache_dirty_after_failed_savepoint'],
    'row one cache label' => [static fn (): mixed => $restart()['rows'][0]['cache_label'], 'schema cache current'],
    'row two transition' => [static fn (): mixed => $restart()['rows'][1]['source_transition'], 'wal>checkpoint>wal>next>database'],
    'row three transition' => [static fn (): mixed => $restart()['rows'][2]['source_transition'], 'database>checkpoint>database>next>wal'],
    'operation names first' => [static fn (): mixed => array_slice($restart()['operation_names'], 0, 4), [
        'restore_hot_journal_page_before_savepoint_checkpoint',
        'restore_hot_journal_page_before_savepoint_checkpoint',
        'rollback_savepoint_page_before_checkpoint',
        'rollback_savepoint_page_before_checkpoint',
    ]],
    'operation names cache' => [static fn (): mixed => array_slice($restart()['operation_names'], 4, 5), [
        'retain_reader_cache_for_checkpoint_current_source',
        'invalidate_reader_cache_for_checkpoint_current_source',
        'invalidate_reader_cache_for_checkpoint_current_source',
        'invalidate_reader_cache_for_checkpoint_current_source',
        'invalidate_reader_cache_for_checkpoint_current_source',
    ]],
    'operation publish current' => [static fn (): mixed => $restart()['operation_names'][9], 'publish_checkpoint_current_source_token'],
    'operation publish next' => [static fn (): mixed => $restart()['operation_names'][10], 'publish_next_wal_source_token'],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next161', $restart()['dependencies'], true), true],
    'dependency token fence' => [static fn (): mixed => in_array('sqlite-hot-journal-savepoint-checkpoint-reader-cache-token-fence', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'does not repeat next159'), true],
    'truncate preserve pinned retained while reader blocks reset' => [static fn (): mixed => $truncateReset()['rows'][0]['cache_reason'], 'reader_cache_matches_checkpoint_current_source_token'],
    'truncate reset retains unpinned' => [static fn (): mixed => $truncateReset()['rows'][1]['cache_reason'], 'reader_cache_matches_checkpoint_current_source_token'],
    'truncate all retained status blocked without rebase' => [static fn (): mixed => $truncateReset()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next161'],
    'missing cache invalidates' => [static fn (): mixed => $plan('restart', 4, [1 => $cache[1]], [1, 2])['rows'][1]['cache_reason'], 'reader_cache_missing_after_checkpoint'],
    'blocked without invalidation' => [static fn (): mixed => $plan('restart', 4, [1 => $cache[1]], [1])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next161'],
    'partial pages' => [static fn (): mixed => $plan('restart', 4, null, [2, 5])['checkpoint_page_numbers'], [2, 5]],
    'partial labels' => [static fn (): mixed => $plan('restart', 4, null, [2, 5])['checkpoint_labels'], ['next161 current wal wp_options commit', 'next161 current wal cron commit']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next161 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerCacheCheckpointPlan('', $databaseBytes, $pageSize, 's', $hot, $savepointBefore, $currentWal, $currentWalBytes, $nextWal, $nextWalBytes, $cache, [1]),
    'empty database bytes rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerCacheCheckpointPlan($databasePath, '', $pageSize, 's', $hot, $savepointBefore, $currentWal, $currentWalBytes, $nextWal, $nextWalBytes, $cache, [1]),
    'empty savepoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerCacheCheckpointPlan($databasePath, $databaseBytes, $pageSize, '', $hot, $savepointBefore, $currentWal, $currentWalBytes, $nextWal, $nextWalBytes, $cache, [1]),
    'bad page size rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerCacheCheckpointPlan($databasePath, $databaseBytes, 500, 's', $hot, $savepointBefore, $currentWal, $currentWalBytes, $nextWal, $nextWalBytes, $cache, [1]),
    'bad mode rejected' => static fn () => $plan('passive'),
    'empty current wal bytes rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerCacheCheckpointPlan($databasePath, $databaseBytes, $pageSize, 's', $hot, $savepointBefore, $currentWal, '', $nextWal, $nextWalBytes, $cache, [1]),
    'empty next wal bytes rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerCacheCheckpointPlan($databasePath, $databaseBytes, $pageSize, 's', $hot, $savepointBefore, $currentWal, $currentWalBytes, $nextWal, '', $cache, [1]),
    'reader past wal rejected' => static fn () => $plan('restart', 5),
    'zero epoch rejected' => static fn () => $plan('restart', 4, null, null, null, null, 0),
    'empty hot pages rejected' => static fn () => $plan('restart', 4, null, null, []),
    'empty savepoint pages rejected' => static fn () => $plan('restart', 4, null, null, null, []),
    'empty reader cache rejected' => static fn () => $plan('restart', 4, []),
    'empty checkpoint pages rejected' => static fn () => $plan('restart', 4, null, []),
    'hot page outside database rejected' => static fn () => $plan('restart', 4, null, null, [9 => $page('bad outside')]),
    'savepoint page outside database rejected' => static fn () => $plan('restart', 4, null, null, null, [9 => $page('bad outside')]),
    'checkpoint page outside database rejected' => static fn () => $plan('restart', 4, null, [9]),
    'bad hot page number rejected' => static fn () => $plan('restart', 4, null, null, [0 => $hot[2]]),
    'short hot image rejected' => static fn () => $plan('restart', 4, null, null, [2 => 'short']),
    'bad savepoint page number rejected' => static fn () => $plan('restart', 4, null, null, null, [0 => $savepointBefore[3]]),
    'short savepoint image rejected' => static fn () => $plan('restart', 4, null, null, null, [3 => 'short']),
    'bad cache page number rejected' => static fn () => $plan('restart', 4, [0 => $cache[1]]),
    'short cache image rejected' => static fn () => $plan('restart', 4, [1 => ['image' => 'short', 'source_id' => $sourceId, 'epoch' => $epoch]]),
    'empty cache source rejected' => static fn () => $plan('restart', 4, [1 => ['image' => $currentImages[1], 'source_id' => '', 'epoch' => $epoch]]),
    'bad cache epoch rejected' => static fn () => $plan('restart', 4, [1 => ['image' => $currentImages[1], 'source_id' => $sourceId, 'epoch' => 0]]),
    'bad checkpoint page rejected' => static fn () => $plan('restart', 4, null, [0]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next161 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
