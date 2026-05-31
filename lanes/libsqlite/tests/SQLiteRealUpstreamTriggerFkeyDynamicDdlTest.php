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
    'real upstream fkey2 ddl cites add column block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-14.1*: ALTER TABLE ADD COLUMN'));
        $t->true(is_string($source) && str_contains($source, 'Cannot add a REFERENCES column with non-NULL default value'));
    },
    'real upstream fkey2 ddl cites rename table block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-14.2*: ALTER TABLE RENAME TABLE'));
        $t->true(is_string($source) && str_contains($source, 'ALTER TABLE t1 RENAME TO t4'));
    },
    'real upstream fkey2 ddl cites drop table block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-14.3*: DROP TABLE'));
        $t->true(is_string($source) && str_contains($source, 'DROP TABLE t1'));
    },
];

$schemas = ['main', 'temp', 'aux'];

for ($i = 1; $i <= 150; ++$i) {
    $schema = $schemas[$i % count($schemas)];
    $parent = 'app_parent_' . $i;
    $child = 'app_child_' . $i;
    $newParent = 'app_parent_new_' . $i;
    $rowCount = 1 + ($i % 5);
    $default = $i % 4 === 0 ? null : 'seed_' . $i;
    $foreignKeys = $i % 7 !== 0;
    $add = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyDdlPlan(
        [[
            'schema' => $schema,
            'name' => $child,
            'sql' => "CREATE TABLE {$child}(setting_id INTEGER, key_name TEXT)",
            'rows' => $rowCount,
        ]],
        [
            'action' => 'add-column',
            'schema' => $schema,
            'table' => $child,
            'column' => 'parent_id',
            'references' => $parent,
            'default' => $default,
            'foreign_keys' => $foreignKeys,
        ]
    );
    $blocked = $foreignKeys && $default !== null;
    $case = sprintf('real upstream fkey2 ddl add references column dynamic %03d %s', $i, $schema);
    foreach ([
        'source' => 'fkey2.test fkey2-14.1.*',
        'operation' => 'foreign-key-ddl-add-column',
        'status' => $blocked ? 'schema-error' : 'commit-ok',
        'schema' => $schema,
        'table' => $child,
        'column' => 'parent_id',
        'references' => $parent,
        'target_row_count' => $rowCount,
        'default_value' => $default,
        'error' => $blocked ? 'Cannot add a REFERENCES column with non-NULL default value' : null,
        'dependencies.0' => 'sqlite-fkey2-add-references-column-allows-null-default',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($add, $path, $expected, $value): void {
            $t->same($expected, $value($add(), (string) $path));
        };
    }
    $tests[$case . ' schema text outcome'] = static function (TestRunner $t) use ($add, $blocked, $child, $parent, $default): void {
        $next = $add()['next_sql'];
        if ($blocked) {
            $t->same("CREATE TABLE {$child}(setting_id INTEGER, key_name TEXT)", $next);
            return;
        }
        $t->true(str_contains((string) $next, 'parent_id'));
        $t->true(str_contains((string) $next, 'REFERENCES ' . $parent));
        if ($default !== null) {
            $t->true(str_contains((string) $next, "DEFAULT 'seed_"));
        }
    };

    $rename = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyDdlPlan(
        [
            [
                'schema' => $schema,
                'name' => $parent,
                'sql' => "CREATE TABLE {$parent}(a PRIMARY KEY, b REFERENCES {$parent})",
            ],
            [
                'schema' => $schema,
                'name' => $child,
                'sql' => "CREATE TABLE {$child}(a REFERENCES {$parent}, b REFERENCES other_parent, c REFERENCES \"{$parent}\")",
            ],
            [
                'schema' => 'archive',
                'name' => $child . '_archive',
                'sql' => "CREATE TABLE {$child}_archive(a REFERENCES {$parent})",
            ],
        ],
        [
            'action' => 'rename-table',
            'schema' => $schema,
            'table' => $parent,
            'new_name' => $newParent,
        ]
    );
    $case = sprintf('real upstream fkey2 ddl rename table dynamic %03d %s', $i, $schema);
    foreach ([
        'source' => 'fkey2.test fkey2-14.2.*',
        'operation' => 'foreign-key-ddl-rename-table',
        'status' => 'commit-ok',
        'schema' => $schema,
        'old_name' => $parent,
        'new_name' => $newParent,
        'renamed_table_names.0' => $newParent,
        'renamed_table_names.1' => $child,
        'renamed_table_names.2' => $child . '_archive',
        'reference_rewrite_count' => 3,
        'dependencies.1' => 'sqlite-fkey2-rename-table-rewrites-child-foreign-key-parents',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($rename, $path, $expected, $value): void {
            $t->same($expected, $value($rename(), (string) $path));
        };
    }
    $tests[$case . ' rewrites same schema only'] = static function (TestRunner $t) use ($rename, $parent, $newParent): void {
        $sql = $rename()['renamed_sql'];
        $t->true(str_contains($sql[0], 'CREATE TABLE "' . $newParent . '"'));
        $t->true(str_contains($sql[0], 'REFERENCES "' . $newParent . '"'));
        $t->true(str_contains($sql[1], 'REFERENCES "' . $newParent . '"'));
        $t->true(str_contains($sql[2], 'REFERENCES ' . $parent));
    };
}

for ($i = 1; $i <= 100; ++$i) {
    $schema = $schemas[$i % count($schemas)];
    $parent = 'app_drop_parent_' . $i;
    $child = 'app_drop_child_' . $i;
    $childRows = $i % 3 === 0 ? 0 : (($i % 4) + 1);
    $foreignKeys = $i % 8 !== 0;
    $drop = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyDdlPlan(
        [
            [
                'schema' => $schema,
                'name' => $parent,
                'sql' => "CREATE TABLE {$parent}(id PRIMARY KEY)",
            ],
            [
                'schema' => $schema,
                'name' => $child,
                'parent' => $parent,
                'child_rows' => $childRows,
                'child_references' => range(1, max(1, $childRows)),
                'sql' => "CREATE TABLE {$child}(parent_id REFERENCES {$parent})",
            ],
        ],
        [
            'action' => 'drop-table',
            'schema' => $schema,
            'table' => $parent,
            'foreign_keys' => $foreignKeys,
        ]
    );
    $blocked = $foreignKeys && $childRows > 0;
    $case = sprintf('real upstream fkey2 ddl drop parent table dynamic %03d %s', $i, $schema);
    foreach ([
        'source' => 'fkey2.test fkey2-14.3.* and fkey2-14.4.*',
        'operation' => 'foreign-key-ddl-drop-table',
        'status' => $blocked ? 'constraint-failed' : 'commit-ok',
        'schema' => $schema,
        'table' => $parent,
        'foreign_keys' => $foreignKeys,
        'referencing_tables.0.table' => $child,
        'referencing_child_row_count' => $childRows,
        'error' => $blocked ? 'FOREIGN KEY constraint failed' : null,
        'dependencies.0' => 'sqlite-fkey2-drop-table-blocks-when-child-rows-reference-parent',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($drop, $path, $expected, $value): void {
            $t->same($expected, $value($drop(), (string) $path));
        };
    }
    $tests[$case . ' remaining table names follow commit boundary'] = static function (TestRunner $t) use ($drop, $blocked, $parent, $child): void {
        $t->same($blocked ? [$parent, $child] : [$child], $drop()['remaining_table_names']);
    };
}

$tests['real upstream fkey2 ddl rejects unsupported action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyDdlPlan([], ['action' => 'vacuum', 'table' => 'app_settings']));
};

$tests['real upstream fkey2 ddl rejects missing target'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyDdlPlan([], ['action' => 'drop-table', 'table' => 'app_settings']));
};

$tests['real upstream fkey2 ddl rejects malformed column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyDdlPlan(
        [['schema' => 'main', 'name' => 'app_settings', 'sql' => 'CREATE TABLE app_settings(id INTEGER)']],
        ['action' => 'add-column', 'table' => 'app_settings', 'column' => 'bad-column', 'references' => 'app_parent']
    ));
};

return $tests;
