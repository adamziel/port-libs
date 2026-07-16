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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test';

$tests = [
    'real upstream fkey1 quoted cascade 20260531 cites simple quoted identifiers' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test fkey1-4.0'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE "xx4"("xx5" TEXT REFERENCES "xx1" ON DELETE CASCADE);'));
        $t->true(is_string($source) && str_contains($source, 'DELETE FROM "xx1"'));
    },
    'real upstream fkey1 quoted cascade 20260531 cites doubled quote identifiers' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test fkey1-4.1'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE """4"("""5" TEXT REFERENCES """1" ON DELETE CASCADE);'));
        $t->true(is_string($source) && str_contains($source, 'DELETE FROM """1"'));
    },
    'real upstream fkey1 quoted cascade 20260531 cites partial parent mismatch section' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_catchsql_test fkey1-5.2'));
        $t->true(is_string($source) && str_contains($source, 'INSERT OR REPLACE INTO t11 VALUES (2, 3);'));
    },
];

$quotedPairs = [
    ['"setting key"', '"setting ref"', 'setting key', 'setting ref'],
    ['"""tenant key"', '"""tenant ref"', '"tenant key', '"tenant ref'],
    ['[load policy]', '[load ref]', 'load policy', 'load ref'],
    ['`group key`', '`group ref`', 'group key', 'group ref'],
    ['"mixed Case"', '[mixed Ref]', 'mixed Case', 'mixed Ref'],
];

for ($i = 1; $i <= 120; ++$i) {
    [$parentIdentifier, $childIdentifier, $parentKey, $childKey] = $quotedPairs[$i % count($quotedPairs)];
    $matchedKey = 'tenant-' . $i;
    $unmatchedKey = 'orphan-' . $i;
    $parents = [
        [$parentKey => $matchedKey, 'payload' => 'parent-' . $i],
        [$parentKey => $unmatchedKey, 'payload' => 'unreferenced-' . $i],
    ];
    $children = [
        [$childKey => $matchedKey, 'label' => 'child-' . $i . '-a'],
        [$childKey => $matchedKey, 'label' => 'child-' . $i . '-b'],
        [$childKey => null, 'label' => 'child-' . $i . '-null'],
    ];

    $cascade = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1QuotedCascadeReplacePlan(
        $parents,
        $children,
        $parentIdentifier,
        $childIdentifier,
        'cascade'
    );
    $restrict = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1QuotedCascadeReplacePlan(
        $parents,
        $children,
        $parentIdentifier,
        $childIdentifier,
        'restrict'
    );
    $noAction = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1QuotedCascadeReplacePlan(
        $parents,
        $children,
        $parentIdentifier,
        $childIdentifier,
        'no action'
    );
    $partial = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1QuotedCascadeReplacePlan(
        $parents,
        $children,
        $parentIdentifier,
        $childIdentifier,
        'cascade',
        true
    );

    foreach ([
        'source' => 'fkey1.test fkey1-4.0..9.1',
        'operation' => 'quoted-identifier-fkey-cascade-replace',
        'status' => 'commit-ok',
        'error' => null,
        'parent_key' => $parentKey,
        'child_key' => $childKey,
        'action' => 'cascade',
        'partial_parent_index' => false,
        'quoted_identifier_dequoted_once' => true,
        'initial_parent_keys.0' => $matchedKey,
        'initial_parent_keys.1' => $unmatchedKey,
        'initial_child_keys.0' => $matchedKey,
        'initial_child_keys.1' => $matchedKey,
        'initial_child_keys.2' => null,
        'remaining_parent_count' => 0,
        'remaining_child_keys.0' => null,
        'trace_statement_count' => 1,
        'dependencies.0' => 'sqlite-fkey1-quoted-identifiers-dequote-once',
        'dependencies.1' => 'sqlite-fkey1-on-delete-cascade-removes-children',
    ] as $path => $expected) {
        $tests["fkey1 quoted cascade dynamic {$i} {$path}"] = static function (TestRunner $t) use ($cascade, $value, $path, $expected): void {
            $t->same($expected, $value($cascade(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-failed',
        'error' => 'FOREIGN KEY constraint failed',
        'action' => 'restrict',
        'remaining_parent_count' => 2,
        'remaining_child_keys.0' => $matchedKey,
        'remaining_child_keys.1' => $matchedKey,
        'remaining_child_keys.2' => null,
        'trace_statement_count' => 0,
        'dependencies.3' => 'sqlite-fkey1-restrict-fails-before-delete',
    ] as $path => $expected) {
        $tests["fkey1 quoted restrict dynamic {$i} {$path}"] = static function (TestRunner $t) use ($restrict, $value, $path, $expected): void {
            $t->same($expected, $value($restrict(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'error' => null,
        'action' => 'no action',
        'remaining_parent_count' => 0,
        'remaining_child_keys.0' => $matchedKey,
        'remaining_child_keys.1' => $matchedKey,
        'remaining_child_keys.2' => null,
        'trace_statement_count' => 0,
    ] as $path => $expected) {
        $tests["fkey1 quoted no action dynamic {$i} {$path}"] = static function (TestRunner $t) use ($noAction, $value, $path, $expected): void {
            $t->same($expected, $value($noAction(), (string) $path));
        };
    }

    foreach ([
        'status' => 'foreign-key-mismatch',
        'error' => 'foreign key mismatch',
        'partial_parent_index' => true,
        'remaining_parent_count' => 0,
        'remaining_child_keys.0' => $matchedKey,
        'remaining_child_keys.1' => $matchedKey,
        'remaining_child_keys.2' => null,
        'dependencies.2' => 'sqlite-fkey1-partial-parent-index-does-not-satisfy-fk',
    ] as $path => $expected) {
        $tests["fkey1 quoted partial parent mismatch dynamic {$i} {$path}"] = static function (TestRunner $t) use ($partial, $value, $path, $expected): void {
            $t->same($expected, $value($partial(), (string) $path));
        };
    }
}

$tests['fkey1 quoted cascade 20260531 rejects unsupported action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey1QuotedCascadeReplacePlan(
        [['setting key' => 'a']],
        [['setting ref' => 'a']],
        '"setting key"',
        '"setting ref"',
        'set null'
    ));
};

$tests['fkey1 quoted cascade 20260531 rejects nul parent identifier'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey1QuotedCascadeReplacePlan(
        [['setting key' => 'a']],
        [['setting ref' => 'a']],
        "setting\0key",
        '"setting ref"',
        'cascade'
    ));
};

$tests['fkey1 quoted cascade 20260531 rejects missing child column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey1QuotedCascadeReplacePlan(
        [['setting key' => 'a']],
        [['missing ref' => 'a']],
        '"setting key"',
        '"setting ref"',
        'cascade'
    ));
};

return $tests;
