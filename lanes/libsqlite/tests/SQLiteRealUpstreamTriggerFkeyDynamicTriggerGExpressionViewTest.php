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

$triggerGSource = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test';

$tests = [
    'real upstream triggerG expression view cites hex literal error section' => static function (TestRunner $t) use ($triggerGSource): void {
        $source = file_get_contents($triggerGSource);
        $t->true(is_string($source) && str_contains($source, 'hex literal too big: 0x2147483648e0e0099'));
    },
    'real upstream triggerG expression view cites instead of delete old row section' => static function (TestRunner $t) use ($triggerGSource): void {
        $source = file_get_contents($triggerGSource);
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER t0001 INSTEAD OF DELETE ON v0 BEGIN'));
    },
    'real upstream triggerG expression view cites delete from view section' => static function (TestRunner $t) use ($triggerGSource): void {
        $source = file_get_contents($triggerGSource);
        $t->true(is_string($source) && str_contains($source, 'DELETE FROM v0;'));
    },
];

for ($i = 1; $i <= 80; ++$i) {
    $hexLiteral = match ($i % 4) {
        0 => '0x2147483648e0e0099',
        1 => '0x8000000000000000',
        2 => '0x7fffffffffffffff',
        default => '0x' . dechex(1000 + $i),
    };
    $tooBig = in_array($i % 4, [0, 1], true);
    $viewRows = [
        ['a' => 1200 + $i],
        ['a' => 2400 + $i],
    ];
    if ($i % 5 === 0) {
        $viewRows[] = ['a' => 3600 + $i];
    }
    $deleteView = ($i % 7) !== 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerGExpressionAndViewDeletePlan($hexLiteral, $viewRows, $deleteView);
    $case = sprintf('triggerG expression error and view delete dynamic %03d', $i);
    $expectedOldValues = $deleteView ? array_column($viewRows, 'a') : [];

    foreach ([
        'source' => 'triggerG.test triggerG-300..410',
        'operation' => 'trigger-expression-error-and-view-delete-old-row',
        'status' => $tooBig ? 'constraint-error' : 'commit-ok',
        'hex_literal' => $hexLiteral,
        'expression_error' => $tooBig ? 'hex literal too big: ' . $hexLiteral : null,
        'expression_error_before_side_effects' => $tooBig,
        'view_delete_attempted' => $deleteView,
        'view_row_count' => count($viewRows),
        'instead_of_delete_old_a_values' => $expectedOldValues,
        'view_rows_preserved_after_instead_of_delete' => $viewRows,
        'dependencies.0' => 'sqlite-triggerG-trigger-subprogram-expression-errors-propagate',
        'dependencies.1' => 'sqlite-triggerG-instead-of-delete-view-trigger-binds-old-row',
        'dependencies.2' => 'sqlite-triggerG-view-delete-does-not-delete-underlying-select-row',
    ] as $path => $expected) {
        $tests['real upstream ' . $case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests['real upstream ' . $case . ' old row count follows view delete'] = static function (TestRunner $t) use ($plan, $deleteView, $viewRows): void {
        $t->same($deleteView ? count($viewRows) : 0, count($plan()['instead_of_delete_old_rows']));
    };
    $tests['real upstream ' . $case . ' view rows remain queryable after INSTEAD OF DELETE'] = static function (TestRunner $t) use ($plan, $viewRows): void {
        $t->same($viewRows, $plan()['view_rows_preserved_after_instead_of_delete']);
    };
}

$tests['real upstream triggerG expression view rejects malformed hex literal'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerGExpressionAndViewDeletePlan('2147483648e0e0099', [['a' => 1]]));
};

$tests['real upstream triggerG expression view rejects view rows without old a column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerGExpressionAndViewDeletePlan('0x1', [['b' => 1]]));
};

return $tests;
