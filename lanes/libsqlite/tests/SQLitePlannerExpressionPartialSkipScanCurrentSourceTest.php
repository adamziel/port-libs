<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$rows141 = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'Plugin_Beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'admin_email', 'option_value' => 'owner@example.test', 'kind' => 'core'],
    ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'plugin_delta', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'plugin_epsilon', 'option_value' => 'a:4', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => '_transient_plugin_alpha', 'option_value' => 'tmp', 'kind' => 'transient'],
    ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'option_value' => 'a:5', 'kind' => 'plugin'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'kind' => 'plugin'],
    ['rowid' => 9, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'a:6', 'kind' => 'plugin'],
];

$currentRows141 = static function () use ($rows141): array {
    $rows = $rows141();
    $rows[] = ['rowid' => 10, 'autoload' => 'no', 'option_name' => 'PLUGIN_THETA', 'option_value' => 'a:7', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 11, 'autoload' => 'auto', 'option_name' => 'plugin_zeta', 'option_value' => 'a:8', 'kind' => 'plugin'];

    return $rows;
};

$nextRows141 = static function () use ($currentRows141): array {
    $rows = $currentRows141();
    $rows[] = ['rowid' => 12, 'autoload' => 'yes', 'option_name' => 'plugin_omega', 'option_value' => 'a:9', 'kind' => 'plugin'];

    return $rows;
};

$stat4141 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
];

$currentStat4141 = static function () use ($stat4141): array {
    $samples = $stat4141();
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 3, 'nDLt' => 3];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

    return $samples;
};

$source141 = static function (array $overrides = []) use ($rows141, $stat4141): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1410',
        'schemaCookie' => 1410,
        'stat4Generation' => 41,
        'indexName' => 'idx_wp_options_autoload_lower_name_partial_current-source',
        'rootPage' => 14101,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rows141(),
        'stat4Samples' => $stat4141(),
    ];
};

$currentSource141 = static function (array $overrides = []) use ($currentRows141, $currentStat4141): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1411',
        'schemaCookie' => 1411,
        'stat4Generation' => 42,
        'indexName' => 'idx_wp_options_autoload_lower_name_partial_current-source',
        'rootPage' => 14111,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRows141(),
        'stat4Samples' => $currentStat4141(),
    ];
};

$nextSource141 = static function (array $overrides = []) use ($nextRows141, $currentStat4141): array {
    $samples = $currentStat4141();
    $samples[] = ['prefix' => 'yes', 'suffix' => 'plugin_omega', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

    return $overrides + [
        'name' => 'next-main.wp_options@cookie1412',
        'schemaCookie' => 1412,
        'stat4Generation' => 43,
        'indexName' => 'idx_wp_options_autoload_lower_name_partial_current-source',
        'rootPage' => 14121,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $nextRows141(),
        'stat4Samples' => $samples,
    ];
};

$partial141 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('__expr_lower_option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$point141 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$isNotNull141 = static fn (string $column): array => ['operator' => 'IS NOT NULL', 'left' => ['column' => $column]];
$range141 = static fn (mixed $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$query141 = [
    $point141('kind', 'plugin'),
    $isNotNull141('__expr_lower_option_name'),
    $range141(['expression' => 'lower(option_name)'], '>=', 'plugin_'),
];
$order141 = [['expression' => 'kind'], ['expression' => 'lower(option_name)']];
$needed141 = ['option_name', 'option_value'];

$plan141 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $next = null,
    ?array $query = null,
    ?array $order = null,
    ?array $needed = null,
    ?SQLiteIndexPredicate $partial = null,
): array => SQLiteSkipScanStat4PartialOrderPlan::expressionPartialSkipScan(
    $prepared ?? $source141(),
    $current ?? $currentSource141(),
    $partial ?? $partial141,
    $query ?? $query141,
    $order ?? $order141,
    $needed ?? $needed141,
    $next,
);

$tests = [
    'planner expression partial skipscan current source current-source status ready without next source' => static fn (TestRunner $t) => $t->same('expression-partial-skipscan-current-source-ready', $plan141()['status']),
    'planner expression partial skipscan current source current-source selects current' => static fn (TestRunner $t) => $t->same('current', $plan141()['selectedSource']),
    'planner expression partial skipscan current source current-source marks stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan141()['stalePreparedStatement']),
    'planner expression partial skipscan current source current-source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan141()['reprepareRequired']),
    'planner expression partial skipscan current source current-source schema changed' => static fn (TestRunner $t) => $t->same(true, $plan141()['schemaCookieChanged']),
    'planner expression partial skipscan current source current-source stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan141()['stat4GenerationChanged']),
    'planner expression partial skipscan current source current-source selected rowids' => static fn (TestRunner $t) => $t->same([1, 2, 11, 4, 5, 7, 10, 9], $plan141()['selectedPlan']['rowids']),
    'planner expression partial skipscan current source current-source prepared rowids' => static fn (TestRunner $t) => $t->same([1, 2, 4, 5, 7, 9], $plan141()['preparedExpression']['rowids']),
    'planner expression partial skipscan current source current-source current rowids' => static fn (TestRunner $t) => $t->same([1, 2, 11, 4, 5, 7, 10, 9], $plan141()['currentExpression']['rowids']),
    'planner expression partial skipscan current source current-source expression flag' => static fn (TestRunner $t) => $t->same(true, $plan141()['selectedPlan']['expressionPartialSkipScan']),
    'planner expression partial skipscan current source current-source expression skipscan flag' => static fn (TestRunner $t) => $t->same(true, $plan141()['selectedPlan']['expressionSkipScan']),
    'planner expression partial skipscan current source current-source range expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan141()['selectedPlan']['rangeExpression']),
    'planner expression partial skipscan current source current-source range column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan141()['selectedPlan']['rangeExpressionColumn']),
    'planner expression partial skipscan current source current-source materialized range column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan141()['selectedPlan']['rangeColumn']),
    'planner expression partial skipscan current source current-source uses skip scan' => static fn (TestRunner $t) => $t->same(true, $plan141()['selectedPlan']['usesSkipScan']),
    'planner expression partial skipscan current source current-source loop prefixes' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($plan141()['selectedPlan']['loops'], 'prefix')),
    'planner expression partial skipscan current source current-source loop matched counts' => static fn (TestRunner $t) => $t->same([3, 2, 2, 1], array_column($plan141()['selectedPlan']['loops'], 'matched')),
    'planner expression partial skipscan current source current-source skips partial rows' => static fn (TestRunner $t) => $t->same(3, $plan141()['selectedPlan']['skippedPartialRows']),
    'planner expression partial skipscan current source current-source omits no null expression rows after partial' => static fn (TestRunner $t) => $t->same(0, $plan141()['selectedPlan']['omittedNullRangeRows']),
    'planner expression partial skipscan current source current-source estimates rows' => static fn (TestRunner $t) => $t->same(4, $plan141()['selectedPlan']['estimatedRows']),
    'planner expression partial skipscan current source current-source estimates cost' => static fn (TestRunner $t) => $t->same(40, $plan141()['selectedPlan']['estimatedCost']),
    'planner expression partial skipscan current source current-source samples used' => static fn (TestRunner $t) => $t->same(8, $plan141()['selectedPlan']['stat4SamplesUsed']),
    'planner expression partial skipscan current source current-source covered rows' => static fn (TestRunner $t) => $t->same(8, $plan141()['selectedPlan']['coveredRowCount']),
    'planner expression partial skipscan current source current-source covering true' => static fn (TestRunner $t) => $t->same(true, $plan141()['selectedPlan']['covering']),
    'planner expression partial skipscan current source current-source no table seek' => static fn (TestRunner $t) => $t->same(false, $plan141()['selectedPlan']['tableSeekRequired']),
    'planner expression partial skipscan current source current-source block sort by skipped prefix' => static fn (TestRunner $t) => $t->same(['autoload'], $plan141()['selectedPlan']['sortBreakColumns']),
    'planner expression partial skipscan current source current-source order mode' => static fn (TestRunner $t) => $t->same('partial-current-next', $plan141()['selectedPlan']['orderByMode']),
    'planner expression partial skipscan current source current-source cursor reads expression column' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', '__expr_lower_option_name'], $plan141()['selectedPlan']['cursorProgram'][3]['columns']),
    'planner expression partial skipscan current source current-source first current row' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan141()['selectedPlan']['currentNextCoveringRows'][0]['current']['covering']['option_name']),
    'planner expression partial skipscan current source current-source first next row preserves case' => static fn (TestRunner $t) => $t->same('Plugin_Beta', $plan141()['selectedPlan']['currentNextCoveringRows'][0]['next']['covering']['option_name']),
    'planner expression partial skipscan current source current-source last next null' => static fn (TestRunner $t) => $t->same(null, $plan141()['selectedPlan']['currentNextCoveringRows'][7]['next']),
    'planner expression partial skipscan current source current-source selected source signature present' => static fn (TestRunner $t) => $t->same(64, strlen($plan141()['selectedPlan']['sourceSignature'])),
    'planner expression partial skipscan current source current-source fence signature present' => static fn (TestRunner $t) => $t->same(64, strlen($plan141()['currentSourceFence']['selectedSourceSignature'])),
    'planner expression partial skipscan current source current-source predicate signature present' => static fn (TestRunner $t) => $t->same(64, strlen($plan141()['currentSourceFence']['partialPredicateSignature'])),
    'planner expression partial skipscan current source current-source predicate operator' => static fn (TestRunner $t) => $t->same('AND', $plan141()['partialPredicateFence']['operator']),
    'planner expression partial skipscan current source current-source predicate child columns' => static fn (TestRunner $t) => $t->same(['kind', '__expr_lower_option_name'], array_column($plan141()['partialPredicateFence']['children'], 'column')),
    'planner expression partial skipscan current source current-source no next source admitted flag' => static fn (TestRunner $t) => $t->same(true, $plan141()['nextSourceAdmitted']),
    'planner expression partial skipscan current source current-source no next source summary' => static fn (TestRunner $t) => $t->same(null, $plan141()['nextSource']),
    'planner expression partial skipscan current source current-source dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-expression-partial-skipscan-current-source'], $plan141()['dependencies']),
    'planner expression partial skipscan current source current-source dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan141()['dependency_closure']),
    'planner expression partial skipscan current source current-source non overlap' => static fn (TestRunner $t) => $t->contains('current-source partial-predicate source fencing', $plan141()['non_overlap']),
    'planner expression partial skipscan current source current-source detail' => static fn (TestRunner $t) => $t->contains('CURRENT-SOURCE PARTIAL FENCE current-source', $plan141()['detail']),
];

$samePlan = static fn (): array => $plan141($source141(), $source141());
$tests['planner expression partial skipscan current source current-source reuses prepared when identical'] = static fn (TestRunner $t) => $t->same('prepared', $samePlan()['selectedSource']);
$tests['planner expression partial skipscan current source current-source identical source ready'] = static fn (TestRunner $t) => $t->same('expression-partial-skipscan-current-source-ready', $samePlan()['status']);
$tests['planner expression partial skipscan current source current-source identical source not stale'] = static fn (TestRunner $t) => $t->same(false, $samePlan()['stalePreparedStatement']);
$tests['planner expression partial skipscan current source current-source identical rowids'] = static fn (TestRunner $t) => $t->same([1, 2, 4, 5, 7, 9], $samePlan()['selectedPlan']['rowids']);

$staleNext = static fn (): array => $plan141(null, null, $nextSource141());
$tests['planner expression partial skipscan current source current-source stale next requires reprepare'] = static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $staleNext()['status']);
$tests['planner expression partial skipscan current source current-source stale next not admitted'] = static fn (TestRunner $t) => $t->same(false, $staleNext()['nextSourceAdmitted']);
$tests['planner expression partial skipscan current source current-source stale next summary name'] = static fn (TestRunner $t) => $t->same('next-main.wp_options@cookie1412', $staleNext()['nextSource']['name']);
$tests['planner expression partial skipscan current source current-source stale next cookie'] = static fn (TestRunner $t) => $t->same(1412, $staleNext()['nextSource']['schemaCookie']);
$tests['planner expression partial skipscan current source current-source stale next stat4'] = static fn (TestRunner $t) => $t->same(43, $staleNext()['nextSource']['stat4Generation']);
$tests['planner expression partial skipscan current source current-source stale next reasons'] = static fn (TestRunner $t) => $t->same(['schema-cookie', 'stat4-generation', 'row-signature', 'stat4-signature'], $staleNext()['nextSource']['replanReasons']);
$tests['planner expression partial skipscan current source current-source stale next signature present'] = static fn (TestRunner $t) => $t->same(64, strlen($staleNext()['nextSource']['sourceSignature']));
$tests['planner expression partial skipscan current source current-source stale next keeps selected rowids'] = static fn (TestRunner $t) => $t->same([1, 2, 11, 4, 5, 7, 10, 9], $staleNext()['selectedPlan']['rowids']);

$freshNext = static fn (): array => $plan141(null, null, $currentSource141());
$tests['planner expression partial skipscan current source current-source matching next admitted'] = static fn (TestRunner $t) => $t->same(true, $freshNext()['nextSourceAdmitted']);
$tests['planner expression partial skipscan current source current-source matching next ready'] = static fn (TestRunner $t) => $t->same('expression-partial-skipscan-current-source-ready', $freshNext()['status']);
$tests['planner expression partial skipscan current source current-source matching next has no reasons'] = static fn (TestRunner $t) => $t->same([], $freshNext()['nextSource']['replanReasons']);

$missingPartial = static fn (): array => $plan141(null, null, null, [$range141(['expression' => 'lower(option_name)'], '>=', 'plugin_')]);
$tests['planner expression partial skipscan current source current-source missing partial unusable'] = static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $missingPartial()['status']);
$tests['planner expression partial skipscan current source current-source missing partial no selected plan'] = static fn (TestRunner $t) => $t->same(null, $missingPartial()['selectedPlan']);

$missingCover = static function () use ($currentSource141, $plan141): array {
    $current = $currentSource141(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
    return $plan141(null, $current);
};
$tests['planner expression partial skipscan current source current-source missing payload needs table seek'] = static fn (TestRunner $t) => $t->same(true, $missingCover()['selectedPlan']['tableSeekRequired']);
$tests['planner expression partial skipscan current source current-source missing payload rejected column'] = static fn (TestRunner $t) => $t->same(['option_value'], $missingCover()['selectedPlan']['coveringRejectedColumns']);

$exclusive = static function () use ($currentSource141, $plan141): array {
    return $plan141(null, $currentSource141(['upperBound' => 'plugin_theta', 'upperInclusive' => false]));
};
$tests['planner expression partial skipscan current source current-source exclusive upper removes theta'] = static fn (TestRunner $t) => $t->same([1, 2, 4, 5, 7, 9], $exclusive()['selectedPlan']['rowids']);

$tests['planner expression partial skipscan current source current-source validates current rows'] = static function (TestRunner $t) use ($currentSource141, $plan141): void {
    $bad = $currentSource141();
    $bad['rows'][] = 'bad-row';
    $t->throws(InvalidArgumentException::class, static fn () => $plan141(null, $bad));
};
$tests['planner expression partial skipscan current source current-source validates next rows'] = static function (TestRunner $t) use ($nextSource141, $plan141): void {
    $bad = $nextSource141();
    $bad['rows'][] = 'bad-row';
    $t->throws(InvalidArgumentException::class, static fn () => $plan141(null, null, $bad));
};
$tests['planner expression partial skipscan current source current-source validates stat4 counters'] = static function (TestRunner $t) use ($currentSource141, $plan141): void {
    $bad = $currentSource141();
    $bad['stat4Samples'][0]['nEq'] = -1;
    $t->throws(InvalidArgumentException::class, static fn () => $plan141(null, $bad));
};
$tests['planner expression partial skipscan current source current-source validates range expression'] = static function (TestRunner $t) use ($currentSource141, $plan141): void {
    $bad = $currentSource141(['rangeExpression' => '']);
    $t->throws(InvalidArgumentException::class, static fn () => $plan141(null, $bad));
};
$tests['planner expression partial skipscan current source current-source validates order expression'] = static function (TestRunner $t) use ($plan141): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan141(null, null, null, null, [['expression' => '']]));
};

return $tests;
