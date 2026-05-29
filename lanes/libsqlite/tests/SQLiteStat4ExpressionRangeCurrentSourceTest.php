<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4ExpressionRangeCurrentSourceNextPlan;

$expr104 = static fn (string $operator, mixed $value): array => [
    'operator' => $operator,
    'left' => ['expression' => 'substr(option_name, 1, 12)'],
    'right' => $value,
];
$and104 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$predicate104 = $and104(
    $expr104('>=', 'plugin_cache'),
    $expr104('<', 'plugin_forms')
);
$needed104 = ['option_name', 'option_value', 'autoload'];

$source104 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-before-plugin-analyze',
        'schemaCookie' => 104,
        'stat4Generation' => 21,
        'coveringColumns' => ['option_name', 'option_value', 'autoload'],
        'indexes' => [[
            'name' => 'idx_wp_options_expr_plugin_prefix_next104',
            'rootPage' => 10401,
            'expression' => 'substr(option_name, 1, 12)',
            'estimatedRows' => 320,
            'coveringColumns' => ['option_name', 'option_value', 'autoload'],
            'stat4Samples' => [
                ['neq' => '12 12', 'nlt' => '0 0', 'sample' => ['plugin_alpha', 11]],
                ['neq' => '48 48', 'nlt' => '12 12', 'sample' => ['plugin_cache', 21]],
                ['neq' => '72 72', 'nlt' => '60 60', 'sample' => ['plugin_forms', 31]],
                ['neq' => '14 14', 'nlt' => '132 132', 'sample' => ['plugin_seo', 41]],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_expr_plugin_prefix_next104 ON wp_options(substr(option_name, 1, 12), option_value)',
        ], [
            'name' => 'idx_wp_options_plain_name_next104',
            'rootPage' => 10402,
            'expression' => 'lower(option_name)',
            'estimatedRows' => 500,
            'coveringColumns' => ['option_name'],
            'stat4Samples' => [
                ['neq' => 30, 'nlt' => 0, 'sample' => ['plugin_cache', 51]],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_plain_name_next104 ON wp_options(lower(option_name))',
        ]],
    ];
};

$current104 = static function () use ($source104): array {
    $source = $source104([
        'name' => 'current-after-plugin-import-analyze',
        'schemaCookie' => 105,
        'stat4Generation' => 22,
    ]);
    $source['indexes'][0]['rootPage'] = 10410;
    $source['indexes'][0]['estimatedRows'] = 220;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '3 3', 'nlt' => '0 0', 'sample' => ['plugin_alpha', 101]],
        ['neq' => '7 7', 'nlt' => '3 3', 'sample' => ['plugin_cache', 102]],
        ['neq' => '9 9', 'nlt' => '10 10', 'sample' => ['plugin_commerce', 103]],
        ['neq' => '6 6', 'nlt' => '19 19', 'sample' => ['plugin_editor', 104]],
        ['neq' => '5 5', 'nlt' => '25 25', 'sample' => ['plugin_forms', 105]],
        ['neq' => '2 2', 'nlt' => '30 30', 'sample' => ['plugin_seo', 106]],
    ];
    $source['indexes'][1]['estimatedRows'] = 70;

    return $source;
};

$plan104 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $columns = null): array => SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange(
    $prepared ?? $source104(),
    $current ?? $current104(),
    $predicate ?? $GLOBALS['predicate104'],
    $columns ?? $GLOBALS['needed104'],
);

$GLOBALS['predicate104'] = $predicate104;
$GLOBALS['needed104'] = $needed104;

$tests = [
    'stat4 expression range current source next104 selects current source' => static fn (TestRunner $t) => $t->same('current', $plan104()['selectedSource']),
    'stat4 expression range current source next104 marks stale prepared statement' => static fn (TestRunner $t) => $t->same(true, $plan104()['stalePreparedStatement']),
    'stat4 expression range current source next104 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan104()['reprepareRequired']),
    'stat4 expression range current source next104 schema cookie changed' => static fn (TestRunner $t) => $t->same(true, $plan104()['schemaCookieChanged']),
    'stat4 expression range current source next104 stat4 generation changed' => static fn (TestRunner $t) => $t->same(true, $plan104()['stat4GenerationChanged']),
    'stat4 expression range current source next104 index signature changed' => static fn (TestRunner $t) => $t->same(true, $plan104()['indexSignatureChanged']),
    'stat4 expression range current source next104 projection stable' => static fn (TestRunner $t) => $t->same(false, $plan104()['projectionChanged']),
    'stat4 expression range current source next104 status usable' => static fn (TestRunner $t) => $t->same('usable', $plan104()['status']),
    'stat4 expression range current source next104 expression plan true' => static fn (TestRunner $t) => $t->same(true, $plan104()['expressionRangePlan']),
    'stat4 expression range current source next104 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_expr_plugin_prefix_next104', $plan104()['selectedPlan']['name']),
    'stat4 expression range current source next104 selected root page is current' => static fn (TestRunner $t) => $t->same(10410, $plan104()['selectedPlan']['rootPage']),
    'stat4 expression range current source next104 prepared root page retained' => static fn (TestRunner $t) => $t->same(10401, $plan104()['preparedSource']['rootPage']),
    'stat4 expression range current source next104 current root page summary' => static fn (TestRunner $t) => $t->same(10410, $plan104()['currentSource']['rootPage']),
    'stat4 expression range current source next104 expression summary' => static fn (TestRunner $t) => $t->same('substr(option_name, 1, 12)', $plan104()['currentSource']['expression']),
    'stat4 expression range current source next104 lower bound summary' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan104()['currentSource']['lowerBound']),
    'stat4 expression range current source next104 upper bound summary' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan104()['currentSource']['upperBound']),
    'stat4 expression range current source next104 lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan104()['selectedPlan']['lowerInclusive']),
    'stat4 expression range current source next104 upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan104()['selectedPlan']['upperInclusive']),
    'stat4 expression range current source next104 uses stat4' => static fn (TestRunner $t) => $t->same(true, $plan104()['selectedPlan']['stat4Used']),
    'stat4 expression range current source next104 matched current sample count' => static fn (TestRunner $t) => $t->same(3, $plan104()['selectedPlan']['stat4MatchedSamples']),
    'stat4 expression range current source next104 prepared matched sample count' => static fn (TestRunner $t) => $t->same(1, $plan104()['preparedSource']['stat4MatchedSamples']),
    'stat4 expression range current source next104 current estimate from neq sum' => static fn (TestRunner $t) => $t->same(22, $plan104()['selectedPlan']['stat4Estimate']),
    'stat4 expression range current source next104 current estimated rows capped' => static fn (TestRunner $t) => $t->same(22, $plan104()['currentSource']['estimatedRows']),
    'stat4 expression range current source next104 prepared estimate is wider' => static fn (TestRunner $t) => $t->same(48, $plan104()['preparedSource']['estimatedRows']),
    'stat4 expression range current source next104 estimate delta is negative' => static fn (TestRunner $t) => $t->same(-26, $plan104()['estimateDelta']),
    'stat4 expression range current source next104 selected cost lower than prepared' => static fn (TestRunner $t) => $t->same(true, $plan104()['selectedPlan']['estimatedCost'] < $plan104()['preparedSource']['estimatedCost']),
    'stat4 expression range current source next104 cost delta is negative' => static fn (TestRunner $t) => $t->same(true, $plan104()['costDelta'] < 0),
    'stat4 expression range current source next104 covering selected' => static fn (TestRunner $t) => $t->same(true, $plan104()['selectedPlan']['covering']),
    'stat4 expression range current source next104 next source covering expression index' => static fn (TestRunner $t) => $t->same('covering-expression-index', $plan104()['selectedPlan']['nextSource']),
    'stat4 expression range current source next104 ranked plan count' => static fn (TestRunner $t) => $t->same(1, $plan104()['selectedPlan']['rankedPlanCount']),
    'stat4 expression range current source next104 ranked plan name' => static fn (TestRunner $t) => $t->same(['idx_wp_options_expr_plugin_prefix_next104'], $plan104()['selectedPlan']['rankedPlanNames']),
    'stat4 expression range current source next104 lower boundary current before range' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan104()['selectedPlan']['stat4RangeCurrentNext']['lower']['current']['key']),
    'stat4 expression range current source next104 lower boundary next in range' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan104()['selectedPlan']['stat4RangeCurrentNext']['lower']['next']['key']),
    'stat4 expression range current source next104 upper boundary current in range' => static fn (TestRunner $t) => $t->same('plugin_editor', $plan104()['selectedPlan']['stat4RangeCurrentNext']['upper']['current']['key']),
    'stat4 expression range current source next104 upper boundary next after range' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan104()['selectedPlan']['stat4RangeCurrentNext']['upper']['next']['key']),
    'stat4 expression range current source next104 first matched current key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan104()['selectedPlan']['stat4MatchedCurrentNext'][0]['current']['key']),
    'stat4 expression range current source next104 first matched next key' => static fn (TestRunner $t) => $t->same('plugin_commerce', $plan104()['selectedPlan']['stat4MatchedCurrentNext'][0]['next']['key']),
    'stat4 expression range current source next104 last matched next null' => static fn (TestRunner $t) => $t->same(null, $plan104()['selectedPlan']['stat4MatchedCurrentNext'][2]['next']),
    'stat4 expression range current source next104 rowid preserved in samples' => static fn (TestRunner $t) => $t->same(104, $plan104()['selectedPlan']['stat4MatchedCurrentNext'][2]['current']['rowid']),
    'stat4 expression range current source next104 neq preserved in samples' => static fn (TestRunner $t) => $t->same(9, $plan104()['selectedPlan']['stat4MatchedCurrentNext'][1]['current']['neq']),
    'stat4 expression range current source next104 nlt preserved in samples' => static fn (TestRunner $t) => $t->same(19, $plan104()['selectedPlan']['stat4MatchedCurrentNext'][2]['current']['nlt']),
    'stat4 expression range current source next104 prepared source name' => static fn (TestRunner $t) => $t->same('prepared-before-plugin-analyze', $plan104()['preparedSource']['name']),
    'stat4 expression range current source next104 current source name' => static fn (TestRunner $t) => $t->same('current-after-plugin-import-analyze', $plan104()['currentSource']['name']),
    'stat4 expression range current source next104 projection signatures equal' => static fn (TestRunner $t) => $t->same($plan104()['preparedSource']['projectionSignature'], $plan104()['currentSource']['projectionSignature']),
    'stat4 expression range current source next104 index signatures differ' => static fn (TestRunner $t) => $t->same(false, $plan104()['preparedSource']['indexSignature'] === $plan104()['currentSource']['indexSignature']),
    'stat4 expression range current source next104 detail reparses current source' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 EXPRESSION RANGE USING CURRENT SOURCE current-after-plugin-import-analyze', $plan104()['detail']),
    'stat4 expression range current source next104 detail names expression range' => static fn (TestRunner $t) => $t->contains('USING STAT4 EXPRESSION RANGE substr(option_name, 1, 12)', $plan104()['detail']),
    'stat4 expression range current source next104 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-stat4-expression-range-current-source-next104', $plan104()['dependencies'], true)),
    'stat4 expression range current source next104 dependency closure note' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan104()['dependency_closure']),
    'stat4 expression range current source next104 reuses prepared when signatures match' => static function (TestRunner $t) use ($source104): void {
        $fresh = $source104(['name' => 'current-same-stat4']);
        $t->same('prepared', SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange($source104(), $fresh, $GLOBALS['predicate104'], $GLOBALS['needed104'])['selectedSource']);
    },
    'stat4 expression range current source next104 no reprepare when signatures match' => static function (TestRunner $t) use ($source104): void {
        $fresh = $source104(['name' => 'current-same-stat4']);
        $t->same(false, SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange($source104(), $fresh, $GLOBALS['predicate104'], $GLOBALS['needed104'])['reprepareRequired']);
    },
    'stat4 expression range current source next104 schema cookie alone invalidates' => static function (TestRunner $t) use ($source104): void {
        $t->same(true, SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange($source104(), $source104(['schemaCookie' => 106]), $GLOBALS['predicate104'], $GLOBALS['needed104'])['schemaCookieChanged']);
    },
    'stat4 expression range current source next104 stat4 generation alone invalidates' => static function (TestRunner $t) use ($source104): void {
        $t->same(true, SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange($source104(), $source104(['stat4Generation' => 23]), $GLOBALS['predicate104'], $GLOBALS['needed104'])['stat4GenerationChanged']);
    },
    'stat4 expression range current source next104 projection change invalidates' => static function (TestRunner $t) use ($source104): void {
        $t->same(true, SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange($source104(), $source104(['coveringColumns' => ['option_name']]), $GLOBALS['predicate104'], $GLOBALS['needed104'])['projectionChanged']);
    },
    'stat4 expression range current source next104 missing covering column table lookup' => static fn (TestRunner $t) => $t->same('table-rowid-lookup', $plan104(null, null, null, ['option_name', 'missing_meta'])['selectedPlan']['nextSource']),
    'stat4 expression range current source next104 missing covering column not covering' => static fn (TestRunner $t) => $t->same(false, $plan104(null, null, null, ['option_name', 'missing_meta'])['selectedPlan']['covering']),
    'stat4 expression range current source next104 between lower bound' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan104(null, null, ['operator' => 'BETWEEN', 'left' => ['expression' => 'substr(option_name,1,12)'], 'right' => ['plugin_cache', 'plugin_forms']])['selectedPlan']['lowerBound']),
    'stat4 expression range current source next104 between upper inclusive' => static fn (TestRunner $t) => $t->same(true, $plan104(null, null, ['operator' => 'BETWEEN', 'left' => ['expression' => 'substr(option_name,1,12)'], 'right' => ['plugin_cache', 'plugin_forms']])['selectedPlan']['upperInclusive']),
    'stat4 expression range current source next104 unsupported expression scans table' => static fn (TestRunner $t) => $t->same('unusable', $plan104(null, null, $expr104('=', 'plugin_cache'))['status']),
    'stat4 expression range current source next104 validates schema cookie' => static function (TestRunner $t) use ($source104): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange($source104(['schemaCookie' => -1]), $source104(), $GLOBALS['predicate104'], $GLOBALS['needed104']));
    },
    'stat4 expression range current source next104 validates stat4 generation' => static function (TestRunner $t) use ($source104): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange($source104(['stat4Generation' => -1]), $source104(), $GLOBALS['predicate104'], $GLOBALS['needed104']));
    },
    'stat4 expression range current source next104 validates indexes list' => static function (TestRunner $t) use ($source104): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange($source104(['indexes' => ['bad' => []]]), $source104(), $GLOBALS['predicate104'], $GLOBALS['needed104']));
    },
    'stat4 expression range current source next104 validates expression index' => static function (TestRunner $t) use ($source104): void {
        $source = $source104();
        $source['indexes'][0]['expression'] = 'option_name';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange($source, $source104(), $GLOBALS['predicate104'], $GLOBALS['needed104']));
    },
    'stat4 expression range current source next104 validates sample rowid' => static function (TestRunner $t) use ($source104): void {
        $source = $source104();
        $source['indexes'][0]['stat4Samples'][0]['sample'][1] = 'rowid';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionRangeCurrentSourceNextPlan::compareExpressionRange($source, $source104(), $GLOBALS['predicate104'], $GLOBALS['needed104']));
    },
];

return $tests;
