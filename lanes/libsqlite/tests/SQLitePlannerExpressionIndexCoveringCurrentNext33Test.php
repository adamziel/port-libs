<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$between = static fn (array $left, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => $left, 'lower' => $lower, 'upper' => $upper];
$inList = static fn (array $left, array $values): array => ['operator' => 'IN', 'left' => $left, 'values' => $values];

$indexes = static fn (): array => [
    [
        'name' => 'idx_lower_expr_only',
        'rootPage' => 91,
        'estimatedRows' => 4000,
        'sql' => 'CREATE INDEX idx_lower_expr_only ON wp_options(lower(option_name))',
    ],
    [
        'name' => 'idx_lower_expr_covering_tail',
        'rootPage' => 92,
        'estimatedRows' => 4000,
        'sql' => 'CREATE INDEX idx_lower_expr_covering_tail ON wp_options(lower(option_name), autoload, option_value)',
    ],
    [
        'name' => 'idx_upper_expr_only',
        'rootPage' => 93,
        'estimatedRows' => 3000,
        'sql' => 'CREATE INDEX idx_upper_expr_only ON wp_options(upper(option_name))',
    ],
    [
        'name' => 'idx_length_expr_tail',
        'rootPage' => 94,
        'estimatedRows' => 2500,
        'sql' => 'CREATE INDEX idx_length_expr_tail ON wp_options(length(option_name), autoload)',
    ],
    [
        'name' => 'idx_value_int_expr_tail',
        'rootPage' => 95,
        'estimatedRows' => 1800,
        'sql' => 'CREATE INDEX idx_value_int_expr_tail ON wp_options(CAST(option_value AS INTEGER), option_name)',
    ],
    [
        'name' => 'idx_lower_partial_expr',
        'rootPage' => 96,
        'estimatedRows' => 9000,
        'sql' => "CREATE INDEX idx_lower_partial_expr ON wp_options(lower(option_name), autoload) WHERE option_name IS NOT NULL",
    ],
];

$cases = [
    'lower expression projection is covering from first expression key' => [
        $point($expr('lower', 'option_name'), 'siteurl'),
        ['lower', 'option_name'],
        [],
        'idx_lower_expr_covering_tail',
        true,
        ['lower(option_name)'],
    ],
    'lower expression plus tail column is covering from expression and ordinary tail' => [
        $point($expr('lower', 'option_name'), 'home'),
        ['lower', 'option_name'],
        ['autoload'],
        'idx_lower_expr_covering_tail',
        true,
        ['lower(option_name)'],
    ],
    'source column is not covered by expression key alone' => [
        $point($expr('lower', 'option_name'), 'siteurl'),
        ['lower', 'option_name'],
        ['option_name'],
        'idx_lower_expr_covering_tail',
        false,
        ['lower(option_name)'],
    ],
    'upper expression projection is covering for matching upper key' => [
        $point($expr('upper', 'option_name'), 'SITEURL'),
        ['upper', 'option_name'],
        [],
        'idx_upper_expr_only',
        true,
        ['upper(option_name)'],
    ],
    'upper expression index does not cover lower expression projection' => [
        $point($expr('upper', 'option_name'), 'SITEURL'),
        ['lower', 'option_name'],
        [],
        'idx_upper_expr_only',
        false,
        [],
    ],
    'length expression projection is covering for integer length buckets' => [
        $point($expr('length', 'option_name'), 7),
        ['length', 'option_name'],
        [],
        'idx_length_expr_tail',
        true,
        ['length(option_name)'],
    ],
    'length expression and autoload tail are jointly covering' => [
        $inList($expr('length', 'option_name'), [4, 7, 8]),
        ['length', 'option_name'],
        ['autoload'],
        'idx_length_expr_tail',
        true,
        ['length(option_name)'],
    ],
    'integer cast expression projection is covering for numeric option value' => [
        $point($expr('cast_integer', 'option_value'), 42),
        ['cast_integer', 'option_value'],
        [],
        'idx_value_int_expr_tail',
        true,
        ['cast_integer(option_value)'],
    ],
    'integer cast expression plus option name tail is covering' => [
        $between($expr('cast_integer', 'option_value'), 100, 60000),
        ['cast_integer', 'option_value'],
        ['option_name'],
        'idx_value_int_expr_tail',
        true,
        ['cast_integer(option_value)'],
    ],
    'integer cast expression index does not cover raw option value' => [
        $point($expr('cast_integer', 'option_value'), 1),
        ['cast_integer', 'option_value'],
        ['option_value'],
        'idx_value_int_expr_tail',
        false,
        ['cast_integer(option_value)'],
    ],
    'range lower expression projection remains covering for expression output' => [
        $range($expr('lower', 'option_name'), '>=', 'plugin_'),
        ['lower', 'option_name'],
        [],
        'idx_lower_expr_covering_tail',
        true,
        ['lower(option_name)'],
    ],
    'in-list lower expression projection remains covering with null search member' => [
        $inList($expr('lower', 'option_name'), ['home', null, 'siteurl']),
        ['lower', 'option_name'],
        [],
        'idx_lower_expr_covering_tail',
        true,
        ['lower(option_name)'],
    ],
    'between lower expression projection remains covering for expression result' => [
        $between($expr('lower', 'option_name'), 'a', 'm'),
        ['lower', 'option_name'],
        [],
        'idx_lower_expr_covering_tail',
        true,
        ['lower(option_name)'],
    ],
];

$tests = [];

foreach ($cases as $name => [$predicate, $neededExpression, $neededColumns, $expectedIndex, $expectedCovering, $expectedExpressions]) {
    $tests['planner expression index covering current next33 ' . $name] = static function (TestRunner $t) use ($indexes, $predicate, $neededExpression, $neededColumns, $expectedIndex, $expectedCovering, $expectedExpressions): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $predicate, [], $neededColumns, [[
            'function' => $neededExpression[0],
            'column' => $neededExpression[1],
        ]]);
        $t->same($expectedIndex, $plan['name']);
        $t->same($expectedCovering, $plan['covering']);
        $t->same($expectedExpressions, $plan['coveringExpressions']);
    };
}

$tests['planner expression index covering current next33 ranks expression covering ahead when estimates tie'] = static function (TestRunner $t) use ($expr, $point): void {
    $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([
        ['name' => 'idx_lower_plain', 'estimatedRows' => 4000, 'sql' => 'CREATE INDEX idx_lower_plain ON wp_options(lower(option_name))'],
        ['name' => 'idx_lower_tail', 'estimatedRows' => 4000, 'sql' => 'CREATE INDEX idx_lower_tail ON wp_options(lower(option_name), autoload)'],
    ], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload'], [$expr('lower', 'option_name')]);
    $t->same('idx_lower_tail', $plans[0]['name']);
    $t->same(true, $plans[0]['covering']);
    $t->same(false, $plans[1]['covering']);
};

$tests['planner expression index covering current next33 keeps covering false when requested expression column differs'] = static function (TestRunner $t) use ($expr, $point): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([
        ['name' => 'idx_lower_name', 'sql' => 'CREATE INDEX idx_lower_name ON wp_options(lower(option_name))'],
    ], $point($expr('lower', 'option_name'), 'siteurl'), [], [], [$expr('lower', 'autoload')]);
    $t->same('idx_lower_name', $plan['name']);
    $t->same(false, $plan['covering']);
    $t->same([], $plan['coveringExpressions']);
};

$tests['planner expression index covering current next33 supports multiple duplicate needed expression outputs'] = static function (TestRunner $t) use ($expr, $point): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([
        ['name' => 'idx_lower_name', 'sql' => 'CREATE INDEX idx_lower_name ON wp_options(lower(option_name))'],
    ], $point($expr('lower', 'option_name'), 'siteurl'), [], [], [$expr('lower', 'option_name'), $expr('lower', 'option_name')]);
    $t->same(true, $plan['covering']);
    $t->same(['lower(option_name)', 'lower(option_name)'], $plan['coveringExpressions']);
};

$tests['planner expression index covering current next33 validates needed expression function'] = static function (TestRunner $t) use ($expr, $point): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost([
        ['name' => 'idx_lower_name', 'sql' => 'CREATE INDEX idx_lower_name ON wp_options(lower(option_name))'],
    ], $point($expr('lower', 'option_name'), 'siteurl'), [], [], [['function' => 'json_extract', 'column' => 'option_value']]));
};

$tests['planner expression index covering current next33 validates needed expression column'] = static function (TestRunner $t) use ($expr, $point): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::rankedPlans([
        ['name' => 'idx_lower_name', 'sql' => 'CREATE INDEX idx_lower_name ON wp_options(lower(option_name))'],
    ], $point($expr('lower', 'option_name'), 'siteurl'), [], [], [['function' => 'lower', 'column' => '']]));
};

$tests['planner expression index covering current next33 keeps old needed column only behavior'] = static function (TestRunner $t) use ($expr, $point): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([
        ['name' => 'idx_lower_tail', 'sql' => 'CREATE INDEX idx_lower_tail ON wp_options(lower(option_name), autoload, option_value)'],
    ], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload']);
    $t->same(true, $plan['covering']);
    $t->same([], $plan['coveringExpressions']);
};

$tests['planner expression index covering current next33 partial lower expression projection is covering when search value proves non null'] = static function (TestRunner $t) use ($expr, $point): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([
        [
            'name' => 'idx_lower_partial_expr',
            'rootPage' => 96,
            'estimatedRows' => 9000,
            'sql' => "CREATE INDEX idx_lower_partial_expr ON wp_options(lower(option_name), autoload) WHERE option_name IS NOT NULL",
        ],
    ], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload'], [$expr('lower', 'option_name')]);
    $t->same('idx_lower_partial_expr', $plan['name']);
    $t->same(true, $plan['partial']);
    $t->same(true, $plan['covering']);
    $t->same(['lower(option_name)'], $plan['coveringExpressions']);
};

$matrix = [
    'explicit covering metadata plus expression output covers lower name and option value' => [
        [
            'name' => 'idx_lower_meta_cover',
            'coveringColumns' => ['option_value'],
            'sql' => 'CREATE INDEX idx_lower_meta_cover ON wp_options(lower(option_name))',
        ],
        $point($expr('lower', 'option_name'), 'home'),
        ['option_value'],
        [$expr('lower', 'option_name')],
        true,
        ['lower(option_name)'],
    ],
    'explicit covering metadata does not cover unrelated lower expression column' => [
        [
            'name' => 'idx_lower_meta_cover',
            'coveringColumns' => ['option_value'],
            'sql' => 'CREATE INDEX idx_lower_meta_cover ON wp_options(lower(option_name))',
        ],
        $point($expr('lower', 'option_name'), 'home'),
        ['option_value'],
        [$expr('lower', 'autoload')],
        false,
        [],
    ],
    'case-insensitive needed expression function name is accepted' => [
        [
            'name' => 'idx_lower_name',
            'sql' => 'CREATE INDEX idx_lower_name ON wp_options(lower(option_name))',
        ],
        $point($expr('lower', 'option_name'), 'home'),
        [],
        [['function' => 'LOWER', 'column' => 'option_name']],
        true,
        ['lower(option_name)'],
    ],
    'case-insensitive needed expression column name is accepted' => [
        [
            'name' => 'idx_lower_name',
            'sql' => 'CREATE INDEX idx_lower_name ON wp_options(lower(option_name))',
        ],
        $point($expr('lower', 'option_name'), 'home'),
        [],
        [['function' => 'lower', 'column' => 'OPTION_NAME']],
        true,
        ['lower(OPTION_NAME)'],
    ],
    'descending upper expression key still covers upper expression output' => [
        [
            'name' => 'idx_upper_desc',
            'sql' => 'CREATE INDEX idx_upper_desc ON wp_options(upper(option_name) DESC)',
        ],
        $range($expr('upper', 'option_name'), '>=', 'PLUGIN_'),
        [],
        [$expr('upper', 'option_name')],
        true,
        ['upper(option_name)'],
    ],
    'collated lower expression key still covers lower expression output' => [
        [
            'name' => 'idx_lower_nocase',
            'sql' => 'CREATE INDEX idx_lower_nocase ON wp_options(lower(option_name) COLLATE NOCASE)',
        ],
        $point($expr('lower', 'option_name'), 'siteurl'),
        [],
        [$expr('lower', 'option_name')],
        true,
        ['lower(option_name)'],
    ],
    'quoted trailing column and expression output are jointly covering' => [
        [
            'name' => 'idx_lower_quoted_tail',
            'sql' => 'CREATE INDEX idx_lower_quoted_tail ON wp_options(lower(option_name), "autoload")',
        ],
        $point($expr('lower', 'option_name'), 'siteurl'),
        ['autoload'],
        [$expr('lower', 'option_name')],
        true,
        ['lower(option_name)'],
    ],
    'table-qualified trailing column and expression output are jointly covering' => [
        [
            'name' => 'idx_lower_qualified_tail',
            'sql' => 'CREATE INDEX idx_lower_qualified_tail ON wp_options(lower(option_name), wp_options.autoload)',
        ],
        $point($expr('lower', 'option_name'), 'siteurl'),
        ['autoload'],
        [$expr('lower', 'option_name')],
        true,
        ['lower(option_name)'],
    ],
    'length in-list expression output does not cover lower expression output' => [
        [
            'name' => 'idx_length_name',
            'sql' => 'CREATE INDEX idx_length_name ON wp_options(length(option_name))',
        ],
        $inList($expr('length', 'option_name'), [4, 7]),
        [],
        [$expr('lower', 'option_name')],
        false,
        [],
    ],
    'cast expression output does not cover length expression output on same column' => [
        [
            'name' => 'idx_int_value',
            'sql' => 'CREATE INDEX idx_int_value ON wp_options(CAST(option_value AS INTEGER))',
        ],
        $point($expr('cast_integer', 'option_value'), 11),
        [],
        [$expr('length', 'option_value')],
        false,
        [],
    ],
    'multiple needed expressions reject when one expression is not indexed' => [
        [
            'name' => 'idx_lower_name',
            'sql' => 'CREATE INDEX idx_lower_name ON wp_options(lower(option_name))',
        ],
        $point($expr('lower', 'option_name'), 'siteurl'),
        [],
        [$expr('lower', 'option_name'), $expr('upper', 'option_name')],
        false,
        [],
    ],
    'needed expression with extra ignored metadata still covers matching key' => [
        [
            'name' => 'idx_lower_name',
            'sql' => 'CREATE INDEX idx_lower_name ON wp_options(lower(option_name))',
        ],
        $point($expr('lower', 'option_name'), 'siteurl'),
        [],
        [['function' => 'lower', 'column' => 'option_name', 'alias' => 'folded']],
        true,
        ['lower(option_name)'],
    ],
    'expression output remains covering for reversed equality predicate' => [
        [
            'name' => 'idx_lower_name',
            'sql' => 'CREATE INDEX idx_lower_name ON wp_options(lower(option_name))',
        ],
        ['operator' => '=', 'left' => 'siteurl', 'right' => $expr('lower', 'option_name')],
        [],
        [$expr('lower', 'option_name')],
        true,
        ['lower(option_name)'],
    ],
    'expression output remains covering for reversed range predicate' => [
        [
            'name' => 'idx_lower_name',
            'sql' => 'CREATE INDEX idx_lower_name ON wp_options(lower(option_name))',
        ],
        ['operator' => '<=', 'left' => 'plugin_z', 'right' => $expr('lower', 'option_name')],
        [],
        [$expr('lower', 'option_name')],
        true,
        ['lower(option_name)'],
    ],
    'covering expression survives another scalar point lookup planning' => [
        [
            'name' => 'idx_lower_name',
            'sql' => 'CREATE INDEX idx_lower_name ON wp_options(lower(option_name))',
        ],
        $point($expr('lower', 'option_name'), 'blogname'),
        [],
        [$expr('lower', 'option_name')],
        true,
        ['lower(option_name)'],
    ],
    'covering expression survives integer cast null lookup planning' => [
        [
            'name' => 'idx_int_value',
            'sql' => 'CREATE INDEX idx_int_value ON wp_options(CAST(option_value AS INTEGER))',
        ],
        $point($expr('cast_integer', 'option_value'), null),
        [],
        [$expr('cast_integer', 'option_value')],
        true,
        ['cast_integer(option_value)'],
    ],
    'explicit noncovering metadata still allows expression-only covering' => [
        [
            'name' => 'idx_lower_meta_empty',
            'coveringColumns' => [],
            'sql' => 'CREATE INDEX idx_lower_meta_empty ON wp_options(lower(option_name))',
        ],
        $point($expr('lower', 'option_name'), 'siteurl'),
        [],
        [$expr('lower', 'option_name')],
        true,
        ['lower(option_name)'],
    ],
    'explicit noncovering metadata blocks mixed expression and column covering' => [
        [
            'name' => 'idx_lower_meta_empty',
            'coveringColumns' => [],
            'sql' => 'CREATE INDEX idx_lower_meta_empty ON wp_options(lower(option_name), autoload)',
        ],
        $point($expr('lower', 'option_name'), 'siteurl'),
        ['autoload'],
        [$expr('lower', 'option_name')],
        false,
        ['lower(option_name)'],
    ],
    'tail column inference covers mixed expression and later ordinary column' => [
        [
            'name' => 'idx_length_tail',
            'sql' => 'CREATE INDEX idx_length_tail ON wp_options(length(option_name), option_value, autoload)',
        ],
        $point($expr('length', 'option_name'), 8),
        ['option_value', 'autoload'],
        [$expr('length', 'option_name')],
        true,
        ['length(option_name)'],
    ],
    'tail column inference does not cover skipped ordinary column' => [
        [
            'name' => 'idx_length_tail',
            'sql' => 'CREATE INDEX idx_length_tail ON wp_options(length(option_name), option_value, autoload)',
        ],
        $point($expr('length', 'option_name'), 8),
        ['blog_id'],
        [$expr('length', 'option_name')],
        false,
        ['length(option_name)'],
    ],
];

foreach ($matrix as $name => [$index, $predicate, $neededColumns, $neededExpressions, $expectedCovering, $expectedExpressions]) {
    $tests['planner expression index covering current next33 matrix ' . $name] = static function (TestRunner $t) use ($index, $predicate, $neededColumns, $neededExpressions, $expectedCovering, $expectedExpressions): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$index], $predicate, [], $neededColumns, $neededExpressions);
        $t->same($index['name'], $plan['name']);
        $t->same($expectedCovering, $plan['covering']);
        $t->same($expectedExpressions, $plan['coveringExpressions']);
    };
}

return $tests;
