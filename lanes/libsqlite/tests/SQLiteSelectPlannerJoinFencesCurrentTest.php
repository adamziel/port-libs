<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJoinOrderPlan;

$statRows = [
    ['tbl' => 'wp_options', 'idx' => null, 'stat' => '12000'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_name', 'stat' => '12000 1'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_autoload', 'stat' => '12000 6000'],
    ['tbl' => 'wp_postmeta', 'idx' => null, 'stat' => '240000'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_key', 'stat' => '240000 800'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_post_id', 'stat' => '240000 40'],
    ['tbl' => 'wp_posts', 'idx' => null, 'stat' => '30000'],
    ['tbl' => 'wp_posts', 'idx' => 'wp_posts_id', 'stat' => '30000 1'],
    ['tbl' => 'wp_posts', 'idx' => 'wp_posts_type_status', 'stat' => '30000 6000 1000'],
];

$indexes = [
    ['name' => 'wp_options_name', 'table' => 'wp_options', 'columns' => ['option_name'], 'unique' => true],
    ['name' => 'wp_options_autoload', 'table' => 'wp_options', 'columns' => ['autoload']],
    ['name' => 'wp_postmeta_key', 'table' => 'wp_postmeta', 'columns' => ['meta_key']],
    ['name' => 'wp_postmeta_post_id', 'table' => 'wp_postmeta', 'columns' => ['post_id']],
    ['name' => 'wp_posts_id', 'table' => 'wp_posts', 'columns' => ['ID'], 'unique' => true],
    ['name' => 'wp_posts_type_status', 'table' => 'wp_posts', 'columns' => ['post_type', 'post_status']],
];

$point = static fn (string $column, mixed $value): array => ['column' => $column, 'operator' => '=', 'value' => $value];
$join = static fn (string $leftTable, string $leftColumn, string $rightTable, string $rightColumn, string $joinType = 'INNER'): array => [
    'leftTable' => $leftTable,
    'leftColumn' => $leftColumn,
    'rightTable' => $rightTable,
    'rightColumn' => $rightColumn,
    'joinType' => $joinType,
];
$cross = static fn (string $leftTable, string $rightTable): array => [
    'leftTable' => $leftTable,
    'rightTable' => $rightTable,
    'joinType' => 'CROSS',
];

$tests = [
    'select planner joins current keeps cross join left operand first despite selective right table' => static function (TestRunner $t) use ($statRows, $indexes, $point, $cross): void {
        $plan = SQLiteJoinOrderPlan::choose(
            $statRows,
            $indexes,
            ['wp_options', 'wp_postmeta'],
            ['wp_postmeta' => [$point('meta_key', '_thumbnail_id')]],
            [$cross('wp_options', 'wp_postmeta')],
        );

        $t->same(['wp_options', 'wp_postmeta'], $plan['tables']);
        $t->same('table-scan', $plan['loops'][0]['access']);
        $t->same('wp_postmeta_key', $plan['loops'][1]['index']);
        $t->same([], $plan['loops'][1]['joinColumns']);
        $t->same(['type' => 'CROSS', 'outerTable' => 'wp_options'], $plan['loops'][1]['joinFence']);
        $t->same('SEARCH wp_postmeta USING INDEX wp_postmeta_key (meta_key=?)', $plan['detail'][1]);
    },
    'select planner joins current ranks inner join by cost when no fence exists' => static function (TestRunner $t) use ($statRows, $indexes, $point, $join): void {
        $plan = SQLiteJoinOrderPlan::choose(
            $statRows,
            $indexes,
            ['wp_posts', 'wp_postmeta'],
            ['wp_postmeta' => [$point('meta_key', '_thumbnail_id')]],
            [$join('wp_posts', 'ID', 'wp_postmeta', 'post_id')],
        );

        $t->same(['wp_postmeta', 'wp_posts'], $plan['tables']);
        $t->same('wp_postmeta_key', $plan['loops'][0]['index']);
        $t->same('wp_posts_id', $plan['loops'][1]['index']);
        $t->same(['ID'], $plan['loops'][1]['joinColumns']);
        $t->same(null, $plan['loops'][1]['joinFence']);
    },
    'select planner joins current keeps left join preserved side before selective nullable side' => static function (TestRunner $t) use ($statRows, $indexes, $point, $join): void {
        $plan = SQLiteJoinOrderPlan::choose(
            $statRows,
            $indexes,
            ['wp_posts', 'wp_postmeta'],
            ['wp_postmeta' => [$point('meta_key', '_edit_lock')]],
            [$join('wp_posts', 'ID', 'wp_postmeta', 'post_id', 'LEFT')],
        );

        $t->same(['wp_posts', 'wp_postmeta'], $plan['tables']);
        $t->same('table-scan', $plan['loops'][0]['access']);
        $t->same('wp_postmeta_post_id', $plan['loops'][1]['index']);
        $t->same(['post_id'], $plan['loops'][1]['joinColumns']);
        $t->same(['type' => 'LEFT', 'outerTable' => 'wp_posts'], $plan['loops'][1]['joinFence']);
    },
    'select planner joins current keeps right join preserved side before nullable side' => static function (TestRunner $t) use ($statRows, $indexes, $point, $join): void {
        $plan = SQLiteJoinOrderPlan::choose(
            $statRows,
            $indexes,
            ['wp_posts', 'wp_postmeta'],
            ['wp_posts' => [$point('post_type', 'post')]],
            [$join('wp_posts', 'ID', 'wp_postmeta', 'post_id', 'RIGHT')],
        );

        $t->same(['wp_postmeta', 'wp_posts'], $plan['tables']);
        $t->same('table-scan', $plan['loops'][0]['access']);
        $t->same('wp_posts_id', $plan['loops'][1]['index']);
        $t->same(['ID'], $plan['loops'][1]['joinColumns']);
        $t->same(['type' => 'RIGHT', 'outerTable' => 'wp_postmeta'], $plan['loops'][1]['joinFence']);
    },
    'select planner joins current records full join as non reorderable left before right' => static function (TestRunner $t) use ($statRows, $indexes, $point, $join): void {
        $orders = SQLiteJoinOrderPlan::rankedOrders(
            $statRows,
            $indexes,
            ['wp_options', 'wp_postmeta'],
            ['wp_postmeta' => [$point('meta_key', '_thumbnail_id')]],
            [$join('wp_options', 'option_id', 'wp_postmeta', 'post_id', 'FULL OUTER')],
        );

        $t->same(1, count($orders));
        $t->same(['wp_options', 'wp_postmeta'], $orders[0]['tables']);
        $t->same(['type' => 'FULL OUTER', 'outerTable' => 'wp_options'], $orders[0]['loops'][1]['joinFence']);
    },
    'select planner joins current supports cross join between two independently filtered sources' => static function (TestRunner $t) use ($statRows, $indexes, $point, $cross): void {
        $plan = SQLiteJoinOrderPlan::choose(
            $statRows,
            $indexes,
            ['wp_options', 'wp_posts'],
            [
                'wp_options' => [$point('option_name', 'siteurl')],
                'wp_posts' => [$point('post_type', 'post'), $point('post_status', 'publish')],
            ],
            [$cross('wp_options', 'wp_posts')],
        );

        $t->same(['wp_options', 'wp_posts'], $plan['tables']);
        $t->same(['wp_options_name', 'wp_posts_type_status'], array_column($plan['loops'], 'index'));
        $t->same(1000, $plan['estimatedRows']);
        $t->same([], $plan['loops'][1]['joinColumns']);
    },
    'select planner joins current rejects unsupported join type metadata' => static function (TestRunner $t) use ($statRows, $indexes): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderPlan::choose(
            $statRows,
            $indexes,
            ['wp_options', 'wp_postmeta'],
            [],
            [['leftTable' => 'wp_options', 'rightTable' => 'wp_postmeta', 'joinType' => 'SIDEWAYS']],
        ));
    },
    'select planner joins current allows cross metadata without equality columns' => static function (TestRunner $t) use ($statRows, $indexes, $cross): void {
        $plan = SQLiteJoinOrderPlan::choose(
            $statRows,
            $indexes,
            ['wp_options', 'wp_postmeta'],
            [],
            [$cross('wp_options', 'wp_postmeta')],
        );

        $t->same(['wp_options', 'wp_postmeta'], $plan['tables']);
        $t->same(['CROSS', 'wp_options'], [$plan['loops'][1]['joinFence']['type'], $plan['loops'][1]['joinFence']['outerTable']]);
    },
];

$outerVariants = [
    ['LEFT OUTER', ['wp_posts', 'wp_postmeta'], 'wp_posts', 'wp_postmeta'],
    ['FULL', ['wp_posts', 'wp_postmeta'], 'wp_posts', 'wp_postmeta'],
    ['FULL OUTER', ['wp_posts', 'wp_postmeta'], 'wp_posts', 'wp_postmeta'],
];

foreach ($outerVariants as $offset => [$joinType, $expected, $left, $right]) {
    $tests["select planner joins current outer fence variant {$offset} {$joinType}"] = static function (TestRunner $t) use ($statRows, $indexes, $point, $join, $joinType, $expected, $left, $right): void {
        $plan = SQLiteJoinOrderPlan::choose(
            $statRows,
            $indexes,
            [$left, $right],
            [$right => [$point('meta_key', '_thumbnail_id')]],
            [$join($left, 'ID', $right, 'post_id', $joinType)],
        );

        $t->same($expected, $plan['tables']);
        $t->same($joinType, $plan['loops'][1]['joinFence']['type']);
        $t->same($left, $plan['loops'][1]['joinFence']['outerTable']);
    };
}

return $tests;
