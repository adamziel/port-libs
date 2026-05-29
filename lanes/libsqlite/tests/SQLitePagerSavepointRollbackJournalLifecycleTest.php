<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointCurrentNextPlan;

$tests = [];

$events = [
    ['op' => 'begin', 'name' => 'wp_import'],
    ['op' => 'page_write', 'page' => 2],
    ['op' => 'savepoint', 'name' => 'plugin_batch'],
    ['op' => 'page_image_write', 'page' => 4, 'image' => str_repeat('P', 64)],
    ['op' => 'page_write', 'page' => 5],
    ['op' => 'savepoint', 'name' => 'single_option'],
    ['op' => 'page_image_write', 'page' => 6, 'image' => str_repeat('R', 64)],
    ['op' => 'page_write', 'page' => 8],
];

$rollback = static fn (): array => SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle(
    $events,
    ['op' => 'rollback_to', 'name' => 'plugin_batch', 'journal_mode' => 'delete', 'page_size' => 1024, 'database_page_count' => 16]
);
$release = static fn (): array => SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle(
    $events,
    ['op' => 'release', 'name' => 'single_option', 'journal_mode' => 'persist', 'page_size' => 1024, 'database_page_count' => 16, 'super_journal_participant' => true]
);
$commit = static fn (): array => SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle(
    $events,
    ['op' => 'commit', 'journal_mode' => 'truncate', 'page_size' => 1024, 'database_page_count' => 16]
);
$rollbackAll = static fn (): array => SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle(
    $events,
    ['op' => 'rollback', 'journal_mode' => 'persist', 'page_size' => 1024, 'database_page_count' => 16, 'hot_journal' => true]
);
$implicitSavepoint = static fn (): array => SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle(
    [],
    ['op' => 'savepoint', 'name' => 'implicit_wp', 'journal_mode' => 'memory', 'page_size' => 512, 'database_page_count' => 1]
);

$cases = [
    'rollback status' => [static fn (): mixed => $rollback()['status'], 'rolled_back_to_savepoint'],
    'rollback mode' => [static fn (): mixed => $rollback()['journal_lifecycle']['mode'], 'delete'],
    'rollback page size' => [static fn (): mixed => $rollback()['journal_lifecycle']['page_size'], 1024],
    'rollback database pages' => [static fn (): mixed => $rollback()['journal_lifecycle']['database_page_count'], 16],
    'rollback journal bytes before' => [static fn (): mixed => $rollback()['journal_lifecycle']['journal_bytes_before'], 5188],
    'rollback journal bytes after' => [static fn (): mixed => $rollback()['journal_lifecycle']['journal_bytes_after'], 1060],
    'rollback statement journal pages' => [static fn (): mixed => $rollback()['journal_lifecycle']['statement_journal_pages'], [4, 5, 6, 8]],
    'rollback restore pages' => [static fn (): mixed => $rollback()['journal_lifecycle']['restore_page_numbers'], [4, 5, 6, 8]],
    'rollback merge pages empty' => [static fn (): mixed => $rollback()['journal_lifecycle']['merge_page_numbers'], []],
    'rollback keeps journal open' => [static fn (): mixed => $rollback()['journal_lifecycle']['final_disposition'], 'keep_open'],
    'rollback hot journal required' => [static fn (): mixed => $rollback()['journal_lifecycle']['hot_journal_required_before'], true],
    'rollback reserved lock remains' => [static fn (): mixed => $rollback()['journal_lifecycle']['requires_reserved_lock'], true],
    'rollback sync target' => [static fn (): mixed => $rollback()['journal_lifecycle']['sync_sequence'][0]['target'], 'statement-journal'],
    'rollback sync reason' => [static fn (): mixed => $rollback()['journal_lifecycle']['sync_sequence'][0]['reason'], 'savepoint_rollback_keeps_outer_transaction'],
    'rollback dependency lifecycle' => [static fn (): mixed => in_array('sqlite-pager-savepoint-rollback-journal-lifecycle', $rollback()['journal_lifecycle']['dependencies'], true), true],
    'rollback dependency journal lifecycle' => [static fn (): mixed => in_array('sqlite-rollback-journal-lifecycle', $rollback()['journal_lifecycle']['dependencies'], true), true],
    'rollback next names' => [static fn (): mixed => $rollback()['next']['names'], ['wp_import', 'plugin_batch']],
    'rollback next pages' => [static fn (): mixed => $rollback()['next']['pending_pages'], [2]],
    'release status' => [static fn (): mixed => $release()['status'], 'savepoint_released'],
    'release mode' => [static fn (): mixed => $release()['journal_lifecycle']['mode'], 'persist'],
    'release statement journal pages' => [static fn (): mixed => $release()['journal_lifecycle']['statement_journal_pages'], [6, 8]],
    'release merge pages' => [static fn (): mixed => $release()['journal_lifecycle']['merge_page_numbers'], [6, 8]],
    'release restore pages empty' => [static fn (): mixed => $release()['journal_lifecycle']['restore_page_numbers'], []],
    'release keeps open' => [static fn (): mixed => $release()['journal_lifecycle']['final_disposition'], 'keep_open'],
    'release super journal participant' => [static fn (): mixed => $release()['journal_lifecycle']['super_journal_participant'], true],
    'release reserved lock remains' => [static fn (): mixed => $release()['journal_lifecycle']['requires_reserved_lock'], true],
    'release sync target' => [static fn (): mixed => $release()['journal_lifecycle']['sync_sequence'][0]['target'], 'statement-journal'],
    'release sync reason' => [static fn (): mixed => $release()['journal_lifecycle']['sync_sequence'][0]['reason'], 'savepoint_release_keeps_outer_transaction'],
    'release next names' => [static fn (): mixed => $release()['next']['names'], ['wp_import', 'plugin_batch']],
    'release next pages' => [static fn (): mixed => $release()['next']['pending_pages'], [2, 4, 5, 6, 8]],
    'commit status' => [static fn (): mixed => $commit()['status'], 'transaction_committed'],
    'commit statement journal pages' => [static fn (): mixed => $commit()['journal_lifecycle']['statement_journal_pages'], [2, 4, 5, 6, 8]],
    'commit merge pages' => [static fn (): mixed => $commit()['journal_lifecycle']['merge_page_numbers'], [2, 4, 5, 6, 8]],
    'commit disposition truncate' => [static fn (): mixed => $commit()['journal_lifecycle']['final_disposition'], 'truncate'],
    'commit no reserved lock after' => [static fn (): mixed => $commit()['journal_lifecycle']['requires_reserved_lock'], false],
    'commit sync count' => [static fn (): mixed => count($commit()['journal_lifecycle']['sync_sequence']), 3],
    'commit first sync journal' => [static fn (): mixed => $commit()['journal_lifecycle']['sync_sequence'][0]['target'], 'rollback-journal'],
    'commit second sync database' => [static fn (): mixed => $commit()['journal_lifecycle']['sync_sequence'][1]['target'], 'database'],
    'commit third sync directory' => [static fn (): mixed => $commit()['journal_lifecycle']['sync_sequence'][2]['target'], 'directory'],
    'commit directory reason' => [static fn (): mixed => $commit()['journal_lifecycle']['sync_sequence'][2]['reason'], 'journal_truncate_durable'],
    'commit next inactive' => [static fn (): mixed => $commit()['next']['active'], false],
    'rollback all status' => [static fn (): mixed => $rollbackAll()['status'], 'transaction_rolled_back'],
    'rollback all statement pages' => [static fn (): mixed => $rollbackAll()['journal_lifecycle']['statement_journal_pages'], [2, 4, 5, 6, 8]],
    'rollback all restore pages' => [static fn (): mixed => $rollbackAll()['journal_lifecycle']['restore_page_numbers'], [2, 4, 5, 6, 8]],
    'rollback all disposition persist' => [static fn (): mixed => $rollbackAll()['journal_lifecycle']['final_disposition'], 'zero_header'],
    'rollback all sync count' => [static fn (): mixed => count($rollbackAll()['journal_lifecycle']['sync_sequence']), 2],
    'rollback all first sync reason' => [static fn (): mixed => $rollbackAll()['journal_lifecycle']['sync_sequence'][0]['reason'], 'rollback_before_database_restore'],
    'rollback all directory reason' => [static fn (): mixed => $rollbackAll()['journal_lifecycle']['sync_sequence'][1]['reason'], 'journal_zero_header_durable'],
    'rollback all hot journal explicit' => [static fn (): mixed => $rollbackAll()['journal_lifecycle']['hot_journal_required_before'], true],
    'rollback all next inactive' => [static fn (): mixed => $rollbackAll()['next']['active'], false],
    'implicit status' => [static fn (): mixed => $implicitSavepoint()['status'], 'savepoint_opened'],
    'implicit memory mode' => [static fn (): mixed => $implicitSavepoint()['journal_lifecycle']['mode'], 'memory'],
    'implicit bytes before' => [static fn (): mixed => $implicitSavepoint()['journal_lifecycle']['journal_bytes_before'], 0],
    'implicit bytes after' => [static fn (): mixed => $implicitSavepoint()['journal_lifecycle']['journal_bytes_after'], 0],
    'implicit final disposition keep open' => [static fn (): mixed => $implicitSavepoint()['journal_lifecycle']['final_disposition'], 'keep_open'],
    'implicit savepoint pages empty' => [static fn (): mixed => $implicitSavepoint()['journal_lifecycle']['statement_journal_pages'], []],
    'implicit reserved lock' => [static fn (): mixed => $implicitSavepoint()['journal_lifecycle']['requires_reserved_lock'], true],
    'implicit sync sequence' => [static fn (): mixed => $implicitSavepoint()['journal_lifecycle']['sync_sequence'][0]['target'], 'statement-journal'],
    'implicit transaction active' => [static fn (): mixed => $implicitSavepoint()['next']['active'], true],
    'implicit depth' => [static fn (): mixed => $implicitSavepoint()['next']['depth'], 1],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint rollback journal lifecycle ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty journal mode' => static fn () => SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle($events, ['op' => 'commit', 'journal_mode' => '']),
    'rejects unsupported journal mode' => static fn () => SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle($events, ['op' => 'commit', 'journal_mode' => 'wal']),
    'rejects bad page size' => static fn () => SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle($events, ['op' => 'commit', 'page_size' => 0]),
    'rejects bad database page count' => static fn () => SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle($events, ['op' => 'commit', 'database_page_count' => 0]),
    'rejects missing rollback savepoint' => static fn () => SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle($events, ['op' => 'rollback_to', 'name' => 'missing']),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint rollback journal lifecycle ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
