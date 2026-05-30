<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAnalyzeStatPlanner;

$statRows = [
    ['tbl' => 'wp_options', 'idx' => null, 'stat' => '10000'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_autoload', 'stat' => '10000 5000'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_name', 'stat' => '10000 1'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_autoload_name', 'stat' => '10000 5000 1'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_name_autoload', 'stat' => '10000 1 1'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_option_id', 'stat' => '10000 1'],
    ['tbl' => 'wp_postmeta', 'idx' => null, 'stat' => '250000'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_post_id', 'stat' => '250000 50'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_key', 'stat' => '250000 500'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_post_key', 'stat' => '250000 50 3'],
    ['tbl' => 'wp_posts', 'idx' => null, 'stat' => '20000'],
    ['tbl' => 'wp_posts', 'idx' => 'wp_posts_type_status_date', 'stat' => '20000 4000 800 80'],
    ['tbl' => 'wp_posts', 'idx' => 'wp_posts_status', 'stat' => '20000 10000'],
];

$indexes = [
    ['name' => 'wp_options_autoload', 'table' => 'wp_options', 'columns' => ['autoload']],
    ['name' => 'wp_options_name', 'table' => 'wp_options', 'columns' => ['option_name'], 'unique' => true],
    ['name' => 'wp_options_autoload_name', 'table' => 'wp_options', 'columns' => ['autoload', 'option_name']],
    ['name' => 'wp_options_name_autoload', 'table' => 'wp_options', 'columns' => ['option_name', 'autoload']],
    ['name' => 'wp_options_option_id', 'table' => 'wp_options', 'columns' => ['option_id'], 'unique' => true],
    ['name' => 'wp_postmeta_post_id', 'table' => 'wp_postmeta', 'columns' => ['post_id']],
    ['name' => 'wp_postmeta_key', 'table' => 'wp_postmeta', 'columns' => ['meta_key']],
    ['name' => 'wp_postmeta_post_key', 'table' => 'wp_postmeta', 'columns' => ['post_id', 'meta_key']],
    ['name' => 'wp_posts_type_status_date', 'table' => 'wp_posts', 'columns' => ['post_type', 'post_status', 'post_date']],
    ['name' => 'wp_posts_status', 'table' => 'wp_posts', 'columns' => ['post_status']],
];

$tests = [];

$cases = [
    'unique option_name equality beats autoload scan' => ['wp_options', [['column' => 'option_name', 'operator' => '=', 'value' => 'siteurl']], 'wp_options_name', 1],
    'unique option_id equality uses rowid-like unique index' => ['wp_options', [['column' => 'option_id', 'operator' => '=', 'value' => 42]], 'wp_options_option_id', 1],
    'autoload equality uses analyzed low-selectivity index' => ['wp_options', [['column' => 'autoload', 'operator' => '=', 'value' => 'yes']], 'wp_options_autoload', 5000],
    'name and autoload equality can still prefer unique name lookup' => ['wp_options', [['column' => 'autoload', 'operator' => '=', 'value' => 'yes'], ['column' => 'option_name', 'operator' => '=', 'value' => 'siteurl']], 'wp_options_name', 1],
    'autoload then name equality can still prefer unique name lookup' => ['wp_options', [['column' => 'autoload', 'operator' => '=', 'value' => 'yes'], ['column' => 'option_name', 'operator' => '=', 'value' => 'home']], 'wp_options_name', 1],
    'autoload IN estimates multiplied stat bucket' => ['wp_options', [['column' => 'autoload', 'operator' => 'IN', 'values' => ['yes', 'no']]], 'wp_options_autoload', 10000],
    'option_name IN preserves unique bucket multiplication' => ['wp_options', [['column' => 'option_name', 'operator' => 'IN', 'values' => ['home', 'siteurl', 'blogname']]], 'wp_options_name', 3],
    'option_name range uses unique-index range bucket' => ['wp_options', [['column' => 'option_name', 'operator' => '>=', 'value' => '_transient_']], 'wp_options_name', 4],
    'autoload range uses analyzed broad bucket' => ['wp_options', [['column' => 'autoload', 'operator' => 'BETWEEN', 'values' => ['a', 'z']]], 'wp_options_autoload', 10000],
    'post_id equality uses postmeta post index' => ['wp_postmeta', [['column' => 'post_id', 'operator' => '=', 'value' => 7]], 'wp_postmeta_post_id', 50],
    'meta_key equality uses analyzed key index' => ['wp_postmeta', [['column' => 'meta_key', 'operator' => '=', 'value' => '_thumbnail_id']], 'wp_postmeta_key', 500],
    'post_id and meta_key equality uses composite post-key index' => ['wp_postmeta', [['column' => 'post_id', 'operator' => '=', 'value' => 7], ['column' => 'meta_key', 'operator' => '=', 'value' => '_edit_lock']], 'wp_postmeta_post_key', 3],
    'post_id IN estimates composite first column' => ['wp_postmeta', [['column' => 'post_id', 'operator' => 'IN', 'values' => [7, 8, 9]]], 'wp_postmeta_post_id', 150],
    'post_id equality and meta_key range uses range on second prefix' => ['wp_postmeta', [['column' => 'post_id', 'operator' => '=', 'value' => 7], ['column' => 'meta_key', 'operator' => '>=', 'value' => '_']], 'wp_postmeta_post_key', 12],
    'post type equality uses composite first bucket' => ['wp_posts', [['column' => 'post_type', 'operator' => '=', 'value' => 'post']], 'wp_posts_type_status_date', 4000],
    'post type status equality uses second composite bucket' => ['wp_posts', [['column' => 'post_type', 'operator' => '=', 'value' => 'post'], ['column' => 'post_status', 'operator' => '=', 'value' => 'publish']], 'wp_posts_type_status_date', 800],
    'post type status date range uses range bucket' => ['wp_posts', [['column' => 'post_type', 'operator' => '=', 'value' => 'post'], ['column' => 'post_status', 'operator' => '=', 'value' => 'publish'], ['column' => 'post_date', 'operator' => '>=', 'value' => '2026-01-01']], 'wp_posts_type_status_date', 320],
    'post status equality uses status index when leading composite absent' => ['wp_posts', [['column' => 'post_status', 'operator' => '=', 'value' => 'publish']], 'wp_posts_status', 10000],
    'unknown column falls back to table scan' => ['wp_options', [['column' => 'missing', 'operator' => '=', 'value' => 1]], null, 10000],
    'suffix-only composite column falls back when no leading prefix exists' => ['wp_postmeta', [['column' => 'meta_key', 'operator' => '<', 'value' => 'z']], 'wp_postmeta_key', 2000],
    'case-insensitive constraint column matching uses stat index' => ['wp_options', [['column' => 'OPTION_NAME', 'operator' => '=', 'value' => 'home']], 'wp_options_name', 1],
    'double-equals operator behaves like equality' => ['wp_options', [['column' => 'option_name', 'operator' => '==', 'value' => 'home']], 'wp_options_name', 1],
    'IS operator behaves like equality for NULL probes' => ['wp_options', [['column' => 'autoload', 'operator' => 'IS', 'value' => null]], 'wp_options_autoload', 5000],
    'greater-than operator uses range estimate' => ['wp_postmeta', [['column' => 'post_id', 'operator' => '>', 'value' => 100]], 'wp_postmeta_post_id', 200],
    'less-or-equal operator uses range estimate' => ['wp_posts', [['column' => 'post_type', 'operator' => '<=', 'value' => 'revision']], 'wp_posts_type_status_date', 16000],
];

foreach ($cases as $name => [$table, $constraints, $expectedIndex, $expectedRows]) {
    $tests['analyze stat1 planner ' . $name] = static function (TestRunner $t) use ($statRows, $indexes, $table, $constraints, $expectedIndex, $expectedRows): void {
        $plan = SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, $table, $constraints);
        $t->same($expectedIndex === null ? 'table-scan' : 'index', $plan['access']);
        $t->same($expectedIndex, $plan['index'] ?? null);
        $t->same($expectedRows, $plan['estimatedRows']);
    };
}

$rankingCases = [
    'ranks unique option name before broader composites' => ['wp_options', [['column' => 'option_name', 'operator' => '=', 'value' => 'home'], ['column' => 'autoload', 'operator' => '=', 'value' => 'yes']], ['wp_options_name', 'wp_options_autoload_name']],
    'ranks composite postmeta before single post id' => ['wp_postmeta', [['column' => 'post_id', 'operator' => '=', 'value' => 3], ['column' => 'meta_key', 'operator' => '=', 'value' => '_edit_last']], ['wp_postmeta_post_key', 'wp_postmeta_post_id']],
    'ranks status scan after type status composite when both prefixes match' => ['wp_posts', [['column' => 'post_type', 'operator' => '=', 'value' => 'page'], ['column' => 'post_status', 'operator' => '=', 'value' => 'publish']], ['wp_posts_type_status_date', 'wp_posts_status']],
    'keeps deterministic name order for equal estimates' => ['wp_options', [['column' => 'option_name', 'operator' => 'IN', 'values' => ['a']]], ['wp_options_name', 'wp_options_name_autoload']],
    'stops composite prefix after range constraint' => ['wp_posts', [['column' => 'post_type', 'operator' => '>=', 'value' => 'attachment'], ['column' => 'post_status', 'operator' => '=', 'value' => 'inherit']], ['wp_posts_status', 'wp_posts_type_status_date']],
    'ignores indexes from other tables' => ['wp_postmeta', [['column' => 'option_name', 'operator' => '=', 'value' => 'siteurl']], []],
    'returns no index plans for unsupported LIKE operator' => ['wp_options', [['column' => 'option_name', 'operator' => 'LIKE', 'value' => 'site%']], []],
    'returns no index plans when first composite column is absent' => ['wp_posts', [['column' => 'post_date', 'operator' => '>=', 'value' => '2026-01-01']], []],
    'includes matched column prefix in rank output' => ['wp_posts', [['column' => 'post_type', 'operator' => '=', 'value' => 'post'], ['column' => 'post_status', 'operator' => '=', 'value' => 'draft'], ['column' => 'post_date', 'operator' => '<', 'value' => '2026-05-01']], ['wp_posts_type_status_date']],
    'uses fallback table cardinality for missing stat row index' => ['wp_options', [['column' => 'shadow_col', 'operator' => '=', 'value' => 'x']], []],
];

foreach ($rankingCases as $name => [$table, $constraints, $expectedLeadingIndexes]) {
    $tests['analyze stat1 planner ranking ' . $name] = static function (TestRunner $t) use ($statRows, $indexes, $table, $constraints, $expectedLeadingIndexes): void {
        $plans = SQLiteAnalyzeStatPlanner::rankedPlans($statRows, $indexes, $table, $constraints);
        $t->same($expectedLeadingIndexes, array_slice(array_column($plans, 'index'), 0, count($expectedLeadingIndexes)));
    };
}

$detailCases = [
    'detail reports searched table and index' => ['wp_options', [['column' => 'option_name', 'operator' => '=', 'value' => 'home']], 'SEARCH wp_options USING INDEX wp_options_name (option_name=?)'],
    'detail reports composite equality prefix' => ['wp_postmeta', [['column' => 'post_id', 'operator' => '=', 'value' => 7], ['column' => 'meta_key', 'operator' => '=', 'value' => '_edit_lock']], 'SEARCH wp_postmeta USING INDEX wp_postmeta_post_key (post_id=?,meta_key=?)'],
    'detail reports range prefix stop' => ['wp_posts', [['column' => 'post_type', 'operator' => '=', 'value' => 'post'], ['column' => 'post_status', 'operator' => '=', 'value' => 'publish'], ['column' => 'post_date', 'operator' => '>=', 'value' => '2026-01-01']], 'SEARCH wp_posts USING INDEX wp_posts_type_status_date (post_type=?,post_status=?,post_date>=?)'],
    'table scan detail names scanned table' => ['wp_options', [['column' => 'missing', 'operator' => '=', 'value' => 1]], 'SCAN wp_options'],
    'stat string is preserved in plan evidence' => ['wp_options', [['column' => 'autoload', 'operator' => '=', 'value' => 'yes']], '10000 5000'],
];

foreach ($detailCases as $name => [$table, $constraints, $expected]) {
    $tests['analyze stat1 planner detail ' . $name] = static function (TestRunner $t) use ($statRows, $indexes, $table, $constraints, $expected, $name): void {
        $plan = SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, $table, $constraints);
        $field = str_contains($name, 'stat string') ? 'stat' : 'detail';
        $t->same($expected, $plan[$field]);
    };
}

$guardCases = [
    'rejects empty stat string' => static fn (): mixed => SQLiteAnalyzeStatPlanner::choose([['tbl' => 'wp_options', 'idx' => 'bad', 'stat' => '']], [['name' => 'bad', 'table' => 'wp_options', 'columns' => ['option_name']]], 'wp_options', [['column' => 'option_name', 'operator' => '=', 'value' => 'home']]),
    'rejects nonnumeric stat token' => static fn (): mixed => SQLiteAnalyzeStatPlanner::choose([['tbl' => 'wp_options', 'idx' => 'bad', 'stat' => '100 x']], [['name' => 'bad', 'table' => 'wp_options', 'columns' => ['option_name']]], 'wp_options', [['column' => 'option_name', 'operator' => '=', 'value' => 'home']]),
    'rejects missing index name' => static fn (): mixed => SQLiteAnalyzeStatPlanner::choose($statRows, [['name' => '', 'table' => 'wp_options', 'columns' => ['option_name']]], 'wp_options', [['column' => 'option_name', 'operator' => '=', 'value' => 'home']]),
    'rejects missing index columns' => static fn (): mixed => SQLiteAnalyzeStatPlanner::choose($statRows, [['name' => 'bad', 'table' => 'wp_options', 'columns' => []]], 'wp_options', [['column' => 'option_name', 'operator' => '=', 'value' => 'home']]),
    'rejects malformed constraint column' => static fn (): mixed => SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, 'wp_options', [['column' => '', 'operator' => '=']]),
];

foreach ($guardCases as $name => $callback) {
    $tests['analyze stat1 planner guard ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, static fn () => $callback());
    };
}

$applicationCases = [
    'copied autoload option scan prefers unique option_name lookup' => ['wp_options', [['column' => 'option_name', 'operator' => '=', 'value' => 'active_plugins']], 'wp_options_name', ['option_name']],
    'copied transient cleanup prefers name range over broad autoload' => ['wp_options', [['column' => 'autoload', 'operator' => '=', 'value' => 'no'], ['column' => 'option_name', 'operator' => '>=', 'value' => '_transient_']], 'wp_options_name', ['option_name']],
    'copied postmeta lookup prefers post key composite' => ['wp_postmeta', [['column' => 'post_id', 'operator' => '=', 'value' => 42], ['column' => 'meta_key', 'operator' => '=', 'value' => '_wp_attached_file']], 'wp_postmeta_post_key', ['post_id', 'meta_key']],
    'copied posts date archive prefers type status date index' => ['wp_posts', [['column' => 'post_type', 'operator' => '=', 'value' => 'post'], ['column' => 'post_status', 'operator' => '=', 'value' => 'publish'], ['column' => 'post_date', 'operator' => 'BETWEEN', 'values' => ['2026-01-01', '2026-12-31']]], 'wp_posts_type_status_date', ['post_type', 'post_status', 'post_date']],
    'copied options unsupported LIKE remains table scan' => ['wp_options', [['column' => 'option_name', 'operator' => 'LIKE', 'value' => '_transient_%']], null, []],
];

foreach ($applicationCases as $name => [$table, $constraints, $expectedIndex, $expectedColumns]) {
    $tests['analyze stat1 planner application ' . $name] = static function (TestRunner $t) use ($statRows, $indexes, $table, $constraints, $expectedIndex, $expectedColumns): void {
        $plan = SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, $table, $constraints);
        $t->same($expectedIndex, $plan['index'] ?? null);
        $t->same($expectedColumns, $plan['matchedColumns']);
    };
}

return $tests;
