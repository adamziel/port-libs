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
    'real upstream fkey1 dynamic corpus cites quoted cascade section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test');
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test fkey1-4.0'));
        $t->true(is_string($source) && str_contains($source, 'This case is identical to the previous except the "xx"'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA table_info="""1"'));
    },
    'real upstream fkey1 dynamic corpus cites replace cascade section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test');
        $t->true(is_string($source) && str_contains($source, 'INSERT OR REPLACE INTO t11 VALUES (2, 3)'));
        $t->true(is_string($source) && str_contains($source, 'DELETE CASCADE caused by deleting that row removes the (3, 2) row'));
        $t->true(is_string($source) && str_contains($source, 'INSERT OR REPLACE INTO Foo(Id, ParentId, C1) VALUES (2, 3'));
    },
    'real upstream fkey1 dynamic corpus cites partial index and wide check sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE UNIQUE INDEX p1x ON p1(x) WHERE y<2'));
        $t->true(is_string($source) && str_contains($source, 'foreign key mismatch - "c1" referencing "p1"'));
        $t->true(is_string($source) && str_contains($source, 'FOREIGN KEY(a,a,a,a,a,a,a,a,a,a,a,a,a,a) REFERENCES t0'));
    },
];

for ($i = 1; $i <= 260; ++$i) {
    $quotedParent = [['"2' => 'abc-' . $i], ['"2' => 'uvw-' . $i]];
    $quotedChild = [['"5' => 'abc-' . $i], ['"5' => 'loose-' . $i]];
    $cascade = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1QuotedCascadeReplacePlan(
        $quotedParent,
        $quotedChild,
        '"2',
        '"5',
        'cascade'
    );
    $restrict = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1QuotedCascadeReplacePlan(
        $quotedParent,
        $quotedChild,
        '"2',
        '"5',
        'restrict'
    );
    $partialIndex = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1QuotedCascadeReplacePlan(
        [['x' => $i, 'y' => 1]],
        [['a' => $i]],
        'x',
        'a',
        'no action',
        true
    );

    foreach ([
        'source' => 'fkey1.test fkey1-4.0..9.1',
        'operation' => 'quoted-identifier-fkey-cascade-replace',
        'status' => 'commit-ok',
        'quoted_identifier_dequoted_once' => true,
        'initial_parent_keys' => ['abc-' . $i, 'uvw-' . $i],
        'initial_child_keys' => ['abc-' . $i, 'loose-' . $i],
        'remaining_child_keys' => ['loose-' . $i],
        'trace_statement_count' => 1,
        'dependencies.0' => 'sqlite-fkey1-quoted-identifiers-dequote-once',
        'dependencies.1' => 'sqlite-fkey1-on-delete-cascade-removes-children',
    ] as $path => $expected) {
        $tests["real upstream fkey1 quoted cascade dynamic {$i} {$path}"] = static function (TestRunner $t) use ($cascade, $value, $path, $expected): void {
            $t->same($expected, $value($cascade(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-failed',
        'error' => 'FOREIGN KEY constraint failed',
        'remaining_parent_count' => 2,
        'remaining_child_keys' => ['abc-' . $i, 'loose-' . $i],
        'dependencies.3' => 'sqlite-fkey1-restrict-fails-before-delete',
    ] as $path => $expected) {
        $tests["real upstream fkey1 quoted restrict dynamic {$i} {$path}"] = static function (TestRunner $t) use ($restrict, $value, $path, $expected): void {
            $t->same($expected, $value($restrict(), (string) $path));
        };
    }

    foreach ([
        'status' => 'foreign-key-mismatch',
        'error' => 'foreign key mismatch',
        'partial_parent_index' => true,
        'dependencies.2' => 'sqlite-fkey1-partial-parent-index-does-not-satisfy-fk',
    ] as $path => $expected) {
        $tests["real upstream fkey1 partial parent index dynamic {$i} {$path}"] = static function (TestRunner $t) use ($partialIndex, $value, $path, $expected): void {
            $t->same($expected, $value($partialIndex(), (string) $path));
        };
    }

    $chain = [
        ['id' => 1, 'parent_id' => null, 'label' => 'root-' . $i],
        ['id' => 2, 'parent_id' => 1, 'label' => 'middle-' . $i],
        ['id' => 3, 'parent_id' => 2, 'label' => 'leaf-' . $i],
        ['id' => 4, 'parent_id' => 3, 'label' => 'tail-' . $i],
    ];
    $replaceViolation = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1SelfReplaceCascadeViolation($chain, 2, 3);
    foreach ([
        'source' => 'fkey1.test fkey1-5.1..5.4',
        'operation' => 'self-referential-replace-cascade-violation',
        'status' => 'constraint-failed',
        'error' => 'FOREIGN KEY constraint failed',
        'replace_id' => 2,
        'new_parent_id' => 3,
        'cascade_deleted_ids' => [2, 3, 4],
        'surviving_ids_before_insert' => [1],
        'dependencies.0' => 'sqlite-fkey1-replace-deletes-old-row-before-insert',
        'dependencies.1' => 'sqlite-fkey1-self-referential-cascade-can-remove-new-parent',
    ] as $path => $expected) {
        $tests["real upstream fkey1 self replace cascade dynamic {$i} {$path}"] = static function (TestRunner $t) use ($replaceViolation, $value, $path, $expected): void {
            $t->same($expected, $value($replaceViolation(), (string) $path));
        };
    }

    $width = 4 + ($i % 13);
    $columns = [];
    $row = ['b' => $i, 'c' => 1, 'd' => $i + 7];
    for ($column = 0; $column < $width; ++$column) {
        $name = 'a' . $column;
        $columns[] = $name;
        $row[$name] = $column % 2 === 0 ? $i : null;
    }
    $wideCheck = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1WideForeignKeyCheck([$row], $columns, 't0');
    foreach ([
        'source' => 'fkey1.test fkey1-7.1..7.2',
        'operation' => 'wide-foreign-key-check-register-allocation',
        'status' => 'commit-ok',
        'foreign_key_width' => $width,
        'table_column_count' => count($row),
        'violation_count' => 1,
        'result_tuples.0' => ['t1', 1, 't0', 0],
        'dependencies.0' => 'sqlite-fkey1-foreign-key-check-wide-key-register-allocation',
        'dependencies.1' => 'sqlite-fkey1-generated-column-wide-fkey-check-does-not-overread',
    ] as $path => $expected) {
        $tests["real upstream fkey1 wide foreign-key-check dynamic {$i} {$path}"] = static function (TestRunner $t) use ($wideCheck, $value, $path, $expected): void {
            $t->same($expected, $value($wideCheck(), (string) $path));
        };
    }
}

$tests['real upstream fkey1 dynamic corpus rejects unsupported action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey1QuotedCascadeReplacePlan(
        [['id' => 1]],
        [['parent_id' => 1]],
        'id',
        'parent_id',
        'set default'
    ));
};

return $tests;
