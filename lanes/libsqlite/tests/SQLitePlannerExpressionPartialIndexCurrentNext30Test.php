<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $name): array => ['column' => $name];

$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$between = static fn (array $left, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => $left, 'lower' => $lower, 'upper' => $upper];
$inList = static fn (array $left, array $values): array => ['operator' => 'IN', 'left' => $left, 'values' => $values];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexes = [
    [
        'name' => 'idx_lower_plugin_expr_partial',
        'rootPage' => 301,
        'estimatedRows' => 900,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_lower_plugin_expr_partial ON wp_options(lower(option_name), autoload) WHERE lower(option_name) >= 'plugin_'",
    ],
    [
        'name' => 'idx_upper_theme_expr_partial',
        'rootPage' => 302,
        'estimatedRows' => 720,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_upper_theme_expr_partial ON wp_options(upper(option_name) DESC) WHERE upper(option_name) < 'THEME_'",
    ],
    [
        'name' => 'idx_length_core_expr_partial',
        'rootPage' => 303,
        'estimatedRows' => 540,
        'coveringColumns' => ['option_name'],
        'sql' => 'CREATE INDEX idx_length_core_expr_partial ON wp_options(length(option_name)) WHERE length(option_name) BETWEEN 4 AND 12',
    ],
    [
        'name' => 'idx_cast_priority_expr_partial',
        'rootPage' => 304,
        'estimatedRows' => 480,
        'coveringColumns' => ['option_value', 'autoload'],
        'sql' => 'CREATE INDEX idx_cast_priority_expr_partial ON wp_options(CAST(option_value AS INTEGER)) WHERE CAST(option_value AS INTEGER) IN (1,2,3,5,8)',
    ],
    [
        'name' => 'idx_lower_plugin_window_expr_partial',
        'rootPage' => 305,
        'estimatedRows' => 620,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_lower_plugin_window_expr_partial ON wp_options(lower(option_name)) WHERE lower(option_name) >= 'plugin_' AND lower(option_name) < 'plugin`'",
    ],
    [
        'name' => 'idx_lower_plugin_or_theme_expr_partial',
        'rootPage' => 306,
        'estimatedRows' => 810,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_lower_plugin_or_theme_expr_partial ON wp_options(lower(option_name)) WHERE lower(option_name) >= 'plugin_' OR lower(option_name) BETWEEN 'theme_' AND 'theme_zzzz'",
    ],
    [
        'name' => 'idx_lower_plugin_autoload_expr_partial',
        'rootPage' => 307,
        'estimatedRows' => 420,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_lower_plugin_autoload_expr_partial ON wp_options(lower(option_name), autoload) WHERE lower(option_name) >= 'plugin_' AND autoload = 'yes'",
    ],
];

$cases = [
    'lower range proves expression lower-bound partial' => [
        $range($expr('lower', 'option_name'), '>=', 'plugin_cache'),
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
    ],
    'lower strict range proves inclusive expression partial' => [
        $range($expr('lower', 'option_name'), '>', 'plugin_'),
        'idx_lower_plugin_or_theme_expr_partial',
        'range->',
        203,
    ],
    'lower broad range rejects expression lower-bound partial' => [
        $range($expr('lower', 'option_name'), '>=', 'option_'),
        null,
        null,
        null,
    ],
    'lower point proves expression lower-bound partial' => [
        $point($expr('lower', 'option_name'), 'plugin_cache'),
        'idx_lower_plugin_window_expr_partial',
        'point',
        7,
    ],
    'lower point before boundary rejects expression partial' => [
        $point($expr('lower', 'option_name'), 'admin_email'),
        null,
        null,
        null,
    ],
    'lower in-list proves expression partial when every value qualifies' => [
        $inList($expr('lower', 'option_name'), ['plugin_cache', 'plugin_theme', null]),
        'idx_lower_plugin_window_expr_partial',
        'IN',
        19,
    ],
    'lower in-list rejects expression partial when one value escapes' => [
        $inList($expr('lower', 'option_name'), ['plugin_cache', 'admin_email']),
        null,
        null,
        null,
    ],
    'upper range proves expression upper-bound partial' => [
        $range($expr('upper', 'option_name'), '<', 'SETTINGS_'),
        'idx_upper_theme_expr_partial',
        'range-<',
        180,
    ],
    'upper inclusive lower value rejects strict expression upper-bound partial' => [
        $range($expr('upper', 'option_name'), '<=', 'THEME_'),
        null,
        null,
        null,
    ],
    'upper point below strict partial boundary is usable' => [
        $point($expr('upper', 'option_name'), 'SITEURL'),
        'idx_upper_theme_expr_partial',
        'point',
        8,
    ],
    'upper point at strict boundary is rejected' => [
        $point($expr('upper', 'option_name'), 'THEME_'),
        null,
        null,
        null,
    ],
    'length between proves expression between partial' => [
        $between($expr('length', 'option_name'), 4, 8),
        'idx_length_core_expr_partial',
        'BETWEEN',
        54,
    ],
    'length lower-only range cannot prove expression between upper bound' => [
        $range($expr('length', 'option_name'), '>=', 4),
        null,
        null,
        null,
    ],
    'length point inside expression between partial is usable' => [
        $point($expr('length', 'option_name'), 7),
        'idx_length_core_expr_partial',
        'point',
        6,
    ],
    'length point outside expression between partial is rejected' => [
        $point($expr('length', 'option_name'), 15),
        null,
        null,
        null,
    ],
    'integer cast in-list subset proves expression partial' => [
        $inList($expr('cast_integer', 'option_value'), [1, 3, null]),
        'idx_cast_priority_expr_partial',
        'IN',
        15,
        [],
        false,
        ['option_value'],
        true,
    ],
    'integer cast point proves expression in-list partial' => [
        $point($expr('cast_integer', 'option_value'), 5),
        'idx_cast_priority_expr_partial',
        'point',
        5,
        [],
        false,
        ['option_value'],
        true,
    ],
    'integer cast in-list rejects value outside expression partial list' => [
        $inList($expr('cast_integer', 'option_value'), [1, 4]),
        null,
        null,
        null,
    ],
    'integer cast string search remains incompatible with integer expression index' => [
        $point($expr('cast_integer', 'option_value'), '5'),
        null,
        null,
        null,
    ],
    'and expression partial is proved by between current term' => [
        $between($expr('lower', 'option_name'), 'plugin_cache', 'plugin_z'),
        'idx_lower_plugin_window_expr_partial',
        'BETWEEN',
        62,
    ],
    'and expression partial rejects range missing upper proof' => [
        $range($expr('lower', 'option_name'), '>=', 'plugin_cache'),
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
    ],
    'and expression partial uses separate lower and upper expression terms' => [
        $and($range($expr('lower', 'option_name'), '>=', 'plugin_cache'), $range($expr('lower', 'option_name'), '<', 'plugin_z')),
        'idx_lower_plugin_window_expr_partial',
        'range->=',
        155,
    ],
    'and expression partial rejects separate upper term past window' => [
        $and($range($expr('lower', 'option_name'), '>=', 'plugin_cache'), $range($expr('lower', 'option_name'), '<', 'theme_')),
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
    ],
    'or expression partial accepts plugin arm' => [
        $range($expr('lower', 'option_name'), '>=', 'plugin_cache'),
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
    ],
    'or expression partial accepts theme between arm' => [
        $between($expr('lower', 'option_name'), 'theme_mods_', 'theme_mods_z'),
        'idx_lower_plugin_or_theme_expr_partial',
        'BETWEEN',
        81,
    ],
    'or expression partial rejects unrelated expression range' => [
        $range($expr('lower', 'option_name'), '<', 'admin_'),
        null,
        null,
        null,
    ],
    'expression partial combines expression and ordinary equality proof' => [
        $and($range($expr('lower', 'option_name'), '>=', 'plugin_cache'), $point($column('autoload'), 'yes')),
        'idx_lower_plugin_autoload_expr_partial',
        'range->=',
        105,
    ],
    'expression partial rejects missing ordinary equality proof' => [
        $range($expr('lower', 'option_name'), '>=', 'plugin_cache'),
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
    ],
    'expression partial rejects wrong ordinary equality proof' => [
        $and($range($expr('lower', 'option_name'), '>=', 'plugin_cache'), $point($column('autoload'), 'no')),
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
    ],
    'reversed expression lower operand proves lower-bound partial' => [
        ['operator' => '<=', 'left' => 'plugin_', 'right' => $expr('lower', 'option_name')],
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
    ],
    'reversed expression upper operand proves upper-bound partial' => [
        ['operator' => '>', 'left' => 'THEME_', 'right' => $expr('upper', 'option_name')],
        'idx_upper_theme_expr_partial',
        'range-<',
        180,
    ],
    'expression partial preserves descending order compatibility' => [
        $range($expr('upper', 'option_name'), '<', 'SETTINGS_'),
        'idx_upper_theme_expr_partial',
        'range-<',
        180,
        [['column' => 'option_name', 'direction' => 'DESC']],
        true,
    ],
    'expression partial rejects opposite descending order compatibility' => [
        $range($expr('upper', 'option_name'), '<', 'SETTINGS_'),
        'idx_upper_theme_expr_partial',
        'range-<',
        180,
        [['column' => 'option_name', 'direction' => 'ASC']],
        false,
    ],
    'expression partial reports covering columns for needed Application fields' => [
        $range($expr('lower', 'option_name'), '>=', 'plugin_cache'),
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
        [],
        false,
        ['option_name', 'autoload', 'option_value'],
        false,
    ],
    'expression partial reports non-covering when option_id is needed' => [
        $range($expr('lower', 'option_name'), '>=', 'plugin_cache'),
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
        [],
        false,
        ['option_id'],
        false,
    ],
    'expression partial validates scalar literal while proving range' => [
        ['operator' => '>=', 'left' => $expr('lower', 'option_name'), 'right' => ['bad']],
        InvalidArgumentException::class,
        null,
        null,
    ],
    'expression partial rejects unsupported trim expression predicate' => [
        ['operator' => '=', 'left' => ['function' => 'trim', 'column' => 'option_name'], 'right' => 'siteurl'],
        null,
        null,
        null,
    ],
    'expression partial ignores plain column partial index for expression proof' => [
        $range($expr('lower', 'option_name'), '>=', 'plugin_cache'),
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
    ],
    'expression partial prefers lower cost matching expression predicate' => [
        $point($expr('cast_integer', 'option_value'), 1),
        'idx_cast_priority_expr_partial',
        'point',
        5,
        [],
        false,
        ['option_value'],
        true,
    ],
    'expression partial handles qualified expression predicate column' => [
        ['operator' => '>=', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => 'plugin_cache'],
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
    ],
    'expression partial rejects all-null expression in-list proof' => [
        $inList($expr('lower', 'option_name'), [null]),
        null,
        null,
        null,
    ],
    'expression partial accepts non-null expression in-list for is not null style proof' => [
        $inList($expr('lower', 'option_name'), ['plugin_cache', null]),
        'idx_lower_plugin_window_expr_partial',
        'IN',
        10,
    ],
    'expression partial keeps root page for accepted expression proof' => [
        $range($expr('lower', 'option_name'), '>=', 'plugin_cache'),
        'idx_lower_plugin_or_theme_expr_partial',
        'range->=',
        203,
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['sqlite planner expression partial index current next30 ' . $name] = static function (TestRunner $t) use ($indexes, $case): void {
        [$predicate, $expectedName, $expectedOperator, $expectedRows] = $case;
        $orderBy = $case[4] ?? [];
        $expectedOrder = $case[5] ?? false;
        $neededColumns = $case[6] ?? ['option_name'];
        $expectedCovering = $case[7] ?? ($expectedName !== null);

        if (is_string($expectedName) && is_a($expectedName, Throwable::class, true)) {
            $t->throws($expectedName, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, $predicate, $orderBy, $neededColumns));
            return;
        }

        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes, $predicate, $orderBy, $neededColumns);
        $t->same($expectedName, $plan['name'] ?? null);
        if ($expectedName === null) {
            return;
        }

        $t->same($expectedOperator, $plan['operator']);
        $t->same($expectedRows, $plan['estimatedRows']);
        $t->same(true, $plan['partial']);
        $t->same($expectedOrder, $plan['orderBySatisfied']);
        $t->same($expectedCovering, $plan['covering']);
        if ($expectedName === 'idx_lower_plugin_expr_partial') {
            $t->same(301, $plan['rootPage']);
        }
        if ($expectedName === 'idx_lower_plugin_or_theme_expr_partial') {
            $t->same(306, $plan['rootPage']);
        }
        if ($expectedName === 'idx_lower_plugin_window_expr_partial') {
            $t->same(305, $plan['rootPage']);
        }
    };
}

return $tests;
