<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$column = static fn (string $name): array => ['column' => $name];
$expr = static fn (string $function, string $column, string $path): array => ['function' => $function, 'column' => $column, 'path' => $path];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$in = static fn (array $left, array $values): array => ['operator' => 'IN', 'left' => $left, 'values' => $values];
$between = static fn (array $left, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => $left, 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexes = static fn (): array => [
    [
        'name' => 'idx_jsonb_channel_cover',
        'rootPage' => 301,
        'estimatedRows' => 6400,
        'sql' => "CREATE INDEX idx_jsonb_channel_cover ON wp_options(jsonb_extract(option_value, '$.plugin.channel') COLLATE NOCASE, autoload, option_id DESC, option_name)",
    ],
    [
        'name' => 'idx_jsonb_priority_cover',
        'rootPage' => 302,
        'estimatedRows' => 4200,
        'sql' => "CREATE INDEX idx_jsonb_priority_cover ON wp_options(jsonb_extract(option_value, '$.plugin.priority'), autoload DESC, option_name)",
    ],
    [
        'name' => 'idx_json_text_mode_cover',
        'rootPage' => 303,
        'estimatedRows' => 3600,
        'sql' => "CREATE INDEX idx_json_text_mode_cover ON wp_options((option_value ->> 'settings.v1') COLLATE RTRIM, option_name, autoload)",
    ],
    [
        'name' => 'idx_json_value_limits_cover',
        'rootPage' => 304,
        'estimatedRows' => 2800,
        'sql' => "CREATE INDEX idx_json_value_limits_cover ON wp_options((option_value -> '$.plugin.limits') COLLATE BINARY, option_name, autoload)",
    ],
    [
        'name' => 'idx_json_extract_partial_cover',
        'rootPage' => 305,
        'estimatedRows' => 2000,
        'sql' => "CREATE INDEX idx_json_extract_partial_cover ON wp_options(json_extract(option_value, '$.plugin.enabled'), option_name, option_id) WHERE autoload = 'yes'",
    ],
    [
        'name' => 'idx_jsonb_channel_plain',
        'rootPage' => 306,
        'estimatedRows' => 6400,
        'sql' => "CREATE INDEX idx_jsonb_channel_plain ON wp_options(jsonb_extract(option_value, '$.plugin.channel'))",
    ],
];

$tests = [
    'jsonb path index covering current next34 parses jsonb_extract expression' => static function (TestRunner $t): void {
        $expression = SQLiteCreateIndex::firstJsonExtractExpression("CREATE INDEX idx ON wp_options(jsonb_extract(option_value, '$.plugin.channel') COLLATE nocase DESC)");
        $t->same('option_value', $expression?->columnName);
        $t->same('$.plugin.channel', $expression?->path);
        $t->same('NOCASE', $expression?->collation);
        $t->same(true, $expression?->descending);
    },
    'jsonb path index covering current next34 parses operator path tail columns' => static function (TestRunner $t): void {
        $columns = SQLiteCreateIndex::columnsAfterFirstExpression("CREATE INDEX idx ON wp_options((option_value ->> 'settings.v1') COLLATE rtrim, option_name, autoload DESC)");
        $t->same(['option_name', 'autoload'], array_map(static fn ($column): string => $column->columnName, $columns));
        $t->same([false, true], array_map(static fn ($column): bool => $column->descending, $columns));
    },
    'jsonb path index covering current next34 chooses jsonb covering equality plan' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('jsonb_extract', 'option_value', '$.plugin.channel'), 'stable'), [['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC']], ['autoload', 'option_id', 'option_name']);
        $t->same('idx_jsonb_channel_cover', $plan['name']);
        $t->same('$.plugin.channel', $plan['path']);
        $t->same(true, $plan['covering']);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'jsonb path index covering current next34 rejects mismatched json path' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), $point($expr('jsonb_extract', 'option_value', '$.plugin.name'), 'cache'), [], ['option_name']);
        $t->same([], $plans);
    },
    'jsonb path index covering current next34 ranks covering jsonb plan ahead of plain' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), $point($expr('jsonb_extract', 'option_value', '$.plugin.channel'), 'stable'), [], ['autoload', 'option_name']);
        $t->same('idx_jsonb_channel_cover', $plans[0]['name']);
        $t->same(true, $plans[0]['covering']);
        $t->same(false, $plans[1]['covering']);
    },
    'jsonb path index covering current next34 keeps expression order for range scan' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $range($expr('jsonb_extract', 'option_value', '$.plugin.channel'), '>=', 'm'), [['column' => 'option_value']], ['autoload']);
        $t->same('idx_jsonb_channel_cover', $plan['name']);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'jsonb path index covering current next34 rejects tail order for range scan' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $range($expr('jsonb_extract', 'option_value', '$.plugin.channel'), '>=', 'm'), [['column' => 'autoload']], ['autoload']);
        $t->same('idx_jsonb_channel_cover', $plan['name']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'jsonb path index covering current next34 handles numeric range path' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $range($expr('jsonb_extract', 'option_value', '$.plugin.priority'), '>=', 3), [], ['autoload', 'option_name']);
        $t->same('idx_jsonb_priority_cover', $plan['name']);
        $t->same(1050, $plan['estimatedRows']);
        $t->same(true, $plan['covering']);
    },
    'jsonb path index covering current next34 handles json text operator equality' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('json_text_operator', 'option_value', '$."settings.v1"'), '{"mode":"dark"}'), [['column' => 'option_name']], ['option_name']);
        $t->same('idx_json_text_mode_cover', $plan['name']);
        $t->same('$."settings.v1"', $plan['path']);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'jsonb path index covering current next34 handles json value operator equality' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('json_value_operator', 'option_value', '$.plugin.limits'), '{"daily":25}'), [['column' => 'option_name']], ['option_name', 'autoload']);
        $t->same('idx_json_value_limits_cover', $plan['name']);
        $t->same(true, $plan['covering']);
    },
    'jsonb path index covering current next34 proves partial json extract with ordinary term' => static function (TestRunner $t) use ($indexes, $expr, $point, $column, $and): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $and($point($expr('json_extract', 'option_value', '$.plugin.enabled'), 1), $point($column('autoload'), 'yes')), [['column' => 'option_name']], ['option_name', 'option_id']);
        $t->same('idx_json_extract_partial_cover', $plan['name']);
        $t->same(true, $plan['partial']);
        $t->same(true, $plan['covering']);
    },
    'jsonb path index covering current next34 rejects partial json extract without proof' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([$indexes()[4]], $point($expr('json_extract', 'option_value', '$.plugin.enabled'), 1), [], ['option_name']);
        $t->same([], $plans);
    },
    'jsonb path index covering current next34 accepts in-list over text path' => static function (TestRunner $t) use ($indexes, $expr, $in): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $in($expr('jsonb_extract', 'option_value', '$.plugin.channel'), ['stable', 'beta', null]), [], ['autoload']);
        $t->same('idx_jsonb_channel_cover', $plan['name']);
        $t->same(['stable', 'beta', null], $plan['values']);
    },
    'jsonb path index covering current next34 accepts between over priority path' => static function (TestRunner $t) use ($indexes, $expr, $between): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $between($expr('jsonb_extract', 'option_value', '$.plugin.priority'), 3, 7), [], ['autoload']);
        $t->same('idx_jsonb_priority_cover', $plan['name']);
        $t->same('BETWEEN', $plan['operator']);
    },
    'jsonb path index covering current next34 rejects malformed predicate path' => static function (TestRunner $t) use ($indexes, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), $point(['function' => 'jsonb_extract', 'column' => 'option_value', 'path' => '$.'], 'stable'), [], ['autoload']);
        $t->same([], $plans);
    },
    'jsonb path index covering current next34 rejects wrong source column' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), $point($expr('jsonb_extract', 'option_name', '$.plugin.channel'), 'stable'), [], ['autoload']);
        $t->same([], $plans);
    },
    'jsonb path index covering current next34 rejects jsonb predicate against json_extract index' => static function (TestRunner $t) use ($expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([
            ['name' => 'idx_json_extract_same_path', 'sql' => "CREATE INDEX idx_json_extract_same_path ON wp_options(json_extract(option_value, '$.plugin.channel'), option_name)"],
        ], $point($expr('jsonb_extract', 'option_value', '$.plugin.channel'), 'stable'), [], ['option_name']);
        $t->same([], $plans);
    },
    'jsonb path index covering current next34 rejects text operator predicate against value operator index' => static function (TestRunner $t) use ($expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([
            ['name' => 'idx_json_value_same_path', 'sql' => "CREATE INDEX idx_json_value_same_path ON wp_options(option_value -> '$.plugin.channel', option_name)"],
        ], $point($expr('json_text_operator', 'option_value', '$.plugin.channel'), 'stable'), [], ['option_name']);
        $t->same([], $plans);
    },
];

foreach ([
    ['stable', 'idx_jsonb_channel_cover', true],
    ['beta', 'idx_jsonb_channel_cover', true],
    ['dev', 'idx_jsonb_channel_cover', true],
    ['network', 'idx_jsonb_channel_cover', true],
    ['mu', 'idx_jsonb_channel_cover', true],
] as [$value, $expected, $covering]) {
    $tests['jsonb path index covering current next34 generated channel equality ' . $value] = static function (TestRunner $t) use ($indexes, $expr, $point, $value, $expected, $covering): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('jsonb_extract', 'option_value', '$.plugin.channel'), $value), [['column' => 'autoload']], ['autoload', 'option_name']);
        $t->same($expected, $plan['name']);
        $t->same($covering, $plan['covering']);
        $t->same(true, $plan['orderBySatisfied']);
    };
}

foreach ([
    [0, '>=', 0, 1050],
    [1, '>', 2, 1050],
    [2, '<=', 9, 1050],
    [3, '<', 10, 1050],
    [4, '=', 5, 42],
    [5, '>=', null, 1050],
] as [$case, $operator, $value, $estimated]) {
    $tests['jsonb path index covering current next34 generated priority range ' . $case] = static function (TestRunner $t) use ($indexes, $expr, $range, $point, $operator, $value, $estimated): void {
        $predicate = $operator === '='
            ? $point($expr('jsonb_extract', 'option_value', '$.plugin.priority'), $value)
            : $range($expr('jsonb_extract', 'option_value', '$.plugin.priority'), $operator, $value);
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $predicate, [], ['autoload', 'option_name']);
        $t->same('idx_jsonb_priority_cover', $plan['name']);
        $t->same($estimated, $plan['estimatedRows']);
    };
}

foreach ([
    ['$.plugin.channel', true],
    ['$.plugin.priority', true],
    ['$.plugin.missing', true],
    ['$."settings.v1"', true],
    ['$.plugin.limits', true],
] as [$path, $wellFormed]) {
    $tests['jsonb path index covering current next34 validates planner path ' . $path] = static function (TestRunner $t) use ($path, $wellFormed): void {
        $t->same($wellFormed, \PortLibs\LibSqlite\SQLiteJsonPath::isWellFormed($path));
    };
}

foreach ([
    ['json_extract', '$.plugin.enabled', 'idx_json_extract_partial_cover'],
    ['jsonb_extract', '$.plugin.channel', 'idx_jsonb_channel_cover'],
    ['json_text_operator', '$."settings.v1"', 'idx_json_text_mode_cover'],
    ['json_value_operator', '$.plugin.limits', 'idx_json_value_limits_cover'],
] as [$function, $path, $expected]) {
    $tests['jsonb path index covering current next34 generated function family ' . $function] = static function (TestRunner $t) use ($indexes, $expr, $point, $column, $and, $function, $path, $expected): void {
        $predicate = $point($expr($function, 'option_value', $path), $function === 'json_extract' ? 1 : 'stable');
        if ($function === 'json_extract') {
            $predicate = $and($predicate, $point($column('autoload'), 'yes'));
        }
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $predicate, [], ['option_name']);
        $t->same($expected, $plan['name']);
        $t->same($path, $plan['path']);
    };
}

foreach ([
    ['autoload', true],
    ['option_id', true],
    ['option_name', true],
    ['option_value', false],
    ['blog_id', false],
] as [$needed, $covering]) {
    $tests['jsonb path index covering current next34 generated coverage column ' . $needed] = static function (TestRunner $t) use ($indexes, $expr, $point, $needed, $covering): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('jsonb_extract', 'option_value', '$.plugin.channel'), 'stable'), [], [$needed]);
        $t->same('idx_jsonb_channel_cover', $plan['name']);
        $t->same($covering, $plan['covering']);
    };
}

foreach ([
    [['column' => 'autoload'], true],
    [['column' => 'option_id', 'direction' => 'DESC'], false],
    [['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC'], true],
    [['column' => 'autoload'], ['column' => 'option_id'], false],
    [['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC'], ['column' => 'option_name'], true],
] as $case => $order) {
    $expected = (bool) array_pop($order);
    $tests['jsonb path index covering current next34 generated order tail ' . $case] = static function (TestRunner $t) use ($indexes, $expr, $point, $order, $expected): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('jsonb_extract', 'option_value', '$.plugin.channel'), 'stable'), $order, ['autoload']);
        $t->same('idx_jsonb_channel_cover', $plan['name']);
        $t->same($expected, $plan['orderBySatisfied']);
    };
}

foreach ([
    ['idx_jsonb_channel_cover', '$.plugin.channel', 'NOCASE', false],
    ['idx_jsonb_priority_cover', '$.plugin.priority', 'BINARY', false],
    ['idx_json_text_mode_cover', '$."settings.v1"', 'RTRIM', false],
    ['idx_json_value_limits_cover', '$.plugin.limits', 'BINARY', false],
    ['idx_json_extract_partial_cover', '$.plugin.enabled', 'BINARY', false],
] as [$name, $path, $collation, $descending]) {
    $tests['jsonb path index covering current next34 generated metadata parse ' . $name] = static function (TestRunner $t) use ($indexes, $name, $path, $collation, $descending): void {
        $definition = array_values(array_filter($indexes(), static fn (array $index): bool => $index['name'] === $name))[0];
        $expression = str_contains($name, 'text_mode')
            ? SQLiteCreateIndex::firstJsonTextOperatorExpression($definition['sql'])
            : (str_contains($name, 'value_limits') ? SQLiteCreateIndex::firstJsonValueOperatorExpression($definition['sql']) : SQLiteCreateIndex::firstJsonExtractExpression($definition['sql']));
        $t->same('option_value', $expression?->columnName);
        $t->same($path, $expression?->path);
        $t->same($collation, $expression?->collation);
        $t->same($descending, $expression?->descending);
    };
}

return $tests;
