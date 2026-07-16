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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test';

$tests = [
    'real upstream triggerC recursive insert depth cutoff cites source block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('do_test triggerC-2.2', $source);
        $t->contains('CREATE TRIGGER t22a AFTER INSERT ON t22', $source);
        $t->contains('INSERT INTO t22 SELECT x + (SELECT max(x) FROM t22) FROM t22', $source);
        $t->contains('SELECT CASE WHEN (SELECT count(*) FROM t22) >= [expr $SQLITE_MAX_TRIGGER_DEPTH / 2]', $source);
        $t->contains('do_test triggerC-2.3', $source);
        $t->contains('CREATE TRIGGER t23a AFTER INSERT ON t23', $source);
        $t->contains('INSERT INTO t23 VALUES(new.x + 1)', $source);
        $t->contains('SELECT CASE WHEN new.x>[expr $SQLITE_MAX_TRIGGER_DEPTH / 2]', $source);
    },
];

$canonicalSelect = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveInsertDepthCutoffPlan(
    1,
    'insert-select-count-cutoff',
    1000
);
$canonicalLinear = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveInsertDepthCutoffPlan(
    1,
    'linear-primary-key-cutoff',
    1000
);

foreach ([
    'source' => 'triggerC.test triggerC-2.2..2.3',
    'scenario' => 'triggerC-2.2',
    'operation' => 'recursive-insert-select-count-cutoff',
    'table_name' => 't22',
    'after_trigger_name' => 't22a',
    'before_trigger_name' => 't22b',
    'cutoff_value' => 500,
    'final_row_count' => 500,
    'accepted_insert_count' => 500,
    'ignored_attempt_ordinal' => 501,
    'row_count_trace.0' => 0,
    'row_count_trace.500' => 500,
    'row_ordinals.0' => 1,
    'row_ordinals.499' => 500,
    'select_reads_mutating_table' => true,
    'primary_key_enforced' => false,
    'dependencies.3' => 'sqlite-triggerC-2-2-after-insert-select-recurses-from-t22',
    'dependencies.4' => 'sqlite-triggerC-2-2-before-insert-count-cutoff-preserves-final-count',
] as $path => $expected) {
    $tests[sprintf('real upstream triggerC recursive insert select cutoff canonical %s', (string) $path)] = static function (TestRunner $t) use ($canonicalSelect, $value, $path, $expected): void {
        $t->same($expected, $value($canonicalSelect(), (string) $path));
    };
}

foreach ([
    'source' => 'triggerC.test triggerC-2.2..2.3',
    'scenario' => 'triggerC-2.3',
    'operation' => 'recursive-linear-primary-key-cutoff',
    'table_name' => 't23',
    'after_trigger_name' => 't23a',
    'before_trigger_name' => 't23b',
    'cutoff_value' => 500,
    'final_row_count' => 500,
    'accepted_insert_count' => 500,
    'first_row_value' => 1,
    'last_row_value' => 500,
    'ignored_row_value' => 501,
    'row_values.0' => 1,
    'row_values.499' => 500,
    'select_reads_mutating_table' => false,
    'primary_key_enforced' => true,
    'primary_key_conflict_count' => 0,
    'dependencies.3' => 'sqlite-triggerC-2-3-after-insert-linear-recursion',
    'dependencies.4' => 'sqlite-triggerC-2-3-before-insert-new-value-cutoff-preserves-final-count',
    'dependencies.5' => 'sqlite-triggerC-2-3-primary-key-distinct-recursive-rows',
] as $path => $expected) {
    $tests[sprintf('real upstream triggerC recursive linear cutoff canonical %s', (string) $path)] = static function (TestRunner $t) use ($canonicalLinear, $value, $path, $expected): void {
        $t->same($expected, $value($canonicalLinear(), (string) $path));
    };
}

for ($i = 1; $i <= 160; ++$i) {
    $maxDepth = 18 + (($i % 37) * 2);
    $cutoff = intdiv($maxDepth, 2);
    $initialValue = 1 + ($i % 9);

    foreach ([
        'insert-select-count-cutoff' => [
            'scenario' => 'triggerC-2.2',
            'operation' => 'recursive-insert-select-count-cutoff',
            'table_name' => 't22',
            'after_trigger_name' => 't22a',
            'before_trigger_name' => 't22b',
            'cutoff_kind' => 'table-row-count',
            'select_reads_mutating_table' => true,
            'primary_key_enforced' => false,
            'row_count_trace.0' => 0,
            'row_count_trace.' . $cutoff => $cutoff,
            'row_ordinals.0' => 1,
            'row_ordinals.' . ($cutoff - 1) => $cutoff,
            'ignored_attempt_ordinal' => $cutoff + 1,
            'ignored_attempt_reason' => 'row-count-cutoff',
            'dependencies.3' => 'sqlite-triggerC-2-2-after-insert-select-recurses-from-t22',
            'dependencies.4' => 'sqlite-triggerC-2-2-before-insert-count-cutoff-preserves-final-count',
        ],
        'linear-primary-key-cutoff' => [
            'scenario' => 'triggerC-2.3',
            'operation' => 'recursive-linear-primary-key-cutoff',
            'table_name' => 't23',
            'after_trigger_name' => 't23a',
            'before_trigger_name' => 't23b',
            'cutoff_kind' => 'new-row-value',
            'select_reads_mutating_table' => false,
            'primary_key_enforced' => true,
            'row_values.0' => $initialValue,
            'row_values.' . ($cutoff - 1) => $initialValue + $cutoff - 1,
            'first_row_value' => $initialValue,
            'last_row_value' => $initialValue + $cutoff - 1,
            'ignored_row_value' => $initialValue + $cutoff,
            'ignored_attempt_reason' => 'new-value-cutoff',
            'primary_key_conflict_count' => 0,
            'dependencies.3' => 'sqlite-triggerC-2-3-after-insert-linear-recursion',
            'dependencies.4' => 'sqlite-triggerC-2-3-before-insert-new-value-cutoff-preserves-final-count',
            'dependencies.5' => 'sqlite-triggerC-2-3-primary-key-distinct-recursive-rows',
        ],
    ] as $shape => $shapePaths) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveInsertDepthCutoffPlan(
            $initialValue,
            $shape,
            $maxDepth
        );

        $paths = [
            'source' => 'triggerC.test triggerC-2.2..2.3',
            'scenarios.0' => 'triggerC-2.2',
            'scenarios.1' => 'triggerC-2.3',
            'recursive_triggers' => true,
            'max_trigger_depth' => $maxDepth,
            'cutoff_expression' => '$SQLITE_MAX_TRIGGER_DEPTH / 2',
            'cutoff_value' => $cutoff,
            'initial_insert_value' => $initialValue,
            'status' => 'commit-ok',
            'final_row_count' => $cutoff,
            'ignored_insert_count' => 1,
            'accepted_insert_count' => $cutoff,
            'after_trigger_invocation_count' => $cutoff,
            'before_trigger_action' => 'RAISE(IGNORE)',
            'raise_ignore_aborts_current_insert_row' => true,
            'statement_commits_rows_before_ignore' => true,
            'upstream_expected_result.0' => $cutoff,
            'dependencies.0' => 'sqlite-triggerC-recursive-triggers-enabled',
            'dependencies.1' => 'sqlite-triggerC-before-insert-raise-ignore-cuts-off-recursion',
            'dependencies.2' => 'sqlite-triggerC-recursive-insert-count-reaches-half-depth-limit',
            'non_overlap' => 'covers triggerC-2.2..2.3 cutoff-success recursion, not triggerC-2.1 ordering or triggerC-3 recursion-error cases',
        ] + $shapePaths;

        foreach ($paths as $path => $expected) {
            $tests[sprintf('real upstream triggerC recursive insert cutoff dynamic %03d %s %s', $i, $shape, (string) $path)] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

$tests['real upstream triggerC recursive insert cutoff trims uppercase shape'] = static function (TestRunner $t): void {
    $plan = SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveInsertDepthCutoffPlan(1, ' LINEAR-PRIMARY-KEY-CUTOFF ', 12);

    $t->same('triggerC-2.3', $plan['scenario']);
    $t->same(6, $plan['final_row_count']);
};
$tests['real upstream triggerC recursive insert cutoff rejects zero initial value'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveInsertDepthCutoffPlan(0, 'linear-primary-key-cutoff', 12));
$tests['real upstream triggerC recursive insert cutoff rejects shallow depth'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveInsertDepthCutoffPlan(1, 'linear-primary-key-cutoff', 1));
$tests['real upstream triggerC recursive insert cutoff rejects unknown shape'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveInsertDepthCutoffPlan(1, 'unsupported-shape', 12));

return $tests;
