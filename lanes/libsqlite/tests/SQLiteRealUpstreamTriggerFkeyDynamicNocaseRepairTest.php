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
    'real upstream fkey2 nocase repair cites trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-12.2.1'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER tt1 AFTER DELETE ON t1'));
    },
    'real upstream fkey2 nocase repair cites restrict block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE t2(y REFERENCES t1 ON DELETE RESTRICT)'));
        $t->true(is_string($source) && str_contains($source, 'catchsql { DELETE FROM t1 }'));
    },
];

$keys = [
    ['A', 'B'],
    ['Alpha', 'Beta'],
    ['MIXED', 'Case'],
    ['north', 'SOUTH'],
    ['Tenant', 'Setting'],
];

for ($i = 1; $i <= 80; ++$i) {
    $pair = $keys[$i % count($keys)];
    $suffix = (string) $i;
    $parents = [
        ['id' => 'p' . $i . 'a', 'key' => $pair[0] . $suffix],
        ['id' => 'p' . $i . 'b', 'key' => $pair[1] . $suffix],
    ];
    $children = [
        ['id' => 'c' . $i . 'a', 'parent_key' => strtolower($pair[0]) . $suffix],
        ['id' => 'c' . $i . 'b', 'parent_key' => strtolower($pair[1]) . $suffix],
    ];
    if ($i % 6 === 0) {
        $parents[] = ['id' => 'p' . $i . 'c', 'key' => 'Unreferenced' . $suffix];
    }
    if ($i % 7 === 0) {
        $children[] = ['id' => 'c' . $i . 'c', 'parent_key' => strtoupper($pair[0]) . $suffix];
    }

    $repair = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::nocaseDeleteTriggerRepair($parents, $children, 'no action');
    $restrict = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::nocaseDeleteTriggerRepair($parents, $children, 'restrict');
    $expectedReinserted = array_values(array_unique(array_map(
        static fn (array $child): string => strtolower((string) $child['parent_key']),
        $children
    )));
    $expectedReinserted = array_values(array_filter(
        array_column($parents, 'key'),
        static fn (string $key): bool => in_array(strtolower($key), $expectedReinserted, true)
    ));
    $case = 'real upstream fkey2 nocase after delete repair dynamic ' . $i;
    foreach ([
        'source' => 'fkey2.test fkey2-12.2.1..12.2.4',
        'operation' => 'nocase-parent-delete-trigger-repair',
        'status' => 'commit-ok',
        'delete_action' => 'no action',
        'trigger_reinserted_keys' => $expectedReinserted,
        'parent_keys' => $expectedReinserted,
        'child_keys' => array_column($children, 'parent_key'),
        'violation_count' => 0,
        'restrict_failed_before_trigger_repair' => false,
        'dependencies.0' => 'sqlite-fkey2-after-delete-trigger-can-repair-no-action-fk',
        'dependencies.1' => 'sqlite-fkey2-nocase-parent-key-match',
    ] as $path => $expected) {
        $tests[$case . ' repaired ' . $path] = static function (TestRunner $t) use ($repair, $path, $expected, $value): void {
            $t->same($expected, $value($repair(), (string) $path));
        };
    }

    foreach ([
        'source' => 'fkey2.test fkey2-12.2.1..12.2.4',
        'operation' => 'nocase-parent-delete-trigger-repair',
        'status' => 'constraint-failed',
        'delete_action' => 'restrict',
        'trigger_reinserted_keys' => [],
        'parent_keys' => array_column($parents, 'key'),
        'child_keys' => array_column($children, 'parent_key'),
        'violation_count' => 0,
        'restrict_failed_before_trigger_repair' => true,
        'dependencies.0' => 'sqlite-fkey2-restrict-is-immediate-before-after-trigger-repair',
        'dependencies.1' => 'sqlite-fkey2-nocase-parent-key-match',
    ] as $path => $expected) {
        $tests[$case . ' restrict ' . $path] = static function (TestRunner $t) use ($restrict, $path, $expected, $value): void {
            $t->same($expected, $value($restrict(), (string) $path));
        };
    }

    $tests[$case . ' repaired children keep original case variants'] = static function (TestRunner $t) use ($repair, $children): void {
        $t->same(array_column($children, 'parent_key'), $repair()['child_keys']);
    };
    $tests[$case . ' repaired parent set excludes unreferenced deletes'] = static function (TestRunner $t) use ($repair): void {
        foreach ($repair()['parent_keys'] as $key) {
            $t->same(false, str_starts_with((string) $key, 'Unreferenced'));
        }
    };
    $tests[$case . ' restrict preserves parent set before trigger program'] = static function (TestRunner $t) use ($restrict, $parents): void {
        $t->same(array_column($parents, 'key'), $restrict()['parent_keys']);
    };
}

return $tests;
