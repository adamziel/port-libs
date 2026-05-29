<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerStat4PartialSkipScanCurrentSourceNextPlan;

$rows = static fn (): array => [
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

$currentRows = static function () use ($rows): array {
    $rows = $rows();
    $rows[] = ['rowid' => 10, 'autoload' => 'no', 'option_name' => 'PLUGIN_THETA', 'option_value' => 'a:7', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 11, 'autoload' => 'auto', 'option_name' => 'plugin_zeta', 'option_value' => 'a:8', 'kind' => 'plugin'];

    return $rows;
};

$stat4 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
];

$currentStat4 = static function () use ($stat4): array {
    $samples = $stat4();
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 3, 'nDLt' => 3];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

    return $samples;
};

$source = static function (array $overrides = []) use ($rows, $stat4): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1450',
        'schemaCookie' => 1450,
        'stat4Generation' => 45,
        'indexName' => 'idx_wp_options_autoload_lower_name_partial_current_source',
        'rootPage' => 14501,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rows(),
        'stat4Samples' => $stat4(),
    ];
};

$currentSource = static function (array $overrides = []) use ($currentRows, $currentStat4): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1451',
        'schemaCookie' => 1451,
        'stat4Generation' => 46,
        'indexName' => 'idx_wp_options_autoload_lower_name_partial_current_source',
        'rootPage' => 14511,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'rangeExpression' => 'lower(option_name)',
        'rangeExpressionColumn' => '__expr_lower_option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'BINARY',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRows(),
        'stat4Samples' => $currentStat4(),
    ];
};

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('__expr_lower_option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$isNotNull = static fn (string $column): array => ['operator' => 'IS NOT NULL', 'left' => ['column' => $column]];
$range = static fn (mixed $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$query = [
    $point('kind', 'plugin'),
    $isNotNull('__expr_lower_option_name'),
    $range(['expression' => 'lower(option_name)'], '>=', 'plugin_'),
];
$order = [['expression' => 'kind'], ['expression' => 'lower(option_name)']];
$needed = ['option_name', 'option_value'];

$plan = static fn (
    ?array $preparedOverride = null,
    ?array $currentOverride = null,
    ?array $queryOverride = null,
    ?array $orderOverride = null,
    ?array $neededOverride = null,
    ?array $nextOverride = null,
): array => SQLitePlannerStat4PartialSkipScanCurrentSourceNextPlan::materialize(
    $preparedOverride ?? $source(),
    $currentOverride ?? $currentSource(),
    $partial,
    $queryOverride ?? $query,
    $orderOverride ?? $order,
    $neededOverride ?? $needed,
    $nextOverride,
);

$tests = [
    'planner stat4 partial skipscan current source current-source status ready' => static fn (TestRunner $t) => $t->same('stat4-partial-skipscan-current-source-ready', $plan()['status']),
    'planner stat4 partial skipscan current source current-source selects current' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
    'planner stat4 partial skipscan current source current-source stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
    'planner stat4 partial skipscan current source current-source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
    'planner stat4 partial skipscan current source current-source schema changed' => static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']),
    'planner stat4 partial skipscan current source current-source stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']),
    'planner stat4 partial skipscan current source current-source selected rowids' => static fn (TestRunner $t) => $t->same([1, 2, 11, 4, 5, 7, 10, 9], $plan()['selectedPlan']['rowids']),
    'planner stat4 partial skipscan current source current-source selected payload count' => static fn (TestRunner $t) => $t->same(8, $plan()['selectedPlan']['payloadRowCount']),
    'planner stat4 partial skipscan current source current-source selected pair count' => static fn (TestRunner $t) => $t->same(4, $plan()['selectedPlan']['stat4PairCount']),
    'planner stat4 partial skipscan current source current-source loop count' => static fn (TestRunner $t) => $t->same(4, $plan()['selectedPlan']['skipScanLoopCount']),
    'planner stat4 partial skipscan current source current-source loop prefixes' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($plan()['prefixProgram'], 'prefix')),
    'planner stat4 partial skipscan current source current-source loop matched' => static fn (TestRunner $t) => $t->same([3, 2, 2, 1], array_column($plan()['prefixProgram'], 'matched')),
    'planner stat4 partial skipscan current source current-source first loop rowids' => static fn (TestRunner $t) => $t->same([1, 2, 11], $plan()['prefixProgram'][0]['rowids']),
    'planner stat4 partial skipscan current source current-source no loop rowids' => static fn (TestRunner $t) => $t->same([7, 10], $plan()['prefixProgram'][2]['rowids']),
    'planner stat4 partial skipscan current source current-source first opcode seek prefix' => static fn (TestRunner $t) => $t->same('SeekPrefix', $plan()['prefixProgram'][0]['opcodes'][0]['opcode']),
    'planner stat4 partial skipscan current source current-source first opcode prefix column' => static fn (TestRunner $t) => $t->same('autoload', $plan()['prefixProgram'][0]['opcodes'][0]['column']),
    'planner stat4 partial skipscan current source current-source second opcode range column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan()['prefixProgram'][0]['opcodes'][1]['column']),
    'planner stat4 partial skipscan current source current-source second opcode lower value' => static fn (TestRunner $t) => $t->same('plugin_', $plan()['prefixProgram'][0]['opcodes'][1]['value']),
    'planner stat4 partial skipscan current source current-source upper opcode inclusive' => static fn (TestRunner $t) => $t->same('IdxGT', $plan()['prefixProgram'][0]['opcodes'][2]['opcode']),
    'planner stat4 partial skipscan current source current-source upper opcode value' => static fn (TestRunner $t) => $t->same('plugin_zzzz', $plan()['prefixProgram'][0]['opcodes'][2]['value']),
    'planner stat4 partial skipscan current source current-source column opcode reads covering columns' => static fn (TestRunner $t) => $t->same(['autoload', 'option_name', 'option_value', 'kind'], $plan()['prefixProgram'][0]['opcodes'][3]['columns']),
    'planner stat4 partial skipscan current source current-source last opcode advances prefix' => static fn (TestRunner $t) => $t->same('NextPrefix', $plan()['prefixProgram'][0]['opcodes'][4]['opcode']),
    'planner stat4 partial skipscan current source current-source payload first rowid' => static fn (TestRunner $t) => $t->same(1, $plan()['payloadRows'][0]['rowid']),
    'planner stat4 partial skipscan current source current-source payload first name' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['payloadRows'][0]['payload']['option_name']),
    'planner stat4 partial skipscan current source current-source payload first value' => static fn (TestRunner $t) => $t->same('a:1', $plan()['payloadRows'][0]['payload']['option_value']),
    'planner stat4 partial skipscan current source current-source payload first next rowid' => static fn (TestRunner $t) => $t->same(2, $plan()['payloadRows'][0]['nextRowid']),
    'planner stat4 partial skipscan current source current-source payload upper-case preserved' => static fn (TestRunner $t) => $t->same('PLUGIN_THETA', $plan()['payloadRows'][6]['payload']['option_name']),
    'planner stat4 partial skipscan current source current-source payload last next null' => static fn (TestRunner $t) => $t->same(null, $plan()['payloadRows'][7]['nextRowid']),
    'planner stat4 partial skipscan current source current-source stat4 pair prefixes' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($plan()['stat4CurrentSourceNextPairs'], 'prefix')),
    'planner stat4 partial skipscan current source current-source stat4 current suffixes' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_delta', 'plugin_gamma', 'plugin_alpha'], array_column($plan()['stat4CurrentSourceNextPairs'], 'currentSuffix')),
    'planner stat4 partial skipscan current source current-source stat4 next suffixes' => static fn (TestRunner $t) => $t->same(['plugin_beta', 'plugin_epsilon', 'plugin_theta', null], array_column($plan()['stat4CurrentSourceNextPairs'], 'nextSuffix')),
    'planner stat4 partial skipscan current source current-source stat4 range samples' => static fn (TestRunner $t) => $t->same([3, 2, 2, 1], array_column($plan()['stat4CurrentSourceNextPairs'], 'rangeSamples')),
    'planner stat4 partial skipscan current source current-source estimates rows' => static fn (TestRunner $t) => $t->same(4, $plan()['selectedPlan']['estimatedRows']),
    'planner stat4 partial skipscan current source current-source estimates cost' => static fn (TestRunner $t) => $t->same(40, $plan()['selectedPlan']['estimatedCost']),
    'planner stat4 partial skipscan current source current-source keeps block sort evidence' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['blockSortRequired']),
    'planner stat4 partial skipscan current source current-source sort break prefix' => static fn (TestRunner $t) => $t->same(['autoload'], $plan()['selectedPlan']['sortBreakColumns']),
    'planner stat4 partial skipscan current source current-source covering true' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['covering']),
    'planner stat4 partial skipscan current source current-source no table seek' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['tableSeekRequired']),
    'planner stat4 partial skipscan current source current-source expression flag' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['expressionPartialSkipScan']),
    'planner stat4 partial skipscan current source current-source next source admitted by default' => static fn (TestRunner $t) => $t->same(true, $plan()['nextSourceAdmitted']),
    'planner stat4 partial skipscan current source current-source dependencies' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-partial-skipscan-current-source'], $plan()['dependencies']),
    'planner stat4 partial skipscan current source current-source dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan()['dependency_closure']),
    'planner stat4 partial skipscan current source current-source non overlap' => static fn (TestRunner $t) => $t->contains('per-prefix STAT4 skip-scan cursor programs', $plan()['non_overlap']),
    'planner stat4 partial skipscan current source current-source detail' => static fn (TestRunner $t) => $t->contains('STAT4 CURRENT-SOURCE PREFIX PROGRAM current-source', $plan()['detail']),
    'planner stat4 partial skipscan current source current-source selected detail' => static fn (TestRunner $t) => $t->contains('CURRENT-SOURCE STAT4 PREFIX PROGRAM current-source', $plan()['selectedPlan']['detail']),
    'planner stat4 partial skipscan current source current-source loop signature present' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['currentSourceFence']['loopProgramSignature'])),
    'planner stat4 partial skipscan current source current-source payload signature present' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['currentSourceFence']['payloadSignature'])),
    'planner stat4 partial skipscan current source current-source stat4 pair signature present' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['currentSourceFence']['stat4PairSignature'])),
    'planner stat4 partial skipscan current source current-source predicate fence operator' => static fn (TestRunner $t) => $t->same('AND', $plan()['currentSourceFence']['partialPredicateFence']['operator']),
];

$same = static fn (): array => $plan($source(), $source());
$tests['planner stat4 partial skipscan current source current-source reuses identical prepared'] = static fn (TestRunner $t) => $t->same('prepared', $same()['selectedSource']);
$tests['planner stat4 partial skipscan current source current-source identical rowids'] = static fn (TestRunner $t) => $t->same([1, 2, 4, 5, 7, 9], $same()['selectedPlan']['rowids']);
$tests['planner stat4 partial skipscan current source current-source identical loop count'] = static fn (TestRunner $t) => $t->same(4, $same()['selectedPlan']['skipScanLoopCount']);

$exclusive = static fn (): array => $plan(null, $currentSource(['upperBound' => 'plugin_theta', 'upperInclusive' => false]));
$tests['planner stat4 partial skipscan current source current-source exclusive upper opcode'] = static fn (TestRunner $t) => $t->same('IdxGE', $exclusive()['prefixProgram'][0]['opcodes'][2]['opcode']);
$tests['planner stat4 partial skipscan current source current-source exclusive upper removes theta and zeta'] = static fn (TestRunner $t) => $t->same([1, 2, 4, 5, 7, 9], $exclusive()['selectedPlan']['rowids']);
$tests['planner stat4 partial skipscan current source current-source exclusive payload removes theta and zeta'] = static fn (TestRunner $t) => $t->same(['plugin_alpha', 'Plugin_Beta', 'plugin_delta', 'plugin_epsilon', 'plugin_gamma', 'plugin_alpha'], array_column(array_column($exclusive()['payloadRows'], 'payload'), 'option_name'));

$missingPartial = static fn (): array => $plan(null, null, [$range(['expression' => 'lower(option_name)'], '>=', 'plugin_')]);
$tests['planner stat4 partial skipscan current source current-source missing partial requires reprepare'] = static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $missingPartial()['status']);
$tests['planner stat4 partial skipscan current source current-source missing partial has empty program'] = static fn (TestRunner $t) => $t->same([], $missingPartial()['prefixProgram']);
$tests['planner stat4 partial skipscan current source current-source missing partial has null selected plan'] = static fn (TestRunner $t) => $t->same(null, $missingPartial()['selectedPlan']);

$staleCurrentSource = static function () use ($currentSource, $plan): array {
    $next = $currentSource(['schemaCookie' => 1452, 'stat4Generation' => 47]);
    $next['rows'][] = ['rowid' => 12, 'autoload' => 'yes', 'option_name' => 'plugin_omega', 'option_value' => 'a:9', 'kind' => 'plugin'];

    return $plan(null, null, null, null, null, $next);
};
$tests['planner stat4 partial skipscan current source current-source stale next rejected'] = static fn (TestRunner $t) => $t->same(false, $staleCurrentSource()['nextSourceAdmitted']);
$tests['planner stat4 partial skipscan current source current-source stale next status'] = static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $staleCurrentSource()['status']);
$tests['planner stat4 partial skipscan current source current-source stale next keeps current program'] = static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($staleCurrentSource()['prefixProgram'], 'prefix'));

$tests['planner stat4 partial skipscan current source current-source validates bad rows'] = static function (TestRunner $t) use ($currentSource, $plan): void {
    $bad = $currentSource();
    $bad['rows'][] = 'bad';
    $t->throws(InvalidArgumentException::class, static fn () => $plan(null, $bad));
};
$tests['planner stat4 partial skipscan current source current-source validates bad covering columns'] = static function (TestRunner $t) use ($currentSource, $plan): void {
    $bad = $currentSource(['coveringColumns' => ['autoload', '']]);
    $t->throws(InvalidArgumentException::class, static fn () => $plan(null, $bad));
};
$tests['planner stat4 partial skipscan current source current-source validates bad needed column'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan(null, null, null, null, ['option_name', '']));
};
$tests['planner stat4 partial skipscan current source current-source validates bad upper inclusive'] = static function (TestRunner $t) use ($currentSource, $plan): void {
    $bad = $currentSource(['upperInclusive' => 'yes']);
    $t->throws(InvalidArgumentException::class, static fn () => $plan(null, $bad));
};

return $tests;
