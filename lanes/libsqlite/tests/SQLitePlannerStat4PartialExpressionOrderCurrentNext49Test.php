<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$jsonExpr = static fn (string $function, string $column, string $path): array => ['function' => $function, 'column' => $column, 'path' => $path];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$between = static fn (array $left, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => $left, 'lower' => $lower, 'upper' => $upper];
$inList = static fn (array $left, array $values): array => ['operator' => 'IN', 'left' => $left, 'values' => $values];
$columnPoint = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$columnRange = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$nameSamples = static fn (): array => [
    ['neq' => '2 2 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => ['active_plugins', 'yes', 11]],
    ['neq' => '7 7 2', 'nlt' => '2 2 1', 'ndlt' => '1 1 1', 'sample' => ['plugin_alpha', 'yes', 19]],
    ['neq' => '5 5 3', 'nlt' => '9 9 3', 'ndlt' => '2 2 2', 'sample' => ['plugin_beta', 'yes', 17]],
    ['neq' => '13 13 4', 'nlt' => '14 14 6', 'ndlt' => '3 3 3', 'sample' => ['plugin_gamma', 'yes', 13]],
    ['neq' => '3 3 1', 'nlt' => '27 27 10', 'ndlt' => '4 4 4', 'sample' => ['siteurl', 'yes', 5]],
    ['neq' => '21 21 8', 'nlt' => '30 30 11', 'ndlt' => '5 5 5', 'sample' => ['transient_feed', 'no', 3]],
];

$lengthSamples = static fn (): array => [
    ['neq' => [4, 2], 'nlt' => [0, 0], 'ndlt' => [0, 0], 'sample' => [3, 'yes']],
    ['neq' => [6, 2], 'nlt' => [4, 2], 'ndlt' => [1, 1], 'sample' => [5, 'yes']],
    ['neq' => [12, 4], 'nlt' => [10, 4], 'ndlt' => [2, 2], 'sample' => [8, 'yes']],
    ['neq' => [2, 1], 'nlt' => [22, 8], 'ndlt' => [3, 3], 'sample' => [13, 'no']],
];

$jsonSamples = static fn (): array => [
    ['neq' => 3, 'nlt' => 0, 'ndlt' => 0, 'sample' => ['core']],
    ['neq' => 9, 'nlt' => 3, 'ndlt' => 1, 'sample' => ['plugin']],
    ['neq' => 4, 'nlt' => 12, 'ndlt' => 2, 'sample' => ['theme']],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_lower_plugin_partial_stat4_order',
        'rootPage' => 491,
        'estimatedRows' => 80,
        'stat4Samples' => $nameSamples(),
        'sql' => "CREATE INDEX idx_lower_plugin_partial_stat4_order ON wp_options(lower(option_name), autoload, option_id DESC, option_value) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
    ],
    [
        'name' => 'idx_lower_plugin_partial_fallback',
        'rootPage' => 492,
        'estimatedRows' => 80,
        'sql' => "CREATE INDEX idx_lower_plugin_partial_fallback ON wp_options(lower(option_name), autoload, option_id DESC, option_value) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
    ],
    [
        'name' => 'idx_length_autoload_partial_stat4_order',
        'rootPage' => 493,
        'estimatedRows' => 40,
        'stat4Samples' => $lengthSamples(),
        'sql' => "CREATE INDEX idx_length_autoload_partial_stat4_order ON wp_options(length(option_value), autoload DESC, option_id) WHERE autoload = 'yes' AND length(option_value) >= 5",
    ],
    [
        'name' => 'idx_json_kind_partial_stat4_order',
        'rootPage' => 494,
        'estimatedRows' => 50,
        'stat4Samples' => $jsonSamples(),
        'sql' => "CREATE INDEX idx_json_kind_partial_stat4_order ON wp_options(json_extract(option_value, '$.kind'), option_id DESC, autoload) WHERE autoload = 'yes'",
    ],
];

$lower = $expr('lower', 'option_name');
$length = $expr('length', 'option_value');
$jsonKind = $jsonExpr('json_extract', 'option_value', '$.kind');

$tests = [
    'planner stat4 partial expression order current next49 satisfies expression plus tail order on range' => static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $range($lower, '>=', 'plugin_')), [['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC']], ['autoload', 'option_id']);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner stat4 partial expression order current next49 chooses partial stat4 order plan over fallback' => static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([$indexes()[1], $indexes()[0]], $and($columnPoint('autoload', 'yes'), $range($lower, '<', 'plugin_gamma'), $range($lower, '>=', 'plugin_')), [['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload']], ['autoload']);
        $t->same('idx_lower_plugin_partial_stat4_order', $plans[0]['name']);
    },
    'planner stat4 partial expression order current next49 preserves stat4 estimate for ordered partial range' => static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $range($lower, '>=', 'plugin_')), [['function' => 'lower', 'column' => 'option_name']], ['autoload']);
        $t->same(78, $plan['estimatedRows']);
    },
    'planner stat4 partial expression order current next49 records current next evidence for ordered partial range' => static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $range($lower, '>=', 'plugin_')), [['function' => 'lower', 'column' => 'option_name']], ['autoload']);
        $t->same('plugin_alpha', $plan['stat4CurrentNext'][1]['current']['key']);
        $t->same('plugin_beta', $plan['stat4CurrentNext'][1]['next']['key']);
    },
    'planner stat4 partial expression order current next49 rejects tail direction mismatch after expression order' => static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $range($lower, '>=', 'plugin_')), [['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload'], ['column' => 'option_id']], ['autoload', 'option_id']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner stat4 partial expression order current next49 rejects skipped tail after expression order' => static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $range($lower, '>=', 'plugin_')), [['function' => 'lower', 'column' => 'option_name'], ['column' => 'option_id', 'direction' => 'DESC']], ['autoload', 'option_id']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner stat4 partial expression order current next49 rejects wrong expression order function' => static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $range($lower, '>=', 'plugin_')), [['function' => 'upper', 'column' => 'option_name']], ['autoload']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner stat4 partial expression order current next49 keeps point lookup trailing order compatibility' => static function (TestRunner $t) use ($indexes, $and, $columnPoint, $point, $lower): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $point($lower, 'plugin_alpha')), [['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC']], ['autoload', 'option_id']);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner stat4 partial expression order current next49 proves expression partial before using stat4' => static function (TestRunner $t) use ($indexes, $range, $lower): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($lower, '>=', 'plugin_'), [['function' => 'lower', 'column' => 'option_name']], ['autoload']);
        $t->same(null, $plan);
    },
    'planner stat4 partial expression order current next49 preserves covering tail for ordered range' => static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $range($lower, '>=', 'plugin_')), [['function' => 'lower', 'column' => 'option_name']], ['autoload', 'option_id', 'option_value']);
        $t->same(true, $plan['covering']);
    },
];

$rangeCases = [
    ['>=', 'plugin_', 78],
    ['>', 'plugin_alpha', 71],
    ['>=', 'plugin_beta', 71],
    ['<', 'plugin_gamma', 14],
    ['<=', 'plugin_gamma', 27],
    ['<', 'active_plugins', 1],
    ['<=', 'siteurl', 30],
    ['>', 'siteurl', 50],
];
foreach ($rangeCases as [$operator, $value, $expectedRows]) {
    $tests["planner stat4 partial expression order current next49 lower range {$operator} {$value}"] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower, $operator, $value, $expectedRows): void {
        $terms = [$columnPoint('autoload', 'yes'), $range($lower, $operator, $value)];
        if ($operator === '<' || $operator === '<=') {
            $terms[] = $range($lower, '>=', 'plugin_');
        }
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and(...$terms), [['function' => 'lower', 'column' => 'option_name']], ['autoload']);
        $t->same($expectedRows, $plan['estimatedRows']);
        $t->same(true, $plan['orderBySatisfied']);
    };
}

$betweenCases = [
    ['plugin_alpha', 'plugin_beta', 12],
    ['plugin_alpha', 'plugin_gamma', 25],
    ['plugin_beta', 'plugin_gamma', 18],
    ['plugin_gamma', 'siteurl', 16],
];
foreach ($betweenCases as [$lowerBound, $upperBound, $expectedRows]) {
    $tests["planner stat4 partial expression order current next49 between {$lowerBound} {$upperBound}"] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $between, $lower, $lowerBound, $upperBound, $expectedRows): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $between($lower, $lowerBound, $upperBound)), [['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload']], ['autoload']);
        $t->same($expectedRows, $plan['estimatedRows']);
        $t->same(true, $plan['orderBySatisfied']);
    };
}

$inCases = [
    [['plugin_alpha', 'plugin_beta'], 12],
    [['plugin_alpha', 'plugin_alpha', 'plugin_gamma'], 20],
    [['plugin_gamma', 'siteurl'], 16],
    [['plugin_unknown', 'plugin_beta'], 19],
];
foreach ($inCases as [$values, $expectedRows]) {
    $tests['planner stat4 partial expression order current next49 in list ' . implode('-', $values)] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $inList, $lower, $values, $expectedRows): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $inList($lower, $values)), [['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload']], ['autoload']);
        $t->same($expectedRows, $plan['estimatedRows']);
        $t->same(true, $plan['orderBySatisfied']);
    };
}

$lengthCases = [
    ['>=', 5, 36],
    ['>', 5, 30],
    ['<=', 8, 22],
    ['<', 8, 10],
    ['>=', 13, 18],
    ['>', 13, 16],
];
foreach ($lengthCases as [$operator, $value, $expectedRows]) {
    $tests["planner stat4 partial expression order current next49 length range {$operator} {$value}"] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $length, $operator, $value, $expectedRows): void {
        $terms = [$columnPoint('autoload', 'yes'), $range($length, $operator, $value)];
        if ($operator === '<' || $operator === '<=') {
            $terms[] = $range($length, '>=', 5);
        }
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[2]], $and(...$terms), [['function' => 'length', 'column' => 'option_value'], ['column' => 'autoload', 'direction' => 'DESC']], ['autoload']);
        $t->same($expectedRows, $plan['estimatedRows']);
        $t->same(true, $plan['orderBySatisfied']);
    };
}

$tests['planner stat4 partial expression order current next49 length rejects ascending desc tail'] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $length): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[2]], $and($columnPoint('autoload', 'yes'), $range($length, '>=', 5)), [['function' => 'length', 'column' => 'option_value'], ['column' => 'autoload']], ['autoload']);
    $t->same(false, $plan['orderBySatisfied']);
};

$tests['planner stat4 partial expression order current next49 length reports numeric current next'] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $length): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[2]], $and($columnPoint('autoload', 'yes'), $range($length, '>=', 5)), [['function' => 'length', 'column' => 'option_value']], ['autoload']);
    $t->same(5, $plan['stat4CurrentNext'][1]['current']['key']);
    $t->same(8, $plan['stat4CurrentNext'][1]['next']['key']);
};

$tests['planner stat4 partial expression order current next49 json path expression order is satisfied'] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $jsonKind): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $and($columnPoint('autoload', 'yes'), $range($jsonKind, '>=', 'plugin')), [['function' => 'json_extract', 'column' => 'option_value', 'path' => '$.kind'], ['column' => 'option_id', 'direction' => 'DESC']], ['option_id']);
    $t->same(true, $plan['orderBySatisfied']);
};

$tests['planner stat4 partial expression order current next49 json path mismatch rejects order'] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $jsonKind): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $and($columnPoint('autoload', 'yes'), $range($jsonKind, '>=', 'plugin')), [['function' => 'json_extract', 'column' => 'option_value', 'path' => '$.missing']], ['option_id']);
    $t->same(false, $plan['orderBySatisfied']);
};

$tests['planner stat4 partial expression order current next49 json estimates ordered range'] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $jsonKind): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $and($columnPoint('autoload', 'yes'), $range($jsonKind, '>=', 'plugin')), [['function' => 'json_extract', 'column' => 'option_value', 'path' => '$.kind']], ['option_id']);
    $t->same(47, $plan['estimatedRows']);
};

$validationOrders = [
    'bad direction on expression' => [['function' => 'lower', 'column' => 'option_name', 'direction' => 'SIDEWAYS']],
    'bad tail direction' => [['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload', 'direction' => 'SIDEWAYS']],
    'missing tail column' => [['function' => 'lower', 'column' => 'option_name'], ['direction' => 'DESC']],
];
foreach ($validationOrders as $label => $orderBy) {
    $tests["planner stat4 partial expression order current next49 validates {$label}"] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower, $orderBy): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $range($lower, '>=', 'plugin_')), $orderBy, ['autoload']));
    };
}

$orderCases = [
    'expression only asc' => [[['function' => 'lower', 'column' => 'option_name']], true],
    'expression explicit asc' => [[['function' => 'lower', 'column' => 'option_name', 'direction' => 'ASC']], true],
    'expression desc mismatch' => [[['function' => 'lower', 'column' => 'option_name', 'direction' => 'DESC']], false],
    'expression autoload tail' => [[['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload']], true],
    'expression autoload id desc tail' => [[['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC']], true],
    'expression autoload id value tail' => [[['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC'], ['column' => 'option_value']], true],
    'expression value skipped tail' => [[['function' => 'lower', 'column' => 'option_name'], ['column' => 'option_value']], false],
    'plain source column legacy compatibility' => [[['column' => 'option_name']], true],
    'plain source column with tail rejected' => [[['column' => 'option_name'], ['column' => 'autoload']], false],
];
foreach ($orderCases as $label => [$orderBy, $expected]) {
    $tests["planner stat4 partial expression order current next49 order {$label}"] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower, $orderBy, $expected): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $range($lower, '>=', 'plugin_')), $orderBy, ['autoload', 'option_id', 'option_value']);
        $t->same($expected, $plan['orderBySatisfied']);
    };
}

$neededCases = [
    'autoload id value covered' => [['autoload', 'option_id', 'option_value'], true],
    'autoload only covered' => [['autoload'], true],
    'source option name not covered' => [['option_name'], false],
    'missing blog id not covered' => [['blog_id'], false],
];
foreach ($neededCases as $label => [$neededColumns, $expected]) {
    $tests["planner stat4 partial expression order current next49 covering {$label}"] = static function (TestRunner $t) use ($indexes, $and, $columnPoint, $range, $lower, $neededColumns, $expected): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and($columnPoint('autoload', 'yes'), $range($lower, '>=', 'plugin_')), [['function' => 'lower', 'column' => 'option_name']], $neededColumns);
        $t->same($expected, $plan['covering']);
    };
}

return $tests;
