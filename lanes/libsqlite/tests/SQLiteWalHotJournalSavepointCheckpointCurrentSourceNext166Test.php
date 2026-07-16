<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next166.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = [
    1 => $page('next166 dirty schema page after plugin import'),
    2 => $page('next166 dirty wp_options root page'),
    3 => $page('next166 dirty active_plugins payload'),
    4 => $page('next166 dirty autoload index page'),
    5 => $page('next166 dirty cron option page'),
    6 => $page('next166 dirty transient timeout page'),
];
$hot = [
    2 => $page('next166 hot journal clean wp_options root'),
    4 => $page('next166 hot journal clean autoload index'),
];
$before = [
    3 => $page('next166 savepoint before active_plugins retry'),
    5 => $page('next166 savepoint before cron retry'),
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
    [1, 0, 'next166 current wal schema draft'],
    [2, 6, 'next166 current wal wp_options commit'],
    [4, 0, 'next166 current wal autoload draft'],
    [5, 6, 'next166 current wal cron commit'],
    [6, 6, 'next166 current wal transient timeout commit'],
], 166, 0x16600101, 0x16600102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next166 next wal active_plugins retry draft'],
    [5, 6, 'next166 next wal cron commit'],
    [6, 6, 'next166 next wal transient timeout commit'],
], 167, 0x16700101, 0x16700102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$rolledBack = $database;
$rolledBack[2] = $hot[2];
$rolledBack[4] = $hot[4];
$rolledBack[3] = $before[3];
$rolledBack[5] = $before[5];
ksort($rolledBack, SORT_NUMERIC);
$rolledBackBytes = implode('', $rolledBack);
$sourceId = 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-inner-next166|restart|5|' . $currentWalBytes . '|' . $rolledBackBytes), 0, 24);
$epoch = 167;
$currentImages = [
    1 => $page('next166 current wal schema draft'),
    2 => $page('next166 current wal wp_options commit'),
    3 => $before[3],
    4 => $page('next166 current wal autoload draft'),
    5 => $page('next166 current wal cron commit'),
    6 => $page('next166 current wal transient timeout commit'),
];
$cache = [
    1 => ['image' => $currentImages[1], 'source_id' => $sourceId, 'epoch' => $epoch, 'label' => 'schema cache current'],
    2 => ['image' => $currentImages[2], 'source_id' => 'old-source-token', 'epoch' => $epoch, 'label' => 'wp_options stale token'],
    3 => ['image' => $currentImages[3], 'source_id' => $sourceId, 'epoch' => 166, 'label' => 'active_plugins stale epoch'],
    4 => ['image' => $page('next166 stale autoload cache image'), 'source_id' => $sourceId, 'epoch' => $epoch, 'label' => 'autoload stale image'],
    5 => ['image' => $currentImages[5], 'source_id' => $sourceId, 'epoch' => $epoch, 'dirty' => true, 'label' => 'cron dirty failed savepoint'],
    6 => ['image' => $currentImages[6], 'source_id' => $sourceId, 'epoch' => $epoch, 'label' => 'transient timeout current'],
];
$checkpointPages = [1, 2, 3, 4, 5, 6];
$release = ['plugin-import-inner-next166' => [3, 5]];

$plan = static fn (
    ?array $released = null,
    string $mode = 'restart',
    int $readerEndFrame = 5,
    ?array $cachePages = null,
    ?array $pages = null,
    string $outer = 'plugin-import-outer-next166',
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planSourceTokenHandoff(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-inner-next166',
    $outer,
    $hot,
    $before,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    $cachePages ?? $cache,
    $pages ?? $checkpointPages,
    $released ?? $release,
    $mode,
    $readerEndFrame,
    166,
);

$restart = static fn (): array => $plan();
$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-release-next166'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'savepoint_release_lineage_fenced_before_checkpoint_current_source_publish'],
    'inner savepoint' => [static fn (): mixed => $restart()['inner_savepoint'], 'plugin-import-inner-next166'],
    'outer savepoint' => [static fn (): mixed => $restart()['outer_savepoint'], 'plugin-import-outer-next166'],
    'base checkpoint action' => [static fn (): mixed => $restart()['current_durable']['wal_action'], 'preserve_wal'],
    'next durable action' => [static fn (): mixed => $restart()['next_durable']['wal_action'], 'preserve_wal'],
    'release complete' => [static fn (): mixed => $restart()['release_complete'], true],
    'released inner pages' => [static fn (): mixed => $restart()['released_inner_page_numbers'], [3, 5]],
    'missing release pages' => [static fn (): mixed => $restart()['missing_release_page_numbers'], []],
    'checkpoint pages' => [static fn (): mixed => $restart()['checkpoint_page_numbers'], [1, 2, 3, 4, 5, 6]],
    'hot journal pages' => [static fn (): mixed => $restart()['hot_journal_page_numbers'], [2, 4]],
    'savepoint pages' => [static fn (): mixed => $restart()['savepoint_rollback_page_numbers'], [3, 5]],
    'retained cache' => [static fn (): mixed => $restart()['retained_cache_page_numbers'], [1, 6]],
    'invalidated cache' => [static fn (): mixed => $restart()['invalidated_cache_page_numbers'], [2, 3, 4, 5]],
    'requires reader reopen' => [static fn (): mixed => $restart()['requires_reader_reopen'], true],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'wal', 'wal', 'wal']],
    'checkpoint sources' => [static fn (): mixed => $restart()['checkpoint_sources'], ['wal', 'wal', 'database', 'wal', 'wal', 'wal']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'database', 'wal', 'database', 'wal', 'wal']],
    'current frames' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, null, 3, 4, 5]],
    'next frames' => [static fn (): mixed => $restart()['next_frame_indexes'], [null, null, 1, null, 2, 3]],
    'current labels' => [static fn (): mixed => $restart()['current_labels'], [
        'next166 current wal schema draft',
        'next166 current wal wp_options commit',
        'next166 savepoint before active_plugins retry',
        'next166 current wal autoload draft',
        'next166 current wal cron commit',
        'next166 current wal transient timeout commit',
    ]],
    'next labels' => [static fn (): mixed => $restart()['next_labels'], [
        'next166 current wal schema draft',
        'next166 current wal wp_options commit',
        'next166 next wal active_plugins retry draft',
        'next166 current wal autoload draft',
        'next166 next wal cron commit',
        'next166 next wal transient timeout commit',
    ]],
    'release rows count' => [static fn (): mixed => count($restart()['release_rows']), 2],
    'release row one page' => [static fn (): mixed => $restart()['release_rows'][0]['page_number'], 3],
    'release row one outer' => [static fn (): mixed => $restart()['release_rows'][0]['outer_savepoint'], 'plugin-import-outer-next166'],
    'release row one released' => [static fn (): mixed => $restart()['release_rows'][0]['released_to_outer'], true],
    'release row one checkpoint label' => [static fn (): mixed => $restart()['release_rows'][0]['checkpoint_label'], 'next166 savepoint before active_plugins retry'],
    'release row one next label' => [static fn (): mixed => $restart()['release_rows'][0]['next_label'], 'next166 next wal active_plugins retry draft'],
    'release row two page' => [static fn (): mixed => $restart()['release_rows'][1]['page_number'], 5],
    'release row two next label' => [static fn (): mixed => $restart()['release_rows'][1]['next_label'], 'next166 next wal cron commit'],
    'barrier count' => [static fn (): mixed => count($restart()['writer_barrier_rows']), 6],
    'barrier order' => [static fn (): mixed => $restart()['writer_barrier_page_order'], [1, 2, 3, 4, 5, 6]],
    'barrier page three release fence' => [static fn (): mixed => $restart()['writer_barrier_rows'][2]['requires_release_fence'], true],
    'barrier page one no release fence' => [static fn (): mixed => $restart()['writer_barrier_rows'][0]['requires_release_fence'], false],
    'barrier page six retained cache' => [static fn (): mixed => $restart()['writer_barrier_rows'][5]['cache_admitted'], true],
    'release operation validate' => [static fn (): mixed => $restart()['release_operations'][0]['op'], 'validate_inner_savepoint_release_pages_before_checkpoint_publish'],
    'release operation fence' => [static fn (): mixed => $restart()['release_operations'][1]['op'], 'fence_checkpoint_current_source_after_savepoint_release'],
    'release operation next' => [static fn (): mixed => $restart()['release_operations'][2]['op'], 'publish_next_wal_after_release_checkpoint_fence'],
    'release op missing pages' => [static fn (): mixed => $restart()['release_operations'][0]['missing_pages'], []],
    'operation names include release' => [static fn (): mixed => array_slice($restart()['operation_names_next166'], -3), [
        'validate_inner_savepoint_release_pages_before_checkpoint_publish',
        'fence_checkpoint_current_source_after_savepoint_release',
        'publish_next_wal_after_release_checkpoint_fence',
    ]],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest_next166']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next166', $restart()['dependencies_next166'], true), true],
    'release dependency marker' => [static fn (): mixed => in_array('sqlite-savepoint-release-lineage-current-source-fence', $restart()['dependencies_next166'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure_next166'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap_next166'], 'does not repeat accepted WAL byte truncation'), true],
    'blocked missing release status' => [static fn (): mixed => $plan(['plugin-import-inner-next166' => [3]])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-release-blocked-next166'],
    'blocked missing release reason' => [static fn (): mixed => $plan(['plugin-import-inner-next166' => [3]])['reason'], 'savepoint_release_lineage_missing_before_checkpoint_current_source_publish'],
    'blocked missing release pages' => [static fn (): mixed => $plan(['plugin-import-inner-next166' => [3]])['missing_release_page_numbers'], [5]],
    'blocked release row two not released' => [static fn (): mixed => $plan(['plugin-import-inner-next166' => [3]])['release_rows'][1]['released_to_outer'], false],
    'blocked base without invalidation' => [static fn (): mixed => $plan(null, 'restart', 5, [1 => $cache[1]], [1])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-release-blocked-next166'],
    'partial checkpoint pages' => [static fn (): mixed => $plan(null, 'restart', 5, null, [3, 5])['writer_barrier_page_order'], [3, 5]],
    'partial checkpoint next labels' => [static fn (): mixed => $plan(null, 'restart', 5, null, [3, 5])['next_labels'], ['next166 next wal active_plugins retry draft', 'next166 next wal cron commit']],
    'truncate reader-blocked pinned cache remains current' => [static fn (): mixed => $plan(null, 'truncate', 0, [
        1 => ['image' => $database[1], 'source_id' => 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-inner-next166|truncate|0|' . $currentWalBytes . '|' . $rolledBackBytes), 0, 24), 'epoch' => $epoch, 'pinned' => true],
        2 => ['image' => $hot[2], 'source_id' => 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-inner-next166|truncate|0|' . $currentWalBytes . '|' . $rolledBackBytes), 0, 24), 'epoch' => $epoch],
    ], [1, 2])['rows'][0]['cache_reason'], 'reader_cache_matches_checkpoint_current_source_token'],
    'truncate blocked by base' => [static fn (): mixed => $plan(null, 'truncate', 0, [
        1 => ['image' => $database[1], 'source_id' => 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-inner-next166|truncate|0|' . $currentWalBytes . '|' . $rolledBackBytes), 0, 24), 'epoch' => $epoch, 'pinned' => true],
        2 => ['image' => $hot[2], 'source_id' => 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-inner-next166|truncate|0|' . $currentWalBytes . '|' . $rolledBackBytes), 0, 24), 'epoch' => $epoch],
    ], [1, 2])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-release-blocked-next166'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next166 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'same outer savepoint rejected' => static fn () => $plan(null, 'restart', 5, null, null, 'plugin-import-inner-next166'),
    'empty outer savepoint rejected' => static fn () => $plan(null, 'restart', 5, null, null, ''),
    'empty release map rejected' => static fn () => $plan([]),
    'empty release savepoint rejected' => static fn () => $plan(['' => [3]]),
    'empty release page list rejected' => static fn () => $plan(['plugin-import-inner-next166' => []]),
    'bad release page rejected' => static fn () => $plan(['plugin-import-inner-next166' => [0]]),
    'base validation still rejects bad mode' => static fn () => $plan($release, 'passive'),
    'base validation still rejects reader frame' => static fn () => $plan($release, 'restart', 9),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next166 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
