<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerDirtyPageCacheSpillPlan;

$tests = [];

$rollbackPages = [
    ['page' => 3, 'bytes' => 4096, 'journaled' => true],
    ['page' => 4, 'bytes' => 2048, 'journaled' => true, 'pinned' => true],
    ['page' => 6, 'bytes' => 4096, 'journaled' => false],
    ['page' => 8, 'bytes' => 4096, 'journaled' => true],
];
$walPages = [
    ['page' => 2, 'bytes' => 4096, 'walFrame' => 11],
    ['page' => 5, 'bytes' => 4096, 'walFrame' => 12],
    ['page' => 7, 'bytes' => 1024, 'journaled' => false],
];

$delete = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(10, 6, 4, $rollbackPages, 'delete', true, 'reserved', true, 1);
$truncate = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(10, 6, 4, $rollbackPages, 'truncate', true, 'pending');
$persist = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(10, 6, 4, $rollbackPages, 'persist', true, 'exclusive');
$memory = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(10, 6, 4, [
    ['page' => 1, 'bytes' => 512],
    ['page' => 9, 'bytes' => 512, 'dirty' => false],
], 'memory', true, 'reserved');
$wal = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(10, 7, 4, $walPages, 'wal', true, 'shared', true, 2);
$off = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(10, 7, 4, $rollbackPages, 'off', true, 'exclusive');
$unsynced = static fn (): array => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(10, 7, 4, $rollbackPages, 'delete', false, 'reserved');

$cases = [
    'delete status spilled' => [static fn (): mixed => $delete()['status'], 'spilled'],
    'delete journal mode recorded' => [static fn (): mixed => $delete()['journal_mode'], 'delete'],
    'delete next journal mode recorded' => [static fn (): mixed => $delete()['next']['journal_mode'], 'delete'],
    'delete spill target rollback journal' => [static fn (): mixed => $delete()['spill_target'], 'database_pages_after_rollback_journal'],
    'delete next spill target rollback journal' => [static fn (): mixed => $delete()['next']['spill_target'], 'database_pages_after_rollback_journal'],
    'delete picks first journaled unpinned page' => [static fn (): mixed => $delete()['next']['spilled_pages'], [3]],
    'delete leaves pinned unjournaled later dirty pages' => [static fn (): mixed => $delete()['next']['dirty_pages'], [4, 6, 8]],
    'delete promotes reserved lock' => [static fn (): mixed => $delete()['operations'][0]['op'], 'promote_lock'],
    'delete writes database page' => [static fn (): mixed => $delete()['operations'][1]['op'], 'write_database_page'],
    'delete write reason preserved' => [static fn (): mixed => $delete()['operations'][1]['reason'], 'spill_dirty_journaled_page'],
    'delete database image changed' => [static fn (): mixed => $delete()['next']['database_image'], 'contains_spilled_dirty_pages'],
    'delete keeps rollback journal required' => [static fn (): mixed => $delete()['next']['journal_required_for_rollback'], true],
    'delete no wal frame pages' => [static fn (): mixed => $delete()['next']['wal_frame_pages'], []],
    'delete dependency marker' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-journalmode-current-source-next107', $delete()['dependencies'], true), true],
    'delete rollback dependency marker' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-rollback-journal-mode-routing', $delete()['dependencies'], true), true],
    'truncate normalized action' => [static fn (): mixed => $truncate()['journal_mode'], 'truncate'],
    'truncate pending lock promotes' => [static fn (): mixed => $truncate()['operations'][0]['from'], 'pending'],
    'truncate spills both eligible pages' => [static fn (): mixed => $truncate()['next']['spilled_pages'], [3, 8]],
    'truncate remaining dirty pages' => [static fn (): mixed => $truncate()['next']['dirty_pages'], [4, 6]],
    'truncate cache size falls by two' => [static fn (): mixed => $truncate()['next']['cache_size'], 4],
    'persist already exclusive skips promotion' => [static fn (): mixed => $persist()['operations'][0]['op'], 'write_database_page'],
    'persist lock stays exclusive' => [static fn (): mixed => $persist()['next']['lock'], 'exclusive'],
    'persist target rollback journal' => [static fn (): mixed => $persist()['next']['spill_target'], 'database_pages_after_rollback_journal'],
    'memory journal mode recorded' => [static fn (): mixed => $memory()['journal_mode'], 'memory'],
    'memory target uses memory journal' => [static fn (): mixed => $memory()['spill_target'], 'database_pages_after_memory_journal'],
    'memory defaults dirty page to journaled' => [static fn (): mixed => $memory()['current']['journaled_pages'], [1]],
    'memory clean page not journaled by default' => [static fn (): mixed => $memory()['current']['dirty_pages'], [1]],
    'memory spills dirty page' => [static fn (): mixed => $memory()['next']['spilled_pages'], [1]],
    'wal status spilled' => [static fn (): mixed => $wal()['status'], 'spilled'],
    'wal journal mode recorded' => [static fn (): mixed => $wal()['journal_mode'], 'wal'],
    'wal spill target frames' => [static fn (): mixed => $wal()['spill_target'], 'wal_frames'],
    'wal writes first frame op' => [static fn (): mixed => $wal()['operations'][0]['op'], 'append_wal_frame'],
    'wal writes first frame page' => [static fn (): mixed => $wal()['operations'][0]['page'], 2],
    'wal marks first frame clean' => [static fn (): mixed => $wal()['operations'][1]['reason'], 'wal_spill_frame_completed'],
    'wal writes second frame page' => [static fn (): mixed => $wal()['operations'][2]['page'], 5],
    'wal has no lock promotion op' => [static fn (): mixed => in_array('promote_lock', array_column($wal()['operations'], 'op'), true), false],
    'wal database image unchanged until checkpoint' => [static fn (): mixed => $wal()['next']['database_image'], 'unchanged_until_checkpoint'],
    'wal records frame pages' => [static fn (): mixed => $wal()['next']['wal_frame_pages'], [2, 5]],
    'wal does not require rollback journal' => [static fn (): mixed => $wal()['next']['journal_required_for_rollback'], false],
    'wal dirty pages leave unjournaled page' => [static fn (): mixed => $wal()['next']['dirty_pages'], [7]],
    'wal dependency marker' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-wal-frame-routing', $wal()['dependencies'], true), true],
    'off status deferred' => [static fn (): mixed => $off()['status'], 'deferred'],
    'off journal mode recorded' => [static fn (): mixed => $off()['journal_mode'], 'off'],
    'off spill target deferred' => [static fn (): mixed => $off()['spill_target'], 'deferred_until_commit'],
    'off records journal mode blocker' => [static fn (): mixed => $off()['journal_mode_blocked_reason'], 'journal_mode_off_has_no_rollback_source'],
    'off blocked by disabled spill' => [static fn (): mixed => $off()['blocked_reasons'], ['cache_spill_disabled']],
    'off database image unchanged' => [static fn (): mixed => $off()['next']['database_image'], 'unchanged'],
    'unsynced delete defers' => [static fn (): mixed => $unsynced()['status'], 'deferred'],
    'unsynced delete reason' => [static fn (): mixed => $unsynced()['blocked_reasons'], ['journal_not_synced']],
    'uppercase journal mode normalized' => [static fn (): mixed => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(3, 3, 2, [['page' => 1, 'journaled' => true]], ' WAL ', true)['journal_mode'], 'wal'],
    'disabled wal defers' => [static fn (): mixed => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(3, 3, 2, [['page' => 1, 'walFrame' => 1]], 'wal', true, 'shared', false)['blocked_reasons'], ['cache_spill_disabled']],
    'below threshold wal defers' => [static fn (): mixed => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(3, 1, 2, [['page' => 1, 'walFrame' => 1]], 'wal', true)['blocked_reasons'], ['cache_below_spill_threshold']],
    'pinned wal page defers no eligible' => [static fn (): mixed => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(3, 3, 2, [['page' => 1, 'walFrame' => 1, 'pinned' => true]], 'wal', true)['blocked_reasons'], ['no_journaled_unpinned_dirty_pages']],
    'max spill one wal page' => [static fn (): mixed => SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(10, 5, 2, $walPages, 'wal', true, 'shared', true, 1)['next']['spilled_pages'], [2]],
    'bad journal mode rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(1, 1, 1, [], 'bad', true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad wal frame rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(1, 1, 1, [['page' => 1, 'walFrame' => 0]], 'wal', true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad page still rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(1, 1, 1, [['page' => 0]], 'delete', true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad lock still rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(1, 1, 1, [], 'delete', true, 'bogus'); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'page past database still rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(1, 1, 1, [['page' => 2]], 'delete', true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'duplicate page still rejected' => [static function (): mixed { try { SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(2, 2, 1, [['page' => 1], ['page' => 1]], 'memory', true); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager cache spill journalmode current source next107 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
