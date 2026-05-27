<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoveringIndexPlan;

$target = 'm';
$outerColumns = ['o.option_id', 'o.option_name', 'o.blog_id', 'o.autoload', 'site_id'];
$col = static fn (string $table, string $column): array => ['table' => $table, 'column' => $column];
$point = static fn (string $table, string $column, mixed $value): array => ['operator' => '=', 'left' => ['table' => $table, 'column' => $column], 'right' => $value];
$join = static fn (string $innerColumn, string $outerTable, string $outerColumn): array => ['operator' => '=', 'left' => ['table' => 'm', 'column' => $innerColumn], 'right' => ['table' => $outerTable, 'column' => $outerColumn]];
$reverseJoin = static fn (string $outerTable, string $outerColumn, string $innerColumn): array => ['operator' => '=', 'left' => ['table' => $outerTable, 'column' => $outerColumn], 'right' => ['table' => 'm', 'column' => $innerColumn]];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['table' => 'm', 'column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['table' => 'm', 'column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexes = static fn (): array => [
    [
        'name' => 'idx_meta_option',
        'rootPage' => 81,
        'estimatedRows' => 50000,
        'sql' => 'CREATE INDEX idx_meta_option ON wp_postmeta(option_id)',
    ],
    [
        'name' => 'idx_meta_option_key_value',
        'rootPage' => 82,
        'estimatedRows' => 3000,
        'sql' => 'CREATE INDEX idx_meta_option_key_value ON wp_postmeta(option_id, meta_key, meta_value)',
    ],
    [
        'name' => 'idx_meta_blog_option_key',
        'rootPage' => 83,
        'estimatedRows' => 8000,
        'sql' => 'CREATE INDEX idx_meta_blog_option_key ON wp_postmeta(blog_id, option_id, meta_key)',
    ],
    [
        'name' => 'idx_meta_key_option_desc',
        'rootPage' => 84,
        'estimatedRows' => 12000,
        'sql' => 'CREATE INDEX idx_meta_key_option_desc ON wp_postmeta(meta_key, option_id DESC, meta_value)',
    ],
    [
        'name' => 'idx_public_meta_option_key',
        'rootPage' => 85,
        'estimatedRows' => 1500,
        'sql' => "CREATE INDEX idx_public_meta_option_key ON wp_postmeta(option_id, meta_key, meta_value) WHERE meta_key IS NOT NULL",
    ],
    [
        'name' => 'idx_autoload_join',
        'rootPage' => 86,
        'estimatedRows' => 900,
        'sql' => "CREATE INDEX idx_autoload_join ON wp_postmeta(autoload, option_id, meta_key, meta_value) WHERE autoload = 'yes'",
    ],
    [
        'name' => 'idx_plugin_meta',
        'rootPage' => 87,
        'estimatedRows' => 650,
        'sql' => "CREATE INDEX idx_plugin_meta ON wp_postmeta(option_id, meta_key, meta_value) WHERE meta_key >= 'plugin_'",
    ],
    [
        'name' => 'idx_nonnull_metakey',
        'rootPage' => 88,
        'estimatedRows' => 700,
        'sql' => 'CREATE INDEX idx_nonnull_metakey ON wp_postmeta(meta_key, meta_value) WHERE meta_key IS NOT NULL',
    ],
];

$tests = [
    'planner covering index join current next26 chooses covering inner lookup for current option row' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id', 'm.meta_key', 'm.meta_value']);
        $t->same('idx_meta_option_key_value', $plan['name']);
        $t->same(true, $plan['covering']);
    },
    'planner covering index join current next26 records current next loop metadata' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id']);
        $t->same('current-next', $plan['joinLoop']);
        $t->same('m', $plan['targetAlias']);
    },
    'planner covering index join current next26 records deferred equality dependency' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id']);
        $t->same(['option_id' => 'o.option_id'], $plan['outerDependencies']);
        $t->same(['option_id'], $plan['deferredEqualityColumns']);
    },
    'planner covering index join current next26 preserves selected root page' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same(82, $plan['rootPage']);
    },
    'planner covering index join current next26 estimates dynamic equality selectivity' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id']);
        $t->same(240, $plan['estimatedRows']);
    },
    'planner covering index join current next26 keeps residual predicate marker' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id']);
        $t->same(true, $plan['residualPredicateRequired']);
    },
    'planner covering index join current next26 accepts reversed join equality operands' => static function (TestRunner $t) use ($indexes, $reverseJoin, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $reverseJoin('o', 'option_id', 'option_id'), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same('idx_meta_option_key_value', $plan['name']);
        $t->same(['option_id' => 'o.option_id'], $plan['outerDependencies']);
    },
    'planner covering index join current next26 combines outer equality and literal key equality' => static function (TestRunner $t) use ($indexes, $join, $point, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $and($join('option_id', 'o', 'option_id'), $point('m', 'meta_key', '_thumbnail_id')), 'm', $outerColumns, ['m.option_id', 'm.meta_key', 'm.meta_value']);
        $t->same(['option_id', 'meta_key'], $plan['usedColumns']);
        $t->same(2, $plan['equalityPrefix']);
    },
    'planner covering index join current next26 estimates two equality prefix columns' => static function (TestRunner $t) use ($indexes, $join, $point, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $and($join('option_id', 'o', 'option_id'), $point('m', 'meta_key', '_thumbnail_id')), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same(10, $plan['estimatedRows']);
    },
    'planner covering index join current next26 adds range after current equality prefix' => static function (TestRunner $t) use ($indexes, $join, $range, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $and($join('option_id', 'o', 'option_id'), $range('meta_key', '>=', 'plugin_')), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same('meta_key', $plan['rangeColumn']);
        $t->same(['option_id', 'meta_key'], $plan['usedColumns']);
    },
    'planner covering index join current next26 estimates equality plus range rows' => static function (TestRunner $t) use ($indexes, $join, $range, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $and($join('option_id', 'o', 'option_id'), $range('meta_key', '>=', 'plugin_')), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same(13, $plan['estimatedRows']);
    },
    'planner covering index join current next26 satisfies order after dynamic equality prefix' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id', 'm.meta_key'], [['column' => 'm.meta_key']]);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner covering index join current next26 rejects wrong order direction after prefix' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[1]], $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id', 'm.meta_key'], [['column' => 'm.meta_key', 'direction' => 'DESC']]);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner covering index join current next26 records descending index equality prefix' => static function (TestRunner $t) use ($indexes, $join, $point, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[3]], $and($point('m', 'meta_key', '_wp_attachment_metadata'), $join('option_id', 'o', 'option_id')), 'm', $outerColumns, ['m.meta_key', 'm.option_id'], [['column' => 'm.option_id', 'direction' => 'DESC']]);
        $t->same('idx_meta_key_option_desc', $plan['name']);
        $t->same(['meta_key', 'option_id'], $plan['usedColumns']);
        $t->same(2, $plan['equalityPrefix']);
    },
    'planner covering index join current next26 uses outer blog id as leading prefix' => static function (TestRunner $t) use ($indexes, $join, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $and($join('blog_id', 'o', 'blog_id'), $join('option_id', 'o', 'option_id')), 'm', $outerColumns, ['m.blog_id', 'm.option_id', 'm.meta_key']);
        $t->same('idx_meta_blog_option_key', $plan['name']);
        $t->same(['blog_id', 'option_id'], $plan['usedColumns']);
    },
    'planner covering index join current next26 records multiple outer dependencies' => static function (TestRunner $t) use ($indexes, $join, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[2]], $and($join('blog_id', 'o', 'blog_id'), $join('option_id', 'o', 'option_id')), 'm', $outerColumns, ['m.blog_id', 'm.option_id']);
        $t->same(['blog_id' => 'o.blog_id', 'option_id' => 'o.option_id'], $plan['outerDependencies']);
    },
    'planner covering index join current next26 stops prefix when leading join column is missing' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans([$indexes()[2]], $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id']);
        $t->same([], $plans);
    },
    'planner covering index join current next26 ignores join to unknown outer column' => static function (TestRunner $t) use ($indexes, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans($indexes(), ['operator' => '=', 'left' => ['table' => 'm', 'column' => 'option_id'], 'right' => ['table' => 'x', 'column' => 'option_id']], 'm', $outerColumns, ['m.option_id']);
        $t->same([], $plans);
    },
    'planner covering index join current next26 ignores equality between two target columns' => static function (TestRunner $t) use ($indexes, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans($indexes(), ['operator' => '=', 'left' => ['table' => 'm', 'column' => 'option_id'], 'right' => ['table' => 'm', 'column' => 'blog_id']], 'm', $outerColumns, ['m.option_id']);
        $t->same([], $plans);
    },
    'planner covering index join current next26 accepts unqualified inner column for target loop' => static function (TestRunner $t) use ($indexes, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), ['operator' => '=', 'left' => ['column' => 'option_id'], 'right' => ['table' => 'o', 'column' => 'option_id']], 'm', $outerColumns, ['option_id', 'meta_key']);
        $t->same('idx_meta_option_key_value', $plan['name']);
    },
    'planner covering index join current next26 accepts unqualified outer column listed by name' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), ['operator' => '=', 'left' => ['table' => 'm', 'column' => 'option_id'], 'right' => ['column' => 'site_id']], 'm', ['site_id'], ['m.option_id']);
        $t->same(['option_id' => 'site_id'], $plan['outerDependencies']);
    },
    'planner covering index join current next26 ranks covering current lookup before narrow index' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans([$indexes()[0], $indexes()[1]], $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id', 'm.meta_key', 'm.meta_value']);
        $t->same(['idx_meta_option_key_value', 'idx_meta_option'], array_column($plans, 'name'));
    },
    'planner covering index join current next26 reports non covering narrow index' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans([$indexes()[0]], $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same(false, $plans[0]['covering']);
    },
    'planner covering index join current next26 strips target alias from needed columns' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[1]], $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id', 'm.meta_key', 'm.meta_value']);
        $t->same(true, $plan['covering']);
    },
    'planner covering index join current next26 keeps non target needed column non covering' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[1]], $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id', 'o.option_name']);
        $t->same(false, $plan['covering']);
    },
    'planner covering index join current next26 accepts literal equality alongside current equality' => static function (TestRunner $t) use ($indexes, $join, $point, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $and($join('option_id', 'o', 'option_id'), $point('m', 'meta_key', '_edit_lock')), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same(2, $plan['equalityPrefix']);
    },
    'planner covering index join current next26 accepts IN list after current equality' => static function (TestRunner $t) use ($indexes, $join, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), $and($join('option_id', 'o', 'option_id'), ['operator' => 'IN', 'left' => ['table' => 'm', 'column' => 'meta_key'], 'values' => ['_edit_lock', '_edit_last', null]]), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same(['option_id', 'meta_key'], $plan['usedColumns']);
        $t->same(2, $plan['equalityPrefix']);
    },
    'planner covering index join current next26 rejects all null IN list after current equality' => static function (TestRunner $t) use ($indexes, $join, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[1]], $and($join('option_id', 'o', 'option_id'), ['operator' => 'IN', 'left' => ['table' => 'm', 'column' => 'meta_key'], 'values' => [null, null]]), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same(['option_id'], $plan['usedColumns']);
    },
    'planner covering index join current next26 accepts BETWEEN after current equality' => static function (TestRunner $t) use ($indexes, $join, $between, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[1]], $and($join('option_id', 'o', 'option_id'), $between('meta_key', '_a', '_z')), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same('meta_key', $plan['rangeColumn']);
    },
    'planner covering index join current next26 accepts reversed range literal around target column' => static function (TestRunner $t) use ($indexes, $join, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[1]], $and($join('option_id', 'o', 'option_id'), ['operator' => '<=', 'left' => '_a', 'right' => ['table' => 'm', 'column' => 'meta_key']]), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same('meta_key', $plan['rangeColumn']);
    },
    'planner covering index join current next26 proves IS NOT NULL partial from dynamic equality' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[7]], $join('meta_key', 'o', 'option_name'), 'm', $outerColumns, ['m.meta_key', 'm.meta_value']);
        $t->same('idx_nonnull_metakey', $plan['name']);
        $t->same(true, $plan['partial']);
    },
    'planner covering index join current next26 does not prove literal partial from unknown outer value' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans([$indexes()[5]], $join('autoload', 'o', 'autoload'), 'm', $outerColumns, ['m.autoload', 'm.option_id']);
        $t->same([], $plans);
    },
    'planner covering index join current next26 proves literal partial when same literal is present' => static function (TestRunner $t) use ($indexes, $join, $point, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[5]], $and($join('option_id', 'o', 'option_id'), $point('m', 'autoload', 'yes')), 'm', $outerColumns, ['m.autoload', 'm.option_id', 'm.meta_key']);
        $t->same('idx_autoload_join', $plan['name']);
    },
    'planner covering index join current next26 rejects literal partial with conflicting literal' => static function (TestRunner $t) use ($indexes, $join, $point, $and, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans([$indexes()[5]], $and($join('option_id', 'o', 'option_id'), $point('m', 'autoload', 'no')), 'm', $outerColumns, ['m.autoload', 'm.option_id']);
        $t->same([], $plans);
    },
    'planner covering index join current next26 proves range partial with literal next constraint' => static function (TestRunner $t) use ($indexes, $join, $range, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[6]], $and($join('option_id', 'o', 'option_id'), $range('meta_key', '>=', 'plugin_cache')), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same('idx_plugin_meta', $plan['name']);
        $t->same(true, $plan['partial']);
    },
    'planner covering index join current next26 rejects range partial when literal bound is too broad' => static function (TestRunner $t) use ($indexes, $join, $range, $and, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans([$indexes()[6]], $and($join('option_id', 'o', 'option_id'), $range('meta_key', '>=', 'admin_')), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same([], $plans);
    },
    'planner covering index join current next26 preserves plain fallback when partial is unproved' => static function (TestRunner $t) use ($indexes, $join, $range, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[1], $indexes()[6]], $and($join('option_id', 'o', 'option_id'), $range('meta_key', '>=', 'admin_')), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same('idx_meta_option_key_value', $plan['name']);
        $t->same(false, $plan['partial']);
    },
    'planner covering index join current next26 ranks proved partial covering ahead of plain fallback' => static function (TestRunner $t) use ($indexes, $join, $range, $and, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans([$indexes()[1], $indexes()[6]], $and($join('option_id', 'o', 'option_id'), $range('meta_key', '>=', 'plugin_cache')), 'm', $outerColumns, ['m.option_id', 'm.meta_key', 'm.meta_value']);
        $t->same(['idx_plugin_meta', 'idx_meta_option_key_value'], array_column($plans, 'name'));
    },
    'planner covering index join current next26 validates target alias' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::rankedJoinPlans($indexes(), $join('option_id', 'o', 'option_id'), '', $outerColumns, ['m.option_id']));
    },
    'planner covering index join current next26 validates outer columns' => static function (TestRunner $t) use ($indexes, $join): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::rankedJoinPlans($indexes(), $join('option_id', 'o', 'option_id'), 'm', [''], ['m.option_id']));
    },
    'planner covering index join current next26 validates missing create index sql' => static function (TestRunner $t) use ($join, $outerColumns): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::rankedJoinPlans([['name' => 'bad']], $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id']));
    },
    'planner covering index join current next26 validates order direction' => static function (TestRunner $t) use ($indexes, $join, $outerColumns): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::rankedJoinPlans($indexes(), $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id'], [['column' => 'm.meta_key', 'direction' => 'SIDEWAYS']]));
    },
    'planner covering index join current next26 validates non scalar range literal' => static function (TestRunner $t) use ($indexes, $join, $and, $outerColumns): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoveringIndexPlan::rankedJoinPlans($indexes(), $and($join('option_id', 'o', 'option_id'), ['operator' => '>=', 'left' => ['table' => 'm', 'column' => 'meta_key'], 'right' => ['bad']]), 'm', $outerColumns, ['m.option_id']));
    },
    'planner covering index join current next26 ignores expression index definitions' => static function (TestRunner $t) use ($join, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans([
            ['name' => 'idx_expr', 'sql' => 'CREATE INDEX idx_expr ON wp_postmeta(lower(meta_key))'],
        ], $join('meta_key', 'o', 'option_name'), 'm', $outerColumns, ['m.meta_key']);
        $t->same([], $plans);
    },
    'planner covering index join current next26 orders same cost plans by name' => static function (TestRunner $t) use ($join, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans([
            ['name' => 'idx_b', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_b ON wp_postmeta(option_id)'],
            ['name' => 'idx_a', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_a ON wp_postmeta(option_id)'],
        ], $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id']);
        $t->same(['idx_a', 'idx_b'], array_column($plans, 'name'));
    },
    'planner covering index join current next26 ranks order satisfied plan ahead when close' => static function (TestRunner $t) use ($join, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans([
            ['name' => 'idx_plain', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_plain ON wp_postmeta(option_id, meta_key)'],
            ['name' => 'idx_desc', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_desc ON wp_postmeta(option_id, meta_key DESC)'],
        ], $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id', 'm.meta_key'], [['column' => 'm.meta_key', 'direction' => 'DESC']]);
        $t->same('idx_desc', $plans[0]['name']);
    },
    'planner covering index join current next26 ranks covering plan ahead when estimates tie' => static function (TestRunner $t) use ($join, $outerColumns): void {
        $plans = SQLiteCoveringIndexPlan::rankedJoinPlans([
            ['name' => 'idx_short', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_short ON wp_postmeta(option_id)'],
            ['name' => 'idx_cover', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_cover ON wp_postmeta(option_id, meta_key)'],
        ], $join('option_id', 'o', 'option_id'), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same('idx_cover', $plans[0]['name']);
    },
    'planner covering index join current next26 supports IS NOT NULL as target range term' => static function (TestRunner $t) use ($indexes, $join, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[1]], $and($join('option_id', 'o', 'option_id'), ['operator' => 'IS NOT NULL', 'left' => ['table' => 'm', 'column' => 'meta_key']]), 'm', $outerColumns, ['m.option_id', 'm.meta_key']);
        $t->same('meta_key', $plan['rangeColumn']);
    },
    'planner covering index join current next26 returns null when no inner index is usable' => static function (TestRunner $t) use ($indexes, $col, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin($indexes(), ['operator' => '=', 'left' => $col('m', 'missing'), 'right' => $col('o', 'option_id')], 'm', $outerColumns, ['m.missing']);
        $t->same(null, $plan);
    },
    'planner covering index join current next26 keeps dependency list scoped to used prefix' => static function (TestRunner $t) use ($indexes, $join, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[1]], $and($join('option_id', 'o', 'option_id'), $join('meta_value', 'o', 'option_name')), 'm', $outerColumns, ['m.option_id', 'm.meta_value']);
        $t->same(['option_id' => 'o.option_id'], $plan['outerDependencies']);
    },
    'planner covering index join current next26 supports current row dependency in order-compatible composite index' => static function (TestRunner $t) use ($indexes, $join, $and, $outerColumns): void {
        $plan = SQLiteCoveringIndexPlan::chooseJoin([$indexes()[2]], $and($join('blog_id', 'o', 'blog_id'), $join('option_id', 'o', 'option_id')), 'm', $outerColumns, ['m.blog_id', 'm.option_id', 'm.meta_key'], [['column' => 'm.meta_key']]);
        $t->same(true, $plan['orderBySatisfied']);
    },
];

return $tests;
