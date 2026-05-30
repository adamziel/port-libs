<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteImportTransactionPlan;

$currentRows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
    ['option_id' => 130, 'option_name' => 'transient_feed', 'option_value' => 'stale', 'autoload' => 'no'],
];

$stagedRows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://new.example', 'autoload' => 'yes'],
    ['option_name' => 'blogname', 'option_value' => 'Ported Site', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:19:"plugin/plugin.php";}', 'autoload' => 'no'],
];

$plan = static fn (array $options = []): array => SQLiteImportTransactionPlan::plan(
    $currentRows(),
    $stagedRows(),
    array_replace(['database_path' => '/tmp/wp-current-import.sqlite', 'page_size' => 1024], $options)
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        if (ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'begin mode is immediate' => static fn (): mixed => $plan()['begin']['mode'],
    'begin write lock is acquired' => static fn (): mixed => $plan()['begin']['write_lock_acquired'],
    'database path is preserved' => static fn (): mixed => $plan()['database_path'],
    'page size is preserved' => static fn (): mixed => $plan()['page_size'],
    'default journal mode is delete' => static fn (): mixed => $plan()['journal_mode'],
    'default sync mode is full' => static fn (): mixed => $plan()['sync_mode'],
    'delete missing defaults false' => static fn (): mixed => $plan()['delete_missing'],
    'replace conflicts defaults false' => static fn (): mixed => $plan()['replace_conflicts'],
    'two staged current rows update' => static fn (): mixed => count($plan()['updated']),
    'siteurl before value captured' => static fn (): mixed => $plan()['updated'][0]['before']['option_value'],
    'siteurl after value captured' => static fn (): mixed => $plan()['updated'][0]['after']['option_value'],
    'active plugins update keeps rowid' => static fn (): mixed => $plan()['updated'][1]['after']['option_id'],
    'active plugins update changes value' => static fn (): mixed => $plan()['updated'][1]['after']['option_value'],
    'one staged row inserts' => static fn (): mixed => count($plan()['inserted']),
    'new row receives next rowid' => static fn (): mixed => $plan()['inserted'][0]['option_id'],
    'new row name is preserved' => static fn (): mixed => $plan()['inserted'][0]['option_name'],
    'new row autoload is preserved' => static fn (): mixed => $plan()['inserted'][0]['autoload'],
    'no row deleted without delete-missing' => static fn (): mixed => count($plan()['deleted']),
    'final row count includes retained current row' => static fn (): mixed => count($plan()['final_rows']),
    'final rows remain rowid sorted' => static fn (): mixed => array_column($plan()['final_rows'], 'option_id'),
    'dirty pages include first leaf' => static fn (): mixed => $plan()['dirty_pages'][0],
    'dirty pages include second leaf' => static fn (): mixed => $plan()['dirty_pages'][1],
    'dirty pages include third leaf' => static fn (): mixed => $plan()['dirty_pages'][2],
    'journal bytes includes dirty page records' => static fn (): mixed => $plan()['journal_bytes'],
    'update statement is first' => static fn (): mixed => $plan()['statements'][0]['op'],
    'update statement counts two rows' => static fn (): mixed => $plan()['statements'][0]['rows'],
    'insert statement is second' => static fn (): mixed => $plan()['statements'][1]['op'],
    'insert statement counts one row' => static fn (): mixed => $plan()['statements'][1]['rows'],
    'sync sequence has journal database directory' => static fn (): mixed => array_column($plan()['sync_sequence'], 'target'),
    'journal sync is full' => static fn (): mixed => $plan()['sync_sequence'][0]['flag_names'],
    'database sync is dataonly full' => static fn (): mixed => $plan()['sync_sequence'][1]['flag_names'],
    'directory sync is normal' => static fn (): mixed => $plan()['sync_sequence'][2]['flag_names'],
    'dependency names current import' => static fn (): mixed => in_array('sqlite-application-import-transaction-current', $plan()['dependencies'], true),
    'delete missing removes absent options' => static fn (): mixed => count($plan(['delete_missing' => true])['deleted']),
    'delete missing includes home' => static fn (): mixed => $plan(['delete_missing' => true])['deleted'][0]['option_name'],
    'delete missing includes transient' => static fn (): mixed => $plan(['delete_missing' => true])['deleted'][1]['option_name'],
    'delete statement appears when deleting missing' => static fn (): mixed => $plan(['delete_missing' => true])['statements'][2]['op'],
    'truncate journal mode is preserved' => static fn (): mixed => $plan(['journal_mode' => 'truncate'])['journal_mode'],
    'normal sync lowers sync flags' => static fn (): mixed => $plan(['sync_mode' => 'normal'])['sync_sequence'][0]['flag_names'],
    'persist journal adds header sync target' => static fn (): mixed => array_column($plan(['journal_mode' => 'persist'])['sync_sequence'], 'target'),
    'exclusive begin is accepted' => static fn (): mixed => $plan(['begin' => 'BEGIN EXCLUSIVE TRANSACTION'])['begin']['mode'],
    'conflicting update aborts by default' => static function () use ($currentRows): mixed {
        try {
            SQLiteImportTransactionPlan::plan($currentRows(), [
                ['option_id' => 1, 'option_name' => 'home', 'option_value' => 'dup', 'autoload' => 'yes'],
            ]);
        } catch (LogicException) {
            return 'aborted';
        }
        return 'missed';
    },
    'replace conflict deletes current name owner' => static function () use ($currentRows): mixed {
        return SQLiteImportTransactionPlan::plan($currentRows(), [
            ['option_id' => 1, 'option_name' => 'home', 'option_value' => 'dup', 'autoload' => 'yes'],
        ], ['replace_conflicts' => true])['deleted'][0]['option_id'];
    },
    'replace conflict records action' => static function () use ($currentRows): mixed {
        return SQLiteImportTransactionPlan::plan($currentRows(), [
            ['option_id' => 1, 'option_name' => 'home', 'option_value' => 'dup', 'autoload' => 'yes'],
        ], ['replace_conflicts' => true])['conflicts'][0]['action'];
    },
    'staged duplicate id uses last source row' => static function () use ($currentRows): mixed {
        return SQLiteImportTransactionPlan::plan($currentRows(), [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'first', 'autoload' => 'yes'],
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'last', 'autoload' => 'yes'],
        ])['updated'][0]['after']['option_value'];
    },
    'staged name without id updates current row' => static function () use ($currentRows): mixed {
        return SQLiteImportTransactionPlan::plan($currentRows(), [
            ['option_name' => 'home', 'option_value' => 'https://new.example', 'autoload' => 'yes'],
        ])['updated'][0]['after']['option_id'];
    },
    'unchanged staged row is tracked' => static function () use ($currentRows): mixed {
        return SQLiteImportTransactionPlan::plan($currentRows(), [
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
        ])['unchanged'][0]['option_name'];
    },
    'unchanged staged row does not dirty pages' => static function () use ($currentRows): mixed {
        return SQLiteImportTransactionPlan::plan($currentRows(), [
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
        ])['dirty_pages'];
    },
    'new explicit rowid is inserted' => static function () use ($currentRows): mixed {
        return SQLiteImportTransactionPlan::plan($currentRows(), [
            ['option_id' => 200, 'option_name' => 'rewrite_rules', 'option_value' => 'rules', 'autoload' => 'no'],
        ])['inserted'][0]['option_id'];
    },
    'new explicit rowid dirties expected leaf' => static function () use ($currentRows): mixed {
        return SQLiteImportTransactionPlan::plan($currentRows(), [
            ['option_id' => 200, 'option_name' => 'rewrite_rules', 'option_value' => 'rules', 'autoload' => 'no'],
        ])['dirty_pages'];
    },
    'deferred begin is rejected' => static function () use ($currentRows, $stagedRows): mixed {
        try {
            SQLiteImportTransactionPlan::plan($currentRows(), $stagedRows(), ['begin' => 'BEGIN DEFERRED']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'relative database path is rejected' => static function () use ($currentRows, $stagedRows): mixed {
        try {
            SQLiteImportTransactionPlan::plan($currentRows(), $stagedRows(), ['database_path' => 'relative.sqlite']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'invalid option name is rejected' => static function () use ($currentRows): mixed {
        try {
            SQLiteImportTransactionPlan::plan($currentRows(), [
                ['option_name' => '', 'option_value' => 'bad', 'autoload' => 'no'],
            ]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'invalid autoload is rejected' => static function () use ($currentRows): mixed {
        try {
            SQLiteImportTransactionPlan::plan($currentRows(), [
                ['option_name' => 'bad_autoload', 'option_value' => 'bad', 'autoload' => 'maybe'],
            ]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'duplicate current option names are rejected' => static function (): mixed {
        try {
            SQLiteImportTransactionPlan::plan([
                ['option_id' => 1, 'option_name' => 'home', 'option_value' => 'a', 'autoload' => 'yes'],
                ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'b', 'autoload' => 'yes'],
            ], []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'duplicate current rowids are rejected' => static function (): mixed {
        try {
            SQLiteImportTransactionPlan::plan([
                ['option_id' => 1, 'option_name' => 'home', 'option_value' => 'a', 'autoload' => 'yes'],
                ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'b', 'autoload' => 'yes'],
            ], []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
];

$expected = [
    'begin mode is immediate' => 'immediate',
    'begin write lock is acquired' => true,
    'database path is preserved' => '/tmp/wp-current-import.sqlite',
    'page size is preserved' => 1024,
    'default journal mode is delete' => 'delete',
    'default sync mode is full' => 'full',
    'delete missing defaults false' => false,
    'replace conflicts defaults false' => false,
    'two staged current rows update' => 2,
    'siteurl before value captured' => 'https://old.example',
    'siteurl after value captured' => 'https://new.example',
    'active plugins update keeps rowid' => 65,
    'active plugins update changes value' => 'a:1:{i:0;s:19:"plugin/plugin.php";}',
    'one staged row inserts' => 1,
    'new row receives next rowid' => 131,
    'new row name is preserved' => 'blogname',
    'new row autoload is preserved' => 'yes',
    'no row deleted without delete-missing' => 0,
    'final row count includes retained current row' => 5,
    'final rows remain rowid sorted' => [1, 2, 65, 130, 131],
    'dirty pages include first leaf' => 2,
    'dirty pages include second leaf' => 3,
    'dirty pages include third leaf' => 4,
    'journal bytes includes dirty page records' => 3124,
    'update statement is first' => 'update',
    'update statement counts two rows' => 2,
    'insert statement is second' => 'insert',
    'insert statement counts one row' => 1,
    'sync sequence has journal database directory' => ['rollback_journal', 'database', 'directory'],
    'journal sync is full' => ['full'],
    'database sync is dataonly full' => ['full', 'dataonly'],
    'directory sync is normal' => ['normal'],
    'dependency names current import' => true,
    'delete missing removes absent options' => 2,
    'delete missing includes home' => 'home',
    'delete missing includes transient' => 'transient_feed',
    'delete statement appears when deleting missing' => 'delete',
    'truncate journal mode is preserved' => 'truncate',
    'normal sync lowers sync flags' => ['normal'],
    'persist journal adds header sync target' => ['rollback_journal', 'database', 'rollback_journal_header', 'directory'],
    'exclusive begin is accepted' => 'exclusive',
    'conflicting update aborts by default' => 'aborted',
    'replace conflict deletes current name owner' => 2,
    'replace conflict records action' => 'delete_conflicting_current',
    'staged duplicate id uses last source row' => 'last',
    'staged name without id updates current row' => 2,
    'unchanged staged row is tracked' => 'home',
    'unchanged staged row does not dirty pages' => [],
    'new explicit rowid is inserted' => 200,
    'new explicit rowid dirties expected leaf' => [5],
    'deferred begin is rejected' => 'rejected',
    'relative database path is rejected' => 'rejected',
    'invalid option name is rejected' => 'rejected',
    'invalid autoload is rejected' => 'rejected',
    'duplicate current option names are rejected' => 'rejected',
    'duplicate current rowids are rejected' => 'rejected',
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application import transaction current next21 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
