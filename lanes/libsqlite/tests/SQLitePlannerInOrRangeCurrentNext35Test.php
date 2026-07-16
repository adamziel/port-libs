<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteMultiColumnRangePlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$in = static fn (string $column, array $values): array => ['operator' => 'IN', 'left' => ['column' => $column], 'values' => $values];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$or = static fn (array ...$terms): array => ['operator' => 'OR', 'terms' => $terms];

$indexes = static fn (): array => [
    [
        'name' => 'idx_blog_name_autoload',
        'rootPage' => 91,
        'estimatedRows' => 30000,
        'sql' => 'CREATE INDEX idx_blog_name_autoload ON wp_options(blog_id, option_name, autoload)',
    ],
    [
        'name' => 'idx_blog_autoload_name',
        'rootPage' => 92,
        'estimatedRows' => 24000,
        'sql' => 'CREATE INDEX idx_blog_autoload_name ON wp_options(blog_id, autoload, option_name)',
    ],
    [
        'name' => 'idx_autoload_name',
        'rootPage' => 93,
        'estimatedRows' => 12000,
        'sql' => 'CREATE INDEX idx_autoload_name ON wp_options(autoload, option_name)',
    ],
];

$basicOr = static fn () => $or(
    $and($point('blog_id', 1), $range('option_name', '>=', '_transient_')),
    $and($point('blog_id', 2), $between('option_name', 'site', 'site_zzzz')),
);

$tests = [
    'planner in or range current next35 builds single index OR range union' => static function (TestRunner $t) use ($indexes, $basicOr): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $basicOr());
        $t->same('single-index-or', $plan['strategy']);
        $t->same(['idx_blog_name_autoload'], $plan['indexNames']);
    },
    'planner in or range current next35 marks rowid union for multiple OR arms' => static function (TestRunner $t) use ($indexes, $basicOr): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $basicOr());
        $t->same(true, $plan['rowidUnionRequired']);
        $t->same(2, $plan['armCount']);
    },
    'planner in or range current next35 preserves arm root pages' => static function (TestRunner $t) use ($indexes, $basicOr): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $basicOr());
        $t->same([91], $plan['rootPages']);
    },
    'planner in or range current next35 reports one current next loop per point arm' => static function (TestRunner $t) use ($indexes, $basicOr): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $basicOr());
        $t->same(2, $plan['currentNextLoops']);
    },
    'planner in or range current next35 keeps current range column per arm' => static function (TestRunner $t) use ($indexes, $basicOr): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $basicOr());
        $t->same(['option_name'], $plan['rangeColumns']);
        $t->same(['blog_id', 'option_name'], $plan['arms'][0]['usedColumns']);
    },
    'planner in or range current next35 accepts IN equality prefix before range' => static function (TestRunner $t) use ($indexes, $in, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $and($in('blog_id', [1, 2, 3]), $range('option_name', '>=', '_')));
        $t->same(3, $plan['currentNextLoops']);
        $t->same(1, $plan['arms'][0]['equalityPrefix']);
    },
    'planner in or range current next35 deduplicates IN prefix seek values' => static function (TestRunner $t) use ($indexes, $in, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $and($in('blog_id', [1, 1, 2, null]), $range('option_name', '>=', '_')));
        $t->same(2, $plan['currentNextLoops']);
    },
    'planner in or range current next35 multiplies composite IN equality loops' => static function (TestRunner $t) use ($indexes, $in, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[1]], $and($in('blog_id', [1, 2]), $in('autoload', ['yes', 'no', 'yes']), $range('option_name', '>=', 'plugin_')));
        $t->same(4, $plan['currentNextLoops']);
        $t->same(['blog_id', 'autoload', 'option_name'], $plan['arms'][0]['usedColumns']);
    },
    'planner in or range current next35 sums IN loops across OR arms' => static function (TestRunner $t) use ($indexes, $in, $range, $and, $or): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $or(
            $and($in('blog_id', [1, 2]), $range('option_name', '>=', 'plugin_')),
            $and($in('blog_id', [3, 4, 5]), $range('option_name', '<', 'theme_')),
        ));
        $t->same(5, $plan['currentNextLoops']);
    },
    'planner in or range current next35 chooses alternate index per OR arm when cheaper' => static function (TestRunner $t) use ($indexes, $point, $range, $and, $or): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange($indexes(), $or(
            $and($point('blog_id', 1), $point('autoload', 'yes'), $range('option_name', '>=', 'plugin_')),
            $and($point('autoload', 'no'), $range('option_name', '>=', '_transient_')),
        ));
        $t->same('multi-index-or', $plan['strategy']);
        $t->same(['idx_blog_autoload_name', 'idx_autoload_name'], $plan['indexNames']);
    },
    'planner in or range current next35 rejects OR when one arm is not indexable' => static function (TestRunner $t) use ($indexes, $point, $range, $or): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $or($range('option_name', '>=', 'plugin_'), $point('blog_id', 1)));
        $t->same(null, $plan);
    },
    'planner in or range current next35 keeps residual next range inside OR arm' => static function (TestRunner $t) use ($indexes, $point, $range, $and, $or): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $or(
            $and($point('blog_id', 1), $range('option_name', '>=', 'plugin_'), $range('autoload', '<', 'z')),
            $and($point('blog_id', 2), $range('option_name', '>=', 'theme_')),
        ));
        $t->same(true, $plan['residualPredicateRequired']);
        $t->same(['autoload'], $plan['arms'][0]['residualRangeColumns']);
    },
    'planner in or range current next35 propagates order compatibility across arms' => static function (TestRunner $t) use ($indexes, $basicOr): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $basicOr(), [['column' => 'option_name']]);
        $t->same([true, true], array_column($plan['arms'], 'orderBySatisfied'));
    },
    'planner in or range current next35 estimates OR rows as arm sum' => static function (TestRunner $t) use ($indexes, $basicOr): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $basicOr());
        $t->same($plan['arms'][0]['estimatedRows'] + $plan['arms'][1]['estimatedRows'], $plan['estimatedRows']);
    },
    'planner in or range current next35 validates OR terms are predicates' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteMultiColumnRangePlan::chooseOrRange([], ['operator' => 'OR', 'terms' => ['bad']]));
    },
    'planner in or range current next35 validates OR term list' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteMultiColumnRangePlan::chooseOrRange([], ['operator' => 'OR', 'terms' => []]));
    },
];

$orRangeVariants = [
    ['>=', 'plugin_', '>=', 'theme_', 2],
    ['>', 'plugin_', '<', 'theme_', 2],
    ['<=', 'admin_', '>=', 'site', 2],
    ['<', 'blog', '>', 'widget', 2],
    ['>=', '_site_', '<=', '_transient_timeout_', 2],
    ['>', '_transient_', '<', 'z', 2],
    ['<=', 'option_z', '>=', 'plugin_a', 2],
    ['<', 'siteurl', '>=', 'template', 2],
];

foreach ($orRangeVariants as $offset => [$leftOperator, $leftValue, $rightOperator, $rightValue, $loops]) {
    $tests["planner in or range current next35 OR range variant {$offset} remains independently indexable"] = static function (TestRunner $t) use ($indexes, $point, $range, $and, $or, $leftOperator, $leftValue, $rightOperator, $rightValue, $loops): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $or(
            $and($point('blog_id', 1), $range('option_name', $leftOperator, $leftValue)),
            $and($point('blog_id', 2), $range('option_name', $rightOperator, $rightValue)),
        ));
        $t->same($loops, $plan['currentNextLoops']);
        $t->same(2, $plan['armCount']);
    };
}

$inPrefixVariants = [
    [[1], 'plugin_', 1],
    [[1, 2], 'plugin_', 2],
    [[1, 2, 3], 'plugin_', 3],
    [[1, 2, 3, 4], 'plugin_', 4],
    [[1, 1, 1, 2], 'plugin_', 2],
    [[null, 4, 5], 'theme_', 2],
    [[7, null, 8, 9], '_transient_', 3],
    [[3, 4, 5, 6, 7], 'site', 5],
    [[10, 11], 'widget_', 2],
    [[12, 13, 14], 'option_', 3],
];

foreach ($inPrefixVariants as $offset => [$values, $lower, $loops]) {
    $tests["planner in or range current next35 IN prefix variant {$offset} counts current next seeks"] = static function (TestRunner $t) use ($indexes, $in, $range, $and, $values, $lower, $loops): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $and($in('blog_id', $values), $range('option_name', '>=', $lower)));
        $t->same($loops, $plan['currentNextLoops']);
        $t->same('single-index-or', $plan['strategy']);
    };
}

$betweenArms = [
    ['plugin_a', 'plugin_z'],
    ['theme_a', 'theme_z'],
    ['site', 'site_zzzz'],
    ['_transient_', '_transient_timeout_zzzz'],
    ['widget_a', 'widget_z'],
    ['cache_a', 'cache_z'],
    ['rewrite_a', 'rewrite_z'],
    ['cron_a', 'cron_z'],
];

foreach ($betweenArms as $offset => [$lower, $upper]) {
    $tests["planner in or range current next35 BETWEEN arm variant {$offset} uses current range"] = static function (TestRunner $t) use ($indexes, $point, $between, $and, $lower, $upper): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $and($point('blog_id', 1), $between('option_name', $lower, $upper)));
        $t->same('BETWEEN', $plan['arms'][0]['rangeConstraint']['operator']);
        $t->same([$lower, $upper], [$plan['arms'][0]['rangeConstraint']['values']['lower'], $plan['arms'][0]['rangeConstraint']['values']['upper']]);
    };
}

$multiIndexVariants = [
    ['yes', 'plugin_', 'idx_blog_autoload_name'],
    ['no', '_transient_', 'idx_blog_autoload_name'],
    ['auto', 'site', 'idx_blog_autoload_name'],
    ['manual', 'theme_', 'idx_blog_autoload_name'],
    ['cron', 'widget_', 'idx_blog_autoload_name'],
];

foreach ($multiIndexVariants as $offset => [$autoload, $lower, $expected]) {
    $tests["planner in or range current next35 multi prefix arm {$offset} keeps best composite index"] = static function (TestRunner $t) use ($indexes, $point, $range, $and, $autoload, $lower, $expected): void {
        $plan = SQLiteMultiColumnRangePlan::chooseOrRange($indexes(), $and($point('blog_id', 1), $point('autoload', $autoload), $range('option_name', '>=', $lower)));
        $t->same($expected, $plan['indexNames'][0]);
        $t->same(['blog_id', 'autoload', 'option_name'], $plan['arms'][0]['usedColumns']);
    };
}

$rejectingPredicates = [
    $range('option_name', '>=', 'plugin_'),
    $and($point('blog_id', null), $range('option_name', '>=', 'plugin_')),
    $and($in('blog_id', [null]), $range('option_name', '>=', 'plugin_')),
    $point('blog_id', 1),
    $and($point('autoload', 'yes'), $range('option_value', '>=', 'a')),
];

foreach ($rejectingPredicates as $offset => $predicate) {
    $tests["planner in or range current next35 rejects unbounded arm {$offset}"] = static function (TestRunner $t) use ($indexes, $predicate): void {
        $t->same(null, SQLiteMultiColumnRangePlan::chooseOrRange([$indexes()[0]], $predicate));
    };
}

return $tests;
