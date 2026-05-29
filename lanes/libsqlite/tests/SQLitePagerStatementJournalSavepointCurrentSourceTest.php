<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 128;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$clean = [
    1 => $page('clean sqlite header'),
    2 => $page('clean wp_options root'),
    3 => $page('clean active plugins'),
    4 => $page('clean transient before statement'),
    5 => $page('clean options index'),
];
$dirty = [
    1 => $page('current sqlite header'),
    2 => $page('current wp_options root'),
    3 => $page('current active plugins'),
    4 => $page('current failed transient statement'),
    5 => $page('current options index'),
];
$databaseBytes = implode('', $dirty);
$nextBeforeImage = $page('current retry statement before image');

$makeStack = static function () use ($clean): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $clean[1]);
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch');
    $stack->recordPageImageWrite(3, $clean[3]);
    $stack->recordWalFrameWrite(3, 3);
    $stack->beginStatementJournal('insert-transient');
    $stack->recordStatementPageImageWrite('insert-transient', 4, $clean[4]);
    $stack->recordStatementPageImageWrite('insert-transient', 5, $clean[5]);
    $stack->recordStatementWalFrameWrite('insert-transient', 4, 4);
    $stack->recordStatementWalFrameWrite('insert-transient', 5, 5, true);

    return $stack;
};

$plan = static fn (): array => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal(
    'insert-transient',
    'retry-transient',
    $databaseBytes,
    [4 => $dirty[4], 5 => $dirty[5]],
    6,
    $nextBeforeImage,
    $pageSize,
    true
);
$plainPlan = static fn (): array => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal(
    'insert-transient',
    'retry-autoload',
    $databaseBytes,
    [5 => $dirty[5], 4 => $dirty[4]],
    7,
    $page('current retry autoload before image'),
    $pageSize
);

$cases = [
    'current statement' => [static fn (): mixed => $plan()['current_statement'], 'insert-transient'],
    'next statement' => [static fn (): mixed => $plan()['next_statement'], 'retry-transient'],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'current source page numbers sorted' => [static fn (): mixed => $plan()['current_source_page_numbers'], [4, 5]],
    'current source prefix page four' => [static fn (): mixed => $plan()['current_source_prefixes'][4], 'current failed transient statement' . str_repeat('.', 14)],
    'current source prefix page five' => [static fn (): mixed => $plan()['current_source_prefixes'][5], 'current options index' . str_repeat('.', 27)],
    'next source prefix page four' => [static fn (): mixed => $plan()['next_source_prefixes'][4], 'clean transient before statement' . str_repeat('.', 16)],
    'next source prefix page five' => [static fn (): mixed => $plan()['next_source_prefixes'][5], 'clean options index' . str_repeat('.', 29)],
    'rollback frame' => [static fn (): mixed => $plan()['rollback_to_wal_frame'], 3],
    'next wal frame' => [static fn (): mixed => $plan()['next_wal_frame_index'], 4],
    'next page number' => [static fn (): mixed => $plan()['next_page_number'], 6],
    'next commit frame' => [static fn (): mixed => $plan()['next_commit_frame'], true],
    'restored page numbers' => [static fn (): mixed => $plan()['rollback_restored_page_numbers'], [4, 5]],
    'discarded wal indexes' => [static fn (): mixed => array_column($plan()['rollback_discarded_wal_frames'], 'frame_index'), [4, 5]],
    'discarded wal pages' => [static fn (): mixed => array_column($plan()['rollback_discarded_wal_frames'], 'page_number'), [4, 5]],
    'discarded wal commits' => [static fn (): mixed => array_column($plan()['rollback_discarded_wal_frames'], 'commit_frame'), [false, true]],
    'journals cleared after rollback' => [static fn (): mixed => $plan()['statement_journals_after_rollback'], []],
    'next journal count' => [static fn (): mixed => count($plan()['statement_journals_after_next']), 1],
    'next journal name' => [static fn (): mixed => $plan()['statement_journals_after_next'][0]['name'], 'retry-transient'],
    'next journal savepoint' => [static fn (): mixed => $plan()['statement_journals_after_next'][0]['savepoint'], 'plugin-batch'],
    'next journal wal start' => [static fn (): mixed => $plan()['statement_journals_after_next'][0]['wal_start_frame'], 3],
    'next journal page' => [static fn (): mixed => $plan()['statement_journals_after_next'][0]['page_numbers'], [6]],
    'next journal wal frame' => [static fn (): mixed => $plan()['statement_journals_after_next'][0]['wal_frame_indexes'], [4]],
    'pending pages after rollback' => [static fn (): mixed => $plan()['pending_page_numbers_after_rollback'], [1, 2, 3]],
    'pending wal after rollback' => [static fn (): mixed => $plan()['pending_wal_frame_indexes_after_rollback'], [1, 2, 3]],
    'pending pages after next' => [static fn (): mixed => $plan()['pending_page_numbers_after_next'], [1, 2, 3, 6]],
    'pending wal after next' => [static fn (): mixed => $plan()['pending_wal_frame_indexes_after_next'], [1, 2, 3, 4]],
    'rolled back page four clean' => [static fn (): mixed => substr($plan()['rolled_back_database_bytes'], 3 * $pageSize, 32), 'clean transient before statement'],
    'rolled back page five clean' => [static fn (): mixed => substr($plan()['rolled_back_database_bytes'], 4 * $pageSize, 19), 'clean options index'],
    'rolled back keeps current page two' => [static fn (): mixed => substr($plan()['rolled_back_database_bytes'], $pageSize, 23), 'current wp_options root'],
    'rolled back byte length unchanged' => [static fn (): mixed => strlen($plan()['rolled_back_database_bytes']), strlen($databaseBytes)],
    'savepoint active after' => [static fn (): mixed => $plan()['savepoint_active_after'], true],
    'transaction active after' => [static fn (): mixed => $plan()['transaction_active_after'], true],
    'dependency current source' => [static fn (): mixed => in_array('sqlite-pager-statement-journal-savepoint-current-source', $plan()['dependencies'], true), true],
    'dependency source guard' => [static fn (): mixed => in_array('sqlite-statement-journal-current-source-guard', $plan()['dependencies'], true), true],
    'dependency inherited rollback' => [static fn (): mixed => in_array('sqlite-statement-journal-rollback-retry', $plan()['dependencies'], true), true],
    'plain plan source pages sorted' => [static fn (): mixed => $plainPlan()['current_source_page_numbers'], [4, 5]],
    'plain plan next frame reused' => [static fn (): mixed => $plainPlan()['next_wal_frame_index'], 4],
    'plain plan next commit false' => [static fn (): mixed => $plainPlan()['next_commit_frame'], false],
    'plain plan next page' => [static fn (): mixed => $plainPlan()['next_page_number'], 7],
    'plain plan next journal page' => [static fn (): mixed => $plainPlan()['statement_journals_after_next'][0]['page_numbers'], [7]],
    'stack state after next' => [static function () use ($makeStack, $databaseBytes, $dirty, $nextBeforeImage, $pageSize): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry-transient', $databaseBytes, [4 => $dirty[4]], 6, $nextBeforeImage, $pageSize);
        return $stack->statementJournalState();
    }, [[
        'name' => 'retry-transient',
        'savepoint' => 'plugin-batch',
        'wal_start_frame' => 3,
        'page_numbers' => [6],
        'wal_frame_indexes' => [4],
    ]]],
    'stack can rollback retry statement' => [static function () use ($makeStack, $databaseBytes, $dirty, $nextBeforeImage, $pageSize): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry-transient', $databaseBytes, [4 => $dirty[4]], 6, $nextBeforeImage, $pageSize);
        return $stack->rollbackStatementOnErrorWithPlan('retry-transient', $pageSize)['restored_page_numbers'];
    }, [6]],
    'stack rollback retry clears journal' => [static function () use ($makeStack, $databaseBytes, $dirty, $nextBeforeImage, $pageSize): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry-transient', $databaseBytes, [4 => $dirty[4]], 6, $nextBeforeImage, $pageSize);
        $stack->rollbackStatementOnErrorWithPlan('retry-transient', $pageSize);
        return $stack->statementJournalState();
    }, []],
    'stack rollback retry restores pending pages' => [static function () use ($makeStack, $databaseBytes, $dirty, $nextBeforeImage, $pageSize): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry-transient', $databaseBytes, [4 => $dirty[4]], 6, $nextBeforeImage, $pageSize);
        $stack->rollbackStatementOnErrorWithPlan('retry-transient', $pageSize);
        return $stack->pendingPageNumbers();
    }, [1, 2, 3]],
    'stack rollback retry restores wal prefix' => [static function () use ($makeStack, $databaseBytes, $dirty, $nextBeforeImage, $pageSize): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry-transient', $databaseBytes, [4 => $dirty[4]], 6, $nextBeforeImage, $pageSize);
        $stack->rollbackStatementOnErrorWithPlan('retry-transient', $pageSize);
        return $stack->pendingWalFrameIndexes();
    }, [1, 2, 3]],
    'stack commit includes retry page' => [static function () use ($makeStack, $databaseBytes, $dirty, $nextBeforeImage, $pageSize): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry-transient', $databaseBytes, [4 => $dirty[4]], 6, $nextBeforeImage, $pageSize);
        return $stack->commitWithPlan()['committed_page_numbers'];
    }, [1, 2, 3, 6]],
    'stack release merges retry page' => [static function () use ($makeStack, $databaseBytes, $dirty, $nextBeforeImage, $pageSize): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry-transient', $databaseBytes, [4 => $dirty[4]], 6, $nextBeforeImage, $pageSize);
        return $stack->releaseWithPlan('plugin-batch')['merged_page_numbers'];
    }, [3, 6]],
    'same statement name reuse after current rollback' => [static function () use ($makeStack, $databaseBytes, $dirty, $nextBeforeImage, $pageSize): mixed {
        return $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'insert-transient', $databaseBytes, [4 => $dirty[4]], 6, $nextBeforeImage, $pageSize)['statement_journals_after_next'][0]['name'];
    }, 'insert-transient'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager statement journal savepoint current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'stale page rejected' => static fn () => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry', $databaseBytes, [4 => $clean[4]], 6, $nextBeforeImage, $pageSize),
    'outside page rejected' => static fn () => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry', $databaseBytes, [6 => $page('missing')], 7, $nextBeforeImage, $pageSize),
    'empty source pages rejected' => static fn () => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry', $databaseBytes, [], 6, $nextBeforeImage, $pageSize),
    'bad source page number rejected' => static fn () => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry', $databaseBytes, [0 => $dirty[4]], 6, $nextBeforeImage, $pageSize),
    'bad source image rejected' => static fn () => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry', $databaseBytes, [4 => 'short'], 6, $nextBeforeImage, $pageSize),
    'unaligned database rejected' => static fn () => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry', $databaseBytes . 'x', [4 => $dirty[4]], 6, $nextBeforeImage, $pageSize),
    'missing statement rejected' => static fn () => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('missing', 'retry', $databaseBytes, [4 => $dirty[4]], 6, $nextBeforeImage, $pageSize),
    'empty next statement rejected' => static fn () => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', '', $databaseBytes, [4 => $dirty[4]], 6, $nextBeforeImage, $pageSize),
    'zero next page rejected' => static fn () => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry', $databaseBytes, [4 => $dirty[4]], 0, $nextBeforeImage, $pageSize),
    'bad next image rejected' => static fn () => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry', $databaseBytes, [4 => $dirty[4]], 6, 'short', $pageSize),
    'zero page size rejected' => static fn () => $makeStack()->rollbackStatementCurrentSourceAndBeginStatementJournal('insert-transient', 'retry', $databaseBytes, [4 => $dirty[4]], 6, $nextBeforeImage, 0),
];

foreach ($throws as $name => $callback) {
    $tests['pager statement journal savepoint current source ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
