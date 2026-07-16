<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 104;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$recovered = 'hot-recovered:157';
$epoch = 57;
$recoveredEpoch = 58;

$currentSourcePages = [
    1 => $page('next157 current schema root clean'),
    3 => $page('next157 current plugin settings before'),
    5 => $page('next157 current options table clean'),
    6 => $page('next157 current retry page before'),
];
$hotJournalPages = [
    2 => $page('next157 hot recovered active plugins'),
    4 => $page('next157 hot recovered autoload index'),
];
$cache = [
    1 => ['image' => $currentSourcePages[1], 'source' => 'database-cache', 'source_id' => $recovered, 'epoch' => $recoveredEpoch],
    2 => ['image' => $page('next157 stale active plugins same token'), 'source' => 'wal-cache', 'source_id' => $recovered, 'epoch' => $recoveredEpoch],
    3 => ['image' => $page('next157 stale plugin before same token'), 'source' => 'database-cache', 'source_id' => $recovered, 'epoch' => $recoveredEpoch],
    4 => ['image' => $hotJournalPages[4], 'source' => 'database-cache', 'source_id' => $recovered, 'epoch' => $recoveredEpoch, 'pinned' => true],
    5 => ['image' => $currentSourcePages[5], 'source' => 'failed-savepoint-cache', 'source_id' => $recovered, 'epoch' => $recoveredEpoch, 'dirty' => true],
    6 => ['image' => $currentSourcePages[6], 'source' => 'database-cache', 'source_id' => 'journal-before-hot:56', 'epoch' => $epoch],
    7 => ['image' => $page('next157 ghost page not in recovered source'), 'source' => 'database-cache', 'source_id' => $recovered, 'epoch' => $recoveredEpoch],
];
$savepointWrites = [
    2 => $page('next157 failed active plugins write'),
    3 => $page('next157 failed plugin settings write'),
    6 => $page('next157 failed retry page write'),
    8 => $page('next157 failed new overflow write'),
];
$rollbackPages = [2, 3, 6, 8];
$retryWrites = [
    2 => $page('next157 retry active plugins write'),
    3 => $page('next157 retry plugin settings write'),
    8 => $page('next157 retry overflow write'),
];
$readPages = [1, 2, 3, 4, 5, 6, 7, 8];

$plan = static fn (
    ?array $cachePages = null,
    ?array $hot = null,
    ?array $source = null,
    ?array $writes = null,
    ?array $rollbacks = null,
    ?array $retries = null,
    ?array $reads = null,
    int $currentEpochArg = 57,
    string $savepoint = 'wp-option-import',
    string $currentSource = 'journal-before-hot:56',
    string $recoveredSource = 'hot-recovered:157',
): array => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceDigestFence(
    $pageSize,
    $savepoint,
    $currentSource,
    $recoveredSource,
    $cachePages ?? $cache,
    $hot ?? $hotJournalPages,
    $source ?? $currentSourcePages,
    $writes ?? $savepointWrites,
    $rollbacks ?? $rollbackPages,
    $retries ?? $retryWrites,
    $reads ?? $readPages,
    $currentEpochArg,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_hot_journal_savepoint_cache_current_source_next157'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'savepoint_before_images_are_fenced_by_recovered_current_source_digests_after_hot_journal_recovery'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint']['name'], 'wp-option-import'],
    'savepoint active' => [static fn (): mixed => $plan()['savepoint']['active_after_rollback'], true],
    'rollback pages' => [static fn (): mixed => $plan()['savepoint']['rollback_page_numbers'], [2, 3, 6, 8]],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], 'journal-before-hot:56'],
    'current epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 57],
    'recovered source id' => [static fn (): mixed => $plan()['recovered_source']['id'], 'hot-recovered:157'],
    'recovered epoch' => [static fn (): mixed => $plan()['recovered_source']['epoch'], 58],
    'recovered pages' => [static fn (): mixed => $plan()['recovered_source']['page_numbers'], [1, 2, 3, 4, 5, 6]],
    'recovered digest count' => [static fn (): mixed => count($plan()['recovered_source']['digests']), 6],
    'retained pages' => [static fn (): mixed => $plan()['cache']['retained_page_numbers'], [1]],
    'retained source' => [static fn (): mixed => $plan()['cache']['retained_entries'][0]['source'], 'database-cache'],
    'invalidated pages' => [static fn (): mixed => $plan()['cache']['invalidated_page_numbers'], [2, 3, 4, 5, 6, 7]],
    'invalidated same token hot image reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['reason'], 'cache_current_source_image_mismatch'],
    'invalidated same token database image reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['reason'], 'cache_current_source_image_mismatch'],
    'invalidated pinned reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['reason'], 'pinned_cache_requires_reopen_after_hot_recovery'],
    'invalidated dirty reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['reason'], 'dirty_cache_after_failed_savepoint'],
    'invalidated stale token reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][4]['reason'], 'stale_cache_source_token'],
    'invalidated absent reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][5]['reason'], 'cache_page_absent_from_recovered_current_source'],
    'savepoint before pages' => [static fn (): mixed => $plan()['savepoint_before_page_numbers'], [2, 3, 6, 8]],
    'savepoint before hot prefix' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][2], 'next157 hot recovered active plugins'],
    'savepoint before database prefix' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][3], 'next157 current plugin settings before'],
    'savepoint before stale token refresh prefix' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][6], 'next157 current retry page before'],
    'savepoint before zero prefix' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][8], ''],
    'rollback hot prefix' => [static fn (): mixed => $plan()['rollback_restored_prefixes'][2], 'next157 hot recovered active plugins'],
    'rollback database prefix' => [static fn (): mixed => $plan()['rollback_restored_prefixes'][3], 'next157 current plugin settings before'],
    'read row count' => [static fn (): mixed => count($plan()['read_pages']), 8],
    'read retained hit' => [static fn (): mixed => $plan()['read_pages'][0]['cache_hit'], true],
    'read hot digest matches' => [static fn (): mixed => $plan()['read_pages'][1]['matches_current_source_digest'], true],
    'read rollback digest matches' => [static fn (): mixed => $plan()['read_pages'][2]['matches_current_source_digest'], true],
    'read pinned restored hot source' => [static fn (): mixed => $plan()['read_pages'][3]['source'], 'hot-journal-recovered-current-source'],
    'read dirty page restored source' => [static fn (): mixed => $plan()['read_pages'][4]['source'], 'database-current-source'],
    'read stale token page source' => [static fn (): mixed => $plan()['read_pages'][5]['source'], 'rollback-to-recovered-current-source-before-image'],
    'read absent page zero fill' => [static fn (): mixed => $plan()['read_pages'][6]['source'], 'zero-fill-recovered-current-source'],
    'read absent digest mismatch' => [static fn (): mixed => $plan()['read_pages'][6]['matches_current_source_digest'], false],
    'retry before pages' => [static fn (): mixed => $plan()['retry_before_page_numbers'], [2, 3, 8]],
    'retry before hot prefix' => [static fn (): mixed => $plan()['retry_before_prefixes'][2], 'next157 hot recovered active plugins'],
    'retry before database prefix' => [static fn (): mixed => $plan()['retry_before_prefixes'][3], 'next157 current plugin settings before'],
    'retry before zero prefix' => [static fn (): mixed => $plan()['retry_before_prefixes'][8], ''],
    'final pages' => [static fn (): mixed => $plan()['final_page_numbers'], [1, 2, 3, 4, 5, 6, 8]],
    'final page one source' => [static fn (): mixed => $plan()['final_sources'][1], 'database-cache'],
    'final page two source' => [static fn (): mixed => $plan()['final_sources'][2], 'retry-write-after-source-fenced-rollback'],
    'final page four source' => [static fn (): mixed => $plan()['final_sources'][4], 'hot-journal-recovered-current-source'],
    'dirty pages' => [static fn (): mixed => $plan()['dirty_page_numbers'], [2, 3, 8]],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 38],
    'operation first retain' => [static fn (): mixed => $plan()['operations'][0]['op'], 'retain_cache_page_matching_recovered_current_source'],
    'operation mismatch reason' => [static fn (): mixed => $plan()['operations'][1]['reason'], 'cache_current_source_image_mismatch'],
    'operation install hot' => [static fn (): mixed => $plan()['operations'][7]['op'], 'install_hot_journal_current_source_page'],
    'operation capture savepoint' => [static fn (): mixed => $plan()['operations'][12]['op'], 'capture_savepoint_before_image_from_recovered_current_source'],
    'operation rollback' => [static fn (): mixed => $plan()['operations'][20]['op'], 'rollback_to_restores_recovered_current_source_before_image'],
    'operation read' => [static fn (): mixed => $plan()['operations'][24]['op'], 'read_after_rollback_to_current_source_fence'],
    'operation retry capture' => [static fn (): mixed => $plan()['operations'][32]['op'], 'capture_retry_before_image_after_source_fence'],
    'operation retry write' => [static fn (): mixed => $plan()['operations'][33]['op'], 'write_retry_page_after_source_fence'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-savepoint-cache-current-source-next157', $plan()['dependencies'], true), true],
    'dependency image fence' => [static fn (): mixed => in_array('sqlite-hot-journal-recovered-source-image-fence', $plan()['dependencies'], true), true],
    'dependency before image validation' => [static fn (): mixed => in_array('sqlite-savepoint-before-image-current-source-validation', $plan()['dependencies'], true), true],
    'dependency digest' => [static fn (): mixed => in_array('sqlite-pager-cache-current-source-digest', $plan()['dependencies'], true), true],
    'matching recovered hot cache can be retained' => [static fn (): mixed => $plan([2 => ['image' => $hotJournalPages[2], 'source' => 'wal-cache', 'source_id' => $recovered, 'epoch' => $recoveredEpoch]], [2 => $hotJournalPages[2]], [1 => $currentSourcePages[1]], [2 => $savepointWrites[2]], [2], [2 => $retryWrites[2]], [2])['cache']['retained_page_numbers'], [2]],
    'missing rollback before image rejected through focused path' => [static fn (): mixed => str_contains((static function () use ($plan): string {
        try {
            $plan(null, null, null, null, [4]);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return '';
    })(), 'rollback page 4 has no savepoint before image'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal savepoint cache current source next157 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects bad page size' => static fn () => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceDigestFence(0, 's', 'a', 'b', $cache, $hotJournalPages, $currentSourcePages, $savepointWrites, $rollbackPages, $retryWrites, $readPages),
    'rejects empty savepoint' => static fn () => $plan(null, null, null, null, null, null, null, 57, ''),
    'rejects empty current source' => static fn () => $plan(null, null, null, null, null, null, null, 57, 's', ''),
    'rejects empty recovered source' => static fn () => $plan(null, null, null, null, null, null, null, 57, 's', 'a', ''),
    'rejects unchanged source' => static fn () => $plan(null, null, null, null, null, null, null, 57, 's', 'same', 'same'),
    'rejects zero epoch' => static fn () => $plan(null, null, null, null, null, null, null, 0),
    'rejects empty cache' => static fn () => $plan([]),
    'rejects empty hot pages' => static fn () => $plan(null, []),
    'rejects empty source pages' => static fn () => $plan(null, null, []),
    'rejects empty savepoint writes' => static fn () => $plan(null, null, null, []),
    'rejects empty rollback pages' => static fn () => $plan(null, null, null, null, []),
    'rejects empty retry writes' => static fn () => $plan(null, null, null, null, null, []),
    'rejects empty read pages' => static fn () => $plan(null, null, null, null, null, null, []),
    'rejects bad cache page' => static fn () => $plan([0 => ['image' => $page('bad')]]),
    'rejects short cache image' => static fn () => $plan([1 => ['image' => 'short']]),
    'rejects bad hot page' => static fn () => $plan(null, [0 => $hotJournalPages[2]]),
    'rejects short hot image' => static fn () => $plan(null, [2 => 'short']),
    'rejects bad source page' => static fn () => $plan(null, null, [0 => $currentSourcePages[1]]),
    'rejects short source image' => static fn () => $plan(null, null, [1 => 'short']),
    'rejects bad savepoint write page' => static fn () => $plan(null, null, null, [0 => $savepointWrites[2]]),
    'rejects short savepoint write image' => static fn () => $plan(null, null, null, [2 => 'short']),
    'rejects bad rollback page' => static fn () => $plan(null, null, null, null, [0]),
    'rejects bad retry page' => static fn () => $plan(null, null, null, null, null, [0 => $retryWrites[2]]),
    'rejects short retry image' => static fn () => $plan(null, null, null, null, null, [2 => 'short']),
    'rejects bad read page' => static fn () => $plan(null, null, null, null, null, null, [0]),
];

foreach ($throws as $name => $callback) {
    $tests['pager hot journal savepoint cache current source next157 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
