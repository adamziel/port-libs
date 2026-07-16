<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$tests = [
    'real upstream without_rowid3 trigger fkey corpus cites recursive and count changes blocks' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid3.test');
        $t->true(is_string($source) && str_contains($source, 'CASCADE) are allowed even if recursive triggers are disabled'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA recursive_triggers = off'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA count_changes = 1'));
        $t->true(is_string($source) && str_contains($source, 'SQLITE_CONSTRAINT_FOREIGNKEY'));
    },
];

for ($i = 1; $i <= 220; ++$i) {
    $root = ($i * 1000) + 1;
    $rows = [
        ['node' => $root, 'parent' => null],
        ['node' => $root + 1, 'parent' => $root],
        ['node' => $root + 2, 'parent' => $root],
        ['node' => $root + 3, 'parent' => $root + 1],
        ['node' => $root + 4, 'parent' => $root + 1],
        ['node' => $root + 5, 'parent' => $root + 2],
        ['node' => $root + 6, 'parent' => $root + 2],
    ];

    $off = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidRecursiveCascadePragmaPlan(
        $rows,
        $rows,
        $root,
        false
    );
    $on = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidRecursiveCascadePragmaPlan(
        $rows,
        $rows,
        $root,
        true
    );

    foreach ([
        'source' => 'without_rowid3.test without_rowid3-4.1..4.4',
        'operation' => 'without-rowid-recursive-foreign-key-cascade-ignores-recursive-trigger-pragma',
        'status' => 'commit-ok',
        'without_rowid' => true,
        'recursive_triggers' => false,
        'foreign_key_deleted_nodes' => [$root, $root + 1, $root + 2, $root + 3, $root + 4, $root + 5, $root + 6],
        'trigger_deleted_nodes' => [$root, $root + 1, $root + 2],
        'foreign_key_remaining_nodes' => [],
        'trigger_remaining_nodes' => [$root + 3, $root + 4, $root + 5, $root + 6],
        'foreign_key_cascade_reaches_grandchildren' => true,
        'ordinary_trigger_reaches_grandchildren' => false,
        'foreign_key_changes' => 7,
        'trigger_changes' => 3,
        'dependencies.3' => 'sqlite-without-rowid3-recursive-fk-actions-ignore-recursive-trigger-pragma',
        'dependencies.4' => 'sqlite-without-rowid3-user-trigger-recursion-obeys-recursive-trigger-pragma',
    ] as $path => $expected) {
        $tests['without_rowid3-4 recursive fk cascade ignores disabled trigger recursion dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($off, $path, $expected, $value): void {
            $t->same($expected, $value($off(), (string) $path));
        };
    }

    foreach ([
        'source' => 'without_rowid3.test without_rowid3-4.1..4.4',
        'operation' => 'without-rowid-recursive-foreign-key-cascade-ignores-recursive-trigger-pragma',
        'status' => 'commit-ok',
        'without_rowid' => true,
        'recursive_triggers' => true,
        'foreign_key_deleted_nodes' => [$root, $root + 1, $root + 2, $root + 3, $root + 4, $root + 5, $root + 6],
        'trigger_deleted_nodes' => [$root, $root + 1, $root + 2, $root + 3, $root + 4, $root + 5, $root + 6],
        'foreign_key_remaining_nodes' => [],
        'trigger_remaining_nodes' => [],
        'foreign_key_cascade_reaches_grandchildren' => true,
        'ordinary_trigger_reaches_grandchildren' => true,
        'foreign_key_changes' => 7,
        'trigger_changes' => 7,
        'dependencies.2' => 'sqlite-fkey2-cascade-delete-visits-descendant-tree',
    ] as $path => $expected) {
        $tests['without_rowid3-4 recursive fk and user trigger both recurse when enabled dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($on, $path, $expected, $value): void {
            $t->same($expected, $value($on(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 160; ++$i) {
    $parents = [
        ['b' => $i, 'c' => $i + 10, 'label' => 'parent-' . $i],
    ];
    $children = [
        ['id' => $i, 'e' => $i, 'f' => $i + 10, 'label' => 'child-' . $i],
    ];
    $immediate = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidCountChangesForeignKeyStatement(
        $parents,
        $children,
        [
            'operation' => 'insert-child',
            'deferred' => false,
            'rows' => [['id' => $i + 10000, 'e' => $i + 20000, 'f' => $i + 30000]],
        ]
    );
    $deferred = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidCountChangesForeignKeyStatement(
        $parents,
        $children,
        [
            'operation' => 'insert-child',
            'deferred' => true,
            'rows' => [['id' => $i + 10000, 'e' => $i + 20000, 'f' => $i + 30000]],
        ]
    );

    foreach ([
        'source' => 'without_rowid3.test without_rowid3-17.1.1..17.1.14',
        'operation' => 'without-rowid-foreign-key-count-changes-statement',
        'without_rowid' => true,
        'status' => 'constraint-failed',
        'deferred' => false,
        'sqlite3_step_result' => 'SQLITE_CONSTRAINT',
        'returned_count_rows' => [],
        'changes' => 0,
        'total_changes_delta' => 0,
        'violation_count' => 1,
        'dependencies.3' => 'sqlite-without-rowid3-count-changes-immediate-fk-fails-before-row-count',
    ] as $path => $expected) {
        $tests['without_rowid3-17 count_changes immediate fk error before row count dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($immediate, $path, $expected, $value): void {
            $t->same($expected, $value($immediate(), (string) $path));
        };
    }

    foreach ([
        'source' => 'without_rowid3.test without_rowid3-17.1.1..17.1.14',
        'operation' => 'without-rowid-foreign-key-count-changes-statement',
        'without_rowid' => true,
        'status' => 'deferred-constraint-failed',
        'deferred' => true,
        'sqlite3_step_result' => 'SQLITE_ROW',
        'finalize_result' => 'SQLITE_CONSTRAINT',
        'returned_count_rows' => [1],
        'changes' => 1,
        'total_changes_delta' => 1,
        'violation_count' => 1,
        'dependencies.4' => 'sqlite-without-rowid3-count-changes-deferred-fk-returns-row-count-before-commit-fail',
    ] as $path => $expected) {
        $tests['without_rowid3-17 count_changes deferred fk row before constraint dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($deferred, $path, $expected, $value): void {
            $t->same($expected, $value($deferred(), (string) $path));
        };
    }
}

$tests['without_rowid3 dynamic wrapper rejects malformed recursive row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidRecursiveCascadePragmaPlan(
        [['node' => 1, 'parent' => null], ['node' => 2, 'parent' => 1]],
        [['node' => 1, 'parent' => null], ['node' => 'bad', 'parent' => 1]],
        1,
        true
    ));
};

$tests['without_rowid3 dynamic count changes wrapper rejects malformed operation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidCountChangesForeignKeyStatement(
        [['b' => 1, 'c' => 2]],
        [],
        ['operation' => 'delete-child']
    ));
};

return $tests;
