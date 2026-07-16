<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$page = static fn (string $label): string => str_pad($label, 64, '.');

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $page('before-header'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);

    $stack->savepoint('plugin-batch');
    $stack->beginStatementJournal('insert-active-plugin');
    $stack->recordStatementPageImageWrite('insert-active-plugin', 3, $page('before-active-plugin'));
    $stack->recordStatementWalFrameWrite('insert-active-plugin', 3, 3);

    $stack->savepoint('single-option');
    $stack->beginStatementJournal('insert-single-option');
    $stack->recordStatementPageImageWrite('insert-single-option', 4, $page('before-single-option'));
    $stack->recordStatementWalFrameWrite('insert-single-option', 4, 4);
    $stack->recordStatementWalFrameWrite('insert-single-option', 5, 5, true);

    return $stack;
};

$pluginPlan = static fn (): array => $makeStack()->rollbackReleaseAndBeginSavepoint(
    'plugin-batch',
    'plugin-retry',
    6,
    $page('before-retry-option'),
    64,
    true
);
$leafPlan = static fn (): array => $makeStack()->rollbackReleaseAndBeginSavepoint(
    'single-option',
    'single-option-retry',
    7,
    $page('before-leaf-retry'),
    64
);
$casePlan = static fn (): array => $makeStack()->rollbackReleaseAndBeginSavepoint(
    'PLUGIN-BATCH',
    'Plugin-Retry-Case',
    8,
    $page('before-case-retry'),
    64
);
$outerPlan = static function () use ($page): array {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordWalFrameWrite(1, 1);

    return $stack->rollbackReleaseAndBeginSavepoint(
        'wp-import',
        'after-outer-release',
        2,
        $page('before-after-outer'),
        64
    );
};

$cases = [
    'plugin savepoint' => [static fn (): mixed => $pluginPlan()['savepoint'], 'plugin-batch'],
    'plugin next savepoint' => [static fn (): mixed => $pluginPlan()['next_savepoint'], 'plugin-retry'],
    'plugin found index' => [static fn (): mixed => $pluginPlan()['found_index'], 1],
    'plugin rollback frame' => [static fn (): mixed => $pluginPlan()['rollback_to_frame'], 2],
    'plugin next wal frame' => [static fn (): mixed => $pluginPlan()['next_wal_frame_index'], 3],
    'plugin next page' => [static fn (): mixed => $pluginPlan()['next_page_number'], 6],
    'plugin next commit frame' => [static fn (): mixed => $pluginPlan()['next_commit_frame'], true],
    'plugin discarded statement journals' => [static fn (): mixed => $pluginPlan()['discarded_statement_journals'], ['insert-active-plugin', 'insert-single-option']],
    'plugin discarded wal frame indexes' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'frame_index'), [3, 4, 5]],
    'plugin discarded wal frame pages' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'page_number'), [3, 4, 5]],
    'plugin discarded wal commit markers' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'commit_frame'), [false, false, true]],
    'plugin discarded wal frame owners' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'frame_name'), ['plugin-batch', 'single-option', 'single-option']],
    'plugin discarded page numbers' => [static fn (): mixed => $pluginPlan()['discarded_page_numbers'], [3, 4, 5]],
    'plugin retained names before release' => [static fn (): mixed => $pluginPlan()['retained_frame_names_before_release'], ['wp-import', 'plugin-batch']],
    'plugin names after rollback keep target open' => [static fn (): mixed => $pluginPlan()['names_after_rollback'], ['wp-import', 'plugin-batch']],
    'plugin names after release close target' => [static fn (): mixed => $pluginPlan()['names_after_release'], ['wp-import']],
    'plugin names after next savepoint' => [static fn (): mixed => $pluginPlan()['names_after_next'], ['wp-import', 'plugin-retry']],
    'plugin wal state outer start' => [static fn (): mixed => $pluginPlan()['wal_state_after_next'][0]['wal_start_frame'], 0],
    'plugin wal state outer frames' => [static fn (): mixed => $pluginPlan()['wal_state_after_next'][0]['wal_frame_indexes'], [1, 2]],
    'plugin wal state next name' => [static fn (): mixed => $pluginPlan()['wal_state_after_next'][1]['name'], 'plugin-retry'],
    'plugin wal state next start frame' => [static fn (): mixed => $pluginPlan()['wal_state_after_next'][1]['wal_start_frame'], 2],
    'plugin wal state next frames' => [static fn (): mixed => $pluginPlan()['wal_state_after_next'][1]['wal_frame_indexes'], [3]],
    'plugin pending pages after next' => [static fn (): mixed => $pluginPlan()['pending_page_numbers_after_next'], [1, 2, 6]],
    'plugin pending wal after next' => [static fn (): mixed => $pluginPlan()['pending_wal_frame_indexes_after_next'], [1, 2, 3]],
    'plugin released savepoint closed' => [static fn (): mixed => $pluginPlan()['released_savepoint_closed'], true],
    'plugin next savepoint active' => [static fn (): mixed => $pluginPlan()['next_savepoint_active_after'], true],
    'plugin transaction active' => [static fn (): mixed => $pluginPlan()['transaction_active_after'], true],
    'plugin dependency rollback release' => [static fn (): mixed => in_array('sqlite-savepoint-rollback-release-current-next68', $pluginPlan()['dependencies'], true), true],
    'plugin dependency wal prefix' => [static fn (): mixed => in_array('sqlite-pager-savepoint-next-wal-prefix68', $pluginPlan()['dependencies'], true), true],
    'plugin stack names after method' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackReleaseAndBeginSavepoint('plugin-batch', 'plugin-retry', 6, $page('before-retry-option'), 64);
        return $stack->names();
    }, ['wp-import', 'plugin-retry']],
    'plugin stack statement journals cleared' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackReleaseAndBeginSavepoint('plugin-batch', 'plugin-retry', 6, $page('before-retry-option'), 64);
        return $stack->statementJournalState();
    }, []],
    'plugin stack release next savepoint merges retry page' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackReleaseAndBeginSavepoint('plugin-batch', 'plugin-retry', 6, $page('before-retry-option'), 64);
        return $stack->releaseWithPlan('plugin-retry')['merged_page_numbers'];
    }, [6]],
    'plugin stack commit includes retry page' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackReleaseAndBeginSavepoint('plugin-batch', 'plugin-retry', 6, $page('before-retry-option'), 64);
        return $stack->commitWithPlan()['committed_page_numbers'];
    }, [1, 2, 6]],
    'plugin stack rollback to retry restores retry page' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackReleaseAndBeginSavepoint('plugin-batch', 'plugin-retry', 6, $page('before-retry-option'), 64);
        return $stack->rollbackToImagePlan('plugin-retry', 64)['restored_page_numbers'];
    }, [6]],
    'plugin stack rollback to retry uses next frame prefix' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackReleaseAndBeginSavepoint('plugin-batch', 'plugin-retry', 6, $page('before-retry-option'), 64);
        return $stack->walRollbackToPlan('plugin-retry')['rollback_to_frame'];
    }, 2],
    'plugin stack can reuse discarded frame after rollback to retry' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackReleaseAndBeginSavepoint('plugin-batch', 'plugin-retry', 6, $page('before-retry-option'), 64);
        $stack->rollbackToWithPlan('plugin-retry');
        $stack->recordWalFrameWrite(3, 9);
        return $stack->pendingWalFrameIndexes();
    }, [1, 2, 3]],
    'leaf found index' => [static fn (): mixed => $leafPlan()['found_index'], 2],
    'leaf rollback frame' => [static fn (): mixed => $leafPlan()['rollback_to_frame'], 3],
    'leaf next wal frame' => [static fn (): mixed => $leafPlan()['next_wal_frame_index'], 4],
    'leaf discarded statement journals' => [static fn (): mixed => $leafPlan()['discarded_statement_journals'], ['insert-single-option']],
    'leaf discarded wal frames' => [static fn (): mixed => array_column($leafPlan()['discarded_wal_frames'], 'frame_index'), [4, 5]],
    'leaf names after rollback' => [static fn (): mixed => $leafPlan()['names_after_rollback'], ['wp-import', 'plugin-batch', 'single-option']],
    'leaf names after release' => [static fn (): mixed => $leafPlan()['names_after_release'], ['wp-import', 'plugin-batch']],
    'leaf names after next' => [static fn (): mixed => $leafPlan()['names_after_next'], ['wp-import', 'plugin-batch', 'single-option-retry']],
    'leaf keeps outer statement journal' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackReleaseAndBeginSavepoint('single-option', 'single-option-retry', 7, $page('before-leaf-retry'), 64);
        return $stack->statementJournalState()[0]['name'];
    }, 'insert-active-plugin'],
    'leaf pending pages after next' => [static fn (): mixed => $leafPlan()['pending_page_numbers_after_next'], [1, 2, 3, 7]],
    'leaf pending wal after next' => [static fn (): mixed => $leafPlan()['pending_wal_frame_indexes_after_next'], [1, 2, 3, 4]],
    'case plan keeps requested spelling' => [static fn (): mixed => $casePlan()['savepoint'], 'PLUGIN-BATCH'],
    'case plan finds target' => [static fn (): mixed => $casePlan()['found_index'], 1],
    'case plan closes stored target' => [static fn (): mixed => $casePlan()['released_savepoint_closed'], true],
    'case plan keeps next spelling' => [static fn (): mixed => $casePlan()['names_after_next'], ['wp-import', 'Plugin-Retry-Case']],
    'case plan next frame' => [static fn (): mixed => $casePlan()['next_wal_frame_index'], 3],
    'case plan next page' => [static fn (): mixed => $casePlan()['next_page_number'], 8],
    'outer release creates implicit next transaction' => [static fn (): mixed => $outerPlan()['names_after_next'], ['after-outer-release']],
    'outer release rollback frame' => [static fn (): mixed => $outerPlan()['rollback_to_frame'], 0],
    'outer release next frame' => [static fn (): mixed => $outerPlan()['next_wal_frame_index'], 1],
    'outer release pending pages' => [static fn (): mixed => $outerPlan()['pending_page_numbers_after_next'], [2]],
    'outer release pending wal' => [static fn (): mixed => $outerPlan()['pending_wal_frame_indexes_after_next'], [1]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint release next current-next68 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing savepoint rejected' => static fn () => $makeStack()->rollbackReleaseAndBeginSavepoint('missing', 'retry', 6, $page('before'), 64),
    'empty next savepoint rejected' => static fn () => $makeStack()->rollbackReleaseAndBeginSavepoint('plugin-batch', '', 6, $page('before'), 64),
    'zero page rejected' => static fn () => $makeStack()->rollbackReleaseAndBeginSavepoint('plugin-batch', 'retry', 0, $page('before'), 64),
    'empty image rejected' => static fn () => $makeStack()->rollbackReleaseAndBeginSavepoint('plugin-batch', 'retry', 6, '', 64),
    'wrong image size rejected' => static fn () => $makeStack()->rollbackReleaseAndBeginSavepoint('plugin-batch', 'retry', 6, 'short', 64),
    'zero page size rejected' => static fn () => $makeStack()->rollbackReleaseAndBeginSavepoint('plugin-batch', 'retry', 6, $page('before'), 0),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint release next current-next68 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
