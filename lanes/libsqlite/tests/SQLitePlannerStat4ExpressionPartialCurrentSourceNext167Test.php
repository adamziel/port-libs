<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq167 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull167 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprRange167 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared167 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-sample-window-next167',
        'schemaCookie' => 1670,
        'stat4Generation' => 81,
        'rows' => [
            ['rowid' => 11, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'stale-cache', 'updated_at' => 11],
            ['rowid' => 22, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 22],
            ['rowid' => 33, 'autoload' => 'yes', 'option_name' => 'plugin_old', 'option_value' => 'old', 'updated_at' => 33],
            ['rowid' => 44, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 44],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_sample_window_next167',
            'rootPage' => 16701,
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
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 22]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_old', 33]],
                ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 44]],
            ],
        ]],
    ], $overrides);
};

$current167 = static function (array $overrides = []) use ($prepared167): array {
    $source = $prepared167([
        'name' => 'current-wp-options-stat4-sample-window-next167',
        'schemaCookie' => 1678,
        'stat4Generation' => 94,
    ]);
    $source['indexes'][0]['rootPage'] = 16788;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
        ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 22]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 55]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 44]],
        ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['theme_mods', 66]],
    ];
    $source['rows'] = [
        ['rowid' => 55, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 55],
        ['rowid' => 11, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh-cache', 'updated_at' => 15],
        ['rowid' => 22, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 22],
        ['rowid' => 44, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 44],
        ['rowid' => 66, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 66],
        ['rowid' => 77, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy', 'updated_at' => 77],
        ['rowid' => 88, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 88],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms167 = static fn (): array => [
    $exprRange167('LOWER( option_name )', '>=', 'plugin_cache'),
    $exprRange167('lower(option_name)', '<', 'plugin_t'),
    $eq167('autoload', 'yes'),
    $notNull167('option_name'),
];
$needed167 = ['option_name', 'option_value', 'updated_at'];
$plan167 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext167(
    $prepared ?? $prepared167(),
    $current ?? $current167(),
    $terms ?? $terms167(),
    $needed ?? $needed167,
);
$fresh167 = static function () use ($prepared167, $plan167): array {
    $source = $prepared167();

    return $plan167($source, $source);
};
$wide167 = static function () use ($terms167, $plan167): array {
    $terms = $terms167();
    $terms[0]['right'] = 'option_';

    return $plan167(null, null, $terms);
};
$noSamples167 = static function () use ($current167, $plan167): array {
    $current = $current167();
    $current['indexes'][0]['stat4Samples'] = [];

    return $plan167(null, $current);
};
$nonCovering167 = static function () use ($current167, $plan167): array {
    $current = $current167();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan167(null, $current);
};

return [
    'planner stat4 expression partial current source next167 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next167-ready', $plan167()['status']),
    'planner stat4 expression partial current source next167 selects current' => static fn (TestRunner $t) => $t->same('current', $plan167()['selectedSource']),
    'planner stat4 expression partial current source next167 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan167()['stalePreparedStatement']),
    'planner stat4 expression partial current source next167 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan167()['reprepareRequired']),
    'planner stat4 expression partial current source next167 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan167()['schemaCookieChanged']),
    'planner stat4 expression partial current source next167 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan167()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next167 source signature changed' => static fn (TestRunner $t) => $t->same(true, $plan167()['sourceSignatureChanged']),
    'planner stat4 expression partial current source next167 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_sample_window_next167', $plan167()['selectedPlan']['name']),
    'planner stat4 expression partial current source next167 root page' => static fn (TestRunner $t) => $t->same(16788, $plan167()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next167 covering' => static fn (TestRunner $t) => $t->same(true, $plan167()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next167 table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan167()['tableLookupRequired']),
    'planner stat4 expression partial current source next167 base partial proof kept' => static fn (TestRunner $t) => $t->same(true, $plan167()['selectedPlan']['partialPredicateImpliedByRange']),
    'planner stat4 expression partial current source next167 base stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan167()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next167 current rowids' => static fn (TestRunner $t) => $t->same([11, 22, 55, 44], $plan167()['matchedRowids']),
    'planner stat4 expression partial current source next167 current keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan167()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next167 prepared window rowids' => static fn (TestRunner $t) => $t->same([11, 22, 33, 44], $plan167()['preparedSampleWindow']['rowids']),
    'planner stat4 expression partial current source next167 current window rowids' => static fn (TestRunner $t) => $t->same([11, 22, 55, 44], $plan167()['currentSampleWindow']['rowids']),
    'planner stat4 expression partial current source next167 prepared window keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_old', 'plugin_seo'], $plan167()['preparedSampleWindow']['keys']),
    'planner stat4 expression partial current source next167 current window keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan167()['currentSampleWindow']['keys']),
    'planner stat4 expression partial current source next167 stale rowid blocked' => static fn (TestRunner $t) => $t->same([33], $plan167()['stalePreparedRowidsBlockedBySampleFence']),
    'planner stat4 expression partial current source next167 current rowid admitted' => static fn (TestRunner $t) => $t->same([55], $plan167()['currentSourceRowidsAdmittedBySampleFence']),
    'planner stat4 expression partial current source next167 refreshed rowids' => static fn (TestRunner $t) => $t->same([11, 22, 44], $plan167()['currentSourceRowidsRefreshedBySampleFence']),
    'planner stat4 expression partial current source next167 fence changed' => static fn (TestRunner $t) => $t->same(true, $plan167()['postAnalyzeSampleFenceChanged']),
    'planner stat4 expression partial current source next167 fence prepared keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_old', 'plugin_seo'], $plan167()['postAnalyzeSampleFence']['preparedKeys']),
    'planner stat4 expression partial current source next167 fence current keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan167()['postAnalyzeSampleFence']['currentKeys']),
    'planner stat4 expression partial current source next167 fence prepared rowids' => static fn (TestRunner $t) => $t->same([11, 22, 33, 44], $plan167()['postAnalyzeSampleFence']['preparedRowids']),
    'planner stat4 expression partial current source next167 fence current rowids' => static fn (TestRunner $t) => $t->same([11, 22, 55, 44], $plan167()['postAnalyzeSampleFence']['currentRowids']),
    'planner stat4 expression partial current source next167 fence lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan167()['postAnalyzeSampleFence']['lowerKey']),
    'planner stat4 expression partial current source next167 fence upper' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan167()['postAnalyzeSampleFence']['upperKey']),
    'planner stat4 expression partial current source next167 estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan167()['postAnalyzeSampleFence']['estimatedRows']),
    'planner stat4 expression partial current source next167 signatures are hashes' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64], array_map('strlen', [$plan167()['preparedSampleWindow']['signature'], $plan167()['currentSampleWindow']['signature'], $plan167()['postAnalyzeSampleFence']['signature'], $plan167()['sampleWindowSignature']])),
    'planner stat4 expression partial current source next167 stat4 fence prepared window hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan167()['stat4Fence']['next167PreparedWindowSignature'])),
    'planner stat4 expression partial current source next167 stat4 fence current window hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan167()['stat4Fence']['next167CurrentWindowSignature'])),
    'planner stat4 expression partial current source next167 selected ready flag' => static fn (TestRunner $t) => $t->same(true, $plan167()['selectedPlan']['next167Ready']),
    'planner stat4 expression partial current source next167 selected stale blocked' => static fn (TestRunner $t) => $t->same([33], $plan167()['selectedPlan']['next167StaleBlockedRowids']),
    'planner stat4 expression partial current source next167 selected current only' => static fn (TestRunner $t) => $t->same([55], $plan167()['selectedPlan']['next167CurrentOnlyRowids']),
    'planner stat4 expression partial current source next167 selected sample keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan167()['selectedPlan']['next167CurrentSampleKeys']),
    'planner stat4 expression partial current source next167 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan167()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next167 cursor fence' => static fn (TestRunner $t) => $t->same('FenceStat4SampleWindow', $plan167()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next167 cursor lower' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'key' => 'plugin_cache'], $plan167()['cursorProgram'][2]),
    'planner stat4 expression partial current source next167 cursor upper' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxLE', 'key' => 'plugin_seo'], $plan167()['cursorProgram'][3]),
    'planner stat4 expression partial current source next167 cursor block stale' => static fn (TestRunner $t) => $t->same(['opcode' => 'BlockPreparedRowids', 'rowids' => [33]], $plan167()['cursorProgram'][4]),
    'planner stat4 expression partial current source next167 cursor admit current' => static fn (TestRunner $t) => $t->same(['opcode' => 'AdmitCurrentRowids', 'rowids' => [55]], $plan167()['cursorProgram'][5]),
    'planner stat4 expression partial current source next167 cursor covering' => static fn (TestRunner $t) => $t->same('ColumnFromIndex', $plan167()['cursorProgram'][6]['opcode']),
    'planner stat4 expression partial current source next167 cursor result' => static fn (TestRunner $t) => $t->same([11, 22, 55, 44], $plan167()['cursorProgram'][7]['rowids']),
    'planner stat4 expression partial current source next167 cursor next' => static fn (TestRunner $t) => $t->same('Next', $plan167()['cursorProgram'][8]['opcode']),
    'planner stat4 expression partial current source next167 current payload wins' => static fn (TestRunner $t) => $t->same('fresh-cache', $plan167()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next167 stale old absent' => static fn (TestRunner $t) => $t->same(false, in_array(33, $plan167()['matchedRowids'], true)),
    'planner stat4 expression partial current source next167 theme absent' => static fn (TestRunner $t) => $t->same(false, in_array(66, $plan167()['matchedRowids'], true)),
    'planner stat4 expression partial current source next167 autoload no absent' => static fn (TestRunner $t) => $t->same(false, in_array(77, $plan167()['matchedRowids'], true)),
    'planner stat4 expression partial current source next167 null name absent' => static fn (TestRunner $t) => $t->same(false, in_array(88, $plan167()['matchedRowids'], true)),
    'planner stat4 expression partial current source next167 fresh falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $fresh167()['status']),
    'planner stat4 expression partial current source next167 fresh no fence change' => static fn (TestRunner $t) => $t->same(false, $fresh167()['postAnalyzeSampleFenceChanged']),
    'planner stat4 expression partial current source next167 wide range fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $wide167()['status']),
    'planner stat4 expression partial current source next167 no samples fallback' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noSamples167()['status']),
    'planner stat4 expression partial current source next167 noncovering ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next167-ready', $nonCovering167()['status']),
    'planner stat4 expression partial current source next167 noncovering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering167()['cursorProgram'][6]['opcode']),
    'planner stat4 expression partial current source next167 detail' => static fn (TestRunner $t) => $t->contains('NEXT167 SAMPLE WINDOW', $plan167()['detail']),
    'planner stat4 expression partial current source next167 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-stat4-expression-partial-current-source-next167', implode(',', $plan167()['dependencies'])),
    'planner stat4 expression partial current source next167 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan167()['dependency_closure']),
    'planner stat4 expression partial current source next167 non overlap' => static fn (TestRunner $t) => $t->contains('post-ANALYZE STAT4 sample-window drift', $plan167()['non_overlap']),
    'planner stat4 expression partial current source next167 invalid rowid' => static function (TestRunner $t) use ($current167, $plan167): void {
        $bad = $current167();
        $bad['rows'][0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan167(null, $bad));
    },
    'planner stat4 expression partial current source next167 invalid stat4' => static function (TestRunner $t) use ($current167, $plan167): void {
        $bad = $current167();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = ['plugin_cache'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan167(null, $bad));
    },
];
