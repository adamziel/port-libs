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
    $stack->recordPageImageWrite(3, $page('before-active-plugins'));
    $stack->recordWalFrameWrite(3, 3);
    $stack->beginStatementJournal('insert-plugin-setting');
    $stack->recordStatementPageImageWrite('insert-plugin-setting', 4, $page('before-plugin-setting'));
    $stack->recordStatementWalFrameWrite('insert-plugin-setting', 4, 4);
    $stack->recordStatementWalFrameWrite('insert-plugin-setting', 5, 5, true);

    return $stack;
};

$retryPlan = static fn (): array => $makeStack()->rollbackStatementAndBeginStatementJournal(
    'insert-plugin-setting',
    'retry-plugin-setting',
    6,
    $page('before-retry-plugin-setting'),
    64,
    true
);
$plainPlan = static fn (): array => $makeStack()->rollbackStatementAndBeginStatementJournal(
    'insert-plugin-setting',
    'retry-autoload-flag',
    7,
    $page('before-autoload-flag'),
    64
);

$cases = [
    'retry plan current statement' => [static fn (): mixed => $retryPlan()['current_statement'], 'insert-plugin-setting'],
    'retry plan next statement' => [static fn (): mixed => $retryPlan()['next_statement'], 'retry-plugin-setting'],
    'retry plan savepoint' => [static fn (): mixed => $retryPlan()['savepoint'], 'plugin-batch'],
    'retry plan page size' => [static fn (): mixed => $retryPlan()['page_size'], 64],
    'retry plan rollback frame' => [static fn (): mixed => $retryPlan()['rollback_to_wal_frame'], 3],
    'retry plan next frame' => [static fn (): mixed => $retryPlan()['next_wal_frame_index'], 4],
    'retry plan next page' => [static fn (): mixed => $retryPlan()['next_page_number'], 6],
    'retry plan next commit frame' => [static fn (): mixed => $retryPlan()['next_commit_frame'], true],
    'retry plan restored statement pages' => [static fn (): mixed => $retryPlan()['rollback_restored_page_numbers'], [4]],
    'retry plan discarded wal frame indexes' => [static fn (): mixed => array_column($retryPlan()['rollback_discarded_wal_frames'], 'frame_index'), [4, 5]],
    'retry plan discarded wal frame pages' => [static fn (): mixed => array_column($retryPlan()['rollback_discarded_wal_frames'], 'page_number'), [4, 5]],
    'retry plan discarded wal commit flags' => [static fn (): mixed => array_column($retryPlan()['rollback_discarded_wal_frames'], 'commit_frame'), [false, true]],
    'retry plan statement journals cleared after rollback' => [static fn (): mixed => $retryPlan()['statement_journals_after_rollback'], []],
    'retry plan pending pages after rollback' => [static fn (): mixed => $retryPlan()['pending_page_numbers_after_rollback'], [1, 2, 3]],
    'retry plan pending wal after rollback' => [static fn (): mixed => $retryPlan()['pending_wal_frame_indexes_after_rollback'], [1, 2, 3]],
    'retry plan statement journal count after next' => [static fn (): mixed => count($retryPlan()['statement_journals_after_next']), 1],
    'retry plan next journal name' => [static fn (): mixed => $retryPlan()['statement_journals_after_next'][0]['name'], 'retry-plugin-setting'],
    'retry plan next journal savepoint' => [static fn (): mixed => $retryPlan()['statement_journals_after_next'][0]['savepoint'], 'plugin-batch'],
    'retry plan next journal wal start' => [static fn (): mixed => $retryPlan()['statement_journals_after_next'][0]['wal_start_frame'], 3],
    'retry plan next journal pages' => [static fn (): mixed => $retryPlan()['statement_journals_after_next'][0]['page_numbers'], [6]],
    'retry plan next journal wal frames' => [static fn (): mixed => $retryPlan()['statement_journals_after_next'][0]['wal_frame_indexes'], [4]],
    'retry plan pending pages after next' => [static fn (): mixed => $retryPlan()['pending_page_numbers_after_next'], [1, 2, 3, 6]],
    'retry plan pending wal after next' => [static fn (): mixed => $retryPlan()['pending_wal_frame_indexes_after_next'], [1, 2, 3, 4]],
    'retry plan savepoint active' => [static fn (): mixed => $retryPlan()['savepoint_active_after'], true],
    'retry plan transaction active' => [static fn (): mixed => $retryPlan()['transaction_active_after'], true],
    'retry plan dependency rollback' => [static fn (): mixed => in_array('sqlite-statement-journal-rollback-retry', $retryPlan()['dependencies'], true), true],
    'retry plan dependency subjournal' => [static fn (): mixed => in_array('sqlite-pager-statement-subjournal-next70', $retryPlan()['dependencies'], true), true],
    'plain plan next frame' => [static fn (): mixed => $plainPlan()['next_wal_frame_index'], 4],
    'plain plan next page' => [static fn (): mixed => $plainPlan()['next_page_number'], 7],
    'plain plan next commit false' => [static fn (): mixed => $plainPlan()['next_commit_frame'], false],
    'plain plan next journal name' => [static fn (): mixed => $plainPlan()['statement_journals_after_next'][0]['name'], 'retry-autoload-flag'],
    'plain plan next journal page' => [static fn (): mixed => $plainPlan()['statement_journals_after_next'][0]['page_numbers'], [7]],
    'plain plan pending pages after next' => [static fn (): mixed => $plainPlan()['pending_page_numbers_after_next'], [1, 2, 3, 7]],
    'plain plan pending wal after next' => [static fn (): mixed => $plainPlan()['pending_wal_frame_indexes_after_next'], [1, 2, 3, 4]],
    'stack names stay in savepoint after next statement' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry-plugin-setting', 6, $page('before-retry'), 64);
        return $stack->names();
    }, ['wp-import', 'plugin-batch']],
    'stack rolls back next statement to retained wal prefix' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry-plugin-setting', 6, $page('before-retry'), 64);
        return $stack->rollbackStatementOnErrorWithPlan('retry-plugin-setting', 64)['rollback_to_wal_frame'];
    }, 3],
    'stack next statement rollback restores retry page' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry-plugin-setting', 6, $page('before-retry'), 64);
        return $stack->rollbackStatementOnErrorWithPlan('retry-plugin-setting', 64)['restored_page_numbers'];
    }, [6]],
    'stack next statement rollback clears journal' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry-plugin-setting', 6, $page('before-retry'), 64);
        $stack->rollbackStatementOnErrorWithPlan('retry-plugin-setting', 64);
        return $stack->statementJournalState();
    }, []],
    'stack next statement rollback restores pending pages' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry-plugin-setting', 6, $page('before-retry'), 64);
        $stack->rollbackStatementOnErrorWithPlan('retry-plugin-setting', 64);
        return $stack->pendingPageNumbers();
    }, [1, 2, 3]],
    'stack next statement rollback restores pending wal frames' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry-plugin-setting', 6, $page('before-retry'), 64);
        $stack->rollbackStatementOnErrorWithPlan('retry-plugin-setting', 64);
        return $stack->pendingWalFrameIndexes();
    }, [1, 2, 3]],
    'stack can append after next statement rollback at reused frame' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry-plugin-setting', 6, $page('before-retry'), 64);
        $stack->rollbackStatementOnErrorWithPlan('retry-plugin-setting', 64);
        $stack->recordWalFrameWrite(4, 8);
        return $stack->pendingWalFrameIndexes();
    }, [1, 2, 3, 4]],
    'stack commit after successful retry includes retained and retry pages' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry-plugin-setting', 6, $page('before-retry'), 64);
        return $stack->commitWithPlan()['committed_page_numbers'];
    }, [1, 2, 3, 6]],
    'stack release after successful retry merges retained and retry pages' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry-plugin-setting', 6, $page('before-retry'), 64);
        return $stack->releaseWithPlan('plugin-batch')['merged_page_numbers'];
    }, [3, 6]],
    'stack rollback to savepoint after retry includes retry page' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry-plugin-setting', 6, $page('before-retry'), 64);
        return $stack->rollbackToPlan('plugin-batch')['rollback_page_numbers'];
    }, [3, 6]],
    'stack rollback to savepoint after retry discards next journal' => [static function () use ($makeStack, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry-plugin-setting', 6, $page('before-retry'), 64);
        $stack->rollbackTo('plugin-batch');
        return $stack->statementJournalState();
    }, []],
    'current statement missing rejected' => [static function () use ($makeStack, $page): mixed {
        try {
            $makeStack()->rollbackStatementAndBeginStatementJournal('missing', 'retry', 6, $page('before'), 64);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'empty next statement rejected' => [static function () use ($makeStack, $page): mixed {
        try {
            $makeStack()->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', '', 6, $page('before'), 64);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'same statement name can be reused after rollback clears journal' => [static function () use ($makeStack, $page): mixed {
        return $makeStack()
            ->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'insert-plugin-setting', 6, $page('before'), 64)
            ['statement_journals_after_next'][0]['name'];
    }, 'insert-plugin-setting'],
    'same statement name reuse starts at retained wal prefix' => [static function () use ($makeStack, $page): mixed {
        return $makeStack()
            ->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'insert-plugin-setting', 6, $page('before'), 64)
            ['statement_journals_after_next'][0]['wal_start_frame'];
    }, 3],
    'same statement name reuse keeps next page only' => [static function () use ($makeStack, $page): mixed {
        return $makeStack()
            ->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'insert-plugin-setting', 6, $page('before'), 64)
            ['statement_journals_after_next'][0]['page_numbers'];
    }, [6]],
    'zero next page rejected' => [static function () use ($makeStack, $page): mixed {
        try {
            $makeStack()->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry', 0, $page('before'), 64);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'empty next image rejected' => [static function () use ($makeStack): mixed {
        try {
            $makeStack()->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry', 6, '', 64);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'wrong next image size rejected' => [static function () use ($makeStack): mixed {
        try {
            $makeStack()->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry', 6, 'short', 64);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'zero page size rejected' => [static function () use ($makeStack, $page): mixed {
        try {
            $makeStack()->rollbackStatementAndBeginStatementJournal('insert-plugin-setting', 'retry', 6, $page('before'), 0);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager statement journal retry ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
