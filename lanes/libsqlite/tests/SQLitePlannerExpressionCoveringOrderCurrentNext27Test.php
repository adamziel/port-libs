<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$column = static fn (string $name): array => ['column' => $name];
$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexes = static fn (): array => [
    [
        'name' => 'idx_lower_cover_order',
        'rootPage' => 81,
        'estimatedRows' => 6000,
        'sql' => 'CREATE INDEX idx_lower_cover_order ON wp_options(lower(option_name), autoload, option_id DESC, option_value)',
    ],
    [
        'name' => 'idx_lower_plain',
        'rootPage' => 82,
        'estimatedRows' => 6000,
        'sql' => 'CREATE INDEX idx_lower_plain ON wp_options(lower(option_name))',
    ],
    [
        'name' => 'idx_length_cover_order',
        'rootPage' => 83,
        'estimatedRows' => 5000,
        'sql' => 'CREATE INDEX idx_length_cover_order ON wp_options(length(option_name), autoload DESC, option_name)',
    ],
    [
        'name' => 'idx_int_cover_order',
        'rootPage' => 84,
        'estimatedRows' => 3000,
        'sql' => 'CREATE INDEX idx_int_cover_order ON wp_options(CAST(option_value AS INTEGER), option_name, autoload)',
    ],
    [
        'name' => 'idx_upper_partial_cover',
        'rootPage' => 85,
        'estimatedRows' => 2000,
        'sql' => "CREATE INDEX idx_upper_partial_cover ON wp_options(upper(option_name), autoload, option_value) WHERE autoload = 'yes'",
    ],
];

$tests = [
    'planner expression covering order current next27 parses trailing columns after expression' => static function (TestRunner $t): void {
        $columns = SQLiteCreateIndex::columnsAfterFirstExpression('CREATE INDEX idx ON wp_options(lower(option_name), autoload, option_id DESC, option_value)');
        $t->same(['autoload', 'option_id', 'option_value'], array_map(static fn ($column): string => $column->columnName, $columns));
        $t->same([false, true, false], array_map(static fn ($column): bool => $column->descending, $columns));
    },
    'planner expression covering order current next27 returns empty trailing columns for ordinary index' => static function (TestRunner $t): void {
        $t->same([], SQLiteCreateIndex::columnsAfterFirstExpression('CREATE INDEX idx ON wp_options(option_name, autoload)'));
    },
    'planner expression covering order current next27 stops trailing parse at second expression' => static function (TestRunner $t): void {
        $columns = SQLiteCreateIndex::columnsAfterFirstExpression('CREATE INDEX idx ON wp_options(lower(option_name), length(option_value), autoload)');
        $t->same([], $columns);
    },
    'planner expression covering order current next27 infers covering columns from expression index tail' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload', 'option_id', 'option_value']);
        $t->same('idx_lower_cover_order', $plan['name']);
        $t->same(true, $plan['covering']);
        $t->same(['autoload', 'option_id', 'option_value'], $plan['trailingColumns']);
    },
    'planner expression covering order current next27 does not infer source column as covered' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [], ['option_name']);
        $t->same('idx_lower_cover_order', $plan['name']);
        $t->same(false, $plan['covering']);
    },
    'planner expression covering order current next27 preserves explicit covering metadata override' => static function (TestRunner $t) use ($expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([
            ['name' => 'idx_meta_cover', 'coveringColumns' => ['option_name', 'autoload'], 'sql' => 'CREATE INDEX idx_meta_cover ON wp_options(lower(option_name), autoload)'],
        ], $point($expr('lower', 'option_name'), 'home'), [], ['option_name', 'autoload']);
        $t->same(true, $plan['covering']);
        $t->same(['autoload'], $plan['trailingColumns']);
    },
    'planner expression covering order current next27 satisfies trailing order after point expression lookup' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC']], ['autoload', 'option_id']);
        $t->same('idx_lower_cover_order', $plan['name']);
        $t->same(true, $plan['orderBySatisfied']);
        $t->same(true, $plan['covering']);
    },
    'planner expression covering order current next27 rejects trailing order direction mismatch' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'ASC']], ['autoload', 'option_id']);
        $t->same('idx_lower_cover_order', $plan['name']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner expression covering order current next27 rejects trailing order column gap' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [['column' => 'option_id', 'direction' => 'DESC']], ['autoload', 'option_id']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner expression covering order current next27 rejects trailing order after range expression scan' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $range($expr('lower', 'option_name'), '>=', 'plugin_'), [['column' => 'autoload']], ['autoload']);
        $t->same('idx_lower_cover_order', $plan['name']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner expression covering order current next27 keeps expression order compatibility for single order term' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $range($expr('lower', 'option_name'), '>=', 'plugin_'), [['column' => 'option_name']], ['autoload']);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner expression covering order current next27 ranks trailing order plan ahead of plain expression index' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC']], ['autoload', 'option_id']);
        $t->same('idx_lower_cover_order', $plans[0]['name']);
        $t->same(true, $plans[0]['orderBySatisfied']);
        $t->same(false, $plans[1]['orderBySatisfied']);
    },
    'planner expression covering order current next27 ranks covering tail ahead when estimates tie' => static function (TestRunner $t) use ($expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([
            ['name' => 'idx_lower_plain', 'estimatedRows' => 4000, 'sql' => 'CREATE INDEX idx_lower_plain ON wp_options(lower(option_name))'],
            ['name' => 'idx_lower_tail', 'estimatedRows' => 4000, 'sql' => 'CREATE INDEX idx_lower_tail ON wp_options(lower(option_name), autoload, option_value)'],
        ], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload', 'option_value']);
        $t->same('idx_lower_tail', $plans[0]['name']);
        $t->same(true, $plans[0]['covering']);
    },
    'planner expression covering order current next27 handles descending ordinary tail after length expression' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('length', 'option_name'), 8), [['column' => 'autoload', 'direction' => 'DESC'], ['column' => 'option_name']], ['autoload', 'option_name']);
        $t->same('idx_length_cover_order', $plan['name']);
        $t->same(true, $plan['orderBySatisfied']);
        $t->same(true, $plan['covering']);
    },
    'planner expression covering order current next27 rejects ascending ordinary tail after length expression' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('length', 'option_name'), 8), [['column' => 'autoload', 'direction' => 'ASC']], ['autoload']);
        $t->same('idx_length_cover_order', $plan['name']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner expression covering order current next27 handles integer cast tail covering order' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('cast_integer', 'option_value'), 42), [['column' => 'option_name'], ['column' => 'autoload']], ['option_name', 'autoload']);
        $t->same('idx_int_cover_order', $plan['name']);
        $t->same(true, $plan['orderBySatisfied']);
        $t->same(true, $plan['covering']);
    },
    'planner expression covering order current next27 rejects missing tail coverage for integer cast index' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('cast_integer', 'option_value'), 42), [['column' => 'option_name']], ['option_name', 'option_value']);
        $t->same('idx_int_cover_order', $plan['name']);
        $t->same(false, $plan['covering']);
    },
    'planner expression covering order current next27 partial expression tail uses ordinary proof term' => static function (TestRunner $t) use ($indexes, $expr, $point, $column, $and): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $and($point($expr('upper', 'option_name'), 'SITEURL'), $point($column('autoload'), 'yes')), [['column' => 'autoload']], ['autoload', 'option_value']);
        $t->same('idx_upper_partial_cover', $plan['name']);
        $t->same(true, $plan['partial']);
        $t->same(true, $plan['covering']);
    },
    'planner expression covering order current next27 rejects partial expression tail without proof term' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([$indexes()[4]], $point($expr('upper', 'option_name'), 'SITEURL'), [['column' => 'autoload']], ['autoload']);
        $t->same([], $plans);
    },
    'planner expression covering order current next27 validates inferred needed columns' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [], ['']));
    },
    'planner expression covering order current next27 validates trailing order column name' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [['column' => '']], ['autoload']));
    },
    'planner expression covering order current next27 validates trailing order direction' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [['column' => 'autoload', 'direction' => 'SIDEWAYS']], ['autoload']));
    },
    'planner expression covering order current next27 preserves root page for inferred covering plan' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [['column' => 'autoload']], ['autoload']);
        $t->same(81, $plan['rootPage']);
    },
    'planner expression covering order current next27 reports trailing columns for noncovering plain expression index' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([$indexes()[1]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload']);
        $t->same('idx_lower_plain', $plans[0]['name']);
        $t->same([], $plans[0]['trailingColumns']);
        $t->same(false, $plans[0]['covering']);
    },
    'planner expression covering order current next27 supports table-qualified ordinary tail terms' => static function (TestRunner $t) use ($expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([
            ['name' => 'idx_qualified_tail', 'sql' => 'CREATE INDEX idx_qualified_tail ON wp_options(lower(option_name), wp_options.autoload, wp_options.option_id DESC)'],
        ], $point($expr('lower', 'option_name'), 'siteurl'), [['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC']], ['autoload', 'option_id']);
        $t->same(true, $plan['orderBySatisfied']);
        $t->same(true, $plan['covering']);
    },
    'planner expression covering order current next27 keeps name tiebreak after equal inferred costs' => static function (TestRunner $t) use ($expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([
            ['name' => 'idx_z_tail', 'estimatedRows' => 5000, 'sql' => 'CREATE INDEX idx_z_tail ON wp_options(lower(option_name), autoload)'],
            ['name' => 'idx_a_tail', 'estimatedRows' => 5000, 'sql' => 'CREATE INDEX idx_a_tail ON wp_options(lower(option_name), autoload)'],
        ], $point($expr('lower', 'option_name'), 'siteurl'), [['column' => 'autoload']], ['autoload']);
        $t->same(['idx_a_tail', 'idx_z_tail'], array_column($plans, 'name'));
    },
];

return $tests;
