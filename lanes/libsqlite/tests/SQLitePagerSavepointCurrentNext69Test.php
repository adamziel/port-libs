<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp_import');
    $stack->recordPageWrite(1);
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin_batch');
    $stack->recordPageImageWrite(3, str_repeat('P', 64));
    $stack->recordWalFrameWrite(3, 3);
    $stack->beginStatementJournal('update_plugin_meta');
    $stack->recordStatementPageImageWrite('update_plugin_meta', 4, str_repeat('M', 64));
    $stack->recordStatementWalFrameWrite('update_plugin_meta', 4, 4);
    $stack->savepoint('single_option');
    $stack->recordPageImageWrite(5, str_repeat('S', 64));
    $stack->recordWalFrameWrite(5, 5);
    $stack->recordWalFrameWrite(6, 6, true);

    return $stack;
};

$pluginPlan = static fn (): array => $makeStack()->rollbackToCurrentAndOpenSavepoint(
    'plugin_batch',
    'retry_single_option',
    7,
    str_repeat('R', 64),
    64,
    true
);

$singlePlan = static fn (): array => $makeStack()->rollbackToCurrentAndOpenSavepoint(
    'single_option',
    'retry_after_leaf',
    8,
    null,
    null,
    false
);

$openOnlyPlan = static fn (): array => $makeStack()->rollbackToCurrentAndOpenSavepoint(
    'plugin_batch',
    'retry_open_only'
);

$casePlan = static fn (): array => $makeStack()->rollbackToCurrentAndOpenSavepoint(
    'PLUGIN_BATCH',
    'Retry_Case',
    9
);

$cases = [
    'plugin savepoint name' => [static fn (): mixed => $pluginPlan()['savepoint'], 'plugin_batch'],
    'plugin next savepoint name' => [static fn (): mixed => $pluginPlan()['next_savepoint'], 'retry_single_option'],
    'plugin found index' => [static fn (): mixed => $pluginPlan()['found_index'], 1],
    'plugin rollback frame' => [static fn (): mixed => $pluginPlan()['rollback_to_frame'], 2],
    'plugin next wal frame' => [static fn (): mixed => $pluginPlan()['next_wal_frame_index'], 3],
    'plugin next page' => [static fn (): mixed => $pluginPlan()['next_page_number'], 7],
    'plugin next commit frame' => [static fn (): mixed => $pluginPlan()['next_commit_frame'], true],
    'plugin discarded statement journal' => [static fn (): mixed => $pluginPlan()['discarded_statement_journals'], ['update_plugin_meta']],
    'plugin discarded wal frame indexes' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'frame_index'), [3, 4, 5, 6]],
    'plugin discarded wal pages' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'page_number'), [3, 4, 5, 6]],
    'plugin discarded wal commit flags' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'commit_frame'), [false, false, false, true]],
    'plugin discarded frame names' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'frame_name'), ['plugin_batch', 'plugin_batch', 'single_option', 'single_option']],
    'plugin discarded page numbers' => [static fn (): mixed => $pluginPlan()['discarded_page_numbers'], [3, 4, 5, 6]],
    'plugin retained frame names' => [static fn (): mixed => $pluginPlan()['retained_frame_names'], ['wp_import', 'plugin_batch']],
    'plugin names after rollback' => [static fn (): mixed => $pluginPlan()['names_after_rollback'], ['wp_import', 'plugin_batch']],
    'plugin names after next' => [static fn (): mixed => $pluginPlan()['names_after_next'], ['wp_import', 'plugin_batch', 'retry_single_option']],
    'plugin current depth after rollback' => [static fn (): mixed => $pluginPlan()['current_depth_after'], 2],
    'plugin next depth after open' => [static fn (): mixed => $pluginPlan()['next_depth_after'], 3],
    'plugin current savepoint active' => [static fn (): mixed => $pluginPlan()['current_savepoint_active_after'], true],
    'plugin next savepoint active' => [static fn (): mixed => $pluginPlan()['next_savepoint_active_after'], true],
    'plugin pending pages after next' => [static fn (): mixed => $pluginPlan()['pending_page_numbers_after_next'], [1, 2, 7]],
    'plugin pending wal after next' => [static fn (): mixed => $pluginPlan()['pending_wal_frame_indexes_after_next'], [1, 2, 3]],
    'plugin next frame wal start' => [static fn (): mixed => $pluginPlan()['next_frame_wal_start'], 2],
    'plugin dependency current next69' => [static fn (): mixed => in_array('sqlite-pager-savepoint-current-next69', $pluginPlan()['dependencies'], true), true],
    'plugin dependency next savepoint' => [static fn (): mixed => in_array('sqlite-next-savepoint-after-rollback-to-current', $pluginPlan()['dependencies'], true), true],
    'plugin stack names after helper' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry_single_option', 7, str_repeat('R', 64), 64, true);
        return $stack->names();
    }, ['wp_import', 'plugin_batch', 'retry_single_option']],
    'plugin stack current frame pages cleared' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry_single_option', 7, str_repeat('R', 64), 64, true);
        return $stack->toArray()[1]['page_numbers'];
    }, []],
    'plugin stack next frame owns retry page' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry_single_option', 7, str_repeat('R', 64), 64, true);
        return $stack->toArray()[2]['page_numbers'];
    }, [7]],
    'plugin stack transaction keeps original pages' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry_single_option', 7, str_repeat('R', 64), 64, true);
        return $stack->toArray()[0]['page_numbers'];
    }, [1, 2]],
    'plugin stack wal current frame cleared' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry_single_option', 7, str_repeat('R', 64), 64, true);
        return $stack->walFrameState()[1]['wal_frame_indexes'];
    }, []],
    'plugin stack wal next frame owns replacement' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry_single_option', 7, str_repeat('R', 64), 64, true);
        return $stack->walFrameState()[2]['wal_frame_indexes'];
    }, [3]],
    'plugin stack release next merges retry page into current' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry_single_option', 7, str_repeat('R', 64), 64, true);
        return $stack->releaseWithPlan('retry_single_option')['merged_page_numbers'];
    }, [7]],
    'plugin stack release current merges retry page into transaction' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry_single_option', 7, str_repeat('R', 64), 64, true);
        $stack->release('retry_single_option');
        return $stack->releaseWithPlan('plugin_batch')['merged_page_numbers'];
    }, [7]],
    'plugin stack commit includes retry page' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry_single_option', 7, str_repeat('R', 64), 64, true);
        return $stack->commitWithPlan()['committed_page_numbers'];
    }, [1, 2, 7]],
    'plugin stack statement journals cleared' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry_single_option', 7, str_repeat('R', 64), 64, true);
        return $stack->statementJournalState();
    }, []],
    'plugin stack can add following wal frame' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry_single_option', 7);
        $stack->recordWalFrameWrite(4, 8);
        return $stack->pendingWalFrameIndexes();
    }, [1, 2, 3, 4]],
    'single plan found leaf index' => [static fn (): mixed => $singlePlan()['found_index'], 2],
    'single plan rollback frame' => [static fn (): mixed => $singlePlan()['rollback_to_frame'], 4],
    'single plan next frame' => [static fn (): mixed => $singlePlan()['next_wal_frame_index'], 5],
    'single plan discarded frames' => [static fn (): mixed => array_column($singlePlan()['discarded_wal_frames'], 'frame_index'), [5, 6]],
    'single plan discarded pages' => [static fn (): mixed => $singlePlan()['discarded_page_numbers'], [5, 6]],
    'single plan retained names' => [static fn (): mixed => $singlePlan()['retained_frame_names'], ['wp_import', 'plugin_batch', 'single_option']],
    'single plan names after next' => [static fn (): mixed => $singlePlan()['names_after_next'], ['wp_import', 'plugin_batch', 'single_option', 'retry_after_leaf']],
    'single plan pending pages' => [static fn (): mixed => $singlePlan()['pending_page_numbers_after_next'], [1, 2, 3, 4, 8]],
    'single plan pending wal' => [static fn (): mixed => $singlePlan()['pending_wal_frame_indexes_after_next'], [1, 2, 3, 4, 5]],
    'single plan next wal start' => [static fn (): mixed => $singlePlan()['next_frame_wal_start'], 4],
    'single plan keeps leaf savepoint open' => [static fn (): mixed => $singlePlan()['current_savepoint_active_after'], true],
    'single stack commit includes plugin prior pages and retry page' => [static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndOpenSavepoint('single_option', 'retry_after_leaf', 8);
        return $stack->commitWithPlan()['committed_page_numbers'];
    }, [1, 2, 3, 4, 8]],
    'open only next frame is null' => [static fn (): mixed => $openOnlyPlan()['next_wal_frame_index'], null],
    'open only next page is null' => [static fn (): mixed => $openOnlyPlan()['next_page_number'], null],
    'open only pending wal unchanged' => [static fn (): mixed => $openOnlyPlan()['pending_wal_frame_indexes_after_next'], [1, 2]],
    'open only pending pages unchanged' => [static fn (): mixed => $openOnlyPlan()['pending_page_numbers_after_next'], [1, 2]],
    'open only names after next' => [static fn (): mixed => $openOnlyPlan()['names_after_next'], ['wp_import', 'plugin_batch', 'retry_open_only']],
    'open only depth after next' => [static fn (): mixed => $openOnlyPlan()['next_depth_after'], 3],
    'case plan reports requested savepoint spelling' => [static fn (): mixed => $casePlan()['savepoint'], 'PLUGIN_BATCH'],
    'case plan finds original frame' => [static fn (): mixed => $casePlan()['found_index'], 1],
    'case plan names after next' => [static fn (): mixed => $casePlan()['names_after_next'], ['wp_import', 'plugin_batch', 'Retry_Case']],
    'case plan next frame after rollback prefix' => [static fn (): mixed => $casePlan()['next_wal_frame_index'], 3],
    'case plan pending pages include retry page' => [static fn (): mixed => $casePlan()['pending_page_numbers_after_next'], [1, 2, 9]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint current next69 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing savepoint rejected' => static fn () => $makeStack()->rollbackToCurrentAndOpenSavepoint('missing', 'retry', 7),
    'empty next savepoint rejected' => static fn () => $makeStack()->rollbackToCurrentAndOpenSavepoint('plugin_batch', '', 7),
    'zero page rejected' => static fn () => $makeStack()->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry', 0),
    'negative page rejected' => static fn () => $makeStack()->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry', -1),
    'empty page image rejected' => static fn () => $makeStack()->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry', 7, ''),
    'image without page rejected' => static fn () => $makeStack()->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry', null, str_repeat('R', 64)),
    'bad page size rejected' => static fn () => $makeStack()->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry', 7, str_repeat('R', 64), 0),
    'mismatched image size rejected' => static fn () => $makeStack()->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry', 7, str_repeat('R', 63), 64),
    'commit marker without page rejected' => static fn () => $makeStack()->rollbackToCurrentAndOpenSavepoint('plugin_batch', 'retry', null, null, null, true),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint current next69 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
