<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq164 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull164 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprRange164 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared164 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-expression-partial-next164',
        'schemaCookie' => 1640,
        'stat4Generation' => 61,
        'rows' => [
            ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'stale-cache', 'updated_at' => 10],
            ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
            ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_range_partial_stat4_next164',
            'rootPage' => 16401,
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
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
            ],
        ]],
    ], $overrides);
};

$current164 = static function (array $overrides = []) use ($prepared164): array {
    $source = $prepared164([
        'name' => 'current-wp-options-stat4-expression-partial-next164',
        'schemaCookie' => 1649,
        'stat4Generation' => 72,
    ]);
    $source['indexes'][0]['rootPage'] = 16488;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
        ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 40]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['theme_mods', 50]],
    ];
    $source['rows'] = [
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh-cache', 'updated_at' => 15],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 50],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy', 'updated_at' => 60],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 70],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms164 = static fn (): array => [
    $exprRange164('LOWER( option_name )', '>=', 'plugin_cache'),
    $exprRange164('lower(option_name)', '<', 'plugin_t'),
    $eq164('autoload', 'yes'),
    $notNull164('option_name'),
];
$needed164 = ['option_name', 'option_value', 'updated_at'];
$plan164 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext164(
    $prepared ?? $prepared164(),
    $current ?? $current164(),
    $terms ?? $terms164(),
    $needed ?? $needed164,
);
$fresh164 = static function () use ($prepared164, $plan164): array {
    $source = $prepared164();

    return $plan164($source, $source);
};
$wideRange164 = static function () use ($terms164, $plan164): array {
    $terms = $terms164();
    $terms[0]['right'] = 'option_';

    return $plan164(null, null, $terms);
};
$noStat4164 = static function () use ($current164, $plan164): array {
    $current = $current164();
    $current['indexes'][0]['stat4Samples'] = [];

    return $plan164(null, $current);
};
$nonCovering164 = static function () use ($current164, $plan164): array {
    $current = $current164();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan164(null, $current);
};

return [
    'planner stat4 expression partial current source next164 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next164-ready', $plan164()['status']),
    'planner stat4 expression partial current source next164 selects current' => static fn (TestRunner $t) => $t->same('current', $plan164()['selectedSource']),
    'planner stat4 expression partial current source next164 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan164()['stalePreparedStatement']),
    'planner stat4 expression partial current source next164 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan164()['reprepareRequired']),
    'planner stat4 expression partial current source next164 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan164()['schemaCookieChanged']),
    'planner stat4 expression partial current source next164 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan164()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next164 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan164()['sourceSignatureChanged']),
    'planner stat4 expression partial current source next164 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_range_partial_stat4_next164', $plan164()['selectedPlan']['name']),
    'planner stat4 expression partial current source next164 root page' => static fn (TestRunner $t) => $t->same(16488, $plan164()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next164 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan164()['selectedPlan']['expression']),
    'planner stat4 expression partial current source next164 covering' => static fn (TestRunner $t) => $t->same(true, $plan164()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next164 table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan164()['tableLookupRequired']),
    'planner stat4 expression partial current source next164 partial by range' => static fn (TestRunner $t) => $t->same(true, $plan164()['selectedPlan']['partialPredicateImpliedByRange']),
    'planner stat4 expression partial current source next164 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan164()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next164 range lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan164()['selectedPlan']['rangeConstraint']['lower']),
    'planner stat4 expression partial current source next164 range upper' => static fn (TestRunner $t) => $t->same('plugin_t', $plan164()['selectedPlan']['rangeConstraint']['upper']),
    'planner stat4 expression partial current source next164 stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan164()['selectedPlan']['matchedStat4Keys']),
    'planner stat4 expression partial current source next164 stat4 rowids' => static fn (TestRunner $t) => $t->same([10, 20, 40, 30], $plan164()['selectedPlan']['matchedStat4Rowids']),
    'planner stat4 expression partial current source next164 estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan164()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next164 estimated cost' => static fn (TestRunner $t) => $t->same(5, $plan164()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial current source next164 row count' => static fn (TestRunner $t) => $t->same(4, $plan164()['selectedPlan']['matchedRowCount']),
    'planner stat4 expression partial current source next164 rowids' => static fn (TestRunner $t) => $t->same([10, 20, 40, 30], $plan164()['matchedRowids']),
    'planner stat4 expression partial current source next164 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan164()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next164 current payload wins' => static fn (TestRunner $t) => $t->same('fresh-cache', $plan164()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next164 mixed case key normalized' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan164()['matchedRows'][2]['expressionKey']),
    'planner stat4 expression partial current source next164 excludes theme row' => static fn (TestRunner $t) => $t->same(false, in_array(50, $plan164()['matchedRowids'], true)),
    'planner stat4 expression partial current source next164 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(60, $plan164()['matchedRowids'], true)),
    'planner stat4 expression partial current source next164 excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(70, $plan164()['matchedRowids'], true)),
    'planner stat4 expression partial current source next164 prepared stale row absent' => static fn (TestRunner $t) => $t->same(false, in_array('stale-cache', array_column(array_column($plan164()['matchedRows'], 'payload'), 'option_value'), true)),
    'planner stat4 expression partial current source next164 cursor open read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan164()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next164 cursor seek lower' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'key' => 'plugin_cache'], $plan164()['cursorProgram'][1]),
    'planner stat4 expression partial current source next164 cursor upper fence' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxLT', 'key' => 'plugin_t'], $plan164()['cursorProgram'][2]),
    'planner stat4 expression partial current source next164 cursor covering column' => static fn (TestRunner $t) => $t->same('ColumnFromIndex', $plan164()['cursorProgram'][3]['opcode']),
    'planner stat4 expression partial current source next164 cursor residual' => static fn (TestRunner $t) => $t->same('ResidualPartialCheck', $plan164()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next164 cursor result rowids' => static fn (TestRunner $t) => $t->same([10, 20, 40, 30], $plan164()['cursorProgram'][5]['rowids']),
    'planner stat4 expression partial current source next164 cursor next' => static fn (TestRunner $t) => $t->same('Next', $plan164()['cursorProgram'][6]['opcode']),
    'planner stat4 expression partial current source next164 fence cookie' => static fn (TestRunner $t) => $t->same(1649, $plan164()['stat4Fence']['schemaCookie']),
    'planner stat4 expression partial current source next164 fence generation' => static fn (TestRunner $t) => $t->same(72, $plan164()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial current source next164 fence hashes' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64], array_map('strlen', [$plan164()['stat4Fence']['sourceSignature'], $plan164()['stat4Fence']['rangeSignature'], $plan164()['stat4Fence']['stat4Signature'], $plan164()['stat4Fence']['rowStreamSignature']])),
    'planner stat4 expression partial current source next164 detail' => static fn (TestRunner $t) => $t->contains('STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT164 RANGE', $plan164()['detail']),
    'planner stat4 expression partial current source next164 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next164'], $plan164()['dependencies']),
    'planner stat4 expression partial current source next164 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan164()['dependency_closure']),
    'planner stat4 expression partial current source next164 non overlap' => static fn (TestRunner $t) => $t->contains('partial expression index from current range bounds', $plan164()['non_overlap']),
    'planner stat4 expression partial current source next164 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh164()['selectedSource']),
    'planner stat4 expression partial current source next164 fresh rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30], $fresh164()['matchedRowids']),
    'planner stat4 expression partial current source next164 wide range cannot prove partial' => static fn (TestRunner $t) => $t->same('requires-next-stage', $wideRange164()['status']),
    'planner stat4 expression partial current source next164 wide range fallback cursor' => static fn (TestRunner $t) => $t->same('FallbackFullScan', $wideRange164()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next164 no stat4 falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4164()['status']),
    'planner stat4 expression partial current source next164 noncovering keeps ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next164-ready', $nonCovering164()['status']),
    'planner stat4 expression partial current source next164 noncovering table lookup' => static fn (TestRunner $t) => $t->same(true, $nonCovering164()['tableLookupRequired']),
    'planner stat4 expression partial current source next164 noncovering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering164()['cursorProgram'][3]['opcode']),
    'planner stat4 expression partial current source next164 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan164(null, null, [])),
    'planner stat4 expression partial current source next164 invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan164(null, null, null, [])),
    'planner stat4 expression partial current source next164 invalid stat4 sample' => static function (TestRunner $t) use ($current164, $plan164): void {
        $bad = $current164();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => $plan164(null, $bad));
    },
    'planner stat4 expression partial current source next164 invalid rowid' => static function (TestRunner $t) use ($current164, $plan164): void {
        $bad = $current164();
        $bad['rows'][0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan164(null, $bad));
    },
];
