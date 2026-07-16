<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJoinOrderPlan;

$statRows = [
    ['tbl' => 'wp_options', 'idx' => null, 'stat' => '12000'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_option_id', 'stat' => '12000 1'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_name', 'stat' => '12000 1'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_autoload', 'stat' => '12000 6000'],
    ['tbl' => 'wp_postmeta', 'idx' => null, 'stat' => '240000'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_post_id', 'stat' => '240000 40'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_key', 'stat' => '240000 800'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_post_key', 'stat' => '240000 40 2'],
    ['tbl' => 'wp_posts', 'idx' => null, 'stat' => '30000'],
    ['tbl' => 'wp_posts', 'idx' => 'wp_posts_id', 'stat' => '30000 1'],
    ['tbl' => 'wp_posts', 'idx' => 'wp_posts_type_status_date', 'stat' => '30000 6000 1000 80'],
    ['tbl' => 'wp_term_relationships', 'idx' => null, 'stat' => '90000'],
    ['tbl' => 'wp_term_relationships', 'idx' => 'wp_term_relationships_object', 'stat' => '90000 3'],
    ['tbl' => 'wp_term_relationships', 'idx' => 'wp_term_relationships_taxonomy', 'stat' => '90000 300'],
];

$indexes = [
    ['name' => 'wp_options_option_id', 'table' => 'wp_options', 'columns' => ['option_id'], 'unique' => true],
    ['name' => 'wp_options_name', 'table' => 'wp_options', 'columns' => ['option_name'], 'unique' => true],
    ['name' => 'wp_options_autoload', 'table' => 'wp_options', 'columns' => ['autoload']],
    ['name' => 'wp_postmeta_post_id', 'table' => 'wp_postmeta', 'columns' => ['post_id']],
    ['name' => 'wp_postmeta_key', 'table' => 'wp_postmeta', 'columns' => ['meta_key']],
    ['name' => 'wp_postmeta_post_key', 'table' => 'wp_postmeta', 'columns' => ['post_id', 'meta_key']],
    ['name' => 'wp_posts_id', 'table' => 'wp_posts', 'columns' => ['ID'], 'unique' => true],
    ['name' => 'wp_posts_type_status_date', 'table' => 'wp_posts', 'columns' => ['post_type', 'post_status', 'post_date']],
    ['name' => 'wp_term_relationships_object', 'table' => 'wp_term_relationships', 'columns' => ['object_id']],
    ['name' => 'wp_term_relationships_taxonomy', 'table' => 'wp_term_relationships', 'columns' => ['term_taxonomy_id']],
];

$point = static fn (string $column, mixed $value): array => ['column' => $column, 'operator' => '=', 'value' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['column' => $column, 'operator' => $operator, 'value' => $value];
$join = static fn (string $leftTable, string $leftColumn, string $rightTable, string $rightColumn): array => [
    'leftTable' => $leftTable,
    'leftColumn' => $leftColumn,
    'rightTable' => $rightTable,
    'rightColumn' => $rightColumn,
];

$basePostMetaJoin = static fn (): array => [$join('wp_posts', 'ID', 'wp_postmeta', 'post_id')];
$postArchiveConstraints = static fn () => [
    'wp_posts' => [$point('post_type', 'post'), $point('post_status', 'publish'), $range('post_date', '>=', '2026-01-01')],
    'wp_postmeta' => [$point('meta_key', '_thumbnail_id')],
];

$tests = [
    'join order current next76 starts from selective post archive before postmeta' => static function (TestRunner $t) use ($statRows, $indexes, $postArchiveConstraints, $basePostMetaJoin): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], $postArchiveConstraints(), $basePostMetaJoin());
        $t->same(['wp_posts', 'wp_postmeta'], $plan['tables']);
    },
    'join order current next76 uses composite posts archive index first' => static function (TestRunner $t) use ($statRows, $indexes, $postArchiveConstraints, $basePostMetaJoin): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], $postArchiveConstraints(), $basePostMetaJoin());
        $t->same('wp_posts_type_status_date', $plan['loops'][0]['index']);
        $t->same(['post_type', 'post_status', 'post_date'], $plan['loops'][0]['matchedColumns']);
    },
    'join order current next76 adds join equality to inner postmeta loop' => static function (TestRunner $t) use ($statRows, $indexes, $postArchiveConstraints, $basePostMetaJoin): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], $postArchiveConstraints(), $basePostMetaJoin());
        $t->same(['post_id', 'meta_key'], $plan['loops'][1]['matchedColumns']);
        $t->same(['post_id'], $plan['loops'][1]['joinColumns']);
    },
    'join order current next76 prefers postmeta first when meta key is the only filter' => static function (TestRunner $t) use ($statRows, $indexes, $point, $basePostMetaJoin): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], ['wp_postmeta' => [$point('meta_key', '_edit_lock')]], $basePostMetaJoin());
        $t->same('wp_postmeta', $plan['tables'][0]);
        $t->same('wp_postmeta_key', $plan['loops'][0]['index']);
    },
    'join order current next76 then probes posts by rowid-equivalent id' => static function (TestRunner $t) use ($statRows, $indexes, $point, $basePostMetaJoin): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], ['wp_postmeta' => [$point('meta_key', '_edit_lock')]], $basePostMetaJoin());
        $t->same('wp_posts_id', $plan['loops'][1]['index']);
        $t->same(['ID'], $plan['loops'][1]['matchedColumns']);
    },
    'join order current next76 rejects disconnected join permutation' => static function (TestRunner $t) use ($statRows, $indexes, $point, $join): void {
        $orders = SQLiteJoinOrderPlan::rankedOrders($statRows, $indexes, ['wp_posts', 'wp_postmeta', 'wp_options'], ['wp_options' => [$point('option_name', 'siteurl')]], [$join('wp_posts', 'ID', 'wp_postmeta', 'post_id')]);
        $t->same([], $orders);
    },
    'join order current next76 handles single table as one loop' => static function (TestRunner $t) use ($statRows, $indexes, $point): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_options'], ['wp_options' => [$point('option_name', 'home')]]);
        $t->same(['wp_options'], $plan['tables']);
        $t->same('wp_options_name', $plan['loops'][0]['index']);
    },
    'join order current next76 validates empty table list' => static function (TestRunner $t) use ($statRows, $indexes): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderPlan::choose($statRows, $indexes, []));
    },
    'join order current next76 validates duplicate table list' => static function (TestRunner $t) use ($statRows, $indexes): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'WP_POSTS']));
    },
    'join order current next76 validates malformed join term' => static function (TestRunner $t) use ($statRows, $indexes): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], [], [['leftTable' => 'wp_posts', 'leftColumn' => '', 'rightTable' => 'wp_postmeta', 'rightColumn' => 'post_id']]));
    },
];

$archiveRanges = [
    ['>=', '2026-01-01', 'wp_posts_type_status_date'],
    ['>', '2026-01-01', 'wp_posts_type_status_date'],
    ['<', '2027-01-01', 'wp_posts_type_status_date'],
    ['<=', '2027-01-01', 'wp_posts_type_status_date'],
];

foreach ($archiveRanges as $offset => [$operator, $value, $expectedIndex]) {
    $tests["join order current next76 archive range {$offset} remains outer posts loop"] = static function (TestRunner $t) use ($statRows, $indexes, $point, $range, $basePostMetaJoin, $operator, $value, $expectedIndex): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], [
            'wp_posts' => [$point('post_type', 'post'), $point('post_status', 'publish'), $range('post_date', $operator, $value)],
            'wp_postmeta' => [$point('meta_key', '_thumbnail_id')],
        ], $basePostMetaJoin());
        $t->same('wp_posts', $plan['tables'][0]);
        $t->same($expectedIndex, $plan['loops'][0]['index']);
    };
}

$metaKeys = ['_thumbnail_id', '_wp_attached_file', '_edit_lock', '_menu_item_url', '_wp_page_template', '_yoast_wpseo_title'];
foreach ($metaKeys as $offset => $metaKey) {
    $tests["join order current next76 meta key {$offset} uses postmeta key outer loop"] = static function (TestRunner $t) use ($statRows, $indexes, $point, $basePostMetaJoin, $metaKey): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], ['wp_postmeta' => [$point('meta_key', $metaKey)]], $basePostMetaJoin());
        $t->same(['wp_postmeta', 'wp_posts'], $plan['tables']);
        $t->same(['wp_postmeta_key', 'wp_posts_id'], array_column($plan['loops'], 'index'));
    };
}

$optionNames = ['siteurl', 'home', 'blogname', 'active_plugins', 'stylesheet', 'template', 'permalink_structure', 'rewrite_rules'];
foreach ($optionNames as $offset => $optionName) {
    $tests["join order current next76 option lookup {$offset} remains unique one-row loop"] = static function (TestRunner $t) use ($statRows, $indexes, $point, $optionName): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_options'], ['wp_options' => [$point('option_name', $optionName)]]);
        $t->same(1, $plan['loops'][0]['estimatedRows']);
        $t->same('SEARCH wp_options USING INDEX wp_options_name (option_name=?)', $plan['detail'][0]);
    };
}

$threeWayCases = [
    ['post', 'publish', 'category', ['wp_term_relationships', 'wp_posts', 'wp_postmeta']],
    ['page', 'publish', 'nav', ['wp_term_relationships', 'wp_posts', 'wp_postmeta']],
    ['attachment', 'inherit', 'media', ['wp_term_relationships', 'wp_posts', 'wp_postmeta']],
    ['product', 'publish', 'shop', ['wp_term_relationships', 'wp_posts', 'wp_postmeta']],
    ['post', 'draft', 'tag', ['wp_term_relationships', 'wp_posts', 'wp_postmeta']],
];

foreach ($threeWayCases as $offset => [$type, $status, $taxonomyValue, $expectedOrder]) {
    $tests["join order current next76 three way connected case {$offset} ranks taxonomy first"] = static function (TestRunner $t) use ($statRows, $indexes, $point, $join, $type, $status, $taxonomyValue, $expectedOrder): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta', 'wp_term_relationships'], [
            'wp_posts' => [$point('post_type', $type), $point('post_status', $status)],
            'wp_postmeta' => [$point('meta_key', '_thumbnail_id')],
            'wp_term_relationships' => [$point('term_taxonomy_id', $taxonomyValue)],
        ], [
            $join('wp_posts', 'ID', 'wp_postmeta', 'post_id'),
            $join('wp_posts', 'ID', 'wp_term_relationships', 'object_id'),
        ]);
        $t->same($expectedOrder, $plan['tables']);
    };
}

$joinDirections = [
    ['wp_posts', 'ID', 'wp_postmeta', 'post_id'],
    ['wp_postmeta', 'post_id', 'wp_posts', 'ID'],
];

foreach ($joinDirections as $offset => [$leftTable, $leftColumn, $rightTable, $rightColumn]) {
    $tests["join order current next76 join direction {$offset} produces same inner id probe"] = static function (TestRunner $t) use ($statRows, $indexes, $point, $join, $leftTable, $leftColumn, $rightTable, $rightColumn): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], ['wp_postmeta' => [$point('meta_key', '_edit_lock')]], [$join($leftTable, $leftColumn, $rightTable, $rightColumn)]);
        $t->same('wp_posts_id', $plan['loops'][1]['index']);
        $t->same(['ID'], $plan['loops'][1]['joinColumns']);
    };
}

$deterministicTables = [
    ['wp_posts', 'wp_postmeta'],
    ['wp_postmeta', 'wp_posts'],
];

foreach ($deterministicTables as $offset => $tables) {
    $tests["join order current next76 input order {$offset} still ranks cheapest connected order"] = static function (TestRunner $t) use ($statRows, $indexes, $point, $basePostMetaJoin, $tables): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, $tables, ['wp_postmeta' => [$point('meta_key', '_edit_lock')]], $basePostMetaJoin());
        $t->same(['wp_postmeta', 'wp_posts'], $plan['tables']);
    };
}

$tableScanCases = [
    ['wp_options', 'autoload', 'yes', 'wp_options_autoload'],
    ['wp_posts', 'post_status', 'publish', null],
    ['wp_postmeta', 'meta_key', '_edit_last', 'wp_postmeta_key'],
];

foreach ($tableScanCases as $offset => [$table, $column, $value, $expectedIndex]) {
    $tests["join order current next76 scan fallback case {$offset} reports access"] = static function (TestRunner $t) use ($statRows, $indexes, $point, $table, $column, $value, $expectedIndex): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, [$table], [$table => [$point($column, $value)]]);
        $t->same($expectedIndex === null ? 'table-scan' : 'index', $plan['loops'][0]['access']);
        $t->same($expectedIndex, $plan['loops'][0]['index']);
    };
}

$costCases = [
    ['wp_options unique lookup cheaper than autoload', ['wp_options'], ['wp_options' => [$point('option_name', 'siteurl'), $point('autoload', 'yes')]], [], 1],
    ['postmeta key lookup has analyzed cost', ['wp_postmeta'], ['wp_postmeta' => [$point('meta_key', '_thumbnail_id')]], [], 800],
    ['posts archive range keeps bounded rows', ['wp_posts'], ['wp_posts' => [$point('post_type', 'post'), $point('post_status', 'publish'), $range('post_date', '>=', '2026-01-01')]], [], 320],
];

foreach ($costCases as $offset => [$label, $tables, $constraints, $joins, $expectedRows]) {
    $tests["join order current next76 cost {$offset} {$label}"] = static function (TestRunner $t) use ($statRows, $indexes, $tables, $constraints, $joins, $expectedRows): void {
        $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, $tables, $constraints, $joins);
        $t->same($expectedRows, $plan['loops'][0]['estimatedRows']);
    };
}

$tests['join order current next76 ranked orders exposes alternatives'] = static function (TestRunner $t) use ($statRows, $indexes, $point, $basePostMetaJoin): void {
    $orders = SQLiteJoinOrderPlan::rankedOrders($statRows, $indexes, ['wp_posts', 'wp_postmeta'], ['wp_postmeta' => [$point('meta_key', '_edit_lock')]], $basePostMetaJoin());
    $t->same(['wp_postmeta', 'wp_posts'], $orders[0]['tables']);
    $t->same(['wp_posts', 'wp_postmeta'], $orders[1]['tables']);
};

$tests['join order current next76 detail preserves nested loop search strings'] = static function (TestRunner $t) use ($statRows, $indexes, $postArchiveConstraints, $basePostMetaJoin): void {
    $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], $postArchiveConstraints(), $basePostMetaJoin());
    $t->same('SEARCH wp_posts USING INDEX wp_posts_type_status_date (post_type=?,post_status=?,post_date>=?)', $plan['detail'][0]);
    $t->same('SEARCH wp_postmeta USING INDEX wp_postmeta_post_key (post_id=?,meta_key=?)', $plan['detail'][1]);
};

$tests['join order current next76 inner loop records joined post id column'] = static function (TestRunner $t) use ($statRows, $indexes, $postArchiveConstraints, $basePostMetaJoin): void {
    $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], $postArchiveConstraints(), $basePostMetaJoin());
    $t->same(['post_id'], $plan['loops'][1]['joinColumns']);
};

$tests['join order current next76 outer loop has no join columns'] = static function (TestRunner $t) use ($statRows, $indexes, $postArchiveConstraints, $basePostMetaJoin): void {
    $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], $postArchiveConstraints(), $basePostMetaJoin());
    $t->same([], $plan['loops'][0]['joinColumns']);
};

$tests['join order current next76 loop positions follow chosen order'] = static function (TestRunner $t) use ($statRows, $indexes, $postArchiveConstraints, $basePostMetaJoin): void {
    $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts', 'wp_postmeta'], $postArchiveConstraints(), $basePostMetaJoin());
    $t->same([0, 1], array_column($plan['loops'], 'position'));
};

$tests['join order current next76 table scan loop still contributes detail'] = static function (TestRunner $t) use ($statRows, $indexes, $point): void {
    $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_posts'], ['wp_posts' => [$point('post_status', 'publish')]]);
    $t->same('SCAN wp_posts', $plan['detail'][0]);
};

$tests['join order current next76 unique option loop reports bounded cost'] = static function (TestRunner $t) use ($statRows, $indexes, $point): void {
    $plan = SQLiteJoinOrderPlan::choose($statRows, $indexes, ['wp_options'], ['wp_options' => [$point('option_name', 'siteurl')]]);
    $t->same(1, $plan['estimatedRows']);
    $t->same(1, $plan['loops'][0]['estimatedRows']);
};

return $tests;
