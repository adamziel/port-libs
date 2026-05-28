<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerTransactionStatePlan;

$tests = [];

$writes = [
    ['page' => 4, 'bytes' => 512],
    ['page' => 2, 'bytes' => 512, 'spill' => true],
    ['page' => 7, 'bytes' => 512],
    ['page' => 4, 'bytes' => 128],
];
$commit = static fn (): array => SQLitePagerTransactionStatePlan::currentNext(5, 41, $writes, 'commit', 3);
$rollback = static fn (): array => SQLitePagerTransactionStatePlan::currentNext(5, 41, $writes, 'rollback', 3);
$readClose = static fn (): array => SQLitePagerTransactionStatePlan::currentNext(5, 41, [], 'close', 3);
$memoryCommit = static fn (): array => SQLitePagerTransactionStatePlan::currentNext(2, 7, [['page' => 1, 'bytes' => 64]], 'commit', 2, false, false);

$cases = [
    'commit status' => [static fn (): mixed => $commit()['status'], 'committed'],
    'commit action normalized' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(5, 41, $writes, ' COMMIT ')['action'], 'commit'],
    'commit current page count' => [static fn (): mixed => $commit()['current']['page_count'], 5],
    'commit next page count grows to highest page' => [static fn (): mixed => $commit()['next']['page_count'], 7],
    'commit increments change counter' => [static fn (): mixed => $commit()['next']['change_counter'], 42],
    'commit wraps change counter' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(1, 0xffffffff, [['page' => 1]], 'commit')['next']['change_counter'], 0],
    'commit dirty pages are unique sorted' => [static fn (): mixed => $commit()['current']['dirty_pages'], [2, 4, 7]],
    'commit dirty page count' => [static fn (): mixed => $commit()['dirty_page_count'], 3],
    'commit spilled pages are unique sorted' => [static fn (): mixed => $commit()['current']['spilled_pages'], [2, 4, 7]],
    'commit spilled page count' => [static fn (): mixed => $commit()['spilled_page_count'], 3],
    'commit keeps journal opened evidence' => [static fn (): mixed => $commit()['current']['journal_opened'], true],
    'commit keeps exclusive lock evidence' => [static fn (): mixed => $commit()['current']['exclusive_lock'], true],
    'commit next journal action' => [static fn (): mixed => $commit()['next']['journal_action'], 'finalize_commit_journal'],
    'commit next lock is shared' => [static fn (): mixed => $commit()['next']['lock'], 'shared'],
    'commit next cache state clean' => [static fn (): mixed => $commit()['next']['cache_state'], 'clean'],
    'commit clears next dirty pages' => [static fn (): mixed => $commit()['next']['dirty_pages'], []],
    'commit clears next spilled pages' => [static fn (): mixed => $commit()['next']['spilled_pages'], []],
    'commit operation count' => [static fn (): mixed => count($commit()['operations']), 10],
    'commit first operation marks dirty' => [static fn (): mixed => $commit()['operations'][0]['op'], 'mark_dirty'],
    'commit duplicate dirty write preserved in operations' => [static fn (): mixed => $commit()['operations'][3]['page'], 4],
    'commit syncs journal before pages' => [static fn (): mixed => $commit()['operations'][4]['op'], 'sync_journal'],
    'commit writes first sorted page' => [static fn (): mixed => $commit()['operations'][5]['page'], 2],
    'commit writes last sorted page' => [static fn (): mixed => $commit()['operations'][7]['page'], 7],
    'commit syncs database' => [static fn (): mixed => $commit()['operations'][8]['op'], 'sync_database'],
    'commit releases dirty cache' => [static fn (): mixed => $commit()['operations'][9]['reason'], 'commit_completed'],
    'commit dependencies include pager current next' => [static fn (): mixed => in_array('sqlite-pager-transaction-current-next', $commit()['dependencies'], true), true],
    'commit dependencies include dirty cache state' => [static fn (): mixed => in_array('sqlite-pager-cache-dirty-page-state', $commit()['dependencies'], true), true],
    'commit below threshold only explicit spill' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(5, 1, array_slice($writes, 0, 2), 'commit', 5)['current']['spilled_pages'], [2]],
    'commit at threshold spills threshold page' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(5, 1, array_slice($writes, 0, 3), 'commit', 3)['current']['spilled_pages'], [2, 7]],
    'commit page count does not shrink' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(9, 1, [['page' => 2]], 'commit')['next']['page_count'], 9],
    'memory commit journal action' => [static fn (): mixed => $memoryCommit()['next']['journal_action'], 'memory_journal_discard'],
    'memory commit releases no exclusive lock' => [static fn (): mixed => $memoryCommit()['next']['lock'], 'none'],
    'memory commit status' => [static fn (): mixed => $memoryCommit()['status'], 'committed'],
    'rollback status' => [static fn (): mixed => $rollback()['status'], 'rolled_back'],
    'rollback page count preserved' => [static fn (): mixed => $rollback()['next']['page_count'], 5],
    'rollback change counter preserved' => [static fn (): mixed => $rollback()['next']['change_counter'], 41],
    'rollback journal action' => [static fn (): mixed => $rollback()['next']['journal_action'], 'restore_journal_pages'],
    'rollback lock after' => [static fn (): mixed => $rollback()['next']['lock'], 'shared'],
    'rollback operations include restore' => [static fn (): mixed => $rollback()['operations'][4]['op'], 'restore_pages'],
    'rollback restores sorted dirty pages' => [static fn (): mixed => $rollback()['operations'][4]['pages'], [2, 4, 7]],
    'rollback releases dirty cache' => [static fn (): mixed => $rollback()['operations'][5]['op'], 'release_dirty_cache'],
    'rollback memory journal action' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(5, 1, [['page' => 2]], 'rollback', 3, false)['next']['journal_action'], 'discard_memory_pages'],
    'rollback duplicate pages still unique' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(5, 1, [['page' => 2], ['page' => 2]], 'rollback')['current']['dirty_pages'], [2]],
    'read close status' => [static fn (): mixed => $readClose()['status'], 'read_transaction_closed'],
    'read close operation' => [static fn (): mixed => $readClose()['operations'][0]['op'], 'close_read_transaction'],
    'read close page count unchanged' => [static fn (): mixed => $readClose()['next']['page_count'], 5],
    'read close change counter unchanged' => [static fn (): mixed => $readClose()['next']['change_counter'], 41],
    'read close journal action' => [static fn (): mixed => $readClose()['next']['journal_action'], 'close_unused_journal'],
    'read close lock after' => [static fn (): mixed => $readClose()['next']['lock'], 'none'],
    'commit without dirty status' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(5, 41, [], 'commit')['status'], 'committed_without_dirty_pages'],
    'commit without dirty action closes read transaction' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(5, 41, [], 'commit')['operations'][0]['reason'], 'no_dirty_pages'],
    'close without journal action' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(5, 41, [], 'close', 3, false)['next']['journal_action'], 'none'],
    'bad page count rejected' => [static function (): mixed { try { SQLitePagerTransactionStatePlan::currentNext(0, 1, []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad change counter rejected' => [static function (): mixed { try { SQLitePagerTransactionStatePlan::currentNext(1, -1, []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad spill threshold rejected' => [static function (): mixed { try { SQLitePagerTransactionStatePlan::currentNext(1, 1, [], 'commit', 0); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad action rejected' => [static function (): mixed { try { SQLitePagerTransactionStatePlan::currentNext(1, 1, [], 'abort'); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad write page rejected' => [static function (): mixed { try { SQLitePagerTransactionStatePlan::currentNext(1, 1, [['page' => 0]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad write bytes rejected' => [static function (): mixed { try { SQLitePagerTransactionStatePlan::currentNext(1, 1, [['page' => 1, 'bytes' => -1]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'missing bytes default accepted' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(1, 1, [['page' => 1]])['operations'][0]['bytes'], 0],
    'write order keeps original dirty events' => [static fn (): mixed => array_column(array_slice($commit()['operations'], 0, 4), 'page'), [4, 2, 7, 4]],
    'uppercase rollback action normalized' => [static fn (): mixed => SQLitePagerTransactionStatePlan::currentNext(1, 1, [['page' => 1]], 'ROLLBACK')['action'], 'rollback'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager transaction current next56 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
