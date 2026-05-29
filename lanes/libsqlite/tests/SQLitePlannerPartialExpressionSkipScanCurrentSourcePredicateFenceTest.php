<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerPartialExpressionSkipScanCurrentSourceNextPlan;

$rows139 = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => null, 'option_value' => 'null-name', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'Plugin_Cache', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'theme_mods_child', 'option_value' => 'theme', 'kind' => 'theme'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'a:4', 'kind' => 'plugin'],
    ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_mail', 'option_value' => 'a:5', 'kind' => 'plugin'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'a:6', 'kind' => 'plugin'],
];
$currentRows139 = static function () use ($rows139): array {
    $rows = $rows139();
    $rows[] = ['rowid' => 9, 'autoload' => 'no', 'option_name' => 'plugin_security', 'option_value' => 'a:7', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'PLUGIN_ZETA', 'option_value' => 'a:8', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 11, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'new-null', 'kind' => 'plugin'];

    return $rows;
};
$stat4139 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_cache', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'no', 'suffix' => 'plugin_forms', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'no', 'suffix' => 'plugin_mail', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_seo', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
];
$currentStat4139 = static function () use ($stat4139): array {
    $samples = $stat4139();
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_security', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];
    $samples[] = ['prefix' => 'yes', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1];

    return $samples;
};
$source139 = static function (array $overrides = []) use ($rows139, $stat4139): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1390',
        'schemaCookie' => 1390,
        'stat4Generation' => 41,
        'indexName' => 'idx_wp_options_autoload_lower_name_partial_next139',
        'rootPage' => 13901,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_next139',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rows139(),
        'stat4Samples' => $stat4139(),
    ];
};
$currentSource139 = static function (array $overrides = []) use ($currentRows139, $currentStat4139): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1391',
        'schemaCookie' => 1391,
        'stat4Generation' => 42,
        'indexName' => 'idx_wp_options_autoload_lower_name_partial_next139',
        'rootPage' => 13909,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name_next139',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRows139(),
        'stat4Samples' => $currentStat4139(),
    ];
};
$preparedPredicate139 = new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin');
$currentPredicate139 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$query139 = [
    ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
    ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
    ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
];
$order139 = [
    ['expression' => 'autoload'],
    ['expression' => 'lower(option_name)'],
];
$needed139 = ['option_name', 'option_value', 'kind'];
$plan139 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?SQLiteIndexPredicate $preparedPredicate = null,
    ?SQLiteIndexPredicate $currentPredicate = null,
    ?array $order = null,
    ?array $needed = null,
): array => SQLitePlannerPartialExpressionSkipScanCurrentSourceNextPlan::materializeCurrentPredicateFence(
    $prepared ?? $source139(),
    $current ?? $currentSource139(),
    $preparedPredicate ?? $preparedPredicate139,
    $currentPredicate ?? $currentPredicate139,
    $query139,
    $order ?? $order139,
    $needed ?? $needed139,
);

$tests = [
    'planner partial expression skipscan current source next139 status ready' => static fn (TestRunner $t) => $t->same('partial-expression-skipscan-current-source-next139-ready', $plan139()['status']),
    'planner partial expression skipscan current source next139 selects current' => static fn (TestRunner $t) => $t->same('current', $plan139()['selectedSource']),
    'planner partial expression skipscan current source next139 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan139()['stalePreparedStatement']),
    'planner partial expression skipscan current source next139 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan139()['reprepareRequired']),
    'planner partial expression skipscan current source next139 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan139()['schemaCookieChanged']),
    'planner partial expression skipscan current source next139 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan139()['stat4GenerationChanged']),
    'planner partial expression skipscan current source next139 predicate changed' => static fn (TestRunner $t) => $t->same(true, $plan139()['partialPredicateChanged']),
    'planner partial expression skipscan current source next139 predicate changed only false with source changes' => static fn (TestRunner $t) => $t->same(false, $plan139()['partialPredicateChangedOnly']),
    'planner partial expression skipscan current source next139 prepared signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan139()['preparedPredicateSignature'])),
    'planner partial expression skipscan current source next139 current signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan139()['currentPredicateSignature'])),
    'planner partial expression skipscan current source next139 signatures differ' => static fn (TestRunner $t) => $t->same(false, $plan139()['preparedPredicateSignature'] === $plan139()['currentPredicateSignature']),
    'planner partial expression skipscan current source next139 prepared term operator' => static fn (TestRunner $t) => $t->same(SQLiteIndexPredicate::EQUALS, $plan139()['preparedPartialTerms']['operator']),
    'planner partial expression skipscan current source next139 current term operator' => static fn (TestRunner $t) => $t->same(SQLiteIndexPredicate::AND, $plan139()['currentPartialTerms']['operator']),
    'planner partial expression skipscan current source next139 current term count' => static fn (TestRunner $t) => $t->same(2, count($plan139()['currentPartialTerms']['value'])),
    'planner partial expression skipscan current source next139 selected expression flag' => static fn (TestRunner $t) => $t->same(true, $plan139()['selectedPlan']['expressionSkipScan']),
    'planner partial expression skipscan current source next139 selected range expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan139()['selectedPlan']['rangeExpression']),
    'planner partial expression skipscan current source next139 selected expression column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name_next139', $plan139()['selectedPlan']['rangeExpressionColumn']),
    'planner partial expression skipscan current source next139 uses skip scan' => static fn (TestRunner $t) => $t->same(true, $plan139()['selectedPlan']['usesSkipScan']),
    'planner partial expression skipscan current source next139 loop prefixes' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($plan139()['selectedPlan']['loops'], 'prefix')),
    'planner partial expression skipscan current source next139 current rowids' => static fn (TestRunner $t) => $t->same([1, 2, 4, 6, 7, 9, 8, 10], $plan139()['currentSkipScanRowids']),
    'planner partial expression skipscan current source next139 prepared rowids' => static fn (TestRunner $t) => $t->same([1, 2, 4, 6, 7, 8], $plan139()['preparedSkipScanRowids']),
    'planner partial expression skipscan current source next139 admitted rowids' => static fn (TestRunner $t) => $t->same([9, 10], $plan139()['currentPredicateAdmittedRowids']),
    'planner partial expression skipscan current source next139 rejected by rowid delta none' => static fn (TestRunner $t) => $t->same([], $plan139()['stalePredicateRejectedRowids']),
    'planner partial expression skipscan current source next139 stable rowids' => static fn (TestRunner $t) => $t->same([1, 2, 4, 6, 7, 8], $plan139()['stablePredicateRowids']),
    'planner partial expression skipscan current source next139 rejects null rows by predicate change' => static fn (TestRunner $t) => $t->same([3, 11], $plan139()['currentRowsRejectedByPredicateChange']),
    'planner partial expression skipscan current source next139 admits no row by predicate change' => static fn (TestRunner $t) => $t->same([], $plan139()['currentRowsAdmittedByPredicateChange']),
    'planner partial expression skipscan current source next139 predicate recheck required' => static fn (TestRunner $t) => $t->same(true, $plan139()['predicateRecheckRequired']),
    'planner partial expression skipscan current source next139 predicate opcode' => static fn (TestRunner $t) => $t->same('IfNotPartialPredicate', $plan139()['predicateRecheckOpcode']),
    'planner partial expression skipscan current source next139 fence predicate opcode' => static fn (TestRunner $t) => $t->same('IfNotPartialPredicate', $plan139()['currentSourceFence']['predicateRecheckOpcode']),
    'planner partial expression skipscan current source next139 fence row count' => static fn (TestRunner $t) => $t->same(8, $plan139()['currentSourceFence']['skipScanRowCount']),
    'planner partial expression skipscan current source next139 fence signature current' => static fn (TestRunner $t) => $t->same($plan139()['currentPredicateSignature'], $plan139()['currentSourceFence']['partialPredicateSignature']),
    'planner partial expression skipscan current source next139 selected samples used' => static fn (TestRunner $t) => $t->same(8, $plan139()['selectedPlan']['stat4SamplesUsed']),
    'planner partial expression skipscan current source next139 estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan139()['selectedPlan']['estimatedRows']),
    'planner partial expression skipscan current source next139 estimated cost' => static fn (TestRunner $t) => $t->same(37, $plan139()['selectedPlan']['estimatedCost']),
    'planner partial expression skipscan current source next139 covered row count' => static fn (TestRunner $t) => $t->same(8, $plan139()['selectedPlan']['coveredRowCount']),
    'planner partial expression skipscan current source next139 covering true' => static fn (TestRunner $t) => $t->same(true, $plan139()['selectedPlan']['covering']),
    'planner partial expression skipscan current source next139 table seek false' => static fn (TestRunner $t) => $t->same(false, $plan139()['selectedPlan']['tableSeekRequired']),
    'planner partial expression skipscan current source next139 tape source current' => static fn (TestRunner $t) => $t->same('current', $plan139()['cursorTape']['source']),
    'planner partial expression skipscan current source next139 tape starts predicate reprepare' => static fn (TestRunner $t) => $t->same('ReprepareIfPartialPredicateStale', $plan139()['cursorTape']['program'][0]['opcode']),
    'planner partial expression skipscan current source next139 tape seekscan' => static fn (TestRunner $t) => $t->same('SeekScan', $plan139()['cursorTape']['program'][1]['opcode']),
    'planner partial expression skipscan current source next139 tape upper opcode inclusive' => static fn (TestRunner $t) => $t->same('IdxGT', $plan139()['cursorTape']['program'][2]['opcode']),
    'planner partial expression skipscan current source next139 tape predicate guard' => static fn (TestRunner $t) => $t->same('IfNotPartialPredicate', $plan139()['cursorTape']['program'][3]['opcode']),
    'planner partial expression skipscan current source next139 tape column reads needed plus key columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'kind', 'autoload', '__expr_lower_option_name_next139'], $plan139()['cursorTape']['program'][4]['columns']),
    'planner partial expression skipscan current source next139 tape next' => static fn (TestRunner $t) => $t->same('Next', $plan139()['cursorTape']['program'][5]['opcode']),
    'planner partial expression skipscan current source next139 tape rows' => static fn (TestRunner $t) => $t->same([1, 2, 4, 6, 7, 9, 8, 10], $plan139()['cursorTape']['rowids']),
    'planner partial expression skipscan current source next139 tape cost' => static fn (TestRunner $t) => $t->same(37, $plan139()['cursorTape']['estimatedCost']),
    'planner partial expression skipscan current source next139 detail names predicate change' => static fn (TestRunner $t) => $t->contains('current-partial-predicate=changed', $plan139()['detail']),
    'planner partial expression skipscan current source next139 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-partial-expression-skipscan-current-source-next139', implode(',', $plan139()['dependencies'])),
    'planner partial expression skipscan current source next139 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan139()['dependency_closure']),
    'planner partial expression skipscan current source next139 non overlap' => static fn (TestRunner $t) => $t->contains('partial index predicate changes', $plan139()['non_overlap']),
    'planner partial expression skipscan current source next139 identical predicate is stable' => static function (TestRunner $t) use ($plan139, $preparedPredicate139): void {
        $t->same(false, $plan139(null, null, $preparedPredicate139, $preparedPredicate139)['partialPredicateChanged']);
    },
    'planner partial expression skipscan current source next139 identical source predicate only changes' => static function (TestRunner $t) use ($source139, $plan139, $preparedPredicate139, $currentPredicate139): void {
        $source = $source139();
        $t->same(true, $plan139($source, $source, $preparedPredicate139, $currentPredicate139)['partialPredicateChangedOnly']);
    },
    'planner partial expression skipscan current source next139 identical source still ready on predicate change' => static function (TestRunner $t) use ($source139, $plan139, $preparedPredicate139, $currentPredicate139): void {
        $source = $source139();
        $t->same('partial-expression-skipscan-current-source-next139-ready', $plan139($source, $source, $preparedPredicate139, $currentPredicate139)['status']);
    },
    'planner partial expression skipscan current source next139 identical source selects prepared view' => static function (TestRunner $t) use ($source139, $plan139, $preparedPredicate139, $currentPredicate139): void {
        $source = $source139();
        $t->same('prepared', $plan139($source, $source, $preparedPredicate139, $currentPredicate139)['selectedSource']);
    },
    'planner partial expression skipscan current source next139 identical source keeps recheck' => static function (TestRunner $t) use ($source139, $plan139, $preparedPredicate139, $currentPredicate139): void {
        $source = $source139();
        $t->same(true, $plan139($source, $source, $preparedPredicate139, $currentPredicate139)['predicateRecheckRequired']);
    },
    'planner partial expression skipscan current source next139 identical predicate needs next stage with fresh source' => static function (TestRunner $t) use ($source139, $plan139, $preparedPredicate139): void {
        $source = $source139();
        $t->same('requires-next-stage', $plan139($source, $source, $preparedPredicate139, $preparedPredicate139)['status']);
    },
    'planner partial expression skipscan current source next139 desc order uses prev' => static function (TestRunner $t) use ($plan139): void {
        $p = $plan139(null, null, null, null, [['expression' => 'autoload', 'direction' => 'DESC'], ['expression' => 'lower(option_name)', 'direction' => 'DESC']]);
        $t->same('Prev', $p['cursorTape']['program'][5]['opcode']);
    },
    'planner partial expression skipscan current source next139 exclusive upper opcode' => static function (TestRunner $t) use ($source139, $currentSource139, $plan139): void {
        $current = $currentSource139(['upperBound' => 'plugin_zeta', 'upperInclusive' => false]);
        $t->same('IdxGE', $plan139($source139(), $current)['cursorTape']['program'][2]['opcode']);
    },
    'planner partial expression skipscan current source next139 missing covering still rechecks predicate' => static function (TestRunner $t) use ($source139, $currentSource139, $plan139): void {
        $current = $currentSource139(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan139($source139(), $current)['predicateRecheckRequired']);
    },
    'planner partial expression skipscan current source next139 missing covering needs table' => static function (TestRunner $t) use ($source139, $currentSource139, $plan139): void {
        $current = $currentSource139(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan139($source139(), $current)['selectedPlan']['tableSeekRequired']);
    },
    'planner partial expression skipscan current source next139 validates current name' => static function (TestRunner $t) use ($source139, $currentSource139, $plan139): void {
        $bad = $currentSource139(['name' => '']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan139($source139(), $bad));
    },
    'planner partial expression skipscan current source next139 validates rowid' => static function (TestRunner $t) use ($source139, $currentSource139, $plan139): void {
        $badRows = $currentSource139()['rows'];
        $badRows[] = ['rowid' => -1, 'autoload' => 'no', 'option_name' => null, 'kind' => 'plugin'];
        $bad = $currentSource139(['rows' => $badRows]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan139($source139(), $bad));
    },
    'planner partial expression skipscan current source next139 validates stat4 counters' => static function (TestRunner $t) use ($source139, $currentSource139, $plan139): void {
        $bad = $currentSource139(['stat4Samples' => [['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => -1, 'nLt' => 0, 'nDLt' => 0]]]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan139($source139(), $bad));
    },
];

return $tests;
