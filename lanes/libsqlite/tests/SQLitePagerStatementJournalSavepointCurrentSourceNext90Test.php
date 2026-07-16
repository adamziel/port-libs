<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 96;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$prefix = static fn (string $label): string => substr($page($label), 0, 48);

$databaseBytes = $page('current header')
    . $page('current wp_options root')
    . $page('current plugin option')
    . $page('current plugin index')
    . $page('current sibling option')
    . $page('current retry slot');

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $page('before header'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordPageImageWrite(2, $page('before root'));
    $stack->recordWalFrameWrite(2, 2, true);

    $stack->savepoint('plugin-batch');
    $stack->recordPageImageWrite(3, $page('before plugin option'));
    $stack->recordWalFrameWrite(3, 3);
    $stack->beginStatementJournal('plugin-update');
    $stack->recordStatementPageImageWrite('plugin-update', 3, $page('stmt before plugin option'));
    $stack->recordStatementWalFrameWrite('plugin-update', 4, 3, true);

    $stack->savepoint('single-option');
    $stack->recordPageImageWrite(4, $page('before plugin index'));
    $stack->recordWalFrameWrite(5, 4);
    $stack->beginStatementJournal('single-option-update');
    $stack->recordStatementPageImageWrite('single-option-update', 4, $page('stmt before plugin index'));
    $stack->recordStatementWalFrameWrite('single-option-update', 6, 4, true);

    return $stack;
};

$releaseSingle = static fn (): array => $makeStack()->releaseCurrentSourceAndBeginStatementJournal(
    'single-option',
    'retry-single-option',
    $databaseBytes,
    [4 => $page('current plugin index')],
    6,
    $page('before retry slot'),
    $pageSize,
    true
);
$releasePlugin = static fn (): array => $makeStack()->releaseCurrentSourceAndBeginStatementJournal(
    'plugin-batch',
    'next-plugin-batch-statement',
    $databaseBytes,
    [3 => $page('current plugin option'), 4 => $page('current plugin index')],
    5,
    $page('before sibling option'),
    $pageSize
);
$caseRelease = static fn (): array => $makeStack()->releaseCurrentSourceAndBeginStatementJournal(
    'SINGLE-OPTION',
    'case-retry',
    $databaseBytes,
    [4 => $page('current plugin index')],
    6,
    $page('before retry slot'),
    $pageSize
);

$cases = [
    'single release savepoint spelling' => [static fn (): mixed => $releaseSingle()['released_savepoint'], 'single-option'],
    'single next statement' => [static fn (): mixed => $releaseSingle()['next_statement'], 'retry-single-option'],
    'single source verified' => [static fn (): mixed => $releaseSingle()['current_source_verified'], true],
    'single source pages' => [static fn (): mixed => $releaseSingle()['current_source_page_numbers'], [4]],
    'single current prefix' => [static fn (): mixed => $releaseSingle()['current_source_prefixes'][4], $prefix('current plugin index')],
    'single next prefix unchanged after release' => [static fn (): mixed => $releaseSingle()['next_source_prefixes'][4], $prefix('current plugin index')],
    'single release found index' => [static fn (): mixed => $releaseSingle()['release_plan']['found_index'], 2],
    'single release frame names' => [static fn (): mixed => $releaseSingle()['release_plan']['released_frame_names'], ['single-option']],
    'single release merged pages' => [static fn (): mixed => $releaseSingle()['release_plan']['merged_page_numbers'], [4]],
    'single release keeps transaction' => [static fn (): mixed => $releaseSingle()['release_plan']['transaction_active_after'], true],
    'single discarded statement journals' => [static fn (): mixed => $releaseSingle()['discarded_statement_journals'], ['single-option-update']],
    'single before release journal count' => [static fn (): mixed => count($releaseSingle()['statement_journals_before_release']), 2],
    'single before release outer journal' => [static fn (): mixed => $releaseSingle()['statement_journals_before_release'][0]['name'], 'plugin-update'],
    'single before release inner journal' => [static fn (): mixed => $releaseSingle()['statement_journals_before_release'][1]['name'], 'single-option-update'],
    'single after release keeps outer journal' => [static fn (): mixed => $releaseSingle()['statement_journals_after_release'][0]['name'], 'plugin-update'],
    'single after release journal count' => [static fn (): mixed => count($releaseSingle()['statement_journals_after_release']), 1],
    'single after next journal count' => [static fn (): mixed => count($releaseSingle()['statement_journals_after_next']), 2],
    'single next journal name' => [static fn (): mixed => $releaseSingle()['statement_journals_after_next'][1]['name'], 'retry-single-option'],
    'single next journal savepoint retargeted to parent' => [static fn (): mixed => $releaseSingle()['statement_journals_after_next'][1]['savepoint'], 'plugin-batch'],
    'single next journal wal start' => [static fn (): mixed => $releaseSingle()['statement_journals_after_next'][1]['wal_start_frame'], 6],
    'single next journal page' => [static fn (): mixed => $releaseSingle()['statement_journals_after_next'][1]['page_numbers'], [6]],
    'single next journal wal frame' => [static fn (): mixed => $releaseSingle()['statement_journals_after_next'][1]['wal_frame_indexes'], [7]],
    'single names after release' => [static fn (): mixed => $releaseSingle()['names_after_release'], ['wp-import', 'plugin-batch']],
    'single pending pages after release' => [static fn (): mixed => $releaseSingle()['pending_page_numbers_after_release'], [1, 2, 3, 4]],
    'single pending wal after release' => [static fn (): mixed => $releaseSingle()['pending_wal_frame_indexes_after_release'], [1, 2, 3, 4, 5, 6]],
    'single pending pages after next' => [static fn (): mixed => $releaseSingle()['pending_page_numbers_after_next'], [1, 2, 3, 4, 6]],
    'single pending wal after next' => [static fn (): mixed => $releaseSingle()['pending_wal_frame_indexes_after_next'], [1, 2, 3, 4, 5, 6, 7]],
    'single next wal start' => [static fn (): mixed => $releaseSingle()['next_wal_start_frame'], 6],
    'single next wal frame' => [static fn (): mixed => $releaseSingle()['next_wal_frame_index'], 7],
    'single next page' => [static fn (): mixed => $releaseSingle()['next_page_number'], 6],
    'single commit frame' => [static fn (): mixed => $releaseSingle()['next_commit_frame'], true],
    'single savepoint closed' => [static fn (): mixed => $releaseSingle()['released_savepoint_active_after'], false],
    'single transaction active' => [static fn (): mixed => $releaseSingle()['transaction_active_after'], true],
    'single dependency next90' => [static fn (): mixed => in_array('sqlite-pager-statement-journal-savepoint-current-source-next90', $releaseSingle()['dependencies'], true), true],
    'single dependency release merge' => [static fn (): mixed => in_array('sqlite-savepoint-release-merges-current-pager-state', $releaseSingle()['dependencies'], true), true],
    'single dependency next journal' => [static fn (): mixed => in_array('sqlite-statement-journal-next-after-release', $releaseSingle()['dependencies'], true), true],
    'single stack names after method' => [static function () use ($makeStack, $databaseBytes, $page, $pageSize): mixed {
        $stack = $makeStack();
        $stack->releaseCurrentSourceAndBeginStatementJournal('single-option', 'retry-single-option', $databaseBytes, [4 => $page('current plugin index')], 6, $page('before retry slot'), $pageSize);
        return $stack->names();
    }, ['wp-import', 'plugin-batch']],
    'single stack statement rollback restores retry' => [static function () use ($makeStack, $databaseBytes, $page, $pageSize): mixed {
        $stack = $makeStack();
        $stack->releaseCurrentSourceAndBeginStatementJournal('single-option', 'retry-single-option', $databaseBytes, [4 => $page('current plugin index')], 6, $page('before retry slot'), $pageSize);
        return $stack->rollbackStatementOnErrorWithPlan('retry-single-option', $pageSize)['restored_page_numbers'];
    }, [6]],
    'single stack statement rollback returns wal prefix' => [static function () use ($makeStack, $databaseBytes, $page, $pageSize): mixed {
        $stack = $makeStack();
        $stack->releaseCurrentSourceAndBeginStatementJournal('single-option', 'retry-single-option', $databaseBytes, [4 => $page('current plugin index')], 6, $page('before retry slot'), $pageSize);
        $stack->rollbackStatementOnErrorWithPlan('retry-single-option', $pageSize);
        return $stack->pendingWalFrameIndexes();
    }, [1, 2, 3, 4, 5, 6]],
    'plugin release discards both statement journals' => [static fn (): mixed => $releasePlugin()['discarded_statement_journals'], ['plugin-update', 'single-option-update']],
    'plugin release closes nested names' => [static fn (): mixed => $releasePlugin()['names_after_release'], ['wp-import']],
    'plugin release next journal savepoint is transaction' => [static fn (): mixed => $releasePlugin()['statement_journals_after_next'][0]['savepoint'], 'wp-import'],
    'plugin release after-release journals empty' => [static fn (): mixed => $releasePlugin()['statement_journals_after_release'], []],
    'plugin release pending pages after release' => [static fn (): mixed => $releasePlugin()['pending_page_numbers_after_release'], [1, 2, 3, 4]],
    'plugin release pending wal after release' => [static fn (): mixed => $releasePlugin()['pending_wal_frame_indexes_after_release'], [1, 2, 3, 4, 5, 6]],
    'plugin release source pages sorted' => [static fn (): mixed => $releasePlugin()['current_source_page_numbers'], [3, 4]],
    'plugin release next wal frame' => [static fn (): mixed => $releasePlugin()['next_wal_frame_index'], 7],
    'plugin release commit frame false' => [static fn (): mixed => $releasePlugin()['next_commit_frame'], false],
    'case release requested spelling' => [static fn (): mixed => $caseRelease()['released_savepoint'], 'SINGLE-OPTION'],
    'case release stored frame name' => [static fn (): mixed => $caseRelease()['release_plan']['released_frame_names'], ['single-option']],
    'case release next savepoint parent' => [static fn (): mixed => $caseRelease()['statement_journals_after_next'][1]['savepoint'], 'plugin-batch'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager statement journal savepoint current source next90 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing savepoint rejected' => static fn () => $makeStack()->releaseCurrentSourceAndBeginStatementJournal('missing', 'retry', $databaseBytes, [4 => $page('current plugin index')], 6, $page('before retry slot'), $pageSize),
    'empty next statement rejected' => static fn () => $makeStack()->releaseCurrentSourceAndBeginStatementJournal('single-option', '', $databaseBytes, [4 => $page('current plugin index')], 6, $page('before retry slot'), $pageSize),
    'unaligned source rejected' => static fn () => $makeStack()->releaseCurrentSourceAndBeginStatementJournal('single-option', 'retry', 'not aligned', [4 => $page('current plugin index')], 6, $page('before retry slot'), $pageSize),
    'empty current pages rejected' => static fn () => $makeStack()->releaseCurrentSourceAndBeginStatementJournal('single-option', 'retry', $databaseBytes, [], 6, $page('before retry slot'), $pageSize),
    'zero source page rejected' => static fn () => $makeStack()->releaseCurrentSourceAndBeginStatementJournal('single-option', 'retry', $databaseBytes, [0 => $page('current plugin index')], 6, $page('before retry slot'), $pageSize),
    'short source image rejected' => static fn () => $makeStack()->releaseCurrentSourceAndBeginStatementJournal('single-option', 'retry', $databaseBytes, [4 => 'short'], 6, $page('before retry slot'), $pageSize),
    'stale current source rejected' => static fn () => $makeStack()->releaseCurrentSourceAndBeginStatementJournal('single-option', 'retry', $databaseBytes, [4 => $page('stale plugin index')], 6, $page('before retry slot'), $pageSize),
    'outside source page rejected' => static fn () => $makeStack()->releaseCurrentSourceAndBeginStatementJournal('single-option', 'retry', $databaseBytes, [9 => $page('outside')], 6, $page('before retry slot'), $pageSize),
    'bad next page rejected' => static fn () => $makeStack()->releaseCurrentSourceAndBeginStatementJournal('single-option', 'retry', $databaseBytes, [4 => $page('current plugin index')], 0, $page('before retry slot'), $pageSize),
    'short next image rejected' => static fn () => $makeStack()->releaseCurrentSourceAndBeginStatementJournal('single-option', 'retry', $databaseBytes, [4 => $page('current plugin index')], 6, 'short', $pageSize),
];

foreach ($throws as $name => $callback) {
    $tests['pager statement journal savepoint current source next90 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
