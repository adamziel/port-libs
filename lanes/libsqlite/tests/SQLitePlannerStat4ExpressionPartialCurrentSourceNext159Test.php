<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext159Plan;

$rows159 = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin', '__expr_lower_option_name' => 'plugin_alpha'],
    ['rowid' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Beta', 'option_value' => 'a:2', 'kind' => 'plugin', '__expr_lower_option_name' => 'plugin_beta'],
    ['rowid' => 3, 'autoload' => 'yes', 'option_name' => 'admin_email', 'option_value' => 'owner@example.test', 'kind' => 'core', '__expr_lower_option_name' => 'admin_email'],
    ['rowid' => 4, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'option_value' => 'a:3', 'kind' => 'plugin', '__expr_lower_option_name' => 'plugin_gamma'],
    ['rowid' => 5, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'empty', 'kind' => 'plugin', '__expr_lower_option_name' => null],
];

$currentRows159 = static function () use ($rows159): array {
    $rows = $rows159();
    $rows[] = ['rowid' => 6, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'a:4', 'kind' => 'plugin', '__expr_lower_option_name' => 'plugin_cache'];
    $rows[] = ['rowid' => 7, 'autoload' => 'yes', 'option_name' => 'plugin_security', 'option_value' => 'a:5', 'kind' => 'plugin', '__expr_lower_option_name' => 'plugin_security'];
    $rows[] = ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'x', 'kind' => 'theme', '__expr_lower_option_name' => 'theme_mods'];

    return $rows;
};

$stat159 = static fn (): array => [
    ['key' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0, 'rowid' => 1],
    ['key' => 'plugin_beta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1, 'rowid' => 2],
];

$currentStat159 = static fn (): array => [
    ['key' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0, 'rowid' => 1],
    ['key' => 'plugin_beta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1, 'rowid' => 2],
    ['key' => 'plugin_cache', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2, 'rowid' => 6],
    ['key' => 'plugin_security', 'nEq' => 1, 'nLt' => 3, 'nDLt' => 3, 'rowid' => 7],
];

$source159 = static function (array $overrides = []) use ($rows159, $stat159): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@1590',
        'schemaCookie' => 1590,
        'stat4Generation' => 59,
        'indexName' => 'idx_wp_options_lower_name_yes_plugin_next159',
        'rootPage' => 15901,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'coveringColumns' => ['option_name', 'autoload'],
        'rows' => $rows159(),
        'stat4Samples' => $stat159(),
    ];
};

$currentSource159 = static function (array $overrides = []) use ($currentRows159, $currentStat159): array {
    return $overrides + [
        'name' => 'current-main.wp_options@1591',
        'schemaCookie' => 1591,
        'stat4Generation' => 60,
        'indexName' => 'idx_wp_options_lower_name_yes_plugin_next159',
        'rootPage' => 15911,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'coveringColumns' => ['option_name', 'autoload'],
        'rows' => $currentRows159(),
        'stat4Samples' => $currentStat159(),
    ];
};

$partial159 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('autoload', SQLiteIndexPredicate::EQUALS, 'yes'),
    new SQLiteIndexPredicate('__expr_lower_option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$point159 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$notNull159 = static fn (string $column): array => ['operator' => 'IS NOT NULL', 'left' => ['column' => $column]];
$range159 = static fn (string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['expression' => 'lower(option_name)'], 'right' => $value];
$between159 = static fn (mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['expression' => 'lower(option_name)'], 'lower' => $lower, 'upper' => $upper];
$query159 = [
    $point159('autoload', 'yes'),
    $notNull159('__expr_lower_option_name'),
    $range159('>=', 'plugin_'),
    $range159('<', 'plugin_t'),
];
$needed159 = ['option_name', 'option_value'];

$plan159 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $query = null,
    ?array $needed = null,
    ?array $next = null,
): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext159Plan::materialize(
    $prepared ?? $source159(),
    $current ?? $currentSource159(),
    $partial159,
    $query ?? $query159,
    $needed ?? $needed159,
    $next,
);

$tests = [
    'planner stat4 expression partial current source next159 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next159-ready', $plan159()['status']),
    'planner stat4 expression partial current source next159 selects current' => static fn (TestRunner $t) => $t->same('current', $plan159()['selectedSource']),
    'planner stat4 expression partial current source next159 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan159()['stalePreparedStatement']),
    'planner stat4 expression partial current source next159 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan159()['reprepareRequired']),
    'planner stat4 expression partial current source next159 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan159()['schemaCookieChanged']),
    'planner stat4 expression partial current source next159 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan159()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next159 index signature changed' => static fn (TestRunner $t) => $t->same(true, $plan159()['indexSignatureChanged']),
    'planner stat4 expression partial current source next159 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_yes_plugin_next159', $plan159()['selectedPlan']['indexName']),
    'planner stat4 expression partial current source next159 selected root' => static fn (TestRunner $t) => $t->same(15911, $plan159()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next159 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan159()['selectedPlan']['expression']),
    'planner stat4 expression partial current source next159 expression column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan159()['selectedPlan']['expressionColumn']),
    'planner stat4 expression partial current source next159 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan159()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 expression partial current source next159 covering false' => static fn (TestRunner $t) => $t->same(false, $plan159()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next159 table lookup required' => static fn (TestRunner $t) => $t->same(true, $plan159()['selectedPlan']['tableLookupRequired']),
    'planner stat4 expression partial current source next159 missing option value' => static fn (TestRunner $t) => $t->same(['option_value'], $plan159()['selectedPlan']['missingCoveringColumns']),
    'planner stat4 expression partial current source next159 rowids' => static fn (TestRunner $t) => $t->same([1, 2, 6, 7], $plan159()['selectedPlan']['rowids']),
    'planner stat4 expression partial current source next159 prepared rowids' => static fn (TestRunner $t) => $t->same([1, 2], $plan159()['preparedPlan']['rowids']),
    'planner stat4 expression partial current source next159 current rowids' => static fn (TestRunner $t) => $t->same([1, 2, 6, 7], $plan159()['currentPlan']['rowids']),
    'planner stat4 expression partial current source next159 range lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan159()['selectedPlan']['rangeLower']),
    'planner stat4 expression partial current source next159 range upper' => static fn (TestRunner $t) => $t->same('plugin_t', $plan159()['selectedPlan']['rangeUpper']),
    'planner stat4 expression partial current source next159 lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan159()['selectedPlan']['lowerInclusive']),
    'planner stat4 expression partial current source next159 upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan159()['selectedPlan']['upperInclusive']),
    'planner stat4 expression partial current source next159 stat4 sample count' => static fn (TestRunner $t) => $t->same(4, $plan159()['selectedPlan']['stat4SampleCount']),
    'planner stat4 expression partial current source next159 stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_beta', 'plugin_cache', 'plugin_security'], array_column($plan159()['selectedPlan']['stat4Samples'], 'key')),
    'planner stat4 expression partial current source next159 estimated rows' => static fn (TestRunner $t) => $t->same(4, $plan159()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next159 estimated cost includes lookup' => static fn (TestRunner $t) => $t->same(19, $plan159()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial current source next159 program opens current root' => static fn (TestRunner $t) => $t->same(['opcode' => 'OpenRead', 'rootPage' => 15911, 'indexName' => 'idx_wp_options_lower_name_yes_plugin_next159'], $plan159()['yieldProgram'][0]),
    'planner stat4 expression partial current source next159 program seek ge' => static fn (TestRunner $t) => $t->same('SeekGE', $plan159()['yieldProgram'][1]['opcode']),
    'planner stat4 expression partial current source next159 program seek value' => static fn (TestRunner $t) => $t->same('plugin_', $plan159()['yieldProgram'][1]['value']),
    'planner stat4 expression partial current source next159 program stop exclusive' => static fn (TestRunner $t) => $t->same('IdxGE', $plan159()['yieldProgram'][2]['opcode']),
    'planner stat4 expression partial current source next159 program reads covering columns' => static fn (TestRunner $t) => $t->same(['option_name', 'autoload'], $plan159()['yieldProgram'][3]['columns']),
    'planner stat4 expression partial current source next159 program defers table seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $plan159()['yieldProgram'][4]['opcode']),
    'planner stat4 expression partial current source next159 program next rowids' => static fn (TestRunner $t) => $t->same([1, 2, 6, 7], $plan159()['yieldProgram'][5]['rowids']),
    'planner stat4 expression partial current source next159 covering rows omit missing' => static fn (TestRunner $t) => $t->same(['option_name' => 'plugin_alpha'], array_intersect_key($plan159()['coveringRows'][0]['payload'], ['option_name' => true])),
    'planner stat4 expression partial current source next159 covering row omits table column' => static fn (TestRunner $t) => $t->same(false, array_key_exists('option_value', $plan159()['coveringRows'][0]['payload'])),
    'planner stat4 expression partial current source next159 table lookup row value' => static fn (TestRunner $t) => $t->same('a:4', $plan159()['tableLookupRows'][2]['payload']['option_value']),
    'planner stat4 expression partial current source next159 table lookup row count' => static fn (TestRunner $t) => $t->same(4, count($plan159()['tableLookupRows'])),
    'planner stat4 expression partial current source next159 stat4 pair count' => static fn (TestRunner $t) => $t->same(4, count($plan159()['stat4YieldPairs'])),
    'planner stat4 expression partial current source next159 first stat4 next' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan159()['stat4YieldPairs'][0]['next']['key']),
    'planner stat4 expression partial current source next159 final stat4 eof' => static fn (TestRunner $t) => $t->same(null, $plan159()['stat4YieldPairs'][3]['next']),
    'planner stat4 expression partial current source next159 fence cookie' => static fn (TestRunner $t) => $t->same(1591, $plan159()['currentSourceFence']['schemaCookie']),
    'planner stat4 expression partial current source next159 fence stat4' => static fn (TestRunner $t) => $t->same(60, $plan159()['currentSourceFence']['stat4Generation']),
    'planner stat4 expression partial current source next159 fence rowset signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan159()['currentSourceFence']['rowsetSignature'])),
    'planner stat4 expression partial current source next159 fence stat4 signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan159()['currentSourceFence']['stat4Signature'])),
    'planner stat4 expression partial current source next159 fence program signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan159()['currentSourceFence']['programSignature'])),
    'planner stat4 expression partial current source next159 next admitted by default' => static fn (TestRunner $t) => $t->same(true, $plan159()['nextSourceAdmitted']),
    'planner stat4 expression partial current source next159 dependencies' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next159'], $plan159()['dependencies']),
    'planner stat4 expression partial current source next159 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan159()['dependency_closure']),
    'planner stat4 expression partial current source next159 non overlap' => static fn (TestRunner $t) => $t->contains('non-skip-scan STAT4 expression partial current-source yield boundary', $plan159()['non_overlap']),
    'planner stat4 expression partial current source next159 detail' => static fn (TestRunner $t) => $t->contains('STAT4 PARTIAL EXPRESSION CURRENT-SOURCE YIELD next159', $plan159()['detail']),
];

$fresh159 = static fn (): array => $plan159($source159(), $source159(['name' => 'current-same-next159']));
$tests['planner stat4 expression partial current source next159 fresh selects prepared'] = static fn (TestRunner $t) => $t->same('prepared', $fresh159()['selectedSource']);
$tests['planner stat4 expression partial current source next159 fresh no reprepare'] = static fn (TestRunner $t) => $t->same(false, $fresh159()['reprepareRequired']);
$tests['planner stat4 expression partial current source next159 fresh rowids'] = static fn (TestRunner $t) => $t->same([1, 2], $fresh159()['selectedPlan']['rowids']);

$covering159 = static fn (): array => $plan159(null, null, null, ['option_name', 'autoload']);
$tests['planner stat4 expression partial current source next159 covering plan true'] = static fn (TestRunner $t) => $t->same(true, $covering159()['selectedPlan']['covering']);
$tests['planner stat4 expression partial current source next159 covering no table lookup'] = static fn (TestRunner $t) => $t->same([], $covering159()['tableLookupRows']);
$tests['planner stat4 expression partial current source next159 covering opcode no table seek'] = static fn (TestRunner $t) => $t->same('NoTableSeek', $covering159()['yieldProgram'][4]['opcode']);

$betweenPlan159 = static fn (): array => $plan159(null, null, [$point159('autoload', 'yes'), $notNull159('__expr_lower_option_name'), $between159('plugin_beta', 'plugin_security')]);
$tests['planner stat4 expression partial current source next159 between rowids'] = static fn (TestRunner $t) => $t->same([2, 6, 7], $betweenPlan159()['selectedPlan']['rowids']);
$tests['planner stat4 expression partial current source next159 between upper inclusive opcode'] = static fn (TestRunner $t) => $t->same('IdxGT', $betweenPlan159()['yieldProgram'][2]['opcode']);
$tests['planner stat4 expression partial current source next159 between stat4 keys'] = static fn (TestRunner $t) => $t->same(['plugin_beta', 'plugin_cache', 'plugin_security'], array_column($betweenPlan159()['selectedPlan']['stat4Samples'], 'key'));

$missingPartial159 = static fn (): array => $plan159(null, null, [$notNull159('__expr_lower_option_name'), $range159('>=', 'plugin_')]);
$tests['planner stat4 expression partial current source next159 missing partial blocked'] = static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $missingPartial159()['status']);
$tests['planner stat4 expression partial current source next159 missing partial unusable'] = static fn (TestRunner $t) => $t->same(false, $missingPartial159()['selectedPlan']['usable']);
$tests['planner stat4 expression partial current source next159 missing partial empty program'] = static fn (TestRunner $t) => $t->same([], $missingPartial159()['yieldProgram']);

$nextChanged159 = static function () use ($currentSource159, $plan159): array {
    return $plan159(null, null, null, null, $currentSource159(['schemaCookie' => 1592]));
};
$tests['planner stat4 expression partial current source next159 next changed rejected'] = static fn (TestRunner $t) => $t->same(false, $nextChanged159()['nextSourceAdmitted']);
$tests['planner stat4 expression partial current source next159 next changed status'] = static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $nextChanged159()['status']);
$tests['planner stat4 expression partial current source next159 next changed still has current rowids'] = static fn (TestRunner $t) => $t->same([1, 2, 6, 7], $nextChanged159()['selectedPlan']['rowids']);

$tests['planner stat4 expression partial current source next159 validates bad row list'] = static function (TestRunner $t) use ($currentSource159, $plan159): void {
    $bad = $currentSource159();
    $bad['rows'][] = 'bad';
    $t->throws(InvalidArgumentException::class, static fn () => $plan159(null, $bad));
};
$tests['planner stat4 expression partial current source next159 validates needed columns'] = static function (TestRunner $t) use ($plan159): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan159(null, null, null, ['option_name', '']));
};
$tests['planner stat4 expression partial current source next159 validates schema cookie'] = static function (TestRunner $t) use ($source159, $currentSource159, $partial159, $query159, $needed159): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext159Plan::materialize($source159(['schemaCookie' => -1]), $currentSource159(), $partial159, $query159, $needed159));
};
$tests['planner stat4 expression partial current source next159 validates query term operator'] = static function (TestRunner $t) use ($plan159): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan159(null, null, [['left' => ['expression' => 'lower(option_name)']]]));
};

return $tests;
