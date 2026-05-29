<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJoinOrderStat4RangeCurrentSourceNextPlan;

$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$join = static fn (string $leftTable, string $leftColumn, string $rightTable, string $rightColumn): array => [
    'leftTable' => $leftTable,
    'leftColumn' => $leftColumn,
    'rightTable' => $rightTable,
    'rightColumn' => $rightColumn,
];

$preparedSource = static function () use ($and, $range, $join): array {
    return [
        'name' => 'prepared-before-option-import',
        'schemaCookie' => 1160,
        'stat4Generation' => 50,
        'tables' => ['wp_posts', 'wp_postmeta', 'wp_options'],
        'tableRows' => ['wp_posts' => 30000, 'wp_postmeta' => 240000, 'wp_options' => 12000],
        'joinTerms' => [
            $join('wp_posts', 'ID', 'wp_postmeta', 'post_id'),
            $join('wp_postmeta', 'meta_value', 'wp_options', 'option_name'),
        ],
        'predicates' => [
            'wp_posts' => $and($range('post_date', '>=', '2026-01-01'), $range('post_date', '<', '2027-01-01')),
            'wp_postmeta' => $and($range('meta_key', '>=', '_plugin_'), $range('meta_key', '<', '_plugin_z')),
            'wp_options' => $and($range('option_name', '>=', 'plugin_'), $range('option_name', '<', 'plugin_z')),
        ],
        'neededColumns' => [
            'wp_posts' => ['post_date', 'ID'],
            'wp_postmeta' => ['meta_key', 'post_id', 'meta_value'],
            'wp_options' => ['option_name', 'autoload'],
        ],
        'indexes' => [
            'wp_posts' => [[
                'name' => 'idx_posts_date_id_next116',
                'estimatedRows' => 30000,
                'rootPage' => 310,
                'stat4Samples' => [
                    ['neq' => '300 300', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['2025-01-01', 1]],
                    ['neq' => '1200 1200', 'nlt' => '300 300', 'ndlt' => '1 1', 'sample' => ['2026-01-01', 200]],
                    ['neq' => '3600 3600', 'nlt' => '1500 1500', 'ndlt' => '2 2', 'sample' => ['2026-06-01', 900]],
                    ['neq' => '4200 4200', 'nlt' => '5100 5100', 'ndlt' => '3 3', 'sample' => ['2026-12-01', 1800]],
                    ['neq' => '800 800', 'nlt' => '9300 9300', 'ndlt' => '4 4', 'sample' => ['2027-01-01', 2400]],
                ],
                'sql' => 'CREATE INDEX idx_posts_date_id_next116 ON wp_posts(post_date, ID)',
            ]],
            'wp_postmeta' => [[
                'name' => 'idx_postmeta_key_post_value_next116',
                'estimatedRows' => 240000,
                'rootPage' => 420,
                'stat4Samples' => [
                    ['neq' => '1000 1000 1000', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => ['_edit_lock', 10, 'old']],
                    ['neq' => '900 900 900', 'nlt' => '1000 1000 1000', 'ndlt' => '1 1 1', 'sample' => ['_plugin_alpha', 20, 'plugin_alpha']],
                    ['neq' => '1400 1400 1400', 'nlt' => '1900 1900 1900', 'ndlt' => '2 2 2', 'sample' => ['_plugin_beta', 30, 'plugin_beta']],
                    ['neq' => '2200 2200 2200', 'nlt' => '3300 3300 3300', 'ndlt' => '3 3 3', 'sample' => ['_plugin_omega', 40, 'plugin_omega']],
                    ['neq' => '5000 5000 5000', 'nlt' => '5500 5500 5500', 'ndlt' => '4 4 4', 'sample' => ['_thumbnail_id', 50, 'thumb']],
                ],
                'sql' => 'CREATE INDEX idx_postmeta_key_post_value_next116 ON wp_postmeta(meta_key, post_id, meta_value)',
            ]],
            'wp_options' => [[
                'name' => 'idx_options_name_autoload_next116_old',
                'estimatedRows' => 12000,
                'rootPage' => 510,
                'stat4Samples' => [
                    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
                    ['neq' => '10 10', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_alpha', 'yes']],
                    ['neq' => '13 13', 'nlt' => '11 11', 'ndlt' => '2 2', 'sample' => ['plugin_omega', 'no']],
                    ['neq' => '800 800', 'nlt' => '24 24', 'ndlt' => '3 3', 'sample' => ['rewrite_rules', 'yes']],
                ],
                'sql' => 'CREATE INDEX idx_options_name_autoload_next116_old ON wp_options(autoload, option_name)',
            ]],
        ],
    ];
};

$currentSource = static function (array $overrides = []) use ($preparedSource): array {
    $source = $preparedSource();
    $source['name'] = 'current-after-plugin-import-analyze';
    $source['schemaCookie'] = 1161;
    $source['stat4Generation'] = 51;
    $source['indexes']['wp_options'][0]['name'] = 'idx_options_name_autoload_next116';
    $source['indexes']['wp_options'][0]['rootPage'] = 512;
    $source['indexes']['wp_options'][0]['sql'] = 'CREATE INDEX idx_options_name_autoload_next116 ON wp_options(option_name, autoload)';
    $source['indexes']['wp_options'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_alpha', 'yes']],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_beta', 'yes']],
        ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_gamma', 'no']],
        ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_omega', 'no']],
        ['neq' => '800 800', 'nlt' => '5 5', 'ndlt' => '5 5', 'sample' => ['rewrite_rules', 'yes']],
    ];

    foreach ($overrides as $key => $value) {
        $source[$key] = $value;
    }

    return $source;
};

$plan = static fn (?array $prepared = null, ?array $current = null): array =>
    SQLiteJoinOrderStat4RangeCurrentSourceNextPlan::materialize($prepared ?? $preparedSource(), $current ?? $currentSource());

$tests = [];

$tests['planner join order stat4 range current source next116 selects current source'] = static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']);
$tests['planner join order stat4 range current source next116 marks stale statement'] = static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']);
$tests['planner join order stat4 range current source next116 requires reprepare'] = static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']);
$tests['planner join order stat4 range current source next116 detects schema cookie'] = static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']);
$tests['planner join order stat4 range current source next116 detects stat4 generation'] = static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']);
$tests['planner join order stat4 range current source next116 detects index signature'] = static fn (TestRunner $t) => $t->same(true, $plan()['indexSignatureChanged']);
$tests['planner join order stat4 range current source next116 changes join order'] = static fn (TestRunner $t) => $t->same(true, $plan()['joinOrderChanged']);
$tests['planner join order stat4 range current source next116 prepared starts with postmeta'] = static fn (TestRunner $t) => $t->same('wp_postmeta', $plan()['preparedPlan']['tables'][0]);
$tests['planner join order stat4 range current source next116 current starts with options'] = static fn (TestRunner $t) => $t->same('wp_options', $plan()['currentPlan']['tables'][0]);
$tests['planner join order stat4 range current source next116 selected starts with options'] = static fn (TestRunner $t) => $t->same('wp_options', $plan()['selectedPlan']['tables'][0]);
$tests['planner join order stat4 range current source next116 selected connected order'] = static fn (TestRunner $t) => $t->same(['wp_options', 'wp_postmeta', 'wp_posts'], $plan()['selectedPlan']['tables']);
$tests['planner join order stat4 range current source next116 option loop uses current index'] = static fn (TestRunner $t) => $t->same('idx_options_name_autoload_next116', $plan()['selectedPlan']['loops'][0]['index']);
$tests['planner join order stat4 range current source next116 option loop root changed in fence'] = static fn (TestRunner $t) => $t->same(1161, $plan()['currentSourceFence']['schemaCookie']);
$tests['planner join order stat4 range current source next116 option stat4 matched samples'] = static fn (TestRunner $t) => $t->same(4, $plan()['selectedPlan']['loops'][0]['stat4MatchedSamples']);
$tests['planner join order stat4 range current source next116 option rows narrow'] = static fn (TestRunner $t) => $t->same(4, $plan()['selectedPlan']['loops'][0]['estimatedRows']);
$tests['planner join order stat4 range current source next116 prepared option rows broad'] = static fn (TestRunner $t) => $t->same(12000, $plan()['preparedPlan']['loops'][2]['estimatedRows']);
$tests['planner join order stat4 range current source next116 current estimate delta negative'] = static fn (TestRunner $t) => $t->same(true, $plan()['estimatedRowsDelta'] < 0);
$tests['planner join order stat4 range current source next116 current cost delta negative'] = static fn (TestRunner $t) => $t->same(true, $plan()['estimatedCostDelta'] < 0);
$tests['planner join order stat4 range current source next116 second loop joins meta value'] = static fn (TestRunner $t) => $t->same(['meta_value'], $plan()['selectedPlan']['loops'][1]['joinColumns']);
$tests['planner join order stat4 range current source next116 second loop uses postmeta index'] = static fn (TestRunner $t) => $t->same('idx_postmeta_key_post_value_next116', $plan()['selectedPlan']['loops'][1]['index']);
$tests['planner join order stat4 range current source next116 second loop preserves range column'] = static fn (TestRunner $t) => $t->same('meta_key', $plan()['selectedPlan']['loops'][1]['rangeColumn']);
$tests['planner join order stat4 range current source next116 second loop matched join column'] = static fn (TestRunner $t) => $t->same(true, in_array('meta_value', $plan()['selectedPlan']['loops'][1]['matchedColumns'], true));
$tests['planner join order stat4 range current source next116 third loop joins posts id'] = static fn (TestRunner $t) => $t->same(['ID'], $plan()['selectedPlan']['loops'][2]['joinColumns']);
$tests['planner join order stat4 range current source next116 third loop uses posts range'] = static fn (TestRunner $t) => $t->same('idx_posts_date_id_next116', $plan()['selectedPlan']['loops'][2]['index']);
$tests['planner join order stat4 range current source next116 detail names reprepare'] = static fn (TestRunner $t) => $t->contains('REPREPARE JOIN ORDER STAT4 RANGE USING CURRENT SOURCE current-after-plugin-import-analyze', $plan()['detail']);
$tests['planner join order stat4 range current source next116 detail names nested searches'] = static fn (TestRunner $t) => $t->contains('SEARCH wp_options USING INDEX idx_options_name_autoload_next116', $plan()['detail']);
$tests['planner join order stat4 range current source next116 dependency helper present'] = static fn (TestRunner $t) => $t->same(true, in_array('SQLiteJoinOrderStat4RangeCurrentSourceNextPlan', $plan()['dependencies'], true));
$tests['planner join order stat4 range current source next116 dependency marker present'] = static fn (TestRunner $t) => $t->same(true, in_array('sqlite-join-order-stat4-range-current-source-next116', $plan()['dependencies'], true));
$tests['planner join order stat4 range current source next116 non overlap note'] = static fn (TestRunner $t) => $t->contains('connected multi-table join orders', $plan()['non_overlap']);
$tests['planner join order stat4 range current source next116 dependency closure note'] = static fn (TestRunner $t) => $t->contains('no new support component needed', $plan()['dependency_closure']);
$tests['planner join order stat4 range current source next116 ranked alternatives exposed'] = static fn (TestRunner $t) => $t->same(true, count($plan()['selectedPlan']['rankedOrders']) >= 2);
$tests['planner join order stat4 range current source next116 best ranked is selected'] = static fn (TestRunner $t) => $t->same($plan()['selectedPlan']['tables'], $plan()['selectedPlan']['rankedOrders'][0]['tables']);
$tests['planner join order stat4 range current source next116 stat4 range upper current'] = static fn (TestRunner $t) => $t->same('plugin_omega', $plan()['selectedPlan']['loops'][0]['stat4RangeCurrentNext']['upper']['current']['key']);
$tests['planner join order stat4 range current source next116 stat4 range upper next'] = static fn (TestRunner $t) => $t->same('rewrite_rules', $plan()['selectedPlan']['loops'][0]['stat4RangeCurrentNext']['upper']['next']['key']);
$tests['planner join order stat4 range current source next116 stat4 lower exact false'] = static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['loops'][0]['stat4RangeCurrentNext']['lower']['exact']);
$tests['planner join order stat4 range current source next116 current source fence stat4'] = static fn (TestRunner $t) => $t->same(51, $plan()['currentSourceFence']['stat4Generation']);
$tests['planner join order stat4 range current source next116 same source reuses prepared'] = static fn (TestRunner $t) => $t->same('prepared', $plan($preparedSource(), $preparedSource())['selectedSource']);
$tests['planner join order stat4 range current source next116 same source skips reprepare'] = static fn (TestRunner $t) => $t->same(false, $plan($preparedSource(), $preparedSource())['reprepareRequired']);
$tests['planner join order stat4 range current source next116 same source keeps prepared order'] = static fn (TestRunner $t) => $t->same(['wp_postmeta', 'wp_posts', 'wp_options'], $plan($preparedSource(), $preparedSource())['selectedPlan']['tables']);
$tests['planner join order stat4 range current source next116 table scan fallback works'] = static function (TestRunner $t) use ($preparedSource, $currentSource, $plan): void {
    $prepared = $preparedSource();
    $current = $currentSource();
    unset($current['indexes']['wp_posts']);
    $t->same('table-scan', $plan($prepared, $current)['selectedPlan']['loops'][2]['access']);
};
$tests['planner join order stat4 range current source next116 disconnected graph has no selected loops'] = static function (TestRunner $t) use ($currentSource, $plan): void {
    $source = $currentSource();
    $source['joinTerms'] = [];
    $t->same([], $plan($source, $source)['selectedPlan']['loops']);
};
$tests['planner join order stat4 range current source next116 validates empty tables'] = static function (TestRunner $t) use ($currentSource): void {
    $source = $currentSource(['tables' => []]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderStat4RangeCurrentSourceNextPlan::materialize($source, $source));
};
$tests['planner join order stat4 range current source next116 validates duplicate tables'] = static function (TestRunner $t) use ($currentSource): void {
    $source = $currentSource(['tables' => ['wp_options', 'WP_OPTIONS']]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderStat4RangeCurrentSourceNextPlan::materialize($source, $source));
};
$tests['planner join order stat4 range current source next116 validates malformed join'] = static function (TestRunner $t) use ($currentSource): void {
    $source = $currentSource(['joinTerms' => [['leftTable' => 'wp_options', 'leftColumn' => '', 'rightTable' => 'wp_postmeta', 'rightColumn' => 'meta_value']]]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderStat4RangeCurrentSourceNextPlan::materialize($source, $source));
};
$tests['planner join order stat4 range current source next116 validates schema cookie'] = static function (TestRunner $t) use ($currentSource): void {
    $source = $currentSource(['schemaCookie' => -1]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderStat4RangeCurrentSourceNextPlan::materialize($source, $source));
};
$tests['planner join order stat4 range current source next116 validates stat4 generation'] = static function (TestRunner $t) use ($currentSource): void {
    $source = $currentSource(['stat4Generation' => -1]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderStat4RangeCurrentSourceNextPlan::materialize($source, $source));
};
$tests['planner join order stat4 range current source next116 validates table rows'] = static function (TestRunner $t) use ($currentSource): void {
    $source = $currentSource(['tableRows' => ['wp_options' => 0]]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderStat4RangeCurrentSourceNextPlan::materialize($source, $source));
};
$tests['planner join order stat4 range current source next116 validates needed columns'] = static function (TestRunner $t) use ($currentSource): void {
    $source = $currentSource(['neededColumns' => ['wp_options' => ['option_name', '']]]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderStat4RangeCurrentSourceNextPlan::materialize($source, $source));
};
$tests['planner join order stat4 range current source next116 validates indexes map'] = static function (TestRunner $t) use ($currentSource): void {
    $source = $currentSource(['indexes' => ['wp_options' => ['not-an-index-map']]]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJoinOrderStat4RangeCurrentSourceNextPlan::materialize($source, $source));
};
$tests['planner join order stat4 range current source next116 supports between predicate'] = static function (TestRunner $t) use ($currentSource, $between, $plan): void {
    $source = $currentSource();
    $source['predicates']['wp_options'] = $between('option_name', 'plugin_alpha', 'plugin_omega');
    $t->same(4, $plan($source, $source)['selectedPlan']['loops'][0]['estimatedRows']);
};
$tests['planner join order stat4 range current source next116 supports narrowed exclusive upper'] = static function (TestRunner $t) use ($currentSource, $and, $range, $plan): void {
    $source = $currentSource();
    $source['predicates']['wp_options'] = $and($range('option_name', '>=', 'plugin_'), $range('option_name', '<', 'plugin_gamma'));
    $t->same(2, $plan($source, $source)['selectedPlan']['loops'][0]['estimatedRows']);
};
$tests['planner join order stat4 range current source next116 supports narrowed inclusive upper'] = static function (TestRunner $t) use ($currentSource, $and, $range, $plan): void {
    $source = $currentSource();
    $source['predicates']['wp_options'] = $and($range('option_name', '>=', 'plugin_'), $range('option_name', '<=', 'plugin_gamma'));
    $t->same(3, $plan($source, $source)['selectedPlan']['loops'][0]['estimatedRows']);
};
$tests['planner join order stat4 range current source next116 supports single table current plan'] = static function (TestRunner $t) use ($currentSource, $plan): void {
    $source = $currentSource(['tables' => ['wp_options'], 'joinTerms' => []]);
    $t->same(['wp_options'], $plan($source, $source)['selectedPlan']['tables']);
};
$tests['planner join order stat4 range current source next116 single table keeps index access'] = static function (TestRunner $t) use ($currentSource, $plan): void {
    $source = $currentSource(['tables' => ['wp_options'], 'joinTerms' => []]);
    $t->same('index', $plan($source, $source)['selectedPlan']['loops'][0]['access']);
};

return $tests;
