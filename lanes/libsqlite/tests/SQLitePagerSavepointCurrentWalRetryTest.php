<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageWrite(1);
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings');
    $stack->recordPageWrite(3);
    $stack->recordWalFrameWrite(3, 3);
    $stack->savepoint('single-option');
    $stack->recordPageWrite(4);
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 5, true);

    return $stack;
};

$pluginPlan = static fn (): array => $makeStack()->rollbackToCurrentAndRecordWalFrame('plugin-settings', 6, true);
$caseInsensitivePlan = static fn (): array => $makeStack()->rollbackToCurrentAndRecordWalFrame('PLUGIN-SETTINGS', 6);
$singleOptionPlan = static fn (): array => $makeStack()->rollbackToCurrentAndRecordWalFrame('single-option', 7);

$cases = [
    'plugin plan savepoint' => [static fn (): mixed => $pluginPlan()['savepoint'], 'plugin-settings'],
    'plugin plan found index' => [static fn (): mixed => $pluginPlan()['found_index'], 1],
    'plugin plan rollback frame' => [static fn (): mixed => $pluginPlan()['rollback_to_frame'], 2],
    'plugin plan next wal frame reuses current prefix' => [static fn (): mixed => $pluginPlan()['next_wal_frame_index'], 3],
    'plugin plan next page' => [static fn (): mixed => $pluginPlan()['next_page_number'], 6],
    'plugin plan commit marker' => [static fn (): mixed => $pluginPlan()['next_commit_frame'], true],
    'plugin plan discarded frame indexes' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'frame_index'), [3, 4, 5]],
    'plugin plan discarded frame pages' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'page_number'), [3, 4, 5]],
    'plugin plan discarded commit markers' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'commit_frame'), [false, false, true]],
    'plugin plan discarded frame names' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'frame_name'), ['plugin-settings', 'single-option', 'single-option']],
    'plugin plan discarded page numbers' => [static fn (): mixed => $pluginPlan()['discarded_page_numbers'], [3, 4, 5]],
    'plugin plan retained frame names' => [static fn (): mixed => $pluginPlan()['retained_frame_names'], ['wp-import', 'plugin-settings']],
    'plugin plan retained wal before next' => [static fn (): mixed => $pluginPlan()['retained_wal_frame_indexes_before_next'], [1, 2]],
    'plugin plan pending pages before next' => [static fn (): mixed => $pluginPlan()['pending_page_numbers_before_next'], [1, 2]],
    'plugin plan pending wal after next' => [static fn (): mixed => $pluginPlan()['pending_wal_frame_indexes_after_next'], [1, 2, 3]],
    'plugin plan pending pages after next' => [static fn (): mixed => $pluginPlan()['pending_page_numbers_after_next'], [1, 2, 6]],
    'plugin plan keeps current savepoint active' => [static fn (): mixed => $pluginPlan()['current_savepoint_active_after'], true],
    'plugin plan keeps transaction active' => [static fn (): mixed => $pluginPlan()['transaction_active_after'], true],
    'plugin plan dependency keeps savepoint marker' => [static fn (): mixed => in_array('sqlite-savepoint-rollback-to-current-keeps-savepoint', $pluginPlan()['dependencies'], true), true],
    'plugin plan dependency keeps stable pager marker' => [static fn (): mixed => in_array('sqlite-pager-savepoint-wal-retry-current', $pluginPlan()['dependencies'], true), true],
    'plugin plan dependency keeps legacy pager marker alias' => [static fn (): mixed => in_array('sqlite-pager-current-next-wal-frame64', $pluginPlan()['dependencies'], true), true],
    'plugin stack names after next write' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndRecordWalFrame('plugin-settings', 6, true);
        return $stack->names();
    }, ['wp-import', 'plugin-settings']],
    'plugin stack wal state transaction frames' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndRecordWalFrame('plugin-settings', 6, true);
        return $stack->walFrameState()[0]['wal_frame_indexes'];
    }, [1, 2]],
    'plugin stack wal state savepoint frames' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndRecordWalFrame('plugin-settings', 6, true);
        return $stack->walFrameState()[1]['wal_frame_indexes'];
    }, [3]],
    'plugin stack savepoint wal start unchanged' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndRecordWalFrame('plugin-settings', 6, true);
        return $stack->walFrameState()[1]['wal_start_frame'];
    }, 2],
    'plugin stack page state transaction pages' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndRecordWalFrame('plugin-settings', 6, true);
        return $stack->toArray()[0]['page_numbers'];
    }, [1, 2]],
    'plugin stack page state current savepoint page' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndRecordWalFrame('plugin-settings', 6, true);
        return $stack->toArray()[1]['page_numbers'];
    }, [6]],
    'plugin stack release after next merges new page' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndRecordWalFrame('plugin-settings', 6, true);
        return $stack->releaseWithPlan('plugin-settings')['merged_page_numbers'];
    }, [6]],
    'plugin stack commit after next includes rewritten page' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndRecordWalFrame('plugin-settings', 6, true);
        return $stack->commitWithPlan()['committed_page_numbers'];
    }, [1, 2, 6]],
    'plugin stack can append following frame' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndRecordWalFrame('plugin-settings', 6);
        $stack->recordWalFrameWrite(4, 7);
        return $stack->pendingWalFrameIndexes();
    }, [1, 2, 3, 4]],
    'plugin stack rejects old discarded frame after next' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndRecordWalFrame('plugin-settings', 6);
        try {
            $stack->recordWalFrameWrite(3, 7);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'case insensitive plan keeps input savepoint spelling' => [static fn (): mixed => $caseInsensitivePlan()['savepoint'], 'PLUGIN-SETTINGS'],
    'case insensitive plan found target' => [static fn (): mixed => $caseInsensitivePlan()['found_index'], 1],
    'case insensitive plan next frame' => [static fn (): mixed => $caseInsensitivePlan()['next_wal_frame_index'], 3],
    'case insensitive plan current active' => [static fn (): mixed => $caseInsensitivePlan()['current_savepoint_active_after'], true],
    'single option plan found leaf savepoint' => [static fn (): mixed => $singleOptionPlan()['found_index'], 2],
    'single option plan rollback frame' => [static fn (): mixed => $singleOptionPlan()['rollback_to_frame'], 3],
    'single option plan next frame' => [static fn (): mixed => $singleOptionPlan()['next_wal_frame_index'], 4],
    'single option plan discarded indexes' => [static fn (): mixed => array_column($singleOptionPlan()['discarded_wal_frames'], 'frame_index'), [4, 5]],
    'single option plan discarded pages' => [static fn (): mixed => $singleOptionPlan()['discarded_page_numbers'], [4, 5]],
    'single option plan retained frames' => [static fn (): mixed => $singleOptionPlan()['retained_frame_names'], ['wp-import', 'plugin-settings', 'single-option']],
    'single option plan retained wal before next' => [static fn (): mixed => $singleOptionPlan()['retained_wal_frame_indexes_before_next'], [1, 2, 3]],
    'single option plan pages before next' => [static fn (): mixed => $singleOptionPlan()['pending_page_numbers_before_next'], [1, 2, 3]],
    'single option plan pending wal after next' => [static fn (): mixed => $singleOptionPlan()['pending_wal_frame_indexes_after_next'], [1, 2, 3, 4]],
    'single option plan pending pages after next' => [static fn (): mixed => $singleOptionPlan()['pending_page_numbers_after_next'], [1, 2, 3, 7]],
    'single option plan current active' => [static fn (): mixed => $singleOptionPlan()['current_savepoint_active_after'], true],
    'transaction savepoint rollback starts next after zero frame' => [static function (): mixed {
        $stack = new SQLiteSavepointStack();
        $stack->savepoint('implicit');
        $stack->recordWalFrameWrite(1, 1);
        return $stack->rollbackToCurrentAndRecordWalFrame('implicit', 2)['next_wal_frame_index'];
    }, 1],
    'transaction savepoint rollback keeps only rewritten frame' => [static function (): mixed {
        $stack = new SQLiteSavepointStack();
        $stack->savepoint('implicit');
        $stack->recordWalFrameWrite(1, 1);
        $stack->rollbackToCurrentAndRecordWalFrame('implicit', 2);
        return $stack->pendingWalFrameIndexes();
    }, [1]],
    'transaction savepoint rollback keeps transaction alive' => [static function (): mixed {
        $stack = new SQLiteSavepointStack();
        $stack->savepoint('implicit');
        return $stack->rollbackToCurrentAndRecordWalFrame('implicit', 2)['transaction_active_after'];
    }, true],
    'transaction savepoint rollback can commit replacement' => [static function (): mixed {
        $stack = new SQLiteSavepointStack();
        $stack->savepoint('implicit');
        $stack->recordWalFrameWrite(1, 1);
        $stack->rollbackToCurrentAndRecordWalFrame('implicit', 2, true);
        return $stack->commitWithPlan()['committed_page_numbers'];
    }, [2]],
    'missing savepoint rejected' => [static function () use ($makeStack): mixed {
        try {
            $makeStack()->rollbackToCurrentAndRecordWalFrame('missing', 6);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'zero next page rejected' => [static function () use ($makeStack): mixed {
        try {
            $makeStack()->rollbackToCurrentAndRecordWalFrame('plugin-settings', 0);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'negative next page rejected' => [static function () use ($makeStack): mixed {
        try {
            $makeStack()->rollbackToCurrentAndRecordWalFrame('plugin-settings', -1);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint current wal retry ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
