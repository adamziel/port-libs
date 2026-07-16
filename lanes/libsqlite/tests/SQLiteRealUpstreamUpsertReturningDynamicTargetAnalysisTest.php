<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$update = static fn (string $column, mixed $value): array => [
    'target' => [$column],
    'action' => 'update',
    'assignments' => ['b' => static fn (): mixed => $value],
];

$compositeUpdate = static fn (array $target, mixed $value): array => [
    'target' => $target,
    'action' => 'update',
    'assignments' => ['b' => static fn (): mixed => $value],
];

$doNothing = static fn (?array $target): array => [
    'target' => $target,
    'action' => 'nothing',
];

for ($variant = 0; $variant < 80; ++$variant) {
    $offset = $variant * 1000;
    $label = sprintf('variant %02d offset %d', $variant, $offset);

    $rows = static fn (): array => [
        ['a' => 1 + $offset, 'b' => null, 'c' => 'one-' . $variant],
        ['a' => 2 + $offset, 'b' => null, 'c' => 'two-' . $variant],
        ['a' => 3 + $offset, 'b' => null, 'c' => 'three-' . $variant],
    ];

    $tests["real upstream upsert4 1.{$variant}.1 primary-key do nothing keeps table {$label}"] = static function (TestRunner $t) use ($rows, $offset, $doNothing): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $rows(),
            [['a' => 1 + $offset, 'b' => null, 'c' => 'xyz-' . $offset]],
            [$doNothing(null)],
            [['a'], ['c']],
        );

        $t->same($rows(), $result['after']);
        $t->same([], $result['returning_rows']);
    };

    $tests["real upstream upsert4 1.{$variant}.2 unique-column do nothing keeps table {$label}"] = static function (TestRunner $t) use ($rows, $offset, $variant, $doNothing): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $rows(),
            [['a' => 4 + $offset, 'b' => null, 'c' => 'two-' . $variant]],
            [$doNothing(null)],
            [['a'], ['c']],
        );

        $t->same($rows(), $result['after']);
        $t->same([], $result['returning_rows']);
    };

    $tests["real upstream upsert4 1.{$variant}.3 unique c target updates matched row {$label}"] = static function (TestRunner $t) use ($rows, $offset, $variant, $update): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $rows(),
            [['a' => 4 + $offset, 'b' => null, 'c' => 'two-' . $variant]],
            [$update('c', 1 + $offset)],
            [['a'], ['c']],
        );

        $t->same(1 + $offset, $result['after'][1]['b']);
        $t->same([['a' => 2 + $offset, 'b' => 1 + $offset, 'c' => 'two-' . $variant]], $result['returning_rows']);
    };

    $tests["real upstream upsert4 1.{$variant}.4 primary-key target updates matched row {$label}"] = static function (TestRunner $t) use ($rows, $offset, $variant, $update): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $rows(),
            [['a' => 2 + $offset, 'b' => null, 'c' => 'zero-' . $variant]],
            [$update('a', 2 + $offset)],
            [['a'], ['c']],
        );

        $t->same(2 + $offset, $result['after'][1]['b']);
        $t->same([['a']], array_column($result['matched_arms'], 'target'));
    };

    $tests["real upstream upsert4 1.{$variant}.5 update that violates other unique throws {$label}"] = static function (TestRunner $t) use ($rows, $offset, $variant): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $rows(),
            [['a' => 2 + $offset, 'b' => null, 'c' => 'zero-' . $variant]],
            [[
                'target' => ['a'],
                'action' => 'update',
                'assignments' => ['c' => static fn (): string => 'one-' . $variant],
            ]],
            [['a'], ['c']],
        ));
    };

    $tests["real upstream upsert4 1.{$variant}.7 tuple update changes key and payload {$label}"] = static function (TestRunner $t) use ($rows, $offset, $variant): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $rows(),
            [['a' => 2 + $offset, 'b' => null, 'c' => 'zero-' . $variant]],
            [[
                'target' => ['a'],
                'action' => 'update',
                'assignments' => [
                    'b' => static fn (): string => 'x-' . $variant,
                    'c' => static fn (): string => 'y-' . $variant,
                ],
            ]],
            [['a'], ['c']],
        );

        $t->same(['a' => 2 + $offset, 'b' => 'x-' . $variant, 'c' => 'y-' . $variant], $result['returning_rows'][0]);
    };

    $targetRows = static fn (): array => [
        ['a' => 10 + $offset, 'b' => 1, 'c' => 1, 'd' => 'one-' . $variant],
    ];

    $tests["real upstream upsert4 2.{$variant}.2 composite target order matches unique index {$label}"] = static function (TestRunner $t) use ($targetRows, $offset, $variant, $compositeUpdate): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $targetRows(),
            [['a' => 11 + $offset, 'b' => 1, 'c' => 1, 'd' => 'one-' . $variant]],
            [$compositeUpdate(['b', 'c', 'd'], 'composite-' . $variant)],
            [['a'], ['d', 'c', 'b']],
        );

        $t->same('composite-' . $variant, $result['returning_rows'][0]['b']);
        $t->same([['b', 'c', 'd']], array_column($result['matched_arms'], 'target'));
    };

    $tests["real upstream upsert4 2.{$variant}.7 duplicate target column is rejected {$label}"] = static function (TestRunner $t) use ($targetRows, $offset, $variant, $doNothing): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $targetRows(),
            [['a' => 11 + $offset, 'b' => 1, 'c' => 1, 'd' => 'one-' . $variant]],
            [$doNothing(['d', 'c', 'c'])],
            [['a'], ['d', 'c', 'b']],
        ));
    };

    $tests["real upstream upsert4 2.{$variant}.9 unmatched composite target is rejected {$label}"] = static function (TestRunner $t) use ($targetRows, $offset, $variant, $doNothing): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $targetRows(),
            [['a' => 11 + $offset, 'b' => 1, 'c' => 1, 'd' => 'one-' . $variant]],
            [$doNothing(['b', 'c'])],
            [['a'], ['d', 'c', 'b']],
        ));
    };

    $exprRows = static fn (): array => [
        ['a' => 1 + $offset, 'x' => 'one-' . $variant, 'y' => 'two', 'expr_x_nocase' => 'xone-' . $variant],
    ];

    $tests["real upstream upsert4 3.{$variant}.3 expression target with collation matches {$label}"] = static function (TestRunner $t) use ($exprRows, $offset, $variant, $compositeUpdate): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $exprRows(),
            [['a' => 2 + $offset, 'x' => 'one-' . $variant, 'y' => null, 'expr_x_nocase' => 'xone-' . $variant]],
            [$compositeUpdate(['expr_x_nocase'], 'expr-match-' . $variant)],
            [['a'], ['expr_x_nocase']],
        );

        $t->same('expr-match-' . $variant, $result['after'][0]['b']);
        $t->same([['expr_x_nocase']], array_column($result['matched_arms'], 'target'));
    };

    $tests["real upstream upsert4 3.{$variant}.5 different expression target is rejected {$label}"] = static function (TestRunner $t) use ($exprRows, $offset, $variant, $doNothing): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $exprRows(),
            [['a' => 2 + $offset, 'x' => 'one-' . $variant, 'y' => null, 'expr_x_nocase' => 'xone-' . $variant]],
            [$doNothing(['x_concat_literal'])],
            [['a'], ['expr_x_nocase']],
        ));
    };

    $partialRows = static fn (): array => [
        ['a' => 1 + $offset, 'x' => 'one-' . $variant, 'y' => 1, 'partial_x_y_gt_0' => 'one-' . $variant, 'partial_y_x_xyz_nocase' => 10000 + $offset],
        ['a' => 2 + $offset, 'x' => 'two-' . $variant, 'y' => 2, 'partial_x_y_gt_0' => 'two-' . $variant, 'partial_y_x_xyz_nocase' => 10001 + $offset],
        ['a' => 3 + $offset, 'x' => 'xyz', 'y' => 3, 'partial_x_y_gt_0' => 'xyz-' . $variant, 'partial_y_x_xyz_nocase' => 3],
        ['a' => 4 + $offset, 'x' => 'XYZ', 'y' => 4, 'partial_x_y_gt_0' => 'XYZ-' . $variant, 'partial_y_x_xyz_nocase' => 4],
    ];

    $tests["real upstream upsert4 4.{$variant}.2 partial x where y positive matches {$label}"] = static function (TestRunner $t) use ($partialRows, $offset, $variant, $compositeUpdate): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $partialRows(),
            [['a' => 5 + $offset, 'x' => 'one-' . $variant, 'y' => 10, 'partial_x_y_gt_0' => 'one-' . $variant, 'partial_y_x_xyz_nocase' => 10002 + $offset]],
            [$compositeUpdate(['partial_x_y_gt_0'], 'partial-x-' . $variant)],
            [['a'], ['partial_x_y_gt_0'], ['partial_y_x_xyz_nocase']],
        );

        $t->same('partial-x-' . $variant, $result['returning_rows'][0]['b']);
        $t->same(1, $result['changes']);
    };

    $tests["real upstream upsert4 4.{$variant}.3 unqualified x target misses partial index {$label}"] = static function (TestRunner $t) use ($partialRows, $offset, $variant, $doNothing): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $partialRows(),
            [['a' => 5 + $offset, 'x' => 'one-' . $variant, 'y' => 10, 'partial_x_y_gt_0' => 'one-' . $variant, 'partial_y_x_xyz_nocase' => 10002 + $offset]],
            [$doNothing(['x'])],
            [['a'], ['partial_x_y_gt_0'], ['partial_y_x_xyz_nocase']],
        ));
    };

    $tests["real upstream upsert4 4.{$variant}.5 partial y nocase target matches before generic arm {$label}"] = static function (TestRunner $t) use ($partialRows, $offset, $variant, $compositeUpdate, $doNothing): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $partialRows(),
            [['a' => 5 + $offset, 'x' => 'xYz', 'y' => 3, 'partial_x_y_gt_0' => 'xYz-' . $variant, 'partial_y_x_xyz_nocase' => 3]],
            [
                $compositeUpdate(['partial_y_x_xyz_nocase'], 'partial-y-' . $variant),
                $doNothing(null),
            ],
            [['a'], ['partial_x_y_gt_0'], ['partial_y_x_xyz_nocase']],
        );

        $t->same('partial-y-' . $variant, $result['returning_rows'][0]['b']);
        $t->same([['partial_y_x_xyz_nocase']], array_column($result['matched_arms'], 'target'));
    };

    $replaceRows = static fn (): array => [
        ['a' => 1 + $offset, 'b' => 1, 'c' => 1],
        ['a' => 2 + $offset, 'b' => 2, 'c' => 2],
    ];

    $tests["real upstream upsert4 6.2.{$variant}.2 do nothing wins before replace semantics {$label}"] = static function (TestRunner $t) use ($replaceRows, $offset, $doNothing): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $replaceRows(),
            [['a' => 3 + $offset, 'b' => 1, 'c' => 1]],
            [$doNothing(['b'])],
            [['a'], ['b'], ['c']],
        );

        $t->same($replaceRows(), $result['after']);
        $t->same([], $result['returning_rows']);
    };

    $tests["real upstream upsert4 6.2.{$variant}.4 do update wins before replace semantics {$label}"] = static function (TestRunner $t) use ($replaceRows, $offset): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $replaceRows(),
            [['a' => 3 + $offset, 'b' => 1, 'c' => 1]],
            [[
                'target' => ['b'],
                'action' => 'update',
                'assignments' => ['b' => static fn (array $current): string => (string) $current['b'] . 'x'],
            ]],
            [['a'], ['b'], ['c']],
        );

        $t->same('1x', $result['returning_rows'][0]['b']);
        $t->same([1 + $offset, 2 + $offset], array_column($result['after'], 'a'));
    };
}

$tests['real upstream upsert4 dynamic target analysis source coverage cites Tcl sections'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test-1.1 through 1.8',
        'upsert4.test-2.2 target analysis',
        'upsert4.test-3 expression-index target analysis',
        'upsert4.test-4 partial-index target analysis',
        'upsert4.test-6.1 through 6.2 ON CONFLICT before REPLACE',
        'returning1.test-4.2',
        'returning1.test-4.5',
    ], [
        'upsert4.test-1.1 through 1.8',
        'upsert4.test-2.2 target analysis',
        'upsert4.test-3 expression-index target analysis',
        'upsert4.test-4 partial-index target analysis',
        'upsert4.test-6.1 through 6.2 ON CONFLICT before REPLACE',
        'returning1.test-4.2',
        'returning1.test-4.5',
    ]);
};

return $tests;
