<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next135.sqlite';
$salt1 = 0x13572468;
$salt2 = 0x24681357;
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x02";
$firstPage[19] = "\x02";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 6), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$databasePages = [
    1 => $firstPage,
    2 => $page('next135 db wp_options root before wal'),
    3 => $page('next135 db active_plugins before wal'),
    4 => $page('next135 db autoload index before wal'),
    5 => $page('next135 db transient row before wal'),
    6 => $page('next135 db usermeta untouched before wal'),
];
$databaseBytes = implode('', $databasePages);
$committedImages = [
    2 => $page('next135 wal committed wp_options root'),
    3 => $page('next135 wal committed active_plugins'),
    4 => $page('next135 wal committed autoload index'),
];
$tailImages = [
    3 => $page('next135 wal uncommitted active_plugins stale tail'),
    5 => $page('next135 wal uncommitted transient stale tail'),
];

$makeWalBytes = static function (bool $withValidTail = false, bool $withCorruptTail = false) use ($pageSize, $salt1, $salt2, $committedImages, $tailImages): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 135, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $append = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

        return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };
    $bytes = $append($bytes, $seed, 2, 0, $committedImages[2]);
    $bytes = $append($bytes, $seed, 3, 0, $committedImages[3]);
    $bytes = $append($bytes, $seed, 4, 6, $committedImages[4]);
    if ($withValidTail) {
        $bytes = $append($bytes, $seed, 3, 0, $tailImages[3]);
        $bytes = $append($bytes, $seed, 5, 0, $tailImages[5]);
    }
    if ($withCorruptTail) {
        $bytes .= str_repeat('x', 51);
    }

    return $bytes;
};

$walBytes = $makeWalBytes();
$walBytesWithTail = $makeWalBytes(true);
$walBytesWithCorruptTail = $makeWalBytes(false, true);
$cachePages = [
    ['page' => 2, 'image' => $page('next135 cache retry wp_options root'), 'bytes' => $pageSize],
    ['page' => 3, 'image' => $page('next135 cache retry active_plugins'), 'bytes' => $pageSize],
    ['page' => 4, 'image' => $page('next135 cache pinned autoload index'), 'bytes' => $pageSize, 'pinned' => true],
    ['page' => 5, 'image' => $page('next135 cache retry transient insert'), 'bytes' => $pageSize],
];

$plan = static fn (
    ?string $bytes = null,
    ?array $pages = null,
    ?int $reader = null,
    int $cacheSize = 8,
    int $threshold = 3,
    ?int $limit = 3,
    bool $enabled = true,
): array => SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan::plan(
    $databasePath,
    $databaseBytes,
    $bytes ?? $walBytes,
    $pages ?? $cachePages,
    $cacheSize,
    $threshold,
    $reader,
    $limit,
    $enabled
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_cache_spill_wal_recovery_current_source_next135'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'wal_committed_prefix_recovered_before_cache_spill'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'page count' => [static fn (): mixed => $plan()['page_count'], 6],
    'recovery status' => [static fn (): mixed => $plan()['recovery']['status'], 'valid'],
    'recovery reason' => [static fn (): mixed => $plan()['recovery']['reason'], 'all_frames_valid'],
    'valid frame count' => [static fn (): mixed => $plan()['recovery']['valid_frame_count'], 3],
    'committed frame count' => [static fn (): mixed => $plan()['recovery']['committed_frame_count'], 3],
    'discarded valid tail count' => [static fn (): mixed => $plan()['recovery']['discarded_valid_tail_frame_count'], 0],
    'discarded corrupt tail count' => [static fn (): mixed => $plan()['recovery']['discarded_corrupt_tail_frame_count'], 0],
    'reader frame' => [static fn (): mixed => $plan()['current_reader_end_frame'], 3],
    'reader does not pin tail' => [static fn (): mixed => $plan()['reader_pins_discarded_tail'], false],
    'wal reset not blocked' => [static fn (): mixed => $plan()['wal_reset_blocked'], false],
    'wal reset reasons empty' => [static fn (): mixed => $plan()['wal_reset_blocked_reasons'], []],
    'source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'source mismatch pages empty' => [static fn (): mixed => $plan()['current_source_mismatch_pages'], []],
    'tail source pages empty' => [static fn (): mixed => $plan()['discarded_tail_source_pages'], []],
    'spill status' => [static fn (): mixed => $plan()['spill']['status'], 'spilled'],
    'spill target' => [static fn (): mixed => $plan()['spill_target'] ?? $plan()['spill']['spill_target'], 'wal_frames'],
    'spilled pages' => [static fn (): mixed => $plan()['spilled_page_numbers'], [2, 3, 5]],
    'spill frame pages' => [static fn (): mixed => $plan()['spill']['next']['wal_frame_pages'], [2, 3, 5]],
    'remaining dirty pinned' => [static fn (): mixed => $plan()['spill']['next']['dirty_pages'], [4]],
    'database image unchanged until checkpoint' => [static fn (): mixed => $plan()['spill']['next']['database_image'], 'unchanged_until_checkpoint'],
    'journal rollback not required in wal' => [static fn (): mixed => $plan()['spill']['next']['journal_required_for_rollback'], false],
    'checkpoint bytes include committed root' => [static fn (): mixed => str_contains($plan()['checkpoint_database_bytes'], 'next135 wal committed wp_options root'), true],
    'checkpoint bytes include committed index' => [static fn (): mixed => str_contains($plan()['checkpoint_database_bytes'], 'next135 wal committed autoload index'), true],
    'checkpoint bytes exclude uncommitted transient' => [static fn (): mixed => str_contains($plan()['checkpoint_database_bytes'], 'next135 wal uncommitted transient stale tail'), false],
    'committed wal bytes length' => [static fn (): mixed => strlen($plan()['committed_wal_bytes']), 32 + (3 * (24 + $pageSize))],
    'valid wal bytes length' => [static fn (): mixed => strlen($plan()['valid_wal_bytes']), 32 + (3 * (24 + $pageSize))],
    'page two committed frame' => [static fn (): mixed => $plan()['cache_page_sources'][0]['committed_frame'], 1],
    'page two latest frame' => [static fn (): mixed => $plan()['cache_page_sources'][0]['latest_valid_frame'], 1],
    'page two recovered source' => [static fn (): mixed => $plan()['cache_page_sources'][0]['uses_recovered_wal_frame'], true],
    'page two no discarded tail' => [static fn (): mixed => $plan()['cache_page_sources'][0]['discarded_tail_frame_source'], false],
    'page two source prefix' => [static fn (): mixed => $plan()['cache_page_sources'][0]['current_source_prefix'], 'next135 wal committed wp_options root'],
    'page two cache prefix' => [static fn (): mixed => $plan()['cache_page_sources'][0]['cache_prefix'], 'next135 cache retry wp_options root'],
    'page five no committed frame' => [static fn (): mixed => $plan()['cache_page_sources'][3]['committed_frame'], null],
    'page five uses database source' => [static fn (): mixed => $plan()['cache_page_sources'][3]['current_source_prefix'], 'next135 db transient row before wal'],
    'operation recovery first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'recover_wal_committed_prefix'],
    'operation reset allowed' => [static fn (): mixed => $plan()['operations'][1]['op'], 'allow_wal_reset_after_recovery_sync'],
    'operation append frame' => [static fn (): mixed => $plan()['operations'][2]['op'], 'append_wal_frame'],
    'operation append page' => [static fn (): mixed => $plan()['operations'][2]['page'], 2],
    'operation mark clean' => [static fn (): mixed => $plan()['operations'][3]['op'], 'mark_page_clean_in_cache'],
    'dependency next135' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-wal-recovery-current-source-next135', $plan()['dependencies'], true), true],
    'dependency transaction boundary' => [static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $plan()['dependencies'], true), true],
    'dependency wal spill routing' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-wal-frame-routing', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager cache spill wal recovery current source next135 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$blockedCases = [
    'valid tail status blocked' => [static fn (): mixed => $plan($walBytesWithTail)['status'], 'pager_cache_spill_wal_recovery_current_source_blocked_next135'],
    'valid tail reason' => [static fn (): mixed => $plan($walBytesWithTail)['reason'], 'cache_spill_blocked_until_wal_recovery_current_source_is_verified'],
    'valid tail recovery reason' => [static fn (): mixed => $plan($walBytesWithTail)['recovery']['reason'], 'uncommitted_valid_tail_after_last_commit'],
    'valid tail discarded count' => [static fn (): mixed => $plan($walBytesWithTail)['recovery']['discarded_valid_tail_frame_count'], 2],
    'valid tail source pages' => [static fn (): mixed => $plan($walBytesWithTail)['discarded_tail_source_pages'], [3, 5]],
    'valid tail blocked reasons' => [static fn (): mixed => $plan($walBytesWithTail)['spill']['blocked_reasons'], ['cache_spill_disabled', 'wal_uncommitted_tail_discarded_before_cache_spill']],
    'valid tail page three flagged' => [static fn (): mixed => $plan($walBytesWithTail)['cache_page_sources'][1]['discarded_tail_frame_source'], true],
    'valid tail page three latest frame' => [static fn (): mixed => $plan($walBytesWithTail)['cache_page_sources'][1]['latest_valid_frame'], 4],
    'valid tail reader pins reset' => [static fn (): mixed => $plan($walBytesWithTail, null, 5)['reader_pins_discarded_tail'], true],
    'valid tail reset blocked' => [static fn (): mixed => $plan($walBytesWithTail, null, 5)['wal_reset_blocked_reasons'], ['reader_pins_valid_uncommitted_tail']],
    'valid tail reset operation deferred' => [static fn (): mixed => $plan($walBytesWithTail, null, 5)['operations'][1]['op'], 'defer_wal_reset'],
    'corrupt tail status blocked' => [static fn (): mixed => $plan($walBytesWithCorruptTail)['status'], 'pager_cache_spill_wal_recovery_current_source_next135'],
    'corrupt tail recovery reason' => [static fn (): mixed => $plan($walBytesWithCorruptTail)['recovery']['reason'], 'corrupt_tail_after_committed_prefix'],
    'corrupt tail discarded count' => [static fn (): mixed => $plan($walBytesWithCorruptTail)['recovery']['discarded_corrupt_tail_frame_count'], 1],
    'corrupt tail reset blocked' => [static fn (): mixed => $plan($walBytesWithCorruptTail)['wal_reset_blocked_reasons'], ['corrupt_tail_requires_recovery_prefix_preservation_until_sync']],
    'source mismatch status' => [static fn (): mixed => $plan(null, [['page' => 2, 'image' => $cachePages[0]['image'], 'current_image' => $databasePages[2]]])['status'], 'pager_cache_spill_wal_recovery_current_source_blocked_next135'],
    'source mismatch pages' => [static fn (): mixed => $plan(null, [['page' => 2, 'image' => $cachePages[0]['image'], 'current_image' => $databasePages[2]]])['current_source_mismatch_pages'], [2]],
    'source mismatch blocked reasons' => [static fn (): mixed => $plan(null, [['page' => 2, 'image' => $cachePages[0]['image'], 'current_image' => $databasePages[2]]])['spill']['blocked_reasons'], ['cache_spill_disabled', 'wal_recovery_current_source_mismatch']],
    'disabled spill status' => [static fn (): mixed => $plan(null, null, null, 8, 3, 3, false)['status'], 'pager_cache_spill_wal_recovery_current_source_blocked_next135'],
    'disabled spill reasons' => [static fn (): mixed => $plan(null, null, null, 8, 3, 3, false)['spill']['blocked_reasons'], ['cache_spill_disabled']],
    'below threshold status' => [static fn (): mixed => $plan(null, null, null, 2, 3)['status'], 'pager_cache_spill_wal_recovery_current_source_blocked_next135'],
    'below threshold reason' => [static fn (): mixed => $plan(null, null, null, 2, 3)['spill']['blocked_reasons'], ['cache_below_spill_threshold']],
    'one page limit' => [static fn (): mixed => $plan(null, null, null, 8, 3, 1)['spilled_page_numbers'], [2]],
    'one page limit dirty pages' => [static fn (): mixed => $plan(null, null, null, 8, 3, 1)['spill']['next']['dirty_pages'], [3, 4, 5]],
];

foreach ($blockedCases as $name => [$callback, $expected]) {
    $tests['pager cache spill wal recovery current source next135 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty path' => static fn () => SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan::plan('', $databaseBytes, $walBytes, $cachePages, 8, 3),
    'rejects empty database' => static fn () => SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan::plan($databasePath, '', $walBytes, $cachePages, 8, 3),
    'rejects empty wal' => static fn () => SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan::plan($databasePath, $databaseBytes, '', $cachePages, 8, 3),
    'rejects empty cache' => static fn () => SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $walBytes, [], 8, 3),
    'rejects negative reader' => static fn () => SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $walBytes, $cachePages, 8, 3, -1),
    'rejects reader past valid prefix' => static fn () => SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $walBytes, $cachePages, 8, 3, 99),
    'rejects unaligned database' => static fn () => SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan::plan($databasePath, $databaseBytes . 'x', $walBytes, $cachePages, 8, 3),
    'rejects cache page zero' => static fn () => $plan(null, [['page' => 0, 'image' => $cachePages[0]['image']]]),
    'rejects cache page outside database' => static fn () => $plan(null, [['page' => 7, 'image' => $cachePages[0]['image']]]),
    'rejects duplicate cache page' => static fn () => $plan(null, [['page' => 2, 'image' => $cachePages[0]['image']], ['page' => 2, 'image' => $cachePages[1]['image']]]),
    'rejects short cache image' => static fn () => $plan(null, [['page' => 2, 'image' => 'short']]),
    'rejects short current image' => static fn () => $plan(null, [['page' => 2, 'image' => $cachePages[0]['image'], 'current_image' => 'short']]),
    'rejects negative cache bytes' => static fn () => $plan(null, [['page' => 2, 'image' => $cachePages[0]['image'], 'bytes' => -1]]),
    'rejects bad spill threshold' => static fn () => SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $walBytes, $cachePages, 8, 0),
    'rejects bad max spill' => static fn () => SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $walBytes, $cachePages, 8, 3, null, 0),
];

foreach ($throws as $name => $callback) {
    $tests['pager cache spill wal recovery current source next135 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
