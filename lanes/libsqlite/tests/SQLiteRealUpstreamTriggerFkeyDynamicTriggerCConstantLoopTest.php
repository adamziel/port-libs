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
    'real upstream triggerC constant loop cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');

        $t->true(is_string($source));
        $t->contains('triggerC-14.1', $source);
        $t->contains('triggerC-14.2', $source);
        $t->contains('not factor constants out of loops within trigger programs', $source);
    },
    'real upstream triggerC quoted target cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');

        $t->true(is_string($source));
        $t->contains('Check that table names used by trigger programs are', $source);
        $t->contains('dequoted exactly', $source);
        $t->contains('CREATE TRIGGER node_delete_referencing AFTER DELETE ON "node"', $source);
        $t->contains('CREATE TRIGGER x1ai AFTER INSERT ON x1', $source);
    },
    'real upstream fkey6 defer pragma cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');

        $t->true(is_string($source));
        $t->contains('fkey6-1.10.1', $source);
        $t->contains('Test that defer_foreign_keys disables RESTRICT', $source);
        $t->contains('CREATE TRIGGER p2t AFTER DELETE ON p2', $source);
    },
];

for ($i = 1; $i <= 260; ++$i) {
    $sourceRows = [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 2, 'b' => 4 + ($i % 3), 'c' => 3],
        ['a' => 9, 'b' => 2, 'c' => 4],
        ['a' => 10, 'b' => 2, 'c' => 3 + ($i % 2)],
    ];
    $emptyValues = $i % 11 === 0 ? [1] : [];
    $nonEmptyValues = [2, 4 + ($i % 3)];
    $lookupConstant = 1234567 + ($i % 5);
    $lookupRows = [
        ['e' => $lookupConstant, 'f' => 3],
        ['e' => $lookupConstant + 1, 'f' => 4],
    ];

    $expected = [];
    $lookupMatches = [3];
    foreach ($sourceRows as $row) {
        $left = in_array($row['a'], $emptyValues, true) || in_array($row['b'], $nonEmptyValues, true);
        $right = in_array($row['c'], $lookupMatches, true);
        if ($left && $right) {
            $expected[] = ['g' => $row['a'], 'h' => $row['b'], 'i' => $row['c']];
        }
    }

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramConstantLoopPlan(
        $sourceRows,
        $emptyValues,
        $nonEmptyValues,
        $lookupRows,
        $lookupConstant
    );

    foreach ([
        'source' => 'triggerC.test triggerC-14.1..14.2',
        'operation' => 'trigger-program-constant-loop-evaluation',
        'status' => 'commit-ok',
        'lookup_constant' => $lookupConstant,
        'source_row_count' => count($sourceRows),
        'visited_source_rows' => count($sourceRows),
        'empty_values' => $emptyValues,
        'non_empty_values' => $nonEmptyValues,
        'lookup_matches' => $lookupMatches,
        'inserted_rows' => $expected,
        'inserted_count' => count($expected),
        'constant_factored_out_of_trigger_loop' => false,
        'dependencies.0' => 'sqlite-triggerC-trigger-program-constants-stay-inside-loop',
        'dependencies.2' => 'sqlite-triggerC-factor-constants-optimization-does-not-change-trigger-result',
    ] as $path => $expectedValue) {
        $tests[sprintf('real upstream triggerC constant loop dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($plan, $value, $path, $expectedValue): void {
            $t->same($expectedValue, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 180; ++$i) {
    $nodes = [
        ['id' => 9, 'pid' => 0, 'key' => 'root'],
        ['id' => 90, 'pid' => 9, 'key' => 'child'],
        ['id' => 900, 'pid' => 90, 'key' => 'grandchild'],
        ['id' => 1000 + $i, 'pid' => 0, 'key' => 'peer-' . $i],
    ];
    $deleteIds = $i % 2 === 0 ? [9] : [90];
    $expectedDeleted = $i % 2 === 0 ? [9, 90, 900] : [90, 900];
    $expectedRemaining = $i % 2 === 0 ? [1000 + $i] : [9, 1000 + $i];
    sort($expectedRemaining);

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::quotedTriggerTargetCascadePlan($nodes, $deleteIds);

    foreach ([
        'source' => 'triggerC.test triggerC-15.1.1..15.2.3',
        'operation' => 'quoted-trigger-target-dequote-once',
        'status' => 'commit-ok',
        'trigger_target' => '"node"',
        'resolved_trigger_target' => 'node',
        'dequote_count' => 1,
        'deleted_ids' => $expectedDeleted,
        'remaining_ids' => $expectedRemaining,
        'remaining_row_count' => count($expectedRemaining),
        'dependencies.0' => 'sqlite-triggerC-quoted-trigger-table-name-dequoted-exactly-once',
    ] as $path => $expectedValue) {
        $tests[sprintf('real upstream triggerC quoted target dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($plan, $value, $path, $expectedValue): void {
            $t->same($expectedValue, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 180; ++$i) {
    $parents = [
        ['id' => 1, 'label' => 'one'],
        ['id' => 2, 'label' => 'two'],
    ];
    $children = [
        ['id' => 'ref-' . $i, 'parent_id' => 1],
    ];
    $defer = ($i % 3) !== 0;
    $newId = $i % 2 === 0 ? 0 : 3;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysUpdateCommit($parents, $children, 1, $newId, $defer);
    $expectedStatus = !$defer ? 'constraint-failed' : ($newId === 0 ? 'commit-failed' : 'commit-failed');

    foreach ([
        'source' => 'fkey6.test fkey6-3.2.1..3.2.6',
        'operation' => 'defer-foreign-keys-restrict-update-commit-check',
        'status' => $expectedStatus,
        'defer_foreign_keys' => $defer,
        'pragma_after_boundary' => 0,
        'old_parent_key' => 1,
        'new_parent_key' => $newId,
        'initial_violation_count' => 1,
        'commit_violation_count' => 1,
        'child_parent_ids.0' => 1,
        'dependencies.0' => $defer
            ? 'sqlite-fkey6-defer-foreign-keys-disables-restrict-until-commit'
            : 'sqlite-fkey6-restrict-update-is-immediate-without-defer-foreign-keys',
    ] as $path => $expectedValue) {
        $tests[sprintf('real upstream fkey6 deferred restrict update dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($plan, $value, $path, $expectedValue): void {
            $t->same($expectedValue, $value($plan(), (string) $path));
        };
    }
}

return $tests;
