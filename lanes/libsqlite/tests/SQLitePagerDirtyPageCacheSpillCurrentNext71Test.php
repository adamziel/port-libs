<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerDirtyPageCacheSpillPlan;

$tests = [];

$cachePages = [
    ['page' => 5, 'bytes' => 512, 'journaled' => true],
    ['page' => 2, 'bytes' => 512, 'journaled' => true, 'pinned' => true],
    ['page' => 4, 'bytes' => 256, 'journaled' => false],
    ['page' => 3, 'bytes' => 128, 'journaled' => true, 'dirty' => false],
    ['page' => 7, 'bytes' => 512, 'journaled' => true],
];

$spill = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::currentNext(8, 6, 4, $cachePages, true, 'reserved', true, 1);
$spillAll = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::currentNext(8, 6, 4, $cachePages, true, 'exclusive');
$unsynced = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::currentNext(8, 6, 4, $cachePages, false, 'reserved');
$shared = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::currentNext(8, 6, 4, $cachePages, true, 'shared');
$disabled = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::currentNext(8, 6, 4, $cachePages, true, 'reserved', false);
$belowThreshold = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::currentNext(8, 3, 4, $cachePages, true, 'reserved');
$noEligible = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::currentNext(8, 6, 4, [
    ['page' => 1, 'journaled' => false],
    ['page' => 2, 'journaled' => true, 'pinned' => true],
    ['page' => 3, 'journaled' => true, 'dirty' => false],
], true, 'reserved');

$cases = [
    'spill status' => [static fn (): mixed => $spill()['status'], 'spilled'],
    'spill preserves current page count' => [static fn (): mixed => $spill()['current']['page_count'], 8],
    'spill preserves next page count' => [static fn (): mixed => $spill()['next']['page_count'], 8],
    'spill records current cache size' => [static fn (): mixed => $spill()['current']['cache_size'], 6],
    'spill records threshold' => [static fn (): mixed => $spill()['current']['spill_threshold'], 4],
    'spill records sorted dirty pages' => [static fn (): mixed => $spill()['current']['dirty_pages'], [2, 4, 5, 7]],
    'spill records sorted journaled pages' => [static fn (): mixed => $spill()['current']['journaled_pages'], [2, 3, 5, 7]],
    'spill records pinned pages' => [static fn (): mixed => $spill()['current']['pinned_pages'], [2]],
    'spill records journal synced' => [static fn (): mixed => $spill()['current']['journal_synced'], true],
    'spill records current lock' => [static fn (): mixed => $spill()['current']['lock'], 'reserved'],
    'spill records cache spill enabled' => [static fn (): mixed => $spill()['current']['cache_spill_enabled'], true],
    'spill chooses lowest eligible page first' => [static fn (): mixed => $spill()['next']['spilled_pages'], [5]],
    'spill leaves pinned and unjournaled dirty pages dirty' => [static fn (): mixed => $spill()['next']['dirty_pages'], [2, 4, 7]],
    'spill lowers cache size by spilled page count' => [static fn (): mixed => $spill()['next']['cache_size'], 5],
    'spill promotes lock to exclusive' => [static fn (): mixed => $spill()['next']['lock'], 'exclusive'],
    'spill marks database image dirty' => [static fn (): mixed => $spill()['next']['database_image'], 'contains_spilled_dirty_pages'],
    'spill keeps write transaction open' => [static fn (): mixed => $spill()['next']['transaction_state'], 'write_transaction_open'],
    'spill retains rollback journal requirement' => [static fn (): mixed => $spill()['next']['journal_required_for_rollback'], true],
    'spill page count' => [static fn (): mixed => $spill()['spilled_page_count'], 1],
    'spill has no blocked reasons' => [static fn (): mixed => $spill()['blocked_reasons'], []],
    'spill promote operation' => [static fn (): mixed => $spill()['operations'][0]['op'], 'promote_lock'],
    'spill promote from reserved' => [static fn (): mixed => $spill()['operations'][0]['from'], 'reserved'],
    'spill promote reason' => [static fn (): mixed => $spill()['operations'][0]['reason'], 'cache_spill_requires_exclusive_lock'],
    'spill writes selected page' => [static fn (): mixed => $spill()['operations'][1]['page'], 5],
    'spill writes selected bytes' => [static fn (): mixed => $spill()['operations'][1]['bytes'], 512],
    'spill write reason' => [static fn (): mixed => $spill()['operations'][1]['reason'], 'spill_dirty_journaled_page'],
    'spill marks cache page clean' => [static fn (): mixed => $spill()['operations'][2]['op'], 'mark_page_clean_in_cache'],
    'spill clean page matches write' => [static fn (): mixed => $spill()['operations'][2]['page'], 5],
    'spill dependencies include cache spill slice' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-current-next71', $spill()['dependencies'], true), true],
    'spill dependencies include journal sync' => [static fn (): mixed => in_array('sqlite-pager-journal-sync-before-spill', $spill()['dependencies'], true), true],
    'spill dependencies include exclusive lock' => [static fn (): mixed => in_array('sqlite-pager-exclusive-lock-before-spill', $spill()['dependencies'], true), true],
    'exclusive spill does not promote already exclusive lock' => [static fn (): mixed => $spillAll()['operations'][0]['op'], 'write_database_page'],
    'exclusive spill writes both eligible pages' => [static fn (): mixed => $spillAll()['next']['spilled_pages'], [5, 7]],
    'exclusive spill clears only eligible dirty pages' => [static fn (): mixed => $spillAll()['next']['dirty_pages'], [2, 4]],
    'exclusive spill cache size falls by two' => [static fn (): mixed => $spillAll()['next']['cache_size'], 4],
    'unsynced journal defers spill' => [static fn (): mixed => $unsynced()['status'], 'deferred'],
    'unsynced journal reason' => [static fn (): mixed => $unsynced()['blocked_reasons'], ['journal_not_synced']],
    'unsynced journal keeps database image unchanged' => [static fn (): mixed => $unsynced()['next']['database_image'], 'unchanged'],
    'unsynced journal keeps lock state' => [static fn (): mixed => $unsynced()['next']['lock'], 'reserved'],
    'unsynced journal operation is defer' => [static fn (): mixed => $unsynced()['operations'][0]['op'], 'defer_cache_spill'],
    'shared lock defers spill' => [static fn (): mixed => $shared()['blocked_reasons'], ['exclusive_lock_unavailable']],
    'disabled cache spill defers spill' => [static fn (): mixed => $disabled()['blocked_reasons'], ['cache_spill_disabled']],
    'below threshold defers spill' => [static fn (): mixed => $belowThreshold()['blocked_reasons'], ['cache_below_spill_threshold']],
    'no eligible defers spill' => [static fn (): mixed => $noEligible()['blocked_reasons'], ['no_journaled_unpinned_dirty_pages']],
    'deferred spill keeps dirty pages' => [static fn (): mixed => $noEligible()['next']['dirty_pages'], [1, 2]],
    'deferred spill has no spilled pages' => [static fn (): mixed => $noEligible()['next']['spilled_pages'], []],
    'pending lock can spill' => [static fn (): mixed => SQLitePagerDirtyPageCacheSpillPlan::currentNext(3, 4, 4, [['page' => 1, 'journaled' => true]], true, 'pending')['next']['lock'], 'exclusive'],
    'uppercase lock is normalized' => [static fn (): mixed => SQLitePagerDirtyPageCacheSpillPlan::currentNext(3, 4, 4, [['page' => 1, 'journaled' => true]], true, ' RESERVED ')['current']['lock'], 'reserved'],
    'clean page is not dirty' => [static fn (): mixed => SQLitePagerDirtyPageCacheSpillPlan::currentNext(3, 4, 4, [['page' => 1, 'journaled' => true, 'dirty' => false]], true, 'reserved')['current']['dirty_pages'], []],
    'zero bytes accepted' => [static fn (): mixed => SQLitePagerDirtyPageCacheSpillPlan::currentNext(3, 4, 4, [['page' => 1, 'bytes' => 0, 'journaled' => true]], true, 'reserved')['operations'][1]['bytes'], 0],
    'bad page count rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::currentNext(0, 1, 1, [], true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad cache size rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::currentNext(1, -1, 1, [], true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad threshold rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::currentNext(1, 1, 0, [], true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad max spill rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::currentNext(1, 1, 1, [], true, 'reserved', true, 0); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad lock rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::currentNext(1, 1, 1, [], true, 'write'); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad page rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::currentNext(1, 1, 1, [['page' => 0]], true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'page past database size rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::currentNext(1, 1, 1, [['page' => 2]], true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad bytes rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::currentNext(1, 1, 1, [['page' => 1, 'bytes' => -1]], true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'duplicate page rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::currentNext(2, 2, 1, [['page' => 1], ['page' => 1]], true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager dirty-page cache spill current next71 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
