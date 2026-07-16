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
    'real upstream fkey2 composite cascade cites update block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-12.3.1'));
        $t->true(is_string($source) && str_contains($source, 'FOREIGN KEY(c39, c38) REFERENCES up ON UPDATE CASCADE'));
    },
    'real upstream fkey2 composite cascade cites restrict block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, "catchsql { DELETE FROM up WHERE c34 = 'yes' }"));
        $t->true(is_string($source) && str_contains($source, 'SELECT c39, c38 FROM down'));
    },
];

for ($i = 1; $i <= 100; ++$i) {
    $oldC34 = 'yes' . $i;
    $oldC35 = 'no' . $i;
    $newC34 = 'possibly' . $i;
    $parents = [
        ['c34' => $oldC34, 'c35' => $oldC35, 'label' => 'target-' . $i],
        ['c34' => 'spare' . $i, 'c35' => 'keep' . $i, 'label' => 'spare-' . $i],
    ];
    $children = [
        ['c39' => $oldC34, 'c38' => $oldC35, 'label' => 'down-target-' . $i],
        ['c39' => 'spare' . $i, 'c38' => 'keep' . $i, 'label' => 'down-spare-' . $i],
    ];
    if ($i % 5 === 0) {
        $children[] = ['c39' => $oldC34, 'c38' => $oldC35, 'label' => 'down-target-extra-' . $i];
    }

    $cascade = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::compositeCascadeRestrictCycle(
        $parents,
        $children,
        $oldC34,
        $oldC35,
        $newC34,
        false
    );
    $restrict = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::compositeCascadeRestrictCycle(
        $parents,
        $children,
        $oldC34,
        $oldC35,
        $newC34,
        true
    );

    $case = 'real upstream fkey2 composite cascade restrict dynamic ' . $i;
    $cascadeCount = $i % 5 === 0 ? 2 : 1;

    foreach ([
        'source' => 'fkey2.test fkey2-12.3.1..12.3.5',
        'operation' => 'composite-foreign-key-cascade-update-restrict-delete',
        'status' => 'commit-ok',
        'parent_key_columns' => ['c34', 'c35'],
        'child_key_columns' => ['c39', 'c38'],
        'updated_parent_keys.0' => [$oldC34, $oldC35, $newC34, $oldC35],
        'cascade_child_keys.0' => [$oldC34, $oldC35, $newC34, $oldC35],
        'cascade_child_keys.' . ($cascadeCount - 1) => [$oldC34, $oldC35, $newC34, $oldC35],
        'restrict_delete_blocked' => false,
        'violation_count' => 0,
        'dependencies.0' => 'sqlite-fkey2-composite-parent-column-order',
        'dependencies.1' => 'sqlite-fkey2-composite-on-update-cascade',
        'dependencies.2' => 'sqlite-fkey2-composite-delete-restrict',
    ] as $path => $expected) {
        $tests[$case . ' cascade ' . $path] = static function (TestRunner $t) use ($cascade, $path, $expected, $value): void {
            $t->same($expected, $value($cascade(), (string) $path));
        };
    }

    $tests[$case . ' cascade updates every matching child'] = static function (TestRunner $t) use ($cascade, $cascadeCount): void {
        $t->same($cascadeCount, count($cascade()['cascade_child_keys']));
    };
    $tests[$case . ' cascade keeps unrelated child composite key'] = static function (TestRunner $t) use ($cascade, $i): void {
        $t->true(in_array(['spare' . $i, 'keep' . $i], $cascade()['child_keys'], true));
    };

    foreach ([
        'source' => 'fkey2.test fkey2-12.3.1..12.3.5',
        'operation' => 'composite-foreign-key-cascade-update-restrict-delete',
        'status' => 'commit-ok',
        'restrict_delete_blocked' => false,
        'deleted_parent_keys' => [],
        'violation_count' => 0,
        'parent_keys.0' => [$newC34, $oldC35],
        'child_keys.0' => [$newC34, $oldC35],
        'dependencies.2' => 'sqlite-fkey2-composite-delete-restrict',
    ] as $path => $expected) {
        $tests[$case . ' post cascade delete old key ' . $path] = static function (TestRunner $t) use ($restrict, $path, $expected, $value): void {
            $t->same($expected, $value($restrict(), (string) $path));
        };
    }
    $tests[$case . ' post cascade preserves duplicated child key count'] = static function (TestRunner $t) use ($restrict, $newC34, $oldC35, $cascadeCount): void {
        $matches = array_values(array_filter(
            $restrict()['child_keys'],
            static fn (array $key): bool => $key === [$newC34, $oldC35]
        ));
        $t->same($cascadeCount, count($matches));
    };
}

return $tests;
