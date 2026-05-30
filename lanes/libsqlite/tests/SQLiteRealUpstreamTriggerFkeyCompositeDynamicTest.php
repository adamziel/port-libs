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
    'real upstream fkey2 composite dynamic cites column order cascade section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-12.3.1'));
        $t->true(is_string($source) && str_contains($source, 'FOREIGN KEY(c39, c38) REFERENCES up ON UPDATE CASCADE'));
    },
    'real upstream fkey2 composite dynamic cites restrict delete section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, "catchsql { DELETE FROM up WHERE c34 = 'yes' }"));
        $t->true(is_string($source) && str_contains($source, 'SELECT c39, c38 FROM down'));
    },
];

for ($i = 1; $i <= 110; ++$i) {
    $oldC34 = 'yes_' . $i;
    $oldC35 = 'no_' . $i;
    $newC34 = 'possibly_' . $i;
    $sideC34 = 'side_' . $i;
    $sideC35 = 'keep_' . $i;

    $parents = [
        ['c34' => $oldC34, 'c35' => $oldC35, 'label' => 'target_' . $i],
        ['c34' => $sideC34, 'c35' => $sideC35, 'label' => 'side_' . $i],
    ];
    if ($i % 4 === 0) {
        $parents[] = ['c34' => 'unused_' . $i, 'c35' => 'orphan_' . $i, 'label' => 'unused_' . $i];
    }

    $children = [
        ['c39' => $oldC34, 'c38' => $oldC35, 'label' => 'first_child_' . $i],
        ['c39' => $sideC34, 'c38' => $sideC35, 'label' => 'side_child_' . $i],
    ];
    if ($i % 3 === 0) {
        $children[] = ['c39' => $oldC34, 'c38' => $oldC35, 'label' => 'second_child_' . $i];
    }

    $run = static fn (bool $attemptRestrict = true): array => SQLiteDynamicTriggerForeignKeyPlan::compositeCascadeRestrictCycle(
        $parents,
        $children,
        $oldC34,
        $oldC35,
        $newC34,
        $attemptRestrict,
    );

    $expectedCascadeCount = $i % 3 === 0 ? 2 : 1;
    $expectedChildKeysAfterCascade = array_map(
        static fn (array $row): array => $row['c39'] === $oldC34 && $row['c38'] === $oldC35
            ? [$newC34, $oldC35]
            : [$row['c39'], $row['c38']],
        $children,
    );
    $case = 'fkey2-12.3 composite column-order cascade restrict dynamic ' . $i;

    foreach ([
        'source' => 'fkey2.test fkey2-12.3.1..12.3.5',
        'operation' => 'composite-foreign-key-cascade-update-restrict-delete',
        'status' => 'commit-ok',
        'parent_key_columns' => ['c34', 'c35'],
        'child_key_columns' => ['c39', 'c38'],
        'updated_parent_keys.0' => [$oldC34, $oldC35, $newC34, $oldC35],
        'cascade_child_keys.0' => [$oldC34, $oldC35, $newC34, $oldC35],
        'restrict_delete_blocked' => false,
        'deleted_parent_keys' => [],
        'violation_count' => 0,
        'dependencies.0' => 'sqlite-fkey2-composite-parent-column-order',
        'dependencies.1' => 'sqlite-fkey2-composite-on-update-cascade',
        'dependencies.2' => 'sqlite-fkey2-composite-delete-restrict',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($run, $path, $expected, $value): void {
            $t->same($expected, $value($run(), (string) $path));
        };
    }

    $tests[$case . ' cascades every matching child key in declared child order'] = static function (TestRunner $t) use ($run, $expectedCascadeCount): void {
        $t->same($expectedCascadeCount, count($run()['cascade_child_keys']));
    };
    $tests[$case . ' child key order follows referenced parent c34 c35 mapping'] = static function (TestRunner $t) use ($run, $expectedChildKeysAfterCascade): void {
        $t->same($expectedChildKeysAfterCascade, $run()['child_keys']);
    };
    $tests[$case . ' nonmatching side child remains on original composite key'] = static function (TestRunner $t) use ($run, $sideC34, $sideC35): void {
        $t->true(in_array([$sideC34, $sideC35], $run()['child_keys'], true));
    };
    $tests[$case . ' cascade-only pass keeps old parent absent'] = static function (TestRunner $t) use ($run, $oldC34, $oldC35): void {
        $t->same(false, in_array([$oldC34, $oldC35], $run(false)['parent_keys'], true));
    };
    $tests[$case . ' cascade-only pass keeps new parent present'] = static function (TestRunner $t) use ($run, $newC34, $oldC35): void {
        $t->same(true, in_array([$newC34, $oldC35], $run(false)['parent_keys'], true));
    };
    $tests[$case . ' restrict delete of old key is a no-op after cascade'] = static function (TestRunner $t) use ($run): void {
        $t->same([], $run()['deleted_parent_keys']);
    };
}

return $tests;
