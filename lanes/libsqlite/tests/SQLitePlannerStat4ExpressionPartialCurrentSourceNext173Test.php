<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq173 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull173 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprRange173 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared173 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-duplicate-fanout-next173',
        'schemaCookie' => 1730,
        'stat4Generation' => 91,
        'rows' => [
            ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'old-cache-a', 'updated_at' => 10],
            ['rowid' => 11, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'old-cache-b', 'updated_at' => 11],
            ['rowid' => 20, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
            ['rowid' => 30, 'autoload' => 'yes', 'option_name' => 'plugin_old', 'option_value' => 'old', 'updated_at' => 30],
            ['rowid' => 40, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 40],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_duplicate_fanout_next173',
            'rootPage' => 17301,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ],
            'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload'],
            'stat4Samples' => [
                ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
                ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
                ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_old', 30]],
                ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 40]],
            ],
        ]],
    ], $overrides);
};

$current173 = static function (array $overrides = []) use ($prepared173): array {
    $source = $prepared173([
        'name' => 'current-wp-options-stat4-duplicate-fanout-next173',
        'schemaCookie' => 1738,
        'stat4Generation' => 108,
    ]);
    $source['indexes'][0]['rootPage'] = 17388;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
        ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 40]],
        ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['theme_mods', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 50, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 12, 'autoload' => 'yes', 'option_name' => 'PLUGIN_CACHE', 'option_value' => 'fresh-cache-b', 'updated_at' => 12],
        ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh-cache-a', 'updated_at' => 14],
        ['rowid' => 20, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 40, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 40],
        ['rowid' => 60, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 60],
        ['rowid' => 70, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy-cache', 'updated_at' => 70],
        ['rowid' => 80, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 80],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms173 = static fn (): array => [
    $exprRange173('LOWER( option_name )', '>=', 'plugin_cache'),
    $exprRange173('lower(option_name)', '<', 'plugin_t'),
    $eq173('autoload', 'yes'),
    $notNull173('option_name'),
];
$needed173 = ['option_name', 'option_value', 'updated_at'];
$plan173 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext173(
    $prepared ?? $prepared173(),
    $current ?? $current173(),
    $terms ?? $terms173(),
    $needed ?? $needed173,
);
$fresh173 = static function () use ($prepared173, $plan173): array {
    $source = $prepared173();

    return $plan173($source, $source);
};
$singleKey173 = static function () use ($current173, $plan173): array {
    $current = $current173();
    $current['rows'] = array_values(array_filter($current['rows'], static fn (array $row): bool => ($row['rowid'] ?? null) !== 12));
    $current['indexes'][0]['stat4Samples'][0]['neq'] = '1 1';

    return $plan173(null, $current);
};
$incompleteFanout173 = static function () use ($current173, $plan173): array {
    $current = $current173();
    $current['indexes'][0]['stat4Samples'][0]['neq'] = '3 1';

    return $plan173(null, $current);
};

return [
    'planner stat4 expression partial current source next173 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next173-ready', $plan173()['status']),
    'planner stat4 expression partial current source next173 selected current' => static fn (TestRunner $t) => $t->same('current', $plan173()['selectedSource']),
    'planner stat4 expression partial current source next173 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan173()['stalePreparedStatement']),
    'planner stat4 expression partial current source next173 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan173()['reprepareRequired']),
    'planner stat4 expression partial current source next173 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan173()['schemaCookieChanged']),
    'planner stat4 expression partial current source next173 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan173()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next173 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_duplicate_fanout_next173', $plan173()['selectedPlan']['name']),
    'planner stat4 expression partial current source next173 root page' => static fn (TestRunner $t) => $t->same(17388, $plan173()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next173 covering' => static fn (TestRunner $t) => $t->same(true, $plan173()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next173 table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan173()['tableLookupRequired']),
    'planner stat4 expression partial current source next173 base next167 rowids' => static fn (TestRunner $t) => $t->same([10, 12, 20, 50, 40], $plan173()['matchedRowids']),
    'planner stat4 expression partial current source next173 base next167 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan173()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next173 prepared rowids' => static fn (TestRunner $t) => $t->same([10, 11, 20, 30, 40], $plan173()['preparedSampleWindow']['rowids']),
    'planner stat4 expression partial current source next173 current rowids' => static fn (TestRunner $t) => $t->same([10, 12, 20, 50, 40], $plan173()['currentSampleWindow']['rowids']),
    'planner stat4 expression partial current source next173 stale blocked' => static fn (TestRunner $t) => $t->same([11, 30], $plan173()['stalePreparedRowidsBlockedBySampleFence']),
    'planner stat4 expression partial current source next173 current admitted' => static fn (TestRunner $t) => $t->same([12, 50], $plan173()['currentSourceRowidsAdmittedBySampleFence']),
    'planner stat4 expression partial current source next173 refreshed rowids' => static fn (TestRunner $t) => $t->same([10, 20, 40], $plan173()['currentSourceRowidsRefreshedBySampleFence']),
    'planner stat4 expression partial current source next173 fence current rowids' => static fn (TestRunner $t) => $t->same([10, 20, 50, 40], $plan173()['postAnalyzeSampleFence']['currentRowids']),
    'planner stat4 expression partial current source next173 fence current keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan173()['postAnalyzeSampleFence']['currentKeys']),
    'planner stat4 expression partial current source next173 duplicate count' => static fn (TestRunner $t) => $t->same(1, $plan173()['stat4DuplicateKeyCount']),
    'planner stat4 expression partial current source next173 duplicate key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan173()['duplicateStat4KeyBuckets'][0]['key']),
    'planner stat4 expression partial current source next173 duplicate sample rowid' => static fn (TestRunner $t) => $t->same(10, $plan173()['duplicateStat4KeyBuckets'][0]['sampleRowid']),
    'planner stat4 expression partial current source next173 duplicate neq' => static fn (TestRunner $t) => $t->same(2, $plan173()['duplicateStat4KeyBuckets'][0]['neq']),
    'planner stat4 expression partial current source next173 duplicate rowids' => static fn (TestRunner $t) => $t->same([10, 12], $plan173()['duplicateStat4KeyBuckets'][0]['rowids']),
    'planner stat4 expression partial current source next173 duplicate row count' => static fn (TestRunner $t) => $t->same(2, $plan173()['duplicateStat4KeyBuckets'][0]['rowCount']),
    'planner stat4 expression partial current source next173 duplicate complete' => static fn (TestRunner $t) => $t->same(true, $plan173()['duplicateStat4KeyBuckets'][0]['complete']),
    'planner stat4 expression partial current source next173 fanout count' => static fn (TestRunner $t) => $t->same(4, count($plan173()['stat4SampleFanout'])),
    'planner stat4 expression partial current source next173 fanout rowids' => static fn (TestRunner $t) => $t->same([10, 12, 20, 40, 50], $plan173()['stat4FanoutRowids']),
    'planner stat4 expression partial current source next173 sample only none' => static fn (TestRunner $t) => $t->same([], $plan173()['sampleOnlyRowidsMissingCurrentFanout']),
    'planner stat4 expression partial current source next173 fanout plugin forms' => static fn (TestRunner $t) => $t->same([20], $plan173()['stat4SampleFanout'][1]['rowids']),
    'planner stat4 expression partial current source next173 fanout plugin mail' => static fn (TestRunner $t) => $t->same([50], $plan173()['stat4SampleFanout'][2]['rowids']),
    'planner stat4 expression partial current source next173 fanout plugin seo' => static fn (TestRunner $t) => $t->same([40], $plan173()['stat4SampleFanout'][3]['rowids']),
    'planner stat4 expression partial current source next173 selected ready' => static fn (TestRunner $t) => $t->same(true, $plan173()['selectedPlan']['next173Ready']),
    'planner stat4 expression partial current source next173 selected duplicate count' => static fn (TestRunner $t) => $t->same(1, $plan173()['selectedPlan']['next173DuplicateKeyCount']),
    'planner stat4 expression partial current source next173 selected fanout rowids' => static fn (TestRunner $t) => $t->same([10, 12, 20, 40, 50], $plan173()['selectedPlan']['next173FanoutRowids']),
    'planner stat4 expression partial current source next173 fanout hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan173()['stat4FanoutSignature'])),
    'planner stat4 expression partial current source next173 selected fanout hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan173()['selectedPlan']['next173FanoutSignature'])),
    'planner stat4 expression partial current source next173 fence fanout hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan173()['stat4Fence']['next173FanoutSignature'])),
    'planner stat4 expression partial current source next173 fence duplicate count' => static fn (TestRunner $t) => $t->same(1, $plan173()['stat4Fence']['next173DuplicateKeyCount']),
    'planner stat4 expression partial current source next173 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan173()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next173 cursor fence' => static fn (TestRunner $t) => $t->same('FenceStat4SampleWindow', $plan173()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next173 cursor expand before result' => static fn (TestRunner $t) => $t->same('ExpandStat4DuplicateKeyFanout', $plan173()['cursorProgram'][7]['opcode']),
    'planner stat4 expression partial current source next173 cursor expand rowids' => static fn (TestRunner $t) => $t->same([10, 12, 20, 40, 50], $plan173()['cursorProgram'][7]['rowids']),
    'planner stat4 expression partial current source next173 cursor result after expand' => static fn (TestRunner $t) => $t->same('ResultRow', $plan173()['cursorProgram'][8]['opcode']),
    'planner stat4 expression partial current source next173 current payload wins first duplicate' => static fn (TestRunner $t) => $t->same('fresh-cache-a', $plan173()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next173 current payload wins second duplicate' => static fn (TestRunner $t) => $t->same('fresh-cache-b', $plan173()['matchedRows'][1]['payload']['option_value']),
    'planner stat4 expression partial current source next173 stale old cache absent' => static fn (TestRunner $t) => $t->same(false, in_array(11, $plan173()['matchedRowids'], true)),
    'planner stat4 expression partial current source next173 stale old absent' => static fn (TestRunner $t) => $t->same(false, in_array(30, $plan173()['matchedRowids'], true)),
    'planner stat4 expression partial current source next173 theme absent' => static fn (TestRunner $t) => $t->same(false, in_array(60, $plan173()['matchedRowids'], true)),
    'planner stat4 expression partial current source next173 autoload no absent' => static fn (TestRunner $t) => $t->same(false, in_array(70, $plan173()['matchedRowids'], true)),
    'planner stat4 expression partial current source next173 null name absent' => static fn (TestRunner $t) => $t->same(false, in_array(80, $plan173()['matchedRowids'], true)),
    'planner stat4 expression partial current source next173 fresh fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $fresh173()['status']),
    'planner stat4 expression partial current source next173 single key fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $singleKey173()['status']),
    'planner stat4 expression partial current source next173 incomplete fanout fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $incompleteFanout173()['status']),
    'planner stat4 expression partial current source next173 incomplete bucket flagged' => static fn (TestRunner $t) => $t->same(false, $incompleteFanout173()['duplicateStat4KeyBuckets'][0]['complete']),
    'planner stat4 expression partial current source next173 detail' => static fn (TestRunner $t) => $t->contains('NEXT173 DUPLICATE SAMPLE FANOUT', $plan173()['detail']),
    'planner stat4 expression partial current source next173 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-stat4-expression-partial-current-source-next173', implode(',', $plan173()['dependencies'])),
    'planner stat4 expression partial current source next173 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan173()['dependency_closure']),
    'planner stat4 expression partial current source next173 non overlap' => static fn (TestRunner $t) => $t->contains('duplicate current rowids', $plan173()['non_overlap']),
    'planner stat4 expression partial current source next173 invalid sample' => static function (TestRunner $t) use ($current173, $plan173): void {
        $bad = $current173();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = ['plugin_cache'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan173(null, $bad));
    },
    'planner stat4 expression partial current source next173 invalid rowid' => static function (TestRunner $t) use ($current173, $plan173): void {
        $bad = $current173();
        $bad['rows'][0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan173(null, $bad));
    },
];
