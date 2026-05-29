<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerStat4PartialSkipScanCurrentSourceNextPlan;

$rows145 = static fn (): array => [
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

$currentRows145 = static function () use ($rows145): array {
    $rows = $rows145();
    $rows[] = ['rowid' => 10, 'autoload' => 'no', 'option_name' => 'PLUGIN_THETA', 'option_value' => 'a:7', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 11, 'autoload' => 'auto', 'option_name' => 'plugin_zeta', 'option_value' => 'a:8', 'kind' => 'plugin'];

    return $rows;
};

$stat4145 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
];

$currentStat4145 = static function () use ($stat4145): array {
    $samples = $stat4145();
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 3, 'nDLt' => 3];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];

    return $samples;
};

$source145 = static function (array $overrides = []) use ($rows145, $stat4145): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1450',
        'schemaCookie' => 1450,
        'stat4Generation' => 45,
        'indexName' => 'idx_wp_options_autoload_lower_name_partial_next145',
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
        'rows' => $rows145(),
        'stat4Samples' => $stat4145(),
    ];
};

$currentSource145 = static function (array $overrides = []) use ($currentRows145, $currentStat4145): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1451',
        'schemaCookie' => 1451,
        'stat4Generation' => 46,
        'indexName' => 'idx_wp_options_autoload_lower_name_partial_next145',
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
        'rows' => $currentRows145(),
        'stat4Samples' => $currentStat4145(),
    ];
};

$partial145 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('__expr_lower_option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$point145 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$isNotNull145 = static fn (string $column): array => ['operator' => 'IS NOT NULL', 'left' => ['column' => $column]];
$range145 = static fn (mixed $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$query145 = [
    $point145('kind', 'plugin'),
    $isNotNull145('__expr_lower_option_name'),
    $range145(['expression' => 'lower(option_name)'], '>=', 'plugin_'),
];
$order145 = [['expression' => 'kind'], ['expression' => 'lower(option_name)']];
$needed145 = ['option_name', 'option_value'];

$plan145 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $query = null,
    ?array $order = null,
    ?array $needed = null,
    ?array $next = null,
): array => SQLitePlannerStat4PartialSkipScanCurrentSourceNextPlan::materializeNext145(
    $prepared ?? $source145(),
    $current ?? $currentSource145(),
    $partial145,
    $query ?? $query145,
    $order ?? $order145,
    $needed ?? $needed145,
    $next,
);

$tests = [
    'planner stat4 partial skipscan current source next145 status ready' => static fn (TestRunner $t) => $t->same('stat4-partial-skipscan-current-source-next145-ready', $plan145()['status']),
    'planner stat4 partial skipscan current source next145 selects current' => static fn (TestRunner $t) => $t->same('current', $plan145()['selectedSource']),
    'planner stat4 partial skipscan current source next145 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan145()['stalePreparedStatement']),
    'planner stat4 partial skipscan current source next145 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan145()['reprepareRequired']),
    'planner stat4 partial skipscan current source next145 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan145()['schemaCookieChanged']),
    'planner stat4 partial skipscan current source next145 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan145()['stat4GenerationChanged']),
    'planner stat4 partial skipscan current source next145 selected rowids' => static fn (TestRunner $t) => $t->same([1, 2, 11, 4, 5, 7, 10, 9], $plan145()['selectedPlan']['rowids']),
    'planner stat4 partial skipscan current source next145 selected payload count' => static fn (TestRunner $t) => $t->same(8, $plan145()['selectedPlan']['payloadRowCount']),
    'planner stat4 partial skipscan current source next145 selected pair count' => static fn (TestRunner $t) => $t->same(4, $plan145()['selectedPlan']['stat4PairCount']),
    'planner stat4 partial skipscan current source next145 loop count' => static fn (TestRunner $t) => $t->same(4, $plan145()['selectedPlan']['skipScanLoopCount']),
    'planner stat4 partial skipscan current source next145 loop prefixes' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($plan145()['prefixProgram'], 'prefix')),
    'planner stat4 partial skipscan current source next145 loop matched' => static fn (TestRunner $t) => $t->same([3, 2, 2, 1], array_column($plan145()['prefixProgram'], 'matched')),
    'planner stat4 partial skipscan current source next145 first loop rowids' => static fn (TestRunner $t) => $t->same([1, 2, 11], $plan145()['prefixProgram'][0]['rowids']),
    'planner stat4 partial skipscan current source next145 no loop rowids' => static fn (TestRunner $t) => $t->same([7, 10], $plan145()['prefixProgram'][2]['rowids']),
    'planner stat4 partial skipscan current source next145 first opcode seek prefix' => static fn (TestRunner $t) => $t->same('SeekPrefix', $plan145()['prefixProgram'][0]['opcodes'][0]['opcode']),
    'planner stat4 partial skipscan current source next145 first opcode prefix column' => static fn (TestRunner $t) => $t->same('autoload', $plan145()['prefixProgram'][0]['opcodes'][0]['column']),
    'planner stat4 partial skipscan current source next145 second opcode range column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan145()['prefixProgram'][0]['opcodes'][1]['column']),
    'planner stat4 partial skipscan current source next145 second opcode lower value' => static fn (TestRunner $t) => $t->same('plugin_', $plan145()['prefixProgram'][0]['opcodes'][1]['value']),
    'planner stat4 partial skipscan current source next145 upper opcode inclusive' => static fn (TestRunner $t) => $t->same('IdxGT', $plan145()['prefixProgram'][0]['opcodes'][2]['opcode']),
    'planner stat4 partial skipscan current source next145 upper opcode value' => static fn (TestRunner $t) => $t->same('plugin_zzzz', $plan145()['prefixProgram'][0]['opcodes'][2]['value']),
    'planner stat4 partial skipscan current source next145 column opcode reads covering columns' => static fn (TestRunner $t) => $t->same(['autoload', 'option_name', 'option_value', 'kind'], $plan145()['prefixProgram'][0]['opcodes'][3]['columns']),
    'planner stat4 partial skipscan current source next145 last opcode advances prefix' => static fn (TestRunner $t) => $t->same('NextPrefix', $plan145()['prefixProgram'][0]['opcodes'][4]['opcode']),
    'planner stat4 partial skipscan current source next145 payload first rowid' => static fn (TestRunner $t) => $t->same(1, $plan145()['payloadRows'][0]['rowid']),
    'planner stat4 partial skipscan current source next145 payload first name' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan145()['payloadRows'][0]['payload']['option_name']),
    'planner stat4 partial skipscan current source next145 payload first value' => static fn (TestRunner $t) => $t->same('a:1', $plan145()['payloadRows'][0]['payload']['option_value']),
    'planner stat4 partial skipscan current source next145 payload first next rowid' => static fn (TestRunner $t) => $t->same(2, $plan145()['payloadRows'][0]['nextRowid']),
    'planner stat4 partial skipscan current source next145 payload upper-case preserved' => static fn (TestRunner $t) => $t->same('PLUGIN_THETA', $plan145()['payloadRows'][6]['payload']['option_name']),
    'planner stat4 partial skipscan current source next145 payload last next null' => static fn (TestRunner $t) => $t->same(null, $plan145()['payloadRows'][7]['nextRowid']),
    'planner stat4 partial skipscan current source next145 stat4 pair prefixes' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($plan145()['stat4CurrentSourceNextPairs'], 'prefix')),
    'planner stat4 partial skipscan current source next145 stat4 current suffixes' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_delta', 'plugin_gamma', 'plugin_alpha'], array_column($plan145()['stat4CurrentSourceNextPairs'], 'currentSuffix')),
    'planner stat4 partial skipscan current source next145 stat4 next suffixes' => static fn (TestRunner $t) => $t->same(['plugin_beta', 'plugin_epsilon', 'plugin_theta', null], array_column($plan145()['stat4CurrentSourceNextPairs'], 'nextSuffix')),
    'planner stat4 partial skipscan current source next145 stat4 range samples' => static fn (TestRunner $t) => $t->same([3, 2, 2, 1], array_column($plan145()['stat4CurrentSourceNextPairs'], 'rangeSamples')),
    'planner stat4 partial skipscan current source next145 estimates rows' => static fn (TestRunner $t) => $t->same(4, $plan145()['selectedPlan']['estimatedRows']),
    'planner stat4 partial skipscan current source next145 estimates cost' => static fn (TestRunner $t) => $t->same(40, $plan145()['selectedPlan']['estimatedCost']),
    'planner stat4 partial skipscan current source next145 keeps block sort evidence' => static fn (TestRunner $t) => $t->same(true, $plan145()['selectedPlan']['blockSortRequired']),
    'planner stat4 partial skipscan current source next145 sort break prefix' => static fn (TestRunner $t) => $t->same(['autoload'], $plan145()['selectedPlan']['sortBreakColumns']),
    'planner stat4 partial skipscan current source next145 covering true' => static fn (TestRunner $t) => $t->same(true, $plan145()['selectedPlan']['covering']),
    'planner stat4 partial skipscan current source next145 no table seek' => static fn (TestRunner $t) => $t->same(false, $plan145()['selectedPlan']['tableSeekRequired']),
    'planner stat4 partial skipscan current source next145 expression flag' => static fn (TestRunner $t) => $t->same(true, $plan145()['selectedPlan']['expressionPartialSkipScan']),
    'planner stat4 partial skipscan current source next145 next source admitted by default' => static fn (TestRunner $t) => $t->same(true, $plan145()['nextSourceAdmitted']),
    'planner stat4 partial skipscan current source next145 dependencies' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-partial-skipscan-current-source-next145'], $plan145()['dependencies']),
    'planner stat4 partial skipscan current source next145 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan145()['dependency_closure']),
    'planner stat4 partial skipscan current source next145 non overlap' => static fn (TestRunner $t) => $t->contains('per-prefix STAT4 skip-scan cursor programs', $plan145()['non_overlap']),
    'planner stat4 partial skipscan current source next145 detail' => static fn (TestRunner $t) => $t->contains('STAT4 CURRENT-SOURCE PREFIX PROGRAM next145', $plan145()['detail']),
    'planner stat4 partial skipscan current source next145 selected detail' => static fn (TestRunner $t) => $t->contains('CURRENT-SOURCE STAT4 PREFIX PROGRAM next145', $plan145()['selectedPlan']['detail']),
    'planner stat4 partial skipscan current source next145 loop signature present' => static fn (TestRunner $t) => $t->same(64, strlen($plan145()['currentSourceFence']['loopProgramSignature'])),
    'planner stat4 partial skipscan current source next145 payload signature present' => static fn (TestRunner $t) => $t->same(64, strlen($plan145()['currentSourceFence']['payloadSignature'])),
    'planner stat4 partial skipscan current source next145 stat4 pair signature present' => static fn (TestRunner $t) => $t->same(64, strlen($plan145()['currentSourceFence']['stat4PairSignature'])),
    'planner stat4 partial skipscan current source next145 predicate fence operator' => static fn (TestRunner $t) => $t->same('AND', $plan145()['currentSourceFence']['partialPredicateFence']['operator']),
];

$same145 = static fn (): array => $plan145($source145(), $source145());
$tests['planner stat4 partial skipscan current source next145 reuses identical prepared'] = static fn (TestRunner $t) => $t->same('prepared', $same145()['selectedSource']);
$tests['planner stat4 partial skipscan current source next145 identical rowids'] = static fn (TestRunner $t) => $t->same([1, 2, 4, 5, 7, 9], $same145()['selectedPlan']['rowids']);
$tests['planner stat4 partial skipscan current source next145 identical loop count'] = static fn (TestRunner $t) => $t->same(4, $same145()['selectedPlan']['skipScanLoopCount']);

$exclusive145 = static fn (): array => $plan145(null, $currentSource145(['upperBound' => 'plugin_theta', 'upperInclusive' => false]));
$tests['planner stat4 partial skipscan current source next145 exclusive upper opcode'] = static fn (TestRunner $t) => $t->same('IdxGE', $exclusive145()['prefixProgram'][0]['opcodes'][2]['opcode']);
$tests['planner stat4 partial skipscan current source next145 exclusive upper removes theta and zeta'] = static fn (TestRunner $t) => $t->same([1, 2, 4, 5, 7, 9], $exclusive145()['selectedPlan']['rowids']);
$tests['planner stat4 partial skipscan current source next145 exclusive payload removes theta and zeta'] = static fn (TestRunner $t) => $t->same(['plugin_alpha', 'Plugin_Beta', 'plugin_delta', 'plugin_epsilon', 'plugin_gamma', 'plugin_alpha'], array_column(array_column($exclusive145()['payloadRows'], 'payload'), 'option_name'));

$missingPartial145 = static fn (): array => $plan145(null, null, [$range145(['expression' => 'lower(option_name)'], '>=', 'plugin_')]);
$tests['planner stat4 partial skipscan current source next145 missing partial requires reprepare'] = static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $missingPartial145()['status']);
$tests['planner stat4 partial skipscan current source next145 missing partial has empty program'] = static fn (TestRunner $t) => $t->same([], $missingPartial145()['prefixProgram']);
$tests['planner stat4 partial skipscan current source next145 missing partial has null selected plan'] = static fn (TestRunner $t) => $t->same(null, $missingPartial145()['selectedPlan']);

$staleNext145 = static function () use ($currentSource145, $plan145): array {
    $next = $currentSource145(['schemaCookie' => 1452, 'stat4Generation' => 47]);
    $next['rows'][] = ['rowid' => 12, 'autoload' => 'yes', 'option_name' => 'plugin_omega', 'option_value' => 'a:9', 'kind' => 'plugin'];

    return $plan145(null, null, null, null, null, $next);
};
$tests['planner stat4 partial skipscan current source next145 stale next rejected'] = static fn (TestRunner $t) => $t->same(false, $staleNext145()['nextSourceAdmitted']);
$tests['planner stat4 partial skipscan current source next145 stale next status'] = static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $staleNext145()['status']);
$tests['planner stat4 partial skipscan current source next145 stale next keeps current program'] = static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($staleNext145()['prefixProgram'], 'prefix'));

$tests['planner stat4 partial skipscan current source next145 validates bad rows'] = static function (TestRunner $t) use ($currentSource145, $plan145): void {
    $bad = $currentSource145();
    $bad['rows'][] = 'bad';
    $t->throws(InvalidArgumentException::class, static fn () => $plan145(null, $bad));
};
$tests['planner stat4 partial skipscan current source next145 validates bad covering columns'] = static function (TestRunner $t) use ($currentSource145, $plan145): void {
    $bad = $currentSource145(['coveringColumns' => ['autoload', '']]);
    $t->throws(InvalidArgumentException::class, static fn () => $plan145(null, $bad));
};
$tests['planner stat4 partial skipscan current source next145 validates bad needed column'] = static function (TestRunner $t) use ($plan145): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan145(null, null, null, null, ['option_name', '']));
};
$tests['planner stat4 partial skipscan current source next145 validates bad upper inclusive'] = static function (TestRunner $t) use ($currentSource145, $plan145): void {
    $bad = $currentSource145(['upperInclusive' => 'yes']);
    $t->throws(InvalidArgumentException::class, static fn () => $plan145(null, $bad));
};

return $tests;
