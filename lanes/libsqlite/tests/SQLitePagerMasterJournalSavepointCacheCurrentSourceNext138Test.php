<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next138.sqlite';
$masterPath = '/srv/wp-content/database/wp-next138.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('next138 stale schema before master hot recovery'),
    2 => $page('next138 stale wp_options before master hot recovery'),
    3 => $page('next138 stale plugin settings before master hot recovery'),
    4 => $page('next138 stale autoload index before master hot recovery'),
    5 => $page('next138 stale retry overflow before master hot recovery'),
];
$recovered = [
    1 => $page('next138 recovered schema current source'),
    2 => $page('next138 recovered wp_options current source'),
    3 => $page('next138 recovered plugin settings current source'),
    4 => $page('next138 recovered autoload index current source'),
    5 => $page('next138 recovered retry overflow current source'),
];
$cache = [
    1 => ['image' => $recovered[1], 'source_id' => 'next138-current-source', 'epoch' => 6, 'source' => 'schema-cache-current'],
    2 => ['image' => $before[2], 'source_id' => 'next138-current-source', 'epoch' => 6, 'source' => 'clean-stale-options-cache'],
    3 => ['image' => $before[3], 'source_id' => 'next138-current-source', 'epoch' => 6, 'dirty' => true, 'source' => 'dirty-plugin-cache'],
    4 => ['image' => $before[4], 'source_id' => 'old-next138-source', 'epoch' => 6, 'source' => 'old-source-autoload-cache'],
];

$plan = static fn (
    string $savepoint = 'wp_import_batch',
    string $statement = 'retry_autoload_update',
    array $savepointWrites = null,
    array $retryWrites = null,
    array $reads = null,
    ?string $currentMaster = null,
    bool $release = true,
): array => SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::plan138(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n/old/site.sqlite-journal\n",
    $currentMaster ?? ($databasePath . "-journal\n/srv/wp-content/database/site-next138.sqlite-journal\n"),
    implode('', $before),
    $pageSize,
    $savepoint,
    $statement,
    $recovered,
    $cache,
    $savepointWrites ?? [
        2 => $page('next138 failed savepoint options write'),
        3 => $page('next138 failed savepoint plugin write'),
    ],
    $retryWrites ?? [
        2 => $page('next138 retry options write after rollback'),
        5 => $page('next138 retry overflow write after rollback'),
    ],
    $reads ?? [1, 2, 3, 4, 5],
    'next138-current-source',
    6,
    $release,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-savepoint-cache-current-source-next138'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_hot_cache_rebases_savepoint_before_retry_statement'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master journal path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint']['name'], 'wp_import_batch'],
    'savepoint still active' => [static fn (): mixed => $plan()['savepoint']['still_active_after_rollback_to'], true],
    'savepoint released' => [static fn (): mixed => $plan()['savepoint']['released_after_retry'], true],
    'savepoint before pages' => [static fn (): mixed => $plan()['savepoint']['before_page_numbers'], [2, 3]],
    'savepoint restored pages' => [static fn (): mixed => $plan()['savepoint']['rollback_restored_page_numbers'], [2, 3]],
    'release merged pages' => [static fn (): mixed => $plan()['savepoint']['release_merged_page_numbers'], [2, 3, 5]],
    'retry statement name' => [static fn (): mixed => $plan()['retry_statement']['name'], 'retry_autoload_update'],
    'retry before pages' => [static fn (): mixed => $plan()['retry_statement']['before_page_numbers'], [2, 5]],
    'retry write pages' => [static fn (): mixed => $plan()['retry_statement']['write_page_numbers'], [2, 5]],
    'hot cache status' => [static fn (): mixed => $plan()['hot_cache_status'], 'pager-master-journal-hot-cache-current-source-next136'],
    'cache stale rejected' => [static fn (): mixed => $plan()['cache_stale_rejected'], true],
    'retained cache pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed cache pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated cache pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4]],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], 'next138-current-source'],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 6],
    'next source id prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-hot-cache:'), true],
    'next source epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 7],
    'savepoint before page two prefix' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][2], 'next138 recovered wp_options current source'],
    'savepoint before page three prefix' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][3], 'next138 recovered plugin settings current source'],
    'retry before page two prefix' => [static fn (): mixed => $plan()['retry_statement_before_prefixes'][2], 'next138 recovered wp_options current source'],
    'retry before page five prefix' => [static fn (): mixed => $plan()['retry_statement_before_prefixes'][5], 'next138 recovered retry overflow current source'],
    'final page one prefix' => [static fn (): mixed => $plan()['final_prefixes'][1], 'next138 recovered schema current source'],
    'final page two prefix' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next138 retry options write after rollback'],
    'final page three prefix' => [static fn (): mixed => $plan()['final_prefixes'][3], 'next138 recovered plugin settings current source'],
    'final page five prefix' => [static fn (): mixed => $plan()['final_prefixes'][5], 'next138 retry overflow write after rollback'],
    'final source page one' => [static fn (): mixed => $plan()['final_sources'][1], 'master-journal-hot-current-source'],
    'final source page two' => [static fn (): mixed => $plan()['final_sources'][2], 'retry-statement-write-after-savepoint-rollback'],
    'final source page three' => [static fn (): mixed => $plan()['final_sources'][3], 'rollback-to-savepoint-master-hot-before-image'],
    'final source page five' => [static fn (): mixed => $plan()['final_sources'][5], 'retry-statement-write-after-savepoint-rollback'],
    'dirty pages' => [static fn (): mixed => $plan()['dirty_page_numbers'], [2, 5]],
    'read count' => [static fn (): mixed => count($plan()['reads']), 5],
    'read page one source' => [static fn (): mixed => $plan()['reads'][0]['source'], 'master-journal-hot-current-source'],
    'read page one dirty' => [static fn (): mixed => $plan()['reads'][0]['dirty'], false],
    'read page two dirty' => [static fn (): mixed => $plan()['reads'][1]['dirty'], true],
    'read page two retry before mismatch' => [static fn (): mixed => $plan()['reads'][1]['matches_retry_before_image'], false],
    'read page three savepoint restored' => [static fn (): mixed => $plan()['reads'][2]['matches_savepoint_before_image'], true],
    'read page four source' => [static fn (): mixed => $plan()['reads'][3]['source'], 'master-journal-hot-current-source'],
    'read page five dirty' => [static fn (): mixed => $plan()['reads'][4]['dirty'], true],
    'operation first reads current master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_hot_cache'],
    'operation stale members discarded' => [static fn (): mixed => $plan()['operations'][1]['op'], 'discard_cached_master_journal_members_for_hot_cache'],
    'operation includes savepoint capture' => [static fn (): mixed => in_array('capture_savepoint_before_image_after_master_hot_cache', array_column($plan()['operations'], 'op'), true), true],
    'operation includes savepoint write' => [static fn (): mixed => in_array('write_savepoint_page_after_master_hot_cache', array_column($plan()['operations'], 'op'), true), true],
    'operation includes savepoint rollback' => [static fn (): mixed => in_array('rollback_to_savepoint_master_hot_before_image', array_column($plan()['operations'], 'op'), true), true],
    'operation includes retry capture' => [static fn (): mixed => in_array('capture_retry_statement_before_image_after_savepoint_rollback', array_column($plan()['operations'], 'op'), true), true],
    'operation retry writes page five' => [static function () use ($plan): mixed {
        foreach ($plan()['operations'] as $operation) {
            if (($operation['op'] ?? '') === 'write_retry_statement_page_after_savepoint_rollback' && ($operation['page_number'] ?? null) === 5) {
                return true;
            }
        }

        return false;
    }, true],
    'operation includes read after retry' => [static fn (): mixed => in_array('read_after_master_hot_savepoint_retry', array_column($plan()['operations'], 'op'), true), true],
    'operation release final' => [static fn (): mixed => end($plan()['operations'])['op'], 'release_savepoint_after_master_hot_retry'],
    'final bytes page two' => [static fn (): mixed => rtrim(substr($plan()['final_database_bytes'], $pageSize, $pageSize), '.'), 'next138 retry options write after rollback'],
    'final bytes page three' => [static fn (): mixed => rtrim(substr($plan()['final_database_bytes'], $pageSize * 2, $pageSize), '.'), 'next138 recovered plugin settings current source'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-master-journal-savepoint-cache-current-source-next138', $plan()['dependencies'], true), true],
    'dependency hot cache' => [static fn (): mixed => in_array('sqlite-pager-master-journal-hot-cache-current-source-next136', $plan()['dependencies'], true), true],
    'dependency savepoint cache' => [static fn (): mixed => in_array('sqlite-pager-master-journal-savepoint-cache-current-source-next125', $plan()['dependencies'], true), true],
    'dependency rollback source' => [static fn (): mixed => in_array('sqlite-savepoint-rollback-to-rebased-pager-cache-current-source', $plan()['dependencies'], true), true],
    'no release leaves merged pages empty' => [static fn (): mixed => $plan(release: false)['savepoint']['release_merged_page_numbers'], []],
    'no release omits release operation' => [static fn (): mixed => end($plan(release: false)['operations'])['op'], 'read_after_master_hot_savepoint_retry'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal savepoint cache current source next138 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty savepoint rejected' => static fn () => $plan(savepoint: ''),
    'empty statement rejected' => static fn () => $plan(statement: ''),
    'empty savepoint writes rejected' => static fn () => $plan(savepointWrites: []),
    'empty retry writes rejected' => static fn () => $plan(retryWrites: []),
    'bad read page rejected' => static fn () => $plan(reads: [0]),
    'current master missing database rejected' => static fn () => $plan(currentMaster: '/tmp/other.sqlite-journal' . "\n"),
    'short savepoint page rejected' => static fn () => $plan(savepointWrites: [2 => 'short']),
    'zero savepoint page rejected' => static fn () => $plan(savepointWrites: [0 => $page('bad')]),
    'short retry page rejected' => static fn () => $plan(retryWrites: [2 => 'short']),
    'zero retry page rejected' => static fn () => $plan(retryWrites: [0 => $page('bad')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal savepoint cache current source next138 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
