<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$jsonExpr = static fn (string $function, string $column, string $path): array => ['function' => $function, 'column' => $column, 'path' => $path];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$columnPoint = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$or = static fn (array ...$terms): array => ['operator' => 'OR', 'terms' => $terms];

$lower = $expr('lower', 'option_name');
$length = $expr('length', 'option_value');
$jsonKind = $jsonExpr('json_extract', 'option_value', '$.kind');

$nameSamples = static fn (): array => [
    ['neq' => '2 2 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => ['active_plugins', 'yes', 11]],
    ['neq' => '7 7 2', 'nlt' => '2 2 1', 'ndlt' => '1 1 1', 'sample' => ['plugin_alpha', 'yes', 19]],
    ['neq' => '5 5 3', 'nlt' => '9 9 3', 'ndlt' => '2 2 2', 'sample' => ['plugin_beta', 'yes', 17]],
    ['neq' => '13 13 4', 'nlt' => '14 14 6', 'ndlt' => '3 3 3', 'sample' => ['plugin_gamma', 'yes', 13]],
    ['neq' => '3 3 1', 'nlt' => '27 27 10', 'ndlt' => '4 4 4', 'sample' => ['siteurl', 'yes', 5]],
];
$lengthSamples = static fn (): array => [
    ['neq' => [4, 2], 'nlt' => [0, 0], 'ndlt' => [0, 0], 'sample' => [5, 'yes']],
    ['neq' => [9, 4], 'nlt' => [4, 2], 'ndlt' => [1, 1], 'sample' => [8, 'yes']],
    ['neq' => [6, 2], 'nlt' => [13, 6], 'ndlt' => [2, 2], 'sample' => [12, 'yes']],
];
$jsonSamples = static fn (): array => [
    ['neq' => 3, 'nlt' => 0, 'ndlt' => 0, 'sample' => ['core']],
    ['neq' => 9, 'nlt' => 3, 'ndlt' => 1, 'sample' => ['plugin']],
    ['neq' => 4, 'nlt' => 12, 'ndlt' => 2, 'sample' => ['theme']],
];
$indexes = static fn (): array => [
    [
        'name' => 'idx_lower_plugin_partial_stat4_or',
        'rootPage' => 531,
        'estimatedRows' => 90,
        'coveringColumns' => ['autoload', 'option_id', 'option_value'],
        'stat4Samples' => $nameSamples(),
        'sql' => "CREATE INDEX idx_lower_plugin_partial_stat4_or ON wp_options(lower(option_name), autoload, option_id DESC, option_value) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
    ],
    [
        'name' => 'idx_length_plugin_partial_stat4_or',
        'rootPage' => 532,
        'estimatedRows' => 60,
        'coveringColumns' => ['autoload', 'option_id', 'option_value'],
        'stat4Samples' => $lengthSamples(),
        'sql' => "CREATE INDEX idx_length_plugin_partial_stat4_or ON wp_options(length(option_value), autoload, option_id) WHERE autoload = 'yes' AND length(option_value) >= 5",
    ],
    [
        'name' => 'idx_json_kind_partial_stat4_or',
        'rootPage' => 533,
        'estimatedRows' => 70,
        'coveringColumns' => ['autoload', 'option_id', 'option_value'],
        'stat4Samples' => $jsonSamples(),
        'sql' => "CREATE INDEX idx_json_kind_partial_stat4_or ON wp_options(json_extract(option_value, '$.kind'), autoload, option_id) WHERE autoload = 'yes'",
    ],
];

$nameArm = static fn (string $name): array => $and($columnPoint('autoload', 'yes'), $point($lower, $name));
$nameRangeArm = static function (string $operator, string $value) use ($and, $columnPoint, $range, $lower): array {
    $terms = [$columnPoint('autoload', 'yes'), $range($lower, $operator, $value)];
    if ($operator === '<' || $operator === '<=') {
        $terms[] = $range($lower, '>=', 'plugin_');
    }

    return $and(...$terms);
};
$lengthArm = static fn (int $lengthValue): array => $and($columnPoint('autoload', 'yes'), $point($length, $lengthValue));
$jsonArm = static fn (string $kind): array => $and($columnPoint('autoload', 'yes'), $point($jsonKind, $kind));
$plan = static fn (array $predicate): ?array => SQLiteSelectExpressionIndexPlan::partialCoveringOrPlan(
    $indexes(),
    $predicate,
    [['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload']],
    ['autoload', 'option_id'],
);

$tests = [
    'planner stat4 or partial expression current next53 rewrites same index points to in strategy' => static function (TestRunner $t) use ($plan, $or, $nameArm): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta'), $nameArm('plugin_alpha')));
        $t->same('or-to-in-partial-expression', $p['strategy']);
    },
    'planner stat4 or partial expression current next53 deduplicates in rewrite values' => static function (TestRunner $t) use ($plan, $or, $nameArm): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta'), $nameArm('plugin_alpha')));
        $t->same(['plugin_alpha', 'plugin_beta'], $p['inRewrite']['values']);
    },
    'planner stat4 or partial expression current next53 uses stat4 rows after in rewrite' => static function (TestRunner $t) use ($plan, $or, $nameArm): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta'), $nameArm('plugin_alpha')));
        $t->same(12, $p['estimatedRows']);
    },
    'planner stat4 or partial expression current next53 keeps original arm count' => static function (TestRunner $t) use ($plan, $or, $nameArm): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta'), $nameArm('plugin_alpha')));
        $t->same(3, $p['armCount']);
    },
    'planner stat4 or partial expression current next53 marks global stat4 used' => static function (TestRunner $t) use ($plan, $or, $nameArm): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta'), $nameArm('plugin_alpha')));
        $t->same(true, $p['stat4Used']);
    },
    'planner stat4 or partial expression current next53 exposes per arm stat4 evidence' => static function (TestRunner $t) use ($plan, $or, $nameArm): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta')));
        $t->same('plugin_alpha', $p['arms'][0]['stat4CurrentNext'][1]['current']['key']);
    },
    'planner stat4 or partial expression current next53 exposes aggregate current next evidence' => static function (TestRunner $t) use ($plan, $or, $nameArm): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta')));
        $t->same('plugin_beta', $p['stat4CurrentNext'][1]['currentNext'][2]['current']['key']);
    },
    'planner stat4 or partial expression current next53 keeps rowid dedupe for or terms' => static function (TestRunner $t) use ($plan, $or, $nameArm): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta')));
        $t->same(true, $p['dedupeRowidsRequired']);
    },
    'planner stat4 or partial expression current next53 reports same index rewrite metadata' => static function (TestRunner $t) use ($plan, $or, $nameArm): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta')));
        $t->same('idx_lower_plugin_partial_stat4_or', $p['inRewrite']['index']);
    },
    'planner stat4 or partial expression current next53 reports rewrite expression type' => static function (TestRunner $t) use ($plan, $or, $nameArm): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta')));
        $t->same('lower', $p['inRewrite']['type']);
    },
    'planner stat4 or partial expression current next53 reports rewrite source column' => static function (TestRunner $t) use ($plan, $or, $nameArm): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta')));
        $t->same('option_name', $p['inRewrite']['column']);
    },
    'planner stat4 or partial expression current next53 records residual predicate requirement' => static function (TestRunner $t) use ($plan, $or, $nameRangeArm, $nameArm): void {
        $p = $plan($or($nameRangeArm('>=', 'plugin_alpha'), $nameArm('plugin_beta')));
        $t->same(true, $p['residualPredicateRequired']);
    },
    'planner stat4 or partial expression current next53 keeps union strategy for range arm' => static function (TestRunner $t) use ($plan, $or, $nameRangeArm, $nameArm): void {
        $p = $plan($or($nameRangeArm('>=', 'plugin_alpha'), $nameArm('plugin_beta')));
        $t->same('or-rowid-union', $p['strategy']);
    },
    'planner stat4 or partial expression current next53 keeps null rewrite for range arm' => static function (TestRunner $t) use ($plan, $or, $nameRangeArm, $nameArm): void {
        $p = $plan($or($nameRangeArm('>=', 'plugin_alpha'), $nameArm('plugin_beta')));
        $t->same(null, $p['inRewrite']);
    },
    'planner stat4 or partial expression current next53 rejects missing partial proof' => static function (TestRunner $t) use ($plan, $or, $point, $lower): void {
        $t->same(null, $plan($or($point($lower, 'plugin_alpha'), $point($lower, 'plugin_beta'))));
    },
];

$pointCases = [
    ['plugin_alpha', 7, 'plugin_beta'],
    ['plugin_beta', 5, 'plugin_gamma'],
    ['plugin_gamma', 13, 'siteurl'],
    ['siteurl', 3, null],
    ['plugin_missing', 18, null],
];
foreach ($pointCases as [$value, $expectedRows, $nextKey]) {
    $tests["planner stat4 or partial expression current next53 point {$value} rows"] = static function (TestRunner $t) use ($plan, $or, $nameArm, $value, $expectedRows): void {
        $p = $plan($or($nameArm($value), $nameArm('plugin_alpha')));
        $t->same($expectedRows, $p['arms'][0]['estimatedRows']);
    };
    $tests["planner stat4 or partial expression current next53 point {$value} current next"] = static function (TestRunner $t) use ($plan, $or, $nameArm, $value, $nextKey): void {
        $p = $plan($or($nameArm($value), $nameArm('plugin_alpha')));
        $pairs = $p['arms'][0]['stat4CurrentNext'];
        $found = array_values(array_filter($pairs, static fn (array $pair): bool => $pair['current']['key'] === $value));
        $next = $found === [] ? null : ($found[0]['next']['key'] ?? null);
        $t->same($nextKey, $next);
    };
}

$rewriteCases = [
    [['plugin_alpha', 'plugin_beta'], 12],
    [['plugin_alpha', 'plugin_gamma'], 20],
    [['plugin_alpha', 'plugin_beta', 'plugin_gamma'], 25],
    [['plugin_alpha', 'plugin_missing'], 25],
    [['plugin_beta', 'plugin_beta', 'plugin_gamma'], 18],
    [['siteurl', 'plugin_alpha', 'siteurl'], 10],
];
foreach ($rewriteCases as [$values, $expectedRows]) {
    $tests['planner stat4 or partial expression current next53 rewrite ' . implode('-', $values)] = static function (TestRunner $t) use ($plan, $or, $nameArm, $values, $expectedRows): void {
        $arms = array_map($nameArm, $values);
        $p = $plan($or(...$arms));
        $t->same($expectedRows, $p['estimatedRows']);
    };
}

$rangeCases = [
    ['>=', 'plugin_alpha', 88],
    ['>', 'plugin_alpha', 81],
    ['<=', 'plugin_beta', 14],
    ['<', 'plugin_gamma', 14],
    ['>=', 'siteurl', 63],
    ['>', 'siteurl', 60],
];
foreach ($rangeCases as [$operator, $value, $expectedRows]) {
    $tests["planner stat4 or partial expression current next53 range {$operator} {$value} rows"] = static function (TestRunner $t) use ($plan, $or, $nameRangeArm, $nameArm, $operator, $value, $expectedRows): void {
        $p = $plan($or($nameRangeArm($operator, $value), $nameArm('plugin_alpha')));
        $t->same($expectedRows, $p['arms'][0]['estimatedRows']);
    };
}

$lengthCases = [
    [5, 4],
    [8, 9],
    [12, 6],
    [15, 20],
];
foreach ($lengthCases as [$value, $expectedRows]) {
    $tests["planner stat4 or partial expression current next53 length point {$value}"] = static function (TestRunner $t) use ($indexes, $or, $lengthArm, $value, $expectedRows): void {
        $p = SQLiteSelectExpressionIndexPlan::partialCoveringOrPlan($indexes(), $or($lengthArm($value), $lengthArm(8)), [['function' => 'length', 'column' => 'option_value'], ['column' => 'autoload']], ['autoload', 'option_id']);
        $t->same($expectedRows, $p['arms'][0]['estimatedRows']);
    };
}

$jsonCases = [
    ['core', 3, 'plugin'],
    ['plugin', 9, 'theme'],
    ['theme', 4, null],
    ['missing', 24, null],
];
foreach ($jsonCases as [$kind, $expectedRows, $nextKey]) {
    $tests["planner stat4 or partial expression current next53 json kind {$kind} rows"] = static function (TestRunner $t) use ($indexes, $or, $jsonArm, $kind, $expectedRows): void {
        $p = SQLiteSelectExpressionIndexPlan::partialCoveringOrPlan($indexes(), $or($jsonArm($kind), $jsonArm('plugin')), [['function' => 'json_extract', 'column' => 'option_value', 'path' => '$.kind'], ['column' => 'autoload']], ['autoload', 'option_id']);
        $t->same($expectedRows, $p['arms'][0]['estimatedRows']);
    };
    $tests["planner stat4 or partial expression current next53 json kind {$kind} current next"] = static function (TestRunner $t) use ($indexes, $or, $jsonArm, $kind, $nextKey): void {
        $p = SQLiteSelectExpressionIndexPlan::partialCoveringOrPlan($indexes(), $or($jsonArm($kind), $jsonArm('plugin')), [['function' => 'json_extract', 'column' => 'option_value', 'path' => '$.kind'], ['column' => 'autoload']], ['autoload', 'option_id']);
        $found = array_values(array_filter($p['arms'][0]['stat4CurrentNext'], static fn (array $pair): bool => $pair['current']['key'] === $kind));
        $next = $found === [] ? null : ($found[0]['next']['key'] ?? null);
        $t->same($nextKey, $next);
    };
}

$metadataCases = [
    'first arm position' => static fn (array $p): int => $p['arms'][0]['position'],
    'second arm position' => static fn (array $p): int => $p['arms'][1]['position'],
    'first arm root page' => static fn (array $p): int => $p['arms'][0]['rootPage'],
    'first arm stat4 estimate' => static fn (array $p): int => $p['arms'][0]['stat4Estimate'],
    'first arm trailing column count' => static fn (array $p): int => count($p['arms'][0]['trailingColumns']),
    'aggregate stat4 arm count' => static fn (array $p): int => count($p['stat4CurrentNext']),
    'index name count' => static fn (array $p): int => count($p['indexNames']),
    'rewrite value count' => static fn (array $p): int => count($p['inRewrite']['values']),
];
$expectedMetadata = [0, 1, 531, 7, 3, 2, 1, 2];
$offset = 0;
foreach ($metadataCases as $label => $reader) {
    $expected = $expectedMetadata[$offset++];
    $tests["planner stat4 or partial expression current next53 metadata {$label}"] = static function (TestRunner $t) use ($plan, $or, $nameArm, $reader, $expected): void {
        $p = $plan($or($nameArm('plugin_alpha'), $nameArm('plugin_beta')));
        $t->same($expected, $reader($p));
    };
}

$orderCases = [
    'expression order satisfied' => [[['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload']], true],
    'wrong expression direction rejected' => [[['function' => 'lower', 'column' => 'option_name', 'direction' => 'DESC']], false],
    'wrong tail direction rejected' => [[['function' => 'lower', 'column' => 'option_name'], ['column' => 'autoload', 'direction' => 'DESC']], false],
];
foreach ($orderCases as $label => [$orderBy, $expected]) {
    $tests["planner stat4 or partial expression current next53 order {$label}"] = static function (TestRunner $t) use ($indexes, $or, $nameArm, $orderBy, $expected): void {
        $p = SQLiteSelectExpressionIndexPlan::partialCoveringOrPlan($indexes(), $or($nameArm('plugin_alpha'), $nameArm('plugin_beta')), $orderBy, ['autoload', 'option_id']);
        $t->same($expected, $p['orderBySatisfied']);
    };
}

return $tests;
