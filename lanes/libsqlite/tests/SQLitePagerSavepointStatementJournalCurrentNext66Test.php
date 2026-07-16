<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$page = static fn (string $label): string => str_pad($label, 64, '.');

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $page('before-db-header'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);

    $stack->savepoint('plugin-batch');
    $stack->beginStatementJournal('insert-active-plugin');
    $stack->recordStatementPageImageWrite('insert-active-plugin', 3, $page('before-active-plugins'));
    $stack->recordStatementWalFrameWrite('insert-active-plugin', 3, 3);

    $stack->savepoint('single-option');
    $stack->beginStatementJournal('insert-plugin-setting');
    $stack->recordStatementPageImageWrite('insert-plugin-setting', 4, $page('before-plugin-setting'));
    $stack->recordStatementWalFrameWrite('insert-plugin-setting', 4, 4);
    $stack->recordStatementWalFrameWrite('insert-plugin-setting', 5, 5, true);

    return $stack;
};

$pluginPlan = static fn (): array => $makeStack()->rollbackToCurrentAndBeginStatementJournal(
    'plugin-batch',
    'retry-plugin-setting',
    6,
    $page('before-retry-setting'),
    64,
    true
);
$casePlan = static fn (): array => $makeStack()->rollbackToCurrentAndBeginStatementJournal(
    'PLUGIN-BATCH',
    'retry-upper',
    7,
    $page('before-upper'),
    64
);
$leafPlan = static fn (): array => $makeStack()->rollbackToCurrentAndBeginStatementJournal(
    'single-option',
    'retry-leaf',
    8,
    $page('before-leaf'),
    64
);

$cases = [
    'plugin plan savepoint' => [static fn (): mixed => $pluginPlan()['savepoint'], 'plugin-batch'],
    'plugin plan statement' => [static fn (): mixed => $pluginPlan()['statement'], 'retry-plugin-setting'],
    'plugin plan found index' => [static fn (): mixed => $pluginPlan()['found_index'], 1],
    'plugin plan rollback frame' => [static fn (): mixed => $pluginPlan()['rollback_to_frame'], 2],
    'plugin plan next frame' => [static fn (): mixed => $pluginPlan()['next_wal_frame_index'], 3],
    'plugin plan next page' => [static fn (): mixed => $pluginPlan()['next_page_number'], 6],
    'plugin plan next commit frame' => [static fn (): mixed => $pluginPlan()['next_commit_frame'], true],
    'plugin plan discarded statement journals' => [static fn (): mixed => $pluginPlan()['discarded_statement_journals'], ['insert-active-plugin', 'insert-plugin-setting']],
    'plugin plan discarded wal frame indexes' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'frame_index'), [3, 4, 5]],
    'plugin plan discarded wal pages' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'page_number'), [3, 4, 5]],
    'plugin plan discarded wal commits' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'commit_frame'), [false, false, true]],
    'plugin plan discarded wal names' => [static fn (): mixed => array_column($pluginPlan()['discarded_wal_frames'], 'frame_name'), ['plugin-batch', 'single-option', 'single-option']],
    'plugin plan discarded pages' => [static fn (): mixed => $pluginPlan()['discarded_page_numbers'], [3, 4, 5]],
    'plugin plan retained frames' => [static fn (): mixed => $pluginPlan()['retained_frame_names'], ['wp-import', 'plugin-batch']],
    'plugin plan no statement journals after rollback' => [static fn (): mixed => $pluginPlan()['statement_journals_after_rollback'], []],
    'plugin plan next statement journal name' => [static fn (): mixed => $pluginPlan()['statement_journals_after_next'][0]['name'], 'retry-plugin-setting'],
    'plugin plan next statement savepoint' => [static fn (): mixed => $pluginPlan()['statement_journals_after_next'][0]['savepoint'], 'plugin-batch'],
    'plugin plan next statement wal start' => [static fn (): mixed => $pluginPlan()['statement_journals_after_next'][0]['wal_start_frame'], 2],
    'plugin plan next statement page' => [static fn (): mixed => $pluginPlan()['statement_journals_after_next'][0]['page_numbers'], [6]],
    'plugin plan next statement wal frame' => [static fn (): mixed => $pluginPlan()['statement_journals_after_next'][0]['wal_frame_indexes'], [3]],
    'plugin plan pending pages after next' => [static fn (): mixed => $pluginPlan()['pending_page_numbers_after_next'], [1, 2, 6]],
    'plugin plan pending wal after next' => [static fn (): mixed => $pluginPlan()['pending_wal_frame_indexes_after_next'], [1, 2, 3]],
    'plugin plan rollback statement pages' => [static fn (): mixed => $pluginPlan()['rollback_statement_restored_pages'], [6]],
    'plugin plan rollback statement frame' => [static fn (): mixed => $pluginPlan()['rollback_statement_to_frame'], 2],
    'plugin plan savepoint remains active' => [static fn (): mixed => $pluginPlan()['current_savepoint_active_after'], true],
    'plugin plan transaction remains active' => [static fn (): mixed => $pluginPlan()['transaction_active_after'], true],
    'plugin plan dependency statement journal' => [static fn (): mixed => in_array('sqlite-statement-journal-current-next66', $pluginPlan()['dependencies'], true), true],
    'plugin plan dependency pager subjournal' => [static fn (): mixed => in_array('sqlite-pager-savepoint-subjournal-current-next66', $pluginPlan()['dependencies'], true), true],
    'plugin stack names after next statement' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry-plugin-setting', 6, $page('before-retry-setting'), 64);
        return $stack->names();
    }, ['wp-import', 'plugin-batch']],
    'plugin stack statement state after next' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry-plugin-setting', 6, $page('before-retry-setting'), 64);
        return $stack->statementJournalState();
    }, [[
        'name' => 'retry-plugin-setting',
        'savepoint' => 'plugin-batch',
        'wal_start_frame' => 2,
        'page_numbers' => [6],
        'wal_frame_indexes' => [3],
    ]]],
    'plugin stack statement rollback restores retry page' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry-plugin-setting', 6, $page('before-retry-setting'), 64);
        return $stack->rollbackStatementOnErrorWithPlan('retry-plugin-setting', 64)['restored_page_numbers'];
    }, [6]],
    'plugin stack statement rollback clears retry journal' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry-plugin-setting', 6, $page('before-retry-setting'), 64);
        $stack->rollbackStatementOnErrorWithPlan('retry-plugin-setting', 64);
        return $stack->statementJournalState();
    }, []],
    'plugin stack statement rollback returns pending pages to outer prefix' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry-plugin-setting', 6, $page('before-retry-setting'), 64);
        $stack->rollbackStatementOnErrorWithPlan('retry-plugin-setting', 64);
        return $stack->pendingPageNumbers();
    }, [1, 2]],
    'plugin stack statement rollback returns wal to outer prefix' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry-plugin-setting', 6, $page('before-retry-setting'), 64);
        $stack->rollbackStatementOnErrorWithPlan('retry-plugin-setting', 64);
        return $stack->pendingWalFrameIndexes();
    }, [1, 2]],
    'plugin stack can append after statement rollback at reused frame' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry-plugin-setting', 6, $page('before-retry-setting'), 64);
        $stack->rollbackStatementOnErrorWithPlan('retry-plugin-setting', 64);
        $stack->recordWalFrameWrite(3, 9);
        return $stack->pendingWalFrameIndexes();
    }, [1, 2, 3]],
    'plugin stack release after successful retry merges page' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry-plugin-setting', 6, $page('before-retry-setting'), 64);
        return $stack->releaseWithPlan('plugin-batch')['merged_page_numbers'];
    }, [6]],
    'plugin stack commit after successful retry includes retry page' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry-plugin-setting', 6, $page('before-retry-setting'), 64);
        return $stack->commitWithPlan()['committed_page_numbers'];
    }, [1, 2, 6]],
    'case plan keeps requested spelling' => [static fn (): mixed => $casePlan()['savepoint'], 'PLUGIN-BATCH'],
    'case plan finds savepoint' => [static fn (): mixed => $casePlan()['found_index'], 1],
    'case plan statement savepoint uses stored spelling' => [static fn (): mixed => $casePlan()['statement_journals_after_next'][0]['savepoint'], 'plugin-batch'],
    'case plan next frame' => [static fn (): mixed => $casePlan()['next_wal_frame_index'], 3],
    'case plan next page' => [static fn (): mixed => $casePlan()['next_page_number'], 7],
    'case plan next commit false' => [static fn (): mixed => $casePlan()['next_commit_frame'], false],
    'leaf plan found index' => [static fn (): mixed => $leafPlan()['found_index'], 2],
    'leaf plan rollback frame' => [static fn (): mixed => $leafPlan()['rollback_to_frame'], 3],
    'leaf plan next frame' => [static fn (): mixed => $leafPlan()['next_wal_frame_index'], 4],
    'leaf plan discarded statement journal' => [static fn (): mixed => $leafPlan()['discarded_statement_journals'], ['insert-plugin-setting']],
    'leaf plan discarded wal frames' => [static fn (): mixed => array_column($leafPlan()['discarded_wal_frames'], 'frame_index'), [4, 5]],
    'leaf plan retained frames' => [static fn (): mixed => $leafPlan()['retained_frame_names'], ['wp-import', 'plugin-batch', 'single-option']],
    'leaf plan keeps outer statement journal' => [static fn (): mixed => $leafPlan()['statement_journals_after_rollback'][0]['name'], 'insert-active-plugin'],
    'leaf plan next statement journal count' => [static fn (): mixed => count($leafPlan()['statement_journals_after_next']), 2],
    'leaf plan next statement savepoint' => [static fn (): mixed => $leafPlan()['statement_journals_after_next'][1]['savepoint'], 'single-option'],
    'leaf plan pending pages after next' => [static fn (): mixed => $leafPlan()['pending_page_numbers_after_next'], [1, 2, 3, 8]],
    'leaf plan pending wal after next' => [static fn (): mixed => $leafPlan()['pending_wal_frame_indexes_after_next'], [1, 2, 3, 4]],
    'missing savepoint rejected' => [static function () use ($makeStack, $page): mixed {
        try {
            $makeStack()->rollbackToCurrentAndBeginStatementJournal('missing', 'retry', 6, $page('before'), 64);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'empty statement rejected' => [static function () use ($makeStack, $page): mixed {
        try {
            $makeStack()->rollbackToCurrentAndBeginStatementJournal('plugin-batch', '', 6, $page('before'), 64);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'zero page rejected' => [static function () use ($makeStack, $page): mixed {
        try {
            $makeStack()->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry', 0, $page('before'), 64);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'empty image rejected' => [static function () use ($makeStack): mixed {
        try {
            $makeStack()->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry', 6, '', 64);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'wrong image size rejected' => [static function () use ($makeStack): mixed {
        try {
            $makeStack()->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry', 6, 'short', 64);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'zero page size rejected' => [static function () use ($makeStack, $page): mixed {
        try {
            $makeStack()->rollbackToCurrentAndBeginStatementJournal('plugin-batch', 'retry', 6, $page('before'), 0);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint statement journal current next66 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
