<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachDetachTransactionPlan;

$tests = [];

$schemas = static fn (): array => [
    'main' => [
        'file' => '/srv/wp/current.sqlite',
        'journal_mode' => 'wal',
    ],
    'analytics' => [
        'file' => '/srv/wp/analytics.sqlite',
        'journal_mode' => 'wal',
        'wal_frames' => 3,
        'lock' => 'shared',
    ],
    'archive' => [
        'file' => '/srv/wp/archive.sqlite',
        'journal_mode' => 'delete',
        'dirty_pages' => [4, 2, 4],
        'lock' => 'reserved',
    ],
    'staging' => [
        'file' => '/srv/wp/staging.sqlite',
        'journal_mode' => 'memory',
        'temp' => true,
    ],
];

$clean = static fn (): array => SQLiteAttachDetachTransactionPlan::currentNext($schemas(), 'analytics');
$dirty = static fn (): array => SQLiteAttachDetachTransactionPlan::currentNext($schemas(), 'archive');
$temp = static fn (): array => SQLiteAttachDetachTransactionPlan::currentNext($schemas(), 'staging');

$cases = [
    'clean detach status' => [static fn (): mixed => $clean()['status'], 'detached'],
    'clean detach is not blocked' => [static fn (): mixed => $clean()['blocked'], false],
    'clean operation marker' => [static fn (): mixed => $clean()['operation'], 'attach-detach-transaction-current'],
    'clean target normalized' => [static fn (): mixed => $clean()['target_schema'], 'analytics'],
    'clean detached schema' => [static fn (): mixed => $clean()['detached_schema'], 'analytics'],
    'clean current database count' => [static fn (): mixed => count($clean()['current_database_list']), 5],
    'clean next database count' => [static fn (): mixed => count($clean()['next_database_list']), 4],
    'clean current order keeps temp before attached' => [static fn (): mixed => array_column($clean()['current_database_list'], 'name'), ['main', 'temp', 'analytics', 'archive', 'staging']],
    'clean next order removes analytics' => [static fn (): mixed => array_column($clean()['next_database_list'], 'name'), ['main', 'temp', 'archive', 'staging']],
    'clean next archive renumbered' => [static fn (): mixed => $clean()['next_database_list'][2]['name'], 'archive'],
    'clean next staging renumbered' => [static fn (): mixed => $clean()['next_database_list'][3]['name'], 'staging'],
    'clean remaining attached excludes detached' => [static fn (): mixed => $clean()['remaining_attached'], ['archive', 'staging']],
    'clean sidecar cleanup removes wal' => [static fn (): mixed => $clean()['sidecar_cleanup'], ['analytics-wal', 'analytics-shm']],
    'clean first op checkpoints wal' => [static fn (): mixed => $clean()['operations'][0]['op'], 'checkpoint_before_detach'],
    'clean first op schema' => [static fn (): mixed => $clean()['operations'][0]['schema'], 'analytics'],
    'clean close btree op present' => [static fn (): mixed => $clean()['operations'][1]['op'], 'close_btree'],
    'clean renumber op present' => [static fn (): mixed => $clean()['operations'][2]['op'], 'renumber_database_array'],
    'clean dependency marker' => [static fn (): mixed => in_array('sqlite-attach-detach-transaction-current', $clean()['dependencies'], true), true],
    'clean database locked dependency marker' => [static fn (): mixed => in_array('sqlite-detach-database-locked-admission', $clean()['dependencies'], true), true],
    'clean array renumber dependency marker' => [static fn (): mixed => in_array('sqlite-attached-database-array-renumber', $clean()['dependencies'], true), true],
    'dirty detach blocks' => [static fn (): mixed => $dirty()['status'], 'blocked'],
    'dirty blocked flag' => [static fn (): mixed => $dirty()['blocked'], true],
    'dirty detached schema null' => [static fn (): mixed => $dirty()['detached_schema'], null],
    'dirty next database list unchanged' => [static fn (): mixed => array_column($dirty()['next_database_list'], 'name'), ['main', 'temp', 'analytics', 'archive', 'staging']],
    'dirty pages sorted unique' => [static fn (): mixed => $dirty()['current_database_list'][3]['dirty_pages'], [2, 4]],
    'dirty reasons include dirty pager pages' => [static fn (): mixed => in_array('dirty_pager_pages', $dirty()['blocked_reasons'], true), true],
    'dirty reasons include reserved lock' => [static fn (): mixed => in_array('reserved_or_exclusive_lock', $dirty()['blocked_reasons'], true), true],
    'dirty sqlite error names archive' => [static fn (): mixed => $dirty()['sqlite_error'], 'database archive is locked'],
    'dirty has no sidecar cleanup' => [static fn (): mixed => $dirty()['sidecar_cleanup'], []],
    'dirty has no operations' => [static fn (): mixed => $dirty()['operations'], []],
    'temp detached status' => [static fn (): mixed => $temp()['status'], 'detached'],
    'temp sidecar cleanup empty' => [static fn (): mixed => $temp()['sidecar_cleanup'], []],
    'temp discard transient pager op' => [static fn (): mixed => $temp()['operations'][0]['op'], 'discard_transient_pager'],
    'temp close btree follows discard' => [static fn (): mixed => $temp()['operations'][1]['op'], 'close_btree'],
    'temp next order removes staging' => [static fn (): mixed => array_column($temp()['next_database_list'], 'name'), ['main', 'temp', 'analytics', 'archive']],
    'quoted schema detaches analytics' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext($schemas(), '"Analytics"')['target_schema'], 'analytics'],
    'bracket schema detaches analytics' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext($schemas(), '[analytics]')['status'], 'detached'],
    'missing schema blocks' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext($schemas(), 'missing')['blocked_reasons'], ['schema_not_attached']],
    'missing schema keeps current list' => [static fn (): mixed => count(SQLiteAttachDetachTransactionPlan::currentNext($schemas(), 'missing')['next_database_list']), 5],
    'main detach is reserved' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext($schemas(), 'main')['blocked_reasons'], ['reserved_schema']],
    'temp detach is reserved' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext($schemas(), 'temp')['blocked_reasons'], ['reserved_schema']],
    'empty schema is missing name' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext($schemas(), '   ')['blocked_reasons'], ['missing_schema_name']],
    'active statement blocks detach' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['active_statements' => 1]], 'blog')['blocked_reasons'], ['active_statement']],
    'savepoint blocks detach' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['savepoint_depth' => 2]], 'blog')['blocked_reasons'], ['open_savepoint']],
    'wal reader blocks detach' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['wal_reader' => true, 'journal_mode' => 'wal']], 'blog')['blocked_reasons'], ['wal_reader_snapshot']],
    'exclusive lock blocks detach' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['lock' => 'exclusive']], 'blog')['blocked_reasons'], ['reserved_or_exclusive_lock']],
    'shared lock does not block clean detach' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['lock' => 'shared']], 'blog')['status'], 'detached'],
    'rollback journal detach has no sidecars' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['journal_mode' => 'delete']], 'blog')['sidecar_cleanup'], []],
    'rollback journal detach closes btree first' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['journal_mode' => 'delete']], 'blog')['operations'][0]['op'], 'close_btree'],
    'wal detach schedules checkpoint first' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['journal_mode' => 'wal']], 'blog')['operations'][0]['reason'], 'wal_database_detach'],
    'memory detach discards pager first' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['journal_mode' => 'memory']], 'blog')['operations'][0]['reason'], 'temporary_or_memory_database_detach'],
    'multiple blockers are all retained' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['active_statements' => 1, 'dirty_pages' => [9], 'savepoint_depth' => 1, 'wal_reader' => true, 'lock' => 'exclusive']], 'blog')['blocked_reasons'], ['active_statement', 'dirty_pager_pages', 'open_savepoint', 'wal_reader_snapshot', 'reserved_or_exclusive_lock']],
    'current database list exposes active statement count' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['active_statements' => 2]], 'blog')['current_database_list'][2]['active_statements'], 2],
    'current database list exposes savepoint depth' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['savepoint_depth' => 3]], 'blog')['current_database_list'][2]['savepoint_depth'], 3],
    'current database list exposes wal reader' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['wal_reader' => true]], 'blog')['current_database_list'][2]['wal_reader'], true],
    'uppercase journal mode normalizes to wal' => [static fn (): mixed => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['journal_mode' => 'WAL']], 'blog')['next_database_list'][0]['name'], 'main'],
    'default main and temp are supplied' => [static fn (): mixed => array_column(SQLiteAttachDetachTransactionPlan::currentNext(['blog' => []], 'blog')['current_database_list'], 'name'), ['main', 'temp', 'blog']],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['attach detach transaction current ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['attach detach transaction current invalid dirty page rejects'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['dirty_pages' => [0]]], 'blog'));
};

$tests['attach detach transaction current invalid active statement count rejects'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['active_statements' => -1]], 'blog'));
};

$tests['attach detach transaction current invalid savepoint depth rejects'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachDetachTransactionPlan::currentNext(['blog' => ['savepoint_depth' => -1]], 'blog'));
};

return $tests;
