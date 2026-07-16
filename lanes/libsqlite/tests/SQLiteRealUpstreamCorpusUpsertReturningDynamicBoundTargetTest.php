<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertConflictTargetExpressionPlan;

$tests = [];

$uniqueExpressions = ['b+3'];

$acceptedTargets = [
    'b+3',
    'b + 3',
    '(b+3)',
    '((b + 3))',
    'B + 3',
];

foreach ($acceptedTargets as $target) {
    $tests["real upstream upsert1 1200 literal expression target accepts {$target}"] = static function (TestRunner $t) use ($uniqueExpressions, $target): void {
        $plan = SQLiteUpsertConflictTargetExpressionPlan::analyze($uniqueExpressions, $target);
        $t->same(true, $plan['valid']);
        $t->same(null, $plan['error']);
        $t->same('b+3', $plan['matched_unique_expression']);
    };
}

for ($case = 1; $case <= 1000; ++$case) {
    $parameter = '?';
    if ($case % 3 === 1) {
        $parameter .= (string) $case;
    } elseif ($case % 3 === 2) {
        $parameter .= 'p' . $case;
    }

    $target = match ($case % 4) {
        0 => 'b+' . $parameter,
        1 => 'b + ' . $parameter,
        2 => '(b+' . $parameter . ')',
        default => '((b + ' . $parameter . '))',
    };

    $tests[sprintf('real upstream upsert1 1210 bound conflict target rejected dynamic %04d', $case)] = static function (TestRunner $t) use ($uniqueExpressions, $target): void {
        $plan = SQLiteUpsertConflictTargetExpressionPlan::analyze($uniqueExpressions, $target);

        $t->same(false, $plan['valid']);
        $t->same('ON CONFLICT clause does not match any PRIMARY KEY or UNIQUE constraint', $plan['error']);
        $t->same(null, $plan['matched_unique_expression']);
    };
}

for ($case = 1; $case <= 250; ++$case) {
    $literal = $case + 3;
    $target = 'b+' . $literal;

    $tests[sprintf('real upstream upsert1 1210 different literal expression rejected dynamic %04d', $case)] = static function (TestRunner $t) use ($uniqueExpressions, $target): void {
        $plan = SQLiteUpsertConflictTargetExpressionPlan::analyze($uniqueExpressions, $target);

        $t->same(false, $plan['valid']);
        $t->same('ON CONFLICT clause does not match any PRIMARY KEY or UNIQUE constraint', $plan['error']);
    };
}

$tests['real upstream upsert1 1200 cites exact Tcl sections'] = static function (TestRunner $t) use ($uniqueExpressions): void {
    $plan = SQLiteUpsertConflictTargetExpressionPlan::analyze($uniqueExpressions, 'b+3');

    $t->same([
        'upsert1.test-1200',
        'upsert1.test-1210',
        'sqlite-upsert-conflict-target-expression-identity',
        'sqlite-upsert-bound-parameter-conflict-target-rejection',
    ], $plan['dependencies']);
};

$tests['real upstream upsert1 1200 rejects malformed unique expression inventory'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertConflictTargetExpressionPlan::analyze([], 'b+3'));
};

$tests['real upstream upsert1 1200 rejects unsupported expression grammar'] = static function (TestRunner $t) use ($uniqueExpressions): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertConflictTargetExpressionPlan::analyze($uniqueExpressions, "json_extract(b,'$.x')"));
};

return $tests;
