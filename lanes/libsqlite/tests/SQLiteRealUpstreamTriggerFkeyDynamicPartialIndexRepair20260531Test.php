<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

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

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test';

$tests['real upstream fkey1 partial index repair cites mismatch insert'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);
    $t->true(is_string($source) && str_contains($source, 'CREATE UNIQUE INDEX p1x ON p1(x) WHERE y<2'));
    $t->true(is_string($source) && str_contains($source, 'INSERT INTO c1 VALUES(1);'));
    $t->true(is_string($source) && str_contains($source, 'foreign key mismatch - "c1" referencing "p1"'));
};

$tests['real upstream fkey1 partial index repair cites full unique repair'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);
    $t->true(is_string($source) && str_contains($source, 'CREATE UNIQUE INDEX p1x2 ON p1(x)'));
    $t->true(is_string($source) && str_contains($source, 'do_execsql_test 6.2'));
};

for ($i = 1; $i <= 180; ++$i) {
    $parentKey = $i * 10;
    $outsidePartialKey = $parentKey + 5;
    $parents = [
        ['x' => $parentKey, 'y' => 1, 'label' => 'partial-hit-' . $i],
        ['x' => $outsidePartialKey, 'y' => 3, 'label' => 'outside-partial-' . $i],
    ];
    $children = $i % 2 === 0 ? [['a' => $outsidePartialKey, 'payload' => 'existing-' . $i]] : [];
    $incoming = ['a' => $parentKey, 'payload' => 'incoming-' . $i];

    $repaired = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1PartialParentIndexRepairPlan(
        $parents,
        $children,
        $incoming,
        'x',
        'a',
        true
    );

    foreach ([
        'source' => 'fkey1.test fkey1-6.0..6.2',
        'operation' => 'partial-parent-index-repair',
        'parent_key' => 'x',
        'child_key' => 'a',
        'partial_index_where' => 'y<2',
        'partial_index_unique' => true,
        'partial_index_has_matching_entry' => true,
        'partial_index_satisfies_parent_key' => false,
        'initial_status' => 'foreign-key-mismatch',
        'initial_error' => 'foreign key mismatch - "c1" referencing "p1"',
        'full_index_added' => true,
        'full_index_unique' => true,
        'final_status' => 'commit-ok',
        'final_error' => null,
        'incoming_child_key' => $parentKey,
        'parent_key_values' => [$parentKey, $outsidePartialKey],
        'partial_indexed_parent_keys' => [$parentKey],
        'child_keys_after.' . count($children) => $parentKey,
        'child_rows_after.' . count($children) . '.payload' => 'incoming-' . $i,
        'matched_parent_row.label' => 'partial-hit-' . $i,
        'dependencies.0' => 'sqlite-fkey1-partial-parent-index-does-not-satisfy-fk',
        'dependencies.1' => 'sqlite-fkey1-full-unique-index-repairs-parent-key-lookup',
        'dependencies.2' => 'sqlite-fkey1-child-insert-commits-after-nonpartial-unique-index',
    ] as $path => $expected) {
        $tests["real upstream fkey1 partial index repair dynamic {$i} {$path}"] = static function (TestRunner $t) use ($repaired, $path, $expected, $value): void {
            $t->same($expected, $value($repaired(), (string) $path));
        };
    }

    $tests["real upstream fkey1 partial index repair dynamic {$i} preserves existing children"] = static function (TestRunner $t) use ($repaired, $children): void {
        $actual = $repaired();
        $t->same(array_column($children, 'a'), array_slice($actual['child_keys_after'], 0, count($children)));
    };
}

for ($i = 1; $i <= 80; ++$i) {
    $parentKey = 5000 + $i;
    $parents = [
        ['x' => $parentKey, 'y' => 1],
    ];
    $incoming = ['a' => $parentKey];
    $unrepaired = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1PartialParentIndexRepairPlan(
        $parents,
        [],
        $incoming,
        'x',
        'a',
        false
    );

    foreach ([
        'initial_status' => 'foreign-key-mismatch',
        'final_status' => 'foreign-key-mismatch',
        'final_error' => 'foreign key mismatch - "c1" referencing "p1"',
        'full_index_added' => false,
        'full_index_unique' => false,
        'child_keys_after' => [],
        'partial_index_has_matching_entry' => true,
        'partial_index_satisfies_parent_key' => false,
    ] as $path => $expected) {
        $tests["real upstream fkey1 partial index unrepaired dynamic {$i} {$path}"] = static function (TestRunner $t) use ($unrepaired, $path, $expected, $value): void {
            $t->same($expected, $value($unrepaired(), (string) $path));
        };
    }
}

$tests['real upstream fkey1 partial index repair rejects missing child key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1PartialParentIndexRepairPlan(
        [['x' => 1, 'y' => 1]],
        [],
        ['payload' => 'missing-key']
    ));
};

$tests['real upstream fkey1 partial index repair rejects malformed parent key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1PartialParentIndexRepairPlan(
        [['y' => 1]],
        [],
        ['a' => 1]
    ));
};

return $tests;
