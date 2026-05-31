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
    'real upstream fkey2 definition diagnostic cites mismatch matrix' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-10.*, test "foreign key mismatch" and'));
        $t->true(is_string($source) && str_contains($source, 'foreign key mismatch - "c" referencing "."'));
    },
    'real upstream fkey2 definition diagnostic cites rowid matrix' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'unknown column "rowid" in foreign key definition'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE t1(rowid PRIMARY KEY, b)'));
    },
];

for ($i = 1; $i <= 180; ++$i) {
    $suffix = (string) $i;
    $validParent = [
        'name' => 'parent_' . $suffix,
        'columns' => ['a', 'b', 'rowid'],
        'primary_key' => ['a'],
        'unique' => [
            ['columns' => ['b'], 'collation' => 'binary'],
            ['columns' => ['rowid'], 'collation' => 'binary'],
        ],
        'collation' => ['a' => 'binary', 'b' => 'binary', 'rowid' => 'binary'],
    ];
    $validChild = [
        'name' => 'child_' . $suffix,
        'columns' => ['x', 'y', 'rowid'],
        'child_columns' => ['x'],
        'parent_columns' => ['a'],
    ];

    $missingParent = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2DefinitionDiagnostic(
        $validParent,
        array_replace($validChild, ['parent_exists' => false, 'parent_name' => 'nosuch_' . $suffix])
    );
    $missingParentColumn = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2DefinitionDiagnostic(
        $validParent,
        array_replace($validChild, ['parent_columns' => ['missing_' . $suffix]])
    );
    $childRowidUnknown = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2DefinitionDiagnostic(
        $validParent,
        [
            'name' => 'child_rowid_unknown_' . $suffix,
            'columns' => ['x', 'y'],
            'child_columns' => ['rowid'],
            'parent_columns' => ['a'],
        ]
    );
    $parentRowidMismatch = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2DefinitionDiagnostic(
        [
            'name' => 'parent_without_rowid_' . $suffix,
            'columns' => ['a', 'b'],
            'primary_key' => ['a'],
        ],
        [
            'name' => 'child_parent_rowid_' . $suffix,
            'columns' => ['x'],
            'child_columns' => ['x'],
            'parent_columns' => ['rowid'],
        ]
    );
    $collationMismatch = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2DefinitionDiagnostic(
        [
            'name' => 'parent_collation_' . $suffix,
            'columns' => ['a'],
            'unique' => [['columns' => ['a'], 'collation' => 'nocase']],
            'collation' => ['a' => 'binary'],
        ],
        [
            'name' => 'child_collation_' . $suffix,
            'columns' => ['x'],
            'child_columns' => ['x'],
            'parent_columns' => ['a'],
        ]
    );
    $declaredRowidOk = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2DefinitionDiagnostic(
        $validParent,
        [
            'name' => 'child_declared_rowid_' . $suffix,
            'columns' => ['rowid', 'payload'],
            'child_columns' => ['rowid'],
            'parent_columns' => ['rowid'],
        ]
    );

    $case = 'real upstream fkey2 definition diagnostic dynamic ' . $i;
    foreach ([
        'source' => 'fkey2.test fkey2-10.1..10.2',
        'operation' => 'foreign-key-definition-diagnostic',
        'status' => 'no-such-parent-table',
        'error' => 'no such table: main.nosuch_' . $suffix,
        'parent_exists' => false,
        'schema_mismatch' => false,
        'dependencies.0' => 'sqlite-fkey2-reports-missing-parent-table-at-dml-time',
    ] as $path => $expected) {
        $tests[$case . ' missing parent ' . $path] = static function (TestRunner $t) use ($missingParent, $path, $expected, $value): void {
            $t->same($expected, $value($missingParent(), (string) $path));
        };
    }

    foreach ([
        'status' => 'foreign-key-mismatch',
        'error' => 'foreign key mismatch - "child_' . $suffix . '" referencing "parent_' . $suffix . '"',
        'schema_mismatch' => true,
        'parent_key_valid' => false,
        'foreign_key_parent_columns.0' => 'missing_' . $suffix,
    ] as $path => $expected) {
        $tests[$case . ' missing parent column ' . $path] = static function (TestRunner $t) use ($missingParentColumn, $path, $expected, $value): void {
            $t->same($expected, $value($missingParentColumn(), (string) $path));
        };
    }

    foreach ([
        'status' => 'definition-error',
        'error' => 'unknown column "rowid" in foreign key definition',
        'unknown_child_rowid' => true,
        'schema_mismatch' => false,
        'dependencies.1' => 'sqlite-fkey2-rowid-child-key-requires-declared-column',
    ] as $path => $expected) {
        $tests[$case . ' unknown child rowid ' . $path] = static function (TestRunner $t) use ($childRowidUnknown, $path, $expected, $value): void {
            $t->same($expected, $value($childRowidUnknown(), (string) $path));
        };
    }

    foreach ([
        'status' => 'foreign-key-mismatch',
        'error' => 'foreign key mismatch - "child_parent_rowid_' . $suffix . '" referencing "parent_without_rowid_' . $suffix . '"',
        'schema_mismatch' => true,
        'foreign_key_parent_columns.0' => 'rowid',
        'dependencies.2' => 'sqlite-fkey2-rowid-parent-key-requires-declared-column',
    ] as $path => $expected) {
        $tests[$case . ' parent rowid mismatch ' . $path] = static function (TestRunner $t) use ($parentRowidMismatch, $path, $expected, $value): void {
            $t->same($expected, $value($parentRowidMismatch(), (string) $path));
        };
    }

    foreach ([
        'status' => 'foreign-key-mismatch',
        'error' => 'foreign key mismatch - "child_collation_' . $suffix . '" referencing "parent_collation_' . $suffix . '"',
        'schema_mismatch' => true,
        'parent_key_valid' => false,
        'dependencies.3' => 'sqlite-fkey2-parent-key-must-match-unique-index-and-collation',
    ] as $path => $expected) {
        $tests[$case . ' parent collation mismatch ' . $path] = static function (TestRunner $t) use ($collationMismatch, $path, $expected, $value): void {
            $t->same($expected, $value($collationMismatch(), (string) $path));
        };
    }

    foreach ([
        'status' => 'definition-ok',
        'error' => null,
        'unknown_child_rowid' => false,
        'schema_mismatch' => false,
        'parent_key_valid' => true,
        'foreign_key_child_columns.0' => 'rowid',
        'foreign_key_parent_columns.0' => 'rowid',
    ] as $path => $expected) {
        $tests[$case . ' declared rowid ok ' . $path] = static function (TestRunner $t) use ($declaredRowidOk, $path, $expected, $value): void {
            $t->same($expected, $value($declaredRowidOk(), (string) $path));
        };
    }
}

$tests['real upstream fkey2 definition diagnostic rejects malformed child name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey2DefinitionDiagnostic(
        ['name' => 'parent', 'columns' => ['a'], 'primary_key' => ['a']],
        ['name' => 'bad name', 'columns' => ['x'], 'child_columns' => ['x'], 'parent_columns' => ['a']]
    ));
};

$tests['real upstream fkey2 definition diagnostic rejects empty parent columns'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey2DefinitionDiagnostic(
        ['name' => 'parent', 'columns' => [], 'primary_key' => ['a']],
        ['name' => 'child', 'columns' => ['x'], 'child_columns' => ['x'], 'parent_columns' => ['a']]
    ));
};

return $tests;
